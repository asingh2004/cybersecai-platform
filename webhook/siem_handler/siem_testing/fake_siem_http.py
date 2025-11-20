# For Splunk, Elastic, Sentinel (they expect HTTP POST):

# Terminal 1: Start server

# python3 -m http.server 8000

## This scirpt python3 fake_siem_http.py

# save as fake_siem_http.py
from http.server import BaseHTTPRequestHandler, HTTPServer
class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        content = self.rfile.read(int(self.headers['Content-Length'])).decode()
        print("=== Received POST ===\n" + content)
        self.send_response(200)
        self.end_headers()
HTTPServer(('localhost', 8000), Handler).serve_forever()