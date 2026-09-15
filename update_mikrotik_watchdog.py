import paramiko
from deploy_config import VPS_HOST, VPS_PORT, VPS_USER, VPS_PASS

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(VPS_HOST, port=VPS_PORT, username=VPS_USER, password=VPS_PASS, timeout=10)

php_script = """<?php
require_once('/var/www/pacenetintegratedapp/include/config.php');
require_once('/var/www/pacenetintegratedapp/lib/routeros_api.class.php');
require_once('/var/www/pacenetintegratedapp/api/common.php');

$targets = array('Rumah-DOLPHIN', 'Dolphin-Hamadi');
foreach ($targets as $s) {
    if (!isset($data[$s])) continue;
    $cfg = $data[$s];
    $ip = explode('!', $cfg[1])[1];
    $user = explode('@|@', $cfg[2])[1];
    $pass = decrypt(explode('#|#', $cfg[3])[1]);

    echo "Connecting to $s ($ip)...\\n";
    $api = new RouterosAPI();
    $api->timeout = 5;
    if ($api->connect($ip, $user, $pass)) {
        // 1. Update pacenet-watchdog scheduler interval to 60s
        $scheds = $api->comm('/system/scheduler/print', array('?name' => 'pacenet-watchdog'));
        if (!empty($scheds[0]['.id'])) {
            $api->comm('/system/scheduler/set', array('.id' => $scheds[0]['.id'], 'interval' => '60s'));
            echo "   Updated scheduler interval to 60s\\n";
        }
        // 2. Update pacenet-watchdog script source to resilient 4-ping check
        $scripts = $api->comm('/system/script/print', array('?name' => 'pacenet-watchdog'));
        if (!empty($scripts[0]['.id'])) {
            $newSrc = ':local vpnGw "10.10.10.1"; :local pingCount [/ping $vpnGw count=4 interval=1s]; :if ($pingCount = 0) do={ :local wanCount ([/ping 8.8.8.8 count=2] + [/ping 1.1.1.1 count=2]); :if ($wanCount >= 2) do={ :log warning "[Pacenet-Watchdog] VPN down but WAN online. Restarting WireGuard..."; /interface/wireguard/disable [find name=wg-vpn-remote]; :delay 2s; /interface/wireguard/enable [find name=wg-vpn-remote]; :delay 3s; :do { /tool fetch url="http://202.10.46.222/join.php?action=heartbeat&ip=' . $ip . '" mode=http keep-result=no } on-error={} } }';
            $api->comm('/system/script/set', array('.id' => $scripts[0]['.id'], 'source' => $newSrc));
            echo "   Updated watchdog script with resilient 4-ping check\\n";
        }
        $api->disconnect();
    } else {
        echo "   Failed to connect to $s\\n";
    }
}
"""

sftp = ssh.open_sftp()
with sftp.file('/tmp/update_watchdog.php', 'w') as f:
    f.write(php_script)
sftp.close()

_, stdout, stderr = ssh.exec_command('php /tmp/update_watchdog.php && rm -f /tmp/update_watchdog.php')
print(stdout.read().decode('utf-8'))
print(stderr.read().decode('utf-8'))
ssh.close()
