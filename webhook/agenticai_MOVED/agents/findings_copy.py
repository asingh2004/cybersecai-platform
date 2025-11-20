# gather_high_risk_findings, gather_all_findings, agent_findings_facts here
from utils.logging import log_to_laravel
from utils.markdown_format import format_high_risk_files_markdown, format_all_risks_files_markdown, format_medium_risk_files_markdown
from utils.dateparse import parse_date_from_query
from typing import Dict, Any, Optional, List
import os, json, re, glob
import csv
from agents.cybersec import highrisk_path, allrisk_path



def agent_high_risk_csv_batch(context):
    config_ids = context.get('config_ids', [])
    user_id = context.get('user_id', 'unknown')
    base_dir = "/home/cybersecai/htdocs/www.cybersecai.io/webhook"
    base_paths = ['M365', 'SMB', 'S3', 'NFS']

    findings = []
    for cid in config_ids:
        for backend in base_paths:
            graph_dir = os.path.join(base_dir, backend, str(cid), "graph")
            if not os.path.isdir(graph_dir):
                continue
            for filepath in glob.glob(os.path.join(graph_dir, "*.json")):
                try:
                    with open(filepath, "r", encoding="utf-8") as f:
                        data = json.load(f)
                        items = data if isinstance(data, list) else [data]
                        for item in items:
                            if not isinstance(item, dict):
                                continue
                            llm = item.get('llm_response')
                            if not llm:
                                continue
                            if isinstance(llm, str):
                                try:
                                    llm = json.loads(llm)
                                except Exception:
                                    try:
                                        llm = json.loads(llm.strip('"\'')) # extra quotes
                                    except Exception:
                                        continue
                            if not isinstance(llm, dict):
                                continue
                            risk_rating = llm.get('overall_risk_rating')
                            if not risk_rating or str(risk_rating).strip().lower() != "high":
                                continue
                            # Compose output row, **add all relevant fields, default blank if missing**
                            row = {
                                'site_id': item.get('site_id', ''),
                                'drive_id': item.get('drive_id', ''),
                                'file_id': item.get('file_id', ''),
                                'file_name': item.get('file_name', ''),
                                'file_type': item.get('file_type', ''),
                                'size_bytes': item.get('size_bytes', ''),
                                'last_modified': item.get('last_modified', ''),
                                'created': item.get('created', ''),
                                'web_url': item.get('web_url', ''),
                                'download_url': item.get('download_url', ''),
                                'parent_reference': item.get('parent_reference', ''),
                                'full_path': item.get('full_path', ''),
                                'file_path': item.get('file_path', ''),
                                'backend_source': backend,
                                'user_id': user_id,
                                'config_id': cid,
                                'auditor_agent_view': llm.get('auditor_agent_view', ''),
                                'auditor_proposed_action': llm.get('auditor_proposed_action', ''),
                                'data_classification': llm.get('data_classification', ''),
                                'likely_data_subject_area': llm.get('likely_data_subject_area', ''),
                                'overall_risk_rating': risk_rating,
                                'cyber_proposed_controls': llm.get('cyber_proposed_controls', ''),
                                'llm_response_raw': json.dumps(llm, separators=(',', ':')),
                                'compliance_findings': json.dumps(llm.get('results', []), separators=(',', ':'))
                            }
                            # Permissions: flatten for analytics and join
                            perms = item.get('permissions', [])
                            row['permissions'] = json.dumps(perms, separators=(',', ':'))
                            roles, users, groups = set(), set(), set()
                            for perm in perms:
                                if 'roles' in perm: roles.update(perm['roles'])
                                if 'grantedToV2' in perm:
                                    if 'user' in perm['grantedToV2']:
                                        users.add(str(perm['grantedToV2']['user'].get('displayName', '')))
                                    if 'siteGroup' in perm['grantedToV2']:
                                        groups.add(str(perm['grantedToV2']['siteGroup'].get('displayName', '')))
                            row['permission_roles'] = ','.join(roles)
                            row['permission_users'] = ','.join(users)
                            row['permission_groups'] = ','.join(groups)
                            findings.append(row)
                except Exception as ex:
                    log_to_laravel(f"Error reading {filepath}: {repr(ex)}")
                    continue

    outdir = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv"
    os.makedirs(outdir, exist_ok=True)
    filename = f"HighRisk_{user_id}.csv"
    out_full = os.path.join(outdir, filename)
    if findings:
        # Final and full field order for robust analytics
        field_order = [
            'site_id', 'drive_id', 'file_id', 'file_name', 'file_type', 'size_bytes',
            'last_modified', 'created', 'web_url', 'download_url', 'parent_reference',
            'full_path', 'file_path', 'backend_source', 'user_id', 'config_id',
            'auditor_agent_view', 'auditor_proposed_action', 'data_classification',
            'likely_data_subject_area', 'overall_risk_rating', 'cyber_proposed_controls',
            'llm_response_raw', 'compliance_findings', 'permissions',
            'permission_roles', 'permission_users', 'permission_groups'
        ]
        all_fields = list({k for f in findings for k in f})
        fieldnames = field_order + [k for k in all_fields if k not in field_order]
        with open(out_full, "w", newline="", encoding="utf-8") as fcsv:
            writer = csv.DictWriter(fcsv, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(findings)
    return {"csv_filename": filename}



def agent_allrisk_csv_batch(context):
    config_ids = context.get('config_ids', [])
    user_id = context.get('user_id', 'unknown')
    base_dir = "/home/cybersecai/htdocs/www.cybersecai.io/webhook"
    base_paths = ['M365', 'SMB', 'S3', 'NFS']

    findings = []
    for cid in config_ids:
        for backend in base_paths:
            graph_dir = os.path.join(base_dir, backend, str(cid), "graph")
            if not os.path.isdir(graph_dir):
                continue
            for filepath in glob.glob(os.path.join(graph_dir, "*.json")):
                try:
                    with open(filepath, "r", encoding="utf-8") as f:
                        data = json.load(f)
                        items = data if isinstance(data, list) else [data]
                        for item in items:
                            if not isinstance(item, dict):
                                continue
                            llm = item.get('llm_response')
                            if not llm:
                                continue
                            if isinstance(llm, str):
                                try:
                                    llm = json.loads(llm)
                                except Exception:
                                    try:
                                        llm = json.loads(llm.strip('"\'')) # for extra quotes
                                    except Exception:
                                        continue
                            if not isinstance(llm, dict):
                                continue

                            row = {
                                # Source/file metadata and lineage
                                'site_id': item.get('site_id', ''),
                                'drive_id': item.get('drive_id', ''),
                                'file_id': item.get('file_id', ''),
                                'file_name': item.get('file_name', ''),
                                'file_type': item.get('file_type', ''),
                                'size_bytes': item.get('size_bytes', ''),
                                'last_modified': item.get('last_modified', ''),
                                'created': item.get('created', ''),
                                'web_url': item.get('web_url', ''),
                                'download_url': item.get('download_url', ''),
                                'parent_reference': item.get('parent_reference', ''),
                                'full_path': item.get('full_path', ''),
                                'file_path': item.get('file_path', ''),
                                'backend_source': backend,
                                'user_id': user_id,
                                'config_id': cid,
                                # LLM and audit/compliance context
                                'auditor_agent_view': llm.get('auditor_agent_view', ''),
                                'auditor_proposed_action': llm.get('auditor_proposed_action', ''),
                                'data_classification': llm.get('data_classification', ''),
                                'likely_data_subject_area': llm.get('likely_data_subject_area', ''),
                                'overall_risk_rating': llm.get('overall_risk_rating', ''),
                                'cyber_proposed_controls': llm.get('cyber_proposed_controls', ''),
                                'llm_response_raw': json.dumps(llm, separators=(',', ':')),
                                'compliance_findings': json.dumps(llm.get('results', []), separators=(',', ':'))
                            }

                            # Permissions: flatten for analytics
                            perms = item.get('permissions', [])
                            row['permissions'] = json.dumps(perms, separators=(',', ':'))
                            roles, users, groups = set(), set(), set()
                            for perm in perms:
                                if 'roles' in perm:
                                    roles.update(perm['roles'])
                                if 'grantedToV2' in perm:
                                    if 'user' in perm['grantedToV2']:
                                        users.add(str(perm['grantedToV2']['user'].get('displayName', '')))
                                    if 'siteGroup' in perm['grantedToV2']:
                                        groups.add(str(perm['grantedToV2']['siteGroup'].get('displayName', '')))
                            row['permission_roles'] = ','.join(roles)
                            row['permission_users'] = ','.join(users)
                            row['permission_groups'] = ','.join(groups)
                            findings.append(row)
                except Exception as ex:
                    # Replace or define this for your Laravel logging
                    print(f"Error reading {filepath}: {repr(ex)}")
                    continue

    outdir = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv"
    os.makedirs(outdir, exist_ok=True)
    filename = f"AllRisk_{user_id}.csv"
    out_full = os.path.join(outdir, filename)
    if findings:
        # Fieldnames: all common, plus any newly discovered
        field_order = [
            'site_id', 'drive_id', 'file_id', 'file_name', 'file_type', 'size_bytes',
            'last_modified', 'created', 'web_url', 'download_url', 'parent_reference',
            'full_path', 'file_path', 'backend_source', 'user_id', 'config_id',
            'auditor_agent_view', 'auditor_proposed_action', 'data_classification',
            'likely_data_subject_area', 'overall_risk_rating', 'cyber_proposed_controls',
            'llm_response_raw', 'compliance_findings', 'permissions',
            'permission_roles', 'permission_users', 'permission_groups'
        ]
        all_fields = list({k for f in findings for k in f})
        fieldnames = field_order + [k for k in all_fields if k not in field_order]
        with open(out_full, "w", newline="", encoding="utf-8") as fcsv:
            writer = csv.DictWriter(fcsv, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(findings)
    return {"csv_filename": filename}


## The function below only Yields or collect only files with overall_risk_rating == 'High'
def gather_high_risk_findings(config_ids, user_id=None):
    """
    Reads findings from pre-generated HighRisk_{user_id}.csv  
    Returns: list of dicts, one per finding (row)
    """
    if user_id is None:
        # Get user_id from config_ids if necessary - you must pass user_id to this function now!
        raise ValueError("user_id required for pre-generated CSV")
    path = highrisk_path(user_id)
    if not os.path.isfile(path):
        return []
    findings = []
    with open(path, newline='', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            findings.append(row)
    return findings

def gather_all_findings(config_ids, user_id=None):
    """
    Reads findings from pre-generated AllRisk_{user_id}.csv  
    Returns: list of dicts, one per finding (row)
    """
    if user_id is None:
        raise ValueError("user_id required for pre-generated CSV")
    path = allrisk_path(user_id)
    if not os.path.isfile(path):
        return []
    findings = []
    with open(path, newline='', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            findings.append(row)
    return findings

def agent_findings_facts(data: Dict[str, Any]):
    """
    Returns readable summaries for:
      - 'How many high risk files overall?'
      - 'How many high risk files by jurisdiction?'
      - 'Show me all files found in <backend>'
    Otherwise, returns pretty JSON.
    """
    op = data.get("operation", "")
    args = data.get("args", {}) if data.get("args") is not None else {}
    config_ids = data.get("config_ids")
    #findings = gather_high_risk_findings(config_ids)
    user_query = (data.get("query") or op or "").lower()

    # Use all findings for medium/low/all risk, else only high!
    if any(kw in user_query for kw in ["medium risk", "low risk", "all risk", "any risk"]):
        #findings = gather_all_findings(config_ids)
        findings = gather_all_findings(config_ids, user_id=data.get("user_id"))
        #findings = gather_all_findings(config_ids, user_id=context.get("user_id") or data.get("user_id"))
    else:
        #findings = gather_high_risk_findings(config_ids)
        findings = gather_high_risk_findings(config_ids, user_id=data.get("user_id"))
        #findings = gather_high_risk_findings(config_ids, user_id=context.get("user_id") or data.get("user_id"))

        # --- Data classification count summary ---
    if re.search(r"(how many|count|number of).*(file|files).*(data classification|classification)", user_query):
        class_count = {}
        for f in findings:
            cls = (f.get("data_classification") or "").strip()
            if cls:
                class_count[cls] = class_count.get(cls, 0) + 1
            else:
                class_count["[Unclassified]"] = class_count.get("[Unclassified]", 0) + 1
        if not class_count:
            reply = "_No data classifications found in current high risk files._"
        else:
            reply = "### High Risk File Count by Data Classification\n\n"
            reply += "| Data Classification | File Count |\n"
            reply += "|---------------------|------------|\n"
            for k, v in class_count.items():
                reply += f"| {k} | {v} |\n"
        return {"reply": reply, "raw": class_count, "operation": op, "args": args}


    def md_table(headers, rows, title=""):
        out = []
        if title: out.append(f"### {title}")
        out.append("| " + " | ".join(headers) + " |")
        out.append("|" + "|".join(["-"*len(h) for h in headers]) + "|")
        for row in rows:
            out.append("| " + " | ".join(str(x) for x in row) + " |")
        return "\n".join(out)


    # --- Data classification count summary ---
    if re.search(r"(how many|count|number of).*(file|files).*(data classification|classification)", user_query):
        class_count = {}
        for f in findings:
            cls = (f.get("data_classification") or "").strip()
            if cls:
                class_count[cls] = class_count.get(cls, 0) + 1
            else:
                class_count["[Unclassified]"] = class_count.get("[Unclassified]", 0) + 1
        if not class_count:
            reply = "_No data classifications found in current high risk files._"
        else:
            reply = "### High Risk File Count by Data Classification\n\n"
            reply += "| Data Classification | File Count |\n"
            reply += "|---------------------|------------|\n"
            for k, v in class_count.items():
                reply += f"| {k} | {v} |\n"
        return {"reply": reply, "raw": class_count, "operation": op, "args": args}

    # How many files are rated as high risk overall?
    if ("how many" in user_query or "count" in user_query) and ("high risk" in user_query) and (
         "overall" in user_query or "risk rating" in user_query or "overall risk" in user_query):
        n = len(findings)
        reply = md_table(
           headers=["Overall Risk Rating", "Number of Files"],
           rows=[["High", n]],
           title="High Risk File Summary"
        )
        #return {"reply": reply, "raw": n, "operation": op, "args": args}
        return reply

    # How many high risk files by jurisdiction?
    if ("how many" in user_query or "count" in user_query) and (
        "jurisdiction" in user_query or "each jurisdiction" in user_query or "by jurisdiction" in user_query
    ):
        # Count files per jurisdiction - each file can count for more than one
        jurisdiction_counts = {}
        for file in findings:
            if isinstance(file.get("compliance_findings"), list):
                jurisdictions = set()
                for cf in file["compliance_findings"]:
                    j = cf.get("jurisdiction")
                    if j:
                        jurisdictions.add(j)
                for j in jurisdictions:
                    jurisdiction_counts[j] = jurisdiction_counts.get(j, 0) + 1
        if not jurisdiction_counts:
            reply = "_No jurisdictions detected in current high-risk findings._"
        else:
            rows = [(j, c) for j, c in jurisdiction_counts.items()]
            reply = md_table(
                headers=["Jurisdiction", "High Risk File Count"],
                rows=rows,
                title="High Risk Files by Jurisdiction"
            )
        return {"reply": reply, "raw": jurisdiction_counts, "operation": op, "args": args}

    # Show all files found in <backend> (SMB, S3, NFS, M365, etc.)
    backend_names = ["smb", "s3", "nfs", "m365"]
    for b in backend_names:
        if f"all files" in user_query and b in user_query:
            # Make a flat table of files with data_source == b (case-insensitive)
            matching = [f for f in findings if (f.get("data_source","").lower() == b)]
            if not matching:
                reply = f"_No high risk files found in the {b.upper()} backend._"
            else:
                headers = ["File Name", "Last Modified", "Data Classification", "Overall Risk", "File Path"]
                rows = [
                    [
                        f.get("file_name", ""),
                        f.get("last_modified", ""),
                        f.get("data_classification", ""),
                        f.get("overall_risk_rating", ""),
                        f.get("full_path", f.get("file_path", "")),
                    ]
                    for f in matching
                ]
                reply = md_table(headers, rows, f"High Risk Files in {b.upper()} Backend")
            return {"reply": reply, "raw": matching, "operation": op, "args": args}