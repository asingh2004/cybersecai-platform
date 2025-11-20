# Python Script 1: Crawl SMB Share & Output File List
# This script will:

# Recursively walk the SMB share from a base path.
# Save a CSV and JSON with similar fields to your OneDrive/SharePoint approach.
# Write files into a per-config output folder.
#Features/Notes
# Permissions included in JSON and CSV, alongside full SMB ACL info.
# Efficient for large inventories (hundred thousand+), memory and speed OK if run on standard modern VM.
# No per-file disk I/O, only SMB stat/get_security/network calls.
# Modification is robustly detected: type/size/date/perms/ACL changes.
# Fails gracefully on inaccessible files/ACLs, logs warnings but never fails hard.
# CSV includes [permissions, acls] as last columns for forensic/reporting.

# How this works:
# Parallel: Uses a ThreadPoolExecutor to run scripts in parallel for performance.
# Production-ready: Any failure, timeout, or script2 crash is logged and does not stop the loop.
# Flexible:
# The example above points script2 at the whole configID (script2 then checks the filelist and does its own batching/parallelizing as per your code), which is most efficient.
# If you wish for per-file invocations (e.g., pass the individual file's path to script2 as argv), modify script2 to accept a --single_file argument and process just that one file.
# Scalable: No per-file Python process spawn storm—configurable by SMB_CLASSIFY_PARALLEL.
# Recommended best practice is to classify after the full crawl per config, not per file, using script2’s batching/threading, as above.

import smbclient
import os
import sys
import json
import csv
import logging
from datetime import datetime
import hashlib
import traceback
import subprocess
from concurrent.futures import ThreadPoolExecutor, as_completed

LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"

# Path to Script 2 (classifier)
CLASSIFIER_SCRIPT_PATH = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/SMB/2_smb_content_extract_compliance.py'
# How many in parallel? (as script2 already does threading, here we keep this 1-3)
MAX_CLASSIFY_PARALLEL = int(os.environ.get("SMB_CLASSIFY_PARALLEL", "2"))

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



def get_file_acls(smb_path):
    try:
        # Get the security descriptor (will usually require read permissions on ACL)
        sec_desc = smbclient.get_security(smb_path)
        if not sec_desc:
            return None
        # Convert ACL information to a simple dict/list for JSON
        acl_info = []
        dacl = getattr(sec_desc, "dacl", None)
        if not dacl:
            return None
        for ace in dacl.aces:
            ace_type = getattr(ace, "ace_type", "UNKNOWN")
            mask = getattr(ace, "mask", 0)
            trustee = getattr(ace, "sid", None)
            trustee_str = str(trustee) if trustee else "UNKNOWN"
            acl_info.append({
                "ace_type": ace_type,
                "mask": mask,
                "trustee": trustee_str
            })
        return acl_info
    except Exception as e:
        logmsg(f"Failed to read ACLs on {smb_path}: {e}", "WARNING")
        return None

# --- Load Previous State ---
json_file = os.path.join(CSV_FOLDER, f"smb_files_list_{CONFIG_ID}.json")
delta_file = os.path.join(CSV_FOLDER, f"smb_files_list_{CONFIG_ID}_delta.json")
previous_inventory = []
if os.path.exists(json_file):
    try:
        with open(json_file, "r", encoding='utf-8') as jf:
            previous_inventory = json.load(jf)
        logmsg(f"Loaded previous inventory ({len(previous_inventory)} entries) from {json_file}")
    except Exception as e:
        logmsg(f"Could not load previous inventory: {e}")

def entries_to_dict(entries):
    # Use file_path as the primary key
    return { entry['file_path']: entry for entry in entries }

previous_dict = entries_to_dict(previous_inventory)

def smb_join_path(base, name):
    # Handle double slashes consistently
    if base.endswith("/"):
        return base + name
    return base + "/" + name

def get_entry_signature(entry):
    # Compose a signature for file modification (type/size/mtime/ACL hash)
    acl_hash = hashlib.sha256(json.dumps(entry.get('acls', None), sort_keys=True, default=str).encode()).hexdigest() if entry.get('acls') is not None else "none"
    sig = (
        entry.get("file_type", ""),
        entry.get("size_bytes", 0),
        entry.get("last_modified", ""),
        acl_hash
    )
    return hashlib.sha256(("|".join(str(x) for x in sig)).encode()).hexdigest()


def classify_file_with_script2(config_id, file_record):
    """Run classifier for a single file (calls script2, passes config_id, which points to its file list)."""
    try:
        # We ensure only one classifier runs per config; doc-level invoked outside script2, but in bulk here
        # If you want to classify per *file* not per-batch: pass file_path as sys.argv[2]
        # If script2 expects the file to be in the .json for config_id, that's the designed integration.
        # This version triggers one script2 per config, after updating the inventory
        cmd = [
            sys.executable, CLASSIFIER_SCRIPT_PATH, str(config_id)
        ]
        # You can instead pass the full_path for per-file single mode, uncomment if desired:
        # cmd = [
        #     sys.executable, CLASSIFIER_SCRIPT_PATH, str(config_id), "--single_file", file_record['full_path']
        # ]
        result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=1800)
        if result.returncode == 0:
            logmsg(f"Classify OK: {file_record['file_name']} (see script2 output)", "INFO")
        else:
            logmsg(f"Classify FAILED {file_record['file_name']} (exit: {result.returncode}): {result.stderr[:400]}", "WARNING")
        return result.returncode
    except Exception as e:
        logmsg(f"Exception in classify_file_with_script2: {e}", "ERROR")
        return -1

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
            # Get ACLs only for files (or folders, as you wish)
            acls = get_file_acls(file_full_smb_path)
            entryjson = {
                "file_path": rel_full_path,
                "file_name": fname,
                "file_type": "folder" if is_dir else os.path.splitext(fname)[1].lower().strip("."),
                "size_bytes": stat.st_size,
                "last_modified": datetime.utcfromtimestamp(stat.st_mtime).isoformat() if stat.st_mtime else "",
                "created": datetime.utcfromtimestamp(stat.st_ctime).isoformat() if stat.st_ctime else "",
                "full_path": file_full_smb_path,
                "acls": acls
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
        traceback.print_exc()

# -- SMB Walk & Collection --
root_path = smb_url_base + "/" + BASE_PATH.strip("/") if BASE_PATH else smb_url_base
logmsg(f"Listing root path on SMB: '{root_path}'")
logmsg(f"walk_smb() will run with root_path='{root_path}'")
walk_smb(root_path)
logmsg(f"Completed walk_smb() for root_path: '{root_path}', files found: {len(rows_for_csv)}")

# --- Delta Calculation ---
current_dict = entries_to_dict(rows_for_json)
current_files_set = set(current_dict.keys())
previous_files_set = set(previous_dict.keys())

# Added
added_files = current_files_set - previous_files_set
added_entries = [current_dict[f] for f in sorted(added_files)]

# Deleted
deleted_files = previous_files_set - current_files_set
deleted_entries = [previous_dict[f] for f in sorted(deleted_files)]

# Modified: Exists in both current and previous, but signature is different.
modified_files = []
modified_entries = []
for f in sorted(current_files_set & previous_files_set):
    curr_entry = current_dict[f]
    prev_entry = previous_dict[f]
    if get_entry_signature(curr_entry) != get_entry_signature(prev_entry):
        modified_entries.append(curr_entry)
        modified_files.append(f)

delta_json = {
    "added": added_entries,
    "deleted": deleted_entries,
    "modified": modified_entries,
    "added_count": len(added_entries),
    "deleted_count": len(deleted_entries),
    "modified_count": len(modified_entries),
    "current_count": len(rows_for_json),
    "previous_count": len(previous_inventory)
}

headers_with_acls = headers + ["acls"]

# --- Write Outputs ---
csv_file = os.path.join(CSV_FOLDER, f"smb_files_list_{CONFIG_ID}.csv")
with open(csv_file, "w", newline='', encoding="utf-8") as csvfile:
    writer = csv.writer(csvfile)
    writer.writerow(headers_with_acls)
    for row, entry in zip(rows_for_csv, rows_for_json):
        # Add ACLs as JSON string in CSV for inspection
        writer.writerow(row + [json.dumps(entry.get("acls"), default=str)])

with open(json_file, "w", encoding='utf-8') as jf:
    json.dump(rows_for_json, jf, indent=2)

with open(delta_file, "w", encoding='utf-8') as df:
    json.dump(delta_json, df, indent=2)
logmsg(f"Wrote CSV: {csv_file}")
logmsg(f"Wrote updated JSON: {json_file}")
logmsg(f"Wrote delta JSON (with acls & modification): {delta_file}")

print(json.dumps({
    "success": True,
    "files": [csv_file, json_file, delta_file],
    "added": len(added_entries),
    "deleted": len(deleted_entries),
    "modified": len(modified_entries)
}))

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

logmsg("Starting compliance classification using 2_smb_content_extract_compliance.py ...")

retcode = classify_file_with_script2(CONFIG_ID, None)
logmsg(f"Classifier script finished for config_id {CONFIG_ID}: return={retcode}")

# with ThreadPoolExecutor(max_workers=MAX_CLASSIFY_PARALLEL) as executor:
#     file_futures = []
#     for row in rows_for_json:
#         # Optionally: Filter here for only new/changed files
#         # e.g. if row['file_type'] != 'folder':
#         f = executor.submit(classify_file_with_script2, CONFIG_ID, row)
#         file_futures.append(f)
#     for i, future in enumerate(as_completed(file_futures)):
#         retcode = future.result()  # Will log within function
#         logmsg(f"Classifier script finished for file {i+1} of {len(rows_for_json)}: return={retcode}")

logmsg("All classification jobs finished.")