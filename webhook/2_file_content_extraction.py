# What This Script Does Step-by-Step
# Scans your onedrive_files_report.csv index for the 2 newest, non-folder files with an allowed extension.
# Downloads each selected file using download_url.
# If the file is:
# .txt, .csv, .json: Decodes as UTF-8 (with fallback to latin-1).
# .docx: Extracts all paragraph text via python-docx.
# .pdf: Extracts all page text via PyPDF2.
# Truncates output to 15,000 characters to avoid giant outputs (changeable).
# Writes each file’s text output to {original_file_name}_{id}.txt e.g. ProjectPlan.docx_d49bbe21x.txt
# Prints progress/status to screen.

import csv
import os
import io
import tempfile
from datetime import datetime
import requests
import re

from docx import Document          # pip install python-docx
from PyPDF2 import PdfReader      # pip install PyPDF2

# ====== CONFIG ======
CSV_FILE = "onedrive_files_report.csv"
ALLOWED_EXT = ('.docx', '.txt', '.pdf', '.csv', '.json')
# 15,000 characters is roughly equivalent to 8.86 pages based on a typical character count of 1,800 per page. 
MAX_OUT_CHARS = 15000

# ---- SUPPLY YOUR SERVICE PRINCIPAL CREDENTIALS HERE (use ENV in prod!) ----
TENANT_ID = "56758e8e-cf76-49f4-b6e0-8d5ae252c727"
CLIENT_ID = "4e02e753-0345-4e7e-a7ba-614ca6d59524"
CLIENT_SECRET = "NNT8Q~yQCD1DiRh5OQT5c6zVm9hYKu7gbjaKXdrQ"

def sanitize_filename(name):
    """
    Replace problematic characters in file names for safe file writing.
    """
    return re.sub(r'[^A-Za-z0-9._-]', '_', name)

def get_access_token():
    """
    Acquire a fresh Microsoft Graph API app access token using client credentials.
    """
    url = f"https://login.microsoftonline.com/{TENANT_ID}/oauth2/v2.0/token"
    data = {
        'grant_type': 'client_credentials',
        'client_id': CLIENT_ID,
        'client_secret': CLIENT_SECRET,
        'scope': 'https://graph.microsoft.com/.default'
    }
    resp = requests.post(url, data=data)
    resp.raise_for_status()
    return resp.json()['access_token']

def get_newest_files(csv_file, count=15):
    """
    Reads CSV, filters to files with allowed extensions, sorts newest first,
    and returns up to `count` files as list of dicts.
    """
    files = []
    with open(csv_file, newline='', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        for row in reader:
            name = row.get('file_name', '')
            ext = os.path.splitext(name)[1].lower()
            # Filter by type, must be a file (not folder).
            if (
                row.get('file_type', '').lower() != 'folder'
                and ext in ALLOWED_EXT
                and row.get('user_id')         # needed for correct Graph API call
                and row.get('file_id')
            ):
                try:
                    # Parse ISO8601 datetimes
                    dt = row['last_modified'] or row.get('last_modifiedDateTime', '')
                    dt_obj = datetime.fromisoformat(dt.replace("Z", "+00:00"))
                except Exception:
                    continue
                files.append((row, dt_obj))
    # Sort newest first
    files.sort(key=lambda tup: tup[1], reverse=True)
    return [tup[0] for tup in files[:count]]

def fetch_file_content(file_info, access_token):
    """
    Download file from Microsoft Graph API using /drive/items/{id}/content,
    and extract text using the right library (depending on extension).
    Returns string containing as much clean text as possible.
    """
    ext = os.path.splitext(file_info['file_name'])[1].lower()
    user_id = file_info['user_id']
    file_id = file_info['file_id']
    # Always construct a new /content endpoint and use our app token!
    url = f"https://graph.microsoft.com/v1.0/users/{user_id}/drive/items/{file_id}/content"
    headers = {"Authorization": f"Bearer {access_token}"}
    resp = requests.get(url, headers=headers)
    resp.raise_for_status()

    # For text-like file types: decode as UTF-8 or fallback.
    if ext in ('.txt', '.csv', '.json'):
        try:
            return resp.content.decode('utf-8')
        except UnicodeDecodeError:
            return resp.content.decode('latin-1', errors="replace")

    # For Word .docx: use python-docx to extract all paragraphs.
    elif ext == '.docx':
        tmp = tempfile.NamedTemporaryFile(delete=False, suffix='.docx')
        try:
            tmp.write(resp.content)
            tmp.close()
            doc = Document(tmp.name)
            text = "\n".join(p.text for p in doc.paragraphs)
            return text
        finally:
            os.unlink(tmp.name)

    # For PDF: Use PyPDF2 to extract page text.
    elif ext == '.pdf':
        pdf_bytes = io.BytesIO(resp.content)
        reader = PdfReader(pdf_bytes)
        text = ""
        for page in reader.pages:
            try:
                page_text = page.extract_text() or ""
                text += page_text + "\n"
            except Exception:
                pass
        return text

    else:
        return "[Unknown filetype, cannot extract text]"

def main():
    # Step 1: Acquire a new access token for Graph API.
    access_token = get_access_token()

    # Step 2: Pull out newest relevant files from CSV index
    newest = get_newest_files(CSV_FILE, 15)     # Change number here
    if not newest:
        print("No matching files found.")
        return

    # Step 3: For each file: download, extract content, save as TXT
    for idx, file_info in enumerate(newest, 1):
        fname = file_info['file_name']
        file_id = file_info['file_id']
        # Sanitize the filename/id for filesystem safety
        fname_clean = sanitize_filename(fname)
        id_clean = sanitize_filename(file_id)
        out_file = f"{fname_clean}_{id_clean}.txt"
        print(f"\nProcessing {fname} (last modified: {file_info['last_modified']}), saving to {out_file}")

        try:
            text = fetch_file_content(file_info, access_token)
            # Truncate large files to avoid disk bloat and OOM errors
            text_out = text[:MAX_OUT_CHARS]
            with open(out_file, "w", encoding="utf-8", newline='\n') as outf:
                outf.write(text_out)
            if len(text) > MAX_OUT_CHARS:
                with open(out_file, "a", encoding="utf-8") as outf:
                    outf.write("\n...[truncated]...\n")
            print(f"File written: {out_file}")
        except Exception as e:
            print(f"ERROR retrieving/decoding file: {e}")

if __name__ == '__main__':
    main()