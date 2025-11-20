# healingapi/threat_feeds.py

# Feeds include ransomware IOCs, breach-specific hash feeds, suspicious extensions, and any exfil indicators.
# Enrichment logic recognizes not only generic IOCs (IP, hash, domain) but also breach-relevant artifacts like suspicious file extensions, 
# known ransomware file hashes, C2 domains, or email addresses/hosts used in credential theft/exfil.

import requests
import redis
import os
import time
import csv

REDIS_URL = os.getenv("REDIS_URL", "redis://localhost:6379/0")
FEED_INTERVAL = int(os.getenv("THREAT_FEED_INTERVAL", "600"))

THREAT_FEEDS = [
    # IPs
    {"name": "otx-ips", "url": "https://otx.alienvault.com/api/v1/indicators/export?type=IPv4", "type": "ip"},
    {"name": "malc0de-ip", "url": "http://malc0de.com/bl/IP_Blacklist.txt", "type": "ip"},
    {"name": "feodo-ip", "url": "https://feodotracker.abuse.ch/downloads/ipblocklist.txt", "type": "ip"},
    {"name": "emergingthreats-compromised", "url": "https://rules.emergingthreats.net/open/suricata/rules/compromised-ips.txt", "type": "ip"},
    {"name": "sslbl-botnet", "url": "https://sslbl.abuse.ch/blacklist/sslipblacklist.txt", "type": "ip"},
    {"name": "spamhaus-drop", "url": "https://www.spamhaus.org/drop/drop.txt", "type": "ip"},
    {"name": "blocklistde-all", "url": "https://lists.blocklist.de/lists/all.txt", "type": "ip"},
    # Domains
    {"name": "otx-domains", "url": "https://otx.alienvault.com/api/v1/indicators/export?type=domain", "type": "domain"},
    # URLs
    {"name": "otx-urls", "url": "https://otx.alienvault.com/api/v1/indicators/export?type=url", "type": "url"},
    {"name": "urlhaus", "url": "https://urlhaus.abuse.ch/downloads/text/", "type": "url"},
    # Hashes (SHA256, MD5, SHA1)
    {"name": "feodo-hashes", "url": "https://feodotracker.abuse.ch/downloads/malware_hashes.csv", "type": "sha256"},
    # Add your own MD5/SHA1 feeds as needed below...
    # Emails (very rare in OSINT, stub for expansion)
    # {"name": "your-email-feed", "url": "...", "type": "email"},
    # Hostnames (stub for custom feeds)
    # {"name": "your-hostname-feed", "url": "...", "type": "hostname"},
]

def parse_simple_list(data): return [line.strip() for line in data.splitlines() if line and not line.startswith("#")]
def parse_otx_domains(data): return parse_simple_list(data)
def parse_otx_ips(data):     return parse_simple_list(data)
def parse_otx_urls(data):    return parse_simple_list(data)
def parse_abuse_urls(data):  return [line.strip() for line in data.splitlines() if line and not line.startswith("#") and "." in line]

def parse_feodo_hashes(data):
    hashes, md5s, sha1s = [], [], []
    reader = csv.DictReader(data.splitlines())
    for row in reader:
        if row.get("sha256"): hashes.append(row["sha256"].lower())
        if row.get("md5"): md5s.append(row["md5"].lower())
        if row.get("sha1"): sha1s.append(row["sha1"].lower())
    return {"sha256": hashes, "md5": md5s, "sha1": sha1s}

def parse_spamhaus_drop(data):
    return [line.split(";")[0].strip() for line in data.splitlines() if line and not line.startswith(";")]

def parse_stub(data): return []

def sync_feeds():
    r = redis.from_url(REDIS_URL)
    for feed in THREAT_FEEDS:
        print(f"[Feed Sync] Fetching {feed['name']}")
        try:
            resp = requests.get(feed["url"], timeout=45)
            resp.raise_for_status()
            feed_type = feed['type']
            values = []

            # Hashes (multiple sets)
            if feed["name"] == "feodo-hashes":
                hashes_by_type = parse_feodo_hashes(resp.text)
                for typ in ("sha256", "md5", "sha1"):
                    key = f"threatfeed:{typ}"
                    vals = hashes_by_type.get(typ, [])
                    r.delete(key)
                    if vals: r.sadd(key, *vals)
                    print(f"[Feed Sync] Loaded {len(vals)} {typ} hashes from feodo-hashes")
                continue
            elif feed["name"].startswith("otx-domains"):
                values = parse_otx_domains(resp.text)
            elif feed["name"].startswith("otx-ips"):
                values = parse_otx_ips(resp.text)
            elif feed["name"].startswith("otx-urls"):
                values = parse_otx_urls(resp.text)
            elif feed["name"] == "urlhaus":
                values = parse_abuse_urls(resp.text)
            elif feed["name"].startswith(("malc0de", "sslbl", "emergingthreats", "blocklistde-all")):
                values = parse_simple_list(resp.text)
            elif feed["name"] == "spamhaus-drop":
                values = parse_spamhaus_drop(resp.text)
            elif feed["name"] == "feodo-ip":
                values = parse_simple_list(resp.text)
            else:
                values = parse_stub(resp.text)

            key = f"threatfeed:{feed_type}"
            r.delete(key)
            if values: r.sadd(key, *values)
            print(f"[Feed Sync] Loaded {len(values)} items for {feed['name']}")
        except Exception as ex:
            print(f"[Sync error] {feed['name']}: {ex}")

if __name__ == "__main__":
    print("=== Starting Threat Feed Sync ===")
    while True:
        try:
            sync_feeds()
        except Exception as exc:
            print(f"[Main Loop] Fatal error: {exc}")
        time.sleep(FEED_INTERVAL)
