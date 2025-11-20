/agents
   /agent_compliance.py
   /agent_policy.py
   /agent_findings.py
   /agent_audit.py
/utils
   /markdown.py
   /docx_export.py
   /logger.py
/config.py
orchestrator.py



Drop-in folder/filenames
/utils/dlp_utils.py
/utils/llm_analysis.py
/utils/lineage_utils.py
/utils/reporting_utils.py
/utils/trigger_utils.py


SUMMARY Table
Use Case	Modular Utility Function(s)	Storage-aware?	AI/LLM Boost?
DLP Policy	filter_by_policy_triggers, get_permission_exposures, llm_policy_analysis	YES	YES
Data Classify	auto_tag_label	YES	YES
Lineage/Audit	build_file_lineage	Often (for context)	(Downstream, if needed)
Federated Reporting	federated_reporting	YES	Optionally for trends
Alert/Review	trigger_alerts_on_events	YES	YES (summary/context)
