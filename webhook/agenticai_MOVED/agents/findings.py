
# gather_high_risk_findings, gather_all_findings, agent_findings_facts here
from typing import Dict, Any, List
import os, json, re, csv
from datetime import datetime
from agents.cybersec import fetch_findings, highrisk_path, allrisk_path

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

def agent_high_risk_csv_batch(context):
    """
    Build HighRisk_{user_id}.csv from MySQL using data_configs.id (config_ids).
    """
    user_id = context.get('user_id', 'unknown')
    config_ids = context.get('config_ids', []) or []
    findings = fetch_findings(user_id=user_id, config_ids=config_ids, risk_filter="High") or []

    out_rows = []
    for item in findings:
        perms = item.get("permissions") or []
        roles, users, groups = set(), set(), set()
        for perm in perms:
            r = perm.get("role")
            if r: roles.add(str(r))
            ptype = (perm.get("principal_type") or "").lower()
            if ptype in ("user","siteuser"):
                users.add(perm.get("principal_email") or perm.get("principal_display_name") or "")
            if ptype in ("group","sitegroup"):
                groups.add(perm.get("principal_display_name") or perm.get("principal_email") or "")
        row = {
            'site_id': item.get('site_id', ''), 'drive_id': item.get('drive_id', ''), 'file_id': item.get('file_id', ''),
            'file_name': item.get('file_name', ''), 'file_type': item.get('file_type', ''), 'size_bytes': item.get('size_bytes', ''),
            'last_modified': _iso(item.get('last_modified')), 'created': _iso(item.get('created')),
            'web_url': item.get('web_url', ''), 'download_url': item.get('download_url', ''), 'parent_reference': item.get('parent_reference', ''),
            'full_path': item.get('full_path', ''), 'file_path': item.get('file_path', ''),
            'backend_source': item.get('backend_source', ''), 'data_source': item.get('data_source', ''),
            'user_id': user_id, 'config_id': item.get('config_id', ''),
            'auditor_agent_view': item.get('auditor_agent_view', ''), 'auditor_proposed_action': item.get('auditor_proposed_action', ''),
            'data_classification': item.get('data_classification', ''), 'likely_data_subject_area': item.get('likely_data_subject_area', ''),
            'overall_risk_rating': item.get('overall_risk_rating', ''), 'cyber_proposed_controls': item.get('cyber_proposed_controls', ''),
            'llm_response_raw': json.dumps(item.get('llm_response_raw')) if isinstance(item.get('llm_response_raw'), (dict, list)) else (item.get('llm_response_raw') or ''),
            'compliance_findings': json.dumps(item.get('compliance_findings', [])),
            'permissions': json.dumps(perms),
            'permission_roles': ','.join(sorted(x for x in roles if x)),
            'permission_users': ','.join(sorted(x for x in users if x)),
            'permission_groups': ','.join(sorted(x for x in groups if x)),
        }
        out_rows.append(row)

    outdir = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv"
    os.makedirs(outdir, exist_ok=True)
    filename = f"HighRisk_{user_id}.csv"
    out_full = os.path.join(outdir, filename)
    if out_rows:
        field_order = [
            'site_id','drive_id','file_id','file_name','file_type','size_bytes','last_modified','created',
            'web_url','download_url','parent_reference','full_path','file_path','backend_source','data_source',
            'user_id','config_id','auditor_agent_view','auditor_proposed_action','data_classification',
            'likely_data_subject_area','overall_risk_rating','cyber_proposed_controls','llm_response_raw',
            'compliance_findings','permissions','permission_roles','permission_users','permission_groups'
        ]
        all_fields = list({k for f in out_rows for k in f})
        fieldnames = field_order + [k for k in all_fields if k not in field_order]
        with open(out_full, "w", newline="", encoding="utf-8") as fcsv:
            writer = csv.DictWriter(fcsv, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(out_rows)
    return {"csv_filename": filename}

def agent_allrisk_csv_batch(context):
    """
    Build AllRisk_{user_id}.csv from MySQL using data_configs.id (config_ids).
    """
    user_id = context.get('user_id', 'unknown')
    config_ids = context.get('config_ids', []) or []
    findings = fetch_findings(user_id=user_id, config_ids=config_ids, risk_filter=None) or []

    out_rows = []
    for item in findings:
        perms = item.get("permissions") or []
        roles, users, groups = set(), set(), set()
        for perm in perms:
            r = perm.get("role")
            if r: roles.add(str(r))
            ptype = (perm.get("principal_type") or "").lower()
            if ptype in ("user","siteuser"):
                users.add(perm.get("principal_email") or perm.get("principal_display_name") or "")
            if ptype in ("group","sitegroup"):
                groups.add(perm.get("principal_display_name") or perm.get("principal_email") or "")
        row = {
            'site_id': item.get('site_id', ''), 'drive_id': item.get('drive_id', ''), 'file_id': item.get('file_id', ''),
            'file_name': item.get('file_name', ''), 'file_type': item.get('file_type', ''), 'size_bytes': item.get('size_bytes', ''),
            'last_modified': _iso(item.get('last_modified')), 'created': _iso(item.get('created')),
            'web_url': item.get('web_url', ''), 'download_url': item.get('download_url', ''), 'parent_reference': item.get('parent_reference', ''),
            'full_path': item.get('full_path', ''), 'file_path': item.get('file_path', ''),
            'backend_source': item.get('backend_source', ''), 'data_source': item.get('data_source', ''),
            'user_id': user_id, 'config_id': item.get('config_id', ''),
            'auditor_agent_view': item.get('auditor_agent_view', ''), 'auditor_proposed_action': item.get('auditor_proposed_action', ''),
            'data_classification': item.get('data_classification', ''), 'likely_data_subject_area': item.get('likely_data_subject_area', ''),
            'overall_risk_rating': item.get('overall_risk_rating', ''), 'cyber_proposed_controls': item.get('cyber_proposed_controls', ''),
            'llm_response_raw': json.dumps(item.get('llm_response_raw')) if isinstance(item.get('llm_response_raw'), (dict, list)) else (item.get('llm_response_raw') or ''),
            'compliance_findings': json.dumps(item.get('compliance_findings', [])),
            'permissions': json.dumps(perms),
            'permission_roles': ','.join(sorted(x for x in roles if x)),
            'permission_users': ','.join(sorted(x for x in users if x)),
            'permission_groups': ','.join(sorted(x for x in groups if x)),
        }
        out_rows.append(row)

    outdir = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv"
    os.makedirs(outdir, exist_ok=True)
    filename = f"AllRisk_{user_id}.csv"
    out_full = os.path.join(outdir, filename)
    if out_rows:
        field_order = [
            'site_id','drive_id','file_id','file_name','file_type','size_bytes','last_modified','created',
            'web_url','download_url','parent_reference','full_path','file_path','backend_source','data_source',
            'user_id','config_id','auditor_agent_view','auditor_proposed_action','data_classification',
            'likely_data_subject_area','overall_risk_rating','cyber_proposed_controls','llm_response_raw',
            'compliance_findings','permissions','permission_roles','permission_users','permission_groups'
        ]
        all_fields = list({k for f in out_rows for k in f})
        fieldnames = field_order + [k for k in all_fields if k not in field_order]
        with open(out_full, "w", newline="", encoding="utf-8") as fcsv:
            writer = csv.DictWriter(fcsv, fieldnames=fieldnames)
            writer.writeheader()
            writer.writerows(out_rows)
    return {"csv_filename": filename}

def gather_high_risk_findings(config_ids, user_id=None):
    if user_id is None:
        raise ValueError("user_id required")
    return fetch_findings(user_id=user_id, config_ids=config_ids or [], risk_filter="High") or []

def gather_all_findings(config_ids, user_id=None):
    if user_id is None:
        raise ValueError("user_id required")
    return fetch_findings(user_id=user_id, config_ids=config_ids or [], risk_filter=None) or []

def agent_findings_facts(data: Dict[str, Any]):
    op = data.get("operation", "")
    args = data.get("args", {}) if data.get("args") is not None else {}
    config_ids = data.get("config_ids")
    user_query = (data.get("query") or op or "").lower()

    if any(kw in user_query for kw in ["medium risk", "low risk", "all risk", "any risk"]):
        findings = gather_all_findings(config_ids, user_id=data.get("user_id"))
    else:
        findings = gather_high_risk_findings(config_ids, user_id=data.get("user_id"))

    def md_table(headers, rows, title=""):
        out = []
        if title: out.append(f"### {title}")
        out.append("| " + " | ".join(headers) + " |")
        out.append("|" + "|".join(["-"*max(3, len(h)) for h in headers]) + "|")
        for row in rows:
            out.append("| " + " | ".join(str(x) for x in row) + " |")
        return "\n".join(out)

    if re.search(r"(how many|count|number of).*(file|files).*(data classification|classification)", user_query):
        class_count = {}
        for f in findings:
            cls = (f.get("data_classification") or "").strip() or "[Unclassified]"
            class_count[cls] = class_count.get(cls, 0) + 1
        if not class_count:
            reply = "_No data classifications found in current files._"
        else:
            reply = "### File Count by Data Classification\n\n| Data Classification | File Count |\n|---------------------|------------|\n"
            for k, v in class_count.items():
                reply += f"| {k} | {v} |\n"
        return {"reply": reply, "raw": class_count, "operation": op, "args": args}

    if ("how many" in user_query or "count" in user_query) and ("high risk" in user_query) and ("overall" in user_query or "risk rating" in user_query or "overall risk" in user_query):
        n = len(findings)
        reply = md_table(["Overall Risk Rating", "Number of Files"], [["High", n]], "High Risk File Summary")
        return reply

    if ("how many" in user_query or "count" in user_query) and ("jurisdiction" in user_query or "each jurisdiction" in user_query or "by jurisdiction" in user_query):
        jurisdiction_counts = {}
        for file in findings:
            cfinds = file.get("compliance_findings") or []
            if isinstance(cfinds, list):
                jurisdictions = set()
                for cf in cfinds:
                    j = cf.get("jurisdiction")
                    if j: jurisdictions.add(j)
                for j in jurisdictions:
                    jurisdiction_counts[j] = jurisdiction_counts.get(j, 0) + 1
        if not jurisdiction_counts:
            reply = "_No jurisdictions detected in current findings._"
        else:
            rows = [(j, c) for j, c in jurisdiction_counts.items()]
            reply = md_table(["Jurisdiction", "High Risk File Count"], rows, "High Risk Files by Jurisdiction")
        return {"reply": reply, "raw": jurisdiction_counts, "operation": op, "args": args}

    backend_names = ["smb", "s3", "nfs", "m365"]
    for b in backend_names:
        if "all files" in user_query and b in user_query:
            matching = [f for f in findings if (f.get("data_source","").lower() == b)]
            if not matching:
                reply = f"_No files found in the {b.upper()} backend._"
            else:
                headers = ["File Name", "Last Modified", "Data Classification", "Overall Risk", "File Path"]
                rows = [[f.get("file_name",""), _iso(f.get("last_modified")), f.get("data_classification",""), f.get("overall_risk_rating",""), f.get("full_path", f.get("file_path",""))] for f in matching]
                reply = md_table(headers, rows, f"Files in {b.upper()} Backend")
            return {"reply": reply, "raw": matching, "operation": op, "args": args}

    pretty = json.dumps(findings[:200], indent=2, ensure_ascii=False)
    return {"reply": f"```json\n{pretty}\n```", "raw": findings, "operation": op, "args": args}