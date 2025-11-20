

import re
from collections import Counter
from datetime import datetime, timedelta, timezone
from agents.cybersec import count_files, fetch_findings

EMAIL_RE = re.compile(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}')

def _parse_iso(dt_str: str):
    if not dt_str:
        return None
    try:
        s = str(dt_str).strip().replace("Z", "+00:00")
        dt = datetime.fromisoformat(s)
        if dt.tzinfo is None:
            dt = dt.replace(tzinfo=timezone.utc)
        return dt
    except Exception:
        return None

def _markdown_table(headers, rows):
    out = [
        "| " + " | ".join(headers) + " |",
        "| " + " | ".join(["-"*max(3, len(h)) for h in headers]) + " |"
    ]
    for r in rows:
        out.append("| " + " | ".join(r) + " |")
    return "\n".join(out)

def agent_summarizer_stats(context):
    """
    High-risk statistics dashboard scoped strictly by config_ids:
    files.user_id IN (:config_ids)
    """
    config_ids = context.get("config_ids") or []
    args = context.get("args") or {}
    days = int(args.get("days", 7))
    filter_source = (args.get("data_source") or "").strip() or None

    now_utc = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%SZ")

    # Scope strictly by config_ids via agents.cybersec
    all_rows = fetch_findings(config_ids=config_ids, risk_filter=None) or []

    def is_high(r):
        return (r.get("overall_risk_rating") or "").strip().lower() == "high"

    high_rows = [r for r in all_rows if is_high(r)]

    # Counts strictly by scope
    total_files = count_files(config_ids=config_ids)
    total_high = len(high_rows)

    # Recent High-risk (last N days)
    cutoff = datetime.now(timezone.utc) - timedelta(days=days)
    recent_high_count = 0
    for r in high_rows:
        lm = _parse_iso(r.get("last_modified") or "")
        if lm and lm >= cutoff:
            recent_high_count += 1

    # Breakdown by classification (High only)
    cls_counter = Counter((r.get("data_classification") or "Unknown").strip() for r in high_rows)
    cls_rows = [[k or "Unknown", str(v)] for k, v in sorted(cls_counter.items(), key=lambda x: (-x[1], x[0] or ""))]

    # Breakdown by subject area (High only)
    subj_counter = Counter((r.get("likely_data_subject_area") or "Unknown").strip() for r in high_rows)
    subj_rows = [[k or "Unknown", str(v)] for k, v in sorted(subj_counter.items(), key=lambda x: (-x[1], x[0] or ""))]

    # Breakdown by data source (High only)
    src_counter = Counter((r.get("data_source") or "unknown").strip().lower() for r in high_rows)
    src_rows = [[(k or "unknown").upper(), str(v)] for k, v in sorted(src_counter.items(), key=lambda x: (-x[1], x[0] or ""))]

    # Optional: Files by chosen source (High only)
    files_by_source_rows = []
    if filter_source:
        files = []
        for r in high_rows:
            if (r.get("data_source") or "").strip().lower() == filter_source.lower():
                files.append([
                    r.get("file_name") or "[unknown]",
                    (r.get("backend_source") or "").upper(),
                    r.get("data_classification") or "",
                    (r.get("overall_risk_rating") or ""),
                    (r.get("last_modified") or "")
                ])
        files_by_source_rows = files[:100]

    # Unique permission emails (High only)
    unique_emails = set()
    for r in high_rows:
        perms = r.get("permissions") or []
        if isinstance(perms, list):
            for p in perms:
                em = (p.get("principal_email") or "").strip().lower()
                if "@" in em:
                    unique_emails.add(em)
        else:
            s = str(perms)
            for e in EMAIL_RE.findall(s):
                unique_emails.add(e.lower())
    unique_emails_list = sorted(unique_emails)

    # Summary by auditor proposed action (High only)
    action_counter = Counter((r.get("auditor_proposed_action") or "None").strip() for r in high_rows)
    action_rows = [[k or "None", str(v)] for k, v in sorted(action_counter.items(), key=lambda x: (-x[1], x[0] or ""))]

    # High risk by jurisdiction (if present)
    juris_counter = Counter()
    for r in high_rows:
        cfs = r.get("compliance_findings") or []
        for cf in cfs:
            j = (cf.get("jurisdiction") or "").strip()
            if j:
                juris_counter[j] += 1
    juris_rows = [[k or "Unknown", str(v)] for k, v in sorted(juris_counter.items(), key=lambda x: (-x[1], x[0] or ""))]

    # Unique list of up to 50 High-risk files (by file_name), sorted by last_modified desc
    seen = set()
    aware_min = datetime.min.replace(tzinfo=timezone.utc)
    def lastmod_key(r):
        dt = _parse_iso(r.get("last_modified") or "") or aware_min
        return dt
    high_sorted = sorted(high_rows, key=lastmod_key, reverse=True)
    high_unique_50 = []
    for r in high_sorted:
        name = (r.get("file_name") or "").strip() or "[unknown]"
        if name.lower() in seen:
            continue
        seen.add(name.lower())
        high_unique_50.append(r)
        if len(high_unique_50) >= 50:
            break

    high_detail_rows = []
    for r in high_unique_50:
        high_detail_rows.append([
            r.get("file_name") or "[unknown]",
            (r.get("backend_source") or "").upper(),
            r.get("data_classification") or "",
            r.get("overall_risk_rating") or "",
            r.get("likely_data_subject_area") or "",
            r.get("last_modified") or ""
        ])

    # Build Markdown dashboard
    sections = []

    intro = (
        "## 🔎 CyberSecAI High‑Risk Statistics Dashboard\n\n"
        "This dashboard is generated by the Expert Cyber AI Agent (CyberSecAI) after analyzing your organization's files "
        "monitored by the cybersecai.io platform. It presents statistics scoped to files with an overall risk rating of "
        "“High” (unless otherwise noted), alongside a unique list of up to 50 High‑risk files with details.\n\n"
        f"- 🕒 Generated: {now_utc} UTC\n"
        f"- ⏱️ Recent window: last {days} days\n"
        "- Scope: files.user_id IN (:config_ids)\n"
    )
    sections.append(intro)

    kpi = (
        "### 📊 Cybersecurity Overview\n\n"
        "| Metric | Value |\n"
        "|-------|-------|\n"
        f"| 🗂️ Total files (all risk levels) | **{total_files}** |\n"
        f"| 🚨 Total High‑risk files | **{total_high}** |\n"
        f"| ⏱️ High‑risk files modified in last {days} days | **{recent_high_count}** |\n"
        "\n> Scope: All breakdowns and lists below are for files with overall_risk_rating == “High”.\n"
    )
    sections.append(kpi)

    sections.append("### 🧭 Breakdown by Data Classification (High‑risk)")
    sections.append(_markdown_table(["Classification", "Count"], cls_rows) if cls_rows else "_No High‑risk classification data._")

    sections.append("\n### 👥 Breakdown by Likely Data Subject Area (High‑risk)")
    sections.append(_markdown_table(["Subject Area", "Count"], subj_rows) if subj_rows else "_No High‑risk subject area data._")

    sections.append("\n### 🗄️ Count by Data Source / Backend (High‑risk)")
    sections.append(_markdown_table(["Data Source", "Count"], src_rows) if src_rows else "_No High‑risk data source info._")

    if filter_source:
        sections.append(f"\n### 📁 High‑risk Files in Source: {filter_source}")
        if files_by_source_rows:
            sections.append(_markdown_table(["File Name", "Source", "Classification", "Risk", "Last Modified"], files_by_source_rows))
        else:
            sections.append("_No High‑risk files found for this source._")

    sections.append("\n### 📨 Unique Permission Emails (High‑risk)")
    if unique_emails_list:
        sections.append("\n".join(f"- {e}" for e in unique_emails_list))
    else:
        sections.append("_No email-style permissions detected among High‑risk files._")

    sections.append("\n### 🧑‍⚖️ Count by Auditor Proposed Action (High‑risk)")
    sections.append(_markdown_table(["Proposed Action", "Count"], action_rows) if action_rows else "_No auditor actions found for High‑risk files._")

    if juris_rows:
        sections.append("\n### 🌐 High‑risk Files by Jurisdiction")
        sections.append(_markdown_table(["Jurisdiction", "Count"], juris_rows))

    sections.append("\n### 🧾 High‑risk Files — Unique List (By Recency)")
    if high_detail_rows:
        sections.append(_markdown_table(
            ["File Name", "Source", "Classification", "Risk", "Subject Area", "Last Modified"],
            high_detail_rows
        ))
        if total_high > 50:
            sections.append(f"_Showing up to 50 unique High‑risk files out of {total_high} total High‑risk files._")
    else:
        sections.append("_No High‑risk file details available to display._")

    reply = "\n\n".join(sections)
    followups = [
        {"label": "Show ALL externally shared files", "operation": "cybersec_show_external", "args": {}, "prompt": "List externally shared files."},
        {"label": "Show ALL duplicate files", "operation": "cybersec_find_duplicates", "args": {}, "prompt": "List duplicate files across storages."}
    ]
    return {"reply": reply, "followups": followups}