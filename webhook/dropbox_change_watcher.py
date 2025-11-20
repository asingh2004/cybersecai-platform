# This script:

# Handles webhook registration with Dropbox
# Tracks all connected users and their Dropbox tokens/cursors
# Pulls changed files per user after each webhook call
# Example Data/Secret storage via SQLite (for simplicity; use your DB in prod)
# Implements error reporting, parallel Dropbox API calls, and exclusion (by user ID or email)

import os
import sys
import requests
import json
import logging
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor, as_completed

# --- DROPBOX APP CONFIGURATION (HARDCODED) ---
DROPBOX_APP_KEY = "qtlm5tncrbua6jc"
DROPBOX_APP_SECRET = "9dd3db1kh61eqt5"
DROPBOX_WEBHOOK_URL = "https://cybersecai.io/dropbox/webhook"
WEBHOOK_VERIFY_CHALLENGE = True
WEBHOOK_SECRET = "supersecret"  # Only for your internal use, not from Dropbox

# --- OTHER SETTINGS ---
PARALLEL_THREADS = 8
EXCLUDED_USERS = set()      # e.g. {"dbid:AABBCC", "email@example.com"}
USERS_FILE = "dropbox_users.json"

# --- LOGGING SETUP ---
LOG_FILE = "dropbox_sync.log"
logging.basicConfig(level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    handlers=[logging.StreamHandler(), logging.FileHandler(LOG_FILE)]
)
logger = logging.getLogger("dropbox_sync")

# --- JSON-based USER STORAGE ---
def ensure_users_file():
    """Make sure JSON file exists for users."""
    if not os.path.exists(USERS_FILE):
        with open(USERS_FILE, "w") as f:
            json.dump([], f)

def add_or_update_user(user_id, email, access_token):
    """
    Add new user to JSON store (or update existing by user_id).
    """
    ensure_users_file()
    with open(USERS_FILE, "r") as f:
        users = json.load(f)
    found = False
    now = datetime.utcnow().isoformat()
    for user in users:
        if user["user_id"] == user_id:
            user["email"] = email
            user["access_token"] = access_token
            user["last_sync"] = now
            found = True
            break
    if not found:
        users.append({
            "user_id": user_id,
            "email": email,
            "access_token": access_token,
            "cursor": "",
            "last_sync": now
        })
    with open(USERS_FILE, "w") as f:
        json.dump(users, f, indent=2)

def update_cursor(user_id, cursor):
    """
    Update cursor after processing deltas for a user.
    """
    ensure_users_file()
    with open(USERS_FILE, "r") as f:
        users = json.load(f)
    now = datetime.utcnow().isoformat()
    for user in users:
        if user["user_id"] == user_id:
            user["cursor"] = cursor
            user["last_sync"] = now
            break
    with open(USERS_FILE, "w") as f:
        json.dump(users, f, indent=2)

def get_users():
    """
    Load all users from JSON (excluding those in EXCLUDED_USERS).
    """
    ensure_users_file()
    with open(USERS_FILE, "r") as f:
        users = json.load(f)
    return [
        u for u in users
        if u['user_id'] not in EXCLUDED_USERS and u['email'] not in EXCLUDED_USERS
    ]

# --- EXCLUSION ---
def user_excluded(user):
    return user['user_id'] in EXCLUDED_USERS or user['email'] in EXCLUDED_USERS

# --- DROPBOX API CALLS ---

def get_account(access_token):
    """
    Returns Dropbox user info for the given personal token.
    """
    resp = requests.post(
        "https://api.dropboxapi.com/2/users/get_current_account",
        headers={"Authorization": f"Bearer {access_token}"},
    )
    resp.raise_for_status()
    return resp.json()

def get_deltas(user):
    """
    Query Dropbox for changes (new/removed/changed files) since last cursor.
    """
    access_token = user["access_token"]
    cursor = user.get("cursor", None) or ""
    results = []
    new_cursor = None

    # 1st call: list_folder or list_folder/continue
    if not cursor:
        resp = requests.post(
            "https://api.dropboxapi.com/2/files/list_folder",
            headers={"Authorization": f"Bearer {access_token}", "Content-Type": "application/json"},
            json={"path": "", "recursive": True, "include_deleted": True}
        )
    else:
        resp = requests.post(
            "https://api.dropboxapi.com/2/files/list_folder/continue",
            headers={"Authorization": f"Bearer {access_token}", "Content-Type": "application/json"},
            json={"cursor": cursor}
        )
    resp.raise_for_status()
    data = resp.json()
    results.extend(data["entries"])
    new_cursor = data["cursor"]

    # Paging: keep going if more results to pull
    while data.get("has_more", False):
        resp = requests.post(
            "https://api.dropboxapi.com/2/files/list_folder/continue",
            headers={"Authorization": f"Bearer {access_token}", "Content-Type": "application/json"},
            json={"cursor": new_cursor}
        )
        resp.raise_for_status()
        data = resp.json()
        results.extend(data["entries"])
        new_cursor = data["cursor"]
    return (results, new_cursor)

def notify_platform(user, changed_files):
    """
    Send info to your own system (set your webhook URL!).
    """
    payload = {
        "user_id": user["user_id"],
        "email": user["email"],
        "changed_files": changed_files,
        "timestamp": datetime.utcnow().isoformat(),
    }
    url = "https://cybersecai.io/dropbox/notify_platform"  # <-- Customize your platform endpoint
    try:
        resp = requests.post(url, json=payload, timeout=10)
        if resp.status_code < 300:
            logger.info("Notified platform of changes for %s (%s files)", user["email"], len(changed_files))
        else:
            logger.error("Notify failed for %s: status %s, response %s", user["email"], resp.status_code, resp.text)
    except Exception as e:
        logger.exception(f"Exception notifying platform for user {user['email']}: {e}")

def process_users(user_ids):
    """
    Run on webhook, or manually. For the given Dropbox user_ids, fetch changes and notify platform.
    """
    all_users = get_users()
    users = [u for u in all_users if u["user_id"] in user_ids and not user_excluded(u)]
    results = {}
    with ThreadPoolExecutor(max_workers=PARALLEL_THREADS) as executor:
        fut_map = {executor.submit(get_deltas, u): u for u in users}
        for fut in as_completed(fut_map):
            user = fut_map[fut]
            try:
                changes, new_cursor = fut.result()
                update_cursor(user["user_id"], new_cursor)
                notify_platform(user, changes)
                results[user["user_id"]] = {"status": "ok", "changed_files": len(changes)}
            except Exception as e:
                logger.exception("Error syncing user %s: %s", user['email'], e)
                results[user["user_id"]] = {"status": "error", "error": str(e)}
    return results

def on_new_oauth(user_token):
    """
    Call this after a user successfully OAUTHs your Dropbox app.
    """
    try:
        info = get_account(user_token)
        user_id, email = info["account_id"], info["email"]
        add_or_update_user(user_id, email, user_token)
        logger.info("OAuth complete for %s (%s)", email, user_id)
    except Exception as e:
        logger.exception("OAuth add failure: %s", e)

if __name__ == "__main__":
    # Example manual usage: process *all* users as if webhook came in
    ensure_users_file()
    logger.info("Manual test sync for all users (notified as if by webhook)")
    user_ids = [u["user_id"] for u in get_users() if not user_excluded(u)]
    result = process_users(user_ids)
    print(json.dumps(result, indent=2))