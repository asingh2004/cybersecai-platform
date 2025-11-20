 ##(Content Extraction & GPT Classification)

import os
import sys
import json
import logging
import io
from concurrent.futures import ThreadPoolExecutor, as_completed
from docx import Document
from PyPDF2 import PdfReader
from googleapiclient.discovery import build
from google.oauth2 import service_account

try:
    import openai
except ImportError:
    openai = None

logging.basicConfig(format="%(asctime)s %(levelname)s %(message)s", level=logging.INFO)
ALLOWED_EXT = ('.docx', '.txt', '.pdf', '.csv', '.json')
MAX_OUT_CHARS = 15000
MAX_WORKERS = 8

if len(sys.argv) < 2:
    sys.exit("Usage: python3 2_gdrive_content_extract_compliance_parallel.py <CONFIG_ID> [<search_root>]")

config_id = sys.argv[1]
search_root = sys.argv[2] if len(sys.argv) > 2 else '/home/cybersecai/htdocs/www.cybersecai.io/webhook/GDRIVE'

def find_config_folder(config_id, root):
    for rootdir, dirs, files in os.walk(root):
        if os.path.basename(rootdir) == config_id:
            return os.path.abspath(rootdir)
    sys.exit(f"Could not find config folder '{config_id}' in '{root}'")

def load_json(path, desc):
    if not os.path.isfile(path):
        sys.exit(f"{desc} not found: {path}")
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)

config_dir = find_config_folder(config_id, search_root)
filelist_json = os.path.join(config_dir, f"gdrive_files_list_{config_id}.json")
secrets_json = os.path.join(config_dir, f"{config_id}_secrets.json")
compliance_matrix = load_json(os.path.join(config_dir, "compliance_matrix.json"), "Compliance matrix")
output_dir = os.path.join(config_dir, "graph")
os.makedirs(output_dir, exist_ok=True)
output_json = os.path.join(output_dir, f"gdrive_output_{config_id}.json")

gdrive_cfg = load_json(secrets_json, "GDrive Secrets file")
SERVICE_ACCOUNT_JSON = gdrive_cfg['SERVICE_ACCOUNT_JSON']
GDRIVE_FOLDER_ID = gdrive_cfg['GDRIVE_FOLDER_ID']

SCOPES = ['https://www.googleapis.com/auth/drive.readonly']
creds = service_account.Credentials.from_service_account_file(SERVICE_ACCOUNT_JSON, scopes=SCOPES)
drive_service = build('drive', 'v3', credentials=creds)

records = load_json(filelist_json, "GDrive file list")
if os.path.isfile(output_json):
    with open(output_json, "r", encoding="utf-8") as outf:
        output_list = json.load(outf)
    processed = set(r['file_id'] for r in output_list)
    logging.info(f"Resuming, {len(processed)} files already classified.")
else:
    output_list = []
    processed = set()

def fetch_file_content(record):
    fid = record['file_id']
    ext = os.path.splitext(record['file_name'])[1].lower()
    if ext not in ALLOWED_EXT or record.get('file_type','') == 'folder':
        return None
    try:
        req = drive_service.files().get_media(fileId=fid)
        fh = io.BytesIO()
        downloader = googleapiclient.http.MediaIoBaseDownload(fh, req)
        done = False
        while not done:
            status, done = downloader.next_chunk()
        fh.seek(0)
        if ext in ('.txt', '.csv', '.json'):
            try:
                return fh.read(MAX_OUT_CHARS*2).decode('utf-8')
            except Exception:
                fh.seek(0)
                return fh.read().decode('latin-1', errors="replace")
        elif ext == '.docx':
            tmpname = "/tmp/tmpgdrive_%s.docx" % (os.urandom(4).hex())
            with open(tmpname, "wb") as tmpf:
                tmpf.write(fh.read())
            doc = Document(tmpname)
            os.unlink(tmpname)
            return "\n".join(p.text for p in doc.paragraphs)
        elif ext == '.pdf':
            fh.seek(0)
            pdf_bytes = io.BytesIO(fh.read())
            reader = PdfReader(pdf_bytes)
            text = ""
            for page in reader.pages:
                text += (page.extract_text() or "") + "\n"
            return text[:MAX_OUT_CHARS]
        return ""
    except Exception as e:
        logging.warning(f"Failed to extract content for Google Drive file {fid}: {e}")
        return None

def build_prompt(compliance_matrix):
    s = ("You are an expert compliance auditor. Using the standards and fields below, "
        "analyze each document for regulated data. For each applicable compliance standard, list:\n"
        "* The standard\n* The jurisdiction\n* Which relevant data types occur in this file.\n"
        "If regulated data is present, assess risk as High, Medium or Low. "
        "Give an overall risk rating (the highest among standards found).\n")
    for entry in compliance_matrix:
        s += f"Standard: {entry['standard']} | Jurisdiction: {entry['jurisdiction']} | Fields: {', '.join(entry['fields'])}\n"
    return s

def gpt_classify_file(text, compliance_matrix):
    if not openai:
        logging.error("OpenAI module not installed or missing API key!")
        return "OpenAI not available"
    system_prompt = build_prompt(compliance_matrix)
    user = f"Classify the following file content for regulated data fields (see system context):\n\n{text[:MAX_OUT_CHARS]}"
    try:
        response = openai.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user}
            ],
            temperature=0.0,
        )
        return response.choices[0].message.content.strip()
    except Exception as e:
        logging.error(f"GPT API error: {e}")
        return f"GPT API error: {e}"

def process_one_file(record):
    fid = record['file_id']
    if fid in processed:
        return None  # Already done
    text = fetch_file_content(record)
    if text and text.strip():
        try:
            res = gpt_classify_file(text[:MAX_OUT_CHARS], compliance_matrix)
            outrec = dict(record)
            outrec['llm_response'] = res
            logging.info(f"Classified: {record['file_name']}")
            return outrec
        except Exception as e:
            logging.error(f"Failed GPT for {fid}: {e}")
            outrec = dict(record)
            outrec['llm_response'] = f"Error: {e}"
            return outrec
    else:
        logging.info(f"No usable text for file: {record['file_name']}")
        return None

todo_records = [r for r in records if r['file_id'] not in processed]
logging.info(f"{len(todo_records)} files will be classified in parallel.")

N = 10
count = 0
with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
    futures = {executor.submit(process_one_file, rec): rec for rec in todo_records}
    for future in as_completed(futures):
        outrec = future.result()
        if outrec:
            output_list.append(outrec)
            processed.add(outrec['file_id'])
            count += 1
            if count % N == 0:
                with open(output_json, "w", encoding="utf-8") as outf:
                    json.dump(output_list, outf, indent=2)
                logging.info(f"Progress: {count} new, total {len(output_list)} files classified.")

with open(output_json, "w", encoding="utf-8") as outf:
    json.dump(output_list, outf, indent=2)
logging.info(f"Completed. {len(output_list)} files classified.")