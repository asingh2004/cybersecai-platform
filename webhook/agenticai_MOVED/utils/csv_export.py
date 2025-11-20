import csv
import os
import uuid


def get_existing_csv_download_link(filename):
    # Sanitize filename so no path traversal
    basename = os.path.basename(filename)
    if not basename.endswith('.csv'):
        raise ValueError("Only CSV files allowed")
    return f"/download_csv?file={basename}"

def get_csv_download_link(findings):
    base_dir = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv"
    os.makedirs(base_dir, exist_ok=True)  # Always create the tmp_csv folder if it doesn't exist

    csv_name = f"highrisk_{uuid.uuid4().hex}.csv"
    csv_path = os.path.join(base_dir, csv_name)
    if findings:
        fields = set()
        for f in findings:
            fields.update(f.keys())
        csv_rows = []
        for f in findings:
            row = dict(f)
            if isinstance(row.get('compliance_findings'), list):
                row['compliance_findings'] = '; '.join(str(x) for x in row['compliance_findings'])
            if isinstance(row.get('permissions'), list):
                row['permissions'] = '; '.join(str(x) for x in row['permissions'])
            csv_rows.append(row)
        with open(csv_path, "w", newline="", encoding="utf-8") as fcsv:
            writer = csv.DictWriter(fcsv, fieldnames=sorted(fields))
            writer.writeheader()
            writer.writerows(csv_rows)
    csv_url = f"/download_csv?file={csv_name}"
    return csv_url

# -------------- Add this below! -----------------
from fastapi import HTTPException
from fastapi.responses import FileResponse

def download_csv(file: str):
    base_dir = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv"
    file_path = os.path.join(base_dir, file)
    if not os.path.exists(file_path) or not file.endswith('.csv'):
        raise HTTPException(status_code=404, detail="File not found")
    return FileResponse(
        file_path,
        media_type='text/csv',
        filename=os.path.basename(file_path)
    )