import os
import sys
import logging
from openai import OpenAI

# Load the API key from the environment
API_KEY = os.getenv("OPENAI_API_KEY")
if API_KEY is None:
    logging.error("OpenAI API key is not set in the environment.")
    sys.exit(1)

client = OpenAI(api_key=API_KEY)

def main(assistant_id):
    # Create the thread
    thread = client.beta.threads.create()
    print(thread.id)  # Return the thread ID

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python createThread.py <assistant_id>")
        sys.exit(1)

    assistant_id = sys.argv[1]
    main(assistant_id)