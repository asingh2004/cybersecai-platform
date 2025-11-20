# File: agents/cybersec.py


import csv
import os
from collections import defaultdict
import codecs
import re
import ast 
from config import client
import json


def get_no_more_questions_followup():
    return [{
        "label": "No More Questions",
        "operation": "cybersec_no_action",
        "args": {},
        "prompt": "Thank you, no further questions."
    }]


def agent_cybersec(context):
    user_id = context.get('user_id')
    total_files = count_files(user_id=user_id)
    highrisk = count_high_risk_files(user_id=user_id)
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
    user_id = context.get('user_id')

    # Get markdown table with externally shared HighRisk files
    reply = get_externally_shared_files(user_id=user_id)

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
    user_id = context.get('user_id')   # CHANGED: extract user_id
    reply = find_duplicate_files(user_id=user_id)  # returns Markdown table

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
    Produces a standards-aware (NIST/ISO) cyber risk report using LLM:
    - Builds high-risk exposure evidence (duplicates + external sharing),
    - Adds per-file details (auditor_agent_view, controls, compliance, classification, paths, etc),
    - Sends evidence to LLM for expert analysis and remediation plan,
    - Returns the LLM report preceded by an explicit intro and summary, followed by detailed high-risk file profiles, then a full appendix (all-risk duplicates + externally shared).
    """
    import csv, re, os
    from collections import defaultdict
    from datetime import datetime

    user_id = context.get('user_id')

    # 1) Primary evidence (HIGH RISK ONLY): prebuilt tables
    duplicates_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
    external_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

    evidence_context = [
        "## High-Risk Files Present in Multiple Storage Locations",
        duplicates_md if "No duplicate files detected" not in duplicates_md else "_No high-risk duplicate files detected._",
        "",
        "## High-Risk Files That Are Externally Shared",
        external_md if "No externally shared files detected" not in external_md else "_No high-risk externally shared files detected._"
    ]
    combined_evidence = "\n\n".join(evidence_context)

    # 2) Build high-risk per-file details from HighRisk CSV for the LLM to use and for user display
    INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}
    email_re = re.compile(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}')

    def truncate(text, limit=800):
        t = (text or "").strip()
        return t if len(t) <= limit else t[:limit - 1] + "…"

    highrisk_path_file = highrisk_path(user_id)

    risk_order = {
        "critical": 0, "very high": 1, "high": 2,
        "medium": 3, "moderate": 4,
        "low": 5, "very low": 6,
        "none": 7, "": 8
    }
    def risk_key(r):
        return risk_order.get((r or "").strip().lower(), 8)

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

    if os.path.isfile(highrisk_path_file):
        with open(highrisk_path_file, newline="", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for row in reader:
                name = (row.get("file_name") or "").strip()
                if not name:
                    continue
                d = per_file[name]
                d["file_name"] = name or d["file_name"]
                # Aggregate storages and paths
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

    # Compute exposure flags and build per-file sections
    highrisk_file_details_sections = []
    sorted_names = sorted(per_file.keys(), key=lambda n: (risk_key(per_file[n]["overall_risk_rating"]), n.lower()))
    for name in sorted_names:
        d = per_file[name]
        is_duplicate = len(d["storages"]) > 1
        is_external = len(d["external_recipients"]) > 0
        if not (is_duplicate or is_external):
            continue

        storages_str = ", ".join(sorted(d["storages"])) or "Unknown"
        paths_sorted = sorted(d["paths"])
        paths_preview = ", ".join(paths_sorted[:3])
        if len(paths_sorted) > 3:
            paths_preview += f" (+{len(paths_sorted) - 3} more)"
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
        highrisk_file_details_sections.append("\n".join(section))

    highrisk_file_details_md = (
        "## High-Risk File Details (Profiles)\n"
        "Below are detailed attributes for high-risk files that are duplicates and/or externally shared.\n\n"
        + ("\n\n".join(highrisk_file_details_sections) if highrisk_file_details_sections else "_No high-risk files with duplicate or external exposures required detailed profiling._")
    )

    # 3) Appendix DATA (all risks, verbatim)
    all_dupes_md = find_duplicate_files(user_id=user_id, high_risk_only=False)
    all_externals_md = get_externally_shared_files(user_id=user_id, high_risk_only=False)
    appendix_markdown = (
        "## Appendix: Full File Exposure Details\n\n"
        "### Appendix A: All Duplicate Files (Any Risk)\n"
        "The table below lists **all files (regardless of risk) that appear in multiple storage locations or folders:**\n\n"
        + (all_dupes_md if "No duplicate files detected" not in all_dupes_md else "_No files with duplicates detected in the environment._")
        + "\n\n"
        "### Appendix B: All Externally Shared Files (Any Risk)\n"
        "This table lists **all files shared with external addresses, regardless of risk rating:**\n\n"
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
        "- Provide a prioritized, actionable remediation plan mapping to relevant NIST/ISO controls (e.g., AC, AU, IR, MP, SC, SI, A.5–A.9, A.12, A.18), "
        "covering access restriction, least privilege, data minimization, retention, encryption, monitoring, incident response, and governance.\n"
        "- Include specific, stepwise actions that can be assigned to owners (who/what/when), and quick wins vs. medium-term tasks.\n"
        "- Call out the most urgent exposures and justify recommendations using the per-file details provided.\n"
        "- Do NOT reproduce or overwrite the appendix tables; simply reference: 'See Appendix for full tables.'\n\n"
        "### High-Risk Exposure Evidence (Tables)\n"
        f"{combined_evidence}\n\n"
        "### High-Risk File Details (Profiles)\n"
        f"{highrisk_file_details_md}\n\n"
        "---\n"
        "Please produce your report in clear Markdown with bold headings, numbered/bulleted lists, and short justifications linked to evidence. "
        "End with a brief note pointing readers to the Appendix for the complete tables."
    )

    system_prompt = (
        "You are an enterprise cybersecurity risk and compliance officer. "
        "All recommendations must explicitly reference (where relevant) NIST SP 800-53 and NIST CSF functions, "
        "and ISO/IEC 27001/27002 controls. Respond strictly in Markdown; do not include raw appendix tables."
    )

    from config import client  # ensure client is available
    resp = client.chat.completions.create(
        model="gpt-4.1",
        temperature=0.2,
        messages=[
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": llm_prompt}
        ]
    )
    expert_report_md = resp.choices[0].message.content

    # 5) Introductory banner at the very top of the final response
    now_utc = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%SZ")
    intro_md = (
        "## CyberSecAI Expert Cyber Risk Report\n\n"
        "This report has been generated by the Expert Cyber AI Agent (CyberSecAI) after analyzing your organization's files "
        "that are monitored by the cybersecai.io platform. It summarizes high‑risk file exposures and provides "
        "standards-aligned recommendations.\n\n"
        f"- Generated: {now_utc} UTC\n"
        f"- Tenant/User: `{user_id}`\n"
        "- Scope: High-risk files that are duplicated across storage systems and/or shared externally\n"
    )

    # Add summary immediately after the introductory text
    total_files = count_files(user_id=user_id)
    highrisk = count_high_risk_files(user_id=user_id)
    summary = (
        f"**Cybersecurity Overview**\n\n"
        f"- Total files: **{total_files}**\n"
        f"- High risk files: **{highrisk}**\n"
    )

    # 6) Final reply: Intro + Summary + expert report + high-risk profiles + appendix (verbatim tables)
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

# def agent_cybersec_recommendations(context):
#     """
#     Produces a standards-aware (NIST/ISO) cyber risk report using LLM:
#     - Builds high-risk exposure evidence (duplicates + external sharing),
#     - Adds per-file details (auditor_agent_view, controls, compliance, classification, paths, etc),
#     - Sends evidence to LLM for expert analysis and remediation plan,
#     - Returns the LLM report preceded by an explicit intro and summary, followed by detailed high-risk file profiles, then a full appendix (all-risk duplicates + externally shared).
#     """
#     import csv, re, os
#     from collections import defaultdict
#     from datetime import datetime

#     user_id = context.get('user_id')

#     # 1) Primary evidence (HIGH RISK ONLY): prebuilt tables
#     duplicates_md = find_duplicate_files(user_id=user_id, high_risk_only=True)
#     external_md = get_externally_shared_files(user_id=user_id, high_risk_only=True)

#     evidence_context = [
#         "## High-Risk Files Present in Multiple Storage Locations",
#         duplicates_md if "No duplicate files detected" not in duplicates_md else "_No high-risk duplicate files detected._",
#         "",
#         "## High-Risk Files That Are Externally Shared",
#         external_md if "No externally shared files detected" not in external_md else "_No high-risk externally shared files detected._"
#     ]
#     combined_evidence = "\n\n".join(evidence_context)

#     # 2) Build high-risk per-file details from HighRisk CSV for the LLM to use and for user display
#     INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}
#     email_re = re.compile(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}')

#     def truncate(text, limit=800):
#         t = (text or "").strip()
#         return t if len(t) <= limit else t[:limit - 1] + "…"

#     highrisk_path_file = highrisk_path(user_id)

#     risk_order = {
#         "critical": 0, "very high": 1, "high": 2,
#         "medium": 3, "moderate": 4,
#         "low": 5, "very low": 6,
#         "none": 7, "": 8
#     }
#     def risk_key(r):
#         return risk_order.get((r or "").strip().lower(), 8)

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

#     if os.path.isfile(highrisk_path_file):
#         with open(highrisk_path_file, newline="", encoding="utf-8") as f:
#             reader = csv.DictReader(f)
#             for row in reader:
#                 name = (row.get("file_name") or "").strip()
#                 if not name:
#                     continue
#                 d = per_file[name]
#                 d["file_name"] = name or d["file_name"]
#                 # Aggregate storages and paths
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

#     # Compute exposure flags and build per-file sections
#     highrisk_file_details_sections = []
#     sorted_names = sorted(per_file.keys(), key=lambda n: (risk_key(per_file[n]["overall_risk_rating"]), n.lower()))
#     for name in sorted_names:
#         d = per_file[name]
#         is_duplicate = len(d["storages"]) > 1
#         is_external = len(d["external_recipients"]) > 0
#         if not (is_duplicate or is_external):
#             continue

#         storages_str = ", ".join(sorted(d["storages"])) or "Unknown"
#         paths_sorted = sorted(d["paths"])
#         paths_preview = ", ".join(paths_sorted[:3])
#         if len(paths_sorted) > 3:
#             paths_preview += f" (+{len(paths_sorted) - 3} more)"
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
#         highrisk_file_details_sections.append("\n".join(section))

#     highrisk_file_details_md = (
#         "## High-Risk File Details (Profiles)\n"
#         "Below are detailed attributes for high-risk files that are duplicates and/or externally shared.\n\n"
#         + ("\n\n".join(highrisk_file_details_sections) if highrisk_file_details_sections else "_No high-risk files with duplicate or external exposures required detailed profiling._")
#     )

#     # 3) Appendix DATA (all risks, verbatim)
#     all_dupes_md = find_duplicate_files(user_id=user_id, high_risk_only=False)
#     all_externals_md = get_externally_shared_files(user_id=user_id, high_risk_only=False)
#     appendix_markdown = (
#         "## Appendix: Full File Exposure Details\n\n"
#         "### Appendix A: All Duplicate Files (Any Risk)\n"
#         "The table below lists **all files (regardless of risk) that appear in multiple storage locations or folders:**\n\n"
#         + (all_dupes_md if "No duplicate files detected" not in all_dupes_md else "_No files with duplicates detected in the environment._")
#         + "\n\n"
#         "### Appendix B: All Externally Shared Files (Any Risk)\n"
#         "This table lists **all files shared with external addresses, regardless of risk rating:**\n\n"
#         + (all_externals_md if "No externally shared files detected" not in all_externals_md else "_No externally shared files detected in the environment._")
#     )

#     # 4) LLM prompt — evidence + per-file details (model generates narrative only)
#     llm_prompt = (
#         "You are an enterprise cybersecurity expert with practical experience implementing NIST SP 800-53, "
#         "the NIST CSF, and ISO/IEC 27001 & 27002 controls. "
#         "You are given concrete evidence of high-risk file exposures (duplicates across storages and external sharing), "
#         "plus per-file details.\n\n"
#         "Your job:\n"
#         "- Summarize key risks and exposure patterns observed in the evidence.\n"
#         "- Provide a prioritized, actionable remediation plan mapping to relevant NIST/ISO controls (e.g., AC, AU, IR, MP, SC, SI, A.5–A.9, A.12, A.18), "
#         "covering access restriction, least privilege, data minimization, retention, encryption, monitoring, incident response, and governance.\n"
#         "- Include specific, stepwise actions that can be assigned to owners (who/what/when), and quick wins vs. medium-term tasks.\n"
#         "- Call out the most urgent exposures and justify recommendations using the per-file details provided.\n"
#         "- Do NOT reproduce or overwrite the appendix tables; simply reference: 'See Appendix for full tables.'\n\n"
#         "### High-Risk Exposure Evidence (Tables)\n"
#         f"{combined_evidence}\n\n"
#         "### High-Risk File Details (Profiles)\n"
#         f"{highrisk_file_details_md}\n\n"
#         "---\n"
#         "Please produce your report in clear Markdown with bold headings, numbered/bulleted lists, and short justifications linked to evidence. "
#         "End with a brief note pointing readers to the Appendix for the complete tables."
#     )

#     system_prompt = (
#         "You are an enterprise cybersecurity risk and compliance officer. "
#         "All recommendations must explicitly reference (where relevant) NIST SP 800-53 and NIST CSF functions, "
#         "and ISO/IEC 27001/27002 controls. Respond strictly in Markdown; do not include raw appendix tables."
#     )

#     from config import client  # ensure client is available
#     resp = client.chat.completions.create(
#         model="gpt-4.1",
#         temperature=0.2,
#         messages=[
#             {"role": "system", "content": system_prompt},
#             {"role": "user", "content": llm_prompt}
#         ]
#     )
#     expert_report_md = resp.choices[0].message.content

#     # 5) Introductory banner at the very top of the final response
#     now_utc = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%SZ")
#     intro_md = (
#         "## CyberSecAI Expert Cyber Risk Report\n\n"
#         "This report has been generated by the Expert Cyber AI Agent (CyberSecAI) after analyzing your organization's files "
#         "that are monitored by the cybersecai.io platform. It summarizes high‑risk file exposures and provides "
#         "standards-aligned recommendations.\n\n"
#         f"- Generated: {now_utc} UTC\n"
#         f"- Tenant/User: `{user_id}`\n"
#         "- Scope: High-risk files that are duplicated across storage systems and/or shared externally\n"
#     )

#     # Add summary immediately after the introductory text
#     total_files = count_files(user_id=user_id)
#     highrisk = count_high_risk_files(user_id=user_id)
#     summary = (
#         f"**Cybersecurity Overview**\n\n"
#         f"- Total files: **{total_files}**\n"
#         f"- High risk files: **{highrisk}**\n"
#     )

#     # 6) Final reply: Intro + Summary + expert report + high-risk profiles + appendix (verbatim tables)
#     full_reply = (
#         f"{intro_md}\n"
#         f"{summary}\n"
#         f"{expert_report_md}\n\n"
#         "---\n\n"
#         f"{highrisk_file_details_md}\n\n"
#         "---\n\n"
#         f"{appendix_markdown}"
#     )

#     followups = [
#         {
#             "label": "Show ALL externally shared files & associated Risk",
#             "operation": "cybersec_show_external",
#             "args": {},
#             "prompt": "Show all files currently shared externally with outside parties."
#         },
#         {
#             "label": "Show ALL duplicate files & associated Risk",
#             "operation": "cybersec_find_duplicates",
#             "args": {},
#             "prompt": "Which files appear in multiple storage locations or folders?"
#         }
#     ] + get_no_more_questions_followup()

#     return {
#         "reply": full_reply,
#         "followups": followups
#     }



def agent_cybersec_no_action(context):
    """
    Handler for 'No More Questions' followup
    """
    return {
        "reply": "Thank you! If you have more questions later, just ask.",
        "followups": []
    }



DATA_PATH = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv"

def allrisk_path(user_id):
    return os.path.join(DATA_PATH, f"AllRisk_{user_id}.csv")
def highrisk_path(user_id):
    return os.path.join(DATA_PATH, f"HighRisk_{user_id}.csv")

def count_files(user_id):
    """
    Returns the total count of files for the given user_id, as found in AllRisk_{user_id}.csv.
    Ignores blank and incomplete rows.
    """
    path = allrisk_path(user_id)
    if not os.path.isfile(path):
        return 0
    # Handles UTF-8 BOM if present
    with codecs.open(path, 'r', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        return sum(1 for row in reader if any((cell or '').strip() for cell in row.values()))

def count_high_risk_files(user_id):
    """
    Returns the count of high risk files for the given user_id, as found in HighRisk_{user_id}.csv.
    Ignores blank and incomplete rows.
    """
    path = highrisk_path(user_id)
    if not os.path.isfile(path):
        return 0
    with codecs.open(path, 'r', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        return sum(1 for row in reader if any((cell or '').strip() for cell in row.values()))



def get_externally_shared_files(user_id, high_risk_only=False):
    """
    Returns a Markdown table of externally shared files.
    Set high_risk_only to True for high-risk files only, False for all-risk files.
    """
    # Set of domains that are considered internal
    INTERNAL_DOMAINS = {'ozzieaccomptyltd.onmicrosoft.com', 'cybersecai.io'}

    path = highrisk_path(user_id) if high_risk_only else allrisk_path(user_id)
    if not os.path.isfile(path):
        return "No externally shared files detected."

    results = []
    with open(path, newline='', encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            file_name = row.get('file_name', '')
            storage_name = row.get('backend_source', '') or row.get('data_source', '')
            risk_rating = row.get('overall_risk_rating', '')

            # Parse permissions: try as JSON, fallback to string search
            perms = row.get('permissions', '')
            shared_with_emails = set()

            # Attempt to parse as JSON
            try:
                permissions_list = json.loads(perms)
                if not isinstance(permissions_list, list):
                    permissions_list = []
            except Exception:
                permissions_list = []

            # Extract emails in known fields for each permission entry
            for perm in permissions_list:
                # Deep search into possible nested user/group objects for 'email'
                def extract_emails(obj):
                    emails = set()
                    if isinstance(obj, dict):
                        for k, v in obj.items():
                            if isinstance(v, (dict, list)):
                                emails |= extract_emails(v)
                            elif k.lower() == 'email' and isinstance(v, str):
                                emails.add(v)
                    elif isinstance(obj, list):
                        for v in obj:
                            emails |= extract_emails(v)
                    return emails
                shared_with_emails |= extract_emails(perm)

            # Fallback: Also regex search for emails in whole permissions string (for legacy data/robustness)
            if not permissions_list or not shared_with_emails:
                found = set(re.findall(r'[A-Za-z0-9\._%+-]+@[A-Za-z0-9\.-]+\.[A-Za-z]{2,}', perms))
                shared_with_emails |= found

            # Remove internal emails
            out_emails = [
                email for email in shared_with_emails
                if not any(email.lower().endswith('@' + d) for d in INTERNAL_DOMAINS)
            ]

            if out_emails:
                results.append({
                    'name': file_name or '[unknown]', 
                    'shared_with': out_emails, 
                    'storage': storage_name,
                    'risk_rating': risk_rating
                })

    if not results:
        return "No externally shared files detected."

    description = (
        "Below is a list of files that have been shared externally (with addresses outside your organization's internal domains). "
        "For each file, the table shows the file name, the email addresses it was shared with, the storage system where the file resides, "
        "and the file's overall risk rating as identified in your assessment."
    )

    lines = [
        description,
        "",
        "| File Name | Shared With | Storage | Overall Risk Rating |",
        "|-----------|-------------|---------|---------------------|"
    ]
    for r in results:
        shared_with_str = ", ".join(sorted(r['shared_with']))
        lines.append(f"| `{r['name']}` | {shared_with_str} | {r['storage'] or 'Unknown'} | {r['risk_rating'] or ''} |")

    return "\n".join(lines)


def find_duplicate_files(user_id, high_risk_only=False):
    """
    Finds files with the same name present in multiple storages/folders.
    Shows storages and the overall risk rating from the CSV.
    """

    path = highrisk_path(user_id) if high_risk_only else allrisk_path(user_id)
    if not os.path.isfile(path):
        return "No file information found."

    from collections import defaultdict
    import codecs, csv

    file_storages = defaultdict(set)
    file_risk = {}

    with codecs.open(path, 'r', encoding='utf-8-sig') as f:
        reader = csv.DictReader(f)
        for row in reader:
            name = (row.get('file_name') or '').strip()
            # Use the correct column from your enhanced CSV:
            storage = (row.get('backend_source') or row.get('data_source') or '').strip()
            risk = (row.get('overall_risk_rating') or '').strip()
            if name and storage:
                file_storages[name].add(storage)
                if name not in file_risk and risk:
                    file_risk[name] = risk

    # Only include if the file is in >1 storage
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

