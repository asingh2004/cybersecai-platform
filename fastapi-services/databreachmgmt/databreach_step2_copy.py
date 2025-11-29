#/home/cybersecai/htdocs/www.cybersecai.io/webhook/venv/bin/gunicorn -w 1 -k uvicorn.workers.UvicornWorker -b 127.0.0.1:8301 databreach_doc_content_llm:app

# curl -X POST "http://localhost:8301/agentic/databreach_doc_content" \
#      -H "Content-Type: application/json" \
#      -d '{"jurisdiction":"Australia","organisation_name":"Test Org Pty Ltd","documents":[{"DocumentType":"Data Breach Response Plan","Purpose":"Handle and manage data breaches quickly.","LegalOrBestPractice":"Mandatory - Privacy Act 1988","BriefDescription":"How to detect, report, and respond to data breaches."}]}'

import os
import openai
import re
import json
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

app = FastAPI()

# --- OpenAI setup ---
OPENAI_API_KEY = os.environ.get("OPENAI_API_KEY")
if not OPENAI_API_KEY:
    raise RuntimeError("OPENAI_API_KEY environment variable not set.")
client = openai.OpenAI(api_key=OPENAI_API_KEY)

def output_guardrails(text: str) -> None:
    if len(text) > 12000:
        raise HTTPException(400, "Output exceeds max length")
    if any(re.search(rf"\b{w}\b", text, re.I) for w in ['illegal', 'offensive']):
        raise HTTPException(400, "Inappropriate content detected in LLM Output")

# --- BaseModel Definitions ---
class DocListReq(BaseModel):
    jurisdiction: str
    organisation_name: str
    documents: list = Field(..., description="List of doc dicts from key docs service")
    user_id: str = Field(..., description="User ID for document storage")

@app.post("/agentic/databreach_doc_content")
def agentic_databreach_doc_content(req: DocListReq):
    """
    For each document in input JSON, generate a high-quality, jurisdiction-specific, expert template/policy/process content as a new field ('DocumentTemplateContent').
    Save the result as a .json file in /home/cybersecai/htdocs/www.cybersecai.io/databreachmgmt/$user_id/
    """
    detailed_docs = []
    for doc in req.documents:
        doc_type = doc.get("DocumentType", "Document")
        purpose = doc.get("Purpose", "")
        mandate = doc.get("LegalOrBestPractice", "")
        brief = doc.get("BriefDescription", "")
        prompt = f"""
You are a top-tier cybersecurity and privacy lawyer. Draft a production-ready, concise but comprehensive template for a '{doc_type}' for "{req.organisation_name}" in {req.jurisdiction}. 
Purpose: {purpose}
Requirement: {mandate}
Use: {brief}
Your output should be Markdown and suitable for initial internal adoption with clear headings, responsibilities, action steps, and any regulatory cross-references for {req.jurisdiction}.
Precise writing like a senior lawyer of highly reputable law firm that can stand scrutiny of law.
        """.strip()
        try:
            resp = client.chat.completions.create(
                model="gpt-4.1",
                messages=[
                    {"role": "system", "content": "You are Data and Privacy Highly Experienced Lawyer."},
                    {"role": "user", "content": prompt}
                ],
                temperature=0.2,
                max_tokens=32768
            )
            template = resp.choices[0].message.content.strip()
            output_guardrails(template)
            doc['DocumentTemplateContent'] = template
            detailed_docs.append(doc)
        except Exception as e:
            raise HTTPException(500, f"Failed to generate template for {doc_type}: {str(e)}")

    # ---- Save to JSON file ----
    sanitized_org_name = re.sub(r"[^A-Za-z0-9_]", "_", req.organisation_name)
    sanitized_jurisdiction = re.sub(r"[^A-Za-z0-9_]", "_", req.jurisdiction)
    sanitized_user_id = re.sub(r"[^A-Za-z0-9_-]", "_", req.user_id)
    folder_path = f"/home/cybersecai/htdocs/www.cybersecai.io/databreachmgmt/{sanitized_user_id}"
    os.makedirs(folder_path, exist_ok=True)
    file_path = f"{folder_path}/{sanitized_jurisdiction}_{sanitized_org_name}_{sanitized_user_id}.json"
    try:
        with open(file_path, "w", encoding="utf-8") as f:
            json.dump({"documents": detailed_docs}, f, ensure_ascii=False, indent=2)
    except Exception as e:
        raise HTTPException(500, f"Failed to write result file: {str(e)}")
    return {"documents": detailed_docs, "file": file_path}

# (Optional for local testing)
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("databreach_doc_content_llm:app", host="127.0.0.1", port=8301)