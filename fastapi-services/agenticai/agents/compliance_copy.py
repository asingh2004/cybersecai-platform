from utils.logging import log_to_laravel
from utils.markdown_format import format_high_risk_files_markdown, format_all_risks_files_markdown, format_medium_risk_files_markdown
from utils.dateparse import parse_date_from_query
from typing import Dict, Any, Optional, List
import os, json, re, glob
from config import client
from utils.guardrails import output_guardrails
from fastapi import HTTPException
from agents.findings import gather_all_findings
from utils.csv_export import get_csv_download_link
from utils.docx_export import robust_write_docx_file


def notify_stakeholders(file: dict, issues: List[str]):
    # Real code would use email/SIEM, here we just log
    log_to_laravel(f"[Compliance ALERT] File: '{file.get('file_name')}' at '{file.get('web_url','')}'. Issues: {', '.join(issues)}")

def parse_llm_response(llm):
    try:
        return json.loads(llm) if isinstance(llm, str) else (llm or {})
    except Exception:
        return {}

def is_external_email(email: str, corporate_domains: List[str]) -> bool:
    if not email or not isinstance(email, str):
        return False
    email = email.lower()
    for dom in corporate_domains:
        if email.endswith(dom.lower()):
            return False
    return True # Not in any allowed domain, so external


def agent_compliance_m365_auto_evidence(context: Dict[str, Any]) -> Dict[str, Any]:
    corporate_domains: List[str] = context.get("corporate_domains")
    config_ids = context.get("config_ids")

    if not corporate_domains or not isinstance(corporate_domains, list):
        raise HTTPException(status_code=400, detail="corporate_domains (list of allowed domains) is required")
    if not config_ids:
        raise HTTPException(status_code=400, detail="config_ids (list of user's config IDs) is required")

    files = gather_all_findings(config_ids) or []
    if not files:
        markdown = "_No files found for compliance evidence generation for your account._"
        csv_url = get_csv_download_link([]) or ''
        docx_url = robust_write_docx_file(markdown) or ''
        download_docx_md = f"\n\n---\n[⬇️ Download as Word (docx)]({docx_url})" if docx_url else ""
        download_csv_md = f"\n\n---\n[⬇️ Download as CSV]({csv_url})" if csv_url else ""
        return {"markdown": markdown + download_docx_md + download_csv_md, "csv_url": csv_url, "docx_url": docx_url}

    alerts = []
    audit_rows = []

    for file in files:
        file_name = file.get('file_name')
        url = file.get('web_url')
        last_modified = file.get('last_modified')
        location = file.get('parent_reference', '') or file.get('full_path', '') or ''
        data_classification = file.get('data_classification', '')
        permissions = file.get('permissions') or []

        llm = parse_llm_response(file.get("llm_response", {}))
        detected_fields = set()
        compliance_findings = file.get("compliance_findings") or []
        for cf in compliance_findings:
            detected_fields.update(cf.get("detected_fields") or [])
        if not detected_fields:
            results = (llm.get('results') or []) if llm else []
            for res in results:
                detected_fields.update(res.get("detected_fields") or [])

        overall_risk = (file.get("overall_risk_rating") or "").strip().lower()
        if not overall_risk:
            overall_risk = (llm.get("overall_risk_rating") or "").strip().lower()
        auditor_notes = llm.get("auditor_agent_view", "") or ""
        proposed_action = llm.get("auditor_proposed_action", "") or ""
        controls = llm.get("cyber_proposed_controls", "") or ""

        risky_location = any(tag in location.lower() for tag in ("public", "guest", "external", "downloads"))
        risky_external_share = False
        risky_permissions = []
        for perm in permissions:
            roles = [r.lower() for r in perm.get("roles") or []]
            for path in ["grantedToIdentitiesV2", "grantedToIdentities", "grantedToV2", "grantedTo"]:
                grantees = perm.get(path)
                if isinstance(grantees, dict):
                    grantees = [grantees]
                for grantee in grantees or []:
                    user = grantee.get("user", {}) or grantee.get("siteUser", {}) or grantee.get("group", {}) or grantee.get("siteGroup", {}) or {}
                    email = user.get("email") or ""
                    display_name = user.get("displayName") or ""
                    if any(role in ("write","owner") for role in roles):
                        if display_name.lower().find("visitor") != -1 or is_external_email(email, corporate_domains):
                            risky_external_share = True
                            risky_permissions.append(f"{','.join(roles)} to {display_name or email or '[Unknown]'}")
            link = perm.get("link", {}) or {}
            if link.get("scope") and str(link.get("scope")).lower() in ("anonymous","users"):
                if any(role in ("write","owner") for role in roles):
                    risky_external_share = True
                    risky_permissions.append(f"{','.join(roles)} [LINK: {link.get('scope')}]")

        all_permissions = []
        for perm in permissions:
            roles = ",".join(perm.get("roles") or [])
            who = []
            for k in ["grantedToIdentitiesV2", "grantedToIdentities", "grantedToV2", "grantedTo"]:
                g = perm.get(k)
                if isinstance(g, dict): g = [g]
                for u in g or []:
                    who.append(
                        u.get("user", {}).get("email")
                        or u.get("user", {}).get("displayName")
                        or "[Unknown]"
                    )
            if not who:
                w2 = perm.get("grantedTo",{}).get("user",{}).get("displayName") or "[Unknown]"
                who = [w2]
            all_permissions.append(f"{roles} to {', '.join(who)}")

        issues = []
        if risky_location:
            issues.append("Sensitive file in risky/public location")
        if risky_external_share:
            issues.append("Permissive/external sharing detected")
        if overall_risk == "high":
            issues.append("High risk rating")
        if proposed_action and "notify" in proposed_action.lower():
            issues.append("Action: " + proposed_action)

        alert_triage = (
            (detected_fields and (risky_external_share or risky_location))
            or overall_risk == "high"
            or ("notify" in (proposed_action.lower() if proposed_action else ""))
        )
        if alert_triage:
            notify_stakeholders(file, issues + list(detected_fields))
            #alerts.append(f"- [ALERT] **{file_name}**: {', '.join(issues)}  \n  [View file]({url})")
            alerts.append(f"- [ALERT] **{file_name}**: {', '.join(issues)}")
            audit_entry = {
                "File Name": file_name,
                "Data Classification": data_classification,
                "Detected Fields": ', '.join(detected_fields) if detected_fields else '',
                "Location": location,
                "Risk": overall_risk,
                "Last Modified": last_modified,
                "Permissions": "; ".join(all_permissions),
                "Risk Issues": "; ".join(issues) if issues else "",
                "Notes": auditor_notes[:80] + ("..." if len(auditor_notes)>80 else ""),
            }
            audit_rows.append(audit_entry)

    sections = ["# Automated Compliance Report\n"]
    max_alerts_display = 25
    if alerts:
        alert_lines = alerts[:max_alerts_display]
        if len(alerts) > max_alerts_display:
            alert_lines.append(
                f"_⏳ Only first {max_alerts_display} alerts shown. Download full CSV for all alerts._"
            )
        sections.append("## 🔴 Alerts for Files at Risk\n" + "\n".join(alert_lines))
    else:
        sections.append("## ✅ No urgent alerts. All monitored files currently in controlled state.")
    
    if audit_rows:
        max_display = 60
        display_rows = audit_rows[:max_display]
        sections.append("\n## 🗂️ Compliance Evidence Table\n")
        headers = display_rows[0].keys()
        sections.append("| " + " | ".join(headers) + " |")
        sections.append("|" + "|".join(["---"]*len(headers)) + "|")
        for row in display_rows:
            sections.append("| " + " | ".join(str(row[h]) for h in headers) + " |")
        if len(audit_rows) > max_display:
            sections.append(
                f"\n_⏳ Table truncated: only first {max_display} out of {len(audit_rows)} files shown. "
                "Download full CSV/Word for complete details._"
            )
    else:
        sections.append("_No compliance evidence rows (no files triggered alerts)._")

    markdown = "\n".join(sections)
    output_guardrails(markdown)

    csv_url = get_csv_download_link(audit_rows)
    docx_url = robust_write_docx_file(markdown)
    download_docx_md = f"\n\n---\n[⬇️ Download this report as Word (docx)]({docx_url})" if docx_url else ""
    download_csv_md = f"\n\n---\n[⬇️ Download all compliance evidence as CSV]({csv_url})" if csv_url else ""



    max_markdown_len = 28000  # about 7K words, adjust as per your system's real hard cutoff
    if len(markdown) > max_markdown_len:
        markdown = markdown[:max_markdown_len] + "\n\n_Output was truncated to system limits. Download attachments for full details._"

    return {
        "markdown": markdown + download_docx_md + download_csv_md,
        "csv_url": csv_url,
        "docx_url": docx_url,
    }


# def agent_compliance_m365_auto_evidence(context: Dict[str, Any]) -> Dict[str, Any]:
#     """
#     Automated compliance/audit/evidence agent for M365/SharePoint/OneDrive.
#     Looks for risky locations, external shares, regulated data, and writes a markdown report.
#     context must include: corporate_domains (List[str]), config_ids (List), etc.
#     """
#     corporate_domains: List[str] = context.get("corporate_domains")
#     config_ids = context.get("config_ids")

#     if not corporate_domains or not isinstance(corporate_domains, list):
#         raise HTTPException(status_code=400, detail="corporate_domains (list of allowed domains) is required")
#     if not config_ids:
#         raise HTTPException(status_code=400, detail="config_ids (list of user's config IDs) is required")

#     files = gather_all_findings(config_ids) or []
#     if not files:
#         return {"markdown": "_No files found for compliance evidence generation for your account._"}

#     alerts = []
#     audit_rows = []

#     for file in files:
#         file_name = file.get('file_name')
#         url = file.get('web_url')
#         last_modified = file.get('last_modified')
#         location = file.get('parent_reference', '') or file.get('full_path', '') or ''
#         data_classification = file.get('data_classification', '')
#         permissions = file.get('permissions') or []

#         llm = parse_llm_response(file.get("llm_response", {}))

#         # --------- Detected Fields FIX -----------
#         detected_fields = set()
#         compliance_findings = file.get("compliance_findings") or []
#         for cf in compliance_findings:
#             detected_fields.update(cf.get("detected_fields") or [])

#         # Fallback if none found at top-level, try llm_response.results
#         if not detected_fields:
#             results = (llm.get('results') or []) if llm else []
#             for res in results:
#                 detected_fields.update(res.get("detected_fields") or [])

#         # --------- Overall Risk FIX ---------------
#         overall_risk = (file.get("overall_risk_rating") or "").strip().lower()
#         if not overall_risk:
#             overall_risk = (llm.get("overall_risk_rating") or "").strip().lower()
#         auditor_notes = llm.get("auditor_agent_view", "") or ""
#         proposed_action = llm.get("auditor_proposed_action", "") or ""
#         controls = llm.get("cyber_proposed_controls", "") or ""

#         risky_location = any(tag in location.lower() for tag in ("public", "guest", "external", "downloads"))

#         risky_external_share = False
#         risky_permissions = []
#         for perm in permissions:
#             roles = [r.lower() for r in perm.get("roles") or []]
#             for path in ["grantedToIdentitiesV2", "grantedToIdentities", "grantedToV2", "grantedTo"]:
#                 grantees = perm.get(path)
#                 if isinstance(grantees, dict):
#                     grantees = [grantees]
#                 for grantee in grantees or []:
#                     user = grantee.get("user", {}) or grantee.get("siteUser", {}) or grantee.get("group", {}) or grantee.get("siteGroup", {}) or {}
#                     email = user.get("email") or ""
#                     display_name = user.get("displayName") or ""
#                     if any(role in ("write","owner") for role in roles):
#                         if display_name.lower().find("visitor") != -1 or is_external_email(email, corporate_domains):
#                             risky_external_share = True
#                             risky_permissions.append(f"{','.join(roles)} to {display_name or email or '[Unknown]'}")
#             link = perm.get("link", {}) or {}
#             if link.get("scope") and str(link.get("scope")).lower() in ("anonymous","users"):
#                 if any(role in ("write","owner") for role in roles):
#                     risky_external_share = True
#                     risky_permissions.append(f"{','.join(roles)} [LINK: {link.get('scope')}]")

#         all_permissions = []
#         for perm in permissions:
#             roles = ",".join(perm.get("roles") or [])
#             who = []
#             for k in ["grantedToIdentitiesV2", "grantedToIdentities", "grantedToV2", "grantedTo"]:
#                 g = perm.get(k)
#                 if isinstance(g, dict): g = [g]
#                 for u in g or []:
#                     who.append(
#                         u.get("user", {}).get("email")
#                         or u.get("user", {}).get("displayName")
#                         or "[Unknown]"
#                     )
#             if not who:
#                 w2 = perm.get("grantedTo",{}).get("user",{}).get("displayName") or "[Unknown]"
#                 who = [w2]
#             all_permissions.append(f"{roles} to {', '.join(who)}")

#         issues = []
#         if risky_location:
#             issues.append("Sensitive file in risky/public location")
#         if risky_external_share:
#             issues.append("Permissive/external sharing detected")
#         if overall_risk=="high":
#             issues.append("High risk rating")
#         if proposed_action and "notify" in proposed_action.lower():
#             issues.append("Action: " + proposed_action)

#         alert_triage = (
#             (detected_fields and (risky_external_share or risky_location))
#             or overall_risk == "high"
#             or ("notify" in (proposed_action.lower() if proposed_action else ""))
#         )
#         if alert_triage:
#             notify_stakeholders(file, issues + list(detected_fields))
#             alerts.append(f"- [ALERT] **{file_name}**: {', '.join(issues)}  \n  [View file]({url})")

#         audit_entry = {
#             "File Name": file_name,
#             "Data Classification": data_classification,
#             "Detected Fields": ', '.join(detected_fields) if detected_fields else '',
#             "Location": location,
#             "Risk": overall_risk,
#             "Last Modified": last_modified,
#             "Permissions": "; ".join(all_permissions),
#             "Risk Issues": "; ".join(issues) if issues else "",
#             "Notes": auditor_notes[:80] + ("..." if len(auditor_notes)>80 else ""),
#         }
#         audit_rows.append(audit_entry)

#     sections = ["# M365 Automated Compliance Report\n"]
#     if alerts:
#         sections.append("## 🔴 Alerts for Files at Risk\n" + "\n".join(alerts))
#     else:
#         sections.append("## ✅ No urgent alerts. All monitored files currently in controlled state.")

#     if audit_rows:
#         sections.append("\n## 🗂️ Compliance Evidence Table\n")
#         headers = audit_rows[0].keys()
#         sections.append("| " + " | ".join(headers) + " |")
#         sections.append("|" + "|".join(["---"]*len(headers)) + "|")
#         for row in audit_rows:
#             sections.append("| " + " | ".join(str(row[h]) for h in headers) + " |")
#     else:
#         sections.append("_No compliance evidence rows (this indicates an integration issue)._")

#     markdown = "\n".join(sections)
#     output_guardrails(markdown)

#     # === Produce download URLs: CSV and DOCX ===
#     # Use the same audit evidence rows for CSV
#     # Construct minimal "findings" for CSV (convert audit_rows to finding-like dicts or reuse the original file dicts as needed)
#     # We'll produce a CSV of "audit_rows"

#     # Simple way: write audit_rows as CSV (fieldnames from headers)
#     # (get_csv_download_link normally uses list of findings, so we'll reuse audit_rows):
#     csv_url = ""
#     try:
#         csv_url = get_csv_download_link(audit_rows)
#     except Exception as ex:
#         log_to_laravel(f"CSV generation error in M365 compliance agent: {repr(ex)}")

#     docx_url = ""
#     try:
#         docx_url = robust_write_docx_file(markdown)
#     except Exception as ex:
#         log_to_laravel(f"Docx generation error in M365 compliance agent: {repr(ex)}")

#     # Append markdown links for UI
#     download_docx_md = f"\n\n---\n[⬇️ Download this report as Word (docx)]({docx_url})" if docx_url else ""
#     download_csv_md = f"\n\n---\n[⬇️ Download all compliance evidence as CSV]({csv_url})" if csv_url else ""

#     return {
#         "markdown": markdown + download_docx_md + download_csv_md,
#         "csv_url": csv_url,
#         "docx_url": docx_url,
#     }


# === PROVIDE PROMPTS AND LOGIC for All Other Agents, Unmodified (compliance, audit, etc.)
def agent_compliance_advisor(data: Dict[str, Any]):
    prompt = f"""
You are the cybersecai.io compliance expert AI Agent. Your reply must follow the professional standards and compliance guardrails defined by cybersecai.io where applicable.

Inputs:
Standard: {data.get('standard')}
Jurisdiction: {data.get('jurisdiction')}
Details: {data.get('requirement_notes', '')}
Event: {data.get('event_type', '')}
Data: {json.dumps(data.get('data', {}), ensure_ascii=False)}

Instructions:
- Your risk assessment and recommendations are provided as the expert opinion of the cybersecai.io compliance AI Agent, in line with cybersecai.io's guardrails and with full impartiality.
- Clearly indicate this at the end of your response as a formal disclaimer.

Please:
1: Score the privacy risk (LOW, MEDIUM, HIGH).
2: Recommend next action(s) (internal_report, notify_authority, communicate_subjects, public_communication, etc).
3: Identify and summarize any other legal or regulatory compliance obligations that may apply in {data.get('jurisdiction')}, beyond {data.get('standard')} (including sector-specific or cross-jurisdictional duties).
4: Generate a notification/report letter that satisfies both the requirements for this standard and broader obligations in the jurisdiction.
5: Write your full response and letter in polished, formal English and use Markdown formatting. Include risk, action, decision_reason, notification_letter (if required), **and a final section clearly stating:**  
"_This opinion is produced by the cybersecai.io compliance expert AI Agent and is based on its programmed guardrails and up-to-date regulatory analysis._"
    """.strip()
    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": "You are an automated compliance advisor."},
                {"role": "user", "content": prompt}
            ]
        )
        text = resp.choices[0].message.content
        output_guardrails(text)
        return {"markdown": text}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Compliance LLM failed: {str(e)}")
