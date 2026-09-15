<?php
/**
 * Pacenet REST API - Multi-Router Live Status, Hardware, & WAN Bandwidth
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ACTION: TEST CREDENTIALS
if ($action === 'test_credentials') {
    $rawInput = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $testIp = trim($rawInput['ip'] ?? '');
    $testUser = trim($rawInput['user'] ?? 'admin');
    $testPass = trim($rawInput['pass'] ?? '');
    $testPort = intval($rawInput['port'] ?? 8728) ?: 8728;

    if (empty($testIp)) {
        jsonResponse(false, null, 'IP Address router wajib diisi.');
    }

    $tApi = new RouterosAPI();
    $tApi->timeout = 3;
    $tApi->attempts = 1;
    $tApi->debug = false;
    $tApi->port = $testPort;

    if ($tApi->connect($testIp, $testUser, $testPass)) {
        $res = $tApi->comm('/system/resource/print');
        $board = $res[0]['board-name'] ?? $res[0]['platform'] ?? 'MikroTik';
        $ver = $res[0]['version'] ?? '-';
        $tApi->disconnect();
        jsonResponse(true, array(
            'board_name' => $board,
            'ros_version' => $ver
        ), "Koneksi berhasil! Terhubung ke {$board} (ROS v{$ver}).");
    } else {
        jsonResponse(false, null, "Gagal terhubung ke {$testIp}:{$testPort}. Pastikan IP, user, password, dan service API MikroTik aktif.");
    }
}

// Guard mutating actions against read-only demo user
if (in_array($action, array('update_credentials', 'delete_router', 'add_router', 'delete'))) {
    checkWritePermission();
}

// ACTION: UPDATE CREDENTIALS / CONFIG
if ($action === 'update_credentials') {
    $rawInput = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $session = trim($rawInput['session'] ?? '');
    $newSession = trim($rawInput['new_session'] ?? $session);
    $newSession = preg_replace('/[^a-zA-Z0-9_\-]/', '', $newSession);
    $ip = trim($rawInput['ip'] ?? '');
    $user = trim($rawInput['user'] ?? 'admin');
    $pass = $rawInput['pass'] ?? '';
    $hsName = trim($rawInput['hotspot_name'] ?? $newSession);
    $dnsName = trim($rawInput['dns_name'] ?? 'hotspot.yunus');
    $currency = trim($rawInput['currency'] ?? 'Rp');

    if (empty($session) || empty($ip)) {
        jsonResponse(false, null, 'Session name dan IP router wajib diisi.');
    }

    $cfgFile = '/var/www/pacenetintegratedapp/include/config.php';
    if (!file_exists($cfgFile)) $cfgFile = __DIR__ . '/../include/config.php';
    if (!file_exists($cfgFile)) {
        jsonResponse(false, null, 'File config.php tidak ditemukan.');
    }

    $oldCfg = $data[$session] ?? null;
    if (!$oldCfg) {
        jsonResponse(false, null, "Sesi router '{$session}' tidak ditemukan dalam config.");
    }

    // If password not passed, keep old encrypted pass
    if ($pass === '' || $pass === null) {
        $oldEncPass = explode('#|#', $oldCfg[3] ?? '')[1] ?? '';
    } else {
        $oldEncPass = encrypt($pass, 128);
    }

    $autoReload = explode('*', $oldCfg[7] ?? '')[1] ?? '10';
    $idleTimeout = explode('(', $oldCfg[8] ?? '')[1] ?? '1';
    $liveTraffic = explode(')', $oldCfg[9] ?? '')[1] ?? '';
    $trafficInt = explode('=', $oldCfg[10] ?? '')[1] ?? '10';
    $telegram = explode('@!@', $oldCfg[11] ?? '')[1] ?? 'disable';

    // Construct new session array line
    $newLine = "\$data['{$newSession}'] = array ('1'=>'{$newSession}!{$ip}','{$newSession}@|@{$user}','{$newSession}#|#{$oldEncPass}','{$newSession}%{$hsName}','{$newSession}^{$dnsName}','{$newSession}&{$currency}','{$newSession}*{$autoReload}','{$newSession}({$idleTimeout}','{$newSession}){$liveTraffic}','{$newSession}={$trafficInt}','{$newSession}@!@{$telegram}');";

    $content = file_get_contents($cfgFile);
    // Replace existing session line
    $pattern = "/\\\$data\['" . preg_quote($session, '/') . "'\]\s*=\s*array\s*\([^;]+\);/";
    if (preg_match($pattern, $content)) {
        $newContent = preg_replace($pattern, $newLine, $content, 1);
    } else {
        $newContent = rtrim($content) . "\n\n" . $newLine . "\n";
    }

    file_put_contents($cfgFile, $newContent);

    jsonResponse(true, array(
        'session' => $newSession,
        'ip' => $ip,
        'user' => $user,
        'hotspot_name' => $hsName
    ), "Kredensial router '{$newSession}' berhasil diperbarui.");
}

// ACTION: DELETE ROUTER
if ($action === 'delete_router') {
    $rawInput = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $session = trim($rawInput['session'] ?? '');

    if (empty($session)) {
        jsonResponse(false, null, 'Parameter session tidak boleh kosong.');
    }

    $cfgFile = '/var/www/pacenetintegratedapp/include/config.php';
    if (!file_exists($cfgFile)) $cfgFile = __DIR__ . '/../include/config.php';

    $content = file_get_contents($cfgFile);
    $pattern = "/\\\$data\['" . preg_quote($session, '/') . "'\]\s*=\s*array\s*\([^;]+\);\s*/";
    if (preg_match($pattern, $content)) {
        $newContent = preg_replace($pattern, '', $content);
        file_put_contents($cfgFile, $newContent);
        jsonResponse(true, array('session' => $session), "Router '{$session}' berhasil dihapus dari sistem billing.");
    } else {
        jsonResponse(false, null, "Sesi router '{$session}' tidak ditemukan.");
    }
}

$publicIp = '202.10.46.222';
if (!empty($_SERVER['SERVER_ADDR']) && filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $publicIp = $_SERVER['SERVER_ADDR'];
}

// Winbox port mapping from iptables
$winboxPortMap = array();
$iptOut = @shell_exec('sudo /usr/sbin/iptables -t nat -S PREROUTING 2>/dev/null');
if ($iptOut && preg_match_all('/--dport\s+([0-9]+).*?-j\s+DNAT\s+--to-destination\s+([0-9.]+):8291/', $iptOut, $pMatches, PREG_SET_ORDER)) {
    foreach ($pMatches as $pm) {
        $winboxPortMap[$pm[2]] = intval($pm[1]);
    }
}

function checkPortOpen($ip, $port = 8728, $timeout = 0.35) {
    if (empty($ip)) return false;
    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

$routersData = array();
$totalActiveSessions = 0;
$totalWanRxBps = 0;
$totalWanTxBps = 0;
$routersOnline = 0;
$routersTotal = 0;

foreach ($data as $sessName => $cfg) {
    if ($sessName === 'mikhmon' || empty($sessName) || strpos($sessName, 'new-') === 0) continue;
    $routersTotal++;

    $ip = explode('!', $cfg[1] ?? '')[1] ?? '';
    $user = explode('@|@', $cfg[2] ?? '')[1] ?? 'admin';
    $pass = decrypt(explode('#|#', $cfg[3] ?? '')[1] ?? '');
    $hsName = explode('%', $cfg[4] ?? '')[1] ?? $sessName;

    if (!empty($winboxPortMap[$ip])) {
        $rWinboxPort = $winboxPortMap[$ip];
    } else {
        $octets = explode('.', $ip);
        $lastOctet = intval(end($octets));
        $rWinboxPort = ($lastOctet >= 2) ? (18290 + ($lastOctet - 1)) : 8291;
    }
    $winboxAddr = $publicIp . ':' . $rWinboxPort;

    $isOnline = checkPortOpen($ip, 8728, 0.35);

    $dnsName = explode('^', $cfg[5] ?? '')[1] ?? 'hotspot.yunus';
    $currency = explode('&', $cfg[6] ?? '')[1] ?? 'Rp';

    $routerItem = array(
        'session' => $sessName,
        'hotspot_name' => $hsName,
        'vpn_ip' => $ip,
        'user' => $user,
        'dns_name' => $dnsName,
        'currency' => $currency,
        'winbox_addr' => $winboxAddr,
        'winbox_port' => $rWinboxPort,
        'online' => $isOnline,
        'active_sessions' => 0,
        'cpu_load' => 0,
        'uptime' => '-',
        'board_name' => '-',
        'ros_version' => '-',
        'free_memory' => 0,
        'total_memory' => 0,
        'wan_interfaces' => array(),
        'total_rx_bps' => 0,
        'total_tx_bps' => 0,
        'total_rx_human' => '0 bps',
        'total_tx_human' => '0 bps'
    );

    if ($isOnline) {
        $routersOnline++;
        $api = new RouterosAPI();
        $api->timeout = 2;
        $api->attempts = 1;
        $api->debug = false;

        if ($api->connect($ip, $user, $pass)) {
            // 1. Active Hotspot Sessions
            $activeCount = $api->comm('/ip/hotspot/active/print', array('count-only' => ''));
            $numActive = intval($activeCount);
            $routerItem['active_sessions'] = $numActive;
            $totalActiveSessions += $numActive;

            // 2. Hardware Resource
            $res = $api->comm('/system/resource/print');
            if (!empty($res[0])) {
                $r0 = $res[0];
                $routerItem['cpu_load'] = intval($r0['cpu_load'] ?? $r0['cpu-load'] ?? 0);
                $routerItem['uptime'] = $r0['uptime'] ?? '-';
                $routerItem['board_name'] = $r0['board_name'] ?? $r0['board-name'] ?? '-';
                $routerItem['ros_version'] = $r0['version'] ?? '-';
                $routerItem['free_memory'] = intval($r0['free_memory'] ?? $r0['free-memory'] ?? 0);
                $routerItem['total_memory'] = intval($r0['total_memory'] ?? $r0['total-memory'] ?? 0);
            }

            // 3. WAN Interfaces Detection
            $wanIfaces = array();
            $routes = $api->comm('/ip/route/print', array('?dst-address' => '0.0.0.0/0', '?active' => 'true'));
            if (!empty($routes)) {
                foreach ($routes as $route) {
                    $gw = $route['gateway'] ?? '';
                    $vrf = $route['vrf_interface'] ?? $route['vrf-interface'] ?? '';
                    $immGw = $route['immediate_gw'] ?? $route['immediate-gw'] ?? '';

                    $ifName = '';
                    if (!empty($vrf)) {
                        $ifName = $vrf;
                    } elseif (strpos($immGw, '%') !== false) {
                        $ifName = explode('%', $immGw)[1];
                    } elseif (!empty($gw) && !preg_match('/^[0-9.]+$/', $gw)) {
                        $ifName = $gw;
                    }
                    if (!empty($ifName) && !in_array($ifName, $wanIfaces) && strpos($ifName, 'wg') === false) {
                        $wanIfaces[] = $ifName;
                    }
                }
            }

            $dhcpClients = $api->comm('/ip/dhcp-client/print', array('?status' => 'bound'));
            if (!empty($dhcpClients)) {
                foreach ($dhcpClients as $dc) {
                    $dIface = $dc['interface'] ?? '';
                    $addDef = $dc['add_default_route'] ?? $dc['add-default-route'] ?? 'yes';
                    if ($addDef !== 'no' && !empty($dIface) && !in_array($dIface, $wanIfaces) && strpos($dIface, 'wg') === false) {
                        $wanIfaces[] = $dIface;
                    }
                }
            }

            if (empty($wanIfaces)) {
                $wanIfaces[] = 'ether1';
            }

            // 4. Real-time traffic monitoring
            foreach ($wanIfaces as $wIface) {
                $mon = $api->comm('/interface/monitor-traffic', array(
                    'interface' => $wIface,
                    'once' => ''
                ));
                $rxBps = 0;
                $txBps = 0;
                if (!empty($mon[0])) {
                    $rxBps = intval($mon[0]['rx_bits_per_second'] ?? $mon[0]['rx-bits-per-second'] ?? 0);
                    $txBps = intval($mon[0]['tx_bits_per_second'] ?? $mon[0]['tx-bits-per-second'] ?? 0);
                }

                $routerItem['wan_interfaces'][] = array(
                    'name' => $wIface,
                    'rx_bps' => $rxBps,
                    'tx_bps' => $txBps,
                    'rx_human' => formatBpsRate($rxBps),
                    'tx_human' => formatBpsRate($txBps)
                );

                $routerItem['total_rx_bps'] += $rxBps;
                $routerItem['total_tx_bps'] += $txBps;
                $totalWanRxBps += $rxBps;
                $totalWanTxBps += $txBps;
            }

            $routerItem['total_rx_human'] = formatBpsRate($routerItem['total_rx_bps']);
            $routerItem['total_tx_human'] = formatBpsRate($routerItem['total_tx_bps']);

            $api->disconnect();
        }
    }

    $routersData[] = $routerItem;
}

// Read VPS Load
$vpsCpu = 0;
$vpsRam = 0;
if (file_exists('/proc/loadavg')) {
    $la = explode(' ', file_get_contents('/proc/loadavg'));
    $vpsCpu = floatval($la[0]);
}
if (file_exists('/proc/meminfo')) {
    $mem = file_get_contents('/proc/meminfo');
    if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $mem, $mt) && preg_match('/MemAvailable:\s+(\d+)\s+kB/', $mem, $ma)) {
        $totalKb = intval($mt[1]);
        $availKb = intval($ma[1]);
        if ($totalKb > 0) {
            $vpsRam = round((($totalKb - $availKb) / $totalKb) * 100, 1);
        }
    }
}

jsonResponse(true, array(
    'summary' => array(
        'total_active_sessions' => $totalActiveSessions,
        'total_rx_bps' => $totalWanRxBps,
        'total_tx_bps' => $totalWanTxBps,
        'total_rx_human' => formatBpsRate($totalWanRxBps),
        'total_tx_human' => formatBpsRate($totalWanTxBps),
        'routers_online' => $routersOnline,
        'routers_total' => $routersTotal,
        'vps_load' => $vpsCpu,
        'vps_ram_percent' => $vpsRam
    ),
    'routers' => $routersData
));
