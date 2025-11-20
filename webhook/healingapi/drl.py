# healingapi/drl.py

# This implementation will maximize sensitivity and agentic response to dangerous data breach/exfil/encrypt scenarios in a real-world, per-tenant, production SaaS managed security system.

import os
import numpy as np
from stable_baselines3 import PPO
from .gym_envs import CyberSelfHealingEnv, ACTION_MAP
from .anomaly import feature_vector  # Use the *same feature engineering* as in anomaly.py

### --------- Model Management -----------

def get_rl_model_path(tenant_id):
    """Return the path for a tenant's RL model."""
    return f"models/{tenant_id}/model_rl.zip"

def ensure_tenant_dir(tenant_id):
    """Ensure folder for RL models exists."""
    path = f"models/{tenant_id}"
    os.makedirs(path, exist_ok=True)
    return path

### --------- Training -----------

def train_drl_policy(tenant_id, event_history):
    """
    Train/retrain a DRL PPO policy for this tenant using event history.
    Includes reward shaping for breach/exfil/encryption scenarios.
    """
    ensure_tenant_dir(tenant_id)
    env = CyberSelfHealingEnv(event_history)
    # You may wish to tune hyperparameters for larger workloads
    model = PPO("MlpPolicy", env, verbose=1, n_steps=2048, batch_size=64)
    model.learn(total_timesteps=100_000)
    model.save(get_rl_model_path(tenant_id))
    print(f"[DRL] Trained model for tenant: {tenant_id}")

### --------- State Extraction (Inference) -----------

def event_to_obs(event, anomaly_score):
    """
    Converts event dict + anomaly_score to the env's expected observation vector.
    Should mirror what is used in the RL environment. Uses feature_vector from anomaly module.
    """
    # Use the same vectorization that your RL env uses
    obs = feature_vector(event, vector_len=21).flatten()  # Set 21 as env shape
    obs[0] = anomaly_score  # Optionally, ensure anomaly_score is first feature slot
    return obs.reshape(1, -1)

def take_action(tenant_id, event, anomaly_score):
    """
    Selects best action for the event+anomaly_score using trained PPO for tenant.
    Returns: string ('isolate_host', 'patch', etc.) from ACTION_MAP.
    """
    model_path = get_rl_model_path(tenant_id)
    if not os.path.isfile(model_path):
        # Fallback action if not trained yet
        return "notify"
    model = PPO.load(model_path)
    obs = event_to_obs(event, anomaly_score)
    action, _ = model.predict(obs, deterministic=True)
    # Maps numeric action to business-playbook action
    return ACTION_MAP[int(action)]

### --------- Continuous Learning/Periodic Retrain -----------

def continuous_retrain(tenant_id, event_history):
    """
    Call with new event_history to regularly refresh tenant RL policy.
    Use in a background job, cron, or triggered by performance drift.
    """
    train_drl_policy(tenant_id, event_history)