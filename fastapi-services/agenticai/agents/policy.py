from utils.logging import log_to_laravel
from utils.markdown_format import format_high_risk_files_markdown, format_all_risks_files_markdown, format_medium_risk_files_markdown
from utils.dateparse import parse_date_from_query
from typing import Dict, Any, Optional, List
import os, json, re, glob

def agent_policy_enforce(data: Dict[str, Any]):
    # From your previous code, nothing omitted
    changed_files, policy_actions, siem_events = [], [], []
    files = data['files']
    policies = data['policies']
    siem_url = data.get('siem_url', "")

    for fname, info in files.items():
        if info.get('changed'):
            changed_files.append(fname)
            compliance_decision = None
            if info.get('risk', '').upper() == "HIGH":
                comp_req = {
                    "standard": policies.get('standard', 'GDPR'),
                    "jurisdiction": policies.get('jurisdiction', 'Europe'),
                    "requirement_notes": policies.get('notes', ''),
                    "event_type": "Sensitive File Change",
                    "data": info
                }
                comp_res = agent_compliance_advisor(comp_req)
                policy_actions.append({
                    "file": fname, "compliance_decision": comp_res
                })
                compliance_decision = comp_res
            if info.get('policy_required'):
                policy_actions.append({
                    "file": fname, "action_taken": f"Policy {policies.get('enforce_type', 'Lock')} applied"
                })
            if siem_url:
                siem_event = {
                    "to": siem_url,
                    "event": {
                        "file": fname,
                        "delta": info.get('delta'),
                        "compliance_decision": compliance_decision
                    }
                }
                siem_events.append(siem_event)
    return {
        "changed_files": changed_files,
        "policy_actions": policy_actions,
        "siem_events": siem_events
    }