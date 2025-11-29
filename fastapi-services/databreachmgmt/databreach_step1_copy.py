#Handles OpenAI prompt for key documents in JSON based on jurisdiction & org
#/home/cybersecai/htdocs/www.cybersecai.io/webhook/venv/bin/gunicorn -w 1 -k uvicorn.workers.UvicornWorker -b 127.0.0.1:8300 databreach_docs_llm:app

# curl -X POST "http://localhost:8300/agentic/databreach_docs" \
#      -H "Content-Type: application/json" \
#      -d '{"jurisdiction":"Australia","organisation_name":"Test Org Pty Ltd"}'

import os
import openai
import json
import re
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

# --- FastAPI setup ---
app = FastAPI()

# --- OpenAI setup ---
OPENAI_API_KEY = os.environ.get("OPENAI_API_KEY")
if not OPENAI_API_KEY:
    raise RuntimeError("OPENAI_API_KEY environment variable not set.")
client = openai.OpenAI(api_key=OPENAI_API_KEY)

def output_guardrails(text: str) -> None:
    """Raise error if content violates basic safety or exceeds max length."""
    if len(text) > 8000:
        raise HTTPException(400, "Output exceeds max length")
    if any(re.search(rf"\b{w}\b", text, re.I) for w in ['illegal', 'offensive']):
        raise HTTPException(400, "Inappropriate content detected in LLM Output")

class DataBreachDocRequest(BaseModel):
    jurisdiction: str
    organisation_name: str

@app.post('/agentic/databreach_docs')
def agentic_databreach_docs(req: DataBreachDocRequest):
    """
    Generate a list of key data breach documents (with summary and legal/mandatory marker) in valid JSON.
    """
    prompt = f"""
You are a multi-jurisdiction expert laywer in data compliance and privacy. For an SME named "{req.organisation_name}" operating in {req.jurisdiction}, create a concise but comprehensive JSON list of the essential documents, policies and procedures that must be in place to manage data breaches. For each, provide:

- DocumentType (e.g. Data Breach Response Plan)
- Purpose (sentence or two)
- LegalOrBestPractice (Mandatory/Recommended, cite regulations/laws if applicable)
- BriefDescription (1-line summary of scope/practical use)

Return a JSON array of these objects. Do not return any introduction or text outside the JSON.
    """.strip()
    try:
        resp = client.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": "You are a compliance documentation expert."},
                {"role": "user", "content": prompt}
            ],
            temperature=0.2,
            max_tokens=32768
        )
        text = resp.choices[0].message.content.strip()
        output_guardrails(text)
        # Parse, validate and return JSON output
        documents = json.loads(text)
        assert isinstance(documents, list)
        for doc in documents:
            assert "DocumentType" in doc
            assert "Purpose" in doc
            assert "LegalOrBestPractice" in doc
            assert "BriefDescription" in doc
        return {"documents": documents}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"OpenAI or JSON error: {str(e)}")

# (Optional for local testing)
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("databreach_docs_llm:app", host="127.0.0.1", port=8300)