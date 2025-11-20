import os, sys, json, io, tempfile, requests, re

def sanitize_filename(name):
    return re.sub(r'[^A-Za-z0-9._-]', '_', name)

def fetch_file_content(file_info, access_token):
    ext = os.path.splitext(file_info['file_name'])[1].lower()
    user_id, file_id = file_info['user_id'], file_info['file_id']
    url = f"https://graph.microsoft.com/v1.0/users/{user_id}/drive/items/{file_id}/content"
    headers = {"Authorization": f"Bearer {access_token}"}
    resp = requests.get(url, headers=headers)
    resp.raise_for_status()
    if ext in ('.txt', '.csv', '.json'):
        try:
            return resp.content.decode('utf-8')
        except UnicodeDecodeError:
            return resp.content.decode('latin-1', errors="replace")
    elif ext == '.docx':
        from docx import Document
        tmp = tempfile.NamedTemporaryFile(delete=False, suffix='.docx')
        try:
            tmp.write(resp.content)
            tmp.close()
            doc = Document(tmp.name)
            text = "\n".join(p.text for p in doc.paragraphs)
            return text
        finally:
            os.unlink(tmp.name)
    elif ext == '.pdf':
        from PyPDF2 import PdfReader
        pdf_bytes = io.BytesIO(resp.content)
        reader = PdfReader(pdf_bytes)
        text = ""
        for page in reader.pages:
            try:
                text += (page.extract_text() or "") + "\n"
            except Exception:
                pass
        return text
    else:
        return ""

def get_access_token(tenant, client_id, client_secret):
    url = f"https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token"
    data = {
        'grant_type': 'client_credentials',
        'client_id': client_id,
        'client_secret': client_secret,
        'scope': 'https://graph.microsoft.com/.default'
    }
    resp = requests.post(url, data=data)
    resp.raise_for_status()
    return resp.json()['access_token']

def main():
    if len(sys.argv) != 2:
        print(json.dumps({"contains_PII":"Error","LLM_Response":"No Args"}))
        sys.exit(1)
    cfg = json.loads(sys.argv[1])
    TENANT_ID = cfg['TENANT_ID']
    CLIENT_ID = cfg['CLIENT_ID']
    CLIENT_SECRET = cfg['CLIENT_SECRET']
    fr = cfg['file_row']
    # Build dict
    file_info = {
        "user_id": fr[0],
        "file_id": fr[1],
        "file_name": fr[2],
        "file_type": fr[3],
        "size_bytes": fr[4],
        "last_modified": fr[5],
        "web_url": fr[6],
        "download_url": fr[7],
        "parent_reference": fr[8],
        "full_path": fr[9]
    }
    # Only do for supported types
    ext = os.path.splitext(file_info['file_name'])[1].lower()
    allowed = ('.docx', '.txt', '.pdf', '.csv', '.json')
    if (file_info.get('file_type', '').lower() == "folder" or ext not in allowed):
        print(json.dumps({"contains_PII":"NotSupported","LLM_Response":"Not a supported file type"}))
        return
    # Get file
    try:
        access_token = get_access_token(TENANT_ID, CLIENT_ID, CLIENT_SECRET)
        text = fetch_file_content(file_info, access_token)
        if not text.strip():
            print(json.dumps({"contains_PII":"NoText","LLM_Response":"No extractable text"}))
            return
    except Exception as ex:
        print(json.dumps({"contains_PII":"Error","LLM_Response":str(ex)}))
        return

    # CLASSIFY using script 3 (as subprocess, pass json with text!)
    try:
        args = json.dumps({"text": text})
        proc = subprocess.run(["python3", "3_file_content_classificationwithLLM_pipeline.py", args],
                              capture_output=True, text=True, timeout=150)
        if proc.returncode == 0 and proc.stdout:
            out = json.loads(proc.stdout)
            print(json.dumps(out))
        else:
            print(json.dumps({"contains_PII":"Error","LLM_Response":proc.stderr}))
    except Exception as ex:
        print(json.dumps({"contains_PII":"Error","LLM_Response":str(ex)}))

if __name__ == '__main__':
    main()