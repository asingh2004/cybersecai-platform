import sys
import json

def format_messages(messages):
    formatted_messages = []
    for message in messages:
        if ": " in message:  # Check if message contains the expected format
            role, content = message.split(': ', 1)  # Only split on the first occurrence
            # Replace newline characters with HTML <br> tags for formatting
            formatted_content = content.replace("\r\n", "<br>").replace("\n", "<br>").replace("\r", "<br>")
            if role == 'assistant':
                formatted_messages.append(f"<strong>Assistant:</strong> {formatted_content}")
            else:
                formatted_messages.append(f"<strong>You:</strong> {formatted_content}")
        else:
            formatted_messages.append("<strong>Note:</strong> Unexpected message format: " + message)

    return formatted_messages

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("No messages provided.")
        sys.exit(1)

    # Read the input from command line arguments and parse as JSON
    try:
        messages = json.loads(sys.argv[1])
        formatted_messages = format_messages(messages)
        print(json.dumps(formatted_messages))  # Print the formatted messages as a JSON array
    except Exception as e:
        print(f"Error parsing messages: {str(e)}")
        sys.exit(1)