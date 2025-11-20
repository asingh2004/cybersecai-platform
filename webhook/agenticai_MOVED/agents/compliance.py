

# File: agents/compliance.py
from typing import Dict, Any, List
import json

from fastapi import HTTPException
from config import client
from utils.guardrails import output_guardrails
from utils.logging import log_to_laravel
from utils.csv_export import get_csv_download_link
from utils.docx_export import robust_write_docx_file
from agents.findings import gather_all_findings


__all__ = [
    "agent_compliance_advisor",
    "agent_compliance_m365_auto_evidence",
    "notify_stakeholders",
    "is_external_email",
]


def notify_stakeholders(file: dict, issues: List[str]):
    # Real implementation would notify via email/SIEM/etc. We log for now.
    try:
        log_to_laravel(
            f"[Compliance ALERT] File: '{file.get('file_name')}' at '{file.get('web_url','')}'. "
            f"Issues: {', '.join(issues)}"
        )
    except Exception:
        pass


def is_external_email(email: str, corporate_domains: List[str]) -> bool:
    if not email or not isinstance(email, str):
        return False
    email = email.lower().strip()
    for dom in corporate_domains or []:
        if not dom:
            continue
        d = dom.lower().lstrip("@").strip()
        if email.endswith("@" + d) or email.endswith(d):
            return False
    return True


def agent_compliance_m365_auto_evidence(context: Dict[str, Any]) -> Dict[str, Any]:
    """
    DB-backed compliance evidence generator.
    Uses data_configs.id (context['config_ids']) and user_id to scope findings (via agents.findings).
    """
    corporate_domains: List[str] = context.get("corporate_domains")
    config_ids = context.get("config_ids")
    user_id = context.get("user_id")

    if not corporate_domains or not isinstance(corporate_domains, list):
        raise HTTPException(status_code=400, detail="corporate_domains (list of allowed domains) is required")
    if not config_ids:
        raise HTTPException(status_code=400, detail="config_ids (list of user's config IDs) is required")
    if not user_id:
        raise HTTPException(status_code=400, detail="user_id is required")

    # Pull all-risk findings from DB via the unified fetch in agents.findings
    files = gather_all_findings(config_ids, user_id=user_id) or []
    if not files:
        markdown = "_No files found for compliance evidence generation for your account._"
        csv_url = get_csv_download_link([]) or ''
        docx_url = robust_write_docx_file(markdown) or ''
        download_docx_md = f"\n\n---\n[⬇️ Download as Word (docx)]({docx_url})" if docx_url else ""
        download_csv_md = f"\n\n---\n[⬇️ Download as CSV]({csv_url})" if csv_url else ""
        return {"markdown": markdown + download_docx_md + download_csv_md, "csv_url": csv_url, "docx_url": docx_url}

    alerts: List[str] = []
    audit_rows: List[Dict[str, str]] = []

    for file in files:
        file_name = file.get('file_name')
        url = file.get('web_url')
        last_modified = file.get('last_modified')
        location = file.get('parent_reference', '') or file.get('full_path', '') or ''
        data_classification = file.get('data_classification', '')
        permissions = file.get('permissions') or []  # DB: list of permission dicts

        overall_risk = (file.get("overall_risk_rating") or "").strip().lower()
        auditor_notes = file.get("auditor_agent_view", "") or ""
        proposed_action = file.get("auditor_proposed_action", "") or ""

        # Detected fields from compliance findings
        detected_fields = set()
        for cf in (file.get("compliance_findings") or []):
            for df in cf.get("detected_fields") or []:
                detected_fields.add(df)

        risky_location = any(tag in str(location).lower() for tag in ("public", "guest", "external", "downloads"))

        risky_external_share = False
        risky_permissions = []

        # Interpret DB permission model
        for perm in permissions if isinstance(permissions, list) else []:
            role = (perm.get("role") or "").lower()
            email = perm.get("principal_email") or ""
            display_name = perm.get("principal_display_name") or ""
            if any(x in role for x in ("write", "owner", "edit")):
                if display_name.lower().find("visitor") != -1 or is_external_email(email, corporate_domains):
                    risky_external_share = True
                    risky_permissions.append(f"{role} to {display_name or email or '[Unknown]'}")

        # Summarize all permissions for table
        all_permissions = []
        for perm in permissions if isinstance(permissions, list) else []:
            role = perm.get("role") or ""
            who = perm.get("principal_email") or perm.get("principal_display_name") or "[Unknown]"
            all_permissions.append(f"{role} to {who}")

        issues = []
        if risky_location:
            issues.append("Sensitive file in risky/public location")
        if risky_external_share:
            issues.append("Permissive/external sharing detected")
        if overall_risk == "high":
            issues.append("High risk rating")
        if proposed_action and "notify" in proposed_action.lower():
            issues.append("Action: " + proposed_action)

        # Alert criteria
        alert_triage = (
            (detected_fields and (risky_external_share or risky_location))
            or overall_risk == "high"
            or ("notify" in (proposed_action.lower() if proposed_action else ""))
        )
        if alert_triage:
            notify_stakeholders(file, issues + list(detected_fields))
            alerts.append(f"- [ALERT] **{file_name}**: {', '.join(issues)}")

        audit_rows.append({
            "File Name": file_name or "",
            "Data Classification": data_classification or "",
            "Detected Fields": ', '.join(sorted(detected_fields)) if detected_fields else '',
            "Location": location or "",
            "Risk": overall_risk or "",
            "Last Modified": last_modified or "",
            "Permissions": "; ".join(all_permissions),
            "Risk Issues": "; ".join(issues) if issues else "",
            "Notes": (auditor_notes[:80] + ("..." if len(auditor_notes) > 80 else "")) if auditor_notes else "",
        })

    sections: List[str] = ["# Automated Compliance Report\n"]
    max_alerts_display = 25
    if alerts:
        alert_lines = alerts[:max_alerts_display]
        if len(alerts) > max_alerts_display:
            alert_lines.append(f"_⏳ Only first {max_alerts_display} alerts shown. Download full CSV for all alerts._")
        sections.append("## 🔴 Alerts for Files at Risk\n" + "\n".join(alert_lines))
    else:
        sections.append("## ✅ No urgent alerts. All monitored files currently in controlled state.")

    if audit_rows:
        max_display = 60
        display_rows = audit_rows[:max_display]
        sections.append("\n## 🗂️ Compliance Evidence Table\n")
        headers = list(display_rows[0].keys())
        sections.append("| " + " | ".join(headers) + " |")
        sections.append("|" + "|".join(["---"] * len(headers)) + "|")
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

    max_markdown_len = 28000
    if len(markdown) > max_markdown_len:
        markdown = markdown[:max_markdown_len] + "\n\n_Output was truncated to system limits. Download attachments for full details._"

    return {
        "markdown": markdown + download_docx_md + download_csv_md,
        "csv_url": csv_url,
        "docx_url": docx_url,
    }


def agent_compliance_advisor(data: Dict[str, Any]):
    """
    Lightweight compliance advisory generator (LLM).
    This is imported by orchestrator.agent_registry; keep the name exactly.
    """
    prompt = f"""
You are the cybersecai.io compliance expert AI Agent. Your reply must follow the professional standards
and compliance guardrails defined by cybersecai.io where applicable.

Inputs:
Standard: {data.get('standard')}
Jurisdiction: {data.get('jurisdiction')}
Details: {data.get('requirement_notes', '')}
Event: {data.get('event_type', '')}
Data: {json.dumps(data.get('data', {}), ensure_ascii=False)}

Instructions:
- Your risk assessment and recommendations are provided as the expert opinion of the cybersecai.io compliance AI Agent.
- Clearly include a formal disclaimer at the end of your response.

Please:
1. Score the privacy risk (LOW, MEDIUM, HIGH).
2. Recommend next action(s) (internal_report, notify_authority, communicate_subjects, public_communication, etc).
3. Identify and summarize any other legal/regulatory obligations in {data.get('jurisdiction')} beyond {data.get('standard')}.
4. Generate a notification/report letter that satisfies the standard and broader obligations for the jurisdiction.
5. Write in polished, formal English and use Markdown. Include risk, action, decision_reason, notification_letter (if required),
   and a final section clearly stating: "_This opinion is produced by the cybersecai.io compliance expert AI Agent and is based on its programmed guardrails and up-to-date regulatory analysis._"
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