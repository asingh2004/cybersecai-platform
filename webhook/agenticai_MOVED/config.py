import os
import openai

OPENAI_API_KEY = os.environ.get("OPENAI_API_KEY")
if not OPENAI_API_KEY:
    raise RuntimeError("OPENAI_API_KEY environment variable not set.")
LARAVEL_LOG_PATH = '/home/cybersecai/htdocs/www.cybersecai.io/storage/logs/laravel.log'
CSV_EXPORT_DIR = "/home/cybersecai/htdocs/www.cybersecai.io/webhook/agenticai/tmp_csv"
BASE_DIR = "/home/cybersecai/htdocs/www.cybersecai.io/webhook"

client = openai.OpenAI(api_key=OPENAI_API_KEY)