# Resumes from per-user (or per-site) index of last-modifieds: Uses an index/index_{id}.json to store last-modified times per file/folder.
# Skips users/sites if no files changed in the last 24h (unless forced).
# Real-time logging + error handling, robust file writes.
# Pool multiprocessing: Uses all (by default) or max-provided worker processes.
# Commented and ready for scale.
# Robust per-user/site index so only new/changed files processed.
# Fully multiprocessing, scales to 10k users/sites.
# Detailed per-resource logging to Laravel and per-run logs.
# Easy to initiate from Laravel or cron.

##It can be tested like this###
###php artisan scan:m365files --max-workers=2

import os, sys, json, csv, requests, time, logging, traceback
from datetime import datetime, timedelta
from multiprocessing import Pool
import multiprocessing

LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"

def logmsg(msg, level="INFO"):
    timestr = datetime.utcnow().isoformat()
    line = f"[M365SCAN {timestr}] [{level}] {msg}"
    try:
        with open(LARAVEL_LOG, "a") as f:
            f.write(line + "\n")
    except Exception:
        pass
    getattr(logging, level.lower(), logging.info)(line)
logging.basicConfig(level=logging.INFO)

BASE_PATH       = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/M365"
INDEX_PATH      = lambda config_id: os.path.join(BASE_PATH, config_id, "index")
OUTPUT_PATH     = lambda config_id: os.path.join(BASE_PATH, config_id)

if len(sys.argv) < 8:
    print("Usage: python3 m365_scan.py <TENANT_ID> <CLIENT_ID> <CLIENT_SECRET> <CONFIG_ID> <SUBSCRIPTIONS_FILE> <REGULATIONS> [--workers=N]")
    sys.exit(1)

TENANT_ID = sys.argv[1]
CLIENT_ID = sys.argv[2]
CLIENT_SECRET = sys.argv[3]
CONFIG_ID = sys.argv[4]
SUBSCRIPTIONS_FILE = sys.argv[5]
RAW_REGULATIONS = sys.argv[6]
WORKERS = next((int(a.split("=")[1]) for a in sys.argv[7:] if a.startswith("--workers=")), multiprocessing.cpu_count())

os.makedirs(OUTPUT_PATH(CONFIG_ID), exist_ok=True)
os.makedirs(INDEX_PATH(CONFIG_ID), exist_ok=True)
CSV_FOLDER = OUTPUT_PATH(CONFIG_ID)

# First: Write compliance_matrix.json
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

# Next: Write secrets JSON
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

def get_access_token():
    url = f"https://login.microsoftonline.com/{TENANT_ID}/oauth2/v2.0/token"
    data = {'grant_type':'client_credentials','client_id':CLIENT_ID,'client_secret':CLIENT_SECRET,'scope':'https://graph.microsoft.com/.default'}
    resp = requests.post(url, data=data, timeout=20)
    resp.raise_for_status()
    return resp.json()['access_token']

def graph_get(url, token, retries=5):
    headers = {"Authorization": f"Bearer {token}"}
    last_err = None
    for _ in range(retries):
        try:
            r = requests.get(url, headers=headers, timeout=30)
            if r.status_code in (429, 500, 502, 503, 504):
                wait = int(r.headers.get("Retry-After", 1))
                time.sleep(wait)
                continue
            if r.ok: return r
            last_err = f"{r.status_code}: {r.text}"
        except Exception as e: last_err = str(e)
        time.sleep(2)
    raise Exception(f"graph_get failed: {last_err or r.status_code}")

def index_file_path(resource_id):
    safeid = resource_id.replace("/","_")
    return os.path.join(INDEX_PATH(CONFIG_ID), f"index_{safeid}.json")

def needs_scan(resource_id, files):
    """Compare current file list/LM dates to the old index. Return True if anything 'new or changed' in last 24h."""
    indexp = index_file_path(resource_id)
    if not os.path.exists(indexp): return True
    try:
        now24 = datetime.utcnow() - timedelta(days=1)
        with open(indexp) as f: oldidx = json.load(f)
        newdict = dict((f['id'], f['lastModifiedDateTime']) for f in files)
        for fid, lm in newdict.items():
            oldlm = oldidx.get(fid)
            if not oldlm: return True
            try:
                dt = datetime.fromisoformat(lm.replace("Z",""))
                olddt = datetime.fromisoformat(oldlm.replace("Z",""))
                if dt != olddt and dt > now24: return True
            except: return True
        return False  # No new/changed file in 24h
    except Exception as ex:
        logmsg(f"{resource_id}: index read error: {ex}", "ERROR")
        return True

def update_index(resource_id, files):
    """Write latest last-modified index per file."""
    fdict = dict((f['id'], f['lastModifiedDateTime']) for f in files if 'id' in f and 'lastModifiedDateTime' in f)
    with open(index_file_path(resource_id), "w") as f:
        json.dump(fdict, f, indent=2)

def crawl_onedrive(user_id, token):
    results = []
    def _crawl(fid, parent=""):
        u = f"https://graph.microsoft.com/v1.0/users/{user_id}/drive/items/{fid}/children" if fid else f"https://graph.microsoft.com/v1.0/users/{user_id}/drive/root/children"
        while u:
            r = graph_get(u, token); d = r.json()
            for itm in d.get("value", []):
                itm["full_path"] = parent.rstrip("/") + "/" + itm.get("name","")
                results.append(itm)
                if "folder" in itm: _crawl(itm["id"], itm["full_path"])
            u = d.get("@odata.nextLink")
    _crawl(None)
    return results

def crawl_sharepoint(site_id, drive_id, token):
    results = []
    def _crawl(fid, parent=""):
        u = f"https://graph.microsoft.com/v1.0/sites/{site_id}/drives/{drive_id}/items/{fid}/children" if fid else f"https://graph.microsoft.com/v1.0/sites/{site_id}/drives/{drive_id}/root/children"
        while u:
            r = graph_get(u, token); d = r.json()
            for itm in d.get("value", []):
                itm["full_path"] = parent.rstrip("/")+"/"+itm.get("name","")
                results.append(itm)
                if "folder" in itm: _crawl(itm["id"], itm["full_path"])
            u = d.get("@odata.nextLink")
    _crawl(None)
    return results

def process_resource(args):
    resource_path, sub, token, outp, idxp, config_id = args
    try:
        if resource_path.startswith("/users/") and "/drive/root" in resource_path:
            user_id = resource_path.strip("/").split("/")[1]
            files = crawl_onedrive(user_id, token)
            if not needs_scan(user_id, files):
                logmsg(f"[{user_id}] No changes in files in 24hr, skipping.")
                return f"{user_id}: SKIP"
            update_index(user_id, files)
            csv_file = os.path.join(outp, f"{user_id}_m365_files_list_{config_id}.csv")
            json_file = os.path.join(outp, f"{user_id}_m365_files_list_{config_id}.json")
            rows = []
            for item in files:
                download_url = item.get("@microsoft.graph.downloadUrl", "")
                rows.append([
                    user_id, item.get('id'), item.get('name'),
                    item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    item.get('size', ''), item.get('lastModifiedDateTime', ''), item.get('webUrl', ''),
                    download_url, item.get("parentReference", {}).get("path", ""), item.get("full_path", "")
                ])
            with open(csv_file, "w", newline="", encoding="utf-8") as f:
                writer = csv.writer(f); writer.writerow([
                    "user_id", "file_id", "file_name", "file_type", "size_bytes", "last_modified",
                    "web_url", "download_url", "parent_reference", "full_path"
                ]); writer.writerows(rows)
            with open(json_file, "w", encoding="utf-8") as jf:
                json.dump([dict(
                    user_id=user_id,
                    file_id=item.get("id"),file_name=item.get("name"),
                    file_type=item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    size_bytes=item.get('size', ''), last_modified=item.get('lastModifiedDateTime', ''),
                    web_url=item.get('webUrl', ''),download_url=download_url,
                    parent_reference=item.get("parentReference", {}).get("path", ""),full_path=item.get("full_path", "")
                ) for item in files], jf, indent=2)
            logmsg(f"[{user_id}] OneDrive exported {len(files)} files.")
            return f"{user_id}: DONE"
        elif resource_path.startswith("/sites/") and "/drives/" in resource_path and resource_path.endswith("/root"):
            parts = resource_path.strip("/").split("/")
            site_id, drive_id = parts[1], parts[3]
            files = crawl_sharepoint(site_id, drive_id, token)
            drive_idx = f"{site_id}_{drive_id}"
            if not needs_scan(drive_idx, files):
                logmsg(f"[{site_id}:{drive_id}] No changes in files in 24hr, skipping.")
                return f"{drive_id}: SKIP"
            update_index(drive_idx, files)
            csv_file = os.path.join(outp, f"{drive_id}_m365_files_list_{config_id}.csv")
            json_file = os.path.join(outp, f"{drive_id}_m365_files_list_{config_id}.json")
            rows = []
            for item in files:
                download_url = item.get("@microsoft.graph.downloadUrl", "")
                rows.append([
                    site_id, drive_id, item.get('id'), item.get('name'),
                    item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    item.get('size', ''), item.get('lastModifiedDateTime', ''), item.get('webUrl', ''),
                    download_url, item.get("parentReference", {}).get("path", ""), item.get("full_path", "")
                ])
            with open(csv_file, "w", newline="", encoding="utf-8") as f:
                writer = csv.writer(f); writer.writerow([
                    "site_id", "drive_id", "file_id", "file_name", "file_type", "size_bytes", "last_modified",
                    "web_url", "download_url", "parent_reference", "full_path"
                ]); writer.writerows(rows)
            with open(json_file, "w", encoding="utf-8") as jf:
                json.dump([dict(
                    site_id=site_id, drive_id=drive_id,
                    file_id=item.get("id"),file_name=item.get("name"),
                    file_type=item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    size_bytes=item.get('size', ''), last_modified=item.get('lastModifiedDateTime', ''),
                    web_url=item.get('webUrl', ''),download_url=download_url,
                    parent_reference=item.get("parentReference", {}).get("path", ""),full_path=item.get("full_path", "")
                ) for item in files], jf, indent=2)
            logmsg(f"[{site_id}:{drive_id}] SharePoint exported {len(files)} files.")
            return f"{drive_id}: DONE"
        else:
            logmsg(f"Resource path [{resource_path}] is neither OneDrive nor SharePoint, skipping.", "WARNING")
            return f"{resource_path}: SKIP"
    except Exception as ex:
        logmsg(f"Error processing {resource_path}: {ex}\n{traceback.format_exc()}", "ERROR")
        return f"{resource_path}: ERROR"

def main():
    with open(SUBSCRIPTIONS_FILE) as f: subscriptions = json.load(f)
    token = get_access_token()
    argslist = [(resource_path, sub, token, OUTPUT_PATH(CONFIG_ID), INDEX_PATH(CONFIG_ID), CONFIG_ID)
               for resource_path, sub in subscriptions.items()]
    with Pool(processes=WORKERS) as pool:
        results = pool.map(process_resource, argslist)
        for res in results:
            logmsg("Resource result: " + str(res))
    logmsg("Scan completed: processed resources.")
    print(json.dumps({"success": True, "compliance_matrix": matrix_path, "secrets_file": secrets_file}))

if __name__ == "__main__":
    main()