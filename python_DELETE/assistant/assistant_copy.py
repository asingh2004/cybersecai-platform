import os
import sys
import logging
from openai import OpenAI, AssistantEventHandler
from typing import List

# Configure logging
logging.basicConfig(level=logging.INFO,
                    format='%(asctime)s - %(levelname)s - %(message)s')

# Load the API key from the environment or another secure location
API_KEY = os.getenv("OPENAI_API_KEY")
if API_KEY is None:
    logging.error("OpenAI API key is not set in the environment.")
    sys.exit(1)

# Initialize OpenAI client
client = OpenAI(api_key=API_KEY)

class CustomEventHandler(AssistantEventHandler):
    def on_text_created(self, text) -> None:
        print(f"\nassistant > {text}", flush=True)

    def on_tool_call_created(self, tool_call):
        print(f"\nassistant > {tool_call.type}\n", flush=True)

    def on_message_done(self, message) -> None:
        message_content = message.content[0].text
        annotations = message_content.annotations
        citations = []
        for index, annotation in enumerate(annotations):
            message_content.value = message_content.value.replace(
                annotation.text, f"[{index}]"  # Replace the text with references
            )
            if file_citation := getattr(annotation, "file_citation", None):
                cited_file = client.files.retrieve(file_citation.file_id)
                citations.append(f"[{index}] {cited_file.filename}")

        print(message_content.value)
        print("\n".join(citations))


class AssistantManager:
    def __init__(self):
        self.client = client  # Use the initialized API client

    def create_assistant(self, name: str = "Financial Analyst Assistant", instructions: str = "You are an expert financial analyst. Use your knowledge base to answer questions about audited financial statements.", model: str = "gpt-4o-mini", tools: List[dict] = [{"type": "file_search"}]):
        assistant = self.client.beta.assistants.create(
            name=name,
            instructions=instructions,
            model=model,
            tools=tools,
        )
        return assistant

    def create_vector_store(self, vector_store_name: str):
        vector_store = self.client.beta.vector_stores.create(name=vector_store_name)
        return vector_store

    def upload_files_to_vector_store(self, vector_store_id: str, file_paths: List[str]):
        file_streams = [open(path, "rb") for path in file_paths]
        file_batch = self.client.beta.vector_stores.file_batches.upload_and_poll(
            vector_store_id=vector_store_id, files=file_streams
        )
        for stream in file_streams:  # Close file streams after upload
            stream.close()
        print(file_batch.status)
        print(file_batch.file_counts)

    def update_assistant_with_vector_store(self, assistant_id: str, vector_store_id: str):
        assistant = self.client.beta.assistants.update(
            assistant_id=assistant_id,
            tool_resources={"file_search": {"vector_store_ids": [vector_store_id]}},
        )
        return assistant

    def upload_file_and_create_thread(self, file_path: str, content: str):
        message_file = self.client.files.create(
            file=open(file_path, "rb"), purpose="assistants"
        )
        
        thread = self.client.beta.threads.create(
            messages=[
                {
                    "role": "user",
                    "content": content,
                    "attachments": [
                        {"file_id": message_file.id, "tools": [{"type": "file_search"}]}
                    ],
                }
            ]
        )
        print(thread.tool_resources.file_search)
        return thread

    def stream_thread_response(self, thread_id: str, assistant_id: str, instructions: str):
        with self.client.beta.threads.runs.stream(
                thread_id=thread_id,
                assistant_id=assistant_id,
                instructions=instructions,
                event_handler=CustomEventHandler(),
        ) as stream:
            stream.until_done()


# Example usage
if __name__ == "__main__":
    manager = AssistantManager()
    
    # Replace the following inputs with your own if needed
    vector_store_name = "Cyber Security Expert"
    file_paths = ["/home/cybersecai/htdocs/www.cybersecai.io/public/storage/app/public/summary_MS7mmVtaDq.pdf", "/home/cybersecai/htdocs/www.cybersecai.io/public/storage/app/public/summary_MS7mmVtaDq.pdf"]  # Optional input
    user_file_path = "/home/cybersecai/htdocs/www.cybersecai.io/public/storage/app/public/summary_MS7mmVtaDq.pdf"  # Example user file path
    user_question = "How many zones are there?"

    # Create assistant with default values
    assistant = manager.create_assistant()
    
    # Create vector store
    vector_store = manager.create_vector_store(vector_store_name)
    
    # Upload files to the vector store
    manager.upload_files_to_vector_store(vector_store.id, file_paths)
    
    # Update assistant with the vector store
    manager.update_assistant_with_vector_store(assistant.id, vector_store.id)
    
    # Upload a user file and create a thread
    thread = manager.upload_file_and_create_thread(user_file_path, user_question)
    
    # Stream the response
    manager.stream_thread_response(thread.id, assistant.id, assistant.instructions)