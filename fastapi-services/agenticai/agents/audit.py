
from typing import Dict, Any
from datetime import datetime, timedelta, timezone
import json
import re
from collections import defaultdict

from config import client
from utils.guardrails import output_guardrails
from utils.logging import log_to_laravel
from agents.findings import gather_high_risk_findings
from agents.cybersec import (
    find_duplicate_files,
    get_externally_shared_files,
    count_files,
    count_high_risk_files,
)

def preview_text(value, limit=800):
    """
    Safe preview for values that could be str, list, or dict. Returns a string trimmed to 'limit'.
    """
    if value is None:
        return ""
    if isinstance(value, str):
        s = value.strip()
    else:
        try:
            s = json.dumps(value, ensure_ascii=False)
        except Exception:
            s = str(value)
    if len(s) <= limit:
        return s
    return s[: limit - 1] + "…"

def agent_audit_dashboard(context):
    followups = [
        {"label": "Board Executive Summary", "operation": "audit_board_summary", "args": {}, "prompt": "Give me an executive summary for the board."},
        {"label": "Full Board-Level Audit Report", "operation": "audit_full", "args": {}, "prompt": "Show the full board-level audit report."},
        {"label": "Show Audit Evidence (tables)", "operation": "audit_evidence", "args": {}, "prompt": "Show me all audit evidence tables."},
    ] + get_audit_no_action_followup()
    intro = "**Audit/Compliance Overview**\n\nUse the options below to review Board or audit-ready summaries, evidence, or full reports."
    return {"reply": intro, "followups": followups}

def agent_audit_full(context):
    result = _agent_audit_base(context)
    return {
        "reply": result["markdown"],
        "followups": [
            {"label": "Board Executive Summary", "operation": "audit_board_summary", "args": {}, "prompt": "Give me an executive summary for the board."},
            {"label": "Show Audit Evidence (tables)", "operation": "audit_evidence", "args": {}, "prompt": "Show me all audit evidence tables."}
        ] + get_audit_no_action_followup()
    }

def agent_audit_board_summary(context):
    result = _agent_audit_board_summary_base(context)
    return {
        "reply": result["markdown"],
        "followups": [
            {"label": "Full Board-Level Audit Report", "operation": "audit_full", "args": {}, "prompt": "Show the full board-level audit report."},
            {"label": "Show Audit Evidence (tables)", "operation": "audit_evidence", "args": {}, "prompt": "Show me all audit evidence tables."}
        ] + get_audit_no_action_followup()
    }

def _agent_audit_base(context):
    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    config_ids = context.get("config_ids") or []
    if not user_id:
        raise ValueError("agent_audit requires 'user_id' in data.")

    # Scope strictly by config_ids for these calls
    dupes_high_md = find_duplicate_files(high_risk_only=True, config_ids=config_ids)
    external_high_md = get_externally_shared_files(high_risk_only=True, config_ids=config_ids)

    evidence_high_md = "\n\n".join([
        "## High-Risk Files Present in Multiple Storage Locations",
        dupes_high_md if "No duplicate files detected" not in dupes_high_md else "_No high-risk duplicate files detected._",
        "",
        "## High-Risk Files That Are Externally Shared",
        external_high_md if "No externally shared files detected" not in external_high_md else "_No high-risk externally shared files detected._"
    ])

    INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}
    email_re = re.compile(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}')

    high_rows = gather_high_risk_findings(config_ids=config_ids, user_id=user_id) or []
    per_file = defaultdict(lambda: {
        "file_name": "", "storages": set(), "paths": set(), "last_modified": "", "created": "",
        "overall_risk_rating": "", "data_classification": "", "likely_data_subject_area": "",
        "auditor_agent_view": "", "auditor_proposed_action": "", "compliance_findings": "",
        "cyber_proposed_controls": "", "permissions": "", "external_recipients": set()
    })

    now = datetime.now(timezone.utc)
    last_month_start = now - timedelta(days=30)
    prior_two_months_start = last_month_start - timedelta(days=60)
    recent_window, prior_window = [], []

    def parse_iso(dt_str: str):
        if not dt_str: return None
        try:
            dt = datetime.fromisoformat(str(dt_str).replace("Z","+00:00"))
            if dt.tzinfo is None: dt = dt.replace(tzinfo=timezone.utc)
            return dt
        except Exception:
            return None

    # Aggregate by file name
    for row in high_rows:
        name = (row.get("file_name") or "").strip()
        if not name: 
            continue
        d = per_file[name]
        d["file_name"] = name or d["file_name"]
        storage = (row.get("backend_source") or row.get("data_source") or "").strip()
        if storage: 
            d["storages"].add(storage)
        full_path = (row.get("full_path") or "").strip()
        file_path = (row.get("file_path") or "").strip()
        if full_path: 
            d["paths"].add(full_path)
        elif file_path: 
            d["paths"].add(file_path)

        for k_csv, k_store in [
            ("last_modified","last_modified"), ("created","created"),
            ("overall_risk_rating","overall_risk_rating"),
            ("data_classification","data_classification"),
            ("likely_data_subject_area","likely_data_subject_area"),
            ("auditor_agent_view","auditor_agent_view"),
            ("auditor_proposed_action","auditor_proposed_action"),
            ("compliance_findings","compliance_findings"),
            ("cyber_proposed_controls","cyber_proposed_controls"),
            ("permissions","permissions"),
        ]:
            val = row.get(k_csv)
            if val and not d[k_store]:
                d[k_store] = val

        perms = row.get("permissions") or []
        if isinstance(perms, list):
            for p in perms:
                e = (p.get("principal_email") or "").strip().lower()
                if e and not any(e.endswith("@" + dom) for dom in INTERNAL_DOMAINS):
                    d["external_recipients"].add(e)
        else:
            s = str(perms)
            for e in email_re.findall(s):
                el = e.lower()
                if not any(el.endswith("@" + dom) for dom in INTERNAL_DOMAINS):
                    d["external_recipients"].add(el)

        lm = parse_iso(row.get("last_modified") or "")
        if lm:
            rec = {
                "file_name": name,
                "last_modified": lm.isoformat(),
                "data_classification": (row.get("data_classification") or "").strip(),
                "proposed_action": (row.get("auditor_proposed_action") or "").strip(),
                "risk": (row.get("overall_risk_rating") or "").strip()
            }
            if lm >= last_month_start: 
                recent_window.append(rec)
            elif prior_two_months_start <= lm < last_month_start: 
                prior_window.append(rec)

    # Build high-risk per-file profiles
    sections = []
    risk_order = {"critical": 0, "very high": 1, "high": 2, "medium": 3, "moderate": 4, "low": 5, "very low": 6, "none": 7, "": 8}
    def risk_key(r): return risk_order.get((r or "").strip().lower(), 8)

    for name in sorted(per_file.keys(), key=lambda n: (risk_key(per_file[n]["overall_risk_rating"]), n.lower())):
        d = per_file[name]
        is_duplicate = len(d["storages"]) > 1
        is_external = len(d["external_recipients"]) > 0
        if not (is_duplicate or is_external):
            continue

        storages_str = ", ".join(sorted(d["storages"])) or "Unknown"
        paths_sorted = sorted(d["paths"])
        paths_preview = ", ".join(paths_sorted[:3]) + (f" (+{len(paths_sorted)-3} more)" if len(paths_sorted) > 3 else "")
        exposure_bits = []
        if is_duplicate:
            exposure_bits.append(f"Duplicate across storages: {storages_str}")
        if is_external:
            recipients = ", ".join(sorted(d["external_recipients"])) or "[unknown recipients]"
            exposure_bits.append(f"Externally shared with: {recipients}")
        exposure_line = "; ".join(exposure_bits) if exposure_bits else "None detected"

        section = [
            f"### File: `{name}`",
            "",
            "| Attribute | Value |",
            "|---|---|",
            f"| Overall Risk Rating | {d['overall_risk_rating'] or 'Unknown'} |",
            f"| Data Classification | {d['data_classification'] or 'Unknown'} |",
            f"| Likely Data Subject Area | {preview_text(d['likely_data_subject_area']) or 'N/A'} |",
            f"| Storages | {storages_str} |",
            f"| Paths | {paths_preview or 'N/A'} |",
            f"| Last Modified | {d['last_modified'] or 'Unknown'} |",
            f"| Created | {d['created'] or 'Unknown'} |",
            f"| Exposure | {exposure_line} |",
            "",
            "**Auditor Agent View**",
            "",
            preview_text(d["auditor_agent_view"]) or "_None provided._",
            "",
            "**Compliance Findings**",
            "",
            preview_text(d["compliance_findings"]) or "_None provided._",
            "",
            "**Proposed Controls (Cyber/ISO/NIST)**",
            "",
            preview_text(d.get("cyber_proposed_controls") or d.get("auditor_proposed_action")) or "_None provided._",
            ""
        ]
        sections.append("\n".join(section))

    highrisk_profiles_md = (
        "## High-Risk File Details (Profiles)\n"
        "Below are detailed attributes for high-risk files that are duplicates and/or externally shared.\n\n"
        + ("\n\n".join(sections) if sections else "_No high-risk files with duplicate or external exposures required detailed profiling._")
    )

    # Time-window tables
    def render_window_table(rows, title):
        if not rows:
            return f"### {title}\n_No high-risk file changes detected in this window._"
        rows_sorted = sorted(rows, key=lambda r: (risk_key(r.get("risk")), r.get("last_modified", "")))
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

    # Appendix (all risks) — use config_ids scope only
    dupes_all_md = find_duplicate_files(high_risk_only=False, config_ids=config_ids)
    external_all_md = get_externally_shared_files(high_risk_only=False, config_ids=config_ids)
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

    # LLM prompt
    now_utc = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%SZ")
    intro_banner = (
        "## CyberSecAI Internal Audit Board Report\n\n"
        "This report has been generated by the Expert Cyber AI Agent (CyberSecAI) after analyzing your organization's files "
        "that are monitored by the cybersecai.io platform. It synthesizes Board-ready insights on high‑risk file exposures, "
        "including duplicates across storages and externally shared items, and trends across the most recent time windows.\n\n"
        f"- Generated: {now_utc} UTC\n"
        f"- Region: {region}\n"
        "- Scope: High-risk files (duplicated and/or externally shared)\n"
    )

    total_files = count_files(config_ids=config_ids)
    highrisk_count = count_high_risk_files(config_ids=config_ids)
    summary_md = (
        "### Cybersecurity Overview\n\n"
        f"- Total files: **{total_files}**\n"
        f"- High risk files: **{highrisk_count}**\n"
    )

    board_prompt = (
        "You are a highly professional internal auditor preparing a Board-level risk report. "
        "Use the evidence and time-window tables below to:\n"
        "1) Write a concise Executive Summary highlighting overall exposure and any sudden changes in the last month.\n"
        "2) Analyze trends and red flags for the last 1 month and the prior 2-month window.\n"
        "3) Provide a comparative analysis (new/recurring/escalating vs resolved risks).\n"
        "4) Describe governance & compliance implications for the specified region.\n"
        "5) Provide Board-ready recommendations (who/what/when), prioritizing actions.\n\n"
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
        try:
            log_to_laravel("agent_audit_error", {"error": str(e)})
        except Exception:
            pass
        from fastapi import HTTPException
        raise HTTPException(status_code=500, detail=f"Audit LLM failed: {str(e)}")

def _agent_audit_board_summary_base(context):
    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    config_ids = context.get("config_ids") or []
    use_case_label = (context.get("label") or context.get("use_case") or "").strip()
    if not user_id:
        raise ValueError("agent_audit_board_summary requires 'user_id' in context.")

    INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}
    email_re = re.compile(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}')

    now = datetime.now(timezone.utc)
    last_month_start = now - timedelta(days=30)
    prior_two_months_start = last_month_start - timedelta(days=60)

    total_files = count_files(config_ids=config_ids)
    highrisk_count = count_high_risk_files(config_ids=config_ids)
    highrisk_pct = round(100.0 * highrisk_count / total_files, 2) if total_files else 0.0

    dupes_high_md = find_duplicate_files(high_risk_only=True, config_ids=config_ids)
    external_high_md = get_externally_shared_files(high_risk_only=True, config_ids=config_ids)

    high_rows = gather_high_risk_findings(config_ids=config_ids, user_id=user_id) or []
    per_file = defaultdict(lambda: {
        "file_name": "", "storages": set(), "paths": set(), "last_modified": "",
        "overall_risk_rating": "", "data_classification": "", "business_area": "",
        "auditor_proposed_action": "", "external_recipients": set()
    })

    recent_window, prior_window = [], []
    risk_order = {"critical": 0, "very high": 1, "high": 2, "medium": 3, "moderate": 4, "low": 5, "very low": 6, "none": 7, "": 8}
    def risk_key(r): return risk_order.get((r or "").strip().lower(), 8)
    def parse_iso(dt_str: str):
        if not dt_str: return None
        try:
            dt = datetime.fromisoformat(str(dt_str).replace("Z","+00:00"))
            if dt.tzinfo is None: dt = dt.replace(tzinfo=timezone.utc)
            return dt
        except Exception:
            return None

    for row in high_rows:
        name = (row.get("file_name") or "").strip()
        if not name: continue
        d = per_file[name]
        d["file_name"] = name
        storage = (row.get("backend_source") or row.get("data_source") or "").strip()
        if storage: d["storages"].add(storage)
        full_path = (row.get("full_path") or "").strip()
        file_path = (row.get("file_path") or "").strip()
        if full_path: d["paths"].add(full_path)
        elif file_path: d["paths"].add(file_path)

        for k_csv, k_store in [
            ("last_modified","last_modified"),
            ("overall_risk_rating","overall_risk_rating"),
            ("data_classification","data_classification"),
            ("business_area","business_area"),
            ("auditor_proposed_action","auditor_proposed_action"),
        ]:
            val = (row.get(k_csv) or "")
            if val and not d[k_store]:
                d[k_store] = val

        perms = (row.get("permissions") or [])
        if isinstance(perms, list):
            for p in perms:
                e = (p.get("principal_email") or "").strip().lower()
                if e and not any(e.endswith("@" + dom) for dom in INTERNAL_DOMAINS):
                    d["external_recipients"].add(e)
        else:
            s = str(perms)
            for email in email_re.findall(s):
                el = email.lower()
                if not any(el.endswith("@" + dom) for dom in INTERNAL_DOMAINS):
                    d["external_recipients"].add(el)

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
            if lm >= last_month_start: recent_window.append(rec)
            elif prior_two_months_start <= lm < last_month_start: prior_window.append(rec)

    def exposure_weight(name):
        d = per_file[name]; w = 0
        if len(d["storages"]) > 1: w += 2
        if len(d["external_recipients"]) > 0: w += 3
        w += max(0, 2 - risk_key(d["overall_risk_rating"]))
        return -w

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

    kpi_md = (
        f"- Total files: {total_files}\n"
        f"- High-risk files: {highrisk_count} ({highrisk_pct}%)\n"
        f"- High-risk duplicates: {len([n for n, d in per_file.items() if len(d['storages']) > 1])}\n"
        f"- High-risk externally shared: {len([n for n, d in per_file.items() if len(d['external_recipients']) > 0])}\n"
    )

    def render_window_table(rows, title):
        if not rows:
            return f"### {title}\n_No high-risk activity detected in this window._"
        rows_sorted = sorted(
            rows,
            key=lambda r: (risk_key(r.get("risk")), r.get("last_modified", "")),
        )
        header = f"### {title}\n| File | Last Modified | Data Type | Business Area | Risk | Proposed Action |\n|------|---------------|-----------|---------------|------|-----------------|"
        body = "\n".join(
            f"| `{r['file_name']}` | {r['last_modified']} | {r['data_classification'] or ''} | "
            f"{r['business_area'] or ''} | {r['risk'] or ''} | {r['proposed_action'] or ''} |"
            for r in rows_sorted[:40]
        )
        return header + "\n" + body

    last_month_table = render_window_table(recent_window, "Last 1 Month — High-Risk Activity")
    prior_two_months_table = render_window_table(prior_window, "1–2 Months Before — High-Risk Activity")

    prompt = (
        "You are a world-class internal auditor preparing a one-page Board Executive Summary. "
        "Provide crisp sections and concrete actions.\n\n"
        f"Region: {region}\n\nKPIs:\n{kpi_md}\n\nHigh-Risk Evidence (duplicates/external):\n{dupes_high_md}\n\n{external_high_md}\n\n"
        f"Time-Window Evidence:\n{last_month_table}\n\n{prior_two_months_table}\n\nTop 5 — Board Attention (evidence table):\n{attention_table_md}\n"
    )

    now_utc = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%SZ")
    intro = (
        "## Board Executive Summary\n"
        f"- Generated: {now_utc} UTC\n"
        f"- Use Case: {use_case_label or 'Board Member: Executive Summary'}\n"
    )

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
    return {"markdown": intro + "\n\n" + narrative}

def agent_audit_evidence(context):
    config_ids = context.get('config_ids') or []
    evidence = []
    evidence.append("## High-Risk Files Present in Multiple Storage Locations\n")
    evidence.append(find_duplicate_files(high_risk_only=True, config_ids=config_ids))
    evidence.append("\n\n## High-Risk Files That Are Externally Shared\n")
    evidence.append(get_externally_shared_files(high_risk_only=True, config_ids=config_ids))
    return {
        "reply": "\n".join(evidence),
        "followups": [
            {"label": "Board Executive Summary", "operation": "audit_board_summary", "args": {}, "prompt": "Give me an executive summary for the board."},
            {"label": "Full Board-Level Audit Report", "operation": "audit_full", "args": {}, "prompt": "Show the full board-level audit report."}
        ] + get_audit_no_action_followup()
    }

def get_audit_no_action_followup():
    return [{"label": "No More Questions", "operation": "audit_no_action", "args": {}, "prompt": "Thank you, no further questions."}]

def agent_audit_no_action(context):
    return {"reply": "Thank you! If you have more questions later about audit/compliance, just ask.", "followups": []}

def agent_audit_compliance_advisory(context):
    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    config_ids = context.get("config_ids") or []
    if not user_id:
        raise ValueError("user_id required")

    now = datetime.now(timezone.utc)
    now_utc = now.strftime("%Y-%m-%d %H:%M UTC")
    total_files = count_files(config_ids=config_ids)
    highrisk_count = count_high_risk_files(config_ids=config_ids)

    dupes_high_md = find_duplicate_files(high_risk_only=True, config_ids=config_ids)
    external_high_md = get_externally_shared_files(high_risk_only=True, config_ids=config_ids)

    evidence_high_md = (
        "#### High-Risk Duplicates\n" + 
        (dupes_high_md if "No duplicate files detected" not in dupes_high_md else "_No high-risk duplicate files detected._") +
        "\n\n#### High-Risk External Shares\n" +
        (external_high_md if "No externally shared files detected" not in external_high_md else "_No high-risk externally shared files detected._")
    )

    high_rows = gather_high_risk_findings(config_ids=config_ids, user_id=user_id) or []
    urgent_table = "_No unresolved or new High risk files detected._"
    if high_rows:
        urgent_table = "| File Name | Storage | Data Classification | Risk | Last Modified | Proposed Action |\n|---|---|---|---|---|---|\n"
        for row in high_rows:
            storage = row.get('backend_source') or row.get('data_source') or ''
            urgent_table += f"| `{row.get('file_name','')}` | {storage} | {row.get('data_classification','')} | {row.get('overall_risk_rating','')} | {row.get('last_modified','')} | {row.get('auditor_proposed_action','')} |\n"

    intro_md = (
        f"## Compliance Advisory & Legal Risk Action – {region}\n\n"
        f"**Generated:** {now_utc}\n"
        f"**Files Monitored:** {total_files}\n"
        f"**High Risk Files:** {highrisk_count}\n"
        "\n> This report provides a prioritized, context-specific compliance and legal risk review."
    )

    prompt = (
        f"{intro_md}\n\n"
        "You are a highly experienced compliance and legal risk advisor. Analyze the technical evidence and produce a compliance advisory suitable for legal/privacy teams. Include:\n"
        "1. Executive Summary.\n"
        "2. High-Risk/Urgent Action Table (from evidence).\n"
        "3. Jurisdictional implications for the specified region.\n"
        "4. Numbered, practical recommendations with owners and deadlines.\n"
        "5. Limitations if evidence is insufficient.\n\n"
        "### Evidence\n"
        f"{evidence_high_md}\n\n"
        "### URGENT FILES FOR ACTION\n"
        f"{urgent_table}\n\n"
    )

    try:
        resp = client.chat.completions.create(
            model="gpt-4.1", temperature=0.19,
            messages=[{"role": "system", "content": "You are a world-class compliance and privacy legal advisor."},
                      {"role": "user", "content": prompt}]
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

def agent_audit_find_risk_hotspots(context):
    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    config_ids = context.get("config_ids") or []
    if not user_id:
        raise ValueError("user_id required")

    now = datetime.now(timezone.utc)
    now_utc = now.strftime("%Y-%m-%d %H:%M UTC")
    total_files = count_files(config_ids=config_ids)
    highrisk_count = count_high_risk_files(config_ids=config_ids)

    dupes_high_md = find_duplicate_files(high_risk_only=True, config_ids=config_ids)
    external_high_md = get_externally_shared_files(high_risk_only=True, config_ids=config_ids)

    high_rows = gather_high_risk_findings(config_ids=config_ids, user_id=user_id) or []
    import os
    hotspots = []
    per_folder = defaultdict(list)
    per_type = defaultdict(list)
    for row in high_rows:
        risk_level = (row.get("overall_risk_rating") or "").lower()
        if risk_level in ("high", "very high", "critical"):
            hotspots.append(row)
            folder = ""
            fp = row.get("file_path") or row.get("full_path") or ""
            if isinstance(fp, str) and "/" in fp:
                folder = os.path.dirname(fp)
            if folder:
                per_folder[folder].append(row)
            dtype = (row.get("data_classification") or "").strip()
            if dtype:
                per_type[dtype].append(row)

    def sort_key(row):
        order = {"critical":0,"very high":1,"high":2,"medium":3,"low":4,"":5}
        lvl = (row.get('overall_risk_rating') or "").lower()
        t = row.get('last_modified') or ""
        return (order.get(lvl,99), str(t)[::-1])
    top_hotspots = sorted(hotspots, key=sort_key)[:10]

    top_hot_table = "_No system hotspots detected._"
    if top_hotspots:
        top_hot_table = "| File/Folder | Data Class | Risk | Last Modified | Exposure | Proposed Action |\n|---|---|---|---|---|---|\n"
        for row in top_hotspots:
            exposures = []
            storage = row.get('backend_source') or row.get('data_source') or ''
            if row.get('file_name') and storage:
                exposures.append(f"duplicate in {storage}")
            perms = row.get('permissions') or []
            if isinstance(perms, list):
                has_external = any((p.get("principal_email") or "").lower().split("@")[-1] not in ("ozzieaccomptyltd.onmicrosoft.com","cybersecai.io") for p in perms if p.get("principal_email"))
                if has_external:
                    exposures.append("external share")
            exp = "; ".join(exposures) if exposures else ""
            top_hot_table += f"| `{row.get('file_name','')}` | {row.get('data_classification','')} | {row.get('overall_risk_rating','')} | {row.get('last_modified','')} | {exp} | {row.get('auditor_proposed_action','')} |\n"

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

    intro_md = (
        f"## System Risk Hotspots & Risk Concentration (Expert Auditor Report)\n\n"
        f"**Generated:** {now_utc}\n"
        f"**Region:** {region}\n"
        f"**Total Files:** {total_files}\n"
        f"**High Risk Files:** {highrisk_count}\n"
        "\n> This expert analysis identifies and prioritizes the system’s most critical risk concentrations and exposure hotspots."
    )

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
        "1. Executive Summary overview.\n"
        "2. Top 10 risk hotspots table (why critical).\n"
        "3. Cluster systemwide risk by folder/data type.\n"
        "4. For each hotspot, prioritized next action with urgency and owner.\n"
        "5. Root causes and monitoring improvements.\n"
    )

    try:
        resp = client.chat.completions.create(
            model="gpt-4.1", temperature=0.19,
            messages=[{"role": "system", "content": "You are an expert digital risk and audit analyst for board-level reporting."},
                      {"role": "user", "content": prompt}]
        )
        narrative = resp.choices[0].message.content
        output_guardrails(narrative)
        return {
            "reply": narrative,
            "followups": [
                {"label": "Compliance Advisory", "operation": "audit_compliance_advisory", "args": {}, "prompt": "Show urgent compliance/legal actions."},
                {"label": "Show Alerts & Monitoring", "operation": "audit_continuous_alerts", "args": {}, "prompt": "Show any new high risk events detected."},
            ] + get_audit_no_action_followup()
        }
    except Exception as e:
        return {"reply": f"Find Risk Hotspots failed: {e}", "followups": get_audit_no_action_followup()}

def agent_audit_continuous_alerts(context):
    user_id = context.get("user_id")
    region = context.get("region") or "Australia"
    config_ids = context.get("config_ids") or []
    if not user_id:
        raise ValueError("user_id required")
    
    now = datetime.now(timezone.utc)
    now_utc = now.strftime("%Y-%m-%d %H:%M UTC")
    time_window_hours = 36
    time_cutoff = now - timedelta(hours=time_window_hours)

    high_rows = gather_high_risk_findings(config_ids=config_ids, user_id=user_id) or []
    new_events = []
    all_alerts = 0
    for row in high_rows:
        lm = row.get("last_modified")
        try:
            dt = datetime.fromisoformat(str(lm).replace("Z", "+00:00")) if lm else None
            if dt and dt >= time_cutoff:
                all_alerts += 1
                new_events.append(row)
        except Exception:
            pass

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

    intro_md = (
        f"## Continuous Risk Alerts & Change Monitoring – {region}\n"
        f"**Generated:** {now_utc}\n"
        f"**Monitoring window:** Last {time_window_hours} hours\n"
        f"**New High-Risk/Non-Compliant Items:** {all_alerts}\n"
        "> Highlights all recently detected high-risk or non-compliant files.\n\n"
    )

    prompt = (
        f"{intro_md}"
        "You are a master auditor/CISO analyst. Produce a board-ready alerts & change monitoring summary:\n"
        "1. Executive Summary of changes since last scan.\n"
        "2. Alerts Table Review: impact, compliance implications, triggers, recommended actions.\n"
        "3. Must-Escalate Detection items.\n"
        "4. Continuous Monitoring & Next Steps.\n\n"
        f"### Alerts/Changelog (Last {time_window_hours}h)\n"
        f"{event_table}"
    )
    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            temperature=0.12,
            messages=[
                {"role": "system", "content": "You are an expert continuous audit, cyber, and compliance monitoring advisor."},
                {"role": "user", "content": prompt},
            ]
        )
        narrative = resp.choices[0].message.content
        output_guardrails(narrative)
        return {
            "reply": narrative,
            "followups": get_audit_no_action_followup(),
        }
    except Exception as e:
        return {
            "reply": f"Continuous alerts failed: {e}",
            "followups": get_audit_no_action_followup(),
        }

def agent_audit_evidence(context):
    config_ids = context.get('config_ids') or []
    evidence = []
    evidence.append("## High-Risk Files Present in Multiple Storage Locations\n")
    evidence.append(find_duplicate_files(high_risk_only=True, config_ids=config_ids))
    evidence.append("\n\n## High-Risk Files That Are Externally Shared\n")
    evidence.append(get_externally_shared_files(high_risk_only=True, config_ids=config_ids))
    return {
        "reply": "\n".join(evidence),
        "followups": [
            {"label": "Board Executive Summary", "operation": "audit_board_summary", "args": {}, "prompt": "Give me an executive summary for the board."},
            {"label": "Full Board-Level Audit Report", "operation": "audit_full", "args": {}, "prompt": "Show the full board-level audit report."}
        ] + get_audit_no_action_followup()
    }

def get_audit_no_action_followup():
    return [{"label": "No More Questions", "operation": "audit_no_action", "args": {}, "prompt": "Thank you, no further questions."}]

def agent_audit_no_action(context):
    return {"reply": "Thank you! If you have more questions later about audit/compliance, just ask.", "followups": []}

def agent_audit_dispatcher(context):
    """
    Central dispatcher used by orchestrator. Routes based on operation or label.
    """
    operation = (context.get("operation") or "").lower()
    label = (context.get("label") or context.get("use_case") or "").strip()

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

    return agent_audit_dashboard(context)