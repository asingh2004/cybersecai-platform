import uuid
from fastapi import APIRouter, HTTPException
from agents.findings import gather_high_risk_findings, gather_all_findings
from orchestrator.agent_registry import AGENT_HANDLERS, AGENT_SCHEMAS
from models.requests import UserChatReq
from utils.logging import log_to_laravel
from utils.docx_export import robust_write_docx_file, download_docx
from utils.csv_export import get_csv_download_link, download_csv
import os, json, re, glob
import csv
from agents.cybersec import highrisk_path, allrisk_path
from utils.csv_export import download_csv  # Not "get_csv_download_link", since we're using pregenerated files!
import inspect
import asyncio



router = APIRouter()
session_mind = {}

def get_session_state(session_id: str) -> dict:
    return session_mind.setdefault(session_id, {})

def update_session_state(session_id: str, **kwargs):
    state = get_session_state(session_id)
    state.update(kwargs)
    session_mind[session_id] = state
    return state



def infer_agent_and_missing(data, user_query: str):
    q = user_query.lower()

    pentest_regex = re.compile(r'(pentest|penetration test|security scan|vulnerability test|external test)')

    compliance_regex = re.compile(
        r'(compliance|gdpr|privacy act|ccpa|cpra|australian privacy|'
        r'personal information|regulation|breach notification|'
        r'sensitive file|privacy violation|data breach|notifiable breach|'
        r'loss of data|accident|medical info|pii|'
        r'employee data|data leak|sensitive data|exposed pii)'
    )
    audit_regex = re.compile(
        r'(audit|board review|board report|summary for board|executive summary|board-level|material risk|governance|internal auditor|for board)'
    )
    policy_regex = re.compile(
        r'(policy|enforce|remediation|lock this file|quarantine|auto-encrypt|'
        r'access control|auto-classification|auto-remediation|'
        r'zero trust|secure by design)'
    )
    findings_facts_regex = re.compile(
        r'(how many|count|number of|list|show me|top \d+|latest \d+|summary of|breakdown).*\b(file|risk|m365|s3|smb|classification|pii|phi|high risk)\b',
        flags=re.I)
    agent = None
    if 'agent' in data and data['agent'] in AGENT_SCHEMAS:
        agent = data['agent']
    elif 'operation' in data and data['operation'] in AGENT_SCHEMAS:
        agent = data['operation']
    else:
        if audit_regex.search(q):
            agent = 'audit'
        elif compliance_regex.search(q):
            agent = 'compliance'
        elif policy_regex.search(q):
            agent = 'policy_enforce'
        elif pentest_regex.search(q):
            agent = 'pentest_auto'
        elif findings_facts_regex.search(q):
            agent = 'findings_facts'
        else:
            agent = 'chatbot'
    data['agent'] = agent
    required = AGENT_SCHEMAS[agent]['required']
    missing = [f for f in required if f not in data]
    if agent == "audit" and "json_data" in missing:
        config_ids = data.get("config_ids")
        if config_ids is not None and len(config_ids) > 0:
            missing = [f for f in missing if f != "json_data"]
    return agent, missing

@router.post("/agentic/auto_orchestrate")

def auto_orchestrate(req: UserChatReq):
#async def auto_orchestrate(req: UserChatReq):
    session_id = req.session_id or str(uuid.uuid4())
    state = get_session_state(session_id)
    context = {**(state.get('context', {})), **req.prior_context, "query": req.user_query}
    if hasattr(req, "user_id") and req.user_id is not None:
        context["user_id"] = req.user_id

    # For pentest_auto agent, push domain from args if needed
    if (context.get('agent') == 'pentest_auto' or context.get('operation') == 'pentest_auto'):
        if 'domain' not in context and 'args' in context and 'domain' in context['args']:
            context['domain'] = context['args']['domain']

    if "corporate_domains" not in context or not context["corporate_domains"]:
        context["corporate_domains"] = ["cybersecai.io"]

    agent, missing = infer_agent_and_missing(context, req.user_query)

    if agent == "findings_facts" and "args" not in context:
        context["args"] = {}

    if agent == "audit":
        if not context.get("json_data"):
            user_id = context.get("user_id")
            findings = []
            # Load from pre-generated high risk CSV file for this user
            if user_id:
                hrisk_path = highrisk_path(user_id)
                if os.path.isfile(hrisk_path):
                    with open(hrisk_path, newline='', encoding='utf-8') as f:
                        reader = csv.DictReader(f)
                        for row in reader:
                            findings.append(row)
            context["json_data"] = json.dumps(findings[:50])  # Limit size for UI/chat display
        if "json_data" in missing and context.get("json_data"):
            missing = [f for f in missing if f != "json_data"]

    if not context.get("messages"):
        context["messages"] = [{"role": "user", "content": context["query"]}]
    elif isinstance(context.get("messages"), str):
        context["messages"] = [{"role": "user", "content": context["messages"]}]
    elif isinstance(context.get("messages"), list) and len(context["messages"]) == 0:
        context["messages"] = [{"role": "user", "content": context["query"]}]

    log_to_laravel(f"AutoOrchestrator: intent={agent}, missing={missing}, context={json.dumps(context)[:400]}")

    required = AGENT_SCHEMAS[agent]['required']
    missing = [f for f in required if f not in context]

    if agent == "findings_facts" and "args" in missing:
        context['args'] = {}
        missing = [f for f in missing if f != 'args']

    if agent == "audit" and "json_data" in missing and context.get("json_data"):
        missing = [f for f in missing if f != "json_data"]

    if missing:
        question = f"To process your request as a {agent.replace('_',' ')}, I need the following: " + ", ".join(missing)
        update_session_state(session_id, context=context, agent=agent, missing=missing)
        return {
            "pending": True,
            "question": question,
            "session_id": session_id
        }
    else:
        handler = AGENT_HANDLERS[agent]
        try:
            result = handler(context)
            #if inspect.iscoroutinefunction(handler):
            #    result = await handler(context)
            #@else:
            #    result = handler(context)    
            log_to_laravel(f"AutoOrchestrator Result {agent}: {str(result)[:450]}")
            update_session_state(session_id, context=context, agent=agent, result=result)
        
            if agent in ("high_risk_csv_batch", "allrisk_csv_batch"):
                return result   # result is {"csv_filename": "..."}
        except Exception as ex:
            result = f"Error running agent: {ex}"
            log_to_laravel(str(result))
            update_session_state(session_id, context=context, agent=agent, result=result)

    user_id = context.get("user_id")
    csv_url = ""
    docx_url = ""
    docx_source = ""

    # ------------------------------
    # Use PRE-GENERATED CSV for download link
    # ------------------------------
    if user_id:
        # Decide which csv to point to (your UI and UX: chatbots often want high risk)
        hrisk_csv_file = f"HighRisk_{user_id}.csv"
        allrisk_csv_file = f"AllRisk_{user_id}.csv"
        # Choose which one to show per agent
        if agent in ("findings_facts", "audit", "compliance", "cybersec", "cybersec_show_external", "cybersec_find_duplicates", "cybersec_recommendations"):
            csv_url = f"/download_csv?file={hrisk_csv_file}"
        else:
            csv_url = f"/download_csv?file={allrisk_csv_file}"

    #agent_for_csv = agent in ("findings_facts", "audit", "compliance", "cybersec", "cybersec_show_external", "cybersec_find_duplicates", "cybersec_recommendations")
    agent_for_csv = agent in (
        "audit",
        "audit_dashboard",
        "audit_evidence",
        "audit_board_summary",
        "audit_full",
        "compliance",
        "chatbot",
        "pentest_auto",
        "cybersec",
        "cybersec_show_external",
        "cybersec_find_duplicates",
        "cybersec_recommendations",
        "summarizer_stats"
    )
    if agent_for_csv:
        download_csv_md = f"\n\n---\n[⬇️ Download High Risk Findings as CSV]({csv_url})" if csv_url else ""
    else:
        download_csv_md = ""

    #agent_for_docx = agent in ("audit", "compliance", "chatbot", "pentest_auto", "cybersec", "cybersec_show_external", "cybersec_find_duplicates", "cybersec_recommendations")
    agent_for_docx = agent in (
        "audit",
        "audit_dashboard",
        "audit_evidence",
        "audit_board_summary",
        "audit_full",
        "compliance",
        "chatbot",
        "pentest_auto",
        "cybersec",
        "cybersec_show_external",
        "cybersec_find_duplicates",
        "cybersec_recommendations",
        "summarizer_stats"
    )
    if agent_for_docx:
        if isinstance(result, dict):
            docx_source = result.get("reply") or result.get("markdown") or ""
        elif isinstance(result, str):
            docx_source = result
        if docx_source and docx_source.strip() and len(docx_source) > 30 and not docx_source.strip().isdigit():
            try:
                docx_url = robust_write_docx_file(docx_source)
            except Exception as ex:
                log_to_laravel(f"Docx generation skipped or failed for non-markdown result: {repr(ex)}")

    download_docx_md = f"\n\n---\n[⬇️ Download this report as Word (docx)]({docx_url})" if docx_url else ""
    download_csv_md = f"\n\n---\n[⬇️ Download High Risk Findings as CSV]({csv_url})" if csv_url else ""

    if isinstance(result, dict):
        if "reply" in result and isinstance(result["reply"], str):
            result["reply"] += download_docx_md + download_csv_md
        if "markdown" in result and isinstance(result["markdown"], str):
            result["markdown"] += download_docx_md + download_csv_md
        
        reply = result.get("reply") or result.get("markdown", "")
        followups = result.get("followups", [])
        return {
            "pending": False,
            "result": reply,
            "session_id": session_id,
            "followups": followups
        }
    elif isinstance(result, str):
        result += download_docx_md + download_csv_md

        return {
            "pending": False,
            "result": result,
            "session_id": session_id
        }

# Download endpoints (for app.include_router!)
router.add_api_route("/download_docx", download_docx, methods=["GET"])
router.add_api_route("/download_csv", download_csv, methods=["GET"])