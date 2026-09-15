import os
import shutil
import paramiko

files_to_sync = [
    ('d:\\App\\M.yunus\\mikhmon\\traffic\\resource_api.php', '/var/www/mikhmon/traffic/resource_api.php'),
    ('d:\\App\\M.yunus\\mikhmon\\system\\resource-graph.php', '/var/www/mikhmon/system/resource-graph.php'),
    ('d:\\App\\M.yunus\\mikhmon\\index.php', '/var/www/mikhmon/index.php'),
    ('d:\\App\\M.yunus\\mikhmon\\include\\menu.php', '/var/www/mikhmon/include/menu.php'),
    ('d:\\App\\M.yunus\\mikhmon\\dashboard\\home.php', '/var/www/mikhmon/dashboard/home.php'),
    ('d:\\App\\M.yunus\\mikhmon\\settings\\sessions.php', '/var/www/mikhmon/settings/sessions.php'),
]

# 1. Local backup sync
backup_roots = [
    'd:\\App\\M.yunus\\vps_backup\\var\\www\\mikhmon',
    'd:\\App\\M.yunus\\vps_backup\\var_www\\mikhmon'
]

for src, r_path in files_to_sync:
    rel_path = r_path.replace('/var/www/mikhmon/', '')
    for b_root in backup_roots:
        dest = os.path.join(b_root, rel_path.replace('/', '\\'))
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        shutil.copy2(src, dest)
        print(f"Local backup synced: {dest}")

# 2. Upload to VPS via Paramiko
try:
    from deploy_config import VPS_HOST, VPS_PORT, VPS_USER, VPS_PASS
except ImportError:
    VPS_HOST = os.environ.get('VPS_HOST', '202.10.46.222')
    VPS_PORT = int(os.environ.get('VPS_PORT', 22))
    VPS_USER = os.environ.get('VPS_USER', 'root')
    VPS_PASS = os.environ.get('VPS_PASS', '')

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(VPS_HOST, port=VPS_PORT, username=VPS_USER, password=VPS_PASS)
sftp = ssh.open_sftp()

for src, r_path in files_to_sync:
    remote_dir = os.path.dirname(r_path)
    try:
        sftp.stat(remote_dir)
    except IOError:
        ssh.exec_command(f"mkdir -p '{remote_dir}'")
    
    sftp.put(src, r_path)
    print(f"Uploaded to VPS: {r_path}")

# Set correct ownership on VPS
stdin, stdout, stderr = ssh.exec_command("chown -R nginx:nginx /var/www/mikhmon && chmod 755 /var/www/mikhmon/traffic/resource_api.php /var/www/mikhmon/system/resource-graph.php")
print(stdout.read().decode('utf-8'))
print(stderr.read().decode('utf-8'))

# Test API directly via php CLI on VPS
stdin, stdout, stderr = ssh.exec_command("php -r '$_SESSION[\"mikhmon\"] = \"admin\"; $_GET[\"session\"] = \"Rumah-DOLPHIN\"; include(\"/var/www/mikhmon/traffic/resource_api.php\");'")
res = stdout.read().decode('utf-8')
print("API Test Output:", res[:300] if len(res) > 300 else res)

ssh.close()
print("All sync and tests completed!")
