<?php
/**
 * Pacenet REST API - Server VPS Cloud Resources & WireGuard Mesh Health
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

// 1. CPU Load
$cpuLoad = array('1m' => 0, '5m' => 0, '15m' => 0);
if (file_exists('/proc/loadavg')) {
    $la = explode(' ', file_get_contents('/proc/loadavg'));
    $cpuLoad = array(
        '1m' => floatval($la[0] ?? 0),
        '5m' => floatval($la[1] ?? 0),
        '15m' => floatval($la[2] ?? 0)
    );
}

// 2. RAM Info
$ram = array('total' => 0, 'used' => 0, 'free' => 0, 'percent' => 0);
if (file_exists('/proc/meminfo')) {
    $mem = file_get_contents('/proc/meminfo');
    if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $mem, $mt) && preg_match('/MemAvailable:\s+(\d+)\s+kB/', $mem, $ma)) {
        $totalKb = intval($mt[1]);
        $availKb = intval($ma[1]);
        $usedKb = max($totalKb - $availKb, 0);
        $percent = $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0;
        $ram = array(
            'total' => formatBytesReadable($totalKb * 1024),
            'used' => formatBytesReadable($usedKb * 1024),
            'free' => formatBytesReadable($availKb * 1024),
            'percent' => $percent
        );
    }
}

// 3. Disk Usage
$disk = array('total' => '-', 'used' => '-', 'free' => '-', 'percent' => 0);
$totalDisk = @disk_total_space('/');
$freeDisk = @disk_free_space('/');
if ($totalDisk && $freeDisk) {
    $usedDisk = $totalDisk - $freeDisk;
    $disk = array(
        'total' => formatBytesReadable($totalDisk),
        'used' => formatBytesReadable($usedDisk),
        'free' => formatBytesReadable($freeDisk),
        'percent' => round(($usedDisk / $totalDisk) * 100, 1)
    );
}

// 4. Uptime
$uptimeStr = '-';
if (file_exists('/proc/uptime')) {
    $upSecs = intval(explode(' ', file_get_contents('/proc/uptime'))[0] ?? 0);
    $days = floor($upSecs / 86400);
    $hours = floor(($upSecs % 86400) / 3600);
    $minutes = floor(($upSecs % 3600) / 60);
    $uptimeStr = "{$days}d {$hours}h {$minutes}m";
}

// 5. WireGuard Peers
$wgPeers = array();
$wgOut = @shell_exec('sudo wg show all dump 2>/dev/null');
if ($wgOut) {
    $lines = explode("\n", trim($wgOut));
    foreach ($lines as $line) {
        $cols = preg_split('/\t+/', trim($line));
        if (count($cols) >= 8) {
            $wgPeers[] = array(
                'interface' => $cols[0],
                'public_key' => substr($cols[1], 0, 12) . '...',
                'endpoint' => $cols[3] ?? '-',
                'allowed_ips' => $cols[4] ?? '-',
                'latest_handshake' => intval($cols[5] ?? 0) > 0 ? date('Y-m-d H:i:s', intval($cols[5])) : 'Never',
                'rx_bytes' => formatBytesReadable(floatval($cols[6] ?? 0)),
                'tx_bytes' => formatBytesReadable(floatval($cols[7] ?? 0))
            );
        }
    }
}

jsonResponse(true, array(
    'hostname' => gethostname(),
    'os' => php_uname('s') . ' ' . php_uname('r'),
    'php_version' => PHP_VERSION,
    'uptime' => $uptimeStr,
    'cpu' => $cpuLoad,
    'ram' => $ram,
    'disk' => $disk,
    'wireguard_peers' => $wgPeers
));
