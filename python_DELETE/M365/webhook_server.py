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

from fastapi import FastAPI, Request, status, HTTPException, Header
from fastapi.responses import JSONResponse, PlainTextResponse
import logging

app = FastAPI()

# Set up strong logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("webhook")

@app.get("/webhook/{user_id}")
async def get_webhook(user_id: int):
    return PlainTextResponse(f"Webhook endpoint for user {user_id}", status_code=200)

@app.post("/webhook/{user_id}")
async def post_webhook(
    user_id: int,
    request: Request,
    clientstate: str = Header(None), # If clientState comes as a header (Graph usually sends in body)
):
    try:
        body = await request.json()
    except Exception:
        body = {}

    # Security: Validate clientState if you store it per user (recommended for prod)
    expected_client_state = str(user_id)   # or look up from DB if more complex
    received_state = str(body.get('clientState', ''))
    if received_state != expected_client_state:
        logger.warning("Invalid clientState for user_id=%s (expected %s, got %s)", user_id, expected_client_state, received_state)
        return JSONResponse({"error": "Invalid clientState"}, status_code=status.HTTP_403_FORBIDDEN)

    # Handle Microsoft webhook validation
    if "validationToken" in body:
        return PlainTextResponse(content=body["validationToken"], status_code=200)

    logger.info(f"Received webhook event: user_id={user_id}, body={body}")
    # TODO: Queue for async processing (don't block response!)
    #       For production, use Celery, Redis queue, etc.

    # Always return quickly to keep Microsoft Graph happy
    return JSONResponse({"status": "ok"}, status_code=200)

@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    logger.error("Unhandled exception: %s", exc, exc_info=True)
    return JSONResponse({"error": "Server error"}, status_code=500)

# For local dev only (not for Gunicorn):
if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=5000)