
# Summary: What does all this do?
# End-to-end pipeline for privacy data discovery in any MySQL DB:
# Connects with limited READ credentials,
# Enumerates all tables/columns,
# Uses LLM to classify only those columns likely to contain user/PII for DSR,
# Persists the discovery summary (privacy_map.json) in /Databases/{id}/,
# When a DSR request comes in, loads prior scan (the .json) and searches for the data subject only in the LLM-identified columns, returning a concise list of tables/rows for DBA review.
# No privacy data is deleted/anonymized by Python—just reported!
# Scales to as many DBs as needed (by ID).
# Secure, auditable, and easily integrated with Laravel UI/workflow.

# uvicorn db_privacy_discover:app --host 0.0.0.0 --port 8205

#sudo systemctl daemon-reload
#sudo systemctl enable prod_db_privacy_discovery_service
#sudo systemctl start prod_db_privacy_discovery_service
#sudo systemctl status prod_db_privacy_discovery_service
#sudo lsof -i :8205
#sudo systemctl restart prod_db_privacy_discovery_service
#sudo tail -n 50 /var/log/prod_db_privacy_discovery_service_error.log

import os
import json
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import List, Dict, Any
from tqdm import tqdm
import re

# LLM client. Adapt to your provider!
from config import client

DATABASE_BASE = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/Databases"
app = FastAPI(title="Universal Database Privacy Discovery API")



class DBDiscoveryRequest(BaseModel):
    id: str                   # unique config/db id
    db_type: str              # "mysql", "mariadb", "oracle", "sqlserver", "odbc", etc
    host: str
    port: int
    user: str
    password: str
    database: str = ""        # optional for some data warehouses
    user_search_value: str = ""

class DBDiscoverResult(BaseModel):
    table: str
    column: str
    field_type: str
    confidence: str

class DBUserDSRFinding(BaseModel):
    table: str
    column: str
    field_type: str
    confidence: str
    matched_rows: list

def ensure_dest_folder(db_id: str):
    db_folder = os.path.join(DATABASE_BASE, str(db_id))
    os.makedirs(db_folder, exist_ok=True)
    return db_folder

# --- DB connection adapters ---

def get_connection(cfg: DBDiscoveryRequest):
    db_type = cfg.db_type.lower()
    if db_type in ("mysql", "mariadb"):
        import mysql.connector
        return mysql.connector.connect(
            host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password, database=cfg.database
        )
    elif db_type == "oracle":
        import oracledb
        # Oracle connection string: user/pwd@host:port/service
        dsn = f"{cfg.host}:{cfg.port}/{cfg.database}"
        return oracledb.connect(user=cfg.user, password=cfg.password, dsn=dsn)
    elif db_type in ("sqlserver", "mssql", "odbc", "fabric"):
        # Try ODBC string (assumes msodbc driver is installed on OS)
        import pyodbc
        # For MS Fabric, set proper driver and conn string.
        if db_type == "fabric":
            driver = "{ODBC Driver 18 for SQL Server}"
        else:
            driver = "{ODBC Driver 17 for SQL Server}"
        conn_str = (
            f"DRIVER={driver};SERVER={cfg.host},{cfg.port};"
            f"DATABASE={cfg.database};UID={cfg.user};PWD={cfg.password};TrustServerCertificate=yes"
        )
        return pyodbc.connect(conn_str, autocommit=True)
    else:
        raise HTTPException(status_code=400, detail=f"Unsupported db_type: {db_type}")

def describe_tables(conn, db_type: str) -> List[Dict]:
    """Returns [{table: '...', columns: [{name, type}]}] for any RDBMS."""
    tables = []
    cur = conn.cursor()
    if db_type in ("mysql", "mariadb"):
        cur.execute("SHOW TABLES")
        all_tables = [row[0] for row in cur.fetchall()]
        for t in all_tables:
            cur.execute(f"DESCRIBE `{t}`")
            columns = [{"name": row[0], "type": row[1]} for row in cur.fetchall()]
            tables.append({"table": t, "columns": columns})
    elif db_type == "oracle":
        cur.execute("SELECT table_name FROM user_tables")
        all_tables = [row[0] for row in cur.fetchall()]
        for t in all_tables:
            cur.execute(f"SELECT column_name, data_type FROM user_tab_columns WHERE table_name = :tbl", [t])
            columns = [{"name": row[0], "type": row[1]} for row in cur.fetchall()]
            tables.append({"table": t, "columns": columns})
    elif db_type in ("sqlserver", "mssql", "odbc", "fabric"):
        # SQL Server, Microsoft Fabric, or generic warehouse with ODBC
        cur.execute("SELECT table_name FROM INFORMATION_SCHEMA.TABLES WHERE table_type='BASE TABLE'")
        all_tables = [row[0] for row in cur.fetchall()]
        for t in all_tables:
            cur.execute(f"SELECT column_name, data_type FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = ?", (t,))
            columns = [{"name": row[0], "type": row[1]} for row in cur.fetchall()]
            tables.append({"table": t, "columns": columns})
    cur.close()
    return tables

def sample_column(conn, db_type, table, column, n=8):
    cur = conn.cursor()
    sql = ""
    try:
        if db_type == "oracle":
            sql = f'SELECT "{column}" FROM "{table}" WHERE "{column}" IS NOT NULL AND ROWNUM <= {n}'
        else:
            sql = f"SELECT [{column}] FROM [{table}] WHERE [{column}] IS NOT NULL"
            if db_type in ("mysql", "mariadb"):
                sql = f"SELECT `{column}` FROM `{table}` WHERE `{column}` IS NOT NULL LIMIT {n}"
            elif db_type in ("sqlserver", "mssql", "odbc", "fabric"):
                sql = f"SELECT TOP {n} [{column}] FROM [{table}] WHERE [{column}] IS NOT NULL"
        cur.execute(sql)
        return [str(r[0]) for r in cur.fetchmany(size=n) if r and r[0] is not None]
    except Exception:
        return []
    finally:
        cur.close()

def llm_classify_column(column_name, column_type, samples, table):
    prompt = (
        f"Table: {table}\n"
        f"Column: {column_name}\n"
        f"Type: {column_type}\n"
        f"Sample data: {json.dumps(samples)}\n"
        "Does this column store regulated personal/user/PII/PIA/PHI or privacy-relevant data? "
        "If yes, what type specifically (e.g. email, name, phone, id, health data)? "
        "Is this a likely search target for a user's data privacy request? "
        "Answer as: {'is_user_column': bool, 'field_type': str, 'confidence': 'high/medium/low'}"
    )
    resp = client.chat.completions.create(
        model="gpt-4.1",
        messages=[
            {"role": "system", "content": "You are a data privacy analyst. Be accurate, minimize false positives."},
            {"role": "user", "content": prompt}
        ],
        temperature=0.1,
    )
    import ast
    try:
        meta = ast.literal_eval(resp.choices[0].message.content)
    except Exception:
        meta = {"is_user_column": False, "field_type": "unknown", "confidence": "low"}
    return meta

def discover_columns(conn, db_type: str):
    columns_of_interest = []
    for tbl in tqdm(describe_tables(conn, db_type)):
        for col in tbl['columns']:
            samples = sample_column(conn, db_type, tbl['table'], col['name'])
            meta = llm_classify_column(col['name'], col['type'], samples, tbl['table'])
            if meta.get('is_user_column', False):
                columns_of_interest.append({
                    "table": tbl['table'],
                    "column": col['name'],
                    "field_type": meta.get('field_type', 'unknown'),
                    "confidence": meta.get('confidence', 'low')
                })
    return columns_of_interest

def find_user_matches(conn, db_type, columns_of_interest, user_value, row_limit=10):
    out = []
    for col_info in columns_of_interest:
        table = col_info['table']
        column = col_info['column']
        cur = conn.cursor()
        try:
            if db_type in ("oracle"):
                sql = f'SELECT * FROM "{table}" WHERE "{column}" = :val AND ROWNUM <= :n'
                cur.execute(sql, val=user_value, n=row_limit)
            elif db_type in ("mysql", "mariadb"):
                sql = f"SELECT * FROM `{table}` WHERE `{column}` = %s LIMIT %s"
                cur.execute(sql, (user_value, row_limit))
            else: # SQL Server/MSSQL/ODBC/Fabric
                sql = f"SELECT TOP {row_limit} * FROM [{table}] WHERE [{column}] = ?"
                cur.execute(sql, (user_value,))
            rows = cur.fetchmany(size=row_limit)
            if rows:
                out.append({
                    "table": table,
                    "column": column,
                    "field_type": col_info['field_type'],
                    "confidence": col_info['confidence'],
                    "matched_rows": rows
                })
        except Exception:
            continue
        finally:
            cur.close()
    return out


@app.post("/db/discover-columns", response_model=List[DBDiscoverResult])
def db_discover_columns(req: DBDiscoveryRequest):
    db_folder = ensure_dest_folder(req.id)
    db_name_part = req.database if req.database else "default"
    safe_db_name = re.sub(r'[^a-zA-Z0-9_\-]', '_', db_name_part)
    schemafile = os.path.join(db_folder, f"privacy_map_{safe_db_name}.json")
    conn = None
    try:
        conn = get_connection(req)
        columns_of_interest = discover_columns(conn, req.db_type)
        with open(schemafile, "w", encoding="utf-8") as f:
            json.dump(columns_of_interest, f, indent=2)
    except Exception as ex:
        raise HTTPException(status_code=400, detail=f"Failed to scan: {ex}")
    finally:
        if conn:
            conn.close()
    return columns_of_interest

@app.post("/db/find-user", response_model=List[DBUserDSRFinding])
def db_find_user(req: DBDiscoveryRequest):
    db_folder = ensure_dest_folder(req.id)
    db_name_part = req.database if req.database else "default"
    safe_db_name = re.sub(r'[^a-zA-Z0-9_\-]', '_', db_name_part)
    schemafile = os.path.join(db_folder, f"privacy_map_{safe_db_name}.json")
    if not os.path.isfile(schemafile):
        raise HTTPException(status_code=400, detail=f"privacy_map_{db_name_part}.json does not exist for this DB id. Please run /db/discover-columns first.")
    try:
        with open(schemafile, "r", encoding="utf-8") as f:
            columns_of_interest = json.load(f)
    except Exception as ex:
        raise HTTPException(status_code=400, detail=f"Could not read privacy_map_{db_name_part}.json: {ex}")
    conn = None
    try:
        conn = get_connection(req)
        findings = find_user_matches(conn, req.db_type, columns_of_interest, req.user_search_value)
        return findings
    finally:
        if conn:
            conn.close()


