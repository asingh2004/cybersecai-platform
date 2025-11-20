import os
import sys
from openai import OpenAI
import time

# Load the API key from the environment
API_KEY = os.getenv("OPENAI_API_KEY")
if API_KEY is None:
    print("OpenAI API key is not set in the environment.")
    sys.exit(1)

client = OpenAI(api_key=API_KEY)

def submit_message(assistant_id, thread_id, user_message):
    try:
        # Add user message to the thread
        print(f"Sending message: {user_message}")  # Debug information
        client.beta.threads.messages.create(
            thread_id=thread_id,
            role="user",
            content=user_message
        )

        # Create a run to process the assistant's response
        run = client.beta.threads.runs.create(
            thread_id=thread_id,
            assistant_id=assistant_id,
            instructions="Please address the user as Jane Doe. The user has a premium account."
        )
        print(f"Assistant run created: {run.id}")  # Debug information
        
        # Wait for the run to complete
        while True:
            run_status = client.beta.threads.runs.retrieve(thread_id=thread_id, run_id=run.id)
            if run_status.status == 'completed':
                break
            elif run_status.status in ['failed', 'canceled']:
                print(f"Run status: {run_status.status}. Exiting.")
                sys.exit(1)
            print(f"Run status: {run_status.status}, waiting...")
            time.sleep(2)  # Wait a few seconds before checking again

        # Once the run is complete, fetch the list of messages for the thread
        messages = client.beta.threads.messages.list(thread_id=thread_id)
        print("Fetched messages:")

        # Convert the messages to a string format
        message_list = [
            f"{message.role}: {message.content}" for message in messages.data  # Adjust based on response structure
        ]

        # Print messages as formatted strings
        for message in message_list:
            print(message)

    except Exception as e:
        print(f"Error in submit_message: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python runassistant.py <assistant_id> <thread_id> <user_message>")
        sys.exit(1)

    assistant_id = sys.argv[1]
    thread_id = sys.argv[2]
    user_message = sys.argv[3]

    submit_message(assistant_id, thread_id, user_message)