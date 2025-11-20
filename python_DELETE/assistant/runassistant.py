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

def submit_messages(assistant_id, thread_id, user_messages):
    try:
        for user_message in user_messages:
            # Add user message to the thread
            #print(f"Sending message: {user_message}")  # Debug information
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
            print(f"Thread: {thread_id}")  # Debug information
            
            # Wait for the run to complete
            while True:
                run_status = client.beta.threads.runs.retrieve(thread_id=thread_id, run_id=run.id)  # Correct usage
                if run_status.status == 'completed':
                    break
                elif run_status.status in ['failed', 'canceled']:
                    #print(f"Run status: {run_status.status}. Exiting.")
                    sys.exit(1)
                #print(f"Run status: {run_status.status}, waiting...")
                time.sleep(2)  # Wait a few seconds before checking again
            
            # Once the run is complete, fetch the list of messages for the thread
            messages = client.beta.threads.messages.list(thread_id=thread_id)
            print("Fetched messages:")

            # Convert the messages to a string format
            message_list = [
                f"{message.role}: {message.content}" for message in messages.data  # Adjust based on response structure
            ]

			#print(f"Fetched messages:{message_list}")
            
            # Print messages as formatted strings
            for message in message_list:
                print(message)
			
            #for message in message_list:
    		#try:
        	#	formatted_message = format_to_html(message)
        	#	print(formatted_message)
    		#except Exception as e:
        		#print(f"Error formatting message: {message}. Error: {str(e)}")

    except Exception as e:
        print(f"Error in submit_messages: {str(e)}")
        sys.exit(1)


def format_to_html(text):
    # 1. Make 'Assistant:' bold
    formatted_text = text.replace("Assistant:", "<strong>Assistant:</strong>")
    
    # 2. Remove text after 'Assistant:' to 'value='
    start_index = formatted_text.find("value='")
    if start_index != -1:
        formatted_text = formatted_text[:start_index + 7]  # Keep 'value='
    
    # 3. Replace specified newline patterns with line breaks
    formatted_text = formatted_text.replace('\n\n###', "<br><br><h3>")
    formatted_text = formatted_text.replace('\n\n---\n\n', "<br><br><hr><br><h4>")
    formatted_text = formatted_text.replace('\n\n', "<br><br>")
    formatted_text = formatted_text.replace('\n', "<br>")
    
    # 4. Wrap bold text for numbered items
    # This finds all instances of numbered items and makes them bold
    formatted_text = formatted_text.replace('#### ', "<h4><strong>")
    formatted_text = formatted_text.replace("\n**", "</strong><br>**")  # format impact items
    formatted_text = formatted_text.replace('</strong><br>**', '</strong><br><strong>')  # wrap bold events or impacts
    
    # Closing tags for h4
    formatted_text = formatted_text.replace('</strong>', "</strong></h4>")
    
    return formatted_text


if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Usage: python runassistant.py <assistant_id> <thread_id> <user_message1> [<user_message2> ...]")
        sys.exit(1)

    assistant_id = sys.argv[1]
    thread_id = sys.argv[2]
    user_messages = sys.argv[3:]  # Gather all subsequent arguments as a list of messages

    submit_messages(assistant_id, thread_id, user_messages)