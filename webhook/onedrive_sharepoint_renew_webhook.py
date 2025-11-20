# Below is a production-grade Python script that implements all four requested features for managing Microsoft Graph webhook subscriptions for all users’ OneDrives and all SharePoint sites:

# Custom error handling: Exceptions provide meaningful output/logging, and failed attempts are summarized.
# Parallel/batched Graph calls: Uses concurrent.futures.ThreadPoolExecutor for concurrent subscription renewals/creations.
# Exclude certain users/sites: Skip user IDs, emails, and/or site IDs/domains you configure.
# Automated removal of old subscriptions: Finds and deletes subscriptions for resources that no longer exist (or are now in the exclusion lists).

# Microsoft’s Guidance
# Monitoring file changes across an entire Microsoft 365 tenant requires programmatically creating and managing a subscription for each desired user and every site/library drive you wish to monitor. The Graph API does not support a wildcard or global resource for subscriptions.

# References:

# Graph Subscription Supported Resources
# Monitoring Changes in Microsoft Graph
# For 10,000 users, you’d need 10,000 subscriptions, subject to API limits (~2000 per app per tenant—see docs).
# Enumerate users: Directory.Read.All or User.Read.All (application) to GET /users.
# OneDrive subscriptions (/users/{id}/drive/root): Files.Read.All or Files.ReadWrite.All (application).
# Enumerate sites: Sites.Read.All (application) to search/list broadly. With Sites.Selected you must pre-specify site IDs and grant them to the app; you usually can’t search all sites.
# Enumerate drives for a site and subscribe: Sites.Read.All or Sites.Selected with per-site grants; plus Files.Read.All/Files.ReadWrite.All is typically required for drive/root subscriptions.



#!/usr/bin/env python3
import json
import os
import sys
from datetime import datetime, timedelta
from concurrent.futures import ThreadPoolExecutor, as_completed

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

# ---------------- Paths and logging ----------------
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
OUTPUT_DIR = os.environ.get("M365_SUBS_OUTPUT_DIR", BASE_DIR)
LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"

def write_log(message):
    ts = datetime.utcnow().isoformat()
    line = f"[PYHOOK {ts}] {message}"
    try:
        with open(LARAVEL_LOG, "a") as f:
            f.write(line + "\n")
    except Exception:
        pass
    try:
        sys.stderr.write(line + "\n")
        sys.stderr.flush()
    except Exception:
        pass

def ensure_output_dir():
    try:
        os.makedirs(OUTPUT_DIR, exist_ok=True)
    except Exception as e:
        write_log(f"Failed to ensure output dir {OUTPUT_DIR}: {e}")

def save_subs_file(config_id, payload):
    ensure_output_dir()
    path = os.path.join(OUTPUT_DIR, f"subscriptions_bulk_{config_id}.json")
    try:
        with open(path, "w") as f:
            json.dump(payload, f, indent=2)
        write_log(f"Saved subscriptions file: {path}")
        return path
    except Exception as e:
        write_log(f"Failed to write {path}: {e}")
        return None

# ---------------- HTTP / Graph helpers ----------------
def make_session():
    try:
        retry = Retry(
            total=7,
            backoff_factor=1.5,
            status_forcelist=[429, 500, 502, 503, 504],
            allowed_methods=["HEAD", "GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
            respect_retry_after_header=True,
        )
    except TypeError:
        retry = Retry(
            total=7,
            backoff_factor=1.5,
            status_forcelist=[429, 500, 502, 503, 504],
            method_whitelist=["HEAD", "GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
            respect_retry_after_header=True,
        )
    adapter = HTTPAdapter(max_retries=retry, pool_connections=200, pool_maxsize=200)
    s = requests.Session()
    s.mount("https://", adapter)
    s.mount("http://", adapter)
    s.headers.update({"User-Agent": "CSAIBulkSubs/1.3"})
    return s

def is_expiring(expiration):
    try:
        exp = datetime.strptime(expiration, "%Y-%m-%dT%H:%M:%SZ")
        return (exp - datetime.utcnow()).total_seconds() < 86400
    except Exception:
        return True

def new_expiry():
    return (datetime.utcnow() + timedelta(minutes=4230)).strftime("%Y-%m-%dT%H:%M:%SZ")

def get_access_token(session, tenant_id, client_id, client_secret):
    data = {
        "grant_type": "client_credentials",
        "client_id": client_id,
        "client_secret": client_secret,
        "scope": "https://graph.microsoft.com/.default",
    }
    url = f"https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token"
    write_log(f"Token request for client_id={client_id[:6]}... tenant={tenant_id}")
    resp = session.post(url, data=data, timeout=60)
    if resp.status_code != 200:
        write_log(f"Token Error: {resp.status_code} {resp.text}")
        resp.raise_for_status()
    return resp.json()["access_token"]

def graph_paged_get(session, url, token, object_type="generic"):
    headers = {"Authorization": f"Bearer {token}"}
    if "search=" in url:
        headers["ConsistencyLevel"] = "eventual"
    items = []
    page = 1
    while url:
        write_log(f"GET {object_type.upper()} PAGE {page}: {url}")
        r = session.get(url, headers=headers, timeout=120)
        if not r.ok:
            write_log(f"Graph API error ({object_type}) [{url}]: {r.status_code} {r.text}")
            break
        data = r.json()
        vals = data.get("value", [])
        write_log(f"  - Got {len(vals)} {object_type}(s) on page {page}")
        items.extend(vals)
        url = data.get("@odata.nextLink")
        page += 1
    write_log(f"Total {object_type}: {len(items)}")
    return items

def get_graph_list(session, endpoint, token, object_type="generic"):
    base = f"https://graph.microsoft.com/v1.0/{endpoint}"
    return graph_paged_get(session, base, token, object_type=object_type)

def get_existing_subscriptions(session, token):
    headers = {"Authorization": f"Bearer {token}"}
    url = "https://graph.microsoft.com/v1.0/subscriptions"
    items = []
    page = 1
    while url:
        write_log(f"GET SUBSCRIPTIONS PAGE {page}: {url}")
        r = session.get(url, headers=headers, timeout=120)
        if not r.ok:
            write_log(f"Error getting subscriptions: {r.status_code} {r.text}")
            break
        data = r.json()
        vals = data.get("value", [])
        items.extend(vals)
        url = data.get("@odata.nextLink")
        page += 1
    write_log(f"Existing subscriptions for this app: {len(items)}")
    return items

def delete_subscription(session, headers, sub_id):
    try:
        url = f"https://graph.microsoft.com/v1.0/subscriptions/{sub_id}"
        write_log(f"DELETE {url}")
        resp = session.delete(url, headers=headers, timeout=60)
        if resp.status_code in (200, 204):
            write_log(f"Deleted subscription {sub_id}")
            return True
        write_log(f"FAILED TO DELETE {sub_id}: {resp.status_code} {resp.text}")
    except Exception as e:
        write_log(f"EXCEPTION deleting subscription {sub_id}: {e}")
    return False

def subscription_resource_user(user):
    return f"/users/{user['id']}/drive/root"

def get_drives_for_site(session, site_id, token):
    drives = []
    headers = {"Authorization": f"Bearer {token}"}
    url = f"https://graph.microsoft.com/v1.0/sites/{site_id}/drives"
    page = 1
    while url:
        write_log(f"GET SITE DRIVES PAGE {page}: {url}")
        r = session.get(url, headers=headers, timeout=120)
        if not r.ok:
            write_log(f"Could not get drives for site {site_id}: {r.status_code} {r.text}")
            break
        data = r.json()
        vals = data.get("value", [])
        drives.extend(vals)
        url = data.get("@odata.nextLink")
        page += 1
    return drives

def subscription_resource_site_drive(site_id, drive_id):
    return f"/sites/{site_id}/drives/{drive_id}/root"

def register_or_renew(session, headers, resource, webhook_url, client_state, existing_sub):
    exp = new_expiry()
    try:
        if existing_sub:
            existing_state = existing_sub.get("clientState")
            if not is_expiring(existing_sub.get("expirationDateTime", "")) and existing_state == client_state:
                return {"resource": resource, "status": "already_valid", "details": existing_sub}

            if existing_state == client_state:
                renew_url = f"https://graph.microsoft.com/v1.0/subscriptions/{existing_sub['id']}"
                resp = session.patch(renew_url, headers=headers, json={"expirationDateTime": exp}, timeout=60)
                if resp.status_code == 200:
                    return {"resource": resource, "status": "renewed", "details": resp.json()}
                else:
                    write_log(f"FAILED PATCH for {resource}: {resp.status_code} {resp.text}. Will try delete+create.")
                    delete_subscription(session, headers, existing_sub["id"])
            else:
                delete_subscription(session, headers, existing_sub["id"])

        payload = {
            "changeType": "updated",
            "notificationUrl": webhook_url,
            "resource": resource,
            "expirationDateTime": exp,
            "clientState": client_state,
        }
        resp = session.post("https://graph.microsoft.com/v1.0/subscriptions", headers=headers, json=payload, timeout=120)
        if resp.status_code == 201:
            return {"resource": resource, "status": "created", "details": resp.json()}
        else:
            write_log(f"FAILED CREATE for {resource}: {resp.status_code} {resp.text}")
            return {"resource": resource, "status": "error", "details": resp.text}
    except Exception as e:
        write_log(f"EXCEPTION register_or_renew for {resource}: {e}")
        return {"resource": resource, "status": "error", "details": str(e)}

# ---------------- Enumeration ----------------
def enumerate_resources(session, base_token, excluded_users, excluded_sites, drives_workers=8):
    users = get_graph_list(session, "users?$select=id,userPrincipalName", base_token, object_type="user")
    exu = set(excluded_users or [])
    filtered_users = [u for u in users if u.get("id") not in exu and u.get("userPrincipalName") not in exu]
    user_resources = [subscription_resource_user(u) for u in filtered_users]

    sites = get_graph_list(session, "sites?search=*", base_token, object_type="site")
    exs = set(excluded_sites or [])
    filtered_sites = []
    for s in sites:
        sid = s.get("id")
        host = ""
        try:
            host = (s.get("webUrl") or "").split("/")[2]
        except Exception:
            pass
        if sid in exs or host in exs:
            continue
        filtered_sites.append(s)

    site_drive_resources = []
    if filtered_sites:
        workers = min(max(2, int(drives_workers)), max(2, len(filtered_sites)))
        with ThreadPoolExecutor(max_workers=workers) as ex:
            futs = {ex.submit(get_drives_for_site, session, s.get("id"), base_token): s.get("id") for s in filtered_sites}
            for fut in as_completed(futs):
                sid = futs[fut]
                try:
                    drives = fut.result()
                    for d in drives or []:
                        did = d.get("id")
                        if sid and did:
                            site_drive_resources.append(subscription_resource_site_drive(sid, did))
                except Exception as e:
                    write_log(f"ERROR enumerating drives for site {sid}: {e}")

    seen = set()
    all_resources = []
    for r in user_resources:
        if r not in seen:
            seen.add(r)
            all_resources.append(r)
    for r in site_drive_resources:
        if r not in seen:
            seen.add(r)
            all_resources.append(r)

    return filtered_users, filtered_sites, all_resources

# ---------------- Per-credential processing ----------------
def process_credential(session, cred, assigned_resources, webhook_url, client_state, batch_size, perform_stale_delete):
    stats = {"created": 0, "renewed": 0, "already_valid": 0, "errors": 0, "deleted_stale": 0}
    results = []
    try:
        token = get_access_token(session, cred["tenant_id"], cred["client_id"], cred["client_secret"])
    except Exception as e:
        write_log(f"[CRED {cred.get('client_id','')[:6]}] Token error: {e}")
        return {"ok": False, "stats": stats, "error": str(e), "assigned_count": len(assigned_resources), "results": results}

    headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}

    existing = get_existing_subscriptions(session, token)
    existing_map = {sub.get("resource"): sub for sub in existing if sub.get("resource")}

    if assigned_resources:
        with ThreadPoolExecutor(max_workers=max(1, int(batch_size))) as executor:
            futs = [executor.submit(register_or_renew, session, headers, r, webhook_url, client_state, existing_map.get(r)) for r in assigned_resources]
            for fut in as_completed(futs):
                try:
                    r = fut.result()
                    results.append(r)
                    status = r.get("status")
                    if status == "created":
                        stats["created"] += 1
                    elif status == "renewed":
                        stats["renewed"] += 1
                    elif status == "already_valid":
                        stats["already_valid"] += 1
                    else:
                        stats["errors"] += 1
                except Exception as ex:
                    results.append({"resource": None, "status": "error", "details": str(ex)})
                    stats["errors"] += 1

    if perform_stale_delete:
        assigned_set = set(assigned_resources)
        stale = [sub for sub in existing if sub.get("resource") not in assigned_set]
        write_log(f"[CRED {cred.get('client_id','')[:6]}] Stale subscriptions to delete: {len(stale)}")
        for sub in stale:
            if delete_subscription(session, headers, sub["id"]):
                stats["deleted_stale"] += 1

    return {"ok": True, "stats": stats, "assigned_count": len(assigned_resources), "results": results}

# ---------------- Group orchestration (writes JSON files) ----------------
def orchestrate_group(session, group, global_batch_size=None, global_max_per_credential=None):
    tenant_id = group["tenant_id"]
    webhook_url = group["webhook_url"]
    client_state = group["client_state"]
    credentials = group.get("credentials") or []
    excluded_users = group.get("excluded_users", [])
    excluded_sites = group.get("excluded_sites", [])
    config_ids = group.get("config_ids", []) or []  # important for per-config files
    batch_size = int(group.get("batch_size") or (global_batch_size or 10))
    max_per_cred = int(group.get("max_per_credential") or (global_max_per_credential or 1500))
    auto_delete_stale = bool(group.get("auto_delete_stale", True))

    creds = [c for c in credentials if c.get("tenant_id") == tenant_id]
    if not creds:
        return {"ok": False, "tenant_id": tenant_id, "error": "No valid credentials for tenant", "subs_files": []}

    base_cred = creds[0]
    try:
        base_token = get_access_token(session, base_cred["tenant_id"], base_cred["client_id"], base_cred["client_secret"])
    except Exception as e:
        write_log(f"[GROUP {tenant_id}] Token error for enumeration: {e}")
        return {"ok": False, "tenant_id": tenant_id, "error": str(e), "subs_files": []}

    write_log(f"[GROUP {tenant_id}] Enumerating users and sites...")
    users, sites, all_resources = enumerate_resources(session, base_token, excluded_users, excluded_sites, drives_workers=8)

    total_resources = len(all_resources)
    ncreds = len(creds)
    capacity = ncreds * max_per_cred
    overflow = 0
    assigned_resources = all_resources
    cover_all = True
    if total_resources > capacity:
        cover_all = False
        overflow = total_resources - capacity
        assigned_resources = all_resources[:capacity]
        write_log(f"[GROUP {tenant_id}] Not enough capacity: total={total_resources} capacity={capacity} overflow={overflow}. Skipping stale deletion for safety.")

    # Assign resources per credential
    assignments = []
    start = 0
    for i in range(ncreds):
        end = min(start + max_per_cred, len(assigned_resources))
        chunk = assigned_resources[start:end]
        assignments.append((creds[i], chunk))
        start = end

    created = renewed = valid = errors = deleted = 0
    all_results = []
    subs_files = []

    max_workers = min(ncreds, 8)
    with ThreadPoolExecutor(max_workers=max_workers) as ex:
        futs = []
        for (cred, chunk) in assignments:
            futs.append(ex.submit(
                process_credential,
                session,
                cred,
                chunk,
                webhook_url,
                client_state,
                batch_size,
                auto_delete_stale and cover_all
            ))
        for fut in as_completed(futs):
            res = fut.result()
            st = res.get("stats", {})
            all_results.extend(res.get("results", []))
            created += st.get("created", 0)
            renewed += st.get("renewed", 0)
            valid += st.get("already_valid", 0)
            errors += st.get("errors", 0)
            deleted += st.get("deleted_stale", 0)

    # Build a resource-indexed map for output
    by_resource = {}
    for r in all_results:
        resource = r.get("resource")
        if not resource:
            continue
        by_resource[resource] = {
            "status": r.get("status"),
            "details": r.get("details"),
        }

    # Write one file per DataConfig id (identical content for each config in this group)
    file_payload = {
        "tenant_id": tenant_id,
        "webhook_url": webhook_url,
        "client_state": client_state,
        "generated_at": datetime.utcnow().isoformat() + "Z",
        "summary": {
            "created": created,
            "renewed": renewed,
            "already_valid": valid,
            "errors": errors,
            "deleted_stale": deleted if cover_all and auto_delete_stale else 0,
            "overflow_resources": overflow,
            "max_per_credential": max_per_cred,
            "capacity": capacity,
            "users_count": len(users),
            "sites_count": len(sites),
            "credentials_count": ncreds,
        },
        "results_by_resource": by_resource,
    }

    if not config_ids:
        write_log(f"[GROUP {tenant_id}] No config_ids present; skipping file write.")
    else:
        for cid in config_ids:
            p = save_subs_file(cid, file_payload)
            if p:
                subs_files.append(p)

    return {
        "ok": errors == 0,
        "tenant_id": tenant_id,
        "webhook_url": webhook_url,
        "client_state": client_state,
        "num_credentials": ncreds,
        "users_count": len(users),
        "sites_count": len(sites),
        "created": created,
        "renewed": renewed,
        "already_valid": valid,
        "errors": errors,
        "deleted_stale": deleted if cover_all and auto_delete_stale else 0,
        "overflow_resources": overflow,
        "max_per_credential": max_per_cred,
        "capacity": capacity,
        "subs_files": subs_files,
    }

# ---------------- Entry points ----------------
def run_bulk():
    raw = sys.stdin.read()
    try:
        payload = json.loads(raw)
    except Exception as e:
        write_log(f"Invalid JSON on STDIN: {e}")
        return {"ok": False, "error": "Invalid JSON payload on STDIN"}

    groups = payload.get("groups", [])
    global_batch_size = payload.get("batch_size", 10)
    global_max_per_credential = payload.get("max_per_credential", 1500)
    if not groups:
        return {"ok": False, "error": "No groups provided"}

    session = make_session()
    group_results = []
    total_errors = 0

    for g in groups:
        if "batch_size" not in g and global_batch_size:
            g["batch_size"] = global_batch_size
        if "max_per_credential" not in g and global_max_per_credential:
            g["max_per_credential"] = global_max_per_credential

        gr = orchestrate_group(session, g, global_batch_size=global_batch_size, global_max_per_credential=global_max_per_credential)
        group_results.append(gr)
        total_errors += gr.get("errors", 0)
        if not gr.get("ok", False):
            total_errors += 1

    return {"ok": all(gr.get("ok", False) for gr in group_results), "error_count": total_errors, "group_results": group_results}

def run_legacy_single():
    if len(sys.argv) != 7:
        write_log("Usage: python3 onedrive_sharepoint_renew_webhook.py --bulk (read JSON from STDIN) OR legacy 6-arg mode.")
        return {"ok": False, "error": "Invalid arguments"}

    tenant_id, client_id, client_secret, webhook_url, client_state, cfg_id = sys.argv[1:]
    group = {
        "tenant_id": tenant_id,
        "webhook_url": webhook_url,
        "client_state": client_state,
        "credentials": [{"tenant_id": tenant_id, "client_id": client_id, "client_secret": client_secret}],
        "excluded_users": [],
        "excluded_sites": [],
        "batch_size": 10,
        "auto_delete_stale": True,
        "max_per_credential": 1500,
        "config_ids": [cfg_id],
    }
    session = make_session()
    gr = orchestrate_group(session, group, global_batch_size=10, global_max_per_credential=1500)
    return {"ok": gr.get("ok", False), "group_results": [gr], "error_count": gr.get("errors", 0)}

if __name__ == "__main__":
    try:
        if len(sys.argv) >= 2 and sys.argv[1] == "--bulk":
            result = run_bulk()
        else:
            result = run_legacy_single()
    except Exception as e:
        write_log(f"FATAL: {e}")
        result = {"ok": False, "error": str(e)}
    sys.stdout.write(json.dumps(result))
    sys.stdout.flush()