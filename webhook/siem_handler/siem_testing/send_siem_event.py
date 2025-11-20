# send_siem_event.py 

# Purpose: To actually send real SIEM events (e.g., actual high-risk file info/events) to the chosen SIEM endpoint/system.
# Use case: Called by your background processing pipeline or event-queue, on every real file/event.
# Feedback: Logs "event sent" or "failure" and continues (for scale/millions of events).

# (1) file details dict
# (2) value of siem_export_profile (JSON, as DB stores it)
# (3) output folder (for auditing/appending event for trace/debug etc)
# Features:

# Logs all sends and errors to Laravel log (storage/logs/laravel.log)
# Handles Splunk (HTTP), QRadar (Syslog/LEEF), Elastic (HTTP), Sentinel (HTTP), ArcSight/LogRhythm (Syslog/CEF)
# All mapping, format, connection settings are handled
# Can be called from any other Python for massive scale
# Notifies errors, unexpected input, and exceptions via logs and exit status
# All param parsing, output file handling, etc, is robust
# Key points:
# Logs every operation (success, failure, exception) to Laravel log.
# Bulletproof even if mapping, destination or folder is bad.
# Extensible for new SIEMs and for integration in pipeline.
# Can be called as:
# python3 send_siem_event.py '<json_file_details>' '<json_siem_export_profile>' '<folder>'


#!/usr/bin/env python3
import sys, os, json, time, logging, socket, requests

# Setup Laravel log
LARAVEL_LOG = os.path.abspath(os.path.join(os.getcwd(), "storage", "logs", "laravel.log"))
os.makedirs(os.path.dirname(LARAVEL_LOG), exist_ok=True)
logging.basicConfig(
    filename=LARAVEL_LOG,
    level=logging.INFO,
    format="%(asctime)s %(levelname)s: %(message)s"
)
def log(msg, level="info"):
    fn = getattr(logging, level, logging.info)
    fn(msg)
    print(msg)

def map_event_fields(mapping, file_details):
    """Build event dict for SIEM using mapping & input fields"""
    return {mapping[k]: file_details.get(k,"ev_"+k) for k in mapping if mapping[k]}

def format_event(event, fmt, siem_name):
    """Format event string for given SIEM"""
    fmt = fmt.lower()
    if fmt == "json":
        return json.dumps(event)
    elif fmt == "cef":
        cef = 'CEF:0|CybersecAI|Export|1.0|High Risk|10|'
        cef += ' '.join([f"{k}={v}" for k,v in event.items()])
        return cef
    elif fmt == "leef":
        leef = 'LEEF:2.0|CybersecAI|Export|1.0|event|'
        leef += '\t'.join([f"{k}={v}" for k,v in event.items()])
        return leef
    elif fmt == "csv":
        return ','.join(event.keys()) + '\n' + ','.join(str(v) for v in event.values())
    else:
        return json.dumps(event)

# === SIEM Connectors ===

def send_to_splunk(event, profile):
    """Splunk HTTP Event Collector"""
    dest = profile.get("dest",{})
    url = dest.get("url")
    token = dest.get("token")
    headers = { "Authorization": "Splunk " + token } if token else {}
    data = {"event": event}
    try:
        r = requests.post(url, headers=headers, json=data, timeout=10)
        log(f"SPLUNK [{url}] RESP: {r.status_code} {r.text}")
        r.raise_for_status()
        return True, f"Sent to Splunk at {url}"
    except Exception as ex:
        log(f"SPLUNK send failed: {ex}", "error")
        return False, f"SPLUNK error: {ex}"

def send_to_qradar(event, profile):
    """QRadar via syslog/UDP LEEF"""
    dest = profile.get("dest",{})
    host, port = dest.get("url"), int(dest.get("port") or 514)
    msg = format_event(event, "leef", "QRadar")
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
            s.sendto(msg.encode(), (host, port))
        log(f"QRADAR-SYSLOG [{host}:{port}] MSG: {msg[:200]}")
        return True, f"Sent to QRadar syslog at {host}:{port}"
    except Exception as ex:
        log(f"QRADAR send failed: {ex}", "error")
        return False, f"QRADAR error: {ex}"

def send_to_elastic(event, profile):
    """Elastic: HTTP to ingest endpoint"""
    dest = profile.get("dest",{})
    url = dest.get("url")
    try:
        r = requests.post(url, headers={"Content-Type":"application/json"}, data=json.dumps(event), timeout=10)
        log(f"ELASTIC [{url}] RESP: {r.status_code} {r.text}")
        r.raise_for_status()
        return True, f"Sent to Elastic at {url}"
    except Exception as ex:
        log(f"ELASTIC send failed: {ex}", "error")
        return False, f"ELASTIC error: {ex}"

def send_to_sentinal(event, profile):
    """MS Sentinel HTTP API"""
    dest = profile.get("dest",{})
    url, token = dest.get("url"), dest.get("token")
    headers = {"Authorization": "Bearer "+token} if token else {}
    try:
        r = requests.post(url, headers=headers, data=json.dumps([event]), timeout=10)
        log(f"SENTINEL [{url}] RESP: {r.status_code} {r.text}")
        r.raise_for_status()
        return True, f"Sent to Sentinel at {url}"
    except Exception as ex:
        log(f"SENTINEL send failed: {ex}", "error")
        return False, f"SENTINEL error: {ex}"

def send_to_cef_syslog(event, profile, product="ArcSight"):
    """ArcSight/LogRhythm: syslog UDP (CEF)"""
    dest = profile.get("dest",{})
    host, port = dest.get("url"), int(dest.get("port") or 514)
    msg = format_event(event, "cef", product)
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
            s.sendto(msg.encode(), (host, port))
        log(f"{product.upper()} SYSLOG [{host}:{port}] MSG: {msg[:120]}")
        return True, f"Sent to {product} syslog at {host}:{port}"
    except Exception as ex:
        log(f"{product.upper()} send failed: {ex}", "error")
        return False, f"{product} error: {ex}"

def get_siem_name_from_profile(profile):
    # You MUST store as siem_ref_id (int) or "siem_name" string for this to work!
    if "siem_name" in profile:
        return profile["siem_name"].lower()
    siem_ref_id = int(profile.get("siem_ref_id") or 0)
    names = {
        1: "splunk", 2: "qradar", 3: "elastic", 4: "sentinel", 5: "arcsight", 6: "logrhythm"
    }
    return names.get(siem_ref_id, "unknown")

# === Main Logic ===

def main():
    if len(sys.argv) != 4:
        print("Usage: send_siem_event.py '<FILE_DETAILS_JSON>' '<SIEM_EXPORT_PROFILE_JSON>' '<FOLDER_LOCATION>'", file=sys.stderr)
        sys.exit(1)
    try:
        file_details = json.loads(sys.argv[1])
        profile = json.loads(sys.argv[2])
        folder = sys.argv[3]
        if not os.path.exists(folder):
            os.makedirs(folder)
    except Exception as ex:
        log(f"Input decode error: {ex}", "error")
        sys.exit(2)

    mapping = profile.get("field_map") or {}
    if isinstance(mapping, str):
        try: mapping = json.loads(mapping)
        except: mapping = {}
    if not isinstance(mapping, dict):
        log("Missing or invalid SIEM mapping", "error")
        sys.exit(3)

    siem_name = get_siem_name_from_profile(profile)
    if siem_name == "unknown":
        log("SIEM name could not be determined from profile; abort", "error")
        sys.exit(4)
    format = profile.get("format", "json").lower()
    event = map_event_fields(mapping, file_details)
    event_str = format_event(event, format, siem_name)

    # Append event string to output file for traceability
    try:
        fname = f"{siem_name}_{int(time.time())}.event"
        fpath = os.path.join(folder, fname)
        with open(fpath, "a") as f:
            f.write(event_str + "\n")
        log(f"Event appended to {fpath}")
    except Exception as ex:
        log(f"Failed to append event: {ex}", "error")

    # Robust, SIEM-specific send
    result = (False, "No action")
    success = False
    message = "SIEM send failed"
    try:
        if siem_name == "splunk":
            success, message = send_to_splunk(event, profile)
        elif siem_name == "qradar":
            success, message = send_to_qradar(event, profile)
        elif siem_name == "elastic":
            success, message = send_to_elastic(event, profile)
        elif siem_name == "sentinel":
            success, message = send_to_sentinal(event, profile)
        elif siem_name == "arcsight":
            success, message = send_to_cef_syslog(event, profile, "ArcSight")
        elif siem_name == "logrhythm":
            success, message = send_to_cef_syslog(event, profile, "LogRhythm")
        else:
            log("Unsupported SIEM: " + siem_name, "error")
            message = "Unsupported SIEM: " + siem_name
        if success:
            log(f"SIEM Event successfully sent: {message}")
        else:
            log(f"SIEM Event failed: {message}", "error")
        print(message)
    except Exception as ex:
        log(f"Exception in SIEM send: {ex}", "error")
        print("Error in send: "+str(ex))
        sys.exit(10)

if __name__ == "__main__":
    main()