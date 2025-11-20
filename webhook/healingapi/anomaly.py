# healingapi/anomaly.py

# This function turns each log/event dict into a vectorized summary that highlights features useful for the detection of data breach, exfiltration and ransomware/encryption events.

import os
import joblib
import numpy as np
from sklearn.ensemble import IsolationForest

# Root directory for storing tenant models
MODEL_DIR = os.getenv("MODEL_DIR", "models")

def ensure_model_dir(tenant_id: str):
    """Ensure per-tenant model directory exists."""
    path = os.path.join(MODEL_DIR, tenant_id)
    os.makedirs(path, exist_ok=True)
    return path

def anomaly_model_path(tenant_id: str):
    """Get path to the tenant's anomaly model file."""
    return os.path.join(MODEL_DIR, tenant_id, "anomaly.pkl")

def feature_vector(event: dict, vector_len=30):
    """
    Advanced feature engineering for breach/exfil/encryption scenarios.
    Produces a strong, informative numeric vector for your anomaly detection.
    Adjust field names if your log schema is different.
    """
    vec = []

    # 1. Event type (categorical, one-hot for common breach/exfil types)
    event_type = event.get('event_type', '').lower()
    for e in ['file_read', 'file_write', 'file_encrypt', 'file_rename', 'network_upload', 'network_connect', 'process_start', 'process_kill']:
        vec.append(float(event_type == e))

    # 2. File extension/target (for ransomware exts, archives)
    fname = event.get('file', '').lower()
    ENCRYPT_EXTS = ['.enc', '.encrypted', '.crypt', '.locky', '.cryptolocker', '.ecc', '.zzz']
    ARCHIVE_EXTS = ['.zip', '.7z', '.rar', '.tar', '.gz', '.bz2']

    vec.append(float(any(fname.endswith(ext) for ext in ENCRYPT_EXTS)))
    vec.append(float(any(fname.endswith(ext) for ext in ARCHIVE_EXTS)))

    # 3. Data size, exfil signal (bytes out never negative, high means possible exfil)
    try:
        vec.append(np.log1p(float(event.get('bytes_out', 0))))
    except Exception:
        vec.append(0.0)
    try:
        vec.append(np.log1p(float(event.get('bytes_in', 0))))
    except Exception:
        vec.append(0.0)

    # 4. File count/batch operation
    try:
        vec.append(np.log1p(float(event.get('file_count', 1))))
    except Exception:
        vec.append(1.0)

    # 5. Suspicious process indicators (categorical)
    proc = event.get('process', '').lower()
    for p in ['ransom', 'encrypt', 'powershell', 'cmd', 'winword', 'curl', 'wget']:
        vec.append(float(p in proc))

    # 6. Success/failure/outcome and user context
    outcome = event.get('outcome', '').lower()
    vec.append(float('fail' in outcome or 'denied' in outcome or 'unauth' in outcome))
    vec.append(float(event.get('user', '').endswith('$')))

    # 7. Network destination (possible exfil C&C)
    remote = event.get('destination', '').lower() + event.get('ip', '').lower()
    vec.append(float(remote.endswith('.ru') or remote.endswith('.cn')))

    # 8. File pattern hints
    vec.append(float(any(x in fname for x in ['backup', 'db', 'export', 'dump'])))

    # 9. Add any custom/situational features that make sense for your org/data here

    # 10. Hash any extra fields for generality
    skip_keys = set(['event_type', 'file', 'bytes_out', 'bytes_in', 'file_count', 'process', 'outcome', 'user', 'destination', 'ip'])
    feature_keys = sorted(k for k in event.keys() if k not in skip_keys)
    for k in feature_keys:
        try:
            v = float(event[k])
        except Exception:
            v = float(abs(hash(str(event[k]))) % 1000) / 1000.0
        vec.append(v)

    # Pad or trim to fixed vector length for the model
    out = (vec + [0.0]*vector_len)[:vector_len]
    return np.array(out).reshape(1, -1)


def fit_anomaly_model(tenant_id: str, event_history: list):
    """
    Train IsolationForest on tenant's event history and persist model.
    `event_history` is a list of event dicts.
    """
    ensure_model_dir(tenant_id)
    X = np.vstack([feature_vector(ev) for ev in event_history])
    model = IsolationForest(n_estimators=150, random_state=42)
    model.fit(X)
    path = anomaly_model_path(tenant_id)
    joblib.dump(model, path)

def load_model_or_train_default(tenant_id: str):
    """
    Load IsolationForest model if exists, else train dummy default.
    """
    path = anomaly_model_path(tenant_id)
    if os.path.isfile(path):
        return joblib.load(path)
    # Fallback: train a default on synthetic "normal" events (for cold-start)
    dummy_events = [{"x": i} for i in range(20)]
    fit_anomaly_model(tenant_id, dummy_events)
    return joblib.load(path)

def predict_anomaly(tenant_id: str, event: dict):
    """
    Predict if event is anomalous for this tenant.
    Returns (is_anomaly: bool, anomaly_score: float)
    """
    model = load_model_or_train_default(tenant_id)
    feats = feature_vector(event)
    # Negate score: higher = more anomalous
    score = -model.decision_function(feats)[0]
    is_anomaly = score > 0.5 # Tune threshold for your use
    return is_anomaly, float(score)