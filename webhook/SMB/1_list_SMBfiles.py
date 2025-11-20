# Python Script 1: Crawl SMB Share & Output File List
# This script will:

# Recursively walk the SMB share from a base path.
# Save a CSV and JSON with similar fields to your OneDrive/SharePoint approach.
# Write files into a per-config output folder.
# Dependencies
# You'll need:

# smbprotocol (for connecting to SMB shares): pip install smbprotocol
# Usual python libs: os, json, csv, logging, etc.

import smbclient
import os
import sys
import json
import csv
import logging
from datetime import datetime

LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"

logging.basicConfig(
    format="%(asctime)s %(levelname)s %(message)s",
    level=logging.INFO,
)
log = logging.getLogger()

def logmsg(msg, level="INFO"):
    timestr = datetime.utcnow().isoformat()
    out = f"[PYSMB {timestr}] [{level}] {msg}"
    try:
        with open(LARAVEL_LOG, "a") as f:
            f.write(out + "\n")
    except Exception:
        pass
    getattr(log, level.lower(), log.info)(out)

# ---- Args ----
if len(sys.argv) < 7:
    logmsg("Usage: python3 1_list_smbfiles.py <SMB_SERVER> <USERNAME> <PASSWORD> <SHARE> [<DOMAIN>] [<BASE_PATH>] <CONFIG_ID> <REGULATIONS_JSON>", "ERROR")
    sys.exit(1)

SMB_SERVER = sys.argv[1]
USERNAME   = sys.argv[2]
PASSWORD   = sys.argv[3]
SHARE      = sys.argv[4]
DOMAIN     = sys.argv[5] if len(sys.argv) > 7 else ''
BASE_PATH  = sys.argv[6] if len(sys.argv) > 7 else ''
CONFIG_ID  = sys.argv[-2]
RAW_REGULATIONS = sys.argv[-1]

BASE_WEBHOOK_PATH = '/home/cybersecai/htdocs/www.cybersecai.io/webhook'
CSV_FOLDER = os.path.join(BASE_WEBHOOK_PATH, 'SMB', CONFIG_ID)
os.makedirs(CSV_FOLDER, exist_ok=True)

try:
    regulations = json.loads(RAW_REGULATIONS)
    logmsg(f"Parsed regulations JSON: {len(regulations)} items")
except Exception as e:
    logmsg(f"Could not parse regulations JSON: {e}", "ERROR")
    sys.exit(1)

# Build compliance matrix
compliance_matrix = []
for reg in regulations:
    fields = reg.get('fields', [])
    compliance_matrix.append({
        "standard": reg.get('standard'),
        "jurisdiction": reg.get('jurisdiction'),
        "fields": sorted(set(fields))
    })
matrix_path = os.path.join(CSV_FOLDER, "compliance_matrix.json")
with open(matrix_path, "w", encoding="utf-8") as mf:
    json.dump(compliance_matrix, mf, indent=2)
logmsg(f"Wrote compliance matrix to {matrix_path}")

smb_url_base = f"//{SMB_SERVER}/{SHARE}"


smbclient.register_session(
    SMB_SERVER,
    username=USERNAME,
    password=PASSWORD
)

headers = [
    "file_path", "file_name", "file_type", "size_bytes",
    "last_modified", "created", "full_path"
]

rows_for_csv = []
rows_for_json = []

def smb_join_path(base, name):
    # Handle double slashes consistently
    if base.endswith("/"):
        return base + name
    return base + "/" + name

def walk_smb(smb_path, rel_path=""):
    try:
        for entry in smbclient.scandir(smb_path):
            fname = entry.name
            if fname in (".", ".."):
                continue
            file_full_smb_path = smb_join_path(smb_path, fname)
            rel_full_path = os.path.join(rel_path, fname) if rel_path else fname
            stat = entry.stat()
            is_dir = entry.is_dir()
            entryjson = {
                "file_path": rel_full_path,
                "file_name": fname,
                "file_type": "folder" if is_dir else os.path.splitext(fname)[1].lower().strip("."),
                "size_bytes": stat.st_size,
                "last_modified": datetime.utcfromtimestamp(stat.st_mtime).isoformat() if stat.st_mtime else "",
                "created": datetime.utcfromtimestamp(stat.st_ctime).isoformat() if stat.st_ctime else "",
                "full_path": file_full_smb_path
            }
            rows_for_json.append(entryjson)
            rows_for_csv.append([
                entryjson['file_path'], entryjson['file_name'], entryjson['file_type'],
                entryjson['size_bytes'], entryjson['last_modified'], entryjson['created'], entryjson['full_path']
            ])
            if is_dir:
                walk_smb(file_full_smb_path, rel_full_path)
    except Exception as e:
        logmsg(f"WARNING: Could not access directory {smb_path}: {e}", "WARNING")
        return

root_path = smb_url_base + "/" + BASE_PATH.strip("/") if BASE_PATH else smb_url_base
logmsg(f"Listing root path on SMB: '{root_path}'")
logmsg(f"walk_smb() will run with root_path='{root_path}'")
walk_smb(root_path)
logmsg(f"Completed walk_smb() for root_path: '{root_path}', files found: {len(rows_for_csv)}")

csv_file = os.path.join(CSV_FOLDER, f"smb_files_list_{CONFIG_ID}.csv")
json_file = os.path.join(CSV_FOLDER, f"smb_files_list_{CONFIG_ID}.json")
with open(csv_file, "w", newline='', encoding="utf-8") as csvfile:
    writer = csv.writer(csvfile)
    writer.writerow(headers)
    writer.writerows(rows_for_csv)
with open(json_file, "w", encoding='utf-8') as jf:
    json.dump(rows_for_json, jf, indent=2)
logmsg(f"Wrote file list CSV: {csv_file}")
logmsg(f"Wrote file list JSON: {json_file}")

# Write secrets JSON (connection details only)
secrets_data = {
    "SMB_SERVER": SMB_SERVER,
    "USERNAME": USERNAME,
    "PASSWORD": PASSWORD,
    "SHARE": SHARE,
    "DOMAIN": DOMAIN,
    "BASE_PATH": BASE_PATH
}
secrets_file = os.path.join(CSV_FOLDER, f"{CONFIG_ID}_secrets.json")
with open(secrets_file, "w", encoding="utf-8") as f:
    json.dump(secrets_data, f, indent=2)
logmsg(f"Wrote secrets JSON: {secrets_file}")

print(json.dumps({"success": True, "files": [csv_file, json_file], "secrets_file": secrets_file}))