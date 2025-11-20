# healingapi/tenant_store.py

import os

# Use environment variable or fallback to 'models'
MODELDIR = os.getenv("MODELDIR", "models")

def ensure_tenant_dir(tenant_id):
    """
    Ensure the model directory for a given tenant exists.
    Returns the full absolute path.
    """
    path = os.path.join(MODELDIR, tenant_id)
    os.makedirs(path, exist_ok=True)
    return path

def anomaly_path(tenant_id):
    """
    Returns full path to the anomaly model (IsolationForest) for the tenant.
    """
    return os.path.join(MODELDIR, tenant_id, "anomaly.pkl")

def drl_path(tenant_id):
    """
    Returns full path to the RL model file for the tenant (Stable Baselines policy).
    """
    return os.path.join(MODELDIR, tenant_id, "drl_policy.zip")

def event_log_path(tenant_id):
    """
    Optional: Path for storing raw event logs per tenant (can be used for retraining).
    """
    return os.path.join(MODELDIR, tenant_id, "events.json")