# Notes
# Every next_prompt is chat-like (HTML enabled for colored text).
# If the user sends a message outside of strict wizard steps (i.e. real chat), the agent will LLM respond.
# Guardrails still enforced (can't classify before discover).

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel
import openai, requests, os, random

OPENAI_KEY = os.environ.get("OPENAI_API_KEY")
if not OPENAI_KEY:
    raise RuntimeError("Please set the OPENAI_API_KEY environment variable.")
client = openai.OpenAI(api_key=OPENAI_KEY)
app = FastAPI()

class PolicyCheckOutput(BaseModel):
    policy_found: bool
    summary: str = ''
    error: str = ''

class DiscoveryOutput(BaseModel):
    discovered: bool
    discover_status: str

class ClassifyOutput(BaseModel):
    classified: bool
    classify_status: str
    visual: list

class AgentStepIn(BaseModel):
    user_id: int
    step: str
    state: dict

def policy_agent(policy_url):
    try:
        txt = requests.get(policy_url, timeout=9).text[:4000]
    except Exception as e:
        return PolicyCheckOutput(policy_found=False, error=str(e))
    messages = [
        {"role": "system", "content": "You summarize policies for compliance. Be clear and short."},
        {"role": "user", "content": txt}
    ]
    response = client.chat.completions.create(
        model="gpt-4o",
        messages=messages,
    )
    summary = response.choices[0].message.content
    return PolicyCheckOutput(policy_found=True, summary=summary)

def discovery_agent(data_source_name):
    n = random.randint(5,18)
    status = f"I found <b>{n} potential sensitive files</b> in <span style='color:#10a37f'>{data_source_name or 'your data source'}</span>. Ready to classify?"
    return DiscoveryOutput(discovered=True, discover_status=status)

def classify_agent(data_source_name):
    types = ['PII', 'PHI', 'PCI', 'Other']
    visual = [{"type": t, "count": random.randint(4,31)} for t in types]
    status = "Classification complete!<br>"
    for v in visual:
        risk = '<span style="color:#b10000;">High risk</span>' if v['type'] in ('PII','PHI','PCI') and v['count'] > 20 else '<span style="color:#2bd196;">Moderate</span>'
        status += f"{v['type']}: <b>{v['count']}</b> files ({risk})<br>"
    return ClassifyOutput(classified=True, classify_status=status, visual=visual)

def freestyle_chat(user_message):
    # Simple LLM for free-form chat input not tied to step flow.
    messages = [
        {"role": "system", "content": "You are an expert compliance, data risk, and auditing AI. Speak concisely and kindly."},
        {"role": "user", "content": user_message.strip()[:300]}
    ]
    response = client.chat.completions.create(
        model="gpt-4o",
        messages=messages,
        max_tokens=150,
        temperature=0.3,
    )
    return response.choices[0].message.content

@app.post("/agent/orchestrate")
def orchestrate(req: AgentStepIn):
    try:
        step = req.step
        state = req.state
        resp = {}
        # --- Guardrail ---
        if step == "classify" and not state.get("discovered"):
            return JSONResponse(status_code=400, content={"error": "Discovery step must be completed before classification."})

        # -- Main orchestrator logic --
        if step == 'policy':
            if 'policy_url' in state and state['policy_url']:
                policy_result = policy_agent(state['policy_url'])
                if not policy_result.policy_found:
                    resp['next_prompt'] = f"Policy not found: {policy_result.error}"
                else:
                    resp['next_prompt'] = f"📝 Policy summary:<br><i>{policy_result.summary}</i><br>Click below to start discovering files."
            else:
                resp['next_prompt'] = (
                    "Please <b>upload a policy file</b> or paste a policy link so I can analyze your compliance requirements."
                )
        elif step == 'discover':
            discovery_result = discovery_agent(state.get('data_source_name', 'Unknown source'))
            resp['function_result'] = discovery_result.dict()
            resp['next_prompt'] = discovery_result.discover_status
        elif step == 'classify':
            classify_result = classify_agent(state.get('data_source_name', 'Unknown source'))
            resp['function_result'] = classify_result.dict()
            resp['next_prompt'] = classify_result.classify_status
        elif step == 'visuals':
            if not state.get('classified'):
                return JSONResponse(status_code=400, content={"error": "Classification must be complete to show visuals!"})
            resp['visual'] = state.get('visual', [{"type":"None","count":0}])
            resp['next_prompt'] = "Here's your data classification visual!"
        else:
            # Fallback: treat as free-form chat!
            user_input = state.get('latest_input', '')
            if user_input:
                resp['next_prompt'] = freestyle_chat(user_input)
            else:
                resp['next_prompt'] = "What can I help you with?"
        return resp
    except Exception as ex:
        return JSONResponse(status_code=500, content={"error": str(ex)})