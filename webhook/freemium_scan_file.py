# What’s Supported now
# PDF: First 8 pages as text
# DOCX: First 300 paragraphs
# XLS/XLSX: First 50 rows, all columns CSV
# CSV: First 50 lines (header+rows)
# JSON: First 5 objects or top-level keys (pretty JSON)
# XML: First 5000 chars (element text)
# TXT/other: First ~40KB as text


import sys, openai, os, json


def extract_text(file_path):
    ext = os.path.splitext(file_path)[1].lower()
    if ext == ".pdf":
        try:
            import pdfplumber
            with pdfplumber.open(file_path) as pdf:
                text = ""
                for page in pdf.pages[:8]:
                    text += page.extract_text() or ''
            return text
        except Exception as ex:
            try:
                from PyPDF2 import PdfReader
                reader = PdfReader(file_path)
                text = ""
                for i, page in enumerate(reader.pages[:8]):
                    text += page.extract_text() or ""
                return text
            except Exception:
                return ""
    elif ext == ".docx":
        try:
            from docx import Document
            doc = Document(file_path)
            text = ""
            for i, para in enumerate(doc.paragraphs):
                text += para.text + "\n"
                if i > 300: break
            return text
        except Exception:
            return ""
    elif ext in [".xls", ".xlsx"]:
        try:
            import pandas as pd
            df = pd.read_excel(file_path, nrows=50)  # Only first 50 rows
            text = df.to_csv(index=False, line_terminator="\n")
            return text
        except Exception:
            return ""
    elif ext == ".csv":
        try:
            import pandas as pd
            df = pd.read_csv(file_path, nrows=50)
            text = df.to_csv(index=False, line_terminator="\n")
            return text
        except Exception:
            # fallback: manual read first lines
            try:
                with open(file_path, 'r', encoding='utf-8') as f:
                    lines = ""
                    for i, line in enumerate(f):
                        lines += line
                        if i >= 50: break
                    return lines
            except Exception:
                return ""
    elif ext == ".json":
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                data = json.load(f)
                if isinstance(data, list):
                    text = json.dumps(data[:5], indent=2)
                elif isinstance(data, dict):
                    items = list(data.items())[:5]
                    text = json.dumps(dict(items), indent=2)
                else:
                    text = str(data)
                return text
        except Exception:
            return ""
    elif ext == ".xml":
        try:
            import lxml.etree as ET
            with open(file_path, 'rb') as f:
                tree = ET.parse(f)
                root = tree.getroot()
                # Grab first ~5000 text content chars from the XML
                text = "".join([et.text or "" for et in root.iter() if et.text])
                return text[:5000]
        except Exception:
            try:
                with open(file_path, 'r', encoding='utf-8') as f:
                    xml = f.read(5000)
                    return xml
            except Exception:
                return ""
    else:
        # txt or unknown: just try to decode first 40k bytes
        try:
            with open(file_path, 'rb') as f:
                filecontent = f.read(40000)
                try: text = filecontent.decode('utf-8')
                except: text = str(filecontent)
            return text
        except Exception:
            return ""

if len(sys.argv) != 3:
    print(json.dumps({"error":"Usage: scan_file.py <file_path> <openai_key>"}))
    exit(1)

file_path = sys.argv[1]
OPENAI_API_KEY = sys.argv[2]

text = extract_text(file_path)
if not text.strip():
    print(json.dumps({"error":f"Could not read file or no text found"}))
    exit(1)

prompt = f"""You are an AI compliance expert. Read the following file content (snippet), do ALL:
\"\"\"
{text[:8000]}
\"\"\"
1. Based on the text and your knowledge of compliance standards, does this file include regulated fields relevant to GDPR, CCPA, or the Australian Privacy Act?
2. List the types of sensitive data detected (e.g., name, email, health, credit card, etc.), only if these fields have actual data in it.
3. Give the highest applicable regulatory risk: (High, Medium, Low, None) per the most strict standard detected.
4. In 2-3 sentences, explain your reasoning (plain English, for auditors) and proposed_action (e.g. remediate, escalate, notify, do_nothing). Output as JSON with: regulation, risk, proposed_action, summary, fields.
"""


# prompt = f"""You are an AI compliance expert. Read the following file content (snippet), do ALL:
# \"\"\"
# {text[:8000]}
# \"\"\"
# 1. List regulations and risks detected (GDPR/CCPA/etc).
# 2. Output "proposed_action" (e.g. remediate, escalate, notify, do_nothing).
# 3. Give a plain-language explanation ("Audit justification").
# 4. Output as JSON: {{ fields, regulation, risk, explanation, proposed_action }}
# """

try:
    client = openai.OpenAI(api_key=OPENAI_API_KEY)
    resp = client.chat.completions.create(
        model="gpt-4.1",
        messages=[
            {"role": "system", "content": "You are a compliance and privacy expert."},
            {"role": "user", "content": prompt}
        ],
        temperature=0.2,
        max_tokens=350
    )
    answer = resp.choices[0].message.content
    import re, ast
    try:
        jsonstr = re.search(r'\{[\s\S]+?\}', answer).group(0)
        data = ast.literal_eval(jsonstr.replace('null','None'))
    except Exception:
        data = {"summary": answer, "fields": [], "regulation":"Unknown", "risk":"None"}
except Exception as ex:
    data = {"summary": str(ex), "fields": [], "regulation":"Error", "risk":"None"}

print(json.dumps(data))