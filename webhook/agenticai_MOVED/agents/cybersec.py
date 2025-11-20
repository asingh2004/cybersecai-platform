

import os
import re
import json
from collections import defaultdict
from datetime import datetime
from typing import List, Tuple

from config import client
from utils.logging import log_to_laravel

# Try DB drivers
_DB_BACKEND = None
try:
    import pymysql  # type: ignore
    from pymysql.cursors import DictCursor  # type: ignore
    _DB_BACKEND = "pymysql"
except Exception:
    try:
        import mysql.connector  # type: ignore
        _DB_BACKEND = "mysql-connector"
    except Exception:
        _DB_BACKEND = None


# ---- .env loader (Laravel) --------------------------------------------------

def _manual_load_env(path: str) -> bool:
    try:
        with open(path, 'r', encoding='utf-8') as f:
            for raw in f:
                line = raw.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                key = k.strip()
                val = v.strip()
                if key.lower().startswith("export "):
                    key = key[7:].strip()
                if (val.startswith('"') and val.endswith('"')) or (val.startswith("'") and val.endswith("'")):
                    val = val[1:-1]
                if key and os.getenv(key) is None:
                    os.environ[key] = val
        return True
    except Exception:
        return False


_DB_ENV_LOGGED = False

def _ensure_env_loaded():
    global _DB_ENV_LOGGED
    if (
        os.getenv("DB_HOST")
        and os.getenv("DB_DATABASE")
        and os.getenv("DB_USERNAME")
        and os.getenv("DB_PASSWORD") not in (None, "")
    ):
        if not _DB_ENV_LOGGED:
            try:
                log_to_laravel("DB_ENV_OK", {
                    "host": os.getenv("DB_HOST"),
                    "database": os.getenv("DB_DATABASE"),
                    "backend": _DB_BACKEND or "none"
                })
            except Exception:
                pass
            _DB_ENV_LOGGED = True
        return

    loaded = False
    dotenv_path = os.getenv("LARAVEL_ENV_PATH") or os.getenv("DOTENV_PATH")
    if dotenv_path:
        try:
            from dotenv import load_dotenv
            loaded = load_dotenv(dotenv_path, override=False)
        except Exception:
            loaded = _manual_load_env(dotenv_path)

    if not loaded:
        try:
            from dotenv import load_dotenv, find_dotenv
            found = find_dotenv(filename=".env", usecwd=True)
            if found:
                loaded = load_dotenv(found, override=False)
        except Exception:
            loaded = False

    if not loaded:
        candidates = []
        try:
            candidates.append(os.path.join(os.getcwd(), ".env"))
        except Exception:
            pass
        d = os.path.abspath(os.path.dirname(__file__))
        for _ in range(6):
            candidates.append(os.path.join(d, ".env"))
            d = os.path.dirname(d)
        candidates.append("/home/cybersecai/htdocs/www.cybersecai.io/.env")
        for p in candidates:
            if os.path.isfile(p) and _manual_load_env(p):
                loaded = True
                break

    try:
        log_to_laravel("DB_ENV_LOAD", {
            "loaded": bool(loaded),
            "host": os.getenv("DB_HOST"),
            "database": os.getenv("DB_DATABASE"),
            "backend": _DB_BACKEND or "none"
        })
    except Exception:
        pass
    _DB_ENV_LOGGED = True


# ---- DB helpers -------------------------------------------------------------

def _db_connect():
    _ensure_env_loaded()
    host = os.getenv("DB_HOST", "127.0.0.1")
    try:
        port = int(os.getenv("DB_PORT", "3306") or "3306")
    except Exception:
        port = 3306
    user = os.getenv("DB_USERNAME", "root")
    password = os.getenv("DB_PASSWORD", "")
    database = os.getenv("DB_DATABASE", "cybersecai")

    try:
        if _DB_BACKEND == "pymysql":
            conn = pymysql.connect(
                host=host,
                port=port,
                user=user,
                password=password,
                database=database,
                charset="utf8mb4",
                cursorclass=DictCursor,
                autocommit=True
            )
        elif _DB_BACKEND == "mysql-connector":
            conn = mysql.connector.connect(
                host=host,
                port=port,
                user=user,
                password=password,
                database=database
            )
        else:
            raise RuntimeError("No MySQL driver available (install PyMySQL or mysql-connector-python).")
        try:
            log_to_laravel("DB_CONNECT_SUCCESS", {
                "host": host, "database": database, "backend": _DB_BACKEND
            })
        except Exception:
            pass
        return conn
    except Exception as e:
        try:
            log_to_laravel("DB_CONNECT_ERROR", {"error": repr(e), "host": host, "database": database, "backend": _DB_BACKEND})
        except Exception:
            pass
        raise


def _fetchall(sql, params=None):
    params = params or ()
    conn = _db_connect()
    try:
        cur = conn.cursor()
        cur.execute(sql, params)
        rows = cur.fetchall()
        if _DB_BACKEND == "mysql-connector":
            cols = [d[0] for d in cur.description]
            out = []
            for r in rows:
                if isinstance(r, dict):
                    out.append(r)
                else:
                    out.append({cols[i]: r[i] for i in range(len(cols))})
            return out
        else:
            return list(rows)
    finally:
        try:
            conn.close()
        except Exception:
            pass


def _fetchone(sql, params=None):
    rows = _fetchall(sql, params)
    return rows[0] if rows else None


# ---- Utility helpers --------------------------------------------------------

def _iso(dt):
    if not dt:
        return ""
    if isinstance(dt, str):
        return dt
    try:
        if isinstance(dt, datetime):
            return dt.isoformat()
    except Exception:
        pass
    return str(dt)

def _backend_from_storage_type(storage_type: str) -> str:
    st = (storage_type or "").lower()
    if st in ("onedrive", "sharepoint"):
        return "M365"
    if st == "smb":
        return "SMB"
    if st in ("aws_s3", "s3"):
        return "S3"
    return (storage_type or "").upper() or "Unknown"

def _to_datasource_lower(backend_source: str) -> str:
    return (backend_source or "").lower()

def _coalesce(*vals):
    for v in vals:
        if v not in (None, "", b""):
            return v
    return None

def _in_clause_placeholders(n: int) -> str:
    if n <= 0:
        return "(NULL)"
    return "(" + ",".join(["%s"] * n) + ")"

def _to_str(value) -> str:
    """Safe stringify; handles lists/dicts before applying strip."""
    if value is None:
        return ""
    if isinstance(value, (list, dict)):
        try:
            return json.dumps(value, ensure_ascii=False)
        except Exception:
            return str(value)
    try:
        return str(value)
    except Exception:
        return ""

def _to_str_strip(value) -> str:
    return _to_str(value).strip()

def _to_str_lower(value) -> str:
    return _to_str(value).strip().lower()


# ---- Config IDs handling (config_id == files.user_id) -----------------------

def _ensure_config_ids(config_ids=None) -> List[int]:
    """
    Ensure we have config_ids (as ints). We do NOT derive them here.
    """
    ids = [int(x) for x in (config_ids or []) if str(x).strip().isdigit()]
    try:
        log_to_laravel("SCOPE_CONFIG_IDS", {"config_ids": ids})
    except Exception:
        pass
    return ids


def _build_scope_where(alias: str, config_ids: List[int]) -> Tuple[str, List[int]]:
    """
    Strict scoping by files.user_id IN (:config_ids), per your clarified logic.
    """
    if not config_ids:
        return "1=0", []
    ph = _in_clause_placeholders(len(config_ids))
    where = f"({alias}.user_id IN {ph})"
    try:
        log_to_laravel("SCOPE_WHERE_BUILT", {"where": where, "config_ids": config_ids})
    except Exception:
        pass    # do not raise on logging
    return where, config_ids


# ---- Public data accessors --------------------------------------------------

def fetch_findings(user_id=None, config_ids=None, risk_filter=None):
    """
    Returns list of dict rows describing files and their latest AI analysis and permissions/compliance.
    Scope: strictly files.user_id IN (:config_ids).
    user_id is not used for scoping (only logged).
    """
    cfg_ids = _ensure_config_ids(config_ids=config_ids)
    scope_where, scope_params = _build_scope_where("f", cfg_ids)
    if scope_where == "1=0":
        try:
            log_to_laravel("FETCH_FINDINGS_EMPTY_SCOPE", {"user_id": user_id, "config_ids": cfg_ids})
        except Exception:
            pass
        return []

    where = [scope_where]
    params = list(scope_params)
    if risk_filter:
        where.append("a.overall_risk_rating = %s")
        params.append(_to_str(risk_filter).title())

    sql = f"""
    SELECT
        f.id AS file_id, f.user_id, f.business_id, f.storage_type, f.source_file_id,
        f.file_name, f.file_type, f.file_extension, f.size_bytes, f.last_modified AS f_last_modified,
        f.full_path AS f_full_path, f.web_url AS f_web_url, f.download_url AS f_download_url,
        f.created_at AS f_created_at,
        s3.bucket, s3.s3_key, s3.full_path AS s3_full_path, s3.last_modified AS s3_last_modified,
        smb.server, smb.share, smb.file_path AS smb_file_path, smb.full_path AS smb_full_path, smb.created AS smb_created, smb.last_modified AS smb_last_modified,
        od.drive_file_id AS od_drive_file_id, od.owner_user_object_id, od.owner_display_name, od.owner_email,
        od.parent_reference AS od_parent_reference, od.web_url AS od_web_url, od.download_url AS od_download_url,
        sp.site_id, sp.drive_id, sp.drive_file_id AS sp_drive_file_id, sp.parent_reference AS sp_parent_reference,
        sp.web_url AS sp_web_url, sp.download_url AS sp_download_url,
        a.id AS analysis_id, a.auditor_agent_view, a.likely_data_subject_area, a.data_classification, a.overall_risk_rating,
        a.hacker_interest, a.auditor_proposed_action, a.raw_json
    FROM files f
    LEFT JOIN s3_files s3 ON s3.file_id = f.id
    LEFT JOIN smb_files smb ON smb.file_id = f.id
    LEFT JOIN onedrive_files od ON od.file_id = f.id
    LEFT JOIN sharepoint_files sp ON sp.file_id = f.id
    LEFT JOIN (
        SELECT a1.*
        FROM file_ai_analyses a1
        JOIN (
            SELECT file_id, MAX(id) AS max_id
            FROM file_ai_analyses
            GROUP BY file_id
        ) x ON x.max_id = a1.id
    ) a ON a.file_id = f.id
    WHERE {" AND ".join(where)}
    """
    try:
        log_to_laravel("FETCH_FINDINGS_SCOPE", {
            "user_id": user_id, "config_ids": cfg_ids, "risk_filter": risk_filter or "Any",
            "where": " AND ".join(where)
        })
    except Exception:
        pass

    rows = _fetchall(sql, params)
    try:
        log_to_laravel("FETCH_FINDINGS_RESULT", {"count": len(rows)})
    except Exception:
        pass

    # collect ids for subsequent queries
    file_ids = [r["file_id"] for r in rows if r.get("file_id") is not None]
    analysis_ids = [r["analysis_id"] for r in rows if r.get("analysis_id") is not None]

    # permissions
    perms_by_file = defaultdict(list)
    if file_ids:
        ph = _in_clause_placeholders(len(file_ids))
        perms_sql = f"""
        SELECT file_id, role, principal_type, principal_display_name, principal_email, principal_id,
               provider_permission_id, provider_share_id, source
        FROM file_permissions
        WHERE file_id IN {ph}
        """
        perms_rows = _fetchall(perms_sql, file_ids)
        for p in perms_rows:
            perms_by_file[p["file_id"]].append({
                "role": p.get("role"),
                "principal_type": p.get("principal_type"),
                "principal_display_name": p.get("principal_display_name"),
                "principal_email": p.get("principal_email"),
                "principal_id": p.get("principal_id"),
                "provider_permission_id": p.get("provider_permission_id"),
                "provider_share_id": p.get("provider_share_id"),
                "source": p.get("source"),
            })
        try:
            log_to_laravel("FETCH_PERMS_RESULT", {"files": len(file_ids), "perms_rows": len(perms_rows)})
        except Exception:
            pass

    # compliance findings + detected fields
    cfinds_by_analysis = defaultdict(list)
    if analysis_ids:
        ph = _in_clause_placeholders(len(analysis_ids))
        find_sql = f"""
        SELECT fi.id AS finding_id, fi.analysis_id, fi.standard, fi.jurisdiction, fi.risk,
               df.field_name
        FROM file_ai_findings fi
        LEFT JOIN file_ai_finding_detected_fields df ON df.finding_id = fi.id
        WHERE fi.analysis_id IN {ph}
        ORDER BY fi.id
        """
        frows = _fetchall(find_sql, analysis_ids)
        tmp = defaultdict(lambda: {"standard": "", "jurisdiction": "", "risk": "", "detected_fields": []})
        for r in frows:
            fid = r["finding_id"]
            entry = tmp[fid]
            entry["standard"] = r.get("standard") or entry["standard"]
            entry["jurisdiction"] = r.get("jurisdiction") or entry["jurisdiction"]
            entry["risk"] = r.get("risk") or entry["risk"]
            if r.get("field_name"):
                entry["detected_fields"].append(r["field_name"])
            entry["_analysis_id"] = r.get("analysis_id")
        for fid, d in tmp.items():
            aid = d.pop("_analysis_id", None)
            if aid:
                cfinds_by_analysis[aid].append(d)
        try:
            log_to_laravel("FETCH_FINDINGS_CF_RESULT", {"analyses": len(analysis_ids), "findings": len(frows)})
        except Exception:
            pass

    # Build output rows
    out = []
    for r in rows:
        file_id = r.get("file_id")
        analysis_id = r.get("analysis_id")
        storage_type = r.get("storage_type")
        backend_source = _backend_from_storage_type(storage_type)
        data_source = _to_datasource_lower(backend_source)

        last_modified = _coalesce(r.get("s3_last_modified"), r.get("smb_last_modified"), r.get("f_last_modified"))
        created = _coalesce(r.get("smb_created"), r.get("f_created_at"))
        full_path = _coalesce(r.get("s3_full_path"), r.get("smb_full_path"), r.get("f_full_path"))
        file_path = _coalesce(r.get("smb_file_path"), r.get("s3_key"))
        web_url = _coalesce(r.get("sp_web_url"), r.get("od_web_url"), r.get("f_web_url"))
        download_url = _coalesce(r.get("sp_download_url"), r.get("od_download_url"), r.get("f_download_url"))
        parent_reference = _coalesce(r.get("sp_parent_reference"), r.get("od_parent_reference"))
        drive_file_id = _coalesce(r.get("od_drive_file_id"), r.get("sp_drive_file_id"))

        llm_raw = r.get("raw_json")
        try:
            if isinstance(llm_raw, str):
                llm_raw_parsed = json.loads(llm_raw)
            else:
                llm_raw_parsed = llm_raw
        except Exception:
            llm_raw_parsed = llm_raw

        perms = perms_by_file.get(file_id, [])
        perm_roles = sorted({_to_str_strip(p.get("role")) for p in perms if p.get("role")})
        perm_users = sorted({
            _to_str_strip(p.get("principal_email") or p.get("principal_display_name"))
            for p in perms if (p.get("principal_type") or "").lower() in ("user","siteuser")
        })
        perm_groups = sorted({
            _to_str_strip(p.get("principal_display_name") or p.get("principal_email"))
            for p in perms if (p.get("principal_type") or "").lower() in ("group","sitegroup")
        })

        out.append({
            "site_id": r.get("site_id") or "",
            "drive_id": r.get("drive_id") or "",
            "drive_file_id": drive_file_id or "",
            "file_id": file_id,
            "file_name": r.get("file_name"),
            "file_type": r.get("file_type"),
            "file_extension": r.get("file_extension"),
            "size_bytes": r.get("size_bytes"),
            "last_modified": _iso(last_modified),
            "created": _iso(created),
            "web_url": web_url or "",
            "download_url": download_url or "",
            "parent_reference": parent_reference or "",
            "full_path": full_path or "",
            "file_path": file_path or "",
            "backend_source": backend_source,
            "data_source": data_source,  # lower-case backend name used by some UIs
            "user_id": r.get("user_id"),  # THIS is the config_id per your logic
            "business_id": r.get("business_id"),
            "config_id": str(r.get("user_id") or ''),  # expose as config_id for frontends
            "auditor_agent_view": r.get("auditor_agent_view") or "",
            "auditor_proposed_action": r.get("auditor_proposed_action") or "",
            "data_classification": r.get("data_classification") or "",
            "likely_data_subject_area": r.get("likely_data_subject_area") or "",
            "overall_risk_rating": r.get("overall_risk_rating") or "",
            "llm_response_raw": llm_raw_parsed,
            "compliance_findings": cfinds_by_analysis.get(analysis_id, []),
            "permissions": perms,
            "permission_roles": ",".join([x for x in perm_roles if x]),
            "permission_users": ",".join([x for x in perm_users if x]),
            "permission_groups": ",".join([x for x in perm_groups if x]),
        })

    return out


def get_no_more_questions_followup():
    return [{
        "label": "No More Questions",
        "operation": "cybersec_no_action",
        "args": {},
        "prompt": "Thank you, no further questions."
    }]


# ---- Agent entry points -----------------------------------------------------

def agent_cybersec(context):
    """
    Overview counts use files WHERE files.user_id IN (:config_ids)
    """
    req_user_id = context.get('user_id')  # used only for logging; not for scoping
    config_ids = context.get('config_ids') or []
    try:
        log_to_laravel("AGENT_CYBERSEC_START", {"request_user_id": req_user_id, "config_ids": config_ids})
    except Exception:
        pass
    total_files = count_files(config_ids=config_ids)
    highrisk = count_high_risk_files(config_ids=config_ids)
    summary = (
        f"**Cybersecurity Overview**\n\n"
        f"- Total files: **{total_files}**\n"
        f"- High risk files: **{highrisk}**\n"
    )
    followups = [
        {
            "label": "Cyber Agent's What-If Analysis",
            "operation": "cybersec_recommendations",
            "args": {},
            "prompt": "What are the top actions to reduce risk for my environment?"
        },
        {
            "label": "Show ALL externally shared files & associated Risk",
            "operation": "cybersec_show_external",
            "args": {},
            "prompt": "Show all files currently shared externally with outside parties."
        },
        {
            "label": "Show ALL duplicate files & associated Risk",
            "operation": "cybersec_find_duplicates",
            "args": {},
            "prompt": "Which files appear in multiple storage locations or folders?"
        }
    ] + get_no_more_questions_followup()
    return {
        "reply": summary,
        "followups": followups
    }


def agent_cybersec_show_external(context):
    """
    Externally shared files where files.user_id IN (:config_ids)
    """
    config_ids = context.get('config_ids') or []
    reply = get_externally_shared_files(high_risk_only=False, config_ids=config_ids)
    followups = [
        {
            "label": "Cyber Agent's What-If Analysis",
            "operation": "cybersec_recommendations",
            "args": {},
            "prompt": "What are the top actiosns to reduce risk for my environment?"
        },
        {
            "label": "Show ALL duplicate files & associated Risk",
            "operation": "cybersec_find_duplicates",
            "args": {},
            "prompt": "Which files appear in multiple storage locations or folders?"
        }
    ] + get_no_more_questions_followup()
    return {
        "reply": reply,
        "followups": followups
    }


def agent_cybersec_find_duplicates(context):
    """
    Duplicate files where files.user_id IN (:config_ids)
    """
    config_ids = context.get('config_ids') or []
    reply = find_duplicate_files(high_risk_only=False, config_ids=config_ids)
    followups = [
        {
            "label": "Cyber Agent's What-If Analysis",
            "operation": "cybersec_recommendations",
            "args": {},
            "prompt": "What are the top actiosns to reduce risk for my environment?"
        },
        {
            "label": "Show ALL externally shared files & associated Risk",
            "operation": "cybersec_show_external",
            "args": {},
            "prompt": "Show all files currently shared externally with outside parties."
        }
    ] + get_no_more_questions_followup()
    return {
        "reply": reply,
        "followups": followups
    }


def agent_cybersec_recommendations(context):
    """
    Standards-aware cyber risk report using LLM; evidence is DB-backed.
    Scope strictly by files.user_id IN (:config_ids)
    """
    from collections import defaultdict
    config_ids = context.get('config_ids') or []

    # 1) Primary evidence (HIGH RISK ONLY): prebuilt tables
    duplicates_md = find_duplicate_files(high_risk_only=True, config_ids=config_ids)
    external_md = get_externally_shared_files(high_risk_only=True, config_ids=config_ids)

    evidence_context = [
        "## High-Risk Files Present in Multiple Storage Locations",
        duplicates_md if "No duplicate files detected" not in duplicates_md else "_No high-risk duplicate files detected._",
        "",
        "## High-Risk Files That Are Externally Shared",
        external_md if "No externally shared files detected" not in external_md else "_No high-risk externally shared files detected._"
    ]
    combined_evidence = "\n\n".join(evidence_context)

    def truncate(text, limit=800):
        t = _to_str(text)
        t = t.strip()
        return t if len(t) <= limit else t[: limit - 1] + "…"

    risk_order = {
        "critical": 0, "very high": 1, "high": 2,
        "medium": 3, "moderate": 4,
        "low": 5, "very low": 6,
        "none": 7, "": 8
    }
    def risk_key(r):
        key = _to_str_lower(r)  # robust against list/dict
        return risk_order.get(key, 8)

    high_rows = fetch_findings(config_ids=config_ids, risk_filter="High") or []
    per_file = defaultdict(lambda: {
        "file_name": "",
        "storages": set(),
        "paths": set(),
        "last_modified": "",
        "created": "",
        "overall_risk_rating": "",
        "data_classification": "",
        "likely_data_subject_area": "",
        "auditor_agent_view": "",
        "auditor_proposed_action": "",
        "compliance_findings": "",
        "permissions": "",
    })

    for row in high_rows:
        name = _to_str_strip(row.get("file_name"))
        if not name:
            continue
        d = per_file[name]
        d["file_name"] = name or d["file_name"]
        storage = _to_str_strip(row.get("backend_source") or row.get("data_source"))
        if storage:
            d["storages"].add(storage)
        full_path = _to_str_strip(row.get("full_path"))
        file_path = _to_str_strip(row.get("file_path"))
        if full_path:
            d["paths"].add(full_path)
        elif file_path:
            d["paths"].add(file_path)
        # Copy key metadata with safe stringify
        if not d["last_modified"]:
            d["last_modified"] = _to_str(row.get("last_modified"))
        if not d["created"]:
            d["created"] = _to_str(row.get("created"))
        if not d["overall_risk_rating"]:
            d["overall_risk_rating"] = _to_str(row.get("overall_risk_rating"))
        if not d["data_classification"]:
            d["data_classification"] = _to_str(row.get("data_classification"))
        if not d["likely_data_subject_area"]:
            d["likely_data_subject_area"] = _to_str(row.get("likely_data_subject_area"))
        if not d["auditor_agent_view"]:
            d["auditor_agent_view"] = row.get("auditor_agent_view")  # keep raw (might be long)
        if not d["auditor_proposed_action"]:
            d["auditor_proposed_action"] = _to_str(row.get("auditor_proposed_action"))
        if not d["compliance_findings"]:
            d["compliance_findings"] = row.get("compliance_findings")  # list is fine
        if not d["permissions"]:
            d["permissions"] = row.get("permissions")  # list is fine

    # Compute exposure flags and build per-file sections
    highrisk_file_details_sections = []
    sorted_names = sorted(per_file.keys(), key=lambda n: (risk_key(per_file[n]["overall_risk_rating"]), n.lower()))
    for name in sorted_names:
        d = per_file[name]
        storages_str = ", ".join(sorted(d["storages"])) or "Unknown"
        paths_sorted = sorted(d["paths"])
        paths_preview = ", ".join(paths_sorted[:3]) + (f" (+{len(paths_sorted) - 3} more)" if len(paths_sorted) > 3 else "")

        section = [
            f"### File: `{name}`",
            "",
            "| Attribute | Value |",
            "|---|---|",
            f"| Overall Risk Rating | {_to_str(d['overall_risk_rating']) or 'Unknown'} |",
            f"| Data Classification | {_to_str(d['data_classification']) or 'Unknown'} |",
            f"| Likely Data Subject Area | {truncate(d['likely_data_subject_area']) or 'N/A'} |",
            f"| Storages | {storages_str} |",
            f"| Paths | {paths_preview or 'N/A'} |",
            f"| Last Modified | {_to_str(d['last_modified']) or 'Unknown'} |",
            f"| Created | {_to_str(d['created']) or 'Unknown'} |",
            "",
            "**Auditor Agent View**",
            "",
            truncate(d["auditor_agent_view"]) or "_None provided._",
            "",
            "**Compliance Findings**",
            "",
            truncate(d["compliance_findings"]) or "_None provided._",
            ""
        ]
        highrisk_file_details_sections.append("\n".join(section))

    highrisk_file_details_md = (
        "## High-Risk File Details (Profiles)\n"
        "Below are detailed attributes for high-risk files.\n\n"
        + ("\n\n".join(highrisk_file_details_sections) if highrisk_file_details_sections else "_No high-risk files required detailed profiling._")
    )

    # 3) Appendix DATA (all risks, verbatim)
    all_dupes_md = find_duplicate_files(high_risk_only=False, config_ids=config_ids)
    all_externals_md = get_externally_shared_files(high_risk_only=False, config_ids=config_ids)
    appendix_markdown = (
        "## Appendix: Full File Exposure Details\n\n"
        "### Appendix A: All Duplicate Files (Any Risk)\n"
        "The table below lists all files (regardless of risk) that appear in multiple storage locations or folders:\n\n"
        + (all_dupes_md if "No duplicate files detected" not in all_dupes_md else "_No files with duplicates detected in the environment._")
        + "\n\n"
        "### Appendix B: All Externally Shared Files (Any Risk)\n"
        "This table lists all files shared with external addresses, regardless of risk rating:\n\n"
        + (all_externals_md if "No externally shared files detected" not in all_externals_md else "_No externally shared files detected in the environment._")
    )

    # 4) LLM prompt — evidence + per-file details (model generates narrative only)
    llm_prompt = (
        "You are an enterprise cybersecurity expert with practical experience implementing NIST SP 800-53, "
        "the NIST CSF, and ISO/IEC 27001 & 27002 controls. "
        "You are given concrete evidence of high-risk file exposures (duplicates across storages and external sharing), "
        "plus per-file details.\n\n"
        "Your job:\n"
        "- Summarize key risks and exposure patterns observed in the evidence.\n"
        "- Provide a prioritized, actionable remediation plan mapping to relevant NIST/ISO controls.\n"
        "- Include specific, stepwise actions that can be assigned to owners (who/what/when), and quick wins vs. medium-term tasks.\n"
        "- Do NOT reproduce or overwrite the appendix tables; simply reference: 'See Appendix for full tables.'\n\n"
        "### High-Risk Exposure Evidence (Tables)\n"
        f"{combined_evidence}\n\n"
        "### High-Risk File Details (Profiles)\n"
        f"{highrisk_file_details_md}\n\n"
        "---\n"
        "Respond in clear Markdown with bold headings, numbered/bulleted lists, and short justifications linked to evidence."
    )

    system_prompt = (
        "You are an enterprise cybersecurity risk and compliance officer. "
        "All recommendations must explicitly reference (where relevant) NIST SP 800-53 and NIST CSF functions, "
        "and ISO/IEC 27001/27002 controls. Respond strictly in Markdown; do not include raw appendix tables."
    )

    resp = client.chat.completions.create(
        model="gpt-4.1",
        temperature=0.2,
        messages=[
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": llm_prompt}
        ]
    )
    expert_report_md = resp.choices[0].message.content

    # 5) Introductory banner + summary
    now_utc = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%SZ")
    intro_md = (
        "## CyberSecAI Expert Cyber Risk Report\n\n"
        f"- Generated: {now_utc} UTC\n"
        "- Scope: Files scoped strictly by config_ids (files.user_id IN config_ids)\n"
    )

    total_files = count_files(config_ids=config_ids)
    highrisk = count_high_risk_files(config_ids=config_ids)
    summary = (
        f"**Cybersecurity Overview**\n\n"
        f"- Total files: **{total_files}**\n"
        f"- High risk files: **{highrisk}**\n"
    )

    # 6) Final reply: Intro + Summary + expert report + high-risk profiles + appendix
    full_reply = (
        f"{intro_md}\n"
        f"{summary}\n"
        f"{expert_report_md}\n\n"
        "---\n\n"
        f"{highrisk_file_details_md}\n\n"
        "---\n\n"
        f"{appendix_markdown}"
    )

    followups = [
        {
            "label": "Show ALL externally shared files & associated Risk",
            "operation": "cybersec_show_external",
            "args": {},
            "prompt": "Show all files currently shared externally with outside parties."
        },
        {
            "label": "Show ALL duplicate files & associated Risk",
            "operation": "cybersec_find_duplicates",
            "args": {},
            "prompt": "Which files appear in multiple storage locations or folders?"
        }
    ] + get_no_more_questions_followup()

    return {
        "reply": full_reply,
        "followups": followups
    }


def agent_cybersec_no_action(context):
    return {
        "reply": "Thank you! If you have more questions later, just ask.",
        "followups": []
    }



# ---- CSV helpers for legacy paths (kept for compatibility) ------------------

DATA_PATH = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv"

def allrisk_path(user_id):
    return os.path.join(DATA_PATH, f"AllRisk_{user_id}.csv")
def highrisk_path(user_id):
    return os.path.join(DATA_PATH, f"HighRisk_{user_id}.csv")


# ---- Counts and exposure helpers (DB-scoped, config_ids-only) --------------

def count_files(config_ids=None):
    cfg_ids = _ensure_config_ids(config_ids=config_ids)
    scope_where, params = _build_scope_where("f", cfg_ids)
    if scope_where == "1=0":
        try:
            log_to_laravel("COUNT_FILES_EMPTY_SCOPE", {"config_ids": cfg_ids})
        except Exception:
            pass
        return 0
    sql = f"SELECT COUNT(*) AS c FROM files f WHERE {scope_where}"
    row = _fetchone(sql, params)
    c = int(row["c"]) if row else 0
    try:
        log_to_laravel("COUNT_FILES_RESULT", {"count": c, "config_ids": cfg_ids})
    except Exception:
        pass
    return c


def count_high_risk_files(config_ids=None):
    cfg_ids = _ensure_config_ids(config_ids=config_ids)
    scope_where, params = _build_scope_where("f", cfg_ids)
    if scope_where == "1=0":
        try:
            log_to_laravel("COUNT_HIGH_EMPTY_SCOPE", {"config_ids": cfg_ids})
        except Exception:
            pass
        return 0
    sql = f"""
    SELECT COUNT(*) AS c
    FROM files f
    JOIN (
        SELECT a1.file_id, a1.overall_risk_rating
        FROM file_ai_analyses a1
        JOIN (
            SELECT file_id, MAX(id) AS max_id
            FROM file_ai_analyses
            GROUP BY file_id
        ) x ON x.max_id = a1.id
    ) a ON a.file_id = f.id
    WHERE {scope_where} AND a.overall_risk_rating = 'High'
    """
    row = _fetchone(sql, params)
    c = int(row["c"]) if row else 0
    try:
        log_to_laravel("COUNT_HIGH_RESULT", {"count": c, "config_ids": cfg_ids})
    except Exception:
        pass
    return c


def get_externally_shared_files(high_risk_only=False, config_ids=None):
    """
    Returns a Markdown table of externally shared files (DB-backed).
    Filters to external emails (not in internal domains).
    Scope: strictly files.user_id IN (:config_ids).
    """
    INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}

    cfg_ids = _ensure_config_ids(config_ids=config_ids)
    scope_where, params = _build_scope_where("f", cfg_ids)
    if scope_where == "1=0":
        try:
            log_to_laravel("EXTERNAL_SHARED_EMPTY_SCOPE", {"config_ids": cfg_ids})
        except Exception:
            pass
        return "No externally shared files detected."

    risk_join = ""
    risk_where = ""
    if high_risk_only:
        risk_join = """
        JOIN (
            SELECT a1.file_id, a1.overall_risk_rating
            FROM file_ai_analyses a1
            JOIN (
                SELECT file_id, MAX(id) AS max_id
                FROM file_ai_analyses
                GROUP BY file_id
            ) x ON x.max_id = a1.id
        ) a ON a.file_id = f.id
        """
        risk_where = "AND a.overall_risk_rating = 'High'"

    sql = f"""
    SELECT
        f.id AS file_id, f.file_name, f.storage_type,
        p.principal_email, p.principal_display_name,
        {"a.overall_risk_rating" if high_risk_only else "(SELECT a2.overall_risk_rating FROM file_ai_analyses a2 WHERE a2.file_id=f.id ORDER BY a2.id DESC LIMIT 1) AS overall_risk_rating"}
    FROM files f
    JOIN file_permissions p ON p.file_id = f.id
    {risk_join}
    WHERE {scope_where} {risk_where}
    """
    rows = _fetchall(sql, params)
    try:
        log_to_laravel("EXTERNAL_SHARED_RESULT", {"count": len(rows), "config_ids": cfg_ids, "high_only": high_risk_only})
    except Exception:
        pass

    results_map = {}
    for r in rows:
        email = _to_str_lower(r.get("principal_email"))
        if not email or "@" not in email:
            continue
        if any(email.endswith("@" + dom) for dom in INTERNAL_DOMAINS):
            continue
        k = (r.get("file_name"), r.get("storage_type"))
        if k not in results_map:
            results_map[k] = {
                "name": r.get("file_name") or "[unknown]",
                "shared_with": set(),
                "storage": _backend_from_storage_type(r.get("storage_type")),
                "risk_rating": _to_str(r.get("overall_risk_rating")),
            }
        results_map[k]["shared_with"].add(email)

    results = []
    for v in results_map.values():
        if v["shared_with"]:
            v["shared_with"] = sorted(v["shared_with"])
            results.append(v)

    if not results:
        return "No externally shared files detected."

    description = (
        "Below is a list of files that have been shared externally (outside internal domains). "
        "Columns: File name, external recipients, storage system, overall risk."
    )
    lines = [
        description,
        "",
        "| File Name | Shared With | Storage | Overall Risk Rating |",
        "|-----------|-------------|---------|---------------------|"
    ]
    for r in results:
        shared_with_str = ", ".join(r['shared_with'])
        lines.append(f"| `{r['name']}` | {shared_with_str} | {r['storage'] or 'Unknown'} | {r['risk_rating'] or ''} |")
    return "\n".join(lines)


def find_duplicate_files(high_risk_only=False, config_ids=None):
    """
    Finds files with the same name present in multiple storages for the given scope.
    Scope: strictly files.user_id IN (:config_ids).
    """
    cfg_ids = _ensure_config_ids(config_ids=config_ids)
    scope_where, params = _build_scope_where("f", cfg_ids)
    if scope_where == "1=0":
        try:
            log_to_laravel("DUPLICATES_EMPTY_SCOPE", {"config_ids": cfg_ids})
        except Exception:
            pass
        return "No duplicate files detected across your environment."

    risk_join = ""
    risk_where = ""
    if high_risk_only:
        risk_join = """
        JOIN (
            SELECT a1.file_id, a1.overall_risk_rating
            FROM file_ai_analyses a1
            JOIN (
                SELECT file_id, MAX(id) AS max_id
                FROM file_ai_analyses
                GROUP BY file_id
            ) x ON x.max_id = a1.id
        ) a ON a.file_id = f.id
        """
        risk_where = "AND a.overall_risk_rating = 'High'"

    sql = f"""
    SELECT f.file_name, f.storage_type,
           {"a.overall_risk_rating" if high_risk_only else "(SELECT a2.overall_risk_rating FROM file_ai_analyses a2 WHERE a2.file_id=f.id ORDER BY a2.id DESC LIMIT 1) AS overall_risk_rating"}
    FROM files f
    {risk_join}
    WHERE {scope_where} {risk_where}
    """
    rows = _fetchall(sql, params)
    try:
        log_to_laravel("DUPLICATES_RESULT", {"count": len(rows), "config_ids": cfg_ids, "high_only": high_risk_only})
    except Exception:
        pass

    file_storages = defaultdict(set)
    file_risk = {}
    for r in rows:
        name = _to_str_strip(r.get("file_name"))
        storage = _backend_from_storage_type(r.get("storage_type"))
        risk = _to_str_strip(r.get("overall_risk_rating"))
        if name and storage:
            file_storages[name].add(storage)
            if name not in file_risk and risk:
                file_risk[name] = risk

    dupes = [
        (name, sorted(storages), file_risk.get(name, ""))
        for name, storages in file_storages.items()
        if len(storages) > 1
    ]

    if not dupes:
        return "No duplicate files detected across your environment."

    description = (
        "Below is a list of files that appear in multiple storage locations. "
        "For each file, the table shows the storage services it was found in, "
        "along with its overall risk rating as identified in the assessment."
    )

    lines = [
        description,
        "",
        "| File Name | Storages | Overall Risk Rating |",
        "|-----------|----------|---------------------|"
    ]
    for name, storages, risk in sorted(dupes, key=lambda x: (x[0])):
        lines.append(f"| `{name}` | {', '.join(storages)} | {risk} |")

    return "\n".join(lines)