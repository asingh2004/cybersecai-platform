import os, sys, json, csv, requests, time, subprocess

def main():
    if len(sys.argv) != 2:
        print("Usage: python3 1_list_onedrivefiles.py '<json_config>'")
        sys.exit(1)
    cfg = json.loads(sys.argv[1])

    TENANT_ID = cfg['TENANT_ID']
    CLIENT_ID = cfg['CLIENT_ID']
    CLIENT_SECRET = cfg['CLIENT_SECRET']
    SUBSCRIPTIONS_FILE = cfg['SUBSCRIPTIONS_FILE']
    CSV_FILE = cfg['CSV_FILE']
    DATA_CONFIG_ID = cfg['id']

    def get_access_token():
        url = f"https://login.microsoftonline.com/{TENANT_ID}/oauth2/v2.0/token"
        data = {
            'grant_type': 'client_credentials', 'client_id': CLIENT_ID, 'client_secret': CLIENT_SECRET,
            'scope': 'https://graph.microsoft.com/.default'
        }
        resp = requests.post(url, data=data)
        resp.raise_for_status()
        return resp.json()['access_token']

    def extract_user_id(resource_path):
        parts = resource_path.strip("/").split("/")
        if len(parts) >= 3 and parts[0] == "users":
            return parts[1]
        return None

    def list_drive_items_recursive(user_id, access_token):
        headers = {"Authorization": f"Bearer {access_token}"}
        all_results = []
        def crawl_folder(folder_id, parent_path=""):
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
                        crawl_folder(item["id"], this_path)
                url = data.get("@odata.nextLink")
        crawl_folder(None)
        return all_results

    # MAIN routine
    access_token = get_access_token()
    with open(SUBSCRIPTIONS_FILE) as f:
        subscriptions = json.load(f)

    headers = [
        "user_id", "file_id", "file_name", "file_type", "size_bytes",
        "last_modified", "web_url", "download_url", "parent_reference", "full_path",
        "contains_PII", "LLM_Response"
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
            for item in files:
                download_url = item.get("@microsoft.graph.downloadUrl", "")
                row = [
                    user_id, item.get('id'), item.get('name'),
                    item.get('file', {}).get('mimeType', '') if item.get("file") else "folder",
                    item.get('size', ''), item.get('lastModifiedDateTime', ''),
                    item.get('webUrl', ''), download_url,
                    item.get("parentReference", {}).get("path", ""),
                    item.get("full_path", "")
                ]
                # Directly call file processor (script 2)
                try:
                    args = json.dumps({
                        'TENANT_ID': TENANT_ID, 'CLIENT_ID': CLIENT_ID, 'CLIENT_SECRET': CLIENT_SECRET,
                        'file_row': row[:10]  # first 10 fields
                    })
                    proc = subprocess.run(
                        ["python3", "2_file_content_extraction_pipeline.py", args],
                        capture_output=True, text=True, timeout=360)
                    if proc.returncode == 0 and proc.stdout:
                        result = json.loads(proc.stdout)
                        pii_val = result['contains_PII']
                        llm_resp = result['LLM_Response']
                    else:
                        pii_val, llm_resp = "Error", proc.stderr
                except Exception as ex:
                    pii_val, llm_resp = "Error", str(ex)
                row.extend([pii_val, llm_resp])
                writer.writerow(row)

    print(f"Done! CSV written to {CSV_FILE}")

if __name__ == "__main__":
    main()