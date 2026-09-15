<?php
/**
 * Pacenet Multi-Router Stats & Real-Time Traffic API
 * Returns real-time active sessions, hardware metrics, and WAN interface bandwidth
 */
session_start();
header('Content-Type: application/json');
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    echo json_encode(array('status' => 'error', 'message' => 'Unauthorized'));
    exit;
}
session_write_close();

function fmtBps($bps, $precision = 1) {
    $units = array('bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps');
    $bps = max(floatval($bps), 0);
    $pow = ($bps > 0) ? floor(log($bps) / log(1000)) : 0;
    $pow = min($pow, count($units) - 1);
    $pow = max($pow, 0);
    $val = ($pow > 0) ? ($bps / pow(1000, $pow)) : $bps;
    return round($val, $precision) . ' ' . $units[$pow];
}

require_once(__DIR__ . '/../lib/routeros_api.class.php');
require_once(__DIR__ . '/../lib/formatbytesbites.php');
include(__DIR__ . '/../include/config.php');

$publicIp = '202.10.46.222';
if (!empty($_SERVER['SERVER_ADDR']) && filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $publicIp = $_SERVER['SERVER_ADDR'];
}

// Load iptables PREROUTING port mapping for Winbox behind NAT
$winboxPortMap = array();
$iptOut = @shell_exec('sudo /usr/sbin/iptables -t nat -S PREROUTING 2>/dev/null');
if ($iptOut && preg_match_all('/--dport\s+([0-9]+).*?-j\s+DNAT\s+--to-destination\s+([0-9.]+):8291/', $iptOut, $pMatches, PREG_SET_ORDER)) {
    foreach ($pMatches as $pm) {
        $winboxPortMap[$pm[2]] = intval($pm[1]);
    }
}

function checkPort($ip, $port = 8728, $timeout = 0.3) {
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
    if ($sessName === 'mikhmon' || empty($sessName)) continue;
    $routersTotal++;

    $ip = explode('!', $cfg[1] ?? '')[1] ?? '';
    $user = explode('@|@', $cfg[2] ?? '')[1] ?? 'admin';
    $pass = decrypt(explode('#|#', $cfg[3] ?? '')[1] ?? '');
    $hsName = explode('%', $cfg[4] ?? '')[1] ?? $sessName;

    // Winbox port calculation
    if (!empty($winboxPortMap[$ip])) {
        $rWinboxPort = $winboxPortMap[$ip];
    } else {
        $octets = explode('.', $ip);
        $lastOctet = intval(end($octets));
        $rWinboxPort = ($lastOctet >= 2) ? (18290 + ($lastOctet - 1)) : 8291;
    }
    $winboxAddr = $publicIp . ':' . $rWinboxPort;

    $isOnline = checkPort($ip, 8728, 0.35);

    $routerItem = array(
        'session' => $sessName,
        'hotspot_name' => $hsName,
        'vpn_ip' => $ip,
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
        'total_tx_bps' => 0
    );

    if ($isOnline) {
        $routersOnline++;
        $api = new RouterosAPI();
        $api->timeout = 2;
        $api->attempts = 1;
        if ($api->connect($ip, $user, $pass)) {
            // 1. Active Hotspot Users
            $activeCount = $api->comm('/ip/hotspot/active/print', array('count-only' => ''));
            $numActive = intval($activeCount);
            $routerItem['active_sessions'] = $numActive;
            $totalActiveSessions += $numActive;

            // 2. Resource & Health
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

            // 3. Detect WAN Interfaces (default routes 0.0.0.0/0)
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

            // Also check bound DHCP clients
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

            // Fallback: If no WAN interface detected, default to ether1
            if (empty($wanIfaces)) {
                $wanIfaces[] = 'ether1';
            }

            // 4. Monitor Real-Time Traffic for all detected WAN interfaces
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
                    'rx_human' => fmtBps($rxBps),
                    'tx_human' => fmtBps($txBps)
                );

                $routerItem['total_rx_bps'] += $rxBps;
                $routerItem['total_tx_bps'] += $txBps;
                $totalWanRxBps += $rxBps;
                $totalWanTxBps += $txBps;
            }

            $api->disconnect();
        }
    }

    $routersData[] = $routerItem;
}

// Server VPS Resources (quick read)
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

echo json_encode(array(
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => array(
        'total_active_sessions' => $totalActiveSessions,
        'total_rx_bps' => $totalWanRxBps,
        'total_tx_bps' => $totalWanTxBps,
        'total_rx_human' => fmtBps($totalWanRxBps),
        'total_tx_human' => fmtBps($totalWanTxBps),
        'routers_online' => $routersOnline,
        'routers_total' => $routersTotal,
        'vps_load' => $vpsCpu,
        'vps_ram_percent' => $vpsRam
    ),
    'routers' => $routersData
));
