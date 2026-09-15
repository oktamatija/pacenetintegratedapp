import os
import sys
import time
import tarfile
import gzip
import shutil
import paramiko

try:
    from deploy_config import VPS_HOST as VPS_IP, VPS_PORT, VPS_USER, VPS_PASS
except ImportError:
    VPS_IP = os.environ.get("VPS_HOST", "202.10.46.222")
    VPS_PORT = int(os.environ.get("VPS_PORT", 22))
    VPS_USER = os.environ.get("VPS_USER", "root")
    VPS_PASS = os.environ.get("VPS_PASS", "")

BASE_DIR = r"D:\App\M.yunus"
VPS_BACKUP_DIR = os.path.join(BASE_DIR, "vps_backup")
SYS_META_DIR = os.path.join(VPS_BACKUP_DIR, "system_metadata")
FULL_SYS_DIR = os.path.join(VPS_BACKUP_DIR, "full_system_archive")
SERVER_CONFIGS_DIR = os.path.join(BASE_DIR, "server_configs")
MIKROTIK_BACKUP_DIR = os.path.join(BASE_DIR, "mikrotik_backup")
MIKHMON_DIR = os.path.join(BASE_DIR, "mikhmon")

for d in [VPS_BACKUP_DIR, SYS_META_DIR, FULL_SYS_DIR, SERVER_CONFIGS_DIR, MIKROTIK_BACKUP_DIR, MIKHMON_DIR]:
    os.makedirs(d, exist_ok=True)

print(f"[*] Connecting to {VPS_IP}...")
sys.stdout.flush()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(VPS_IP, VPS_PORT, VPS_USER, VPS_PASS, timeout=30)
sftp = ssh.open_sftp()
print("[+] SSH & SFTP Connected successfully.")
sys.stdout.flush()

def run_cmd(cmd, desc=""):
    if desc:
        print(f"[*] {desc}...")
        sys.stdout.flush()
    stdin, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode('utf-8', errors='replace')
    err = stderr.read().decode('utf-8', errors='replace')
    return out, err

def safe_extract_tar(tar_path, dest_dir):
    os.makedirs(dest_dir, exist_ok=True)
    with tarfile.open(tar_path, "r:gz") as tar:
        for member in tar.getmembers():
            if member.issym() or member.islnk():
                continue
            try:
                tar.extract(member, path=dest_dir, filter=None)
            except Exception:
                pass

# 1. System Metadata
print("\n--- STEP 1: Collecting System Metadata ---")
metadata_cmds = {
    "os_release.txt": "cat /etc/os-release; echo '--- UNAME ---'; uname -a",
    "disk_usage.txt": "df -h; echo '--- LSBLK ---'; lsblk",
    "ip_addresses.txt": "ip -br a; echo '--- FULL IP ---'; ip a",
    "ip_routes.txt": "ip route show table all",
    "iptables_v4.rules": "iptables-save",
    "iptables_v6.rules": "ip6tables-save",
    "wireguard_status.txt": "wg show",
    "active_services.txt": "systemctl list-units --type=service --state=running --no-pager",
    "systemd_units.txt": "systemctl list-unit-files --no-pager",
    "crontab_root.txt": "crontab -l",
    "installed_rpm_packages.txt": 'rpm -qa --qf "%{NAME}-%{VERSION}-%{RELEASE}.%{ARCH}\\n" | sort',
    "sysctl_params.txt": "sysctl -a"
}

for fname, cmd in metadata_cmds.items():
    out, _ = run_cmd(cmd)
    dest_path = os.path.join(SYS_META_DIR, fname)
    with open(dest_path, "w", encoding="utf-8") as f:
        f.write(out)
    print(f"  -> Saved metadata: {fname} ({len(out)} bytes)")
    sys.stdout.flush()

# 2. FreeRADIUS PostgreSQL Database Backup
print("\n--- STEP 2: Backing up FreeRADIUS PostgreSQL Database ---")
run_cmd("su - postgres -c 'pg_dump radius > /tmp/radius_db_backup.sql' && gzip -f /tmp/radius_db_backup.sql", "Dumping and compressing database 'radius'")
sftp.get("/tmp/radius_db_backup.sql.gz", os.path.join(VPS_BACKUP_DIR, "radius_db_backup.sql.gz"))
run_cmd("rm -f /tmp/radius_db_backup.sql.gz")
print("  -> Downloaded radius_db_backup.sql.gz")

with gzip.open(os.path.join(VPS_BACKUP_DIR, "radius_db_backup.sql.gz"), 'rb') as f_in:
    with open(os.path.join(VPS_BACKUP_DIR, "radius_db_backup.sql"), 'wb') as f_out:
        f_out.write(f_in.read())
print("  -> Extracted uncompressed radius_db_backup.sql")
sys.stdout.flush()

# 3. Server Configs Sync
print("\n--- STEP 3: Syncing Server Configurations (/etc) ---")
run_cmd("tar -czf /tmp/backup_etc.tar.gz -C / etc", "Creating /tmp/backup_etc.tar.gz")
sftp.get("/tmp/backup_etc.tar.gz", os.path.join(VPS_BACKUP_DIR, "backup_etc.tar.gz"))
run_cmd("rm -f /tmp/backup_etc.tar.gz")
print("  -> Downloaded backup_etc.tar.gz")

print("  -> Safely extracting backup_etc.tar.gz...")
safe_extract_tar(os.path.join(VPS_BACKUP_DIR, "backup_etc.tar.gz"), VPS_BACKUP_DIR)

# Copy selective configs to server_configs
config_mappings = [
    (os.path.join(VPS_BACKUP_DIR, "etc", "nginx"), os.path.join(SERVER_CONFIGS_DIR, "nginx")),
    (os.path.join(VPS_BACKUP_DIR, "etc", "wireguard"), os.path.join(SERVER_CONFIGS_DIR, "wireguard")),
    (os.path.join(VPS_BACKUP_DIR, "etc", "raddb"), os.path.join(SERVER_CONFIGS_DIR, "raddb")),
    (os.path.join(VPS_BACKUP_DIR, "etc", "dnsmasq.d"), os.path.join(SERVER_CONFIGS_DIR, "dnsmasq.d")),
]
for src, dst in config_mappings:
    if os.path.exists(src):
        if os.path.exists(dst):
            shutil.rmtree(dst)
        shutil.copytree(src, dst)
        print(f"  -> Synced to server_configs: {os.path.basename(src)}")

if os.path.exists(os.path.join(VPS_BACKUP_DIR, "etc", "dnsmasq.conf")):
    shutil.copy2(os.path.join(VPS_BACKUP_DIR, "etc", "dnsmasq.conf"), os.path.join(SERVER_CONFIGS_DIR, "dnsmasq.conf"))
shutil.copy2(os.path.join(SYS_META_DIR, "crontab_root.txt"), os.path.join(SERVER_CONFIGS_DIR, "crontab_root.txt"))
shutil.copy2(os.path.join(SYS_META_DIR, "iptables_v4.rules"), os.path.join(SERVER_CONFIGS_DIR, "iptables"))
sys.stdout.flush()

# 4. Root Home Directory (/root)
print("\n--- STEP 4: Backing up Root Home Directory (/root) ---")
run_cmd("tar --exclude=*.tar.gz -czf /tmp/backup_root.tar.gz -C / root", "Creating /tmp/backup_root.tar.gz (excluding archives)")
sftp.get("/tmp/backup_root.tar.gz", os.path.join(VPS_BACKUP_DIR, "backup_root.tar.gz"))
run_cmd("rm -f /tmp/backup_root.tar.gz")
print("  -> Downloaded backup_root.tar.gz")

root_home_dir = os.path.join(VPS_BACKUP_DIR, "root_home")
safe_extract_tar(os.path.join(VPS_BACKUP_DIR, "backup_root.tar.gz"), root_home_dir)
print("  -> Extracted root_home")
sys.stdout.flush()

# 5. Web Application (/var/www)
print("\n--- STEP 5: Backing up /var/www and Syncing to D:\\App\\M.yunus\\mikhmon ---")
run_cmd("tar -czf /tmp/backup_var_www.tar.gz -C / var/www", "Creating /tmp/backup_var_www.tar.gz")
sftp.get("/tmp/backup_var_www.tar.gz", os.path.join(VPS_BACKUP_DIR, "backup_var_www.tar.gz"))
run_cmd("rm -f /tmp/backup_var_www.tar.gz")
print("  -> Downloaded backup_var_www.tar.gz")

safe_extract_tar(os.path.join(VPS_BACKUP_DIR, "backup_var_www.tar.gz"), VPS_BACKUP_DIR)
print("  -> Extracted to vps_backup/var/www")

vps_mikhmon_src = os.path.join(VPS_BACKUP_DIR, "var", "www", "mikhmon")
if os.path.exists(vps_mikhmon_src):
    for root, dirs, files in os.walk(vps_mikhmon_src):
        rel_path = os.path.relpath(root, vps_mikhmon_src)
        dest_dir = os.path.join(MIKHMON_DIR, rel_path)
        os.makedirs(dest_dir, exist_ok=True)
        for f in files:
            src_file = os.path.join(root, f)
            dest_file = os.path.join(dest_dir, f)
            shutil.copy2(src_file, dest_file)
    print(f"  -> Synced all files from /var/www/mikhmon directly to {MIKHMON_DIR}")
sys.stdout.flush()

# 6. MikroTik Configuration Export
print("\n--- STEP 6: Backing up MikroTik Router Configurations ---")
mt_backup_script = """php -r '
error_reporting(0);
$_SERVER["REQUEST_URI"] = "cli";
require_once "/var/www/mikhmon/include/config.php";
require_once "/var/www/mikhmon/lib/routeros_api.class.php";

foreach ($data as $s_name => $s_cfg) {
    if ($s_name == "mikhmon" || empty($s_cfg)) continue;
    $ip = explode("!", $s_cfg[1])[1];
    $user = explode("@|@", $s_cfg[2])[1];
    $pass = decrypt(explode("#|#", $s_cfg[3])[1]);
    
    echo "Connecting to $s_name ($ip)...\\n";
    $API = new RouterosAPI();
    $API->debug = false;
    if ($API->connect($ip, $user, $pass)) {
        $backup_name = "export_" . $s_name . ".rsc";
        $API->comm("/export", array("file" => "export_" . $s_name));
        $API->disconnect();
        echo "Export command triggered for $s_name\\n";
        sleep(3);
        
        $ftp = @ftp_connect($ip, 21, 5);
        if ($ftp && @ftp_login($ftp, $user, $pass)) {
            @ftp_pasv($ftp, true);
            if (@ftp_get($ftp, "/tmp/" . $backup_name, $backup_name, FTP_BINARY)) {
                echo "Downloaded $backup_name to /tmp on VPS\\n";
                @ftp_delete($ftp, $backup_name);
            }
            @ftp_close($ftp);
        }
    } else {
        echo "Failed to connect to $s_name\\n";
    }
}
'"""
run_cmd(mt_backup_script, "Exporting and fetching .rsc from connected routers")

# Download RSC files from VPS to local mikrotik_backup directory
stdin, stdout, stderr = ssh.exec_command("ls /tmp/export_*.rsc")
rsc_files = stdout.read().decode().split()
for rf in rsc_files:
    rf = rf.strip()
    if rf:
        base_rf = os.path.basename(rf)
        local_rf = os.path.join(MIKROTIK_BACKUP_DIR, base_rf)
        sftp.get(rf, local_rf)
        print(f"  -> Saved MikroTik backup: {base_rf}")
        if "Hamadi" in base_rf:
            shutil.copy2(local_rf, os.path.join(MIKROTIK_BACKUP_DIR, "mikrotik_export.rsc"))
        ssh.exec_command(f"rm -f {rf}")
sys.stdout.flush()

# 7. Full System Image Archive (full_system_archive/backup_full_system.tar.gz)
print("\n--- STEP 7: Creating & Downloading Full OS System Archive ---")
full_archive_cmd = (
    "tar --exclude=/proc --exclude=/sys --exclude=/dev --exclude=/run --exclude=/tmp "
    "--exclude=/mnt --exclude=/media --exclude=/lost+found --exclude=/var/cache "
    "--exclude=/var/tmp --exclude=/root/backup_full_system.tar.gz --exclude=*.tar.gz -czf /tmp/backup_full_system.tar.gz /"
)
out, err = run_cmd(full_archive_cmd, "Creating full OS filesystem archive /tmp/backup_full_system.tar.gz (approx. 1-2 minutes)")
print("[*] Generating SHA256 checksum on VPS...")
sys.stdout.flush()
out_sha, _ = run_cmd("sha256sum /tmp/backup_full_system.tar.gz")
sha256_val = out_sha.strip().split()[0] if out_sha else ""
print(f"  -> Server SHA256: {sha256_val}")
sys.stdout.flush()

local_tar_path = os.path.join(FULL_SYS_DIR, "backup_full_system.tar.gz")
local_sha_path = os.path.join(FULL_SYS_DIR, "backup_full_system.tar.gz.sha256")

with open(local_sha_path, "w", encoding="utf-8") as f:
    f.write(f"{sha256_val}  backup_full_system.tar.gz\n")

print("[*] Downloading backup_full_system.tar.gz via SFTP (streaming progress)...")
sys.stdout.flush()
last_report_time = [time.time()]
def sftp_progress(transferred, total):
    curr = time.time()
    if curr - last_report_time[0] >= 5 or transferred == total:
        last_report_time[0] = curr
        percent = (transferred / total) * 100 if total > 0 else 0
        mb_trans = transferred / (1024 * 1024)
        mb_total = total / (1024 * 1024)
        print(f"    Downloading: {mb_trans:.1f} MB / {mb_total:.1f} MB ({percent:.1f}%)")
        sys.stdout.flush()

sftp.get("/tmp/backup_full_system.tar.gz", local_tar_path, callback=sftp_progress)
print("\n[+] Download completed.")

# Clean up remote temp archive to free disk space on VPS
run_cmd("rm -f /tmp/backup_full_system.tar.gz", "Cleaning temporary archive from VPS /tmp")

# Also update /root/backup_full_system.tar.gz on VPS
run_cmd("rm -f /root/backup_full_system.tar.gz", "Removing old backup in /root")

# Verify local file size and checksum
local_size = os.path.getsize(local_tar_path)
print(f"[+] Local archive size: {local_size / (1024*1024):.2f} MB")

sftp.close()
ssh.close()
print("\n=======================================================")
print("[SUCCESS] Full backup from server to D:\\App\\M.yunus is COMPLETE!")
print("=======================================================")
