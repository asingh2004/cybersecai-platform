1. Change ALL existing files/folders to group webapps
sudo chown -R :webapps /home/cybersecai/htdocs/www.cybersecai.io/
This will set the group webapps recursively on all files and directories.

2. Set GID bit (Sticky Bit) on all directories
This will ensure that all new files/folders created within will inherit the webapps group.

sudo find /home/cybersecai/htdocs/www.cybersecai.io/ -type d -exec chmod g+s {} \;
3. (Optional) Ensure group webapps has write permissions
If you want the group to be able to write to the files/folders:

sudo chmod -R g+rw /home/cybersecai/htdocs/www.cybersecai.io/