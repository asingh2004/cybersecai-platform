#/home/cybersecai/htdocs/www.cybersecai.io/webhook/venv/bin/gunicorn -w 1 -k uvicorn.workers.UvicornWorker -b 127.0.0.1:8301 databreach_doc_content_llm:app

# curl -X POST "http://localhost:8301/agentic/databreach_doc_content" \
#      -H "Content-Type: application/json" \
#      -d '{"jurisdiction":"Australia","organisation_name":"Test Org Pty Ltd","documents":[{"DocumentType":"Data Breach Response Plan","Purpose":"Handle and manage data breaches quickly.","LegalOrBestPractice":"Mandatory - Privacy Act 1988","BriefDescription":"How to detect, report, and respond to data breaches."}]}'

import json
import os
import re
from typing import Any, Dict, List, Optional

from fastapi import FastAPI, HTTPException
from openai import OpenAI
from pydantic import BaseModel, Field

app = FastAPI()
client = OpenAI(api_key=os.environ.get("OPENAI_API_KEY"))

def guardrails(text: str) -> None:
    if len(text) > 20000:
        raise HTTPException(400, "Output too long")
    if any(re.search(fr"\b{word}\b", text, flags=re.I) for word in ["illegal instructional", "offensive slur"]):
        raise HTTPException(400, "Content rejected")

class BusinessContext(BaseModel):
    industry: Optional[str] = ""
    country: Optional[str] = ""
    about_business: Optional[str] = ""

class DocListReq(BaseModel):
    organisation_name: str
    user_id: str
    documents: List[Dict[str, Any]] = Field(..., description="List from catalog service")
    business_context: BusinessContext

@app.post("/agentic/databreach_doc_content")
def generate_doc_templates(req: DocListReq):
    detailed_docs: List[Dict[str, Any]] = []

    context_lines = [
        f"Organisation: {req.organisation_name}",
        f"Industry: {req.business_context.industry or 'General'}",
        f"Country/Jurisdiction: {req.business_context.country or 'Global'}",
        f"About: {req.business_context.about_business or 'Not provided'}",
    ]
    context = "\n".join(context_lines)

    for doc in req.documents:
        doc_type = doc.get("DocumentType", "Document")
        purpose = doc.get("Purpose", "")
        mandate = doc.get("LegalOrBestPractice", "")
        brief = doc.get("BriefDescription", "")

        prompt = f"""
Context
{context}

Document type: {doc_type}
Purpose: {purpose}
Requirement: {mandate}
Usage: {brief}

Draft a production-ready template in Markdown, with sections, roles, procedures, escalation paths, regulatory hooks relevant to {req.business_context.country or 'the jurisdiction'}, and succinct language suitable for immediate adoption by leadership.
""".strip()

        try:
            completion = client.chat.completions.create(
                model="gpt-4.1",
                temperature=0.25,
                messages=[
                    {"role": "system", "content": "You are a seasoned privacy & cyber law partner."},
                    {"role": "user", "content": prompt}
                ]
            )
            template = completion.choices[0].message.content.strip()
            guardrails(template)
            enriched = dict(doc)
            enriched["DocumentTemplateContent"] = template
            enriched["business_context"] = req.business_context.dict()
            detailed_docs.append(enriched)
        except Exception as exc:
            raise HTTPException(500, f"Template generation failed for {doc_type}: {exc}")

    safe_org = re.sub(r"[^A-Za-z0-9_]", "_", req.organisation_name)
    safe_country = re.sub(r"[^A-Za-z0-9_]", "_", req.business_context.country or "global")
    safe_user = re.sub(r"[^A-Za-z0-9_-]", "_", req.user_id)

    folder = f"/home/cybersecai/htdocs/www.cybersecai.io/databreachmgmt/{safe_user}"
    os.makedirs(folder, exist_ok=True)
    file_path = f"{folder}/{safe_country}_{safe_org}_{safe_user}.json"

    try:
        with open(file_path, "w", encoding="utf-8") as f:
            json.dump({"documents": detailed_docs}, f, ensure_ascii=False, indent=2)
    except Exception as exc:
        raise HTTPException(500, f"Failed to persist template set: {exc}")

    return {"documents": detailed_docs, "file": file_path}