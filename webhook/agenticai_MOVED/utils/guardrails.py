import re
from fastapi import HTTPException

def output_guardrails(text: str) -> None:
    bad_words = ['racist', 'intercourse']  # Expand as needed
    if any(re.search(rf"\b{w}\b", text, re.I) for w in bad_words):
        raise HTTPException(400, "Inappropriate content detected in LLM Output")
    if len(text) > 30000:
        raise HTTPException(400, "Output exceeds max length")