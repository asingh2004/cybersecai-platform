from google.oauth2 import service_account
from googleapiclient.discovery import build

SCOPES = [
  'https://www.googleapis.com/auth/admin.directory.user.readonly',
  'https://www.googleapis.com/auth/drive.readonly'
]
creds = service_account.Credentials.from_service_account_file(
  '/home/cybersecai/htdocs/www.cybersecai.io/webhook/GDRIVE/cybersecaiapi-90ed3561da10.json', scopes=SCOPES, subject='info@cybersecai.io'
)
admin = build('admin', 'directory_v1', credentials=creds, cache_discovery=False)
print(admin.users().list(customer='my_customer', maxResults=10).execute())