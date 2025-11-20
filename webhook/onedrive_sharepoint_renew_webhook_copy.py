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

import json
import requests
import os
import sys
from datetime import datetime, timedelta
from concurrent.futures import ThreadPoolExecutor, as_completed

# --- Configuration for logging ---
LARAVEL_LOG = "/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log"  # Update as needed

def write_log(message):
    """Log message to Laravel log and stdout."""
    timestamp = datetime.utcnow().isoformat()
    full_msg = f"[PYHOOK {timestamp}] {message}\n"
    print(full_msg.strip())
    try:
        with open(LARAVEL_LOG, "a") as f:
            f.write(full_msg)
    except Exception as e:
        print(f"Logging failed: {e}")

# --- Args from Laravel/CLI ---
if len(sys.argv) != 7:
    write_log("Usage error: python renew_or_register_all.py <tenant_id> <client_id> <client_secret> <webhook_url> <webhook_client_state> <data_config_id>")
    sys.exit(1)

tenant_id, client_id, client_secret, webhook_url, client_state, config_id = sys.argv[1:]
SUBS_FILE = f"subscriptions_bulk_{config_id}.json"
BATCH_SIZE = 10

write_log(f"Start call with args: tenant_id={tenant_id}, client_id={client_id}, client_secret=***REDACTED***, webhook_url={webhook_url}, client_state={client_state}, config_id={config_id}")

# --- Exclusion lists for targeted filtering ---
EXCLUDED_USERS = []  # Add user IDs or emails to exclude here
EXCLUDED_SITES = []  # Add site IDs or domains to exclude here

def get_access_token():
    """Obtain OAuth token from Microsoft Graph using client credentials; log all failures."""
    data = {
        'grant_type': 'client_credentials',
        'client_id': client_id,
        'client_secret': client_secret,
        'scope': 'https://graph.microsoft.com/.default'
    }
    url = f"https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token"
    write_log(f"Requesting access token: POST {url} with data { {k:(v if k!='client_secret' else '***REDACTED***') for k,v in data.items()} }")
    resp = requests.post(url, data=data)
    if resp.status_code != 200:
        write_log(f"Token Error: {resp.status_code} {resp.text}")
        resp.raise_for_status()
    return resp.json()['access_token']

def is_expiring(expiration):
    """Returns True if subscription expires within 24 hours. Log conversion failures."""
    try:
        exp = datetime.strptime(expiration, "%Y-%m-%dT%H:%M:%SZ")
        return (exp - datetime.utcnow()).total_seconds() < 86400
    except Exception as e:
        write_log(f"Bad expiration format '{expiration}' ({e})")
        return True

def new_expiry():
    """Maximum expiry time for Graph subscriptions (2-3 days, time zone UTC, ISO8601)."""
    max_minutes = 4230  # As per Graph documentation (max for drive/root resources)
    return (datetime.utcnow() + timedelta(minutes=max_minutes)).strftime("%Y-%m-%dT%H:%M:%SZ")

def get_graph_list(endpoint, token, object_type="generic"):
    """GET all results for paged Graph API endpoint. Log every step & page."""
    headers = {"Authorization": f"Bearer {token}"}
    url = f"https://graph.microsoft.com/v1.0/{endpoint}"
    items = []
    page = 1
    while url:
        write_log(f"GET {object_type.upper()} PAGE {page}: {url}")
        r = requests.get(url, headers=headers)
        if not r.ok:
            write_log(f"Graph API error ({object_type}): {r.status_code} - {r.text}")
            break
        try:
            data = r.json()
        except Exception as e:
            write_log(f"JSON parse error on {object_type} from {url}: {e}")
            break
        page_items = data.get('value', [])
        write_log(f"  - Got {len(page_items)} {object_type}(s) on page {page}")
        items.extend(page_items)
        url = data.get('@odata.nextLink')
        page += 1
    write_log(f"Total {object_type}: {len(items)}")
    if not items:
        write_log(f"WARNING: No {object_type}s found via Graph (may be a permission/scope issue)")
    return items

def get_existing_subscriptions(token):
    """Get all existing subscriptions for this app in tenant. Log failures."""
    url = "https://graph.microsoft.com/v1.0/subscriptions"
    headers = {"Authorization": f"Bearer {token}"}
    write_log(f"GET EXISTING SUBSCRIPTIONS: {url}")
    try:
        resp = requests.get(url, headers=headers)
        resp.raise_for_status()
        data = resp.json()
        write_log(f"  - Existing count: {len(data.get('value',[]))}")
        return data.get('value', [])
    except Exception as e:
        write_log(f"Error getting subscriptions: {e}")
        return []

def save_subscriptions_bulk(subs):
    """Save all subscription results to a JSON file keyed by config_id."""
    try:
        with open(SUBS_FILE, 'w') as f:
            json.dump(subs, f, indent=2)
        write_log(f"Wrote all subscription records to {SUBS_FILE}")
    except Exception as e:
        write_log(f"Failed to write subscriptions file {SUBS_FILE}: {e}")

def load_subscriptions_bulk():
    if os.path.exists(SUBS_FILE):
        with open(SUBS_FILE) as f:
            return json.load(f)
    return {}

def user_excluded(user):
    """Return True if userPrincipalName or user ID is excluded; log exclusions."""
    if user['userPrincipalName'] in EXCLUDED_USERS or user['id'] in EXCLUDED_USERS:
        write_log(f"EXCLUDED USER: {user}")
        return True
    return False

def site_excluded(site):
    """Return True if site ID or webUrl's hostname is excluded; log exclusions."""
    host = ""
    try:
        host = site.get('webUrl', '').split('/')[2]
    except Exception:
        pass
    if site['id'] in EXCLUDED_SITES or host in EXCLUDED_SITES:
        write_log(f"EXCLUDED SITE: {site} (host: {host})")
        return True
    return False

def subscription_resource_user(user):
    """Return Graph resource path for a user’s OneDrive."""
    return f"/users/{user['id']}/drive/root"

def get_drives_for_site(site_id, token):
    """Enumerate ALL drives (doc libs) for a site. Highly logged."""
    drives = []
    headers = {"Authorization": f"Bearer {token}"}
    url = f"https://graph.microsoft.com/v1.0/sites/{site_id}/drives"
    page = 1
    while url:
        write_log(f"GET SITE DRIVES PAGE {page}: {url}")
        r = requests.get(url, headers=headers)
        if not r.ok:
            write_log(f"Could not get drives for site {site_id}: {r.status_code} {r.text}")
            break
        data = r.json()
        these_drives = data.get('value', [])
        drives.extend(these_drives)
        write_log(f"  - Got {len(these_drives)} drives on page {page} (cumulative: {len(drives)})")
        url = data.get('@odata.nextLink')
        page += 1
    if not drives:
        write_log(f"WARNING: No drives for site {site_id}")
    return drives

def subscription_resource_site_drive(site_id, drive_id):
    """Return Graph resource path for a document library drive in a site."""
    return f"/sites/{site_id}/drives/{drive_id}/root"

def register_or_renew(resource, headers, webhook_url, client_state, existing_sub=None):
    """Register or renew a Graph subscription for a resource (user or site)."""
    exp = new_expiry()
    error_details = None
    try:
        payload = {
            "changeType": "updated",  # Only 'updated' allowed for drive/root
            "notificationUrl": webhook_url,
            "resource": resource,
            "expirationDateTime": exp,
            "clientState": client_state
        }
        ##if existing_sub and not is_expiring(existing_sub.get('expirationDateTime','')):
            ##write_log(f"SKIPPING (already valid): resource={resource}")
            ##return {"resource": resource, "status": "already_valid", "details": existing_sub}


        if existing_sub:
            # Only skip if both not expiring AND clientState is what we want
            existing_state = existing_sub.get('clientState')
            if not is_expiring(existing_sub.get('expirationDateTime','')) and existing_state == client_state:
                write_log(f"SKIPPING (already valid/clientState OK): resource={resource}")
                return {"resource": resource, "status": "already_valid", "details": existing_sub}
            else:
                # clientState wrong/missing or expiring -- so delete and recreate
                write_log(f"Deleting & re-registering for clientState mismatch or expiration: {resource}")
                delete_subscription(existing_sub['id'], headers)


                

        # Log intended API call and payload
        write_log(f"Registering/Renewing subscription on resource: {resource}")
        if existing_sub:
            renew_url = f"https://graph.microsoft.com/v1.0/subscriptions/{existing_sub['id']}"
            write_log(f"PATCH {renew_url}: {{'expirationDateTime': '{exp}'}}")
            resp = requests.patch(renew_url, headers=headers, json={"expirationDateTime": exp})
            if resp.status_code == 200:
                write_log(f"Successfully renewed subscription (resource {resource})")
                return {"resource": resource, "status": "renewed", "details": resp.json()}
            else:
                error_details = resp.text
                write_log(f"FAILED PATCH for {resource}: {resp.status_code} {error_details}")
        resp = requests.post("https://graph.microsoft.com/v1.0/subscriptions", headers=headers, json=payload)
        if resp.status_code == 201:
            write_log(f"Created subscription for {resource}")
            return {"resource": resource, "status": "created", "details": resp.json()}
        else:
            error_details = resp.text
            write_log(f"FAILED CREATE for {resource}: {resp.status_code} {error_details}")
    except Exception as e:
        error_details = str(e)
        write_log(f"EXCEPTION during register/renew: {e}")
    if error_details:
        write_log(f"ERROR with resource {resource}: {error_details}")
    return {"resource": resource, "status": "error", "details": error_details}

def delete_subscription(sub_id, headers):
    """Remove a subscription from Graph; log all actions."""
    try:
        url = f"https://graph.microsoft.com/v1.0/subscriptions/{sub_id}"
        write_log(f"DELETE {url}")
        resp = requests.delete(url, headers=headers)
        if resp.status_code in [204, 200]:
            write_log(f"Deleted subscription {sub_id}")
            return True
        else:
            write_log(f"FAILED TO DELETE {sub_id}: {resp.status_code} {resp.text}")
            return False
    except Exception as e:
        write_log(f"EXCEPTION deleting subscription {sub_id}: {e}")
        return False

def renew_or_register_all():
    """Bulk create/renew subscriptions for all eligible users and site drives."""
    try:
        token = get_access_token()
        headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}
    except Exception as e:
        write_log(f"Could not obtain access token: {e}")
        return {"ok": False, "err": str(e)}
    # ---------------- OneDrive Subscriptions ----------------
    write_log("Enumerating all users (OneDrives)...")
    users = get_graph_list("users?$select=id,userPrincipalName", token, object_type="user")
    users = [u for u in users if not user_excluded(u)]
    write_log(f"USERS: monitoring {len(users)} (excluded: {len(EXCLUDED_USERS)})")
    # ---------------- SharePoint Subscriptions (all doclibs) ----------------
    write_log("Enumerating all SharePoint sites (list all sites)...")
    sites = get_graph_list("sites?search=*", token, object_type="site")
    sites = [s for s in sites if not site_excluded(s)]
    write_log(f"SITES: monitoring {len(sites)} (excluded: {len(EXCLUDED_SITES)})")
    # Get ALL drives/libraries in each site:
    site_drive_resources = []
    for idx, site in enumerate(sites, 1):
        try:
            drives = get_drives_for_site(site['id'], token)
            for drive in drives:
                resource = subscription_resource_site_drive(site['id'], drive['id'])
                site_drive_resources.append(resource)
            write_log(f"[{idx}/{len(sites)}] Site {site['displayName']} id={site['id']} has {len(drives)} doc libs for subscription.")
        except Exception as e:
            write_log(f"ERROR getting drives for site {site.get('id','')}: {e}")

    # -------------- Existing subscriptions and batch map --------------
    all_existing = get_existing_subscriptions(token)
    mapping = {s["resource"]: s for s in all_existing}
    all_resources_needed = [subscription_resource_user(u) for u in users] + site_drive_resources
    # -------------- Register or renew subscriptions in thread batch --------------
    tasks = []
    subs_results = {}
    unhandled = []
    write_log(f"Initiating parallel renew/create for {len(users)} user and {len(site_drive_resources)} site-drive subscriptions...")
    with ThreadPoolExecutor(max_workers=BATCH_SIZE) as executor:
        for user in users:
            resource = subscription_resource_user(user)
            tasks.append(executor.submit(register_or_renew, resource, headers, webhook_url, client_state, mapping.get(resource)))
        for resource in site_drive_resources:
            tasks.append(executor.submit(register_or_renew, resource, headers, webhook_url, client_state, mapping.get(resource)))
        for future in as_completed(tasks):
            try:
                result = future.result()
                subs_results[result['resource']] = result
                if result['status'] == "error":
                    write_log(f"FAILED: {result['resource']}: {result['details']}")
                    unhandled.append(result)
            except Exception as e:
                write_log(f"FUTURE failed: {e}")
                unhandled.append({"status":"error","details":str(e)})

    # --- Remove stale subscriptions that are not in our target list
    to_delete = [sub for sub in all_existing if sub['resource'] not in all_resources_needed]
    write_log(f"Automated removal: {len(to_delete)} subscriptions to remove.")
    for sub in to_delete:
        ok = delete_subscription(sub['id'], headers)
        if ok:
            write_log(f"Removed stale subscription for resource: {sub['resource']}")
        else:
            write_log(f"!! Could not remove stale subscription for resource: {sub['resource']}")

    save_subscriptions_bulk(subs_results)
    write_log(f"Done. Total managed subscriptions: {len(subs_results)}. All saved in {SUBS_FILE}. Errors: {len(unhandled)}")
    if unhandled:
        write_log("ERROR SUMMARY:")
        for e in unhandled:
            write_log(f"Resource: {e.get('resource','?')}, Details: {str(e.get('details',''))[:500]}")
    return {"ok": True, "error_count": len(unhandled), "subs_file": SUBS_FILE, "unhandled": unhandled}

if __name__ == "__main__":
    result = renew_or_register_all()
    # Laravel will read stdout, so print only JSON result
    print(json.dumps(result))