1. ComplianceAdvisorController.php

Path: app/Http/Controllers/AgenticAI/InternalAuditorController.php

Role:
Handles requests to generate an AI-powered audit report for  business sensitive data, tailored for the board.

What it does:

Shows a form for region selection for Board level report.

On submit, GATHERS all classified/monitored data (here as a dummy $json_data array, but you will fetch real records from your DB or parse your actual classified JSON files).

Sends this data and region to the Python /agentic/audit endpoint.

Receives a markdown-formatted board report and displays it.

2. ComplianceAdvisorController.php

Path: app/Http/Controllers/AgenticAI/ComplianceAdvisorController.php

Role:
Acts as an AI "compliance officer" to analyze privacy/security events for correct regulatory (GDPR/etc) handling.

What it does:

Shows a form to enter standard, jurisdiction, notes, event type, and factual data (as JSON), or you can pre-fill/generate this from your logs/data.
Validates and parses that data.
Calls Python /agentic/compliance_advisor with those inputs.
Receives a structured JSON (risk/action/comment/notification) from the agent and renders it.
Where to integrate:
You can pre-populate the form or load the data JSON field dynamically from your internal event/log system.

3. PolicyEnforcerController.php

Path: app/Http/Controllers/AgenticAI/PolicyEnforcerController.php

Role:
Monitors designated folders for JSON file changes (policy enforcement) and coordinates handovers to Compliance Advisor and SIEM logger as needed.

What it does:

Prepares a list of all files you’re tracking, along with their "change" state (detected however you wish), their risk (already classified or re-calculated in your pipeline), their change "delta", and whether any policy must be applied.
Passes all this to Python /agentic/policy_enforce endpoint along with policies/config/SIEM information.
Displays what files were changed, what policy decisions or handovers happened, and SIEM event logs.
Where to integrate:
Replace the $files array with your real file monitoring logic. For example, you could:

Scan a folder for all .json files
Compare file hashes to previously stored hashes (in database or a .hash file)
If hash differs, load new and "last" state and compute a delta (changed/added/removed fields)
Set the 'changed' flag and risk value appropriately


How agent handover works
Policy Enforcer (PHP controller) passes changed files and their metadata to FastAPI /agentic/policy_enforce.
The Python microservice automatically detects high-risk changes and calls its own /agentic/compliance_advisor endpoint (in-process) on those, returning both the policy action and compliance decision back to the frontend.
You get all results (policy actions, compliance decisions, SIEM log events) reported back to the user in the Blade views.
Guardrails are applied in Python (schema validation, output safety checks).
