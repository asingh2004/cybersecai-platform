# source venv/bin/activate
# /home/cybersecai/htdocs/www.cybersecai.io/webhook/venv/bin/gunicorn -w 1 -k uvicorn.workers.UvicornWorker -b 127.0.0.1:8302 docs_generator_agentic_ai:app --timeout 1200
#lsof -i :8302

# Agentic AI service for generating custom compliance documents as .docx files, using:

# LLM (via OpenAI API) for document text/content.
# Microsoft Word (.docx) output only (no PDF logic).
# Data per user, taken from user’s folder.
# Step-by-Step:
# Receives a POST request with:

# user_id (string, e.g. "1")
# json_data (list of sensitive data/classified info for that user)
# Looks in /home/cybersecai/htdocs/www.cybersecai.io/databreachmgmt/<user_id>/ for all .json files.

# Each JSON file should encode a policy/compliance document (or a set, depending on file structure).
# Loads all found JSONs, collects "documents" from each.

# Each document item is a template, which the LLM can rewrite/tailor.
# For each document:

# Asks the LLM (OpenAI API) whether this template can benefit from merging in classified data.
# If “yes”:
# Prompts the LLM to generate a real document in Markdown using the classified data.
# Converts this Markdown to .docx (MS Word file).
# Returns a JSON response listing the generated results and download links.

# Has endpoints for downloading generated .docx files.

import os
import sys
import json
import shutil
import uuid
import logging
from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse
from typing import List, Dict
from pydantic import BaseModel
import openai
from docx import Document
from pathlib import Path
from docx.shared import Pt
from docx.enum.text import WD_PARAGRAPH_ALIGNMENT
import re


def detect_mandatory(doc):
    law = str(doc.get("LegalOrBestPractice", "")).lower()
    return "mandatory" in law

def group_doc_type(doc_type):
    doc_type_lower = doc_type.lower()
    if "policy" in doc_type_lower:
        return "Policy"
    elif "plan" in doc_type_lower:
        return "Plan"
    elif "procedure" in doc_type_lower or "process" in doc_type_lower:
        return "Procedure"
    elif any(x in doc_type_lower for x in ("register", "log", "record")):
        return "Register/Log"
    else:
        return "Other"

def prettify_filename(s):
    out = os.path.basename(s)
    out = out.replace("_generated", "")
    out = out.replace(".docx", "").replace(".json", "")
    out = out.replace("_", " ")
    out = out.title()
    return out


openai.api_key = os.getenv("OPENAI_API_KEY")

USER_DOC_DIR = "/home/cybersecai/htdocs/www.cybersecai.io/databreachmgmt"
LOG_PATH = "/tmp/agentic_service.log"
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler(LOG_PATH, encoding='utf-8')
    ]
)
logger = logging.getLogger("agentic_service")

app = FastAPI(title="Agentic AI Markdown/Docx Doc Generator")

def extract_organisation_name_from_filename(filename: str, user_id: str = "") -> str:
    base = os.path.basename(filename)
    # Remove extension
    if base.endswith('.json'):
        base = base[:-5]
    # Look for __userId or __number at end
    m = re.search(r'__(\d+)$', base)
    if m:
        org_part = base[:m.start()]
    else:
        org_part = base
    # Replace all underscores with space
    org = org_part.replace('_', ' ').strip()
    return org

def extract_organisation_name_from_anywhere(doc, filename, user_org_name=None):
    # Priority: doc ("OrganisationName"/"organisation_name"), POST from user, filename, fallback
    name = (
        doc.get("OrganisationName") or
        doc.get("organisation_name") or
        user_org_name or
        extract_organisation_name_from_filename(filename, "") or
        "Unknown Organisation"
    )
    return name.strip()

class DocumentRequest(BaseModel):
    user_id: str
    organisation_name: str
    json_data: List[Dict]

def log_err(msg, ex=None):
    logger.error(msg + (f". Exception: {ex}" if ex else ""))

def safe_filename(s):
    return "".join(c if c.isalnum() or c in ('-', '_') else '_' for c in s)

def ask_benefit_from_sensitive(doc_type, template_doc, sensitive_data):
    SYSTEM = (
        "You're a privacy and infosec compliance expert. Based on the DocumentType, template summary and sample sensitive data, "
        "reply as JSON (single line): {\"can_benefit\": true/false, \"reason\": \"...\"}"
    )
    prompt = (
        f"Document type: {doc_type}\n"
        f"Template: {template_doc.get('BriefDescription','')}\n"
        f"Sensitive data sample: {json.dumps(sensitive_data)[:1500]}\n"
        "Reply as JSON as described."
    )
    try:
        logger.info(f"Asking LLM if '{doc_type}' can benefit from sensitive data.")
        response = openai.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": SYSTEM},
                {"role": "user", "content": prompt},
            ],
            max_tokens=500,
            temperature=0.1
        )
        out = response.choices[0].message.content
        j = json.loads(out.strip())
        return j if isinstance(j, dict) else {"can_benefit": False, "reason": "Parse failed"}
    except Exception as e:
        log_err("Benefit check LLM failed", e)
        return {"can_benefit": False, "reason": "LLM API failed"}

def calculate_max_tokens(template_doc):
    text = template_doc.get('DocumentTemplateContent', "")
    length = len(text)
    if length > 10000:
        return 11000
    elif length > 6000:
        return 9000
    elif length > 4000:
        return 9000
    elif length > 2500:
        return 9000
    else:
        return 9000

def markdown_to_docx(md_content, docx_path):
    import re
    doc = Document()
    lines = md_content.splitlines()
    i = 0
    while i < len(lines):
        line = lines[i].rstrip("\n")

        # Markdown Heading
        if re.match(r"^#{1,6} ", line):
            level = len(line.split(" ")[0])
            doc.add_heading(line[level+1:].strip(), min(level-1, 4))

        # Markdown Table
        elif line.strip().startswith("|") and "|" in line:
            table_lines = []
            while i < len(lines) and lines[i].strip().startswith("|"):
                table_lines.append(lines[i])
                i += 1
            i -= 1
            headers = [c.strip() for c in table_lines[0].strip().strip('|').split('|')]
            table = doc.add_table(rows=1, cols=len(headers))
            table.style = 'Light Grid Accent 1'
            for idx, text in enumerate(headers):
                table.rows[0].cells[idx].text = text
            for trow in table_lines[2:]:
                vals = [c.strip() for c in trow.strip().strip('|').split('|')]
                row = table.add_row()
                for idx, cell in enumerate(vals):
                    row.cells[idx].text = cell

        # --- Improved Bullet [Detect bold bullet heading followed by sub-bullets] ---
        elif re.match(r"^\s*[-*+] ", line) or re.match(r"^• ", line):
            # Collect all contiguous bullet lines
            items = []
            while i < len(lines) and (re.match(r"^\s*[-*+] ", lines[i]) or re.match(r"^• ", lines[i])):
                bullet = re.sub(r"^(\s*[-*+]|•)\s*", '', lines[i]).strip()
                items.append(bullet)
                i += 1
            i -= 1
            # Detect bolded lead bullet as group label
            idx = 0
            while idx < len(items):
                bold_heading = re.match(r"^\*\*(.+?)\*\*:?$", items[idx])
                if bold_heading:
                    # Add as strong bullet label
                    para = doc.add_paragraph(style='List Bullet')
                    run = para.add_run(bold_heading.group(1) + (":" if items[idx].endswith(":") else ""))
                    run.bold = True
                    idx += 1
                    # Subsequent items not starting with ** are subpoints
                    subidx = idx
                    while subidx < len(items) and not re.match(r"^\*\*.+?\*\*", items[subidx]):
                        para2 = doc.add_paragraph(items[subidx], style='List Bullet 2')
                        subidx += 1
                    idx = subidx
                else:
                    doc.add_paragraph(items[idx], style='List Bullet')
                    idx += 1

        # Numbered list
        elif re.match(r"^\s*\d+\.", line):
            while i < len(lines) and re.match(r"^\s*\d+\.", lines[i]):
                txt = re.sub(r"^\s*\d+\.\s*", '', lines[i]).strip()
                doc.add_paragraph(txt, style='List Number')
                i += 1
            i -= 1

        # Horizontal rule
        elif re.match("^(-{3,}|_{3,}|\\*{3,})$", line.strip()):
            doc.add_paragraph().add_run().add_break()

        # Blockquote
        elif line.strip().startswith(">"):
            quote = line.lstrip("> ").strip()
            para = doc.add_paragraph()
            run = para.add_run(quote)
            run.italic = True
            para.alignment = WD_PARAGRAPH_ALIGNMENT.LEFT

        elif line.strip() != "":
            para = doc.add_paragraph(line)

        i += 1
    doc.save(docx_path)

def gen_doc_with_sensitive(doc_type, template_doc, sensitive_data):
    SYSTEM = (
        "You are a top-tier privacy and cyber lawyer. Generate the production document; use and embed provided data as an expert where appropriate. Stating that the source of data is their cybersecai.io platform."
        "Output as Markdown only."
    )
    prompt = (
        f"DocumentType: {doc_type}\n"
        f"OriginalTemplate:\n{template_doc.get('DocumentTemplateContent', '')[:10000]}\n"
        f"SensitiveData(JSON):\n{json.dumps(sensitive_data)[:10000]}\n"
        "Output as ready Markdown."
    )

    max_tokens = calculate_max_tokens(template_doc)
    logger.info(f"LLM generating for '{doc_type}' with max_tokens={max_tokens}")
    try:
        response = openai.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": SYSTEM},
                {"role": "user", "content": prompt},
            ],
            max_tokens=max_tokens,
            temperature=0.35
        )
        outmd = response.choices[0].message.content
        if outmd.strip().startswith("```"):
            outmd = outmd.strip().strip("```").replace("markdown", "").strip()
        logger.info(f"Generated markdown for '{doc_type}': {outmd[:120]}... (length: {len(outmd)})")
        return outmd
    except Exception as e:
        log_err("Doc generation LLM failed", e)
        return "# ERROR: Document generation failed."



def load_all_user_documents(user_id):
    folder = f"{USER_DOC_DIR}/{user_id}"
    processed_folder = os.path.join(folder, "processed")
    os.makedirs(processed_folder, exist_ok=True)

    logger.info(f"Loading user docs from: {folder}")
    if not os.path.isdir(folder):
        logger.warning("User folder not found: " + folder)
        raise HTTPException(status_code=404, detail="User's folder not found.")
    docs = []
    doc_file_map = {}

    for file in Path(folder).glob("*.json"):
        fpath = str(file)
        # Skip files in processed or generated subfolders!
        if os.path.commonpath([fpath, processed_folder]) == processed_folder:
            continue
        if "generated" in fpath:
            continue

        logger.info(f"Reading: {fpath}")
        try:
            organisation_name = extract_organisation_name_from_filename(file.name, user_id)
            with open(file, "r") as f:
                data = json.load(f)
                doc_list = []
                if isinstance(data, dict) and "documents" in data and isinstance(data["documents"], list):
                    doc_list = data["documents"]
                elif isinstance(data, list):
                    doc_list = data
                elif isinstance(data, dict):
                    doc_list = [data]
                for d in doc_list:
                    d["organisation_name"] = organisation_name  # Set it directly on the doc
                    docs.append(d)
                    key = d.get("DocumentType")
                    if key:
                        doc_file_map[key] = fpath
            # MOVE
            processed_path = os.path.join(processed_folder, file.name)
            try:
                shutil.move(fpath, processed_path)
                logger.info(f"Moved processed {fpath} → {processed_path}")
            except Exception as e:
                logger.warning(f"Failed to move {fpath} to processed/: {e}")

        except Exception as e:
            log_err(f"Malformed file {file.name}", e)
            continue

    logger.info(f"Total docs loaded: {len(docs)}")
    if not docs:
        logger.warning("No valid document templates found.")
        raise HTTPException(status_code=404, detail="No valid document templates found for this user.")
    return docs, doc_file_map


@app.post("/agentic/generate_sensitive_docs")
async def generate_sensitive_docs(request: DocumentRequest):
    logger.info(f"--- New request started for user_id={request.user_id} ---")
    user_id, classified_data, user_org_name = request.user_id, request.json_data, request.organisation_name
    try:
        all_docs, doc_file_map = load_all_user_documents(user_id)
    except HTTPException as ex:
        log_err(f"load_all_user_documents failed for user {user_id}", ex)
        raise
    except Exception as e:
        log_err("Problem loading user docs", e)
        raise HTTPException(status_code=500, detail="Error loading document templates.")

    result_docs = []
    user_folder = os.path.join(USER_DOC_DIR, user_id)
    os.makedirs(user_folder, exist_ok=True)

    for idx, doc in enumerate(all_docs):
        doc_type = doc.get("DocumentType", f"Doc_{uuid.uuid4().hex[:8]}")
        clean_type = safe_filename(doc_type)
        logger.info(f"[{idx + 1}/{len(all_docs)}] Processing '{doc_type}'")
        benefit = ask_benefit_from_sensitive(doc_type, doc, classified_data)
        logger.info(f"Benefit: {benefit}")
        if benefit.get("can_benefit") is not True:
            logger.info(f"Skipping {doc_type}.")
            continue
        markdown = gen_doc_with_sensitive(doc_type, doc, classified_data)
        # Determine input file location for this doc_type
        src_file = doc_file_map.get(doc_type)
        base_dir = os.path.dirname(src_file) if src_file else user_folder
        gen_dir = os.path.join(base_dir, "generated")
        os.makedirs(gen_dir, exist_ok=True)
        out_filename_json = f"{clean_type}_generated.json"
        out_path_json = os.path.join(gen_dir, out_filename_json)
        out_filename_docx = f"{clean_type}_generated.docx"
        out_path_docx = os.path.join(gen_dir, out_filename_docx)

        try:
            # Merge all original doc properties plus new ones
            doc_json = dict(doc)  # all metadata from earlier step (including LegalOrBestPractice, Purpose, etc)
            doc_json.update({
                "DocumentType": doc_type,
                "benefit_reason": benefit.get("reason"),
                "markdown": markdown
            })
            # Write JSON
            with open(out_path_json, "w") as f:
                json.dump(doc_json, f, indent=2)
                logger.info(f"Wrote output: {out_path_json}")

            # Write DOCX (AFTER file is closed!)
            markdown_to_docx(markdown, out_path_docx)
            logger.info(f"Wrote DOCX: {out_path_docx}")

        except Exception as e:
            log_err(f"Could not write gen output for {doc_type}", e)

        is_mandatory = detect_mandatory(doc)
        doc_group = group_doc_type(doc_type)
        file_display_name = prettify_filename(out_path_docx)
        organisation_name = extract_organisation_name_from_anywhere(doc, src_file, user_org_name)

        result_docs.append({
            **doc,  # brings through all key metadata like LegalOrBestPractice, Purpose, etc.
            "DocumentType": doc_type,
            "doc_group": doc_group,
            "is_mandatory": is_mandatory,
            "benefit_reason": benefit.get("reason"),
            "output_json": out_path_json,
            "output_docx": out_path_docx,
            "file_display_name": file_display_name,
            "markdown": markdown,
            "organisation_name": organisation_name
        })

    logger.info(f"User {user_id} completed, {len(result_docs)} docs.")
    if not result_docs:
        logger.warning("No relevant documents created.")
        raise HTTPException(status_code=200, detail="No relevant documents created.")
    return {
        "status": "ok",
        "documents": result_docs,
        "user_folder": user_folder,
    }

@app.get("/agentic/download_json/{user_id}/{filename:path}")
def download_generated_json(user_id: str, filename: str):
    base = os.path.join(USER_DOC_DIR, user_id)
    for p in Path(base).rglob('generated'):
        file_path = os.path.join(str(p), filename)
        if os.path.isfile(file_path):
            logger.info(f"Serving generated JSON: {file_path}")
            return FileResponse(file_path, media_type='application/json', filename=filename)
    logger.error(f"Generated JSON not found: {filename}")
    raise HTTPException(status_code=404, detail="File not found.")

@app.get("/agentic/download_docx/{user_id}/{filename:path}")
def download_generated_docx(user_id: str, filename: str):
    import os
    from fastapi.responses import FileResponse

    base = os.path.join(USER_DOC_DIR, user_id)
    # 1. Try direct match first (to allow "generated/foo.docx" in URL)
    direct_path = os.path.join(base, filename)
    logger.info(f"Trying direct path: {direct_path}")
    if os.path.isfile(direct_path):
        logger.info(f"Serving generated DOCX (direct): {direct_path}")
        return FileResponse(
            direct_path,
            media_type='application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            filename=os.path.basename(direct_path)
        )

    # 2. Try searching all 'generated' folders recursively if relative doesn't work
    logger.info(f"Direct path not found, searching rglob for: {filename}")
    for p in Path(base).rglob('generated'):
        candidate = os.path.join(str(p), os.path.basename(filename))
        logger.info(f"Trying candidate path: {candidate}")
        if os.path.isfile(candidate):
            logger.info(f"Serving generated DOCX (fallback): {candidate}")
            return FileResponse(
                candidate,
                media_type='application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                filename=os.path.basename(candidate)
            )

    # 3. Not found
    logger.error(f"Generated DOCX NOT FOUND for user_id={user_id}, filename={filename}")
    raise HTTPException(status_code=404, detail="File not found.")

@app.get("/agentic/healthz")
def healthcheck():
    logger.info("Healthcheck.")
    return {"status": "ok"}