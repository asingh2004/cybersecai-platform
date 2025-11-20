import smbclient
import os

server = "195.35.11.166"
share_name = "smbshare"
username = "smbuser"
password = "smb@Adlon2025"
smb_path = f"//{server}/{share_name}"

smbclient.ClientConfig(username=username, password=password)


def list_files_and_read(smb_path):
    try:
        files = smbclient.listdir(smb_path)
        print(f"Files in {smb_path}:")
        for file in files:
            file_path = os.path.join(smb_path, file)
            print(f"- {file}")
            try:
                with smbclient.open_file(file_path, mode='rb') as fd:
                    content = fd.read()
                    print(f"Content of {file}:")
                    print(content.decode('utf-8', errors='ignore'))
            except Exception as e:
                print(f"Could not read file {file}: {e}")
    except Exception as e:
        print(f"Could not list files in {smb_path}: {e}")


list_files_and_read(smb_path)
