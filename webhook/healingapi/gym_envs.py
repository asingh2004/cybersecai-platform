# healingapi/gym_envs.py

import gymnasium as gym
from gymnasium import spaces
import numpy as np

ACTION_MAP = {
    0: "isolate_host",
    1: "patch",
    2: "ignore",
    3: "escalate",
    4: "notify"
}

class CyberSelfHealingEnv(gym.Env):
    """
    Custom RL Gym environment for self-healing cybersecurity agent.
    Provide event_history list of dicts [{event1}, {event2}, ...].
    """
    def __init__(self, event_history):
        super().__init__()
        self.action_space = spaces.Discrete(len(ACTION_MAP))
        self.observation_space = spaces.Box(low=0, high=1, shape=(21,))
        self.event_history = event_history or [{"dummy": 0}]
        self.idx = 0
        self.current_obs = self._get_obs()

    def reset(self):
        self.idx = 0
        self.current_obs = self._get_obs()
        return self.current_obs

    def step(self, action):
        anomaly_score = self.current_obs[0]
        is_attack = anomaly_score > 0.7
        # Reward: correct action on high anomaly = 1, escalate/notify = 0.5, etc.
        if is_attack and action == 0:   # Isolate on attack
            reward = 1.0
        elif is_attack and action in (3, 4):  # Escalate/notify on attack
            reward = 0.5
        else:
            reward = 0.05

        self.idx += 1
        done = self.idx >= len(self.event_history)
        self.current_obs = self._get_obs()
        info = {}
        return self.current_obs, reward, done, info

    def _get_obs(self):
        if self.idx < len(self.event_history):
            raw_event = self.event_history[self.idx]
            feat = []
            for k in sorted(raw_event):
                v = raw_event[k]
                try:
                    feat.append(float(v))
                except Exception:
                    feat.append(abs(hash(str(v))) % 1000 / 1000.0)
            anomaly_score = np.clip(feat[0] if feat else 0.5, 0, 1)
            obs = [anomaly_score] + feat[1:]
        else:
            obs = [0.0]
        return np.array((obs + [0.0] * 21)[:21])
