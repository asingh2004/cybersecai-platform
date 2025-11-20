from datetime import datetime
from config import LARAVEL_LOG_PATH

def log_to_laravel(message: str):
    timestamp = datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')
    with open(LARAVEL_LOG_PATH, 'a', encoding='utf-8') as log_file:
        log_file.write(f"[{timestamp}] python.INFO: {message}\n")