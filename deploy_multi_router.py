import os
import shutil
import paramiko

# 1. Collect all API files and App build files from backend/ to sync
local_backend = 'd:\\App\\M.yunus\\backend'
remote_root = '/var/www/mikhmon'

files_to_sync = [
    (os.path.join(local_backend, 'index.php'), f'{remote_root}/index.php'),
    (os.path.join(local_backend, 'join.php'), f'{remote_root}/join.php'),
]

# Add API directory files
api_dir = os.path.join(local_backend, 'api')
if os.path.exists(api_dir):
    for f in os.listdir(api_dir):
        if f.endswith('.php'):
            files_to_sync.append((os.path.join(api_dir, f), f'{remote_root}/api/{f}'))

# Add Lib directory files
lib_dir = os.path.join(local_backend, 'lib')
if os.path.exists(lib_dir):
    for f in os.listdir(lib_dir):
        if f.endswith('.php'):
            files_to_sync.append((os.path.join(lib_dir, f), f'{remote_root}/lib/{f}'))

# Add Traffic directory files
traffic_dir = os.path.join(local_backend, 'traffic')
if os.path.exists(traffic_dir):
    for f in os.listdir(traffic_dir):
        if f.endswith('.php'):
            files_to_sync.append((os.path.join(traffic_dir, f), f'{remote_root}/traffic/{f}'))

# Add Hotspot-login captive portal files
hl_dir = os.path.join(local_backend, 'hotspot-login')
if os.path.exists(hl_dir):
    for f in os.listdir(hl_dir):
        files_to_sync.append((os.path.join(hl_dir, f), f'{remote_root}/hotspot-login/{f}'))

# Add App build directory files recursively
app_dir = os.path.join(local_backend, 'app')
if os.path.exists(app_dir):
    for root, dirs, files in os.walk(app_dir):
        for f in files:
            full_local = os.path.join(root, f)
            rel = os.path.relpath(full_local, app_dir)
            files_to_sync.append((full_local, f'{remote_root}/app/{rel.replace("\\", "/")}'))

print(f"Total files to deploy from backend/: {len(files_to_sync)}", flush=True)

# 2. Local backup update
backup_roots = [
    'd:\\App\\M.yunus\\vps_backup\\var\\www\\mikhmon',
    'd:\\App\\M.yunus\\vps_backup\\var_www\\mikhmon'
]

for src, r_path in files_to_sync:
    rel_path = r_path.replace(f'{remote_root}/', '')
    for b_root in backup_roots:
        dest = os.path.join(b_root, rel_path.replace('/', '\\'))
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        shutil.copy2(src, dest)

print("Local backups synchronized.", flush=True)

# 3. Connect to VPS
try:
    from deploy_config import VPS_HOST, VPS_PORT, VPS_USER, VPS_PASS
except ImportError:
    VPS_HOST = os.environ.get('VPS_HOST', '202.10.46.222')
    VPS_PORT = int(os.environ.get('VPS_PORT', 22))
    VPS_USER = os.environ.get('VPS_USER', 'root')
    VPS_PASS = os.environ.get('VPS_PASS', '')

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(VPS_HOST, port=VPS_PORT, username=VPS_USER, password=VPS_PASS, timeout=10)

# Pre-create all remote directories in one command
_, stdout, _ = ssh.exec_command("mkdir -p /var/www/mikhmon/api /var/www/mikhmon/lib /var/www/mikhmon/traffic /var/www/mikhmon/hotspot-login /var/www/mikhmon/app/assets")
stdout.channel.recv_exit_status()

sftp = ssh.open_sftp()
for src, r_path in files_to_sync:
    sftp.put(src, r_path)
    print(f"Uploaded: {r_path}", flush=True)

sftp.close()
print("All files uploaded successfully.", flush=True)

# 4. Check PHP syntax on VPS for all .php files
print("\nValidating PHP syntax on VPS:", flush=True)
for _, r_path in files_to_sync:
    if r_path.endswith('.php'):
        _, stdout, stderr = ssh.exec_command(f"php -l {r_path}")
        res = stdout.read().decode('utf-8').strip()
        err = stderr.read().decode('utf-8').strip()
        if "No syntax errors detected" not in res:
            print(f"ERROR on {r_path}: {res} {err}", flush=True)
        else:
            print(f"OK: {r_path}", flush=True)

# 5. Update Nginx configuration for /app/ SPA routing
nginx_conf_cmd = """
cat << 'EOF' > /etc/nginx/conf.d/mikhmon.conf
# HTTP Server (Port 80)
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name hy0045.my.id 202.10.46.222 _;
    root /var/www/mikhmon;
    index index.php index.html;

    # Allow ACME Challenge for Let's Encrypt
    location /.well-known/acme-challenge/ {
        root /var/www/mikhmon;
    }

    # Allow RouterOS join bootstrap without redirect
    location = /join.php {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Allow Hotspot Login directly on HTTP without SSL warnings on captive portals
    location /hotspot-login/ {
        try_files $uri $uri/ /hotspot-login/index.php?$query_string;

        location ~ \\.php$ {
            include fastcgi_params;
            fastcgi_pass unix:/run/php-fpm/www.sock;
            fastcgi_read_timeout 300s;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_index index.php;
        }
    }

    # Redirect all other HTTP requests to HTTPS domain
    location / {
        return 301 https://hy0045.my.id$request_uri;
    }
}

# Dedicated MikroTik Port 8080 (No redirects, reliable for RouterOS fetch)
server {
    listen 8080 default_server;
    listen [::]:8080 default_server;
    server_name _;
    root /var/www/mikhmon;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \\.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_read_timeout 300s;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_index index.php;
    }

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico)$ {
        expires -1;
        add_header Cache-Control "no-store, no-cache, must-revalidate, max-age=0";
    }

    location ~ /\\.ht {
        deny all;
    }
}

# HTTPS Server (Port 443) - hy0045.my.id
server {
    listen 443 ssl http2 default_server;
    listen [::]:443 ssl http2 default_server;
    server_name hy0045.my.id;
    root /var/www/mikhmon;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/hy0045.my.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/hy0045.my.id/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # If accessing by IP over HTTPS, redirect to domain
    if ($host != "hy0045.my.id") {
        return 301 https://hy0045.my.id$request_uri;
    }

    # Modern React SPA at /app/
    location /app/ {
        alias /var/www/mikhmon/app/;
        index index.html;
        try_files $uri $uri/ /app/index.html;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \\.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_read_timeout 300s;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_index index.php;
    }

    location ~* \\.(js|css|png|jpg|jpeg|gif|ico)$ {
        expires -1;
        add_header Cache-Control "no-store, no-cache, must-revalidate, max-age=0";
    }

    location ~ /\\.ht {
        deny all;
    }
}
EOF
"""
_, stdout, _ = ssh.exec_command(nginx_conf_cmd)
stdout.channel.recv_exit_status()

# 6. Test Nginx and reload
_, stdout, stderr = ssh.exec_command("nginx -t && systemctl reload nginx")
print("\nNginx test & reload:", flush=True)
print(stdout.read().decode('utf-8'), flush=True)
print(stderr.read().decode('utf-8'), flush=True)

# 7. Set correct permissions and enable Watchdog Expire cron
_, stdout, _ = ssh.exec_command("chown -R nginx:nginx /var/www/mikhmon && chmod -R 755 /var/www/mikhmon/app /var/www/mikhmon/api && chmod +x /var/www/mikhmon/api/watchdog_expire.php")
stdout.channel.recv_exit_status()

cron_setup = """
(crontab -l 2>/dev/null | grep -v 'watchdog_expire.php'; echo "* * * * * /usr/bin/php /var/www/mikhmon/api/watchdog_expire.php >/dev/null 2>&1") | crontab -
"""
_, stdout, _ = ssh.exec_command(cron_setup)
stdout.channel.recv_exit_status()
print("Watchdog Expire cron configured and active (every 1 min).", flush=True)

ssh.close()
print("\nReact SPA and REST APIs deployed and operational at https://hy0045.my.id/app/ !", flush=True)

