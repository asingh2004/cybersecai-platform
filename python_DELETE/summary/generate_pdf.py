import sys
from fpdf import FPDF

def create_pdf(text, output_path):
    pdf = FPDF()
    pdf.add_page()
    pdf.set_font('Arial', 'I', 12)
    
    for line in text.split('\n'):
        pdf.cell(0, 10, line, 0, 1)

    pdf.output(output_path)

if __name__ == "__main__":
    text = sys.argv[1]
    output_path = sys.argv[2]
    create_pdf(text, output_path)