# healingapi/llm_xai.py

import os
import openai

# Set your OpenAI API key (set as env variable to avoid hardcoding)
openai.api_key = os.environ.get("OPENAI_API_KEY")

def explain_decision(event: dict, anomaly_score: float, action: str):
    """
    Generate a business-style AI explanation for a detection/response choice.
    """
    prompt = (
        "You are an XAI security advisor. Given the following event:\n"
        f"{event}\n"
        f"with anomaly score: {anomaly_score:.2f}\n"
        f"And the chosen action: {action}\n"
        "Explain, concisely and in plain English, why this response was chosen."
    )
    resp = openai.ChatCompletion.create(
        model="gpt-4.1",
        temperature=0.3,
        max_tokens=128,
        messages=[{"role": "user", "content": prompt}]
    )
    return resp.choices[0].message.content.strip()