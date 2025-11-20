# - `pip install google-api-python-client google-auth-httplib2 google-auth-oauthlib google-auth PyPDF2 python-docx openai`
# - You need a **Google Drive service account** (`.json` credentials) or use OAuth (adjust as needed)
# - Assumes service account credentials JSON path is passed or stored as env var.
# (File Listing)

#!/usr/bin/env python3
import os
import sys
import json
import csv
import logging
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor, as_completed

from googleapiclient.discovery import build
from google.oauth2 import service_account

# -----------------------------------------------
# CONFIGURATION: adjust only if paths differ
LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"
BASE_WEBHOOK_PATH = "/home/cybersecai/htdocs/www.cybersecai.io/webhook"
# -----------------------------------------------

logging.basicConfig(format="%(asctime)s %(levelname)s %(message)s", level=logging.INFO)
log = logging.getLogger()

def logmsg(msg, level="INFO"):
    ts = datetime.utcnow().isoformat()
    out = f"[PY_GDrive {ts}] [{level}] {msg}"
    try:
        with open(LARAVEL_LOG, "a") as f:
            f.write(out + "\n")
    except Exception:
        pass
    getattr(log, level.lower(), log.info)(out)

def fail_exit(why, code=1):
    logmsg(why, "ERROR")
    print(json.dumps({"success": False, "err": why}))  # JSON error for Laravel
    sys.exit(code)

# ---- Input parsing ----
if len(sys.argv) != 5:
    fail_exit("Usage: python3 1_list_gdrivefiles_parallel.py "
              "<SERVICE_ACCOUNT_JSON> <CONFIG_ID> <REGULATIONS_JSON> <GDRIVE_FOLDER_ID>")

SERVICE_ACCOUNT_ARG, CONFIG_ID, RAW_REGULATIONS, FOLDER_ID = sys.argv[1:5]
CSV_FOLDER = os.path.join(BASE_WEBHOOK_PATH, 'GDRIVE', CONFIG_ID)
os.makedirs(CSV_FOLDER, exist_ok=True)

# ---- Load + write compliance matrix (unchanged) ----
try:
    regs = json.loads(RAW_REGULATIONS)
    matrix = []
    for r in regs:
        matrix.append({
            "standard": r.get('standard'),
            "jurisdiction": r.get('jurisdiction'),
            "fields": sorted(set(r.get('fields', [])))
        })
    with open(os.path.join(CSV_FOLDER, "compliance_matrix.json"), "w", encoding="utf-8") as mf:
        json.dump(matrix, mf, indent=2)
except Exception as e:
    fail_exit(f"Invalid REGULATIONS_JSON or write error: {e}")

# ---- Prepare Service Account credentials file ----
def is_json(s):
    try: json.loads(s); return True
    except: return False

if os.path.isfile(SERVICE_ACCOUNT_ARG):
    sa_path = SERVICE_ACCOUNT_ARG
else:
    if is_json(SERVICE_ACCOUNT_ARG):
        creds_blob = json.loads(SERVICE_ACCOUNT_ARG)
        sa_path = os.path.join(CSV_FOLDER, "sa-tmp.json")
        with open(sa_path, "w", encoding="utf-8") as f:
            json.dump(creds_blob, f)
    else:
        fail_exit("SERVICE_ACCOUNT_JSON must be a file path or a JSON blob.")

logmsg(f"Service account JSON at: {sa_path}", "INFO")

# ---- Impersonation admin (must be set via ENV) ----
#ADMIN_EMAIL = os.environ.get("GDRIVE_IMPERSONATE_USER")
ADMIN_EMAIL = "cybersecaiapi@cybersecaiapi.iam.gserviceaccount.com"
if not ADMIN_EMAIL:
    fail_exit("You must set GDRIVE_IMPERSONATE_USER=superadmin@yourdomain.com")

SCOPES = [
    'https://www.googleapis.com/auth/admin.directory.user.readonly',
    'https://www.googleapis.com/auth/drive.readonly'
]

# ---- Build Credentials & Clients ----
try:
    creds = service_account.Credentials.from_service_account_file(
        sa_path, scopes=SCOPES, subject=ADMIN_EMAIL
    )
    logmsg("Loaded service account credentials with impersonation.", "INFO")
except Exception as e:
    fail_exit(f"SA credential load error: {e}")

try:
    admin_client = build('admin', 'directory_v1', credentials=creds, cache_discovery=False)
    logmsg("Built Admin SDK Directory client.", "INFO")
except Exception as e:
    fail_exit(f"Directory API client error: {e}")

# Note: we will build per-user Drive clients below, so no global drive_client here

# ---- Fetch ALL USERS ----
users = []
page = None
logmsg("Listing all users...", "INFO")
while True:
    try:
        resp = admin_client.users().list(
            customer="my_customer", maxResults=200,
            pageToken=page, orderBy="email"
        ).execute()
    except Exception as e:
        fail_exit(f"Error listing users: {e}")
    batch = resp.get('users', [])
    users.extend(batch)
    logmsg(f"Retrieved {len(batch)} users, total so far {len(users)}", "INFO")
    page = resp.get('nextPageToken')
    if not page:
        break

if not users:
    fail_exit("No users found. Check your impersonation and delegation settings.")

# ---- Helpers to list files under a folder for a given Drive client ----
def list_files_under_folder(drive_svc, folder_id):
    files = []
    stack = [folder_id]
    while stack:
        fid = stack.pop()
        q = f"'{fid}' in parents and trashed=false"
        token = None
        while True:
            try:
                resp = drive_svc.files().list(
                    q=q, pageSize=500,
                    pageToken=token,
                    fields="nextPageToken, files(id, name, mimeType, createdTime, modifiedTime, size, parents)"
                ).execute()
            except Exception as ee:
                logmsg(f"Error listing files in folder {fid}: {ee}", "ERROR")
                break
            for f in resp.get('files', []):
                if f['mimeType'] == 'application/vnd.google-apps.folder':
                    stack.append(f['id'])
                else:
                    files.append(f)
            token = resp.get('nextPageToken')
            if not token:
                break
    return files

# ---- Main scan: per-user impersonation, per-folder recursion ----
headers = ["user_email","file_id","file_name","file_type","size_bytes","created_time","modified_time","full_path"]
csv_rows = []
json_rows = []

logmsg(f"Beginning per-user scan under folder '{FOLDER_ID}' for {len(users)} users", "INFO")

for user in users:
    email = user.get('primaryEmail')
    if not email:
        logmsg(f"Skipping invalid user record: {user}", "WARNING")
        continue

    logmsg(f"Impersonating {email}", "INFO")
    user_creds = creds.with_subject(email)
    try:
        drive_svc = build('drive', 'v3', credentials=user_creds, cache_discovery=False)
    except Exception as e:
        logmsg(f"Failed to build Drive client for {email}: {e}", "ERROR")
        continue

    files = list_files_under_folder(drive_svc, FOLDER_ID)
    logmsg(f"{email}: found {len(files)} files", "INFO")

    for f in files:
        ext = os.path.splitext(f.get('name',''))[1].lower().strip('.') if f.get('name') else ''
        entry = {
            "user_email": email,
            "file_id": f.get('id'),
            "file_name": f.get('name'),
            "file_type": ext,
            "size_bytes": int(f.get('size',0) or 0),
            "created_time": f.get('createdTime'),
            "modified_time": f.get('modifiedTime'),
            "full_path": f.get('name')
        }
        csv_rows.append([
            entry["user_email"], entry["file_id"], entry["file_name"],
            entry["file_type"], entry["size_bytes"], entry["created_time"],
            entry["modified_time"], entry["full_path"]
        ])
        json_rows.append(entry)

# ---- Write out CSV + JSON ----
try:
    with open(os.path.join(CSV_FOLDER, f"gdrive_files_list_{CONFIG_ID}.csv"),
              "w", newline='', encoding="utf-8") as cf:
        writer = csv.writer(cf)
        writer.writerow(headers)
        writer.writerows(csv_rows)

    with open(os.path.join(CSV_FOLDER, f"gdrive_files_list_{CONFIG_ID}.json"),
              "w", encoding="utf-8") as jf:
        json.dump(json_rows, jf, indent=2)

    logmsg(f"Scan complete. Total files: {len(json_rows)}", "INFO")
    print(json.dumps({
        "success": True,
        "files": [
            os.path.join(CSV_FOLDER, f"gdrive_files_list_{CONFIG_ID}.csv"),
            os.path.join(CSV_FOLDER, f"gdrive_files_list_{CONFIG_ID}.json")
        ]
    }))
except Exception as e:
    fail_exit(f"Error writing output: {e}")