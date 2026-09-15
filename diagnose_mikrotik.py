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

# 1. Config.php routers
run("grep -E \"\\\$data\['\" /var/www/pacenetintegratedapp/include/config.php", "CONFIG.PHP SESSIONS")

# 2. pending_routers.json
run("cat /var/www/pacenetintegratedapp/data/pending_routers.json", "PENDING_ROUTERS.JSON")

# 3. WireGuard Status (full)
run("wg show", "WIREGUARD SHOW")

# 4. WireGuard config file
run("cat /etc/wireguard/wg0.conf", "/etc/wireguard/wg0.conf")

# 5. Ping tests to 10.10.10.x
run("for i in 2 3 4 5 6 7 8; do echo '--- PING 10.10.10.'$i '---'; ping -c 2 -W 1 10.10.10.$i; done", "PING 10.10.10.x")

# 6. iptables NAT rules for winbox
run("iptables -t nat -L PREROUTING -n -v", "IPTABLES PREROUTING")

# 7. Check kernel logs / dmesg / network errors
run("dmesg -T | grep -E 'wireguard|wg0|drop|oom|conntrack' | tail -n 30", "DMESG LOGS")

# 8. Check VPS resource usage & network interface stats
run("free -m; uptime; ip -s link show wg0", "VPS STATS & WG0 STATS")

ssh.close()
