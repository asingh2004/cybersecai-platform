import os
import sys
import logging
from openai import OpenAI

# Load the API key from the environment or another secure location
API_KEY = os.getenv("OPENAI_API_KEY")
if API_KEY is None:
    logging.error("OpenAI API key is not set in the environment.")
    sys.exit(1)

# Initialize OpenAI client
client = OpenAI(api_key=API_KEY)

# Function to create an assistant and return its ID
def create_assistant(name, instructions, model):
    assistant = client.beta.assistants.create(
        name=name,
        instructions=instructions,
        model=model,
    )
    return assistant.id  # Access the 'id' property of the assistant object

# Accept user input for name, instructions, and model from command-line arguments
if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python assistant.py <name> <instructions> <model>")
        sys.exit(1)

    name = sys.argv[1]  # First argument
    instructions = sys.argv[2]  # Second argument
    model = sys.argv[3]  # Third argument

    # Create the assistant and get the assistant ID
    assistant_id = create_assistant(name, instructions, model)

    # Output the assistant ID
    print(assistant_id)  # Make sure only the ID is printed