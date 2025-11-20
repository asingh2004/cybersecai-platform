from utils.logging import log_to_laravel
from utils.markdown_format import format_high_risk_files_markdown, format_all_risks_files_markdown, format_medium_risk_files_markdown
from utils.dateparse import parse_date_from_query
from typing import Dict, Any, Optional, List
import os, json, re, glob
from config import client
from utils.guardrails import output_guardrails
from collections import defaultdict
import csv 
from datetime import datetime, timedelta, timezone
from agents.cybersec import (
    find_duplicate_files,
    get_externally_shared_files,
    highrisk_path,
    allrisk_path,
    count_files,
    count_high_risk_files,
)


# def agent_audit(data: Dict[str, Any]):
  
#     prompt = (
#         f"You are a highly professional internal auditor preparing a risk report for the Board of Directors in {data.get('region')}. "
#         "You are provided with AI-generated file risk summaries from the cybersecai.io platform, each record assessed as HIGH overall risk. "
#         "Each file record contains information such as file name, the 'auditor_proposed_action', last modified time, data classification, and business area."
#         "You must analyze, compare, and highlight high-risk file changes and trends across time windows: "
#         "specifically, (1) the last 1 month, and (2) the prior 2-month window (excluding the most recent month). "
#         "Help the Board understand what risks are *new*, what has *persisted*, and what is *escalating or recurring*."
#         "\n\n"
#         f"Raw JSON data to audit is below:\n\n{data.get('json_data')}\n\n"
#         "Your report MUST use Markdown headings, clear Board-level language, and be structured as follows:\n"
#         "1. ### Executive Summary\n"
#         "   - Concisely describe the organization's overall exposure, and highlight any sudden changes or escalations in the last month.\n"
#         "2. ### Trends and Red Flags: Last 1 Month\n"
#         "   - Analyze and bullet key risk trends in just the last month (by file type, department, business area).\n"
#         "   - Markdown table: list files changed/flagged in the last month, with columns for file name, last modified, data type, proposed action, and any Board-urgent flags (use bold/callouts if needed).\n"
#         "   - List any *new* or *escalated* risks not seen in the previous months.\n"
#         "3. ### Trends and Red Flags: 1-2 Months Ago\n"
#         "   - Analyze and bullet patterns in the window 1-2 months ago. Show its own Markdown table (same columns).\n"
#         "   - Summarize risks that have persisted (appearing in both tables), and risks that were resolved or remediated.\n"
#         "4. ### Comparative Analysis\n"
#         "   - Compare the two time periods. Clearly highlight:\n"
#         "     * New/recurring risks in the last month\n"
#         "     * Which risks have emerged, worsened, or faded\n"
#         "     * Any areas where risk control has improved or slipped\n"
#         "     * Any files/actions needing Board-level escalation or urgent review\n"
#         "5. ### Governance & Compliance Implications\n"
#         "   - Describe, for {data.get('region')}, what legal/reporting obligations arise out of the last 1 month’s findings (especially if new/escalated). Flag if formal authority notification or public statement may be required.\n"
#         "6. ### Board-Ready Recommendations\n"
#         "   - Numbered, actionable next steps for the Board, using Board-resolution language (who, what, urgent deadlines)."
#         "7. ### Risk Evolution Tables\n"
#         "   - Render side-by-side (or one after the other) Markdown tables summarizing high-risk file counts by key action/type for each period (last month vs prior).\n"
#         "\nStrictly do NOT repeat or dump raw JSON—synthesize, cluster, and write for the Board’s review. Use Markdown headings (### ...), bullets, and tables throughout. Highlight urgent items in bold or with callouts!"
#     )
#     try:
#         resp = client.chat.completions.create(
#             model="gpt-4.1",
#             messages=[
#                 {"role": "system", "content": "You are a world class internal auditor."},
#                 {"role": "user", "content": prompt}
#             ]
#         )
#         output = resp.choices[0].message.content
#         output_guardrails(output)
#         return {"markdown": output}
#     except Exception as e:
#         raise HTTPException(status_code=500, detail=f"Audit LLM failed: {str(e)}")



def agent_audit_dashboard(context):
    followups = [
        {
            "label": "Board Executive Summary",
            "operation": "audit_board_summary",
            "args": {},
            "prompt": "Give me an executive summary for the board."
        },
        {
            "label": "Full Board-Level Audit Report",
            "operation": "audit_full",
            "args": {},
            "prompt": "Show the full board-level audit report."
        },
        {
            "label": "Show Audit Evidence (tables)",
            "operation": "audit_evidence",
            "args": {},
            "prompt": "Show me all audit evidence tables."
        },
    ] + get_audit_no_action_followup()
    intro = "**Audit/Compliance Overview**\n\nUse the options below to review Board or audit-ready summaries, evidence, or full reports."
    return {"reply": intro, "followups": followups}



def agent_audit_full(context):
    result = _agent_audit_base(context)
    return {
        "reply": result["markdown"],
        "followups": [
            {
                "label": "Board Executive Summary",
                "operation": "audit_board_summary",
                "args": {},
                "prompt": "Give me an executive summary for the board."
            },
            {
                "label": "Show Audit Evidence (tables)",
                "operation": "audit_evidence",
                "args": {},
                "prompt": "Show me all audit evidence tables."
            }
        ] + get_audit_no_action_followup()
    }

def agent_audit_board_summary(context):
    result = _agent_audit_board_summary_base(context)
    return {
        "reply": result["markdown"],
        "followups": [
            {
                "label": "Full Board-Level Audit Report",
                "operation": "audit_full",
                "args": {},
                "prompt": "Show the full board-level audit report."
            },
            {
                "label": "Show Audit Evidence (tables)",
                "operation": "audit_evidence",
                "args": {},
                "prompt": "Show me all audit evidence tables."
            }
        ] + get_audit_no_action_followup()
    }

def _agent_audit_base(context):
    """
    Internal audit Board report that leverages the same evidence pipeline as the cybersec agent:
    - High-risk duplicate and externally shared evidence (tables)
    - High-risk per-file profiles including auditor/compliance/controls metadata
    - Time-window segmentation: last 1 month vs prior 2-month window based on last_modified
    - Returns LLM narrative + verbatim evidence sections (profiles + technical appendix tables)
    """
    # Required input
    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    if not user_id:
        raise ValueError("agent_audit requires 'user_id' in data.")

    from agents.cybersec import (
        find_duplicate_files,
        get_externally_shared_files,
        highrisk_path,
        allrisk_path,
        count_files,
        count_high_risk_files,
    )

    import os, csv, re
    from collections import defaultdict
    from datetime import datetime, timedelta, timezone

    # 1) High-risk evidence tables (duplicates + external)
    dupes_high_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
    external_high_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

    evidence_high_md = "\n\n".join([
        "## High-Risk Files Present in Multiple Storage Locations",
        dupes_high_md if "No duplicate files detected" not in dupes_high_md else "_No high-risk duplicate files detected._",
        "",
        "## High-Risk Files That Are Externally Shared",
        external_high_md if "No externally shared files detected" not in external_high_md else "_No high-risk externally shared files detected._"
    ])

    # 2) Build high-risk per-file profiles from CSV (same approach as cybersec agent)
    INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}
    email_re = re.compile(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}')

    def truncate(text, limit=800):
        t = (text or "").strip()
        return t if len(t) <= limit else t[:limit - 1] + "…"

    risk_order = {
        "critical": 0, "very high": 1, "high": 2,
        "medium": 3, "moderate": 4,
        "low": 5, "very low": 6,
        "none": 7, "": 8
    }
    def risk_key(r):
        return risk_order.get((r or "").strip().lower(), 8)

    # Parse HighRisk CSV
    high_path = highrisk_path(user_id)
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
        "cyber_proposed_controls": "",
        "permissions": "",
        "external_recipients": set()
    })

    # Time window buckets for Board trend sections

    now = datetime.now(timezone.utc)
    last_month_start = now - timedelta(days=30)
    prior_two_months_start = last_month_start - timedelta(days=60)
    recent_window = []   # last 1 month
    prior_window = []    # 1-2 months before last month

    def parse_iso(dt_str: str):
        if not dt_str:
            return None
        try:
            # Handles strings like 2025-05-20T11:17:36+00:00 or Z
            dt = datetime.fromisoformat(dt_str.replace("Z", "+00:00"))
            if dt.tzinfo is None:
                dt = dt.replace(tzinfo=timezone.utc)
            return dt
        except Exception:
            return None

    if os.path.isfile(high_path):
        with open(high_path, newline="", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for row in reader:
                name = (row.get("file_name") or "").strip()
                if not name:
                    continue
                d = per_file[name]
                d["file_name"] = name or d["file_name"]

                # Use new backend_source (with fallback)
                storage = (row.get("backend_source") or row.get("data_source") or "").strip()
                if storage:
                    d["storages"].add(storage)

                full_path = (row.get("full_path") or "").strip()
                file_path = (row.get("file_path") or "").strip()
                if full_path:
                    d["paths"].add(full_path)
                elif file_path:
                    d["paths"].add(file_path)

                # Metadata (first non-empty wins)
                for k_csv, k_store in [
                    ("last_modified", "last_modified"),
                    ("created", "created"),
                    ("overall_risk_rating", "overall_risk_rating"),
                    ("data_classification", "data_classification"),
                    ("likely_data_subject_area", "likely_data_subject_area"),
                    ("auditor_agent_view", "auditor_agent_view"),
                    ("auditor_proposed_action", "auditor_proposed_action"),
                    ("compliance_findings", "compliance_findings"),
                    ("cyber_proposed_controls", "cyber_proposed_controls"),
                    ("permissions", "permissions"),
                ]:
                    val = (row.get(k_csv) or "").strip()
                    if val and not d[k_store]:
                        d[k_store] = val

                # External recipients (filter internal)
                perms = (row.get("permissions") or "")
                if "@" in perms:
                    found = set(email_re.findall(perms))
                    for email in found:
                        e = email.lower()
                        if not any(e.endswith("@" + dom) for dom in INTERNAL_DOMAINS):
                            d["external_recipients"].add(email)

                # Time windows based on last_modified
                lm = parse_iso(row.get("last_modified") or "")
                if lm:
                    if lm >= last_month_start:
                        recent_window.append({
                            "file_name": name,
                            "last_modified": lm.isoformat(),
                            "data_classification": (row.get("data_classification") or "").strip(),
                            "proposed_action": (row.get("auditor_proposed_action") or "").strip(),
                            "risk": (row.get("overall_risk_rating") or "").strip()
                        })
                    elif prior_two_months_start <= lm < last_month_start:
                        prior_window.append({
                            "file_name": name,
                            "last_modified": lm.isoformat(),
                            "data_classification": (row.get("data_classification") or "").strip(),
                            "proposed_action": (row.get("auditor_proposed_action") or "").strip(),
                            "risk": (row.get("overall_risk_rating") or "").strip()
                        })

    # Build high-risk per-file profile markdown
    sections = []
    sorted_names = sorted(per_file.keys(), key=lambda n: (risk_key(per_file[n]["overall_risk_rating"]), n.lower()))
    for name in sorted_names:
        d = per_file[name]
        is_duplicate = len(d["storages"]) > 1
        is_external = len(d["external_recipients"]) > 0
        if not (is_duplicate or is_external):
            continue

        storages_str = ", ".join(sorted(d["storages"])) or "Unknown"
        paths_sorted = sorted(d["paths"])
        paths_preview = ", ".join(paths_sorted[:3]) + (f" (+{len(paths_sorted)-3} more)" if len(paths_sorted) > 3 else "")
        recipients = ", ".join(sorted(d["external_recipients"])) if is_external else ""
        exposures = []
        if is_duplicate:
            exposures.append(f"Duplicate across storages: {storages_str}")
        if is_external:
            exposures.append(f"Externally shared with: {recipients or '[unknown recipients]'}")
        exposure_line = "; ".join(exposures) if exposures else "None detected"

        section = [
            f"### File: `{name}`",
            "",
            "| Attribute | Value |",
            "|---|---|",
            f"| Overall Risk Rating | {d['overall_risk_rating'] or 'Unknown'} |",
            f"| Data Classification | {d['data_classification'] or 'Unknown'} |",
            f"| Likely Data Subject Area | {truncate(d['likely_data_subject_area']) or 'N/A'} |",
            f"| Storages | {storages_str} |",
            f"| Paths | {paths_preview or 'N/A'} |",
            f"| Last Modified | {d['last_modified'] or 'Unknown'} |",
            f"| Created | {d['created'] or 'Unknown'} |",
            f"| Exposure | {exposure_line} |",
            "",
            "**Auditor Agent View**",
            "",
            truncate(d["auditor_agent_view"]) or "_None provided._",
            "",
            "**Compliance Findings**",
            "",
            truncate(d["compliance_findings"]) or "_None provided._",
            "",
            "**Proposed Controls (Cyber/ISO/NIST)**",
            "",
            truncate(d["cyber_proposed_controls"] or d["auditor_proposed_action"]) or "_None provided._",
            ""
        ]
        sections.append("\n".join(section))

    highrisk_profiles_md = (
        "## High-Risk File Details (Profiles)\n"
        "Below are detailed attributes for high-risk files that are duplicates and/or externally shared.\n\n"
        + ("\n\n".join(sections) if sections else "_No high-risk files with duplicate or external exposures required detailed profiling._")
    )

    # 3) Time-window tables for the Board
    def render_window_table(rows, title):
        if not rows:
            return f"### {title}\n_No high-risk file changes detected in this window._"
        rows_sorted = sorted(rows, key=lambda r: (risk_key(r.get("risk")), r.get("last_modified", "")), reverse=False)
        lines = [
            f"### {title}",
            "",
            "| File Name | Last Modified | Data Type | Proposed Action | Risk |",
            "|-----------|---------------|-----------|-----------------|------|"
        ]
        for r in rows_sorted:
            lines.append(f"| `{r['file_name']}` | {r['last_modified']} | {r['data_classification'] or ''} | {r['proposed_action'] or ''} | {r['risk'] or ''} |")
        return "\n".join(lines)

    last_month_table = render_window_table(recent_window, "Trends and Red Flags: Last 1 Month")
    prior_two_months_table = render_window_table(prior_window, "Trends and Red Flags: 1–2 Months Before Last Month")

    # 4) Technical appendix (ALL RISKS) — real tables
    dupes_all_md = find_duplicate_files(user_id=user_id, high_risk_only=False)
    external_all_md = get_externally_shared_files(user_id=user_id, high_risk_only=False)
    appendix_md = (
        "## Appendix: Full File Exposure Details\n\n"
        "### Appendix A: All Duplicate Files (Any Risk)\n"
        "The table below lists all files (regardless of risk) that appear in multiple storage locations or folders:\n\n"
        + (dupes_all_md if "No duplicate files detected" not in dupes_all_md else "_No files with duplicates detected in the environment._")
        + "\n\n"
        "### Appendix B: All Externally Shared Files (Any Risk)\n"
        "This table lists all files shared with external addresses, regardless of risk rating:\n\n"
        + (external_all_md if "No externally shared files detected" not in external_all_md else "_No externally shared files detected in the environment._")
    )

    # 5) LLM prompt for Board-level report (uses evidence + profiles + time windows)
    now_utc = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%SZ")
    intro_banner = (
        "## CyberSecAI Internal Audit Board Report\n\n"
        "This report has been generated by the Expert Cyber AI Agent (CyberSecAI) after analyzing your organization's files "
        "that are monitored by the cybersecai.io platform. It synthesizes Board-ready insights on high‑risk file exposures, "
        "including duplicates across storages and externally shared items, and trends across the most recent time windows.\n\n"
        f"- Generated: {now_utc} UTC\n"
        f"- Tenant/User: `{user_id}`\n"
        f"- Region: {region}\n"
        "- Scope: High-risk files (last modified within the relevant windows) that are duplicated and/or shared externally\n"
    )

    # NEW: Cybersecurity Overview summary right after the intro
    total_files = count_files(user_id=user_id)
    highrisk_count = count_high_risk_files(user_id=user_id)
    summary_md = (
        "### Cybersecurity Overview\n\n"
        f"- Total files: **{total_files}**\n"
        f"- High risk files: **{highrisk_count}**\n"
    )

    board_prompt = (
        "You are a highly professional internal auditor preparing a Board-level risk report. "
        "Use the evidence and time-window tables below to:\n"
        "1) Write a concise Executive Summary highlighting overall exposure and any sudden changes in the last month.\n"
        "2) Analyze trends and red flags for the last 1 month and the prior 2-month window (excluding the most recent month).\n"
        "3) Provide a comparative analysis (new/recurring/escalating vs resolved risks).\n"
        "4) Describe governance & compliance implications for the specified region.\n"
        "5) Provide Board-ready recommendations (who/what/when), prioritizing actions.\n\n"
        "Important:\n"
        "- Synthesize and cluster; do NOT dump raw data.\n"
        "- Use Markdown headings, bullets, and tables where helpful.\n"
        "- Refer readers to the Technical Appendix for full file tables; do not rewrite those tables.\n\n"
        "### High-Risk Evidence (Tables)\n"
        f"{evidence_high_md}\n\n"
        "### High-Risk File Details (Profiles)\n"
        f"{highrisk_profiles_md}\n\n"
        "### Time-Window Evidence\n"
        f"{last_month_table}\n\n"
        f"{prior_two_months_table}\n"
    )

    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            temperature=0.2,
            messages=[
                {"role": "system", "content": "You are a world-class internal auditor writing for a Board of Directors. Respond in Markdown."},
                {"role": "user", "content": intro_banner + "\n\n" + board_prompt}
            ]
        )
        narrative = resp.choices[0].message.content
        output_guardrails(narrative)

        # Final stitched output: Intro + Summary + LLM narrative + high-risk profiles + technical appendix
        full_markdown = (
            f"{intro_banner}\n"
            f"{summary_md}\n"
            f"{narrative}\n\n"
            "---\n\n"
            f"{highrisk_profiles_md}\n\n"
            "---\n\n"
            f"{appendix_md}"
        )
        return {"markdown": full_markdown}
    except Exception as e:
        # Optionally log
        try:
            log_to_laravel("agent_audit_error", {"error": str(e)})
        except Exception:
            pass
        from fastapi import HTTPException
        raise HTTPException(status_code=500, detail=f"Audit LLM failed: {str(e)}")

# def _agent_audit_base(context):

# #def agent_audit(context):
#     """
#     Internal audit Board report that leverages the same evidence pipeline as the cybersec agent:
#     - High-risk duplicate and externally shared evidence (tables)
#     - High-risk per-file profiles including auditor/compliance/controls metadata
#     - Time-window segmentation: last 1 month vs prior 2-month window based on last_modified
#     - Returns LLM narrative + verbatim evidence sections (profiles + technical appendix tables)
#     """
#     # Required input
#     user_id = context.get("user_id")
#     region = context.get("region") or "Australia"
#     if not user_id:
#         raise ValueError("agent_audit requires 'user_id' in data.")

#     # Import evidence builders and counters from the cybersec agent to ensure consistency
#     from agents.cybersec import (
#         find_duplicate_files,
#         get_externally_shared_files,
#         highrisk_path,
#         allrisk_path,
#         count_files,
#         count_high_risk_files,
#     )

#     # 1) High-risk evidence tables (duplicates + external)
#     dupes_high_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
#     external_high_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

#     evidence_high_md = "\n\n".join([
#         "## High-Risk Files Present in Multiple Storage Locations",
#         dupes_high_md if "No duplicate files detected" not in dupes_high_md else "_No high-risk duplicate files detected._",
#         "",
#         "## High-Risk Files That Are Externally Shared",
#         external_high_md if "No externally shared files detected" not in external_high_md else "_No high-risk externally shared files detected._"
#     ])

#     # 2) Build high-risk per-file profiles from CSV (same approach as cybersec agent)
#     INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}
#     email_re = re.compile(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}')

#     def truncate(text, limit=800):
#         t = (text or "").strip()
#         return t if len(t) <= limit else t[:limit - 1] + "…"

#     risk_order = {
#         "critical": 0, "very high": 1, "high": 2,
#         "medium": 3, "moderate": 4,
#         "low": 5, "very low": 6,
#         "none": 7, "": 8
#     }
#     def risk_key(r):
#         return risk_order.get((r or "").strip().lower(), 8)

#     # Parse HighRisk CSV
#     high_path = highrisk_path(user_id)
#     per_file = defaultdict(lambda: {
#         "file_name": "",
#         "storages": set(),
#         "paths": set(),
#         "last_modified": "",
#         "created": "",
#         "overall_risk_rating": "",
#         "data_classification": "",
#         "likely_data_subject_area": "",
#         "auditor_agent_view": "",
#         "auditor_proposed_action": "",
#         "compliance_findings": "",
#         "cyber_proposed_controls": "",
#         "permissions": "",
#         "external_recipients": set()
#     })

#     # Time window buckets for Board trend sections

#     now = datetime.now(timezone.utc)
#     last_month_start = now - timedelta(days=30)
#     prior_two_months_start = last_month_start - timedelta(days=60)
#     recent_window = []   # last 1 month
#     prior_window = []    # 1-2 months before last month

#     def parse_iso(dt_str: str):
#         if not dt_str:
#             return None
#         try:
#             # Handles strings like 2025-05-20T11:17:36+00:00 or Z
#             dt = datetime.fromisoformat(dt_str.replace("Z", "+00:00"))
#             if dt.tzinfo is None:
#                 dt = dt.replace(tzinfo=timezone.utc)
#             return dt
#         except Exception:
#             return None

#     if os.path.isfile(high_path):
#         with open(high_path, newline="", encoding="utf-8") as f:
#             reader = csv.DictReader(f)
#             for row in reader:
#                 name = (row.get("file_name") or "").strip()
#                 if not name:
#                     continue
#                 d = per_file[name]
#                 d["file_name"] = name or d["file_name"]

#                 storage = (row.get("data_source") or "").strip()
#                 if storage:
#                     d["storages"].add(storage)

#                 full_path = (row.get("full_path") or "").strip()
#                 file_path = (row.get("file_path") or "").strip()
#                 if full_path:
#                     d["paths"].add(full_path)
#                 elif file_path:
#                     d["paths"].add(file_path)

#                 # Metadata (first non-empty wins)
#                 for k_csv, k_store in [
#                     ("last_modified", "last_modified"),
#                     ("created", "created"),
#                     ("overall_risk_rating", "overall_risk_rating"),
#                     ("data_classification", "data_classification"),
#                     ("likely_data_subject_area", "likely_data_subject_area"),
#                     ("auditor_agent_view", "auditor_agent_view"),
#                     ("auditor_proposed_action", "auditor_proposed_action"),
#                     ("compliance_findings", "compliance_findings"),
#                     ("cyber_proposed_controls", "cyber_proposed_controls"),
#                     ("permissions", "permissions"),
#                 ]:
#                     val = (row.get(k_csv) or "").strip()
#                     if val and not d[k_store]:
#                         d[k_store] = val

#                 # External recipients (filter internal)
#                 perms = (row.get("permissions") or "")
#                 if "@" in perms:
#                     found = set(email_re.findall(perms))
#                     for email in found:
#                         e = email.lower()
#                         if not any(e.endswith("@" + dom) for dom in INTERNAL_DOMAINS):
#                             d["external_recipients"].add(email)

#                 # Time windows based on last_modified
#                 lm = parse_iso(row.get("last_modified") or "")
#                 if lm:
#                     if lm >= last_month_start:
#                         recent_window.append({
#                             "file_name": name,
#                             "last_modified": lm.isoformat(),
#                             "data_classification": (row.get("data_classification") or "").strip(),
#                             "proposed_action": (row.get("auditor_proposed_action") or "").strip(),
#                             "risk": (row.get("overall_risk_rating") or "").strip()
#                         })
#                     elif prior_two_months_start <= lm < last_month_start:
#                         prior_window.append({
#                             "file_name": name,
#                             "last_modified": lm.isoformat(),
#                             "data_classification": (row.get("data_classification") or "").strip(),
#                             "proposed_action": (row.get("auditor_proposed_action") or "").strip(),
#                             "risk": (row.get("overall_risk_rating") or "").strip()
#                         })

#     # Build high-risk per-file profile markdown
#     sections = []
#     sorted_names = sorted(per_file.keys(), key=lambda n: (risk_key(per_file[n]["overall_risk_rating"]), n.lower()))
#     for name in sorted_names:
#         d = per_file[name]
#         is_duplicate = len(d["storages"]) > 1
#         is_external = len(d["external_recipients"]) > 0
#         if not (is_duplicate or is_external):
#             continue

#         storages_str = ", ".join(sorted(d["storages"])) or "Unknown"
#         paths_sorted = sorted(d["paths"])
#         paths_preview = ", ".join(paths_sorted[:3]) + (f" (+{len(paths_sorted)-3} more)" if len(paths_sorted) > 3 else "")
#         recipients = ", ".join(sorted(d["external_recipients"])) if is_external else ""
#         exposures = []
#         if is_duplicate:
#             exposures.append(f"Duplicate across storages: {storages_str}")
#         if is_external:
#             exposures.append(f"Externally shared with: {recipients or '[unknown recipients]'}")
#         exposure_line = "; ".join(exposures) if exposures else "None detected"

#         section = [
#             f"### File: `{name}`",
#             "",
#             "| Attribute | Value |",
#             "|---|---|",
#             f"| Overall Risk Rating | {d['overall_risk_rating'] or 'Unknown'} |",
#             f"| Data Classification | {d['data_classification'] or 'Unknown'} |",
#             f"| Likely Data Subject Area | {truncate(d['likely_data_subject_area']) or 'N/A'} |",
#             f"| Storages | {storages_str} |",
#             f"| Paths | {paths_preview or 'N/A'} |",
#             f"| Last Modified | {d['last_modified'] or 'Unknown'} |",
#             f"| Created | {d['created'] or 'Unknown'} |",
#             f"| Exposure | {exposure_line} |",
#             "",
#             "**Auditor Agent View**",
#             "",
#             truncate(d["auditor_agent_view"]) or "_None provided._",
#             "",
#             "**Compliance Findings**",
#             "",
#             truncate(d["compliance_findings"]) or "_None provided._",
#             "",
#             "**Proposed Controls (Cyber/ISO/NIST)**",
#             "",
#             truncate(d["cyber_proposed_controls"] or d["auditor_proposed_action"]) or "_None provided._",
#             ""
#         ]
#         sections.append("\n".join(section))

#     highrisk_profiles_md = (
#         "## High-Risk File Details (Profiles)\n"
#         "Below are detailed attributes for high-risk files that are duplicates and/or externally shared.\n\n"
#         + ("\n\n".join(sections) if sections else "_No high-risk files with duplicate or external exposures required detailed profiling._")
#     )

#     # 3) Time-window tables for the Board
#     def render_window_table(rows, title):
#         if not rows:
#             return f"### {title}\n_No high-risk file changes detected in this window._"
#         rows_sorted = sorted(rows, key=lambda r: (risk_key(r.get("risk")), r.get("last_modified", "")), reverse=False)
#         lines = [
#             f"### {title}",
#             "",
#             "| File Name | Last Modified | Data Type | Proposed Action | Risk |",
#             "|-----------|---------------|-----------|-----------------|------|"
#         ]
#         for r in rows_sorted:
#             lines.append(f"| `{r['file_name']}` | {r['last_modified']} | {r['data_classification'] or ''} | {r['proposed_action'] or ''} | {r['risk'] or ''} |")
#         return "\n".join(lines)

#     last_month_table = render_window_table(recent_window, "Trends and Red Flags: Last 1 Month")
#     prior_two_months_table = render_window_table(prior_window, "Trends and Red Flags: 1–2 Months Before Last Month")

#     # 4) Technical appendix (ALL RISKS) — real tables
#     dupes_all_md = find_duplicate_files(user_id=user_id, high_risk_only=False)
#     external_all_md = get_externally_shared_files(user_id=user_id, high_risk_only=False)
#     appendix_md = (
#         "## Appendix: Full File Exposure Details\n\n"
#         "### Appendix A: All Duplicate Files (Any Risk)\n"
#         "The table below lists all files (regardless of risk) that appear in multiple storage locations or folders:\n\n"
#         + (dupes_all_md if "No duplicate files detected" not in dupes_all_md else "_No files with duplicates detected in the environment._")
#         + "\n\n"
#         "### Appendix B: All Externally Shared Files (Any Risk)\n"
#         "This table lists all files shared with external addresses, regardless of risk rating:\n\n"
#         + (external_all_md if "No externally shared files detected" not in external_all_md else "_No externally shared files detected in the environment._")
#     )

#     # 5) LLM prompt for Board-level report (uses evidence + profiles + time windows)
#     now_utc = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%SZ")
#     intro_banner = (
#         "## CyberSecAI Internal Audit Board Report\n\n"
#         "This report has been generated by the Expert Cyber AI Agent (CyberSecAI) after analyzing your organization's files "
#         "that are monitored by the cybersecai.io platform. It synthesizes Board-ready insights on high‑risk file exposures, "
#         "including duplicates across storages and externally shared items, and trends across the most recent time windows.\n\n"
#         f"- Generated: {now_utc} UTC\n"
#         f"- Tenant/User: `{user_id}`\n"
#         f"- Region: {region}\n"
#         "- Scope: High-risk files (last modified within the relevant windows) that are duplicated and/or shared externally\n"
#     )

#     # NEW: Cybersecurity Overview summary right after the intro
#     total_files = count_files(user_id=user_id)
#     highrisk_count = count_high_risk_files(user_id=user_id)
#     summary_md = (
#         "### Cybersecurity Overview\n\n"
#         f"- Total files: **{total_files}**\n"
#         f"- High risk files: **{highrisk_count}**\n"
#     )

#     board_prompt = (
#         "You are a highly professional internal auditor preparing a Board-level risk report. "
#         "Use the evidence and time-window tables below to:\n"
#         "1) Write a concise Executive Summary highlighting overall exposure and any sudden changes in the last month.\n"
#         "2) Analyze trends and red flags for the last 1 month and the prior 2-month window (excluding the most recent month).\n"
#         "3) Provide a comparative analysis (new/recurring/escalating vs resolved risks).\n"
#         "4) Describe governance & compliance implications for the specified region.\n"
#         "5) Provide Board-ready recommendations (who/what/when), prioritizing actions.\n\n"
#         "Important:\n"
#         "- Synthesize and cluster; do NOT dump raw data.\n"
#         "- Use Markdown headings, bullets, and tables where helpful.\n"
#         "- Refer readers to the Technical Appendix for full file tables; do not rewrite those tables.\n\n"
#         "### High-Risk Evidence (Tables)\n"
#         f"{evidence_high_md}\n\n"
#         "### High-Risk File Details (Profiles)\n"
#         f"{highrisk_profiles_md}\n\n"
#         "### Time-Window Evidence\n"
#         f"{last_month_table}\n\n"
#         f"{prior_two_months_table}\n"
#     )

#     try:
#         resp = client.chat.completions.create(
#             model="gpt-4.1",
#             temperature=0.2,
#             messages=[
#                 {"role": "system", "content": "You are a world-class internal auditor writing for a Board of Directors. Respond in Markdown."},
#                 {"role": "user", "content": intro_banner + "\n\n" + board_prompt}
#             ]
#         )
#         narrative = resp.choices[0].message.content
#         output_guardrails(narrative)

#         # Final stitched output: Intro + Summary + LLM narrative + high-risk profiles + technical appendix
#         full_markdown = (
#             f"{intro_banner}\n"
#             f"{summary_md}\n"
#             f"{narrative}\n\n"
#             "---\n\n"
#             f"{highrisk_profiles_md}\n\n"
#             "---\n\n"
#             f"{appendix_md}"
#         )
#         return {"markdown": full_markdown}
#     except Exception as e:
#         # Optionally log
#         try:
#             log_to_laravel("agent_audit_error", {"error": str(e)})
#         except Exception:
#             pass
#         from fastapi import HTTPException
#         raise HTTPException(status_code=500, detail=f"Audit LLM failed: {str(e)}")


def _agent_audit_board_summary_base(context):
    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    use_case_label = (context.get("label") or context.get("use_case") or "").strip()
    if not user_id:
        raise ValueError("agent_audit_board_summary requires 'user_id' in context.")

    from agents.cybersec import (
        find_duplicate_files,
        get_externally_shared_files,
        highrisk_path,
        count_files,
        count_high_risk_files,
    )

    import os, csv, re
    from collections import defaultdict
    from datetime import datetime, timedelta, timezone

    # KPI counters
    total_files = count_files(user_id=user_id)
    highrisk_count = count_high_risk_files(user_id=user_id)
    highrisk_pct = round(100.0 * highrisk_count / total_files, 2) if total_files else 0.0

    # Evidence tables (reuse existing renderers so UI remains consistent)
    dupes_high_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
    external_high_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

    # Parse HighRisk CSV once for fast insights
    INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}
    email_re = re.compile(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}')

    now = datetime.now(timezone.utc)
    last_month_start = now - timedelta(days=30)
    prior_two_months_start = last_month_start - timedelta(days=60)

    high_path = highrisk_path(user_id)
    per_file = defaultdict(lambda: {
        "file_name": "",
        "storages": set(),
        "paths": set(),
        "last_modified": "",
        "overall_risk_rating": "",
        "data_classification": "",
        "business_area": "",
        "auditor_proposed_action": "",
        "external_recipients": set()
    })

    recent_window = []  # last 1 month
    prior_window = []   # prior 2 months (excluding last month)

    risk_order = {"critical": 0, "very high": 1, "high": 2, "medium": 3, "moderate": 4,
                  "low": 5, "very low": 6, "none": 7, "": 8}
    def risk_key(r):
        return risk_order.get((r or "").strip().lower(), 8)

    def parse_iso(dt_str: str):
        if not dt_str:
            return None
        try:
            dt = datetime.fromisoformat(dt_str.replace("Z", "+00:00"))
            if dt.tzinfo is None:
                dt = dt.replace(tzinfo=timezone.utc)
            return dt
        except Exception:
            return None

    if os.path.isfile(high_path):
        with open(high_path, newline="", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for row in reader:
                name = (row.get("file_name") or "").strip()
                if not name:
                    continue
                d = per_file[name]
                d["file_name"] = name
                # Updated storage field below:
                storage = (row.get("backend_source") or row.get("data_source") or "").strip()
                if storage:
                    d["storages"].add(storage)
                full_path = (row.get("full_path") or "").strip()
                file_path = (row.get("file_path") or "").strip()
                if full_path:
                    d["paths"].add(full_path)
                elif file_path:
                    d["paths"].add(file_path)

                # core fields
                for k_csv, k_store in [
                    ("last_modified", "last_modified"),
                    ("overall_risk_rating", "overall_risk_rating"),
                    ("data_classification", "data_classification"),
                    ("business_area", "business_area"),
                    ("auditor_proposed_action", "auditor_proposed_action"),
                ]:
                    val = (row.get(k_csv) or "").strip()
                    if val and not d[k_store]:
                        d[k_store] = val

                # external recipients
                perms = (row.get("permissions") or "")
                if "@" in perms:
                    for email in email_re.findall(perms):
                        e = email.lower()
                        if not any(e.endswith("@" + dom) for dom in INTERNAL_DOMAINS):
                            d["external_recipients"].add(email)

                # time buckets
                lm = parse_iso(row.get("last_modified") or "")
                rec = {
                    "file_name": name,
                    "last_modified": (lm.isoformat() if lm else ""),
                    "data_classification": (row.get("data_classification") or "").strip(),
                    "business_area": (row.get("business_area") or "").strip(),
                    "proposed_action": (row.get("auditor_proposed_action") or "").strip(),
                    "risk": (row.get("overall_risk_rating") or "").strip(),
                }
                if lm:
                    if lm >= last_month_start:
                        recent_window.append(rec)
                    elif prior_two_months_start <= lm < last_month_start:
                        prior_window.append(rec)

    # Derived insights
    highrisk_dupe_files = [n for n, d in per_file.items() if len(d["storages"]) > 1]
    highrisk_external_files = [n for n, d in per_file.items() if len(d["external_recipients"]) > 0]

    # Prepare top-5 attention list (recent first, then riskiness, then exposure type)
    def exposure_weight(name):
        d = per_file[name]
        w = 0
        if len(d["storages"]) > 1:
            w += 2
        if len(d["external_recipients"]) > 0:
            w += 3
        w += max(0, 2 - risk_key(d["overall_risk_rating"]))  # critical/very high/high boost
        return -w  # negative for ascending sort

    recent_names = set(r["file_name"] for r in recent_window)
    candidates = [n for n in per_file if n in recent_names]
    top_attention = sorted(candidates, key=lambda n: (exposure_weight(n), n.lower()))[:5]

    def mk_attention_table(names):
        if not names:
            return "_No high-risk files requiring Board attention were newly observed in the last month._"
        lines = ["| File | Risk | Data Type | Exposure | Proposed Action | Last Modified |",
                 "|------|------|-----------|----------|-----------------|---------------|"]
        for n in names:
            d = per_file[n]
            exposure_bits = []
            if len(d["storages"]) > 1:
                exposure_bits.append("Duplicate across storages")
            if len(d["external_recipients"]) > 0:
                exposure_bits.append("Externally shared")
            lines.append(
                f"| `{n}` | {d['overall_risk_rating'] or ''} | {d['data_classification'] or ''} | "
                f"{'; '.join(exposure_bits) or '—'} | {d['auditor_proposed_action'] or ''} | {d['last_modified'] or ''} |"
            )
        return "\n".join(lines)

    attention_table_md = mk_attention_table(top_attention)

    # Build the evidence bundle for the LLM
    kpi_md = (
        f"- Total files: {total_files}\n"
        f"- High-risk files: {highrisk_count} ({highrisk_pct}%)\n"
        f"- High-risk duplicates: {len(highrisk_dupe_files)}\n"
        f"- High-risk externally shared: {len(highrisk_external_files)}\n"
    )

    # Time-window quick tables for the model to reason (keep concise)
    def render_window_table(rows, title):
        if not rows:
            return f"### {title}\n_No high-risk activity detected in this window._"
        # sort: riskier first, newer first
        rows_sorted = sorted(
            rows,
            key=lambda r: (risk_key(r.get("risk")), r.get("last_modified", "")),
        )
        header = f"### {title}\n| File | Last Modified | Data Type | Business Area | Risk | Proposed Action |\n|------|---------------|-----------|---------------|------|-----------------|"
        body = "\n".join(
            f"| `{r['file_name']}` | {r['last_modified']} | {r['data_classification'] or ''} | "
            f"{r['business_area'] or ''} | {r['risk'] or ''} | {r['proposed_action'] or ''} |"
            for r in rows_sorted[:40]  # cap for brevity
        )
        return header + "\n" + body

    last_month_table = render_window_table(recent_window, "Last 1 Month — High-Risk Activity")
    prior_two_months_table = render_window_table(prior_window, "1–2 Months Before — High-Risk Activity")

    # Exceptional prompt tailored for Board Executive Summary
    prompt = (
        "You are a world-class internal auditor preparing a one-page Board Executive Summary. "
        "Write crisply for Board members (non-technical), prioritize clarity, and do not invent facts.\n\n"
        "Produce sections in this exact order and keep the entire output under ~700 words:\n"
        "1) Executive Summary (<= 220 words): What changed this month, overall exposure, and the single most important risk pattern.\n"
        "2) Key Trends (bullets): Last 1 month vs prior 2 months — new, recurring, escalating, or resolved risks.\n"
        "3) Top 5 Items Requiring Board Attention: Use the provided table; do not repeat every column, but call out why each item matters.\n"
        "4) Compliance and Governance Implications (region-specific): Required notifications, reporting thresholds, and immediate obligations for the region specified.\n"
        "5) Board-Ready Recommendations (numbered): Who is responsible, what action, and concrete due dates (7/30/60 days). Keep actions verifiable.\n"
        "6) 30–60 Day Outlook: Residual risk if no action is taken (qualitative likelihood and impact).\n\n"
        "Rules:\n"
        "- Synthesize; do not restate all raw tables. Be precise and avoid jargon.\n"
        "- If evidence is insufficient to support a claim, explicitly state the limitation.\n"
        "- Reference the appendix/tables instead of duplicating them.\n\n"
        f"Region: {region}\n\n"
        "KPIs:\n"
        f"{kpi_md}\n"
        "High-Risk Evidence (duplicates and external sharing):\n"
        f"{dupes_high_md}\n\n"
        f"{external_high_md}\n\n"
        "Time-Window Evidence:\n"
        f"{last_month_table}\n\n"
        f"{prior_two_months_table}\n\n"
        "Top 5 — Board Attention (evidence table):\n"
        f"{attention_table_md}\n"
    )

    # Call the model
    now_utc = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%SZ")
    intro = (
        "## Board Executive Summary\n"
        f"- Generated: {now_utc} UTC\n"
        f"- Tenant/User: `{user_id}`\n"
        f"- Use Case: {use_case_label or 'Board Member: Executive Summary'}\n"
    )

    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            temperature=0.15,
            messages=[
                {"role": "system", "content": "You are a world-class internal auditor for a Board of Directors. Respond in concise Markdown suitable for executives."},
                {"role": "user", "content": intro + "\n\n" + prompt}
            ]
        )
        narrative = resp.choices[0].message.content
        output_guardrails(narrative)

        # Return only the summary narrative; the UI can still show detailed tables from other endpoints if needed
        return {"markdown": intro + "\n\n" + narrative}
    except Exception as e:
        try:
            log_to_laravel("agent_audit_board_summary_error", {"error": str(e)})
        except Exception:
            pass
        from fastapi import HTTPException
        raise HTTPException(status_code=500, detail=f"Audit Board Summary LLM failed: {str(e)}")

# def _agent_audit_board_summary_base(context):
#     # This is your agent_audit_board_summary function - updated to take `context` for argument, logic completely unchanged.
#     # Copy-paste your block here, only data->context

#     user_id = context.get("user_id")
#     region = context.get("region") or "Australia"
#     use_case_label = (context.get("label") or context.get("use_case") or "").strip()
#     if not user_id:
#         raise ValueError("agent_audit_board_summary requires 'user_id' in context.")


#     from agents.cybersec import (
#         find_duplicate_files,
#         get_externally_shared_files,
#         highrisk_path,
#         count_files,
#         count_high_risk_files,
#     )

#     import os, csv, re
#     from collections import defaultdict
#     from datetime import datetime, timedelta, timezone

#     # KPI counters
#     total_files = count_files(user_id=user_id)
#     highrisk_count = count_high_risk_files(user_id=user_id)
#     highrisk_pct = round(100.0 * highrisk_count / total_files, 2) if total_files else 0.0

#     # Evidence tables (reuse existing renderers so UI remains consistent)
#     dupes_high_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
#     external_high_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

#     # Parse HighRisk CSV once for fast insights
#     INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}
#     email_re = re.compile(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}')

#     now = datetime.now(timezone.utc)
#     last_month_start = now - timedelta(days=30)
#     prior_two_months_start = last_month_start - timedelta(days=60)

#     high_path = highrisk_path(user_id)
#     per_file = defaultdict(lambda: {
#         "file_name": "",
#         "storages": set(),
#         "paths": set(),
#         "last_modified": "",
#         "overall_risk_rating": "",
#         "data_classification": "",
#         "business_area": "",
#         "auditor_proposed_action": "",
#         "external_recipients": set()
#     })

#     recent_window = []  # last 1 month
#     prior_window = []   # prior 2 months (excluding last month)

#     risk_order = {"critical": 0, "very high": 1, "high": 2, "medium": 3, "moderate": 4,
#                   "low": 5, "very low": 6, "none": 7, "": 8}
#     def risk_key(r):
#         return risk_order.get((r or "").strip().lower(), 8)

#     def parse_iso(dt_str: str):
#         if not dt_str:
#             return None
#         try:
#             dt = datetime.fromisoformat(dt_str.replace("Z", "+00:00"))
#             if dt.tzinfo is None:
#                 dt = dt.replace(tzinfo=timezone.utc)
#             return dt
#         except Exception:
#             return None

#     if os.path.isfile(high_path):
#         with open(high_path, newline="", encoding="utf-8") as f:
#             reader = csv.DictReader(f)
#             for row in reader:
#                 name = (row.get("file_name") or "").strip()
#                 if not name:
#                     continue
#                 d = per_file[name]
#                 d["file_name"] = name
#                 storage = (row.get("data_source") or "").strip()
#                 if storage:
#                     d["storages"].add(storage)
#                 full_path = (row.get("full_path") or "").strip()
#                 file_path = (row.get("file_path") or "").strip()
#                 if full_path:
#                     d["paths"].add(full_path)
#                 elif file_path:
#                     d["paths"].add(file_path)

#                 # core fields
#                 for k_csv, k_store in [
#                     ("last_modified", "last_modified"),
#                     ("overall_risk_rating", "overall_risk_rating"),
#                     ("data_classification", "data_classification"),
#                     ("business_area", "business_area"),
#                     ("auditor_proposed_action", "auditor_proposed_action"),
#                 ]:
#                     val = (row.get(k_csv) or "").strip()
#                     if val and not d[k_store]:
#                         d[k_store] = val

#                 # external recipients
#                 perms = (row.get("permissions") or "")
#                 if "@" in perms:
#                     for email in email_re.findall(perms):
#                         e = email.lower()
#                         if not any(e.endswith("@" + dom) for dom in INTERNAL_DOMAINS):
#                             d["external_recipients"].add(email)

#                 # time buckets
#                 lm = parse_iso(row.get("last_modified") or "")
#                 rec = {
#                     "file_name": name,
#                     "last_modified": (lm.isoformat() if lm else ""),
#                     "data_classification": (row.get("data_classification") or "").strip(),
#                     "business_area": (row.get("business_area") or "").strip(),
#                     "proposed_action": (row.get("auditor_proposed_action") or "").strip(),
#                     "risk": (row.get("overall_risk_rating") or "").strip(),
#                 }
#                 if lm:
#                     if lm >= last_month_start:
#                         recent_window.append(rec)
#                     elif prior_two_months_start <= lm < last_month_start:
#                         prior_window.append(rec)

#     # Derived insights
#     highrisk_dupe_files = [n for n, d in per_file.items() if len(d["storages"]) > 1]
#     highrisk_external_files = [n for n, d in per_file.items() if len(d["external_recipients"]) > 0]

#     # Prepare top-5 attention list (recent first, then riskiness, then exposure type)
#     def exposure_weight(name):
#         d = per_file[name]
#         w = 0
#         if len(d["storages"]) > 1:
#             w += 2
#         if len(d["external_recipients"]) > 0:
#             w += 3
#         w += max(0, 2 - risk_key(d["overall_risk_rating"]))  # critical/very high/high boost
#         return -w  # negative for ascending sort

#     recent_names = set(r["file_name"] for r in recent_window)
#     candidates = [n for n in per_file if n in recent_names]
#     top_attention = sorted(candidates, key=lambda n: (exposure_weight(n), n.lower()))[:5]

#     def mk_attention_table(names):
#         if not names:
#             return "_No high-risk files requiring Board attention were newly observed in the last month._"
#         lines = ["| File | Risk | Data Type | Exposure | Proposed Action | Last Modified |",
#                  "|------|------|-----------|----------|-----------------|---------------|"]
#         for n in names:
#             d = per_file[n]
#             exposure_bits = []
#             if len(d["storages"]) > 1:
#                 exposure_bits.append("Duplicate across storages")
#             if len(d["external_recipients"]) > 0:
#                 exposure_bits.append("Externally shared")
#             lines.append(
#                 f"| `{n}` | {d['overall_risk_rating'] or ''} | {d['data_classification'] or ''} | "
#                 f"{'; '.join(exposure_bits) or '—'} | {d['auditor_proposed_action'] or ''} | {d['last_modified'] or ''} |"
#             )
#         return "\n".join(lines)

#     attention_table_md = mk_attention_table(top_attention)

#     # Build the evidence bundle for the LLM
#     kpi_md = (
#         f"- Total files: {total_files}\n"
#         f"- High-risk files: {highrisk_count} ({highrisk_pct}%)\n"
#         f"- High-risk duplicates: {len(highrisk_dupe_files)}\n"
#         f"- High-risk externally shared: {len(highrisk_external_files)}\n"
#     )

#     # Time-window quick tables for the model to reason (keep concise)
#     def render_window_table(rows, title):
#         if not rows:
#             return f"### {title}\n_No high-risk activity detected in this window._"
#         # sort: riskier first, newer first
#         rows_sorted = sorted(
#             rows,
#             key=lambda r: (risk_key(r.get("risk")), r.get("last_modified", "")),
#         )
#         header = f"### {title}\n| File | Last Modified | Data Type | Business Area | Risk | Proposed Action |\n|------|---------------|-----------|---------------|------|-----------------|"
#         body = "\n".join(
#             f"| `{r['file_name']}` | {r['last_modified']} | {r['data_classification'] or ''} | "
#             f"{r['business_area'] or ''} | {r['risk'] or ''} | {r['proposed_action'] or ''} |"
#             for r in rows_sorted[:40]  # cap for brevity
#         )
#         return header + "\n" + body

#     last_month_table = render_window_table(recent_window, "Last 1 Month — High-Risk Activity")
#     prior_two_months_table = render_window_table(prior_window, "1–2 Months Before — High-Risk Activity")

#     # Exceptional prompt tailored for Board Executive Summary
#     prompt = (
#         "You are a world-class internal auditor preparing a one-page Board Executive Summary. "
#         "Write crisply for Board members (non-technical), prioritize clarity, and do not invent facts.\n\n"
#         "Produce sections in this exact order and keep the entire output under ~700 words:\n"
#         "1) Executive Summary (<= 220 words): What changed this month, overall exposure, and the single most important risk pattern.\n"
#         "2) Key Trends (bullets): Last 1 month vs prior 2 months — new, recurring, escalating, or resolved risks.\n"
#         "3) Top 5 Items Requiring Board Attention: Use the provided table; do not repeat every column, but call out why each item matters.\n"
#         "4) Compliance and Governance Implications (region-specific): Required notifications, reporting thresholds, and immediate obligations for the region specified.\n"
#         "5) Board-Ready Recommendations (numbered): Who is responsible, what action, and concrete due dates (7/30/60 days). Keep actions verifiable.\n"
#         "6) 30–60 Day Outlook: Residual risk if no action is taken (qualitative likelihood and impact).\n\n"
#         "Rules:\n"
#         "- Synthesize; do not restate all raw tables. Be precise and avoid jargon.\n"
#         "- If evidence is insufficient to support a claim, explicitly state the limitation.\n"
#         "- Reference the appendix/tables instead of duplicating them.\n\n"
#         f"Region: {region}\n\n"
#         "KPIs:\n"
#         f"{kpi_md}\n"
#         "High-Risk Evidence (duplicates and external sharing):\n"
#         f"{dupes_high_md}\n\n"
#         f"{external_high_md}\n\n"
#         "Time-Window Evidence:\n"
#         f"{last_month_table}\n\n"
#         f"{prior_two_months_table}\n\n"
#         "Top 5 — Board Attention (evidence table):\n"
#         f"{attention_table_md}\n"
#     )

#     # Call the model
#     now_utc = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%SZ")
#     intro = (
#         "## Board Executive Summary\n"
#         f"- Generated: {now_utc} UTC\n"
#         f"- Tenant/User: `{user_id}`\n"
#         f"- Use Case: {use_case_label or 'Board Member: Executive Summary'}\n"
#     )

#     try:
#         resp = client.chat.completions.create(
#             model="gpt-4.1",
#             temperature=0.15,
#             messages=[
#                 {"role": "system", "content": "You are a world-class internal auditor for a Board of Directors. Respond in concise Markdown suitable for executives."},
#                 {"role": "user", "content": intro + "\n\n" + prompt}
#             ]
#         )
#         narrative = resp.choices[0].message.content
#         output_guardrails(narrative)

#         # Return only the summary narrative; the UI can still show detailed tables from other endpoints if needed
#         return {"markdown": intro + "\n\n" + narrative}
#     except Exception as e:
#         try:
#             log_to_laravel("agent_audit_board_summary_error", {"error": str(e)})
#         except Exception:
#             pass
#         from fastapi import HTTPException
#         raise HTTPException(status_code=500, detail=f"Audit Board Summary LLM failed: {str(e)}")

def agent_audit_evidence(context):
    """
    Returns just the main audit evidence (duplicate and externally shared high-risk files, for example),
    in markdown table form, for a quick evidence review (like the 'show evidence' in cybersec)
    """
    from agents.cybersec import (
        find_duplicate_files,
        get_externally_shared_files,
    )
    user_id = context.get('user_id')
    evidence = []
    evidence.append("## High-Risk Files Present in Multiple Storage Locations\n")
    evidence.append(find_duplicate_files(user_id=user_id, high_risk_only=True))
    evidence.append("\n\n## High-Risk Files That Are Externally Shared\n")
    evidence.append(get_externally_shared_files(user_id=user_id, high_risk_only=True))
    return {
        "reply": "\n".join(evidence),
        "followups": [
            {
                "label": "Board Executive Summary",
                "operation": "audit_board_summary",
                "args": {},
                "prompt": "Give me an executive summary for the board."
            },
            {
                "label": "Full Board-Level Audit Report",
                "operation": "audit_full",
                "args": {},
                "prompt": "Show the full board-level audit report."
            }
        ] + get_audit_no_action_followup()
    }

def get_audit_no_action_followup():
    return [
        {
            "label": "No More Questions",
            "operation": "audit_no_action",
            "args": {},
            "prompt": "Thank you, no further questions."
        }
    ]

def agent_audit_no_action(context):
    return {
        "reply": "Thank you! If you have more questions later about audit/compliance, just ask.",
        "followups": []
    }


def agent_audit_compliance_advisory(context):
    """
    Generates an expert compliance advisory with strong context, business intro, summary tables, 
    and numbered urgent/jurisdictional actions as per latest evidence, using LLM.
    """
    import os, csv
    from datetime import datetime, timezone

    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    if not user_id:
        raise ValueError("user_id required")

    now = datetime.now(timezone.utc)
    now_utc = now.strftime("%Y-%m-%d %H:%M UTC")
    total_files = count_files(user_id=user_id)
    highrisk_count = count_high_risk_files(user_id=user_id)

    # High-risk evidence
    dupes_high_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
    external_high_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

    evidence_high_md = (
        "#### High-Risk Duplicates\n" + 
        (dupes_high_md if "No duplicate files detected" not in dupes_high_md else "_No high-risk duplicate files detected._") +
        "\n\n#### High-Risk External Shares\n" +
        (external_high_md if "No externally shared files detected" not in external_high_md else "_No high-risk externally shared files detected._")
    )

    # Per-file high risk profile summary (concise, for table/action use below)
    high_path = highrisk_path(user_id)
    highrisk_rows = []
    if os.path.isfile(high_path):
        with open(high_path, newline="", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for row in reader:
                risk = (row.get("overall_risk_rating") or "").lower()
                if risk in ["high", "very high", "critical"]:
                    highrisk_rows.append(row)
    urgent_table = "_No unresolved or new High/Critical risk files detected._"
    if highrisk_rows:
        urgent_table = "| File Name | Storage | Data Classification | Risk | Last Modified | Proposed Action |\n|---|---|---|---|---|---|\n"
        for row in highrisk_rows:
            storage = row.get('backend_source') or row.get('data_source') or ''
            urgent_table += f"| `{row.get('file_name','')}` | {storage} | {row.get('data_classification','')} | {row.get('overall_risk_rating','')} | {row.get('last_modified','')} | {row.get('auditor_proposed_action','')} |\n"

    # Generate an expert, deep context lead-in for the LLM (modeled after your board intro)
    intro_md = (
        f"## Compliance Advisory & Legal Risk Action – {region}\n\n"
        f"**Generated:** {now_utc}\n"
        f"**Region:** {region}\n"
        f"**Files Monitored:** {total_files}\n"
        f"**High Risk Files:** {highrisk_count}\n"
        "\n"
        "> This report provides a prioritized, context-specific compliance and legal risk review. It references the most recent evidence produced by CyberSecAI, focused on files with the highest compliance/cyber exposure or unresolved risk. All advice is jurisdictionally aware, referencing {region} privacy regulations and best practice action timelines."
    )

    # COMPLIANCE PROMPT: instruct LLM to produce professional report
    prompt = (
        f"{intro_md}\n\n"
        "You are a highly experienced compliance and legal risk advisor. Analyze the technical evidence provided and produce a compliance advisory suitable for legal/privacy teams. The output must contain:\n\n"
        "1. **Executive Summary**: Discuss overall compliance status, trends, and notable changes. Provide board-level context—how is legal/compliance risk evolving?\n"
        "2. **High-Risk/Urgent Action Table**: Identify all new or unresolved HIGH or CRITICAL risk files. For each, outline: file, risk, urgent action, proposed owner, and deadline. Use evidence table format for clarity.\n"
        "3. **Jurisdictional/Region Implications**: Summarize regulatory breach thresholds, notification triggers, and duties under {region} law based on the evidence (e.g., GDPR, Privacy Act, sector rules).\n"
        "4. **Numbered, Practical Recommendations**: Assign each to the appropriate function (legal, security, business), with recommended deadlines and needed documentation.\n"
        "5. If evidence is insufficient for certainty, explicitly state limitations and open items.\n"
        "6. **Appendix** (if needed): Refer to tables for deep technical details.\n\n"
        f"# Evidence\n"
        f"{evidence_high_md}\n\n"
        f"### URGENT FILES FOR ACTION\n"
        f"{urgent_table}\n\n"
    )

    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            temperature=0.19,
            messages=[
                {"role": "system", "content": "You are a world-class compliance and privacy legal advisor."},
                {"role": "user", "content": prompt}
            ]
        )
        narrative = resp.choices[0].message.content
        output_guardrails(narrative)
        return {
            "reply": narrative,
            "followups": [
                {"label": "Find Risk Hotspots", "operation": "audit_find_risk_hotspots", "args": {}, "prompt": "Show me the riskiest files/folders now."},
                {"label": "Show Alerts & Monitoring", "operation": "audit_continuous_alerts", "args": {}, "prompt": "Show any new high risk events detected."},
            ] + get_audit_no_action_followup()
        }
    except Exception as e:
        return {"reply": f"Compliance advisory failed: {e}", "followups": get_audit_no_action_followup()}

# def agent_audit_compliance_advisory(context):
#     """
#     Generates an expert compliance advisory with strong context, business intro, summary tables, 
#     and numbered urgent/jurisdictional actions as per latest evidence, using LLM.
#     """
#     user_id = context.get("user_id")
#     region = context.get("region") or "Australia"
#     if not user_id:
#         raise ValueError("user_id required")

#     now = datetime.now(timezone.utc)
#     now_utc = now.strftime("%Y-%m-%d %H:%M UTC")
#     total_files = count_files(user_id=user_id)
#     highrisk_count = count_high_risk_files(user_id=user_id)

#     # High-risk evidence
#     dupes_high_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
#     external_high_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

#     evidence_high_md = (
#         "#### High-Risk Duplicates\n" + 
#         (dupes_high_md if "No duplicate files detected" not in dupes_high_md else "_No high-risk duplicate files detected._") +
#         "\n\n#### High-Risk External Shares\n" +
#         (external_high_md if "No externally shared files detected" not in external_high_md else "_No high-risk externally shared files detected._")
#     )

#     # Per-file high risk profile summary (concise, for table/action use below)
#     high_path = highrisk_path(user_id)
#     highrisk_rows = []
#     if os.path.isfile(high_path):
#         with open(high_path, newline="", encoding="utf-8") as f:
#             reader = csv.DictReader(f)
#             for row in reader:
#                 risk = (row.get("overall_risk_rating") or "").lower()
#                 if risk in ["high", "very high", "critical"]:
#                     highrisk_rows.append(row)
#     urgent_table = "_No unresolved or new High/Critical risk files detected._"
#     if highrisk_rows:
#         urgent_table = "| File Name | Data Classification | Risk | Last Modified | Proposed Action |\n|---|---|---|---|---|\n"
#         for row in highrisk_rows:
#             urgent_table += f"| `{row.get('file_name','')}` | {row.get('data_classification','')} | {row.get('overall_risk_rating','')} | {row.get('last_modified','')} | {row.get('auditor_proposed_action','')} |\n"

#     # Generate an expert, deep context lead-in for the LLM (modeled after your board intro)
#     intro_md = (
#         f"## Compliance Advisory & Legal Risk Action – {region}\n\n"
#         f"**Generated:** {now_utc}\n"
#         f"**Region:** {region}\n"
#         f"**Files Monitored:** {total_files}\n"
#         f"**High Risk Files:** {highrisk_count}\n"
#         "\n"
#         "> This report provides a prioritized, context-specific compliance and legal risk review. It references the most recent evidence produced by CyberSecAI, focused on files with the highest compliance/cyber exposure or unresolved risk. All advice is jurisdictionally aware, referencing {region} privacy regulations and best practice action timelines."
#     )

#     # COMPLIANCE PROMPT: instruct LLM to produce professional report
#     prompt = (
#         f"{intro_md}\n\n"
#         "You are a highly experienced compliance and legal risk advisor. Analyze the technical evidence provided and produce a compliance advisory suitable for legal/privacy teams. The output must contain:\n\n"
#         "1. **Executive Summary**: Discuss overall compliance status, trends, and notable changes. Provide board-level context—how is legal/compliance risk evolving?\n"
#         "2. **High-Risk/Urgent Action Table**: Identify all new or unresolved HIGH or CRITICAL risk files. For each, outline: file, risk, urgent action, proposed owner, and deadline. Use evidence table format for clarity.\n"
#         "3. **Jurisdictional/Region Implications**: Summarize regulatory breach thresholds, notification triggers, and duties under {region} law based on the evidence (e.g., GDPR, Privacy Act, sector rules).\n"
#         "4. **Numbered, Practical Recommendations**: Assign each to the appropriate function (legal, security, business), with recommended deadlines and needed documentation.\n"
#         "5. If evidence is insufficient for certainty, explicitly state limitations and open items.\n"
#         "6. **Appendix** (if needed): Refer to tables for deep technical details.\n\n"
#         f"# Evidence\n"
#         f"{evidence_high_md}\n\n"
#         f"### URGENT FILES FOR ACTION\n"
#         f"{urgent_table}\n\n"
#     )

#     try:
#         resp = client.chat.completions.create(
#             model="gpt-4.1",
#             temperature=0.19,
#             messages=[
#                 {"role": "system", "content": "You are a world-class compliance and privacy legal advisor."},
#                 {"role": "user", "content": prompt}
#             ]
#         )
#         narrative = resp.choices[0].message.content
#         output_guardrails(narrative)
#         return {
#             "reply": narrative,
#             "followups": [
#                 {"label": "Find Risk Hotspots", "operation": "audit_find_risk_hotspots", "args": {}, "prompt": "Show me the riskiest files/folders now."},
#                 {"label": "Show Alerts & Monitoring", "operation": "audit_continuous_alerts", "args": {}, "prompt": "Show any new high risk events detected."},
#             ] + get_audit_no_action_followup()
#         }
#     except Exception as e:
#         return {"reply": f"Compliance advisory failed: {e}", "followups": get_audit_no_action_followup()}


def agent_audit_find_risk_hotspots(context):
    """
    Detects and analyzes the highest-risk files/folders ("hotspots") system-wide, providing executive context,
    detailed evidence tables, system-wide clustering (by folder, data type, exposure), and prioritized recommendations.
    """
    import os, csv
    from datetime import datetime, timezone
    from collections import defaultdict

    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    if not user_id:
        raise ValueError("user_id required")

    now = datetime.now(timezone.utc)
    now_utc = now.strftime("%Y-%m-%d %H:%M UTC")
    total_files = count_files(user_id=user_id)
    highrisk_count = count_high_risk_files(user_id=user_id)

    # Technical evidence, duplicates and external sharing (as markdown tables)
    dupes_high_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
    external_high_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

    # Build hotspots: pull from high-risk CSV all "high", "very high", "critical"
    high_path = highrisk_path(user_id)
    hotspots = []
    per_folder = defaultdict(list)
    per_type = defaultdict(list)
    if os.path.isfile(high_path):
        with open(high_path, newline="", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for row in reader:
                risk_level = (row.get("overall_risk_rating") or "").lower()
                if risk_level in ("high", "very high", "critical"):
                    hotspots.append(row)
                    folder = os.path.dirname(row.get("file_path", "") or row.get("full_path", "") or "").strip()
                    if folder:
                        per_folder[folder].append(row)
                    dtype = (row.get("data_classification") or "").strip()
                    if dtype:
                        per_type[dtype].append(row)
    # Top 10 riskiest (by risk, then recency)
    def sort_key(row):
        order = {"critical":0,"very high":1,"high":2,"medium":3,"low":4,"":5}
        lvl = (row.get('overall_risk_rating') or "").lower()
        t = row.get('last_modified') or ""
        return (order.get(lvl,99), t[::-1])
    top_hotspots = sorted(hotspots, key=sort_key)[:10]

    # Table for LLM and for UI
    top_hot_table = "_No system hotspots detected._"
    if top_hotspots:
        top_hot_table = "| File/Folder | Data Class | Risk | Last Modified | Exposure | Proposed Action |\n|---|---|---|---|---|---|\n"
        for row in top_hotspots:
            exposures = []
            # Use backend_source (with fallback for legacy data)
            storage = row.get('backend_source') or row.get('data_source') or ''
            if row.get('file_name') and storage:
                exposures.append(f"duplicate in {storage}")
            if row.get('permissions') and '@' in row['permissions']:
                exposures.append("external share")
            exp = "; ".join(exposures) if exposures else ""
            top_hot_table += f"| `{row.get('file_name','')}` | {row.get('data_classification','')} | {row.get('overall_risk_rating','')} | {row.get('last_modified','')} | {exp} | {row.get('auditor_proposed_action','')} |\n"

    # Folder clustering overview
    cluster_md = ""
    if per_folder:
        cluster_md = "\n**Top Hotspot Folders:**\n"
        most = sorted(per_folder.items(), key=lambda kv: len(kv[1]), reverse=True)[:5]
        for folder, files in most:
            cluster_md += f"- `{folder}`: {len(files)} high risk files\n"
    type_md = ""
    if per_type:
        type_md = "\n**High-Risk by Data Type:**\n"
        most = sorted(per_type.items(), key=lambda kv: len(kv[1]), reverse=True)[:5]
        for dtype, files in most:
            type_md += f"- `{dtype}`: {len(files)} entries\n"

    # Deep expert intro
    intro_md = (
        f"## System Risk Hotspots & Risk Concentration (Expert Auditor Report)\n\n"
        f"**Generated:** {now_utc}\n"
        f"**Region:** {region}\n"
        f"**Total Files:** {total_files}\n"
        f"**High Risk Files:** {highrisk_count}\n"
        "\n"
        "> This expert analysis identifies and prioritizes the system’s most critical risk concentrations and exposure hotspots. Use this to direct urgent remediation and proactive management, focusing on business units, folders, or data types that contribute outsize risk."
    )

    # Prompt to LLM
    prompt = (
        f"{intro_md}\n\n"
        f"### High-Risk Technical Evidence Tables\n"
        f"{'-'*18}\n"
        "**Duplicates:**\n" +
        (dupes_high_md if "No duplicate files detected" not in dupes_high_md else "_No high-risk duplicate files detected._") +
        "\n\n**External Shares:**\n" +
        (external_high_md if "No externally shared files detected" not in external_high_md else "_No high-risk externally shared files detected._") +
        "\n"
        f"{cluster_md}\n"
        f"{type_md}\n"
        "\n"
        "You are a world-class audit/risk expert. From this data:\n"
        "1. Provide an Executive Summary overview – where are the biggest risk concentrations and what business/context is involved?\n"
        "2. List the top 10 risk hotspots (files/folders) in a table; highlight why they are critical (duplicates, external shares, type, business area, recency, etc).\n"
        "3. Cluster systemwide risk by folder, business area, and data type. Where should remediation efforts focus?\n"
        "4. For each hotspot, recommend a prioritized next action, with urgency (immediate, this month, end of quarter) and owner (team, role, function).\n"
        "5. Advise on root causes, systemic factors, and ongoing monitoring.\n"
        "6. If evidence is not strong enough to defend a claim, say so.\n\n"
        "**Top 10 Hotspots:**\n"
        f"{top_hot_table}\n"
    )

    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            temperature=0.19,
            messages=[
                {
                    "role": "system",
                    "content": (
                        "You are an expert digital risk and audit analyst for board-level reporting. "
                        "Cluster and explain risk hotpots, thinking as an advisor who must defend findings before stakeholders."
                    )
                },
                {"role": "user", "content": prompt}
            ]
        )
        narrative = resp.choices[0].message.content
        output_guardrails(narrative)
        return {
            "reply": narrative,
            "followups": [
                {
                    "label": "Compliance Advisory",
                    "operation": "audit_compliance_advisory",
                    "args": {},
                    "prompt": "Show urgent compliance/legal actions."
                },
                {
                    "label": "Show Alerts & Monitoring",
                    "operation": "audit_continuous_alerts",
                    "args": {},
                    "prompt": "Show any new high risk events detected."
                },
            ] + get_audit_no_action_followup()
        }
    except Exception as e:
        return {"reply": f"Find Risk Hotspots failed: {e}", "followups": get_audit_no_action_followup()}

# def agent_audit_find_risk_hotspots(context):
#     """
#     Detects and analyzes the highest-risk files/folders ("hotspots") system-wide, providing executive context,
#     detailed evidence tables, system-wide clustering (by folder, data type, exposure), and prioritized recommendations.
#     """
#     user_id = context.get("user_id")
#     region = context.get("region") or "Australia"
#     if not user_id:
#         raise ValueError("user_id required")

#     now = datetime.now(timezone.utc)
#     now_utc = now.strftime("%Y-%m-%d %H:%M UTC")
#     total_files = count_files(user_id=user_id)
#     highrisk_count = count_high_risk_files(user_id=user_id)

#     # Technical evidence, duplicates and external sharing (as markdown tables)
#     dupes_high_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
#     external_high_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

#     # Build hotspots: pull from high-risk CSV all "high", "very high", "critical"
#     high_path = highrisk_path(user_id)
#     hotspots = []
#     per_folder = defaultdict(list)
#     per_type = defaultdict(list)
#     if os.path.isfile(high_path):
#         with open(high_path, newline="", encoding="utf-8") as f:
#             reader = csv.DictReader(f)
#             for row in reader:
#                 risk_level = (row.get("overall_risk_rating") or "").lower()
#                 if risk_level in ("high", "very high", "critical"):
#                     hotspots.append(row)
#                     folder = os.path.dirname(row.get("file_path", "") or row.get("full_path", "") or "").strip()
#                     if folder:
#                         per_folder[folder].append(row)
#                     dtype = (row.get("data_classification") or "").strip()
#                     if dtype:
#                         per_type[dtype].append(row)
#     # Top 10 riskiest (by risk, then recency)
#     def sort_key(row):
#         order = {"critical":0,"very high":1,"high":2,"medium":3,"low":4,"":5}
#         lvl = (row.get('overall_risk_rating') or "").lower()
#         t = row.get('last_modified') or ""
#         return (order.get(lvl,99), t[::-1])
#     top_hotspots = sorted(hotspots, key=sort_key)[:10]

#     # Table for LLM and for UI
#     top_hot_table = "_No system hotspots detected._"
#     if top_hotspots:
#         top_hot_table = "| File/Folder | Data Class | Risk | Last Modified | Exposure | Proposed Action |\n|---|---|---|---|---|---|\n"
#         for row in top_hotspots:
#             exposures = []
#             if row.get('file_name'):
#                 if row.get('data_source'):
#                     exposures.append(f"duplicate in {row['data_source']}")
#             if row.get('permissions') and '@' in row['permissions']:
#                 exposures.append("external share")
#             exp = "; ".join(exposures) if exposures else ""
#             top_hot_table += f"| `{row.get('file_name','')}` | {row.get('data_classification','')} | {row.get('overall_risk_rating','')} | {row.get('last_modified','')} | {exp} | {row.get('auditor_proposed_action','')} |\n"

#     # Folder clustering overview
#     cluster_md = ""
#     if per_folder:
#         cluster_md = "\n**Top Hotspot Folders:**\n"
#         most = sorted(per_folder.items(), key=lambda kv: len(kv[1]), reverse=True)[:5]
#         for folder, files in most:
#             cluster_md += f"- `{folder}`: {len(files)} high risk files\n"
#     type_md = ""
#     if per_type:
#         type_md = "\n**High-Risk by Data Type:**\n"
#         most = sorted(per_type.items(), key=lambda kv: len(kv[1]), reverse=True)[:5]
#         for dtype, files in most:
#             type_md += f"- `{dtype}`: {len(files)} entries\n"

#     # Deep expert intro
#     intro_md = (
#         f"## System Risk Hotspots & Risk Concentration (Expert Auditor Report)\n\n"
#         f"**Generated:** {now_utc}\n"
#         f"**Region:** {region}\n"
#         f"**Total Files:** {total_files}\n"
#         f"**High Risk Files:** {highrisk_count}\n"
#         "\n"
#         "> This expert analysis identifies and prioritizes the system’s most critical risk concentrations and exposure hotspots. Use this to direct urgent remediation and proactive management, focusing on business units, folders, or data types that contribute outsize risk."
#     )

#     # Prompt to LLM
#     prompt = (
#         f"{intro_md}\n\n"
#         f"### High-Risk Technical Evidence Tables\n"
#         f"{'-'*18}\n"
#         "**Duplicates:**\n" +
#         (dupes_high_md if "No duplicate files detected" not in dupes_high_md else "_No high-risk duplicate files detected._") +
#         "\n\n**External Shares:**\n" +
#         (external_high_md if "No externally shared files detected" not in external_high_md else "_No high-risk externally shared files detected._") +
#         "\n"
#         f"{cluster_md}\n"
#         f"{type_md}\n"
#         "\n"
#         "You are a world-class audit/risk expert. From this data:\n"
#         "1. Provide an Executive Summary overview – where are the biggest risk concentrations and what business/context is involved?\n"
#         "2. List the top 10 risk hotspots (files/folders) in a table; highlight why they are critical (duplicates, external shares, type, business area, recency, etc).\n"
#         "3. Cluster systemwide risk by folder, business area, and data type. Where should remediation efforts focus?\n"
#         "4. For each hotspot, recommend a prioritized next action, with urgency (immediate, this month, end of quarter) and owner (team, role, function).\n"
#         "5. Advise on root causes, systemic factors, and ongoing monitoring.\n"
#         "6. If evidence is not strong enough to defend a claim, say so.\n\n"
#         "**Top 10 Hotspots:**\n"
#         f"{top_hot_table}\n"
#     )

#     try:
#         resp = client.chat.completions.create(
#             model="gpt-4.1",
#             temperature=0.19,
#             messages=[
#                 {
#                     "role": "system",
#                     "content": (
#                         "You are an expert digital risk and audit analyst for board-level reporting. "
#                         "Cluster and explain risk hotpots, thinking as an advisor who must defend findings before stakeholders."
#                     )
#                 },
#                 {"role": "user", "content": prompt}
#             ]
#         )
#         narrative = resp.choices[0].message.content
#         output_guardrails(narrative)
#         return {
#             "reply": narrative,
#             "followups": [
#                 {
#                     "label": "Compliance Advisory",
#                     "operation": "audit_compliance_advisory",
#                     "args": {},
#                     "prompt": "Show urgent compliance/legal actions."
#                 },
#                 {
#                     "label": "Show Alerts & Monitoring",
#                     "operation": "audit_continuous_alerts",
#                     "args": {},
#                     "prompt": "Show any new high risk events detected."
#                 },
#             ] + get_audit_no_action_followup()
#         }
#     except Exception as e:
#         return {"reply": f"Find Risk Hotspots failed: {e}", "followups": get_audit_no_action_followup()}


def agent_audit_continuous_alerts(context):
    """
    Provides continuous audit monitoring: highlights newly detected high-risk or non-compliant files since the last checkpoint,
    delivers an executive summary of environmental changes, analyzes compliance/regulatory impact, and gives expert master-auditor recommendations for immediate, follow-up, and ongoing monitoring steps.
    """
    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    if not user_id:
        raise ValueError("user_id required")
    
    now = datetime.now(timezone.utc)
    now_utc = now.strftime("%Y-%m-%d %H:%M UTC")
    time_window_hours = 36
    time_cutoff = now - timedelta(hours=time_window_hours)
    high_path = highrisk_path(user_id)
    new_events = []
    all_alerts = 0

    # Collect all new events in the last 36 hours (or adjust for your system frequency)
    if os.path.isfile(high_path):
        with open(high_path, newline="", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for row in reader:
                lm = row.get("last_modified")
                try:
                    dt = datetime.fromisoformat(lm.replace("Z", "+00:00")) if lm else None
                    if dt and dt >= time_cutoff:
                        all_alerts += 1
                        new_events.append(row)
                except Exception:
                    pass
    event_table = ""
    if new_events:
        event_table = "| File Name | Last Modified | Data Class | Risk | Proposed Action |\n|---|---|---|---|---|\n"
        for row in new_events:
            event_table += (
                f"| `{row.get('file_name','')}` "
                f"| {row.get('last_modified','')} "
                f"| {row.get('data_classification','')} "
                f"| {row.get('overall_risk_rating','')} "
                f"| {row.get('auditor_proposed_action','')} |\n"
            )
    else:
        event_table = "_No new high risk or non-compliant files detected since last scan._"

    # Strong, context-rich master auditor intro
    intro_md = (
        f"## Continuous Risk Alerts & Change Monitoring – {region}\n"
        f"**Generated:** {now_utc}\n"
        f"**Monitoring window:** Last {time_window_hours} hours\n"
        f"**New High-Risk/Non-Compliant Items:** {all_alerts}\n"
        "> This continuous assurance report highlights all recently detected high-risk or non-compliant files. It enables rapid detection, escalation, and compliance/risk action. Analysis is tailored for privacy/board/cyber functions in {region}, referencing active regulatory duties.\n\n"
    )

    prompt = (
        f"{intro_md}"
        "You are a master auditor/CISO analyst. From the evidence below, produce a board-ready alerts & change monitoring summary. Deliver:\n"
        "1. **Executive Summary:**  What has changed in the compliance and cyber risk environment since the last scan? Highlight new risks, suspicious changes, or improvements.\n"
        "2. **Alerts Table Review:**  For each new file or incident, assess:\n"
        "   - Risk impact and compliance implication\n"
        "   - Regulatory notification triggers (e.g. notifiable breach, sector obligation, GDPR, AU Privacy Act)\n"
        "   - Recommended urgent/board escalation actions\n"
        "3. **Must-Escalate Detection:**  Flag any event that would warrant urgent board, legal, or data regulator notification under {region} rules.\n"
        "4. **Continuous Monitoring & Next Steps:**  Offer continuous improvement advice for the organization’s ongoing monitoring program; clarify if evidence is insufficient for full assurance.\n\n"
        f"### Alerts/Changelog (Last {time_window_hours}h)\n"
        f"{event_table}"
    )
    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            temperature=0.12,
            messages=[
                {
                    "role": "system",
                    "content": (
                        "You are an expert continuous audit, cyber, and compliance monitoring advisor. "
                        "Think like an internal auditor who must defend their findings to both legal and technical stakeholders."
                    ),
                },
                {"role": "user", "content": prompt},
            ]
        )
        narrative = resp.choices[0].message.content
        output_guardrails(narrative)
        return {
            "reply": narrative,
            "followups": [
                {
                    "label": "Hotspots",
                    "operation": "audit_find_risk_hotspots",
                    "args": {},
                    "prompt": "Show most risky areas overall.",
                },
                {
                    "label": "Compliance Advisory",
                    "operation": "audit_compliance_advisory",
                    "args": {},
                    "prompt": "Show urgent compliance/legal actions.",
                },
            ] + get_audit_no_action_followup(),
        }
    except Exception as e:
        return {
            "reply": f"Continuous alerts failed: {e}",
            "followups": get_audit_no_action_followup(),
        }

def agent_audit_dispatcher(context):
    """
    Main switchboard for audit agent, matching what the cybersec agent does.
    Dispatches based on context['operation'] or context['label']/['use_case'], returns
    reply and followups.
    """
    operation = (context.get("operation") or "").lower()
    label = (context.get("label") or context.get("use_case") or "").strip()

    # Explicit operation (use-case) routing
    if operation == "audit_compliance_advisory":
        return agent_audit_compliance_advisory(context)
    if operation == "audit_find_risk_hotspots":
        return agent_audit_find_risk_hotspots(context)
    if operation == "audit_continuous_alerts":
        return agent_audit_continuous_alerts(context)
    if operation == "audit_board_summary" or label == "Board Member: Executive Summary":
        return agent_audit_board_summary(context)
    if operation == "audit_full" or label == "Board Member: Board-Level Audit Report":
        return agent_audit_full(context)
    if operation == "audit_evidence":
        return agent_audit_evidence(context)
    if operation == "audit_no_action":
        return agent_audit_no_action(context)

    # DEFAULT: present menu/followups just like cybersec agent (dashboard)
    followups = [
        {
            "label": "Board Executive Summary",
            "operation": "audit_board_summary",
            "args": {},
            "prompt": "Give me an executive summary for the board."
        },
        {
            "label": "Full Board-Level Audit Report",
            "operation": "audit_full",
            "args": {},
            "prompt": "Show the full board-level audit report."
        },
        {
            "label": "Show Audit Evidence (tables)",
            "operation": "audit_evidence",
            "args": {},
            "prompt": "Show me all audit evidence tables."
        }
    ] + get_audit_no_action_followup()

    intro = "**Audit/Compliance Overview**\n\nUse the options below to review Board or audit-ready summaries, evidence, or full reports."

    return {
        "reply": intro,
        "followups": followups
    }

