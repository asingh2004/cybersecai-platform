def format_high_risk_files_markdown(findings):
    out = []
    out.append("### All High Risk Files: Detailed Inventory\n")
    if not findings:
        out.append("_No high risk files found._")
        return "\n".join(out)
    for f in findings:
        out.append("---")
        out.append(f"#### **{f.get('file_name', '[No Name]')}**\n")
        out.append("**File Details:**")
        out.append(f"- **File Name:** {f.get('file_name','')}")
        out.append(f"- **Full Path:** {f.get('full_path','')}")
        out.append(f"- **Last Modified:** {f.get('last_modified','')}")
        out.append(f"- **Created:** {f.get('created','') or '_None_'}")
        out.append(f"- **Data Source:** {f.get('data_source','')}")
        out.append(f"- **Classification:** {f.get('data_classification','')}")
        out.append(f"- **Data Subject Area:** {f.get('likely_data_subject_area','')}")
        out.append(f"- **Overall Risk Rating:** {f.get('overall_risk_rating','')}")

        auditor_view = f.get("auditor_agent_view")
        if auditor_view:
            out.append(f"**Auditor View:**  \n> {auditor_view}")

        auditor_action = f.get("auditor_proposed_action")
        if auditor_action:
            out.append(f"**Auditor Proposed Action:**  \n{auditor_action}")

        controls = f.get("cyber_proposed_controls")
        if controls:
            out.append("**Cyber Proposed Controls:**")
            if isinstance(controls, list):
                for c in controls:
                    out.append(f"- {c}")
            else:
                out.append(f"{controls}")

        # Compliance Findings Table
        cf = f.get('compliance_findings', [])
        if cf:
            out.append("\n**Compliance Findings:**")
            out.append("| Standard | Jurisdiction | Detected Fields | Risk |")
            out.append("|----------|--------------|-----------------|------|")
            for c in cf:
                out.append(
                    f"| {c.get('standard','')} | {c.get('jurisdiction','')} | {', '.join(c.get('detected_fields', []) or [])} | {c.get('risk','')} |"
                )
        out.append("")  # Space after each file
    return "\n".join(out)

def format_all_risks_files_markdown(findings):
    out = []
    out.append("### All Risk Files: Detailed Inventory\n")
    if not findings:
        out.append("_No risk files found._")
        return "\n".join(out)
    for f in findings:
        # If you only want files with ANY risk, not "None" or blank:
        risk = (f.get('overall_risk_rating','') or '').lower()
        if risk in ['none', '', None]:
            continue
        out.append("---")
        out.append(f"#### **{f.get('file_name', '[No Name]')}**\n")
        out.append("**File Details:**")
        out.append(f"- **File Name:** {f.get('file_name','')}")
        out.append(f"- **Full Path:** {f.get('full_path','')}")
        out.append(f"- **Last Modified:** {f.get('last_modified','')}")
        out.append(f"- **Created:** {f.get('created','') or '_None_'}")
        out.append(f"- **Data Source:** {f.get('data_source','')}")
        out.append(f"- **Classification:** {f.get('data_classification','')}")
        out.append(f"- **Data Subject Area:** {f.get('likely_data_subject_area','')}")
        out.append(f"- **Overall Risk Rating:** {f.get('overall_risk_rating','')}")
        auditor_view = f.get("auditor_agent_view")
        if auditor_view:
            out.append(f"**Auditor View:**  \n> {auditor_view}")
        auditor_action = f.get("auditor_proposed_action")
        if auditor_action:
            out.append(f"**Auditor Proposed Action:**  \n{auditor_action}")
        controls = f.get("cyber_proposed_controls")
        if controls:
            out.append("**Cyber Proposed Controls:**")
            if isinstance(controls, list):
                for c in controls:
                    out.append(f"- {c}")
            else:
                out.append(f"{controls}")
        # Compliance Findings Table
        cf = f.get('compliance_findings', [])
        if cf:
            out.append("\n**Compliance Findings:**")
            out.append("| Standard | Jurisdiction | Detected Fields | Risk |")
            out.append("|----------|--------------|-----------------|------|")
            for c in cf:
                out.append(
                    f"| {c.get('standard','')} | {c.get('jurisdiction','')} | {', '.join(c.get('detected_fields', []) or [])} | {c.get('risk','')} |"
                )
        out.append("")  # Space after each file
    if len(out) == 1:
        out.append("_No risk files found._") # Only header; i.e. all were none risk.
    return "\n".join(out)


def format_medium_risk_files_markdown(findings):
    out = []
    out.append("### All Medium Risk Files: Detailed Inventory\n")
    found_any = False
    for f in findings:
        risk = (f.get('overall_risk_rating','') or '').lower()
        if risk != 'medium':
            continue
        found_any = True
        out.append("---")
        out.append(f"#### **{f.get('file_name', '[No Name]')}**\n")
        out.append("**File Details:**")
        out.append(f"- **File Name:** {f.get('file_name','')}")
        out.append(f"- **Full Path:** {f.get('full_path','')}")
        out.append(f"- **Last Modified:** {f.get('last_modified','')}")
        out.append(f"- **Created:** {f.get('created','') or '_None_'}")
        out.append(f"- **Data Source:** {f.get('data_source','')}")
        out.append(f"- **Classification:** {f.get('data_classification','')}")
        out.append(f"- **Data Subject Area:** {f.get('likely_data_subject_area','')}")
        out.append(f"- **Overall Risk Rating:** {f.get('overall_risk_rating','')}")
        auditor_view = f.get("auditor_agent_view")
        if auditor_view:
            out.append(f"**Auditor View:**  \n> {auditor_view}")
        auditor_action = f.get("auditor_proposed_action")
        if auditor_action:
            out.append(f"**Auditor Proposed Action:**  \n{auditor_action}")
        controls = f.get("cyber_proposed_controls")
        if controls:
            out.append("**Cyber Proposed Controls:**")
            if isinstance(controls, list):
                for c in controls:
                    out.append(f"- {c}")
            else:
                out.append(f"{controls}")
        # Compliance Findings Table
        cf = f.get('compliance_findings', [])
        if cf:
            out.append("\n**Compliance Findings:**")
            out.append("| Standard | Jurisdiction | Detected Fields | Risk |")
            out.append("|----------|--------------|-----------------|------|")
            for c in cf:
                out.append(
                    f"| {c.get('standard','')} | {c.get('jurisdiction','')} | {', '.join(c.get('detected_fields', []) or [])} | {c.get('risk','')} |"
                )
        out.append("")  # Space after each file
    if not found_any:
        out.append("_No medium risk files found._")
    return "\n".join(out)
