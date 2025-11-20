import sys
from PyPDF2 import PdfReader

def pdf_to_text(pdf_file, text_file):
    # Log message indicating the start of the PDF reading process
    print(f"[INFO] Starting to read PDF file: {pdf_file}")
    
    try:
        # Create a PDF file reader object
        pdf_reader = PdfReader(pdf_file)
    except Exception as e:
        print(f"[ERROR] Unable to read the PDF file: {e}")
        sys.exit(1)
    
    # Initialize an empty string to hold the extracted text
    text = ''
    
    # Loop through each page in the PDF and extract the text
    total_pages = len(pdf_reader.pages)
    print(f"[INFO] Found {total_pages} pages in PDF.")
    
    for page_number, page in enumerate(pdf_reader.pages, start=1):
        try:
            page_text = page.extract_text()
            text += page_text if page_text else ''
            print(f"[INFO] Extracting text from page {page_number}/{total_pages}...")
        except Exception as e:
            print(f"[WARNING] Error extracting text from page {page_number}: {e}")

    # Write the extracted text to the text file
    try:
        with open(text_file, 'w', encoding='utf-8') as file:
            file.write(text)
            print(f"[INFO] Successfully wrote extracted text to {text_file}")
    except Exception as e:
        print(f"[ERROR] Unable to write to text file: {e}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python pdf_to_text.py <pdf_file> <text_file>")
        sys.exit(1)
    
    pdf_file = sys.argv[1]
    text_file = sys.argv[2]
    
    # Call the function to convert PDF to text
    pdf_to_text(pdf_file, text_file)