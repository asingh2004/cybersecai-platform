# Run with Gunicorn and the Uvicorn worker:

# gunicorn -w 4 -k uvicorn.workers.UvicornWorker webhook_server:app
# (Assuming your script is named webhook_server.py and the FastAPI instance is app.)

# Why FastAPI for Webhooks on Gunicorn?
# Resilient async engine—won’t block if dozens of events arrive at once.
# await support: Use HTTP, DB, or task queues efficiently.
# Strong error handling/logging with simple code (see above).
# Native OpenAPI docs, easy JSON parsing, scalable with Gunicorn + Uvicorn.
# Extra Production Tips
# Configure logs to file or SIEM service.
# Add Celery/Redis for background (async) processing rather than heavy tasks in handler.
# Protect your endpoint with more than just the user_id: use a random, per-user clientState as described in earlier steps.
# Never block or sleep in the handler—respond quickly (Microsoft Graph will retry or fail otherwise).

from fastapi import FastAPI, Request, Query
from fastapi.responses import PlainTextResponse, JSONResponse
import os
from datetime import datetime

LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"

def logmsg(msg, level="INFO"):
    timestr = datetime.utcnow().isoformat()
    out = f"[PYHOOK {timestr}] [{level}] {msg}"
    try:
        with open(LARAVEL_LOG, "a") as f:
            f.write(out + "\n")
    except Exception:
        pass

app = FastAPI()

@app.post("/webhook/{combo_id}")
async def webhook_post(combo_id: str, request: Request):
    # QUERY PARAM
    validationToken = request.query_params.get("validationToken")
    if validationToken:
        logmsg(f"Graph validation challenge (POST, query param) for combo_id={combo_id} token={repr(validationToken)}")
        return PlainTextResponse(content=validationToken, status_code=200)
    # FORM-DATA
    try:
        form = await request.form()
        if "validationToken" in form:
            token = form["validationToken"]
            logmsg(f"Graph validation challenge (POST, form-data) for combo_id={combo_id} token={repr(token)}")
            return PlainTextResponse(content=token, status_code=200)
    except Exception as ex:
        logmsg(f"No form data or not a form POST: {ex}", "DEBUG")
    # JSON
    try:
        body = await request.json()
        if isinstance(body, dict) and "validationToken" in body:
            token = body["validationToken"]
            logmsg(f"Graph validation challenge (POST, JSON body) for combo_id={combo_id} token={repr(token)}")
            return PlainTextResponse(content=token, status_code=200)
    except Exception as ex:
        logmsg(f"No JSON or parse fail: {ex}", "DEBUG")
    # Normal notification processing
    logmsg(f"Received webhook event for combo_id={combo_id}", "INFO")
    return JSONResponse({"ok": True})