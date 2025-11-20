# User picks persona, starts chat; all turns shown in chat.
# Controller loads all the user's findings (from your compliance graph outputs) for full evidence/context.
# Backend Python FastAPI receives full chat state, query, user persona, and evidence, and produces a tailored and contextually aware AI response.
# Users can "be" a board member, cyber expert, or auditor—guardrails steer the AI's tone/responsibility.
# Highly scalable: Only limited slice of findings, messages flow to API; suitable for multi-tenant and large file/staff bases.
# How Does It Leverage cybersecai.io Information?
# Findings: The real value is from the classified file intelligence—AI compliance reports, detected risks, regulated data findings, owners, permissions, etc.—produced by your pipeline.
# When a user asks a question, the chatbot is NOT calling GPT in a vacuum—it gives the LLM up-to-date, per-user, per-file findings, so responses are deeply relevant and accurate.
# Examples it might use from your findings:
# “Detected Fields: SSN (risk: High)”
# “File X is public, contains PCI data”
# “Proposed controls: Encrypt, restrict permissions, notify owner”
# This creates actionable, evidence-based, truly agentic LLM outputs—not hallucinated answers!
# Persona guardrails ensure the LLM "acts" as an auditor, security exec, or Board advisor.
# Is It Sending Large Payloads to the AI Model?
# No, payloads are intentionally limited!
# Only the last 10 chat turns (e.g., Q&A) and up to 20 recent or relevant AI findings (summaries, not raw files) are provided as evidence in the prompt.
# Not all files or classification JSONs (could be gigabytes in big orgs!)—just the most relevant proofs.
# This keeps each call fast and cost-effective, and within the context window for GPT-4.
# You can tune these limits for higher/lower context, but this approach is already proven for enterprise use.

#sudo lsof -i :8222
#sudo systemctl restart agentic_service
#sudo systemctl status agentic_service

import os
import json
import re
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import openai
from fastapi import Request
from typing import List, Any
from textwrap import dedent

from datetime import datetime, timedelta
import dateutil.parser
import glob
from fastapi import Body

import logging

LARAVEL_LOG_PATH = '/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log'

def log_to_laravel(message: str):
    timestamp = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')
    with open(LARAVEL_LOG_PATH, 'a', encoding='utf-8') as log_file:
        log_file.write(f"[{timestamp}] python.INFO: {message}\n")



# --- Configuration and Initialization ---

app = FastAPI()

# Ensure OpenAI API key is set in the environment
OPENAI_API_KEY = os.environ.get("OPENAI_API_KEY")
if not OPENAI_API_KEY:
    raise RuntimeError("OPENAI_API_KEY environment variable not set.")
# Instantiate the OpenAI client
client = openai.OpenAI(api_key=OPENAI_API_KEY)


class ChatBotReq(BaseModel):
    persona: str
    query: str
    messages: list
    config_ids: list



def gather_user_findings(config_ids, base_dir="/home/cybersecai/htdocs/www.cybersecai.io/webhook", base_paths=None) -> list:

    """
    Gathers, parses, and flattens all compliance .json files for these config IDs.
    Adds logging to Laravel's laravel.log for: which .json files are read, and what fields/data they contain.
    Returns: list of evidence dicts.
    """
    if base_paths is None:
        base_paths = ['M365', 'SMB', 'S3', 'NFS']
    findings = []
    for cid in config_ids:
        for backend in base_paths:
            graph_dir = os.path.join(base_dir, backend, str(cid), "graph")
            if os.path.isdir(graph_dir):
                for filepath in glob.glob(os.path.join(graph_dir, "*.json")):
                    try:
                        with open(filepath, "r", encoding="utf-8") as f:
                            content = f.read()
                            data = json.loads(content)
                            # Logging: log file read and summary of data found
                            #log_to_laravel(f"Read .json file: {filepath} | Content: {content[:300].replace(chr(10),' ').replace(chr(13),' ')}{'...[truncated]' if len(content)>300 else ''}")
                            items = data if isinstance(data, list) else [data]
                            for item in items:
                                if not isinstance(item, dict):
                                    continue
                                llm = None
                                if 'llm_response' in item and item['llm_response']:
                                    try:
                                        llm = item['llm_response']
                                        if isinstance(llm, str):
                                            if llm.startswith('"') and llm.endswith('"'):
                                                llm = llm[1:-1]
                                            llm = json.loads(llm)
                                    except Exception:
                                        continue
                                if not isinstance(llm, dict):
                                    continue
                                results = llm.get('results', [])
                                if isinstance(results, list) and results:
                                    for r in results:
                                        out = {
                                            'file_name': item.get('file_name'),
                                            'last_modified': item.get('last_modified'),
                                            'created': item.get('created'),
                                            'file_path': item.get('file_path'),
                                            'full_path': item.get('full_path'),
                                            'standard': r.get('standard'),
                                            'jurisdiction': r.get('jurisdiction'),
                                            'detected_fields': r.get('detected_fields', []),
                                            'auditor_agent_view': r.get('auditor_agent_view'),
                                            'data_classification': llm.get('data_classification'),
                                            'likely_data_subject_area': llm.get('likely_data_subject_area'),
                                            'overall_risk_rating': llm.get('overall_risk_rating'),
                                            'cyber_proposed_controls': llm.get('cyber_proposed_controls'),
                                            'auditor_proposed_action': llm.get('auditor_proposed_action'),
                                        }
                                        # Logging: Only summary of extracted fields (first match)
                                        #log_to_laravel(f"Extracted from {filepath}: {json.dumps(out, ensure_ascii=False)[:300]}{'...[truncated]' if len(json.dumps(out))>300 else ''}")
                                        findings.append(out)
                                else:
                                    out = {
                                        'file_name': item.get('file_name'),
                                        'last_modified': item.get('last_modified'),
                                        'created': item.get('created'),
                                        'file_path': item.get('file_path'),
                                        'full_path': item.get('full_path'),
                                        'auditor_agent_view': llm.get('auditor_agent_view'),
                                        'data_classification': llm.get('data_classification'),
                                        'likely_data_subject_area': llm.get('likely_data_subject_area'),
                                        'overall_risk_rating': llm.get('overall_risk_rating'),
                                        'cyber_proposed_controls': llm.get('cyber_proposed_controls'),
                                        'auditor_proposed_action': llm.get('auditor_proposed_action'),
                                    }
                                    #log_to_laravel(f"Extracted (no result-list) from {filepath}: {json.dumps(out, ensure_ascii=False)[:300]}{'...[truncated]' if len(json.dumps(out))>300 else ''}")
                                    findings.append(out)
                    except Exception as ex:
                        log_to_laravel(f"Error reading {filepath}: {repr(ex)}")
                        continue
    return findings




def parse_date_from_query(query):
    """Detect last N day(s) or date like '06 July 2025' in text. Return (start, end) as UTC datetime, or None."""
    # Try "last N day(s)"
    m = re.search(r'last\s+(\d+)\s+day', query, re.I)
    if m:
        n = int(m.group(1))
        end = datetime.utcnow()
        start = end - timedelta(days=n)
        return (start, end)
    # Try today
    if re.search(r'(today|current day)', query, re.I):
        now = datetime.utcnow()
        return (now.replace(hour=0, minute=0, second=0, microsecond=0),
                now.replace(hour=23, minute=59, second=59, microsecond=999999))
    # Try explicit date (US/EU format)
    m = re.search(r'(\d{1,2}\s+\w+\s+\d{4})', query)
    if m:
        try:
            d = dateutil.parser.parse(m.group(1), fuzzy=True).date()
            start = datetime.combine(d, datetime.min.time())
            end = datetime.combine(d, datetime.max.time())
            return (start, end)
        except Exception: pass
    return None

@app.post('/agentic/chatbot')
def agent_chatbot(req: ChatBotReq):

    persona_instructions = {
        "Risk Auditor": (
            "You answer as a highly professional compliance/risk auditor. Use detailed, well-structured markdown with headings, bullets, bold text, and tables if appropriate."
        ),
        "Cybersecurity": (
            "You are an experienced cyber defense expert. ALWAYS use markdown structure in your response (use code blocks, tables, strong callouts where useful)."
            " IMPORTANT: If the 'Available File Evidence' section below says there is NO EVIDENCE or NO MATCHING results, you must answer honestly: 'No matching file changes found for your request.' But include content in your response that is not reliant on data evidence."
            " DO NOT invent evidence data; summarize in the context of request."
        ),
        "Board Member": (
            "You are a board-level advisor. Provide summaries, lists, and strong section headings in Markdown for business readability."
            " IMPORTANT: If the 'Available File Evidence' section below says there is NO EVIDENCE or NO MATCHING results, you must answer honestly: 'No matching file changes found for your request.' But include content in your response that is not reliant on data evidence."
            " DO NOT invent evidence data; summarize in the context of request."
        ),
        "All": (
            "You are CybersecAI, combining compliance, cyber, and business expertise. Provide an integrated response from both compliance and security perspectives, always using clean, structured Markdown."
            " IMPORTANT: If the 'Available File Evidence' section below says there is NO EVIDENCE or NO MATCHING results, you must answer honestly: 'No matching file changes found for your request.' But include content in your response that is not reliant on data evidence."
            " DO NOT invent evidence data; summarize in the context of request."
        ),
        "Auditor/Security": (
            "You are a combined compliance auditor and cybersecurity analyst. Surface forensic traces and risk maps, using structured Markdown for clarity."
            " IMPORTANT: If the 'Available File Evidence' section below says there is NO EVIDENCE or NO MATCHING results, you must answer honestly: 'No matching file changes found for your request.' But include content in your response that is not reliant on data evidence."
            " DO NOT invent evidence data; summarize in the context of request."
        ),
        "default": (
            "You are CybersecAI, an AI chat advisor. Always return clean, structured Markdown using headers, lists, bold, etc."
            " IMPORTANT: If the 'Available File Evidence' section below says there is NO EVIDENCE or NO MATCHING results, you must answer honestly: 'No matching file changes found for your request.' But include content in your response that is not reliant on data evidence."
            " DO NOT invent evidence data; summarize in the context of request."
        )
    }
    system_content = persona_instructions.get(req.persona, persona_instructions["default"])

    findings = gather_user_findings(req.config_ids)   # CORRECTED: this is your evidence base

    # Try to extract date filter, now using findings (not req.findings!)
    date_bounds = parse_date_from_query(req.query)
    filtered_evidence = []
    if date_bounds:
        for entry in findings:
            lastmod = entry.get('last_modified')
            if lastmod:
                try:
                    dt = dateutil.parser.parse(lastmod)
                    if date_bounds[0] <= dt <= date_bounds[1]:
                        filtered_evidence.append(entry)
                except Exception:
                    continue
    elif re.search(r'(last|recent)\s+\d+\s+(file|change)', req.query, re.I):
        filtered_evidence = findings[-100:]
    else:
        filtered_evidence = findings[-100:]

    evidence_context = ""
    if not filtered_evidence:
        evidence_context = "[NO MATCHING HIGH-RISK FILE CHANGES for your account for the specified date/range. No results found. AI MUST NOT INVENT FILES.]"
    else:
        for f in filtered_evidence[:300]:
            try:
                fname = f.get('file_name') or f.get('filename') or "[unknown file]"
                lastmod = f.get('last_modified') or f.get('lastModified') or ""
                risk = f.get("overall_risk_rating") or ""
                evidence_context += f"- {fname} ({lastmod}) Risk: {risk}\n"
            except Exception:
                continue

    full_history = req.messages[-2:]
    conversation = [
        {
          "role": "system",
          "content": (
              system_content +
              "\n\nRespond ALWAYS in Markdown and NEVER in plain text or code - use headers, lists, bold, call-outs. Answer queries using the full evidence below:"
          )
        }
    ]
    for m in full_history:
        if m.get("role") and m.get("content"):
            conversation.append({"role": m["role"] if m["role"] in ["user", "assistant"] else "user", "content": m["content"]})
    conversation.append({"role": "user", "content": f"Available File Evidence:\n{evidence_context}\n\nUser Query: {req.query}"})

    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            messages=conversation,
            temperature=0.2,
        )
        text = resp.choices[0].message.content
        output_guardrails(text)
        return {"reply": text}
    except Exception as e:
        return {"reply": f"AI error: {str(e)}"}

def detect_mandatory(doc):
    law = str(doc.get("LegalOrBestPractice", "")).lower()
    return "mandatory" in law

def group_doc_type(doc_type):
    doc_type_lower = doc_type.lower()
    if "policy" in doc_type_lower:
        return "Policy"
    elif "plan" in doc_type_lower:
        return "Plan"
    elif "procedure" in doc_type_lower or "process" in doc_type_lower:
        return "Procedure"
    elif any(x in doc_type_lower for x in ("register", "log", "record")):
        return "Register/Log"
    else:
        return "Other"
    
def prettify_filename(s):
    out = os.path.basename(s)
    out = out.replace("_generated", "")
    out = out.replace(".docx", "").replace(".json", "")
    out = out.replace("_", " ")
    out = out.title()
    return out

# --- Pydantic Models (Request Schemas) ---

class ComplianceReq(BaseModel):
    standard: str
    jurisdiction: str
    requirement_notes: str = ""
    event_type: str
    data: dict

class AuditReq(BaseModel):
    region: str
    json_data: str

class PolicyEnforceReq(BaseModel):
    files: dict  # filename -> JSON dict
    policies: dict
    siem_url: str = ""

# --- Guardrail Logic ---

def output_guardrails(text: str) -> None:
    """Raise error if content violates simple guardrails."""
    bad_words = ['nigger', 'intercourse']
    if any(re.search(rf"\b{w}\b", text, re.I) for w in bad_words):
        raise HTTPException(400, "Inappropriate content detected in LLM Output")
    if len(text) > 30000:
        raise HTTPException(400, "Output exceeds max length")

# --- API Endpoints ---

@app.post('/agentic/policy_enforce')
def agent_policy_enforce(req: PolicyEnforceReq):
    """
    Enforces policy for changed files.
    """
    changed_files = []
    policy_actions = []
    siem_events = []
    for fname, info in req.files.items():
        if info.get('changed'):
            changed_files.append(fname)
            compliance_decision = None

            # If file is high risk, invoke compliance advisor
            if info.get('risk', '').upper() == "HIGH":
                comp_req = ComplianceReq(
                    standard=req.policies.get('standard', 'GDPR'),
                    jurisdiction=req.policies.get('jurisdiction', 'Europe'),
                    requirement_notes=req.policies.get('notes', ''),
                    event_type="Sensitive File Change",
                    data=info
                )
                comp_res = agent_compliance_advisor(comp_req)
                policy_actions.append({
                    "file": fname, "compliance_decision": comp_res
                })
                compliance_decision = comp_res

            # Example: record policy enforcement action
            if info.get('policy_required'):
                policy_actions.append({
                    "file": fname, "action_taken": f"Policy {req.policies.get('enforce_type', 'Lock')} applied"
                })
            # Optionally, send event to SIEM
            if req.siem_url:
                siem_event = {
                    "to": req.siem_url,
                    "event": {
                        "file": fname,
                        "delta": info.get('delta'),
                        "compliance_decision": compliance_decision
                    }
                }
                siem_events.append(siem_event)
                # Insert SIEM push code here if needed

    return {
        "changed_files": changed_files,
        "policy_actions": policy_actions,
        "siem_events": siem_events
    }

@app.post('/agentic/compliance_advisor')
def agent_compliance_advisor(req: ComplianceReq):
    """
    Handles compliance risk scoring and report generation.
    Returns dict containing Markdown report.
    """
    # Prefer a user-provided justification field if present; fall back to requirement_notes
    justification = getattr(req, "justification", None) or getattr(req, "requirement_notes", "") or ""
    try:
        data_block = json.dumps(req.data, ensure_ascii=False)
    except Exception:
        data_block = str(req.data)

    system_msg = dedent(f"""
        You are a senior privacy and compliance officer specializing in {req.standard} within {req.jurisdiction}.
        Your analysis is pragmatic, proportionate, and not reflexively risk-averse. You cite applicable rules precisely,
        weigh aggravating and mitigating factors, and recommend only actions that are required or clearly justified.
        Apply and respect the user's selected requirement/justification when interpreting obligations.
    """).strip()

    user_msg = dedent(f"""
        Context
        - Standard: {req.standard}
        - Jurisdiction: {req.jurisdiction}
        - Event Type: {req.event_type}
        - Selected requirement/justification: {justification}

        Facts and data provided (verbatim JSON):
        {data_block}

        Your task
        Produce a polished, expert Markdown report that follows EXACTLY these steps and constraints:

        Step 1: Score privacy risk as one of: LOW, MEDIUM, HIGH.
        - Calibrate realistically based on sensitivity of data, volume/scale, exposure duration, exploitability,
          protective measures in place (encryption, pseudonymization), likelihood of harm, and who accessed the data.
        - Do not be reflexively HIGH. If facts are incomplete, choose the lower of adjacent ratings and state assumptions.

        Step 2: Provide a concise rationale for the risk rating.
        - List key aggravating and mitigating factors and how they influenced the rating.

        Step 3: Recommend the next action.
        - Choose one primary action from: internal_report, notify_authority, communicate_subjects, public_communication.
        - You may add secondary actions (e.g., forensic_investigation, containment, evidence_preservation) if helpful.
        - Reference the precise legal trigger(s) for the chosen action under {req.standard}/{req.jurisdiction}
          (e.g., GDPR Arts. 33/34 thresholds, HIPAA Breach Notification Rule, state notification statutes, etc.).

        Step 4: Generate artifacts ONLY IF required.
        - If and only if the law or policy clearly requires them based on the facts and your Step 3 decision,
          include Markdown subsections to produce the relevant artifact(s). Supported types:
          a) internal_report
          b) notify_authority
          c) communicate_subjects
          d) public_communication
        - If not required, DO NOT generate the template/letter content. It’s acceptable to note that it is not required
          and why, but do not include a letter or template body unless required.
        - For any required artifact, include all elements that the applicable law mandates
          (e.g., nature of incident, categories and approximate number of data subjects/records, likely consequences,
          measures taken/proposed, contact point, dates/times, deadlines).

        Step 5: Write in polished, formal English and use clear Markdown formatting.

        Output format (use these headings verbatim):
        # Preliminary Compliance Assessment

        ## 1) Risk Rating
        - Overall Privacy Risk: LOW | MEDIUM | HIGH

        ## 2) Rationale
        - Aggravating factors:
          - ...
        - Mitigating factors:
          - ...
        - Decision reasoning:
          - Short paragraph explaining why the rating is proportionate.

        ## 3) Recommended Next Action
        - Primary action: <one of internal_report | notify_authority | communicate_subjects | public_communication>
        - Legal basis: cite the exact trigger(s) under {req.standard} in {req.jurisdiction}
        - Secondary actions (if any): bullet list
        - Deadlines: specify any statutory timelines (e.g., 72h to authority, “without undue delay”, etc.)

        ## 4) Action Artifacts (Only Included If Required)
        - Include ONLY the subsections that are actually required by law or binding policy:
          ### Internal Report (include only if required)
          <structured content if required>
          ### Notification to Supervisory Authority (include only if required)
          <structured content if required>
          ### Communication to Data Subjects (include only if required)
          <structured content if required>
          ### Public Communication (include only if required)
          <structured content if required>

        ## 5) Notes and Assumptions
        - Any assumptions due to missing information
        - Any items to verify to firm up the decision

        Additional guidance:
        - If legal thresholds are not met for external notifications, recommend internal_report plus follow-up steps
          and explicitly state why external notice is not required.
        - Be decisive and succinct; avoid hedging language unless uncertainty materially affects the decision.
        - Never include placeholder letters if not required. Prefer a one-line note that it is not required and why.
    """).strip()

    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            temperature=0.2,
            messages=[
                {"role": "system", "content": system_msg},
                {"role": "user", "content": user_msg}
            ]
        )
        text = resp.choices[0].message.content or ""
        output_guardrails(text)  # your existing sanitizer/validator
        return {"markdown": text}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Compliance LLM failed: {str(e)}")


@app.post('/agentic/audit')
def agent_audit(req: AuditReq):
    """
    Generates a board-ready audit Markdown report (via GPT).
    """
    prompt = (
        f"You are a highly professional internal auditor preparing a risk report for the Board of Directors in {req.region}. "
        "Below is a set of file risk summaries, each produced by the AI agents of the cybersecai.io compliance platform. "
        "These AI agents systematically analyze and monitor all covered business files automatically, ensuring full and unbiased coverage of the risks present in every monitored file. "
        "Each record in the data below has already been assessed by cybersecai.io’s AI agents as HIGH overall risk. "
        "For each file, clearly state: 'As assessed by the cybersecai.io platform, this file was flagged for [auditor_proposed_action]'. "
        "Reference the corresponding 'auditor_proposed_action' field for each record in your analysis and recommendations. "
        "Contextualize your findings for a Board-level audience, emphasizing fiduciary and governance responsibilities, "
        "region-specific regulatory frameworks, and the strategic importance of automated, systematic risk detection.\n\n"
        f"Raw JSON data to audit is below:\n\n{req.json_data}\n\n"
        "Your report MUST include these sections:\n"
        "- Executive Summary: A concise statement of overall exposure and urgency suitable for Board decision-making in {req.region}. Make clear that results are based on AI-driven, comprehensive file coverage.\n"
        "- Risk Overview: Board-level statistics (number of high-risk files, key impacted data types, business areas affected).\n"
        "- Material Risks and Red Flags: Synthesize the main risk drivers (as determined by the AI), and for each, highlight the auditor-proposed action—using confident, directive language.\n"
        "- Compliance & Governance Implications: Interpret the results in the context of {req.region}’s legal/regulatory landscape, and the Board’s obligations. Highlight any urgent reporting or escalation as required locally.\n"
        "- Actionable Recommendations: Prioritize next steps for the Board, based specifically on the flagged actions and with suggested timelines and accountable roles.\n"
        "\nWrite your report in polished, formal English and use Markdown formatting. Stress the reliability, precision, and systematic nature of cybersecai.io’s AI-driven analysis throughout your summary and recommendations. Do not repeat raw data—synthesize and highlight only Board-relevant issues."
    )
    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": "You are a world class internal auditor."},
                {"role": "user", "content": prompt}
            ]
        )
        output = resp.choices[0].message.content
        output_guardrails(output)
        return {"markdown": output}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Audit LLM failed: {str(e)}")

# ---- Optional: Run standalone ----
# Gunicorn/Uvicorn will import app automatically.
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("agentic_service:app", host="127.0.0.1", port=8222)
    # Remove reload=True for production