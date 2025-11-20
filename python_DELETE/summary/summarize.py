import os
import sys
import logging
from typing import List, Tuple, Optional
from openai import OpenAI
import tiktoken
from tqdm import tqdm

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

def get_chat_completion(messages, model):
    try:
        # logging.info("Getting chat completion...")
        response = client.chat.completions.create(
            model=model,
            messages=messages,
            temperature=0,
        )
        summary_content = response.choices[0].message.content
        # logging.info("Received summary from OpenAI.")
        logging.info(f"\n\n{summary_content}")  # Log the raw summary content
        return summary_content
    except Exception as e:
        logging.error(f"Error while getting chat completion: {str(e)}")
        sys.exit(1)

def tokenize(text: str) -> List[str]:
    encoding = tiktoken.encoding_for_model('gpt-4o-mini')
    return encoding.encode(text)

def chunk_on_delimiter(input_string: str,
                       max_tokens: int, delimiter: str) -> List[str]:
    chunks = input_string.split(delimiter)
    combined_chunks, _, dropped_chunk_count = combine_chunks_with_no_minimum(
        chunks, max_tokens, chunk_delimiter=delimiter, add_ellipsis_for_overflow=True
    )
    if dropped_chunk_count > 0:
        print(f"warning: {dropped_chunk_count} chunks were dropped due to overflow")
    combined_chunks = [f"{chunk}{delimiter}" for chunk in combined_chunks]
    return combined_chunks

def combine_chunks_with_no_minimum(
        chunks: List[str],
        max_tokens: int,
        chunk_delimiter="\n\n",
        header: Optional[str] = None,
        add_ellipsis_for_overflow=False,
) -> Tuple[List[str], List[int]]:
    dropped_chunk_count = 0
    output = []  # list to hold the final combined chunks
    output_indices = []  # list to hold the indices of the final combined chunks
    candidate = (
        [] if header is None else [header]
    )  # list to hold the current combined chunk candidate
    candidate_indices = []
    for chunk_i, chunk in enumerate(chunks):
        chunk_with_header = [chunk] if header is None else [header, chunk]
        if len(tokenize(chunk_delimiter.join(chunk_with_header))) > max_tokens:
            print(f"warning: chunk overflow")
            if (
                add_ellipsis_for_overflow
                and len(tokenize(chunk_delimiter.join(candidate + ["..."]))) <= max_tokens
            ):
                candidate.append("...")
                dropped_chunk_count += 1
            continue  # this case would break downstream assumptions
        # estimate token count with the current chunk added
        extended_candidate_token_count = len(tokenize(chunk_delimiter.join(candidate + [chunk])))
        # If the token count exceeds max_tokens, add the current candidate to output and start a new candidate
        if extended_candidate_token_count > max_tokens:
            output.append(chunk_delimiter.join(candidate))
            output_indices.append(candidate_indices)
            candidate = chunk_with_header  # re-initialize candidate
            candidate_indices = [chunk_i]
        # otherwise keep extending the candidate
        else:
            candidate.append(chunk)
            candidate_indices.append(chunk_i)
    # add the remaining candidate to output if it's not empty
    if (header is not None and len(candidate) > 1) or (header is None and len(candidate) > 0):
        output.append(chunk_delimiter.join(candidate))
        output_indices.append(candidate_indices)
    return output, output_indices, dropped_chunk_count


def summarize(text: str, 
			detail: float = 0.5, 
            additional_instructions: Optional[str] = None, 
            name: str = '', 
            instructions: str = '', 
            ai_formal_name: str = '', 
            summarize_recursively=False, 
            verbose=False):
    """
    Summarizes a given text by splitting it into chunks, each of which is summarized individually. 
    The level of detail in the summary can be adjusted, and the process can optionally be made recursive.

    Parameters:
    - text (str): The text to be summarized.
    - detail (float, optional): A value between 0 and 1 indicating the desired level of detail in the summary.
      0 leads to a higher level summary, and 1 results in a more detailed summary. Defaults to 0.
    - model (str, optional): The model to use for generating summaries. Defaults to 'gpt-3.5-turbo'.
    - additional_instructions (Optional[str], optional): Additional instructions to provide to the model for customizing summaries.
    - minimum_chunk_size (Optional[int], optional): The minimum size for text chunks. Defaults to 500.
    - chunk_delimiter (str, optional): The delimiter used to split the text into chunks. Defaults to ".".
    - summarize_recursively (bool, optional): If True, summaries are generated recursively, using previous summaries for context.
    - verbose (bool, optional): If True, prints detailed information about the chunking process.

    Returns:
    - str: The final compiled summary of the text.

    The function first determines the number of chunks by interpolating between a minimum and a maximum chunk count based on the `detail` parameter. 
    It then splits the text into chunks and summarizes each chunk. If `summarize_recursively` is True, each summary is based on the previous summaries, 
    adding more context to the summarization process. The function returns a compiled summary of all chunks.
    """

    # check detail is set correctly
    assert 0 <= detail <= 1

    # Interpolate the number of chunks based on specified level of detail
    minimum_chunk_size = 500
    chunk_delimiter = "."
    
    max_chunks = len(chunk_on_delimiter(text, minimum_chunk_size, chunk_delimiter))
    min_chunks = 1
    num_chunks = int(min_chunks + detail * (max_chunks - min_chunks))
    
    # Adjust chunk size based on interpolated number of chunks
    document_length = len(tokenize(text))
    chunk_size = max(minimum_chunk_size, document_length // num_chunks)
    text_chunks = chunk_on_delimiter(text, chunk_size, chunk_delimiter)

    if verbose:
        print(f"Splitting the text into {len(text_chunks)} chunks to be summarized.")
        print(f"Chunk lengths are {[len(tokenize(x)) for x in text_chunks]}")

    # Set system message with additional instructions if provided
    # system_message_content = "Rewrite this text in summarized form."
    #system_message_content = f"{instructions}\n" + \
    #	'''You are expert in concise writing and summarising.
    #	You are an AI assistant that can adapt into an expert Persona based on the topic to summarise.
    #	Your task is to create an expert summary that reflects critical and analytical thinking, including areas of improvement.

    #	Your tone of voice must be formal, polite, straightforward, and concise.

    #	Use dot points, tables, numbering, and bold text to make your responses more readable and engaging.
    #	You MUST format your responses using Markdown. You MUST use en-GB spelling.'''
        
    system_message_content = f"{instructions}\n" + \
    	'''You are expert in concise writing and summarising. You MUST format your responses using Markdown. You MUST use en-GB spelling.'''
    if additional_instructions is not None:
        system_message_content += f"\n\n{additional_instructions}"

    accumulated_summaries = []
    for chunk in tqdm(text_chunks):
        if summarize_recursively and accumulated_summaries:
            accumulated_summaries_string = '\n\n'.join(accumulated_summaries)
            user_message_content = f"Previous summaries:\n\n{accumulated_summaries_string}\n\nText to summarize next:\n\n{chunk}"
        else:
            user_message_content = chunk

        messages = [
            {"role": "system", "content": system_message_content},
            {"role": "user", "content": user_message_content}
        ]

        # Get the summary using the provided model
        summary_text = get_chat_completion(messages, model=ai_formal_name)
        accumulated_summaries.append(summary_text)

    final_summary = '\n\n'.join(accumulated_summaries)

    return final_summary


if __name__ == "__main__":
    if len(sys.argv) < 7:  # Update to expect 7 args, including the new ones
        logging.error("Usage: python summarize.py <text_file> <detail> <additional_instructions> <name> <instructions> <ai_formal_name>")
        sys.exit(1)

    # Get all parameters from command line
    text_file = sys.argv[1]
    detail = float(sys.argv[2])
    additional_instructions = sys.argv[3] if len(sys.argv) > 3 else None
    name = sys.argv[4]
    instructions = sys.argv[5]
    ai_formal_name = sys.argv[6]

    try:
        with open(text_file, 'r', encoding='utf-8') as f:
            text = f.read()
    except Exception as e:
        logging.error(f"Error reading file: {str(e)}")
        sys.exit(1)

    summary = summarize(text, detail, additional_instructions, name, instructions, ai_formal_name)  # Pass new parameters
    
    if not summary:
        logging.warning("Summary is empty after processing.")
    else:
        logging.info("Summary generated successfully.")