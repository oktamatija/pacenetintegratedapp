import os
import sys
sys.stdout.reconfigure(encoding='utf-8')
import paramiko
from deploy_config import VPS_USER, VPS_PASS

SERVERS = [
    ("hy0045.my.id", "202.10.46.222"),
    ("hi1271.my.id", "202.10.47.76")
]

LOCAL_APP_DIR = r"D:\App\M.yunus\pacenetintegratedapp\app"
REMOTE_APP_DIR = "/var/www/pacenetintegratedapp/app"

for domain, ip in SERVERS:
    print(f"\n==========================================")
    print(f"[*] Deploying to {domain} ({ip})...")
    print(f"==========================================")
    try:
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        ssh.connect(ip, port=22, username=VPS_USER, password=VPS_PASS, timeout=10)
        
        # Ensure remote directories exist
        ssh.exec_command(f"mkdir -p {REMOTE_APP_DIR}/assets")
        
        sftp = ssh.open_sftp()
        
        # Upload all files from LOCAL_APP_DIR
        for root, dirs, files in os.walk(LOCAL_APP_DIR):
            for file in files:
                local_path = os.path.join(root, file)
                rel_path = os.path.relpath(local_path, LOCAL_APP_DIR).replace('\\', '/')
                remote_path = f"{REMOTE_APP_DIR}/{rel_path}"
                
                remote_dir = os.path.dirname(remote_path)
                try:
                    sftp.stat(remote_dir)
                except FileNotFoundError:
                    ssh.exec_command(f"mkdir -p {remote_dir}")
                
                sftp.put(local_path, remote_path)
                print(f"  [+] Uploaded: {rel_path} ({os.path.getsize(local_path)} bytes)")
        
        # Also upload updated API files
        api_files = ["common.php", "generate.php", "profiles.php", "reports.php", "watchdog_expire.php"]
        for af in api_files:
            local_f = os.path.join(r"D:\App\M.yunus\pacenetintegratedapp\api", af)
            remote_f = f"/var/www/pacenetintegratedapp/api/{af}"
            sftp.put(local_f, remote_f)
            print(f"  [+] Uploaded API: {remote_f}")
        
        sftp.close()
        
        # Clean up old asset bundles on remote server
        active_js = [f for f in os.listdir(os.path.join(LOCAL_APP_DIR, 'assets')) if f.endswith('.js')][0]
        active_css = [f for f in os.listdir(os.path.join(LOCAL_APP_DIR, 'assets')) if f.endswith('.css')][0]
        ssh.exec_command(f'find {REMOTE_APP_DIR}/assets -type f ! -name "{active_js}" ! -name "{active_css}" -delete')

        # Set proper permissions and test nginx
        ssh.exec_command(f"chmod -R 755 {REMOTE_APP_DIR}")
        for af in api_files:
            ssh.exec_command(f"chmod 644 /var/www/pacenetintegratedapp/api/{af}")
            ssh.exec_command(f"php -l /var/www/pacenetintegratedapp/api/{af}")
        ssh.exec_command("systemctl reload nginx")
        
        # Verify index.html on server
        stdin, stdout, stderr = ssh.exec_command(f"head -n 20 {REMOTE_APP_DIR}/index.html")
        print(f"  [*] Remote index.html preview:\n{stdout.read().decode('utf-8')[:300]}")
        
        ssh.close()
        print(f"[✓] Deployment to {domain} ({ip}) SUCCESSFUL!")
    except Exception as e:
        print(f"[!] Error deploying to {domain} ({ip}): {e}")

print("\nAll servers deployed successfully.")
