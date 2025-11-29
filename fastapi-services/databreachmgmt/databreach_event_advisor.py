
#/home/cybersecai/htdocs/www.cybersecai.io/webhook/venv/bin/gunicorn -w 2 -k uvicorn.workers.UvicornWorker -b 127.0.0.1:8303 databreach_event_advisor:app
#sudo systemctl restart databreach_event_advisor
#sudo lsof -i :8303

import os
import json
import re
from typing import List, Optional, Dict, Any

import openai
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

app = FastAPI()
client = openai.OpenAI(api_key=os.environ.get("OPENAI_API_KEY"))


class BreachReq(BaseModel):
    business_id: str
    user_id: Optional[int] = None
    event_title: str
    incident_text: str
    selected_regulations: List[Dict[str, Any]]
    evidence_meta: Optional[Dict[str, Any]] = None


def _format_regulations(regs: List[Dict[str, Any]]) -> str:
    if not regs:
        return "None supplied."
    lines = []
    for reg in regs:
        fields = ', '.join(reg.get('fields', []) or [])
        lines.append(f"- {reg.get('standard','(unknown)')} ({reg.get('jurisdiction','N/A')}) :: {fields}")
    return "\n".join(lines)


def _build_markdown(payload: Dict[str, Any]) -> str:
    assessment = payload.get("assessment", {})
    steps = payload.get("process_steps", [])
    determination = payload.get("determination", {})
    citations = payload.get("citations", [])
    cyber = payload.get("cyber_security_summary", {})

    lines = []
    lines.append("## Incident Assessment Summary")
    lines.append(f"**Risk Rating:** {assessment.get('risk_rating', 'N/A')} ({assessment.get('confidence','n/a')} confidence)")
    lines.append(f"**Summary:** {assessment.get('summary','')}")
    if assessment.get("key_findings"):
        lines.append("\n**Key Findings:**")
        for item in assessment["key_findings"]:
            lines.append(f"- {item}")
    if assessment.get("exposed_data"):
        lines.append("\n**Impacted Data:** " + ", ".join(assessment["exposed_data"]))
    if assessment.get("timeline"):
        lines.append(f"**Timeline:** {assessment['timeline']}")

    if steps:
        lines.append("\n## Data Breach Process")
        for step in steps:
            lines.append(f"### Step {step.get('step_number','')} · {step.get('title','')}")
            lines.append(f"_Status:_ {step.get('status','')}")
            if step.get("tagline"):
                lines.append(f"_Target:_ {step['tagline']}")
            lines.append(step.get("details",""))
            if step.get("communication_focus"):
                lines.append(f"_Focus:_ {step['communication_focus']}")
            if step.get("notification_template"):
                lines.append("\n> **Notification Draft:**\n>\n> " + step["notification_template"].replace("\n", "\n> "))
            if step.get("not_applicable_reason"):
                lines.append(f"> Not applicable because: {step['not_applicable_reason']}")

    lines.append("\n## Determination")
    notifiable = "YES" if determination.get("is_notifiable") else "NO"
    lines.append(f"- **Notifiable:** {notifiable}")
    lines.append(f"- **Summary:** {determination.get('determination_summary','')}")
    if determination.get("evidence"):
        lines.append("**Evidence:**")
        for ev in determination["evidence"]:
            lines.append(f"- {ev}")
    if determination.get("final_recommendation"):
        lines.append(f"**Recommendation:** {determination['final_recommendation']}")

    if cyber:
        lines.append("\n## Cybersecurity Synopsis")
        lines.append(f"- **Headline:** {cyber.get('headline','')}")
        if cyber.get("attack_vector"):
            lines.append(f"- **Attack vector:** {cyber['attack_vector']}")
        if cyber.get("threat_actor_assessment"):
            lines.append(f"- **Threat actor:** {cyber['threat_actor_assessment']}")
        if cyber.get("residual_risk"):
            lines.append(f"- **Residual risk:** {cyber['residual_risk']}")
        tasks = cyber.get("containment_priority") or []
        if tasks:
            lines.append("**Containment priorities:**")
            for task in tasks:
                lines.append(f"- {task}")

    if citations:
        lines.append("\n## Citations")
        for cite in citations:
            clauses = ", ".join(cite.get("clauses", []))
            lines.append(f"- **{cite.get('regulation','')}**: {clauses} – {cite.get('reason','')}")

    return "\n".join(lines).strip()


def _parse_model_json(text: str) -> Dict[str, Any]:
    block = re.search(r"```(?:json)?\s*([\s\S]+?)\s*```", text)
    json_str = block.group(1) if block else text[text.find("{"):]
    try:
        return json.loads(json_str)
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"Unable to parse model JSON: {exc}")


def generate_breach_plan(req: BreachReq) -> Dict[str, Any]:
    regulations = _format_regulations(req.selected_regulations)
    prompt = f"""
You are a senior Data Breach & Privacy Counsel backed by a cyber incident responder.
Take a realistic, evidence-based approach—do NOT label every incident as reportable.
Only escalate to a notifiable breach when legal thresholds (e.g., likely serious harm, statutory reporting conditions) are genuinely met.

Regulations tied to the business:
{regulations}

Incident to assess:
Title: {req.event_title}
Details:
{req.incident_text}

Respond ONLY with a single ```json block using this schema:

{{
  "assessment": {{
    "risk_rating": "LOW|MEDIUM|HIGH",
    "summary": "",
    "key_findings": [],
    "exposed_data": [],
    "impact_scope": [],
    "timeline": "",
    "confidence": "High|Medium|Low"
  }},
  "process_steps": [
    {{
      "step_number": 1,
      "title": "",
      "status": "required|optional|not_applicable",
      "tagline": "",
      "details": "",
      "communication_focus": "",
      "notification_template": "",
      "reference_regulation": "",
      "reference_clause": "",
      "not_applicable_reason": ""
    }}
  ],
  "determination": {{
    "is_notifiable": true,
    "determination_summary": "",
    "evidence": [],
    "final_recommendation": "",
    "authority_notifications": [
      {{"authority":"", "deadline":"", "rationale":""}}
    ],
    "subject_notifications": [
      {{"audience":"", "deadline":"", "message":""}}
    ],
    "containment_actions": [
      "..."
    ]
  }},
  "citations": [
    {{"regulation":"", "clauses":[""], "reason":""}}
  ],
  "cyber_security_summary": {{
    "headline": "",
    "attack_vector": "",
    "threat_actor_assessment": "",
    "containment_priority": [],
    "residual_risk": ""
  }}
}}

Rules:
- Process steps must follow detect/contain → assess → authority notification → subject notification → documentation → post-incident review. Include steps even if not applicable (mark status and explain).
- If a step triggers communication (notify authority, notify subjects, internal exec briefing, etc.), provide a polished, ready-to-send text in `notification_template` (email or letter style, crisp and professional).
- Authority and subject notification lists must align with determination: if not notifiable, state that and explain why.
- Reference only the regulations provided. cite the clause you rely upon.
- Cybersecurity summary should read like a concise SOC briefing (attack vector, likely actor, containment priorities, residual risk).

Be decisive yet grounded. If evidence is weak for notification, recommend monitoring instead of forcing a report.
"""

    try:
        completion = client.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": "You are a meticulous privacy, legal, and cyber incident expert."},
                {"role": "user", "content": prompt}
            ],
            temperature=0.25,
        )
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"OpenAI request failed: {exc}")

    text = completion.choices[0].message.content
    parsed = _parse_model_json(text)

    report_markdown = _build_markdown(parsed)
    parsed["report_markdown"] = report_markdown
    parsed["raw_model"] = text
    parsed["input_meta"] = {
        "business_id": req.business_id,
        "evidence_meta": req.evidence_meta,
    }
    return parsed


@app.post("/databreach/analyze")
def analyze(req: BreachReq):
    if not req.selected_regulations:
        raise HTTPException(status_code=400, detail="selected_regulations cannot be empty.")
    if not req.incident_text.strip():
        raise HTTPException(status_code=400, detail="incident_text cannot be blank.")

    result = generate_breach_plan(req)
    return result