# python3 test_siem_connection.py '<FILE_DETAILS_JSON>' '<SIEM_EXPORT_PROFILE_JSON>' '<FOLDER_LOCATION>'
# FILE_DETAILS_JSON: The info you’d use for the SIEM event, eg. { "file_path": "/path/file.txt", "risk_level": "High", ... }
# SIEM_EXPORT_PROFILE_JSON: The connection/format/mapping block from data_configs
# FOLDER_LOCATION: Where to append a copy of the test event
# How this works:
# All success and failure is logged to storage/logs/laravel.log as well as printed.
# Bulletproof against missing fields/types/mapping.
# Handles missing folder, creates as needed.
# Appends the test event to a log for audit.
# SIEM handling is robust: HTTP for Splunk/Elastic/Sentinel, UDP syslog for QRadar/ArcSight/LogRhythm, easy to extend.

#!/usr/bin/env python3
import sys, os, json, time, logging, socket, requests

# Setup logging to Laravel log
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
    return {mapping[k]: file_details.get(k,"sample_"+k) for k in mapping}

def format_event(event, fmt, siem_name):
    """Format event string for given SIEM"""
    fmt = fmt.lower()
    if fmt == "json":
        return json.dumps(event)
    elif fmt == "cef":
        # ArcSight, LogRhythm: CEF:0|CybersecAI|Export|1.0|...
        cef = 'CEF:0|CybersecAI|Export|1.0|High Risk|10|'
        cef += ' '.join([f"{k}={v}" for k,v in event.items()])
        return cef
    elif fmt == "leef":
        # QRadar: LEEF:2.0|CybersecAI|Export|1.0|...
        leef = 'LEEF:2.0|CybersecAI|Export|1.0|highfile|'
        leef += '\t'.join([f"{k}={v}" for k,v in event.items()])
        return leef
    elif fmt == "csv":
        # generic
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
        log(f"Splunk response: {r.status_code}: {r.text}")
        r.raise_for_status()
        return True, f"Sent to Splunk at {url}"
    except Exception as ex:
        log(f"Splunk send failed: {ex}", "error")
        return False, f"Splunk error: {ex}"

def send_to_qradar(event, profile):
    """QRadar via syslog UDP (LEEF)"""
    dest = profile.get("dest",{})
    host, port = dest.get("url"), int(dest.get("port") or 514)
    msg = format_event(event, "leef", "QRadar")
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
            s.sendto(msg.encode(), (host, port))
        log(f"QRadar syslog sent to {host}:{port}")
        return True, f"Sent to QRadar syslog at {host}:{port}"
    except Exception as ex:
        log(f"QRadar send failed: {ex}", "error")
        return False, f"QRadar error: {ex}"

def send_to_elastic(event, profile):
    """Elastic: send one doc to index endpoint"""
    dest = profile.get("dest",{})
    url = dest.get("url")
    try:
        r = requests.post(url, headers={"Content-Type":"application/json"}, data=json.dumps(event), timeout=10)
        log(f"Elastic response: {r.status_code}: {r.text}")
        r.raise_for_status()
        return True, f"Sent to Elastic at {url}"
    except Exception as ex:
        log(f"Elastic send failed: {ex}", "error")
        return False, f"Elastic error: {ex}"

def send_to_sentinal(event, profile):
    """MS Sentinel HTTP Data Collector API (Log Analytics)"""
    # This needs valid connection string and shared key; demo only
    dest = profile.get("dest",{})
    url, token = dest.get("url"), dest.get("token")
    # You must implement signature as Azure Log Analytics expects. For demo, POST.
    headers = {"Authorization": "Bearer "+token} if token else {}
    try:
        r = requests.post(url, headers=headers, data=json.dumps([event]), timeout=10)
        log(f"Sentinel response: {r.status_code}: {r.text}")
        r.raise_for_status()
        return True, f"Sent to Sentinel at {url}"
    except Exception as ex:
        log(f"Sentinel send failed: {ex}", "error")
        return False, f"Sentinel error: {ex}"

def send_to_cef_syslog(event, profile, product="ArcSight"):
    """ArcSight/LogRhythm via syslog UDP (CEF)"""
    dest = profile.get("dest",{})
    host, port = dest.get("url"), int(dest.get("port") or 514)
    msg = format_event(event, "cef", product)
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
            s.sendto(msg.encode(), (host, port))
        log(f"{product} syslog sent to {host}:{port}")
        return True, f"Sent to {product} syslog at {host}:{port}"
    except Exception as ex:
        log(f"{product} send failed: {ex}", "error")
        return False, f"{product} error: {ex}"

# === Main Logic ===

def main():
    if len(sys.argv) != 4:
        print("Usage: test_siem_connection.py '<FILE_DETAILS_JSON>' '<SIEM_EXPORT_PROFILE_JSON>' '<FOLDER_LOCATION>'", file=sys.stderr)
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

    siem_ref_id = int(profile.get("siem_ref_id") or 0)
    format = profile.get("format", "json").lower()
    mapping = profile.get("field_map") or {}
    if isinstance(mapping, str):
        try: mapping = json.loads(mapping)
        except: mapping = {}
    if not isinstance(mapping, dict):
        log("Missing or invalid SIEM mapping", "error")
        sys.exit(3)

    siem_name = {
        1: "Splunk", 2: "QRadar", 3: "Elastic", 4: "Sentinel", 5: "ArcSight", 6: "LogRhythm"
    }.get(siem_ref_id, "Unknown")

    event = map_event_fields(mapping, file_details)
    event_str = format_event(event, format, siem_name)

    # Save to folder (append, date+type based filename)
    try:
        fname = f"{siem_name}_test_{int(time.time())}.log"
        fpath = os.path.join(folder, fname)
        with open(fpath, "a") as f:
            f.write(event_str + "\n")
        log(f"Appended test event to {fpath}")
    except Exception as ex:
        log(f"Failed to write to output folder: {ex}", "error")

    # Send test to SIEM (robust per-product)
    result = (False, "No action")
    if siem_name == "Splunk":
        result = send_to_splunk(event, profile)
    elif siem_name == "QRadar":
        result = send_to_qradar(event, profile)
    elif siem_name == "Elastic":
        result = send_to_elastic(event, profile)
    elif siem_name == "Sentinel":
        result = send_to_sentinal(event, profile)
    elif siem_name == "ArcSight":
        result = send_to_cef_syslog(event, profile, "ArcSight")
    elif siem_name == "LogRhythm":
        result = send_to_cef_syslog(event, profile, "LogRhythm")
    else:
        log("No SIEM handler for: " + str(siem_ref_id), "error")
        result = (False, f"Unknown SIEM id {siem_ref_id}")

    if result[0]:
        log(f"Test SIEM Event Success: {result[1]}")
    else:
        log(f"Test SIEM Event Failure: {result[1]}", "error")
    print(result[1])

if __name__ == "__main__":
    main()