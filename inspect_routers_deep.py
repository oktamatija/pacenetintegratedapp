import paramiko
from deploy_config import VPS_HOST, VPS_PORT, VPS_USER, VPS_PASS

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(VPS_HOST, port=VPS_PORT, username=VPS_USER, password=VPS_PASS, timeout=10)

def run(cmd, desc):
    print(f"\n==================== {desc} ====================")
    _, stdout, stderr = ssh.exec_command(cmd)
    out = stdout.read().decode('utf-8', errors='replace')
    err = stderr.read().decode('utf-8', errors='replace')
    if out:
        print(out.strip())
    if err:
        print("ERR:", err.strip())

# 1. Check access log for join.php requests
run("grep 'join.php' /var/log/nginx/access.log | tail -n 40", "NGINX ACCESS LOG JOIN.PHP")

# 2. Check access log for heartbeat
run("grep 'heartbeat' /var/log/nginx/access.log | tail -n 40", "NGINX ACCESS LOG HEARTBEAT")

# 3. Check all nginx logs for 204.51.40.73
run("grep '204.51.40.73' /var/log/nginx/access.log* | tail -n 40", "LOGS FOR 204.51.40.73")

# 4. Check Wireguard config on VPS - check if there are duplicate peers or keys
run("wg show wg0 peers", "WG PEERS LIST")

# 5. Check if 10.10.10.6 or 10.10.10.8 are reachable from VPS right now
run("ping -c 3 10.10.10.6; ping -c 3 10.10.10.8", "PING 10.10.10.6 AND 8")

# 6. Check Wireguard dump
run("wg show wg0 dump", "WG DUMP")

# 7. Check if there are other access log files (access.log.1, etc.)
run("zgrep 'join.php' /var/log/nginx/access.log* | tail -n 30", "ZGREP JOIN.PHP")

ssh.close()
