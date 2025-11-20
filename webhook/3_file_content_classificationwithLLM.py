# production-ready, fully-commented Python script that:

# Finds all files with extensions: .txt, .pdf, .docx, .csv, .json (recursively in all subfolders where the script is run)
# Extracts text from each file using robust extraction logic (PyPDF2, python-docx, etc.)
# Uses GPT (OpenAI Chat API) and a detailed compliance schema to classify each file for regulated data by jurisdiction/standard/field (all compliance logic is in the GPT prompt)
# Writes results to a single output file for auditor or automation review

import os
import openai
from PyPDF2 import PdfReader
from docx import Document

# ========== USER CONFIG ==========
# Which file extensions to classify/PDF/text extract
SUPPORTED_EXT = ('.txt', '.pdf', '.docx', '.csv', '.json')
# Output report
OUTPUT_FILE = "pii_output_gpt_classification.txt"
# How many chars per file max to process (controls API cost, can increase!)
CHUNK_SIZE = 10000

# Compliance mapping for standards/fields – extend as needed
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

def find_supported_files(root_dir="."):
    """
    Recursively walk through the directory tree and find all files with SUPPORTED_EXT.
    
    Returns:
        List of full file paths, relative to root_dir.
    """
    matched_files = []
    for dirpath, _, filenames in os.walk(root_dir):
        for fname in filenames:
            if fname.lower().endswith(SUPPORTED_EXT):
                matched_files.append(os.path.join(dirpath, fname))
    return matched_files

def extract_text(file_path):
    """
    Extract text from .txt, .csv, .json, .docx, and .pdf files.
    Returns clean string, or '' if extraction fails.
    """
    ext = os.path.splitext(file_path)[1].lower()
    try:
        # Plaintext: .txt, .csv, .json
        if ext in ('.txt', '.csv', '.json'):
            with open(file_path, encoding='utf-8', errors='ignore') as f:
                return f.read()
        # PDF
        elif ext == '.pdf':
            with open(file_path, 'rb') as f:
                reader = PdfReader(f)
                text = ""
                for page in reader.pages:
                    page_text = page.extract_text() or ""
                    text += page_text
            return text
        # Word .docx
        elif ext == '.docx':
            doc = Document(file_path)
            return '\n'.join([p.text for p in doc.paragraphs])
    except Exception as e:
        print(f"Error reading {file_path}: {e}")
    return ''

def build_gpt_system_prompt():
    """
    Builds the LLM system prompt referencing the compliance table.
    """
    instructions = "You are an expert compliance auditor. Using the standards and fields below, analyze each document for regulated data. " \
        "For each applicable compliance standard, list:\n* The standard\n* The jurisdiction\n* Which relevant data types (fields) occur in this file.\n" \
        "If none, write 'No regulated data detected.'\n"
    instructions += "Compliance list:\n"
    for entry in COMPLIANCE_MATRIX:
        instructions += f"Standard: {entry['standard']} | Jurisdiction: {entry['jurisdiction']} | Fields: {', '.join(entry['fields'])}\n"
    return instructions

def gpt_classify_file(text):
    """
    Query OpenAI GPT model to classify the content based on compliance matrix.
    Returns GPT's reply string.
    """
    system_prompt = build_gpt_system_prompt()
    user_prompt = f"Classify the following file content for regulated data fields (see system context):\n\n{text[:CHUNK_SIZE]}"
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

def main():
    # 1. Set OpenAI API key from environment
    openai.api_key = os.environ.get("OPENAI_API_KEY")
    if not openai.api_key:
        print("Please set your OpenAI API key in the OPENAI_API_KEY environment variable.")
        return

    # 2. Find all supported files, recursively
    files = find_supported_files()
    print(f"Found {len(files)} files: {files}")

    results = []
    for fpath in files:
        print(f"\nProcessing file: {fpath}")
        text = extract_text(fpath)
        if not text or not text.strip():
            print(f"  Warning: no extractable text. Skipping file.")
            continue
        print(f"  Classifying with GPT…")
        gpt_result = gpt_classify_file(text)
        output_block = (
            f"\n==========================\n"
            f"File: {fpath}\n"
            f"{gpt_result}\n"
        )
        print(output_block)
        results.append(output_block)

    # 3. Write results to output file
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as outf:
        outf.write('\n'.join(results))
    print(f"\nDone. Compliance classification results written to {OUTPUT_FILE}.")

if __name__ == '__main__':
    main()