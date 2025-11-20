import sys
import requests
from bs4 import BeautifulSoup

def blog_to_text(urls, output_file):
    """
    Extracts text from web pages given in the URLs and writes them to a single output file.
    
    :param urls: List of URLs to extract text from.
    :param output_file: The file where extracted text will be written.
    """
    # Initialize an empty string to hold the extracted text
    all_text = ''
    
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    }
        

    for url in urls:
        print(f"[INFO] Fetching URL: {url}")
        try:
            response = requests.get(url)
            response.raise_for_status()  # Raise an error for bad responses
            soup = BeautifulSoup(response.text, 'html.parser')

            # Extract text, you may want to refine this further
            page_text = soup.get_text(separator='\n', strip=True)
            all_text += page_text + "\n\n"  # Separate content from different pages

            print(f"[INFO] Successfully extracted text from {url}")

        except requests.exceptions.RequestException as e:
            print(f"[ERROR] Unable to fetch {url}: {e}")

    # Write the combined text to the output file
    try:
        with open(output_file, 'w', encoding='utf-8') as file:
            file.write(all_text)
            print(f"[INFO] Successfully wrote extracted text to {output_file}")
    except Exception as e:
        print(f"[ERROR] Unable to write to text file: {e}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python blog_to_text.py <url1> <url2> ... <urlN> <output_file>")
        sys.exit(1)

    # Separate URLs from the output file argument
    urls = sys.argv[1:-1]  # All arguments except the last one
    output_file = sys.argv[-1]  # The last one is the output file

    # Call the function to convert URLs to text
    blog_to_text(urls, output_file)