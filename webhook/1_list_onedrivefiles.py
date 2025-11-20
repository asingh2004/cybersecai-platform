import json
import csv
import requests
import time

# ==== CREDENTIALS (Replace with your vault/env read in prod) ====
TENANT_ID = "56758e8e-cf76-49f4-b6e0-8d5ae252c727"
CLIENT_ID = "4e02e753-0345-4e7e-a7ba-614ca6d59524"
CLIENT_SECRET = "NNT8Q~yQCD1DiRh5OQT5c6zVm9hYKu7gbjaKXdrQ"

# ==== FILE PATHS ====
SUBSCRIPTIONS_FILE = "subscriptions_bulk_11.json"
CSV_FILE = "onedrive_files_report.csv"

# ================ 1. AUTH =====================
def get_access_token():
    url = f"https://login.microsoftonline.com/{TENANT_ID}/oauth2/v2.0/token"
    data = {
        'grant_type': 'client_credentials',
        'client_id': CLIENT_ID,
        'client_secret': CLIENT_SECRET,
        'scope': 'https://graph.microsoft.com/.default'
    }
    resp = requests.post(url, data=data)
    resp.raise_for_status()
    return resp.json()['access_token']

# ================ 2. FILE/FOLDER ENUM =====================

def extract_user_id(resource_path):
    # Expects: /users/<user_id>/drive/root
    parts = resource_path.strip("/").split("/")
    if len(parts) >= 3 and parts[0] == "users":
        return parts[1]
    return None

def list_drive_items_recursive(user_id, access_token):
    """Recursively crawl all items in user's OneDrive."""
    headers = {"Authorization": f"Bearer {access_token}"}
    all_results = []

    def crawl_folder(folder_id, parent_path=""):
        # If folder_id is None, crawl root
        if folder_id:
            url = f"https://graph.microsoft.com/v1.0/users/{user_id}/drive/items/{folder_id}/children"
        else:
            url = f"https://graph.microsoft.com/v1.0/users/{user_id}/drive/root/children"
        while url:
            resp = requests.get(url, headers=headers)
            if resp.status_code == 429:
                retry = int(resp.headers.get("Retry-After", 1))
                print(f"Throttled. Sleeping {retry}s...")
                time.sleep(retry)
                continue
            elif not resp.ok:
                print(f"Failed to list [{url}]: {resp.status_code} {resp.text}")
                break
            data = resp.json()
            for item in data.get("value", []):
                this_path = (parent_path.rstrip("/") + "/" + item["name"])
                item["full_path"] = this_path
                all_results.append(item)
                if item.get("folder"):
                    crawl_folder(item["id"], this_path)  # RECURSIVE
            url = data.get("@odata.nextLink")
    crawl_folder(None)
    return all_results

# =============== 3. MAIN & OUTPUT ===================

def main():
    # Load MS Graph subscriptions from file
    with open(SUBSCRIPTIONS_FILE) as f:
        subscriptions = json.load(f)
    access_token = get_access_token()
    headers = [
        "user_id",
        "file_id",
        "file_name",
        "file_type",
        "size_bytes",
        "last_modified",
        "web_url",
        "download_url",
        "parent_reference",
        "full_path"
    ]
    with open(CSV_FILE, "w", newline='', encoding="utf-8") as csvfile:
        writer = csv.writer(csvfile)
        writer.writerow(headers)
        for resource_path, sub in subscriptions.items():
            user_id = extract_user_id(resource_path)
            if not user_id:
                continue
            print(f"Recursively listing files for user {user_id} ...")
            files = list_drive_items_recursive(user_id, access_token)
            print(f"   {len(files)} items found for user {user_id}.")
            for item in files:
                download_url = item.get("@microsoft.graph.downloadUrl", "")  # Only for files, not folders
                writer.writerow([
                    user_id,
                    item.get('id'),
                    item.get('name'),
                    item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    item.get('size', ''),
                    item.get('lastModifiedDateTime', ''),
                    item.get('webUrl', ''),
                    download_url,
                    item.get("parentReference", {}).get("path", ""),
                    item.get("full_path", ""),
                ])
    print(f"Done! CSV written to {CSV_FILE}")

if __name__ == "__main__":
    main()