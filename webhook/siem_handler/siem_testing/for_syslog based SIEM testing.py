Set Up a Local Syslog Listener for UDP SIEMs
For QRadar, ArcSight, LogRhythm:

Terminal 2: Listen for syslog events

nc -klu 65000

# Or, with socat: socat -u udp-recv:65000 STDOUT


RUN YOUR TEST
1. Save the JSONs separately for clarity:
echo '{"file_path":"/mnt/sensitive.txt","compliance":"GDPR","risk_level":"HIGH"}' > file.json
echo '...splunk json as above...' > splunk.json
# repeat for others
2. Run your script for each SIEM (change second arg):
Replace send_siem_event.py with your actual script name and path.

python3 send_siem_event.py "$(cat test_file_details_json.json)" "$(cat test_siem_splunk.json)" /home/cybersecai/htdocs/www.cybersecai.io/webhook/siem_handler/siem_testing
python3 send_siem_event.py "$(cat test_file_details_json.json)" "$(cat test_siem_qradar.json)" /home/cybersecai/htdocs/www.cybersecai.io/webhook/siem_handler/siem_testing
python3 send_siem_event.py "$(cat test_file_details_json.json)" "$(cat test_siem_elastic.json)" /home/cybersecai/htdocs/www.cybersecai.io/webhook/siem_handler/siem_testing
python3 send_siem_event.py "$(cat test_file_details_json.json)" "$(cat test_siem_sentinel.json)" /home/cybersecai/htdocs/www.cybersecai.io/webhook/siem_handler/siem_testing
python3 send_siem_event.py "$(cat test_file_details_json.json)" "$(cat test_siem_arcsight.json)" /home/cybersecai/htdocs/www.cybersecai.io/webhook/siem_handler/siem_testing
python3 send_siem_event.py "$(cat test_file_details_json.json)" "$(cat test_siem_logrhythm.json)" /home/cybersecai/htdocs/www.cybersecai.io/webhook/siem_handler/siem_testing
Alternatively, paste the full JSON directly on the command line (be careful with nested quotes!).

D. WHAT YOU SHOULD SEE
For HTTP SIEMs: POST data will show up in the terminal running your HTTP server
For syslog SIEMs: Event will print in your nc window
Each time you run, an event file will be appended in /tmp/siemtest/ (or whatever test folder you specify)
Laravel log (storage/logs/laravel.log) will also get a full trace of event sent, any errors, and field mapping used
E. TROUBLESHOOTING
If you see an error, review the terminal/debug output and check laravel.log.
Try deliberately making a bad destination (wrong port) and ensure the script logs a send failure.
No real SIEMs needed; all event output will be visible in your local listeners and in test files.



. PREPARE SAMPLE JSONS
Below are generic file details and profile JSONS for each SIEM.

1) Sample <test_file_details_json> for ALL SIEMs
{
  "file_path": "/mnt/sensitive.txt",
  "compliance": "GDPR",
  "risk_level": "HIGH"
}
2) Sample <test_siem_profile_json> for each SIEM
Splunk (HTTP, JSON)
{
  "siem_ref_id": 1,
  "format": "json",
  "field_map": {
    "file_path": "filePath",
    "compliance": "compTag",
    "risk_level": "risk"
  },
  "dest": {
    "url": "http://localhost:8000",  // <-- your local fake http server!
    "token": "SPLUNK-FAKE-TOKEN"
  }
}
QRadar (Syslog/UDP, LEEF)
{
  "siem_ref_id": 2,
  "format": "leef",
  "field_map": {
    "file_path": "filePath",
    "compliance": "compTag",
    "risk_level": "risk"
  },
  "dest": {
    "url": "127.0.0.1",
    "port": "65000"
  }
}
Elastic (HTTP, JSON)
{
  "siem_ref_id": 3,
  "format": "json",
  "field_map": {
    "file_path": "filePath",
    "compliance": "compTag",
    "risk_level": "risk"
  },
  "dest": {
    "url": "http://localhost:8000"
  }
}
Microsoft Sentinel (HTTP, JSON)
{
  "siem_ref_id": 4,
  "format": "json",
  "field_map": {
    "file_path": "filePath",
    "compliance": "compTag",
    "risk_level": "risk"
  },
  "dest": {
    "url": "http://localhost:8000",
    "token": "SENTINEL-FAKE-TOKEN"
  }
}
ArcSight (Syslog/UDP, CEF)
{
  "siem_ref_id": 5,
  "format": "cef",
  "field_map": {
    "file_path": "filePath",
    "compliance": "compTag",
    "risk_level": "risk"
  },
  "dest": {
    "url": "127.0.0.1",
    "port": "65000"
  }
}
LogRhythm (Syslog/UDP, CEF)
{
  "siem_ref_id": 6,
  "format": "cef",
  "field_map": {
    "file_path": "filePath",
    "compliance": "compTag",
    "risk_level": "risk"
  },
  "dest": {
    "url": "127.0.0.1",
    "port": "65000"
  }
}