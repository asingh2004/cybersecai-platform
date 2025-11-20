#sudo lsof -i :8224
# Place this at: /etc/systemd/system/prod_agentic_orchestrator_service.service
#sudo systemctl daemon-reload
#sudo systemctl enable prod_agentic_orchestrator_service
#sudo systemctl restart prod_agentic_orchestrator_service
#sudo systemctl status prod_agentic_orchestrator_service
# tail -n 50 /var/log/prod_agentic_orchestrator_service_error.log

from fastapi import FastAPI
from orchestrator.orchestrator import router as orchestrator_router

# If you have other routers for other modules, import and include them here as well.

app = FastAPI(
    title="CyberSecAI Agentic Orchestrator",
    description="Modular Agentic AI Orchestration Service for GRC, Compliance, Cybersecurity, and Board-Readiness.",
    version="1.0.0"
)

# Mount orchestrator, which includes auto_orchestrate and download endpoints
app.include_router(orchestrator_router)

# Optionally, add a health check endpoint.
@app.get("/health", tags=["Health Check"])
def health_check():
    return {"status": "ok"}

# You can add other routers, e.g.,
# from agents.findings import findings_router
# app.include_router(findings_router)

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="127.0.0.1", port=8224, reload=False)