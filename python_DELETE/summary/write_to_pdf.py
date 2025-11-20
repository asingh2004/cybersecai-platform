import os
import sys  # <- Add this import statement
import random
import nltk
from fpdf import FPDF
from nltk.tokenize import sent_tokenize

# Download the "punkt" package
nltk.download('punkt')

"""
This function accepts a string as input and uses the NLTK library to
break down the whole text into paragraphs. Then it converts it into a PDF file.
And stores the PDF file on the current working directory.
"""
def write_to_pdf(text, filename):    
    # Tokenize the summary into paragraphs
    summary_paras = sent_tokenize(text)
    
    # Instantiate the FPDF class
    pdf = FPDF(orientation='P',  # Portrait
               unit='in',  # inches
               format='A4'  # A4 Page
               )
    
    # Path to the fonts
    font_path = "/home/cybersecai/htdocs/www.cybersecai.io/public/fonts/"

    # Add the font files for Times New Roman
    pdf.add_font(family="Times", style="", fname=font_path + "times new roman.ttf", uni=True)
    pdf.add_font(family="Times", style="B", fname=font_path + "times new roman bold.ttf", uni=True)
    pdf.add_font(family="Times", style="I", fname=font_path + "times new roman italic.ttf", uni=True)
    pdf.add_font(family="Times", style="BI", fname=font_path + "times new roman bold italic.ttf", uni=True)
    
    # Set margins to 1 inch
    pdf.set_margins(left=1, top=1, right=1)
    
    # Add a page
    pdf.add_page()

    # Set the style to Bold & Italic, and size to 20 for the first page
    pdf.set_font(family="Times", style='BI', size=20)
    pdf.multi_cell(w=6.27, h=1, txt="AI.IO Summary", align='C')
    
    # Add a new page
    pdf.add_page()

    # Set the style to normal and font size to 12
    pdf.set_font(family="Times", style='', size=12)
    
    sentence_num = random.randrange(start=4, stop=8)
    sentence_count = 0
    paragraph = "     "
    for sentence in summary_paras:
        sentence_count += 1
        if sentence_count > sentence_num:
            # Insert the paragraph in pdf
            pdf.multi_cell(w=6.27, h=0.285, txt=paragraph, align='')
            # Insert a line-break
            pdf.cell(w=0, h=0.285, txt="", ln=1, align='')
            sentence_count = 0
            sentence_num = random.randrange(start=4, stop=8)
            paragraph = "     "
        paragraph += " " + sentence
        
    # Insert the last paragraph in pdf
    pdf.multi_cell(w=6.27, h=0.285, txt=paragraph, align='')
    
    # Save the pdf with name .pdf
    pdf.output(filename)

    print(f"PDF successfully saved as {filename}.")

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Usage: python write_to_pdf.py <input_file> <filename>")
        sys.exit(1)

    input_file = sys.argv[1]

    # Read the input file
    with open(input_file, 'r') as file:
        text = file.read()

    filename = sys.argv[2]

    # Call the function to write to PDF
    write_to_pdf(text, filename)