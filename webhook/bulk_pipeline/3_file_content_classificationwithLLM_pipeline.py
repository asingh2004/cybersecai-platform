import sys, json, os

# Setup your compliance matrix as in your initial code
COMPLIANCE_MATRIX = [
    {
        "standard": "HIPAA",
        "jurisdiction": "USA",
        "fields": [
            "Name", "Date of Birth", "Medical Record Number", "Social Security Number",
            "Medicare Number", "Health Plan Beneficiary Number", "Insurance Account Number",
            "Diagnosis Information", "Treatment Information", "Medical Data", "Clinical Notes",
            "Biometric Data", "Address", "Phone Number", "Email"
        ]
    },
    {
        "standard": "GDPR",
        "jurisdiction": "EU/EEA (& Global orgs handling EU data)",
        "fields": [
            "Name", "Date of Birth", "National ID", "Passport Number", "Driver's License Number", "Health Data",
            "Genetic Data", "Biometric Data", "Address", "Phone Number", "Email", "Bank Account Number",
            "Credit Card Number", "Religious Data", "Political Views", "Ethnicity", "Location Data", "Login Credentials"
        ]
    },
    {
        "standard": "UK GDPR",
        "jurisdiction": "UK",
        "fields": [
            "Name", "Date of Birth", "National Insurance Number", "NHS Number", "Password", "Address", "Phone Number", "Health Data", "Location Data"
        ]
    },
    {
        "standard": "CJIS",
        "jurisdiction": "USA",
        "fields": [
            "Name", "DOB", "SSN", "FBI/State ID Number", "Case Number", "Criminal Charges", "Mugshots", "Fingerprints", "Criminal Records"
        ]
    },
    {
        "standard": "FERPA",
        "jurisdiction": "USA",
        "fields": [
            "Name", "DOB", "Student ID", "Parent Name", "Grades", "Transcript", "Disciplinary Records", "Address"
        ]
    },
    {
        "standard": "TPN",
        "jurisdiction": "Media/Global",
        "fields": [
            "Title", "Script", "Actor Name", "Crew Name", "Release Date", "Budget", "Pre-release Media", "Marketing Plans"
        ]
    },
    {
        "standard": "SEC",
        "jurisdiction": "USA",
        "fields": [
            "Name", "DOB", "Tax ID", "Bank Account", "Audit Record", "Insider Trading Data", "Financial Statements"
        ]
    },
    {
        "standard": "FEDRAMP",
        "jurisdiction": "USA Fed Cloud/Data",
        "fields": [
            "Name", "DOB", "SSN", "Credentials", "Authorization Keys", "System Config", "Transaction History"
        ]
    },
    {
        "standard": "Australia Privacy Act",
        "jurisdiction": "Australia",
        "fields": [
            "Name", "DOB", "Tax File Number", "Driver’s License", "Medicare", "Passport Number", "Health Data", "Financial Account"
        ]
    },
    {
        "standard": "PCI DSS",
        "jurisdiction": "Global/Payment",
        "fields": [
            "PAN (Credit Card Number)", "Cardholder Name", "Expiration Date", "CVV", "Billing Address", "Transaction Details"
        ]
    },
    # Add further if required.
]

def build_gpt_system_prompt():
    instructions = "You are an expert compliance auditor ... (as before) ..."
    for entry in COMPLIANCE_MATRIX:
        instructions += f"Standard: {entry['standard']} | Jurisdiction: {entry['jurisdiction']} | Fields: {', '.join(entry['fields'])}\n"
    return instructions

def gpt_classify_file(text):
    import openai
    openai.api_key = os.environ.get("OPENAI_API_KEY")
    system_prompt = build_gpt_system_prompt()
    user_prompt = f"Classify the following file content for regulated data fields (see system context):\n\n{text[:10000]}"
    try:
        response = openai.chat.completions.create(
            model="gpt-4.1",
            messages=[
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt}
            ],
            temperature=0.0,
        )
        return response.choices[0].message.content.strip()
    except Exception as e:
        return f"GPT API error: {e}"

def contains_pii(llm_response):
    # Heuristic: If LLM lists any compliance standard (not "no regulated data"), then yes
    if "No regulated data" in llm_response or "no regulated data" in llm_response:
        return "No PII"
    return "Has PII"

def main():
    if len(sys.argv) != 2:
        print(json.dumps({"contains_PII":"Error","LLM_Response":"Missing param"}))
        return
    job = json.loads(sys.argv[1])
    text = job["text"]
    llm_response = gpt_classify_file(text)
    pii = contains_pii(llm_response)
    print(json.dumps({
        "contains_PII": pii,
        "LLM_Response": llm_response
    }))

if __name__=='__main__':
    main()