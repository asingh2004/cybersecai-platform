
from utils.logging import log_to_laravel
from utils.markdown_format import format_high_risk_files_markdown, format_all_risks_files_markdown, format_medium_risk_files_markdown
from utils.dateparse import parse_date_from_query
from agents.findings import gather_high_risk_findings, gather_all_findings
from config import client

from typing import Dict, Any
import os, json, re
from utils.guardrails import output_guardrails

CHATBOT_MODEL = os.getenv("OPENAI_GENERAL_CHAT_MODEL", "gpt-4.1")
CHATBOT_FALLBACK_MODEL = os.getenv("OPENAI_GENERAL_CHAT_FALLBACK_MODEL", "gpt-4o-mini")
CHATBOT_TEMPERATURE = float(os.getenv("CHATBOT_TEMPERATURE", "0.3"))
CHATBOT_MAX_HISTORY = int(os.getenv("CHATBOT_MAX_MESSAGES", "20"))

def agent_chatbot(data: Dict[str, Any]):
    class DummyChatReq:
        persona: str
        query: str
        messages: list
        config_ids: list
        user_id: Any
        def __init__(self, persona, query, messages, config_ids, user_id):
            self.persona = persona; self.query = query; self.messages = messages; self.config_ids = config_ids; self.user_id = user_id
    cbreq = DummyChatReq(
        data.get('persona', 'default'),
        data.get('query', ''),
        data.get('messages') or ([{"role": "user", "content": data.get("query", "")}] if data.get("query") else []),
        data.get('config_ids') or [],
        data.get('user_id')
    )
    return agent_chatbot_actual(cbreq)

def agent_chatbot_actual(req):
    def general_chat_fallback(req) -> Dict[str, Any]:
        persona_instructions = {
            "Risk Auditor": "You answer as a highly professional compliance/risk auditor. Provide well-structured Markdown.",
            "Cybersecurity": "You are an experienced cyber defense expert. Always use Markdown structure in responses.",
            "Board Member": "You are a board-level advisor. Provide concise, structured Markdown for business readability.",
            "All": "You are CybersecAI, combining compliance, cyber, and business expertise. Always provide structured Markdown.",
            "Auditor/Security": "You are a combined compliance auditor and cybersecurity analyst. Provide structured Markdown.",
            "default": "You are CybersecAI, an AI chat advisor. Always return clean, structured Markdown using headers, lists, bold, etc."
        }
        system_content = persona_instructions.get(getattr(req, "persona", "default"), persona_instructions["default"])
        system_content += "\n\nRespond ALWAYS in Markdown (no plain text)."

        messages = getattr(req, "messages", []) or []
        if not isinstance(messages, list): messages = []
        if len(messages) > CHATBOT_MAX_HISTORY: messages = messages[-CHATBOT_MAX_HISTORY:]

        conversation = [{"role": "system", "content": system_content}]
        for m in messages:
            role = m.get("role", "user")
            if role not in ("user","assistant"): role = "user"
            content = m.get("content", "")
            if content: conversation.append({"role": role, "content": content})

        if getattr(req, "query", None):
            last_user_content = None
            for m in reversed(conversation):
                if m["role"] == "user":
                    last_user_content = m["content"]
                    break
            if last_user_content != req.query:
                conversation.append({"role": "user", "content": req.query})

        try:
            resp = client.chat.completions.create(model=CHATBOT_MODEL, messages=conversation, temperature=CHATBOT_TEMPERATURE)
        except Exception as e1:
            log_to_laravel(f"General-chat primary model '{CHATBOT_MODEL}' failed: {repr(e1)}; trying fallback '{CHATBOT_FALLBACK_MODEL}'")
            try:
                resp = client.chat.completions.create(model=CHATBOT_FALLBACK_MODEL, messages=conversation, temperature=CHATBOT_TEMPERATURE)
            except Exception as e2:
                return {"reply": f"AI error: {str(e2)}"}

        text = resp.choices[0].message.content
        try: output_guardrails(text)
        except Exception: pass
        return {"reply": text}

    if not getattr(req, "user_id", None) or not getattr(req, "config_ids", None):
        return general_chat_fallback(req)

    CSV_FIELD_MAP = {
        "data subject area": "likely_data_subject_area",
        "subject area": "likely_data_subject_area",
        "data classification": "data_classification",
        "classification": "data_classification",
        "risk rating": "overall_risk_rating",
        "file name": "file_name",
        "file path": "file_path",
        "full path": "full_path",
        "last modified": "last_modified",
        "created": "created",
        "permissions": "permissions",
        "auditor agent view": "auditor_agent_view",
        "auditor proposed action": "auditor_proposed_action",
        "Immediate Containment": "auditor_proposed_action",
        "Containment": "auditor_proposed_action",
        "auditor agent insight": "auditor_agent_view",
        "recommended action": "auditor_proposed_action",
        "cyber proposed controls": "cyber_proposed_controls",
    }

    COMPLIANCE_FIELD_MAP = {
        "standard": "standard",
        "jurisdiction": "jurisdiction",
        "detected field": "detected_fields",
        "detected fields": "detected_fields",
        "finding risk": "risk"
    }

    user_query = (getattr(req, "query", "") or "").lower().strip()
    user_id = getattr(req, "user_id", None)
    config_ids = getattr(req, "config_ids", []) or []

    if any(kw in user_query for kw in ["medium risk", "low risk", "all risk", "any risk"]):
        findings = gather_all_findings(config_ids, user_id=user_id)
    else:
        findings = gather_high_risk_findings(config_ids, user_id=user_id)

    if re.search(r'(list|show|all).*(detail|record|full|everything).*(high risk)', user_query, re.I):
        markdown = format_high_risk_files_markdown(findings)
        return {"reply": markdown, "raw": findings}
    elif re.search(r'(list|show|all).*(detail|record|full|everything).*(medium risk)', user_query, re.I):
        markdown = format_medium_risk_files_markdown(findings)
        return {"reply": markdown, "raw": findings}
    elif re.search(r'(list|show|all).*(detail|record|full|everything).*(risk)', user_query, re.I):
        markdown = format_all_risks_files_markdown(findings)
        return {"reply": markdown, "raw": findings}

    if re.search(r"(how many|count|number of).*(file|files).*(data classification|classification)", user_query):
        class_count = {}
        for f in findings:
            cls = (f.get("data_classification") or "").strip()
            if cls: class_count[cls] = class_count.get(cls, 0) + 1
            else: class_count["[Unclassified]"] = class_count.get("[Unclassified]", 0) + 1
        if not class_count:
            reply = "_No data classifications found in current high risk files._"
        else:
            reply = "### High Risk File Count by Data Classification\n\n| Data Classification | File Count |\n|---------------------|------------|\n"
            for k, v in class_count.items(): reply += f"| {k} | {v} |\n"
        return {"reply": reply, "raw": class_count}

    for key, field in CSV_FIELD_MAP.items():
        patterns = [fr"(list|show|unique|all).*{re.escape(key)}", fr"{re.escape(key)}.*(list|show|unique|all)"]
        if any(re.search(p, user_query) for p in patterns):
            unique_values = sorted(set(str(f.get(field, '')).strip() for f in findings if f.get(field)))
            if field == "permissions":
                perms = set()
                for f in findings:
                    pval = f.get("permissions")
                    if not pval: continue
                    if isinstance(pval, str): perms.add(pval)
                    elif isinstance(pval, list):
                        for p in pval: perms.add(str(p))
                    elif isinstance(pval, dict): perms.add(str(pval))
                unique_values = sorted(perms)
            if unique_values:
                reply = f"# Unique {key.title()}\n" + "\n".join(f"- {x}" for x in unique_values)
            else:
                reply = f"_No {key} values found in the current evidence._"
            return {"reply": reply}

    for key, field in COMPLIANCE_FIELD_MAP.items():
        patterns = [fr"(list|show|unique|all).*{re.escape(key)}", fr"{re.escape(key)}.*(list|show|unique|all)"]
        if any(re.search(p, user_query) for p in patterns):
            results_set = set()
            for f in findings:
                compliance = f.get("compliance_findings", [])
                if not compliance or not isinstance(compliance, list): continue
                for r in compliance:
                    if field == "detected_fields" and isinstance(r.get(field), list):
                        for val in r[field]:
                            if val: results_set.add(val)
                    else:
                        value = r.get(field)
                        if value: results_set.add(str(value))
            unique_values = sorted(results_set)
            reply = (f"# Unique Compliance Finding {key.title()}\n" + "\n".join(f"- {v}" for v in unique_values)) if unique_values else f"_No {key} values found in compliance findings._"
            return {"reply": reply}

    storage_count, backend_files = {}, {}
    for entry in findings:
        ds = (entry.get('data_source') or "").upper()
        if ds:
            storage_count[ds] = storage_count.get(ds, 0) + 1
            backend_files.setdefault(ds, []).append(entry.get("file_name", ""))
    storage_md = ""
    if storage_count:
        storage_md = "### File Count by Storage Backend\n\n| Storage Backend | Files Found |\n|----------------|-------------|\n"
        for ds, cnt in storage_count.items():
            storage_md += f"| {ds} | {cnt} |\n"
        storage_md += "\n"
        for ds, files in backend_files.items():
            storage_md += f"**{ds} Files:**\n" + "\n".join(f"- {f}" for f in files) + "\n\n"
    else:
        storage_md = "_No files found in any backend._\n"

    if re.search(r'list all files|show all files|all file names', user_query):
        all_files = sorted(set(str(f.get("file_name", "")).strip() for f in findings if f.get("file_name")))
        reply = "# All File Names\n" + "\n".join(f"- {f}" for f in all_files) if all_files else "_No files found in the current evidence._"
        return {"reply": reply}
    if re.search(r'(list|show|all) (records|rows|details|findings)', user_query):
        pretty = json.dumps(findings, indent=2, ensure_ascii=False)
        return {"reply": f"```json\n{pretty}\n```"}

    persona_instructions = {
        "Risk Auditor": "You answer as a highly professional compliance/risk auditor. Use Markdown.",
        "Cybersecurity": "You are an experienced cyber defense expert. Use Markdown.",
        "Board Member": "You are a board-level advisor. Use concise Markdown.",
        "All": "You are CybersecAI, combining compliance, cyber, and business expertise.",
        "Auditor/Security": "You are a combined compliance auditor and cybersecurity analyst.",
        "default": "You are CybersecAI, an AI chat advisor. Always return structured Markdown."
    }
    system_content = persona_instructions.get(getattr(req, "persona", "default"), persona_instructions["default"])

    date_bounds = parse_date_from_query(getattr(req, "query", ""))
    filtered_evidence = []
    if date_bounds:
        import dateutil.parser
        for entry in findings:
            lastmod = entry.get('last_modified')
            if lastmod:
                try:
                    dt = dateutil.parser.parse(lastmod)
                    if date_bounds[0] <= dt <= date_bounds[1]:
                        filtered_evidence.append(entry)
                except Exception:
                    continue
    elif re.search(r'(last|recent)\s+\d+\s+(file|change)', getattr(req, "query", ""), re.I):
        filtered_evidence = findings[-100:]
    else:
        filtered_evidence = findings[-100:]

    evidence_context = storage_md
    if filtered_evidence:
        evidence_context += "\n#### Selected Files in Range:\n"
        for f in filtered_evidence[:300]:
            try:
                fname = f.get('file_name') or "[unknown file]"
                lastmod = f.get('last_modified') or ""
                risk = f.get("overall_risk_rating") or ""
                evidence_context += f"- {fname} ({lastmod}) Risk: {risk}\n"
            except Exception:
                continue
    else:
        evidence_context += "\n[NO MATCHING FILE CHANGES for your account for the specified date/range. No results found. AI MUST NOT INVENT FILES.]"

    full_history = getattr(req, "messages", [])[-2:]
    conversation = [{"role": "system", "content": system_content + "\n\nRespond ALWAYS in Markdown and NEVER in plain text - use headers, lists, bold, call-outs. Answer using the evidence below:"}]
    for m in full_history:
        if m.get("role") and m.get("content"):
            conversation.append({"role": m["role"] if m["role"] in ["user","assistant"] else "user", "content": m["content"]})
    conversation.append({"role": "user", "content": f"Available File Evidence:\n{evidence_context}\n\nUser Query: {getattr(req, 'query', '')}"})

    try:
        resp = client.chat.completions.create(model=CHATBOT_MODEL, messages=conversation, temperature=0.2)
        text = resp.choices[0].message.content
        output_guardrails(text)
        return {"reply": text}
    except Exception as e:
        try:
            resp = client.chat.completions.create(model=CHATBOT_FALLBACK_MODEL, messages=conversation, temperature=0.2)
            text = resp.choices[0].message.content
            output_guardrails(text)
            return {"reply": text}
        except Exception as e2:
            return {"reply": f"AI error: {str(e2)}"}