
from agents.compliance import agent_compliance_advisor
from agents.audit import (
    agent_audit_dispatcher,
    agent_audit_dashboard,
    agent_audit_evidence,
    agent_audit_board_summary,
    agent_audit_full,
    agent_audit_no_action,
    agent_audit_compliance_advisory,
    agent_audit_find_risk_hotspots,
    agent_audit_continuous_alerts,
)
from agents.findings import agent_findings_facts
from agents.compliance import agent_compliance_m365_auto_evidence
from agents.cybersec import agent_cybersec, agent_cybersec_show_external, agent_cybersec_find_duplicates, agent_cybersec_recommendations
from agents.pentest_llm import agentic_pentest_llm
from agents.findings import agent_high_risk_csv_batch, agent_allrisk_csv_batch
from agents.summarizer import agent_summarizer_stats

AGENT_SCHEMAS = {
    "compliance": {
        "required": ["standard", "jurisdiction", "event_type", "data"],
    },
    "audit": {"required": ["user_id"]},   # Now like cybersec (expect user_id as minimum)
    "audit_dashboard": {"required": ["user_id"]},
    "audit_evidence": {"required": ["user_id"]},
    "audit_board_summary": {"required": ["user_id"]},
    "audit_full": {"required": ["user_id"]},
    "audit_no_action": {"required": ["user_id"]},
    "audit_compliance_advisory": {"required": ["user_id"]},
    "audit_find_risk_hotspots": {"required": ["user_id"]},
    "audit_continuous_alerts": {"required": ["user_id"]},
    "findings_facts": {
        "required": ["operation", "config_ids", "args"],
    },
    "m365_compliance_auto": {
        "required": ["config_ids", "corporate_domains"],
    },
    "pentest_auto": {
        "required": ["domain"],
    },
    "summarizer_stats": {
        "required": ["user_id"],    # args optional
        "optional": ["args"]
    },
    "cybersec": {"required": ["user_id"]},
    "cybersec_show_external": {"required": ["user_id"]},
    "cybersec_find_duplicates": {"required": ["user_id"]},
    "cybersec_recommendations": {"required": ["user_id"]},
    "high_risk_csv_batch": {'required':['config_ids','user_id']},
    "allrisk_csv_batch": {'required':['config_ids','user_id']}
}

AGENT_HANDLERS = {
    "audit": agent_audit_dispatcher,
    "audit_dashboard": agent_audit_dashboard,
    "audit_evidence": agent_audit_evidence,
    "audit_board_summary": agent_audit_board_summary,
    "audit_full": agent_audit_full,
    "audit_no_action": agent_audit_no_action,
    "audit_compliance_advisory": agent_audit_compliance_advisory,
    "audit_find_risk_hotspots": agent_audit_find_risk_hotspots,
    "audit_continuous_alerts": agent_audit_continuous_alerts,
    "compliance": agent_compliance_advisor,
    "findings_facts": agent_findings_facts,
    "m365_compliance_auto": agent_compliance_m365_auto_evidence,
    "cybersec": agent_cybersec,
    "cybersec_show_external": agent_cybersec_show_external,
    "cybersec_find_duplicates": agent_cybersec_find_duplicates,
    "cybersec_recommendations": agent_cybersec_recommendations,
    "pentest_auto": lambda context: agentic_pentest_llm(context),
    "high_risk_csv_batch": agent_high_risk_csv_batch,
    "summarizer_stats": agent_summarizer_stats,
    "allrisk_csv_batch": agent_allrisk_csv_batch
}