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
import stat

LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"
CLASSIFIER_SCRIPT_PATH = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/NFS/2_nfs_content_extract_compliance.py'
MAX_CLASSIFY_PARALLEL = int(os.environ.get("NFS_CLASSIFY_PARALLEL", "2"))

logging.basicConfig(
    format="%(asctime)s %(levelname)s %(message)s",
    level=logging.INFO,
)
log = logging.getLogger()

def logmsg(msg, level="INFO"):
    timestr = datetime.utcnow().isoformat()
    out = f"[NFS-SCAN {timestr}] [{level}] {msg}"
    try:
        with open(LARAVEL_LOG, "a") as f:
            f.write(out + "\n")
    except Exception:
        pass
    getattr(log, level.lower(), log.info)(out)

# ---- Args ----
if len(sys.argv) < 5:
    logmsg("Usage: python3 NFS_master_scan_delta_classify_step.py <BASE_PATH> <CONFIG_ID> <REGULATIONS_JSON>", "ERROR")
    sys.exit(1)

BASE_PATH   = sys.argv[1]  # Must be a local-mount path, e.g. /mnt/share
CONFIG_ID   = sys.argv[2]
RAW_REGULATIONS = sys.argv[3]

BASE_WEBHOOK_PATH = '/home/cybersecai/htdocs/www.cybersecai.io/webhook'
CSV_FOLDER = os.path.join(BASE_WEBHOOK_PATH, 'NFS', CONFIG_ID)
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

headers = [
    "file_path", "file_name", "file_type", "size_bytes",
    "last_modified", "created", "full_path", "permissions", "owner", "group"
]

rows_for_csv = []
rows_for_json = []

def get_file_perms(st, is_dir):
    """Return ls-style permissions string, e.g. -rwxr-xr-x"""
    return stat.filemode(st.st_mode)

def get_file_owner_group(st):
    try:
        import pwd, grp
        owner = pwd.getpwuid(st.st_uid).pw_name
        group = grp.getgrgid(st.st_gid).gr_name
        return owner, group
    except Exception:
        return "", ""

def walk_nfs(local_path, rel_path=""):
    try:
        for entry in os.scandir(local_path):
            fname = entry.name
            if fname in (".", ".."):
                continue
            full_path = os.path.join(local_path, fname)
            rel_full_path = os.path.join(rel_path, fname) if rel_path else fname
            try:
                st = entry.stat(follow_symlinks=False)
            except Exception as e:
                logmsg(f"Cannot stat {full_path}: {e}", "WARNING")
                continue
            is_dir = entry.is_dir(follow_symlinks=False)
            perms = get_file_perms(st, is_dir)
            owner, group = get_file_owner_group(st)
            entryjson = {
                "file_path": rel_full_path,
                "file_name": fname,
                "file_type": "folder" if is_dir else os.path.splitext(fname)[1].lower().strip("."),
                "size_bytes": st.st_size,
                "last_modified": datetime.utcfromtimestamp(st.st_mtime).isoformat() if st.st_mtime else "",
                "created": datetime.utcfromtimestamp(st.st_ctime).isoformat() if st.st_ctime else "",
                "full_path": full_path,
                "permissions": perms,
                "owner": owner,
                "group": group
            }
            rows_for_json.append(entryjson)
            rows_for_csv.append([
                entryjson['file_path'],
                entryjson['file_name'],
                entryjson['file_type'],
                entryjson['size_bytes'],
                entryjson['last_modified'],
                entryjson['created'],
                entryjson['full_path'],
                entryjson['permissions'],
                entryjson['owner'],
                entryjson['group'],
            ])
            if is_dir:
                walk_nfs(full_path, rel_full_path)
    except Exception as e:
        logmsg(f"WARNING: Could not access directory {local_path}: {e}", "WARNING")
        traceback.print_exc()

# --- Load Previous State ---
json_file = os.path.join(CSV_FOLDER, f"nfs_files_list_{CONFIG_ID}.json")
delta_file = os.path.join(CSV_FOLDER, f"nfs_files_list_{CONFIG_ID}_delta.json")
previous_inventory = []
if os.path.exists(json_file):
    try:
        with open(json_file, "r", encoding='utf-8') as jf:
            previous_inventory = json.load(jf)
        logmsg(f"Loaded previous inventory ({len(previous_inventory)} entries) from {json_file}")
    except Exception as e:
        logmsg(f"Could not load previous inventory: {e}")

def entries_to_dict(entries):
    return { entry['file_path']: entry for entry in entries }

def get_entry_signature(entry):
    sig = (
        entry.get("file_type", ""),
        entry.get("size_bytes", 0),
        entry.get("last_modified", ""),
        entry.get("permissions", ""),
        entry.get('owner', ""),
        entry.get('group', "")
    )
    return hashlib.sha256(("|".join(str(x) for x in sig)).encode()).hexdigest()

previous_dict = entries_to_dict(previous_inventory)

# -- Main crawl --
logmsg(f"Listing root path on NFS: '{BASE_PATH}'")
walk_nfs(BASE_PATH)
logmsg(f"Completed walk_nfs() for BASE_PATH: '{BASE_PATH}', files found: {len(rows_for_csv)}")

# --- Delta Calculation ---
current_dict = entries_to_dict(rows_for_json)
current_files_set = set(current_dict.keys())
previous_files_set = set(previous_dict.keys())

added_files = current_files_set - previous_files_set
deleted_files = previous_files_set - current_files_set
added_entries = [current_dict[f] for f in sorted(added_files)]
deleted_entries = [previous_dict[f] for f in sorted(deleted_files)]

modified_entries = []
for f in sorted(current_files_set & previous_files_set):
    curr_entry = current_dict[f]
    prev_entry = previous_dict[f]
    if get_entry_signature(curr_entry) != get_entry_signature(prev_entry):
        modified_entries.append(curr_entry)

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

# --- Write Outputs ---
csv_file = os.path.join(CSV_FOLDER, f"nfs_files_list_{CONFIG_ID}.csv")
with open(csv_file, "w", newline='', encoding="utf-8") as csvfile:
    writer = csv.writer(csvfile)
    writer.writerow(headers)
    writer.writerows(rows_for_csv)

with open(json_file, "w", encoding='utf-8') as jf:
    json.dump(rows_for_json, jf, indent=2)

with open(delta_file, "w", encoding='utf-8') as df:
    json.dump(delta_json, df, indent=2)
logmsg(f"Wrote CSV: {csv_file}")
logmsg(f"Wrote updated JSON: {json_file}")
logmsg(f"Wrote delta JSON: {delta_file}")

print(json.dumps({
    "success": True,
    "files": [csv_file, json_file, delta_file],
    "added": len(added_entries),
    "deleted": len(deleted_entries),
    "modified": len(modified_entries)
}))

# Write secrets JSON (for audit, not needed but for consistency)
secrets_data = {
    "BASE_PATH": BASE_PATH
}
secrets_file = os.path.join(CSV_FOLDER, f"{CONFIG_ID}_secrets.json")
with open(secrets_file, "w", encoding="utf-8") as f:
    json.dump(secrets_data, f, indent=2)
logmsg(f"Wrote secrets JSON: {secrets_file}")

# --- Classification step (calls script 2 for compliance) ---
logmsg("Starting compliance classification using 2_nfs_content_extract_compliance.py ...")
with ThreadPoolExecutor(max_workers=MAX_CLASSIFY_PARALLEL) as executor:
    file_futures = []
    for row in rows_for_json:
        f = executor.submit(
            lambda config_id=CONFIG_ID, file_record=row: subprocess.run(
                [sys.executable, CLASSIFIER_SCRIPT_PATH, str(config_id)],
                stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, timeout=1800
            ), CONFIG_ID, row)
        file_futures.append(f)
    for i, future in enumerate(as_completed(file_futures)):
        retcode = future.result().returncode
        logmsg(f"Classifier script finished for file {i+1} of {len(rows_for_json)}: return={retcode}")

logmsg("All classification jobs finished.")