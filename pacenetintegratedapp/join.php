<?php
/**
 * Zero-Touch Bootstrap & Heartbeat Endpoint for MikroTik Routers
 * Antigravity IDE - Pacenet Billing System Integration
 */
header("Access-Control-Allow-Origin: *");
error_reporting(0);

$dataFile = '/var/www/pacenetintegratedapp/data/pending_routers.json';
$configFile = '/var/www/pacenetintegratedapp/include/config.php';
$serverPubIp = '202.10.46.222';
$serverWgPub = trim(@file_get_contents('/etc/wireguard/server_public.key') ?: 'UfYb+alr8F2T69ylHUjN14K0TpZ4mjwn+8fHsV5aWWc=');

function loadPending($file) {
    if (!file_exists($file)) return array();
    $c = @file_get_contents($file);
    return json_decode($c, true) ?: array();
}

function savePending($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Robustly detect ALL allocated IPs and ports across:
 * 1. pending_routers.json
 * 2. include/config.php (registered sessions)
 * 3. wg show wg0 allowed-ips (kernel WireGuard peers)
 * 4. iptables PREROUTING rules
 */
function getAllocations($pendingFile, $configFile) {
    $usedIps = array('10.10.10.1', '10.10.10.2'); // 10.10.10.1 = VPS, 10.10.10.2 = Hotspot-Yunus
    $usedPorts = array(18291); // 18291 = Hotspot-Yunus
    $knownSessions = array();

    // 1. From pending_routers.json
    if (file_exists($pendingFile)) {
        $raw = @file_get_contents($pendingFile);
        $pending = json_decode($raw, true) ?: array();
        foreach ($pending as $item) {
            if (!empty($item['vpn_ip'])) $usedIps[] = trim($item['vpn_ip']);
            if (!empty($item['winbox_port'])) $usedPorts[] = intval($item['winbox_port']);
            if (!empty($item['identity']) && !empty($item['vpn_ip'])) {
                $knownSessions[$item['identity']] = array(
                    'vpn_ip' => $item['vpn_ip'],
                    'winbox_port' => $item['winbox_port'] ?? 0,
                    'pubkey' => $item['pubkey'] ?? '',
                    'privkey' => $item['privkey'] ?? ''
                );
            }
        }
    }

    // 2. From include/config.php
    if (file_exists($configFile)) {
        $lines = @file($configFile);
        if ($lines) {
            foreach ($lines as $line) {
                if (preg_match("/\\\$data\['([^']+)'\]\s*=\s*array\s*\('1'=>'[^!]+!([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)'/", $line, $m)) {
                    $sName = trim($m[1]);
                    $sIp = trim($m[2]);
                    if ($sName !== 'mikhmon') {
                        $usedIps[] = $sIp;
                        if (!isset($knownSessions[$sName])) {
                            $knownSessions[$sName] = array('vpn_ip' => $sIp);
                        }
                    }
                }
            }
        }
    }

    // 3. From active WireGuard kernel peers (wg show wg0 allowed-ips)
    $wgOutput = @shell_exec('sudo wg show wg0 allowed-ips 2>/dev/null');
    if ($wgOutput && preg_match_all("/10\.10\.10\.[0-9]+/", $wgOutput, $matches)) {
        foreach ($matches[0] as $ip) {
            $usedIps[] = trim($ip);
        }
    }

    // 4. From iptables NAT PREROUTING rules (Winbox ports)
    $iptOutput = @shell_exec('sudo iptables -t nat -L PREROUTING -n 2>/dev/null');
    if ($iptOutput) {
        if (preg_match_all("/dpt:([0-9]+)/", $iptOutput, $pmatches)) {
            foreach ($pmatches[1] as $port) {
                $usedPorts[] = intval($port);
            }
        }
        if (preg_match_all("/to:(10\.10\.10\.[0-9]+):8291/", $iptOutput, $imatches)) {
            foreach ($imatches[1] as $toIp) {
                $usedIps[] = trim($toIp);
            }
        }
    }

    $usedIps = array_values(array_unique(array_filter($usedIps)));
    $usedPorts = array_values(array_unique(array_filter($usedPorts)));

    return array(
        'used_ips' => $usedIps,
        'used_ports' => $usedPorts,
        'known_sessions' => $knownSessions
    );
}

$action = $_GET['action'] ?? ($_GET['join'] ? 'bootstrap' : '');

if ($action === 'heartbeat') {
    header('Content-Type: text/plain');
    $ip = $_GET['ip'] ?? $_SERVER['REMOTE_ADDR'];
    $pending = loadPending($dataFile);
    $found = false;
    foreach ($pending as &$p) {
        if ($p['vpn_ip'] === $ip) {
            $p['last_seen'] = date('Y-m-d H:i:s');
            $p['connected'] = true;
            $found = true;
            break;
        }
    }
    if ($found) savePending($dataFile, $pending);
    echo "OK";
    exit;
}

if ($action === 'bootstrap') {
    header('Content-Type: text/plain');
    $identity = trim($_GET['identity'] ?? $_GET['name'] ?? 'MikroTik-New');
    // Clean identity
    $cleanId = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $identity);
    if (empty($cleanId)) $cleanId = 'MikroTik-' . rand(100, 999);

    $pending = loadPending($dataFile);
    $allocs = getAllocations($dataFile, $configFile);

    // Helper to check if a name is already used in config.php or pending
    $isNameTaken = function($name) use ($allocs, $pending) {
        if (isset($allocs['known_sessions'][$name])) return true;
        foreach ($pending as $p) {
            if (($p['identity'] ?? '') === $name) return true;
        }
        return false;
    };

    // Check if this is an existing pending re-fetch (same router retrying bootstrap)
    $existing = null;
    foreach ($pending as $item) {
        if ($item['identity'] === $cleanId && ($item['status'] ?? '') === 'pending' && !empty($item['privkey']) && !empty($item['vpn_ip'])) {
            if (!isset($allocs['known_sessions'][$cleanId])) {
                $existing = $item;
                break;
            }
        }
    }

    // If identity is already in use by another router in config.php or pending, auto-disambiguate
    if (!$existing && $isNameTaken($cleanId)) {
        $baseName = $cleanId;
        $counter = 2;
        while ($isNameTaken($baseName . '-' . $counter)) {
            $counter++;
        }
        $cleanId = $baseName . '-' . $counter;
    }

    if ($existing) {
        $clientIp = $existing['vpn_ip'];
        $clientPriv = $existing['privkey'];
        $clientPub = $existing['pubkey'];
        $winboxPort = $existing['winbox_port'];
        $cleanId = $existing['identity'];
    } else {
        // Find next available VPN IP (strictly non-colliding)
        $nextNum = 2;
        while (in_array("10.10.10.$nextNum", $allocs['used_ips'])) {
            $nextNum++;
        }
        $clientIp = "10.10.10.$nextNum";

        // Find next available Winbox Port
        $nextPort = 18292;
        while (in_array($nextPort, $allocs['used_ports'])) {
            $nextPort++;
        }
        $winboxPort = $nextPort;

        // Generate client keys
        $clientPriv = trim(shell_exec('wg genkey'));
        $clientPub = trim(shell_exec("echo " . escapeshellarg($clientPriv) . " | wg pubkey"));

        // Add peer to wg0 kernel via sudo
        shell_exec("sudo wg set wg0 peer " . escapeshellarg($clientPub) . " allowed-ips {$clientIp}/32 2>/dev/null");
        shell_exec("sudo wg-quick save wg0 2>/dev/null");

        $newEntry = array(
            'identity' => $cleanId,
            'original_name' => $identity,
            'vpn_ip' => $clientIp,
            'winbox_port' => $winboxPort,
            'pubkey' => $clientPub,
            'privkey' => $clientPriv,
            'created_at' => date('Y-m-d H:i:s'),
            'last_seen' => date('Y-m-d H:i:s'),
            'status' => 'pending',
            'connected' => false
        );

        $updated = array();
        foreach ($pending as $p) {
            if ($p['identity'] !== $cleanId) $updated[] = $p;
        }
        $updated[] = $newEntry;
        savePending($dataFile, $updated);
    }

    // Generate RouterOS commands
    $rsc = "#====================================================================\n";
    $rsc .= "# RouterOS v7 Auto-Join WireGuard Cloud & Pacenet Billing System Registration\n";
    $rsc .= "# Assigned VPN IP: {$clientIp} | Winbox Remote: {$serverPubIp}:{$winboxPort}\n";
    $rsc .= "#====================================================================\n";
    $rsc .= ":log info \"[Pacenet-Join] Memulai inisialisasi koneksi WireGuard...\"\n";
    $rsc .= ":do { /system/identity/set name=\"{$cleanId}\" } on-error={}\n";
    $rsc .= "/interface/wireguard/remove [find name=wg-vpn-remote]\n";
    $rsc .= "/interface/wireguard/add name=wg-vpn-remote listen-port=13231 private-key=\"{$clientPriv}\"\n";
    $rsc .= "/ip/address/remove [find interface=wg-vpn-remote]\n";
    $rsc .= "/ip/address/add address={$clientIp}/24 interface=wg-vpn-remote comment=\"VPN Remote Pacenet Billing\"\n";
    $rsc .= "/interface/wireguard/peers/remove [find interface=wg-vpn-remote]\n";
    $rsc .= "/interface/wireguard/peers/add interface=wg-vpn-remote public-key=\"{$serverWgPub}\" endpoint-address=\"{$serverPubIp}\" endpoint-port=51820 allowed-address=10.10.10.0/24 persistent-keepalive=25s\n";
    $rsc .= "\n# Aktifkan service API, FTP, dan WWW untuk manajemen otomatis Pacenet Billing\n";
    $rsc .= ":do { /ip/service/enable [find name=api] } on-error={}\n";
    $rsc .= ":do { /ip/service/enable [find name=ftp] } on-error={}\n";
    $rsc .= ":do { /ip/service/enable [find name=www] } on-error={}\n";
    $rsc .= ":do { /ip/service/set [find name=api] address=0.0.0.0/0 disabled=no } on-error={}\n";
    $rsc .= ":do { /ip/service/set [find name=ftp] address=0.0.0.0/0 disabled=no } on-error={}\n";
    $rsc .= ":do { /ip/service/set [find name=www] address=0.0.0.0/0 disabled=no } on-error={}\n";
    $rsc .= "\n# Sinkronisasi SNTP Client Publik (Mencegah kegagalan timestamp WireGuard saat mati lampu/blackout)\n";
    $rsc .= ":do { /system/ntp/client/set enabled=yes mode=unicast servers=162.159.200.1,216.239.35.0 } on-error={}\n";
    $rsc .= "\n# Bersihkan rute statis usang jika ada\n";
    $rsc .= ":do { /ip/route/remove [find comment=\"WG Tunnel Protection to VPS\"] } on-error={}\n";
    $rsc .= "\n# Hardware Watchdog & System Watchdog (Kernel Freeze Protection)\n";
    $rsc .= ":do { /system/watchdog/set watchdog-timer=yes watch-address=none ping-start-after-boot=5m ping-timeout=1m automatic-supout=yes auto-send-supout=no } on-error={}\n";
    $rsc .= "\n# Watchdog Otomatis 24/7 (Blackout / Mati Lampu Auto-Recovery)\n";
    $rsc .= ":do { /system/script/remove [find name=pacenet-watchdog] } on-error={}\n";
    $rsc .= "/system/script/add name=pacenet-watchdog comment=\"Auto-reconnect WireGuard after blackout\" source=\":local vpnGw \\\"10.10.10.1\\\"; :local pingCount [/ping \\\$vpnGw count=2 interval=1s]; :if (\\\$pingCount = 0) do={ :local wanCount ([/ping 8.8.8.8 count=1] + [/ping 1.1.1.1 count=1]); :if (\\\$wanCount > 0) do={ :log warning \\\"[Pacenet-Watchdog] VPN down but WAN online. Restarting WireGuard...\\\"; /interface/wireguard/disable [find name=wg-vpn-remote]; :delay 1s; /interface/wireguard/enable [find name=wg-vpn-remote]; :delay 2s; :do { /tool fetch url=\\\"http://202.10.46.222/join.php?action=heartbeat&ip={$clientIp}\\\" mode=http keep-result=no } on-error={} } else={ :do { :local dhcpStat [/ip/dhcp-client get [find interface=ether1] status]; :if (\\\$dhcpStat != \\\"bound\\\") do={ :log warning \\\"[Pacenet-Watchdog] DHCP ether1 is \\\$dhcpStat. Renewing...\\\"; /ip/dhcp-client renew [find interface=ether1] } } on-error={} } }\"\n";
    $rsc .= ":do { /system/scheduler/remove [find name=pacenet-watchdog] } on-error={}\n";
    $rsc .= "/system/scheduler/add name=pacenet-watchdog interval=15s start-time=startup on-event=\"/system/script/run pacenet-watchdog\" comment=\"Keep WireGuard connected 24/7 across blackouts\"\n";
    $rsc .= ":delay 2s\n";
    $rsc .= "/tool fetch url=\"http://10.10.10.1/join.php?action=heartbeat&ip={$clientIp}\" mode=http keep-result=no\n";
    $rsc .= ":log info \"[Pacenet-Join] BERHASIL! Router terhubung ke WireGuard cloud. Buka Pacenet Billing System untuk klik Accept.\"\n";

    header('Content-Length: ' . strlen($rsc));
    header('Connection: close');
    echo $rsc;
    exit;
}

// Fallback JSON API for listing pending routers
if ($action === 'list_pending') {
    header('Content-Type: application/json');
    $pending = loadPending($dataFile);
    $activePending = array_filter($pending, function($x) { return ($x['status'] === 'pending'); });
    echo json_encode(array_values($activePending));
    exit;
}

header('Content-Type: application/json');
echo json_encode(array('status' => 'ready', 'server' => $serverPubIp));
