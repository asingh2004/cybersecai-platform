
#/home/cybersecai/htdocs/www.cybersecai.io/webhook/venv/bin/gunicorn -w 2 -k uvicorn.workers.UvicornWorker -b 127.0.0.1:8303 databreach_event_advisor:app
#sudo systemctl restart databreach_event_advisor

import openai, os, json, re
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

app = FastAPI()

class AgentReq(BaseModel):
    standard: str
    jurisdiction: str
    requirement_notes: str
    event_type: str
    data: dict

def markdown_compliance_reply(results: dict) -> str:
    lines = []
    # 1. Risk Section
    risk = results.get('risk', '') or ''
    if risk:
        lines.append(f"### 🛡️ Privacy Risk Score: **{risk}**")
    if results.get('decision_reason'):
        lines.append(f"> _Reasoning:_ {results['decision_reason']}")

    # 2. Next Steps
    actions = results.get('action', [])
    if isinstance(actions, str):
        actions = [x.strip() for x in actions.split(',') if x.strip()]
    if actions:
        lines.append("\n### ✨ Recommended Next Steps\n")
        for act in actions:
            lines.append(f"- {act}")

    # 3. Draft Documents for actions
    # Canonical action fields
    doc_fields = [
        ('internal_report', 'Internal Report for Management'),
        ('authority_notification_letter', 'Authority Notification Letter'),
        ('data_subject_notification', 'Data Subject Notification'),
        ('public_statement', 'Public Statement/FAQ'),
    ]
    # Add any additional action fields present in result, that aren't above
    for k in results.keys():
        if k.endswith('_letter') or k.endswith('_notification') or k.endswith('_report') or k == 'public_statement':
            if k not in dict(doc_fields):
                label = k.replace('_', ' ').title()
                doc_fields.append((k, label))

    has_docs = False
    for key, label in doc_fields:
        text = results.get(key, '')
        if text:
            has_docs = True
            lines.append(f"\n#### {label}\n{text.strip()}")

    if not has_docs:
        lines.append("\n_No drafts required for this scenario._\n")

    # 4. Legal/Ethical Reasoning
    if results.get("decision_reason"):
        lines.append("\n---\n> _Legal/Ethical Reasoning:_ " + results['decision_reason'])

    # 5. Table of all fields (excluding big draft bodies, show "[See Above]")
    lines.append("\n---\n### Output Summary Table\n")
    lines.append("| Field | Value |")
    lines.append("|-------|-------|")
    for k, v in results.items():
        if k in dict(doc_fields) and v and len(str(v)) > 120:
            valstr = "[See Above]"
        elif isinstance(v, list):
            valstr = ', '.join(map(str, v))
        else:
            valstr = v
        lines.append(f"| `{k}` | {valstr} |")

    return "\n".join(lines)

def dynamic_agent(req: AgentReq):
    # ESCAPE inner triple-backticks by using quadruple-backticks in the prompt!
    prompt = f"""
You are acting as a world-class privacy/compliance officer specializing in {req.standard} for {req.jurisdiction}.

Jurisdictional notes for your decision:
{req.requirement_notes}

Event to analyze:
- {req.event_type}
- File/Data: {json.dumps(req.data, ensure_ascii=False)}

**Your answer must use professional English, CLEAR headings, numbered steps, and Markdown formatting, with bold, italics, and call-out blocks as appropriate.**

**Instructions:**
1. Analyze the event and input data. Always identify *true* risk drivers; never invent missing details.
2. **Step 1:** Score the privacy risk (**LOW**, **MEDIUM**, or **HIGH**) with a one-sentence justification.
3. **Step 2:** Explicitly recommend all next steps (as a bulleted list) as relevant for this scenario, such as any or all of:
    - `internal_report`
    - `notify_authority`
    - `communicate_subjects`
    - `public_communication`
    - …and any other mandatory next steps.
4. **Step 3:** For **each** required action in Step 2:
    - **internal_report**: If required, generate a complete internal summary report for management (otherwise leave empty).
    - **notify_authority**: If required, generate a draft notification letter that fully meets regulator requirements (otherwise leave empty).
    - **communicate_subjects**: If required, draft a clear notification for affected data subjects (otherwise leave empty).
    - **public_communication**: If required, draft a concise public statement/FAQ (otherwise leave empty).
    - For any other required action, provide briefly what should be communicated/documented.
5. **Step 4:** Provide your full reasoning for these choices.
6. **Step 5:** At the very end, output minimized JSON (no extra explanation!) in a code block, with keys:
    - `risk`
    - `action` (list)
    - `decision_reason`
    - `internal_report`
    - `authority_notification_letter`
    - `data_subject_notification`
    - `public_statement`
    - ...as many action fields as needed (set unused ones as `""`).

**EXPLICIT OUTPUT FORMAT:**
---------------------------
### 🛡️ Risk Score
- **Risk:** LOW/MEDIUM/HIGH
- **Reason:** ...

### ✨ Recommended Next Steps
- internal_report
- notify_authority
- communicate_subjects
- ...

### 📄 Draft Documents

#### Internal Report
(text, if required; else "not required" or leave blank)

#### Authority Notification Letter
(text, if required; else blank)

#### Data Subject Notification
(text, if required; else blank)

#### Public Communication / Statement
(text, if required; else blank)

---

> _Legal/Ethical Reasoning:_  
(your reasoning here)

---

At the end, output this JSON in a code block; keys for unused actions should be present with an empty string:

```json
{{
  "risk": "HIGH",
  "action": ["internal_report","notify_authority","communicate_subjects"],
  "decision_reason": "...",
  "internal_report": "...",
  "authority_notification_letter": "...",
  "data_subject_notification": "...",
  "public_statement": ""
}}

"""
    client = openai.OpenAI(api_key=os.environ.get("OPENAI_API_KEY"))
    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": "You are an expert compliance advisor."},
                {"role": "user", "content": prompt}
            ]
        )
        text = resp.choices[0].message.content
        # Prefer code block JSON at end
        block = re.search(r"```(?:json)?\s*([\s\S]+?)\s*```", text)
        json_str = ""
        if block:
            json_str = block.group(1)
        else:
            # fallback: try first { ... }
            start = text.find('{')
            if start != -1:
                json_str = text[start:]
        try:
            json_out = json.loads(json_str)
        except Exception as e:
            raise HTTPException(status_code=500, detail=f"AI response JSON parse failed: {e}\nRAW:\n{text[:300]}...")
        # Ensure action is string/list normalized
        if isinstance(json_out.get('action'), list):
            json_out['action'] = ', '.join(json_out['action'])
        return json_out
    except HTTPException:
        raise
    except Exception as e:
        import traceback
        tb = traceback.format_exc()
        print("AI Agentic ERROR:", e, '\n', tb)
        raise HTTPException(status_code=500, detail=f"Failed: {str(e)} | Trace: {tb[:600]}")

@app.post("/compliance/agent_decide")
def compliance_agent(req: AgentReq):
    d = dynamic_agent(req)
    reply = markdown_compliance_reply(d)
    return {"markdown": reply, "raw": d}