#Handles OpenAI prompt for key documents in JSON based on jurisdiction & org
#/home/cybersecai/htdocs/www.cybersecai.io/webhook/venv/bin/gunicorn -w 1 -k uvicorn.workers.UvicornWorker -b 127.0.0.1:8300 databreach_docs_llm:app

# curl -X POST "http://localhost:8300/agentic/databreach_docs" \
#      -H "Content-Type: application/json" \
#      -d '{"jurisdiction":"Australia","organisation_name":"Test Org Pty Ltd"}'

import json
import os
import re
from typing import Optional

from fastapi import FastAPI, HTTPException
from openai import OpenAI
from pydantic import BaseModel

app = FastAPI()
client = OpenAI(api_key=os.environ.get("OPENAI_API_KEY"))

def guardrails(text: str) -> None:
    if len(text) > 10000:
        raise HTTPException(400, "Output exceeds max length")
    if any(re.search(fr"\b{word}\b", text, flags=re.I) for word in ["illegal instructional", "offensive slur"]):
        raise HTTPException(400, "Content rejected")

class DataBreachDocRequest(BaseModel):
    organisation_name: str
    industry: Optional[str] = ""
    country: Optional[str] = ""
    about_business: Optional[str] = ""
    user_id: Optional[str] = None
    business_id: Optional[str] = None

@app.post("/agentic/databreach_docs")
def generate_doc_catalog(req: DataBreachDocRequest):
    context = f"""
Organisation: {req.organisation_name}
Industry: {req.industry or 'Not specified'}
Operating Country/Jurisdiction: {req.country or 'Not specified'}
Business Snapshot: {req.about_business or 'No additional context provided.'}
""".strip()

    prompt = f"""
You are a senior privacy/compliance lawyer. Produce a JSON array describing all mandatory and best-practice documents needed for this organisation's data breach management framework.

Context:
{context}

For each document return:
- "DocumentType": e.g. "Data Breach Response Plan"
- "Purpose": 1-2 sentences
- "LegalOrBestPractice": e.g. "Mandatory under Privacy Act" or "Best Practice"
- "BriefDescription": practical scope/use

Limit to the essentials (policy, plans, procedures, registers, communication templates). Output *only* valid JSON.
""".strip()

    try:
        completion = client.chat.completions.create(
            model="gpt-4.1",
            temperature=0.2,
            messages=[
                {"role": "system", "content": "You are a compliance documentation architect."},
                {"role": "user", "content": prompt}
            ]
        )
        text = completion.choices[0].message.content.strip()
        guardrails(text)
        documents = json.loads(text)
        if not isinstance(documents, list) or not documents:
            raise ValueError("JSON list empty or invalid")
        for doc in documents:
            for key in ("DocumentType", "Purpose", "LegalOrBestPractice", "BriefDescription"):
                if key not in doc:
                    raise ValueError(f"Missing {key} in document entry")
        return {"documents": documents}
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"LLM error: {exc}")