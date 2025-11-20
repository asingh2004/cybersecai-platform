##**Python Script 1:** Parallel, checkpoint-capable recursive listing, writes file metadata JSON/CSV.

import os
import sys
import json
import csv
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime
import boto3

# Laravel log file
LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"

def logmsg(msg, level="INFO"):
    timestr = datetime.utcnow().isoformat()
    out = f"[S3SCAN {timestr}] [{level}] {msg}"
    try:
        with open(LARAVEL_LOG, "a") as f:
            f.write(out + "\n")
    except Exception:
        pass

def log_exception(msg, exc):
    logmsg(f"{msg}: {repr(exc)}", "ERROR")

# === ARGS ===
if len(sys.argv) < 6:
    print("Usage: python3 1_list_s3files_parallel.py <AWS_ACCESS_KEY> <AWS_SECRET_KEY> <CONFIG_ID> <REGULATIONS_JSON> <AWS_REGION> [<S3_BUCKET>]")
    sys.exit(1)

AWS_ACCESS_KEY  = sys.argv[1]
AWS_SECRET_KEY  = sys.argv[2]
CONFIG_ID       = sys.argv[3]
RAW_REGULATIONS = sys.argv[4]
AWS_REGION      = sys.argv[5]
S3_BUCKET       = sys.argv[6] if len(sys.argv) > 6 else None

BASE_WEBHOOK_PATH = "/home/cybersecai/htdocs/www.cybersecai.io/webhook"
S3_PATH           = os.path.join(BASE_WEBHOOK_PATH, 'S3')

# === COMPLIANCE LOGIC ===
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

# === S3 CLIENT ===
try:
    s3 = boto3.client(
        "s3",
        region_name=AWS_REGION,
        aws_access_key_id=AWS_ACCESS_KEY,
        aws_secret_access_key=AWS_SECRET_KEY
    )
    logmsg(f"Initialized boto3 client for region {AWS_REGION}.")
    # List all buckets if none supplied
    if not S3_BUCKET:
        all_buckets = [bucket['Name'] for bucket in s3.list_buckets()['Buckets']]
        logmsg(f"Discovered buckets: {all_buckets}")
    else:
        all_buckets = [S3_BUCKET]
except Exception as e:
    log_exception("Failed to create boto3 S3 client or enumerate buckets", e)
    print(json.dumps({"success": False, "err": f"Could not connect to S3 or list buckets: {e}"}))
    sys.exit(1)

def list_all_s3_keys(bucketname, s3):
    logmsg(f"Listing all objects for bucket: {bucketname} ...")
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


CONFIG_FOLDER = os.path.join(S3_PATH, CONFIG_ID)
os.makedirs(CONFIG_FOLDER, exist_ok=True)

# Write a single compliance_matrix.json
matrix_path = os.path.join(CONFIG_FOLDER, "compliance_matrix.json")
with open(matrix_path, "w", encoding="utf-8") as mf:
    json.dump(compliance_matrix, mf, indent=2)
logmsg(f"Wrote compliance matrix: {matrix_path}")

# Write a single secrets file
secrets_file = os.path.join(CONFIG_FOLDER, f"{CONFIG_ID}_secrets.json")
with open(secrets_file, "w", encoding="utf-8") as f:
    json.dump({
        "AWS_ACCESS_KEY": AWS_ACCESS_KEY,
        "AWS_SECRET_KEY": AWS_SECRET_KEY,
        "AWS_REGION": AWS_REGION,
    }, f, indent=2)
logmsg(f"Wrote secrets file: {secrets_file}")



for bucket in all_buckets:
    logmsg(f"=== Scanning bucket: {bucket} ===")
    CSV_FOLDER = CONFIG_FOLDER   # For clarity in the rest of the loop.


    headers = ["key", "file_name", "file_type", "size_bytes", "last_modified", "full_path"]
    csv_file = os.path.join(CONFIG_FOLDER, f"{bucket}_s3_files_list_{CONFIG_ID}.csv")
    json_file = os.path.join(CONFIG_FOLDER, f"{bucket}_s3_files_list_{CONFIG_ID}.json")
    state_file = os.path.join(CONFIG_FOLDER, f"{bucket}_scan_state.json")
    rows_for_csv = []
    rows_for_json = []
    seen = set()

    if os.path.exists(state_file):
        try:
            with open(state_file, "r") as f:
                seen = set(json.load(f))
            logmsg(f"Loaded checkpoint: {len(seen)} objects scanned already for {bucket}.")
        except Exception as e:
            log_exception(f"Failed to load state for {bucket}", e)

    try:
        all_objects = list_all_s3_keys(bucket, s3)
        logmsg(f"Start parallel processing of {len(all_objects)} objects in {bucket} (max_workers=16)")
        with ThreadPoolExecutor(max_workers=16) as executor:
            futures = {executor.submit(process_key, obj): obj for obj in all_objects if obj['Key'] not in seen}
            for i, future in enumerate(as_completed(futures), 1):
                entry = future.result()
                if entry:
                    rows_for_csv.append([
                        entry['key'], entry['file_name'], entry['file_type'],
                        entry['size_bytes'], entry['last_modified'], entry['full_path']
                    ])
                    rows_for_json.append(entry)
                    seen.add(entry['key'])
                # Periodic checkpoint
                if i % 200 == 0:
                    with open(csv_file, "w", newline='', encoding="utf-8") as csvfile:
                        writer = csv.writer(csvfile)
                        writer.writerow(headers)
                        writer.writerows(rows_for_csv)
                    with open(json_file, "w", encoding="utf-8") as jf:
                        json.dump(rows_for_json, jf, indent=2)
                    with open(state_file, "w") as f:
                        json.dump(list(seen), f)
    except Exception as e:
        log_exception(f"S3 traversal/export failure for {bucket}", e)
        continue

    # Final write/sync for this bucket
    try:
        with open(csv_file, "w", newline='', encoding="utf-8") as csvfile:
            writer = csv.writer(csvfile)
            writer.writerow(headers)
            writer.writerows(rows_for_csv)
        with open(json_file, "w", encoding="utf-8") as jf:
            json.dump(rows_for_json, jf, indent=2)
        with open(state_file, "w") as f:
            json.dump(list(seen), f)
        logmsg(f"Completed writing {len(rows_for_csv)} for {bucket} to {csv_file} / {json_file}")
    except Exception as e:
        log_exception(f"Failed to write results for {bucket}", e)


logmsg("SUCCESS: All bucket(s) traversal and export complete.", "INFO")
print(json.dumps({"success": True}))