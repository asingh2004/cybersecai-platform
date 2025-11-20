import json
import csv
import requests
import time
import sys
import os
import logging
from datetime import datetime

##########################################################
# Logging: Python logger AND Laravel log file both
##########################################################
LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"

def logmsg(msg, level="INFO"):
    timestr = datetime.utcnow().isoformat()
    out = f"[PYCRAWL {timestr}] [{level}] {msg}"
    try:
        with open(LARAVEL_LOG, "a") as f:
            f.write(out + "\n")
    except Exception:
        pass
    getattr(logging, level.lower(), logging.info)(out)

logging.basicConfig(
    format="%(asctime)s %(levelname)s %(message)s",
    level=logging.INFO,
)

##########################################################
# Argument Handling (expect 6 args!)
##########################################################
if len(sys.argv) != 6:
    logmsg("Usage: python3 1_list_onedrivefiles.py <TENANT_ID> <CLIENT_ID> <CLIENT_SECRET> <CONFIG_ID> <REGULATIONS_JSON>", "ERROR")
    print(json.dumps({"success": False, "err": "Invalid arguments"}))
    sys.exit(1)

TENANT_ID = sys.argv[1]
CLIENT_ID = sys.argv[2]
CLIENT_SECRET = sys.argv[3]
CONFIG_ID = sys.argv[4]
RAW_REGULATIONS = sys.argv[5] # string (may be ignored by the file crawl process below)

BASE_WEBHOOK_PATH   = '/home/cybersecai/htdocs/www.cybersecai.io/webhook'
M365_PATH           = os.path.join(BASE_WEBHOOK_PATH, 'M365')
SUBSCRIPTIONS_FILE  = os.path.join(BASE_WEBHOOK_PATH, f"subscriptions_bulk_{CONFIG_ID}.json")
CSV_FOLDER          = os.path.join(M365_PATH, CONFIG_ID)
try:
    os.makedirs(CSV_FOLDER, exist_ok=True)
    logmsg(f"Ensured output directory exists: {CSV_FOLDER}")
except Exception as e:
    logmsg(f"Could not create output folder: {e}", "ERROR")
    print(json.dumps({"success": False, "err": f"Cannot create output folder {CSV_FOLDER}: {str(e)}"}))
    sys.exit(1)

# =========== Parse (and Write) compliance_matrix.json ===========
try:
    regulations = json.loads(RAW_REGULATIONS)
    logmsg(f"Received regulations JSON: {len(regulations)} items")
except Exception as e:
    logmsg(f"Could not parse regulations JSON: {e}", "ERROR")
    print(json.dumps({"success": False, "err": f"Invalid regulations JSON: {e}"}))
    sys.exit(1)

compliance_matrix = []
try:
    for reg in regulations:
        standard = reg.get('standard')
        jurisdiction = reg.get('jurisdiction')
        fields = []
        if 'fields' in reg and isinstance(reg['fields'], list):
            fields = reg['fields']
        else:
            for k, v in reg.items():
                if k.endswith('Risk fields') and isinstance(v, list):
                    fields.extend(v)
        compliance_matrix.append({
            "standard": standard,
            "jurisdiction": jurisdiction,
            "fields": sorted(list(set(fields)))
        })
    matrix_path = os.path.join(CSV_FOLDER, "compliance_matrix.json")
    with open(matrix_path, "w", encoding="utf-8") as mf:
        json.dump(compliance_matrix, mf, indent=2)
    logmsg(f"Wrote compliance matrix JSON: {matrix_path}")
except Exception as e:
    logmsg(f"Failed to write compliance_matrix.json: {e}", "ERROR")
    print(json.dumps({"success": False, "err": f"Could not write compliance_matrix.json: {e}"}))
    sys.exit(1)

##########################################################
# MS Graph Auth & Throttle Handler
##########################################################
def get_access_token():
    url = f"https://login.microsoftonline.com/{TENANT_ID}/oauth2/v2.0/token"
    data = {
        'grant_type': 'client_credentials',
        'client_id': CLIENT_ID,
        'client_secret': CLIENT_SECRET,
        'scope': 'https://graph.microsoft.com/.default'
    }
    try:
        resp = requests.post(url, data=data, timeout=15)
        resp.raise_for_status()
        access_token = resp.json()['access_token']
        logmsg("Obtained MS Graph access token successfully.")
        return access_token
    except Exception as e:
        logmsg(f"Failed to obtain access token: {e}", "ERROR")
        print(json.dumps({"success": False, "err": f"Access token error: {e}"}))
        sys.exit(2)

def graph_get_with_throttle(url, access_token, max_retries=8):
    headers = {"Authorization": f"Bearer {access_token}"}
    attempt = 0
    backoff = 2
    while attempt < max_retries:
        try:
            resp = requests.get(url, headers=headers, timeout=30)
        except Exception as e:
            logmsg(f"Network error retrieving {url}: {e}", "ERROR")
            time.sleep(backoff)
            attempt += 1
            continue
        if resp.status_code == 429:
            retry = int(resp.headers.get("Retry-After", 1))
            logmsg(f"Throttled GET {url}. Sleeping {retry}s...", "WARNING")
            time.sleep(retry)
            attempt += 1
            continue
        elif not resp.ok and resp.status_code in {500,502,503,504}:
            logmsg(f"Temp HTTP error {resp.status_code} at {url}: {resp.text}", "ERROR")
            time.sleep(backoff)
            attempt += 1
            continue
        return resp
    logmsg(f"Failed to retrieve {url} after {max_retries} attempts", "ERROR")
    return resp


def fetch_permissions(user_id, item_id, access_token):
    url = f"https://graph.microsoft.com/v1.0/users/{user_id}/drive/items/{item_id}/permissions"
    try:
        resp = graph_get_with_throttle(url, access_token)
        if resp and resp.ok:
            return resp.json().get('value', [])
    except Exception as e:
        logmsg(f"Failed to fetch permissions for file {item_id}: {e}", "WARNING")
    return []

def fetch_sp_permissions(site_id, drive_id, item_id, access_token):
    url = f"https://graph.microsoft.com/v1.0/sites/{site_id}/drives/{drive_id}/items/{item_id}/permissions"
    try:
        resp = graph_get_with_throttle(url, access_token)
        if resp and resp.ok:
            return resp.json().get('value', [])
    except Exception as e:
        logmsg(f"Could not fetch SP permissions for {item_id}: {e}", "WARNING")
    return []

# =========== OneDrive ===========

def crawl_onedrive(user_id, access_token):
    logmsg(f"Crawling OneDrive for user {user_id}")
    all_results = []
    def crawl_folder(folder_id, parent_path=""):
        if folder_id:
            url = f"https://graph.microsoft.com/v1.0/users/{user_id}/drive/items/{folder_id}/children"
        else:
            url = f"https://graph.microsoft.com/v1.0/users/{user_id}/drive/root/children"
        while url:
            resp = graph_get_with_throttle(url, access_token)
            if not resp or not resp.ok:
                logmsg(f"Failed to list [{url}]: {resp.status_code if resp else 'no resp'} {resp.text if resp else ''}", "ERROR")
                break
            data = resp.json()
            for item in data.get("value", []):
                this_path = (parent_path.rstrip("/") + "/" + item.get("name", ""))
                item["full_path"] = this_path

                if not item.get("folder"):
                    # Only for files (not folders)
                    if item.get("id"):
                        item["permissions"] = fetch_permissions(user_id, item["id"], access_token)

                all_results.append(item)
                if item.get("folder"):
                    crawl_folder(item["id"], this_path)
            url = data.get("@odata.nextLink")
    crawl_folder(None)
    logmsg(f"  {len(all_results)} files found for OneDrive user {user_id}")
    return all_results

# =========== SharePoint/Teams ===========

def crawl_sharepoint(site_id, drive_id, access_token):
    logmsg(f"Crawling SharePoint for site {site_id}, drive {drive_id}")
    all_results = []
    def crawl_folder(folder_id, parent_path=""):
        if folder_id:
            url = f"https://graph.microsoft.com/v1.0/sites/{site_id}/drives/{drive_id}/items/{folder_id}/children"
        else:
            url = f"https://graph.microsoft.com/v1.0/sites/{site_id}/drives/{drive_id}/root/children"
        while url:
            resp = graph_get_with_throttle(url, access_token)
            if not resp or not resp.ok:
                logmsg(f"Failed to list [{url}]: {resp.status_code if resp else 'no resp'} {resp.text if resp else ''}", "ERROR")
                break
            data = resp.json()
            for item in data.get("value", []):
                this_path = (parent_path.rstrip("/") + "/" + item.get("name", ""))
                item["full_path"] = this_path
                if not item.get("folder") and item.get("id"):
                    item["permissions"] = fetch_sp_permissions(site_id, drive_id, item["id"], access_token)
                all_results.append(item)
                if item.get("folder"):
                    crawl_folder(item["id"], this_path)
            url = data.get("@odata.nextLink")
    crawl_folder(None)
    logmsg(f"  {len(all_results)} files found for SharePoint drive {drive_id}")
    return all_results

##########################################################
# Main Process
##########################################################
def main():
    if not os.path.isfile(SUBSCRIPTIONS_FILE):
        logmsg(f"Subscriptions file does not exist: {SUBSCRIPTIONS_FILE}", "ERROR")
        print(json.dumps({"success": False, "err": f"Subscriptions file not found: {SUBSCRIPTIONS_FILE}"}))
        sys.exit(1)
    logmsg(f"Loading subscriptions from: {SUBSCRIPTIONS_FILE}")
    try:
        with open(SUBSCRIPTIONS_FILE) as f:
            subscriptions = json.load(f)
    except Exception as ex:
        logmsg(f"Could not read subscriptions file: {ex}", "ERROR")
        print(json.dumps({"success": False, "err": f"Could not read subscriptions file: {ex}"}))
        sys.exit(1)

    access_token = get_access_token()
    headers_row = [
        "user_id", "file_id", "file_name", "file_type", "size_bytes",
        "last_modified", "web_url", "download_url", "parent_reference", "full_path"
    ]
    headers_row_sp = [
        "site_id", "drive_id", "file_id", "file_name", "file_type",
        "size_bytes", "last_modified", "web_url", "download_url", 
        "parent_reference", "full_path"
    ]

    result_files = []
    for resource_path, sub in subscriptions.items():
        # OneDrive
        if resource_path.startswith("/users/") and "/drive/root" in resource_path:
            user_id = resource_path.strip("/").split("/")[1]
            csv_file = os.path.join(CSV_FOLDER, f"{user_id}_m365_files_list_{CONFIG_ID}.csv")
            json_file = os.path.join(CSV_FOLDER, f"{user_id}_m365_files_list_{CONFIG_ID}.json")
            files = crawl_onedrive(user_id, access_token)
            rows_for_csv = []
            rows_for_json = []
            for item in files:
                download_url = item.get("@microsoft.graph.downloadUrl", "")
                row = [
                    user_id, item.get('id'), item.get('name'),
                    item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    item.get('size', ''), item.get('lastModifiedDateTime', ''), item.get('webUrl', ''),
                    download_url, item.get("parentReference", {}).get("path", ""), item.get("full_path", "")
                ]
                rows_for_csv.append(row)
                rows_for_json.append({
                    "user_id": user_id,
                    "file_id": item.get('id'),
                    "file_name": item.get('name'),
                    "file_type": item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    "size_bytes": item.get('size', ''),
                    "last_modified": item.get('lastModifiedDateTime', ''),
                    "web_url": item.get('webUrl', ''),
                    "download_url": download_url,
                    "parent_reference": item.get("parentReference", {}).get("path", ""),
                    "full_path": item.get("full_path", ""),
                    "permissions": item.get("permissions", [])
                })
            try:
                with open(csv_file, "w", newline='', encoding="utf-8") as csvfile:
                    writer = csv.writer(csvfile)
                    writer.writerow(headers_row)
                    writer.writerows(rows_for_csv)
                with open(json_file, "w", encoding='utf-8') as jf:
                    json.dump(rows_for_json, jf, indent=2)
                logmsg(f"Wrote user CSV: {csv_file}")
                logmsg(f"Wrote user JSON: {json_file}")
            except Exception as ex:
                logmsg(f"Error writing files for user {user_id}: {ex}", "ERROR")
                continue
            result_files.append({'csv': csv_file, 'json': json_file, 'user_id': user_id})

        # SharePoint/Teams document libraries
        elif resource_path.startswith("/sites/") and "/drives/" in resource_path and resource_path.endswith("/root"):
            parts = resource_path.strip("/").split("/")
            site_id = parts[1]
            drive_id = parts[3]
            csv_file = os.path.join(CSV_FOLDER, f"{drive_id}_m365_files_list_{CONFIG_ID}.csv")
            json_file = os.path.join(CSV_FOLDER, f"{drive_id}_m365_files_list_{CONFIG_ID}.json")
            files = crawl_sharepoint(site_id, drive_id, access_token)
            rows_for_csv = []
            rows_for_json = []
            for item in files:
                download_url = item.get("@microsoft.graph.downloadUrl", "")
                row = [
                    site_id, drive_id, item.get('id'), item.get('name'),
                    item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    item.get('size', ''), item.get('lastModifiedDateTime', ''),
                    item.get('webUrl', ''), download_url, item.get("parentReference", {}).get("path", ""),
                    item.get("full_path", "")
                ]
                rows_for_csv.append(row)
                rows_for_json.append({
                    "site_id": site_id,
                    "drive_id": drive_id,
                    "file_id": item.get('id'),
                    "file_name": item.get('name'),
                    "file_type": item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    "size_bytes": item.get('size', ''),
                    "last_modified": item.get('lastModifiedDateTime', ''),
                    "web_url": item.get('webUrl', ''),
                    "download_url": download_url,
                    "parent_reference": item.get("parentReference", {}).get("path", ""),
                    "full_path": item.get("full_path", ""),
                    "permissions": item.get("permissions", [])
                })
            try:
                with open(csv_file, "w", newline='', encoding="utf-8") as csvfile:
                    writer = csv.writer(csvfile)
                    writer.writerow(headers_row_sp)
                    writer.writerows(rows_for_csv)
                with open(json_file, "w", encoding='utf-8') as jf:
                    json.dump(rows_for_json, jf, indent=2)
                logmsg(f"Wrote SharePoint CSV: {csv_file}")
                logmsg(f"Wrote SharePoint JSON: {json_file}")
            except Exception as ex:
                logmsg(f"Error writing files for site {site_id} drive {drive_id}: {ex}", "ERROR")
                continue
            result_files.append({'csv': csv_file, 'json': json_file, 'site_id': site_id, 'drive_id': drive_id})
        else:
            logmsg(f"Resource path [{resource_path}] is neither OneDrive nor SharePoint, skipping.", "WARNING")

    # Write secrets JSON
    secrets_data = {"TENANT_ID": TENANT_ID, "CLIENT_ID": CLIENT_ID, "CLIENT_SECRET": CLIENT_SECRET}
    secrets_file = os.path.join(CSV_FOLDER, f"{CONFIG_ID}_secrets.json")
    try:
        with open(secrets_file, "w", encoding="utf-8") as f:
            json.dump(secrets_data, f, indent=2)
        logmsg(f"Wrote secrets JSON: {secrets_file}")
    except Exception as ex:
        logmsg(f"Failed to write secrets JSON: {ex}", "ERROR")
        print(json.dumps({
            "success": False,
            "err": f"Could not write secrets file: {secrets_file} - {ex}"
        }))
        sys.exit(2)

    logmsg(f"All file exports (and secrets file) completed. Total: {len(result_files)} resources.")
    print(json.dumps({"success": True, "files": result_files, "secrets_file": secrets_file}))
    sys.exit(0)

if __name__ == "__main__":
    main()