# This script:

### Important Note:
# Initial full crawl and snapshot (state file).
# All next runs are incremental by diffing previous {key: last_modified} → current {key: last_modified}.
# If the state is lost/deleted, full scan is done again.
# → This IS the best practice analog for S3, because S3 has no true "delta" interface; the ONLY robust way is local checkpoint/compare.

# Writes compliance matrix/secrets once per config
# For each bucket:
# Loads or creates a checkpoint {bucket}_scan_state.json (dict of {key: last_modified})
# Only processes new/changed/removed files
# Fully re-creates outputs if you delete .json/state/checkpoint files
# Calls Script 4 for each bucket immediately after updating files
# Everything is logged to Laravel log and printed to console
## php artisan s3masterclassifier:s3files --max-workers=2



# CISO/Audit Statement:
# “The S3 delta scan logic implements robust best practice: each bucket is snapshotted on first scan, and only new/changed/deleted files 
# are processed on subsequent runs, ensuring no data changes are missed. If a checkpoint is lost, a fresh scan safely reboots the baseline—yielding 
# the same resilient, delta-only workflow recommended by Microsoft for Graph API, adapted for AWS S3.”

import os
import sys
import json
import csv
import re
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime
import boto3

LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"
def logmsg(msg, level="INFO"):
    timestr = datetime.utcnow().isoformat()
    out = f"[S3SCAN {timestr}] [{level}] {msg}"
    try: 
    	with open(LARAVEL_LOG, "a") as f: 
    		f.write(out+"\n")
    except Exception: 
    	pass
    print(out)

def log_exception(msg, exc):
    logmsg(f"{msg}: {repr(exc)}", "ERROR")

if len(sys.argv) < 6:
    print("Usage: python3 s3_master_scan_delta_classify_step1.py <AWS_ACCESS_KEY> <AWS_SECRET_KEY> <CONFIG_ID> <REGULATIONS_JSON> <AWS_REGION> [<S3_BUCKET>]")
    sys.exit(1)

AWS_ACCESS_KEY  = sys.argv[1]
AWS_SECRET_KEY  = sys.argv[2]
CONFIG_ID       = sys.argv[3]
RAW_REGULATIONS = sys.argv[4]
AWS_REGION      = sys.argv[5]
S3_BUCKET       = sys.argv[6] if len(sys.argv) > 6 else None

BASE_WEBHOOK_PATH = "/home/cybersecai/htdocs/www.cybersecai.io/webhook"
S3_PATH           = os.path.join(BASE_WEBHOOK_PATH, 'S3')
CONFIG_FOLDER     = os.path.join(S3_PATH, CONFIG_ID)
os.makedirs(CONFIG_FOLDER, exist_ok=True)

try:
    regulations = json.loads(RAW_REGULATIONS)
    logmsg(f"Received {len(regulations)} compliance regulation entries.")
except Exception as e:
    log_exception("Could not parse regulations JSON", e)
    print(json.dumps({"success": False, "err": f"Invalid regulations JSON: {e}"}))
    sys.exit(1)

compliance_matrix = []
for reg in regulations:
    fields = reg.get('fields', [])
    compliance_matrix.append({
        "standard": reg.get('standard'),
        "jurisdiction": reg.get('jurisdiction'),
        "fields": sorted(set(fields))
    })
matrix_path = os.path.join(CONFIG_FOLDER, "compliance_matrix.json")
with open(matrix_path, "w", encoding="utf-8") as mf:
    json.dump(compliance_matrix, mf, indent=2)
logmsg(f"Wrote compliance matrix: {matrix_path}")

secrets_file = os.path.join(CONFIG_FOLDER, f"{CONFIG_ID}_secrets.json")
with open(secrets_file, "w", encoding="utf-8") as f:
    json.dump({
        "AWS_ACCESS_KEY": AWS_ACCESS_KEY,
        "AWS_SECRET_KEY": AWS_SECRET_KEY,
        "AWS_REGION": AWS_REGION,
    }, f, indent=2)
logmsg(f"Wrote secrets file: {secrets_file}")

try:
    s3 = boto3.client(
        "s3",
        region_name=AWS_REGION,
        aws_access_key_id=AWS_ACCESS_KEY,
        aws_secret_access_key=AWS_SECRET_KEY
    )
    logmsg(f"Initialized boto3 client for region {AWS_REGION}.")
    if not S3_BUCKET:
        all_buckets = [bucket['Name'] for bucket in s3.list_buckets()['Buckets']]
        logmsg(f"Discovered buckets: {all_buckets}")
    else:
        all_buckets = [S3_BUCKET]
except Exception as e:
    log_exception("Failed to create boto3 client or enumerate buckets", e)
    sys.exit(1)

def list_all_s3_keys(bucketname, s3):
    logmsg(f"Listing objects for bucket: {bucketname}")
    keys = []
    paginator = s3.get_paginator('list_objects_v2')
    try:
        for page_idx, page in enumerate(paginator.paginate(Bucket=bucketname), 1):
            count = len(page.get('Contents', []))
            logmsg(f"{bucketname} page {page_idx}: {count} objects")
            for obj in page.get('Contents', []):
                keys.append(obj)
        logmsg(f"Total for bucket {bucketname}: {len(keys)} objects")
        return keys
    except Exception as e:
        log_exception(f"Error during traversal for {bucketname}", e)
        return []

def process_key(obj):
    key = obj['Key']
    if key.endswith('/'):  # skip folder placeholders
        return None
    ext = os.path.splitext(key)[1].lower()
    entry = {
        "key": key,
        "file_name": os.path.basename(key),
        "file_type": ext,
        "size_bytes": obj.get('Size', 0),
        "last_modified": obj.get('LastModified').isoformat() if obj.get('LastModified') else '',
        "full_path": key
    }
    return entry

def launch_classifier_script(config_id, s3_path):
    script4_path = '/home/cybersecai/htdocs/www.cybersecai.io/webhook/S3/s3_master_classifier_step2.py'
    cmd = [
        'python3', script4_path, str(config_id), s3_path
    ]
    import subprocess
    logmsg(f"Launching classifier: {' '.join([str(c) for c in cmd])}", "INFO")
    try:
        subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, close_fds=True)
    except Exception as ex:
        logmsg(f"Failed to launch S3 classifier for config {config_id}: {ex}", "ERROR")


for bucket in all_buckets:
    lockfile = os.path.join(CONFIG_FOLDER, f"lock_s3_{bucket}.lck")
    # Lockfile check: skip this bucket if already processing
    if os.path.exists(lockfile):
        logmsg(f"Already locked/running: {lockfile}", "WARNING")
        continue  # Skip to next bucket (or use 'break' or 'sys.exit(0)' if you want to abort all)

    with open(lockfile, "w") as lf:
        lf.write(str(os.getpid()))

    try:
        logmsg(f"=== Scanning bucket: {bucket} ===")
        json_file = os.path.join(CONFIG_FOLDER, f"{bucket}_s3_files_list_{CONFIG_ID}.json")
        csv_file  = os.path.join(CONFIG_FOLDER, f"{bucket}_s3_files_list_{CONFIG_ID}.csv")
        state_file = os.path.join(CONFIG_FOLDER, f"{bucket}_scan_state.json")

        headers = ["key", "file_name", "file_type", "size_bytes", "last_modified", "full_path"]
        existing_entries = {}
        # Load previous state as dict {key: last_modified}
        if os.path.isfile(state_file):
            try:
                with open(state_file, "r") as f:
                    state_data = json.load(f)
                if isinstance(state_data, dict):
                    existing_entries = state_data
                elif isinstance(state_data, list):
                    existing_entries = {k: "" for k in state_data}  # back-compat
                logmsg(f"Loaded checkpoint: {len(existing_entries)} objects for {bucket}.")
            except Exception as e:
                log_exception(f"Failed to load state for {bucket}", e)

        all_objects = list_all_s3_keys(bucket, s3)
        logmsg(f"Found {len(all_objects)} objects total for {bucket} (processing delta)")

        updated_dict = {}
        rows_for_csv, rows_for_json = [], []
        new_or_changed, deleted = 0, 0

        # Index current objects by key for quick lookup
        curr_keys = {obj['Key']: obj for obj in all_objects if not obj['Key'].endswith('/')}
        for key, obj in curr_keys.items():
            lastmod = obj['LastModified'].isoformat() if obj.get('LastModified') else ''
            # Process if never seen or last_modified changed
            if key not in existing_entries or existing_entries[key] != lastmod:
                entry = process_key(obj)
                if entry:
                    rows_for_csv.append([
                        entry['key'], entry['file_name'], entry['file_type'],
                        entry['size_bytes'], entry['last_modified'], entry['full_path']
                    ])
                    rows_for_json.append(entry)
                    new_or_changed += 1
            # Always track latest
            updated_dict[key] = lastmod

        # Retain (write) only currently present objects; delete from outputs any files not present anymore
        with open(json_file, "w", encoding="utf-8") as jf:
            json.dump(rows_for_json, jf, indent=2)
        with open(csv_file, "w", newline="", encoding="utf-8") as csvfile:
            writer = csv.writer(csvfile)
            writer.writerow(headers)
            writer.writerows(rows_for_csv)
        with open(state_file, "w") as f:
            json.dump(updated_dict, f)

        deleted = len({k for k in existing_entries if k not in curr_keys})
        logmsg(f"[{bucket}] Delta scan: {new_or_changed} new/changed, {deleted} deleted. Final {len(rows_for_json)} files.")

        # Call Script 4 after scan/completion for this bucket
        launch_classifier_script(CONFIG_ID, S3_PATH)

    except Exception as e:
        log_exception(f"Exception during bucket scan for {bucket}", e)
    finally:
        try:
            os.remove(lockfile)
        except Exception as e:
            log_exception(f"Failed to remove lockfile for {bucket}", e)

logmsg("SUCCESS: All bucket(s) traversal and export complete.", "INFO")
print(json.dumps({"success": True}))

# for bucket in all_buckets:
#     logmsg(f"=== Scanning bucket: {bucket} ===")
#     json_file = os.path.join(CONFIG_FOLDER, f"{bucket}_s3_files_list_{CONFIG_ID}.json")
#     csv_file  = os.path.join(CONFIG_FOLDER, f"{bucket}_s3_files_list_{CONFIG_ID}.csv")
#     state_file = os.path.join(CONFIG_FOLDER, f"{bucket}_scan_state.json")

#     headers = ["key", "file_name", "file_type", "size_bytes", "last_modified", "full_path"]
#     existing_entries = {}
#     # Load previous state as dict {key: last_modified}
#     if os.path.isfile(state_file):
#         try:
#             with open(state_file, "r") as f:
#                 state_data = json.load(f)
#             if isinstance(state_data, dict):
#                 existing_entries = state_data
#             elif isinstance(state_data, list):
#                 existing_entries = {k: "" for k in state_data}  # back-compat
#             logmsg(f"Loaded checkpoint: {len(existing_entries)} objects for {bucket}.")
#         except Exception as e:
#             log_exception(f"Failed to load state for {bucket}", e)

#     all_objects = list_all_s3_keys(bucket, s3)
#     logmsg(f"Found {len(all_objects)} objects total for {bucket} (processing delta)")

#     updated_dict = {}
#     rows_for_csv, rows_for_json = [], []
#     new_or_changed, deleted = 0, 0

#     # Index current objects by key for quick lookup
#     curr_keys = {obj['Key']: obj for obj in all_objects if not obj['Key'].endswith('/')}
#     for key, obj in curr_keys.items():
#         lastmod = obj['LastModified'].isoformat() if obj.get('LastModified') else ''
#         # Process if never seen or last_modified changed
#         if key not in existing_entries or existing_entries[key] != lastmod:
#             entry = process_key(obj)
#             if entry:
#                 rows_for_csv.append([
#                     entry['key'], entry['file_name'], entry['file_type'],
#                     entry['size_bytes'], entry['last_modified'], entry['full_path']
#                 ])
#                 rows_for_json.append(entry)
#                 new_or_changed += 1
#         # Always track latest
#         updated_dict[key] = lastmod

#     # Retain (write) only currently present objects; delete from outputs any files not present anymore
#     with open(json_file, "w", encoding="utf-8") as jf:
#         json.dump(rows_for_json, jf, indent=2)
#     with open(csv_file, "w", newline="", encoding="utf-8") as csvfile:
#         writer = csv.writer(csvfile)
#         writer.writerow(headers)
#         writer.writerows(rows_for_csv)
#     with open(state_file, "w") as f:
#         json.dump(updated_dict, f)

#     deleted = len({k for k in existing_entries if k not in curr_keys})
#     logmsg(f"[{bucket}] Delta scan: {new_or_changed} new/changed, {deleted} deleted. Final {len(rows_for_json)} files.")

#     # Call Script 4 after scan/completion for this bucket
#     launch_classifier_script(CONFIG_ID, S3_PATH)

# logmsg("SUCCESS: All bucket(s) traversal and export complete.", "INFO")
# print(json.dumps({"success": True}))