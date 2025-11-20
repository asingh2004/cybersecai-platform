# healingapi/healingapi_server.py

from fastapi import FastAPI, HTTPException, status
from pydantic import BaseModel
from .anomaly import predict_anomaly, fit_anomaly_model
from .drl import take_action, train_drl_policy
from .llm_xai import explain_decision
from .enrich import enrich_event

import uvicorn

app = FastAPI(title="Agentic AI - Threat Detection & RL Response")

class EventIn(BaseModel):
    tenant_id: str
    event: dict

class TrainIn(BaseModel):
    tenant_id: str
    event_history: list


@app.post("/api/enrich_event")
def enrich_event_api(body: EventIn):
    """
    Threat intelligence enrichment endpoint for event data.
    Returns threat_score [0,1], and details of matched IOCs (if any).
    """
    try:
        result = enrich_event(body.event)
        return result
    except Exception as ex:
        raise HTTPException(status_code=500, detail=str(ex))

@app.post("/api/anomaly_detect")
def anomaly_detect(body: EventIn):
    """
    Detects anomaly status for an event/tenant.
    """
    try:
        is_anomaly, score = predict_anomaly(body.tenant_id, body.event)
        return {"tenant_id": body.tenant_id, "anomaly": is_anomaly, "score": score}
    except Exception as ex:
        raise HTTPException(status_code=500, detail=str(ex))

@app.post("/api/incident_response")
def incident_response(body: EventIn):
    """
    Full pipeline: anomaly detection, RL action selection, and LLM XAI explanation.
    """
    try:
        is_anomaly, score = predict_anomaly(body.tenant_id, body.event)
        if not is_anomaly:
            return {"anomaly": False, "action": None, "score": score, "explanation": "No threat detected."}
        action = take_action(body.tenant_id, body.event, score)
        explanation = explain_decision(body.event, score, action)
        return {
            "anomaly": True,
            "score": score,
            "action": action,
            "explanation": explanation
        }
    except Exception as ex:
        raise HTTPException(status_code=500, detail=str(ex))

@app.post("/api/train_anomaly")
def train_anomaly(body: TrainIn):
    """
    Triggers (re)training of a tenant's IsolationForest anomaly model.
    """
    try:
        fit_anomaly_model(body.tenant_id, body.event_history)
        return {"ok": True}
    except Exception as ex:
        raise HTTPException(status_code=500, detail=str(ex))

@app.post("/api/train_rl")
def train_rl(body: TrainIn):
    """
    Triggers RL model training for a tenant (requires Gym env and DRL agent logic).
    """
    try:
        train_drl_policy(body.tenant_id, body.event_history)
        return {"ok": True}
    except Exception as ex:
        raise HTTPException(status_code=500, detail=str(ex))

@app.get("/api/health")
def health():
    """
    Liveness probe for ops.
    """
    return {"status": "ok"}

if __name__ == "__main__":
    # Production: use gunicorn/uvicorn + process manager, not __main__.
    uvicorn.run("healingapi.healingapi_server:app", host="0.0.0.0", port=8305, reload=True)