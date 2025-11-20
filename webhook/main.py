#gunicorn -w 4 -k uvicorn.workers.UvicornWorker -b 0.0.0.0:8111 main:app

import openai, os, json
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel


app = FastAPI()

class AgentReq(BaseModel):
    standard: str
    jurisdiction: str
    requirement_notes: str
    event_type: str
    data: dict



def dynamic_agent(req: AgentReq):
    prompt = f"""
You are a senior compliance officer for {req.standard} in {req.jurisdiction}.
Details for this jurisdiction: {req.requirement_notes}
Event: {req.event_type}
Data: {json.dumps(req.data)}
Step 1: Score privacy risk (LOW, MEDIUM, HIGH).
Step 2: Recommend next action (internal_report, notify_authority, communicate_subjects, public_communication, etc.).
Step 3: Generate a notification/report letter satisfying requirements for this standard/jurisdiction.
Step 4: Output as JSON: risk, action, decision_reason, notification_letter.
    """
    client = openai.OpenAI(api_key=os.environ.get("OPENAI_API_KEY"))
    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": "You are an automated compliance advisor."},
                {"role": "user", "content": prompt}
            ]
        )
        text = resp.choices[0].message.content
        print("=== LLM RAW REPLY ===\n", text)
        start = text.find('{')
        json_out = json.loads(text[start:])
        # CONVERT 'action' to string for sure
        if isinstance(json_out.get('action'), list):
            json_out['action'] = ', '.join(json_out['action'])
        print("About to return:", json_out)
        return json_out
    except Exception as e:
        print("ERROR in parsing/logic:", e)
        raise HTTPException(status_code=500, detail=f"Failed: {str(e)}")



@app.post("/compliance/agent_decide")
def compliance_agent(req: AgentReq):
    d = dynamic_agent(req)
    # Only primitives
    clean = {k: (', '.join(map(str, v)) if isinstance(v, list) else v) for k, v in d.items()}
    print("Finally returning to client:", clean)
    return clean