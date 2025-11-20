# healingapi/enrich.py

# Feeds include ransomware IOCs, breach-specific hash feeds, suspicious extensions, and any exfil indicators.
# Enrichment logic recognizes not only generic IOCs (IP, hash, domain) but also breach-relevant artifacts 
# like suspicious file extensions, known ransomware file hashes, C2 domains, or email addresses/hosts used in credential theft/exfil.

import redis
import os
import re

REDIS_URL = os.getenv("REDIS_URL", "redis://localhost:6379/0")

def get_redis():
    return redis.from_url(REDIS_URL, decode_responses=True)

# Regexes
IP_RE = re.compile(r"^\d{1,3}(?:\.\d{1,3}){3}$")
DOMAIN_RE = re.compile(r"^(?!\-)([A-Za-z0-9\-]{,63}(?<!\-)\.)+[A-Za-z]{2,6}$")
HOSTNAME_RE = re.compile(r"^(?!\-)([A-Za-z0-9\-]{,63}(?<!\-)\.)*[A-Za-z0-9\-]{,63}(?<!\-)$")
SHA256_RE = re.compile(r"\b[a-fA-F0-9]{64}\b")
SHA1_RE = re.compile(r"\b[a-fA-F0-9]{40}\b")
MD5_RE = re.compile(r"\b[a-fA-F0-9]{32}\b")
EMAIL_RE = re.compile(r"\b([a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+)\b")
URL_RE = re.compile(
    r"http[s]?://(?:[a-zA-Z]|[0-9]|[$-_@.&+]|[!*\\(\\),]|(?:%[0-9a-fA-F][0-9a-fA-F]))+"
)
# For file extension threats (breach & ransomware focus)
ENCRYPT_EXTS = ['.enc', '.encrypted', '.crypt', '.locky', '.cryptolocker', '.ecc', '.zzz']
ARCHIVE_EXTS = ['.zip', '.7z', '.rar', '.tar', '.gz', '.bz2']

TYPE_WEIGHTS = {
    "ip": 1.0,
    "domain": 0.9,
    "hostname": 0.7,
    "url": 0.8,
    "sha256": 1.0,
    "sha1": 0.8,
    "md5": 0.7,
    "email": 0.6,
    "enc_file": 1.0,     # Suspicious encrypted file extension
    "archive_file": 0.7  # Suspicious archive
}
SCORE_MAX = 5.0
SCORE_CLAMP = 1.0

def enrich_event(event):
    """
    Checks all typical fields including filename extensions for breach/crypto/extfil attempts.
    Returns weighted score and IOC details.
    """
    r = get_redis()
    details = []
    total_score = 0.0
    seen = set()

    def trymatch(typ, value, src):
        nonlocal total_score
        key = f"threatfeed:{typ}"
        if value and (typ, value) not in seen:
            if r.sismember(key, value):
                details.append({"type": typ, "value": value, "source": src})
                total_score += TYPE_WEIGHTS.get(typ, 0.5)
                seen.add((typ, value))

    # IOC scan
    for k, raw_value in event.items():
        if not raw_value:
            continue
        value = str(raw_value).strip().lower()
        # -- IP/Domains --
        if IP_RE.fullmatch(value): trymatch("ip", value, "IP TI Feeds")
        for url in URL_RE.findall(value): trymatch("url", url, "URL TI Feeds")
        if "." in value and not value.startswith("http") and not IP_RE.fullmatch(value):
            if DOMAIN_RE.fullmatch(value): trymatch("domain", value, "Domain TI Feeds")
            elif HOSTNAME_RE.fullmatch(value): trymatch("hostname", value, "Hostname TI Feeds")
        # -- Hashes --
        for md5 in MD5_RE.findall(value): trymatch("md5", md5, "Hash TI Feeds")
        for sha1 in SHA1_RE.findall(value): trymatch("sha1", sha1, "Hash TI Feeds")
        for sha256 in SHA256_RE.findall(value): trymatch("sha256", sha256, "Hash TI Feeds")
        # -- Email --
        for email in EMAIL_RE.findall(value): trymatch("email", email, "Email TI Feeds")

        # --- File extension threat emphasis ---
        if k in ("file", "filename", "src_file", "dst_file"):
            for ext in ENCRYPT_EXTS:
                if value.endswith(ext):
                    details.append({"type": "enc_file", "value": value, "source": "Suspicious File Encrypt Ext"})
                    total_score += TYPE_WEIGHTS["enc_file"]
            for ext in ARCHIVE_EXTS:
                if value.endswith(ext):
                    details.append({"type": "archive_file", "value": value, "source": "Suspicious Archive"})
                    total_score += TYPE_WEIGHTS["archive_file"]

    score = min(total_score / SCORE_MAX, SCORE_CLAMP)
    return {"score": round(score, 2), "details": details}
