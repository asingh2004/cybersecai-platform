from pydantic import BaseModel
from typing import List, Optional, Dict, Any

class ComplianceFinding(BaseModel):
    standard: Optional[str]
    jurisdiction: Optional[str]
    detected_fields: Optional[List[str]] = []
    risk: Optional[str]

class FileFinding(BaseModel):
    file_name: Optional[str]
    last_modified: Optional[str]
    created: Optional[str]
    file_path: Optional[str]
    full_path: Optional[str]
    compliance_findings: Optional[List[ComplianceFinding]] = []
    auditor_agent_view: Optional[str]
    data_classification: Optional[str]
    likely_data_subject_area: Optional[str]
    overall_risk_rating: Optional[str]
    cyber_proposed_controls: Optional[Any]  # Can be List or str
    auditor_proposed_action: Optional[str]
    permissions: Optional[Any]
    data_source: Optional[str]