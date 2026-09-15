<?php
/*
 *  Pacenet Billing System - Standalone VPS Resource API
 *  Real-time metrics for Linux VPS Cloud Server: CPU, RAM, Disk, and Bandwidth.
 */
session_start();
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["mikhmon"])) {
    http_response_code(401);
    echo json_encode(array('status' => 'error', 'message' => 'Unauthorized'));
    exit;
}

function fmtBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function fmtBps($bps, $precision = 1) {
    $units = array('bps', 'kbps', 'Mbps', 'Gbps');
    $bps = max($bps, 0);
    $pow = floor(($bps ? log($bps) : 0) / log(1000));
    $pow = min($pow, count($units) - 1);
    $val = $bps / pow(1000, $pow);
    return round($val, $precision) . ' ' . $units[$pow];
}

// 1. CPU Usage Calculation via /proc/stat
function getVpsCpu() {
    $stat1 = @file('/proc/stat');
    if (!$stat1 || empty($stat1[0])) {
        return 0;
    }
    $info1 = explode(' ', preg_replace('/\s+/', ' ', trim($stat1[0])));
    usleep(75000); // 75ms sample
    $stat2 = @file('/proc/stat');
    $info2 = explode(' ', preg_replace('/\s+/', ' ', trim($stat2[0])));

    $idle1 = floatval($info1[4]) + floatval($info1[5]);
    $idle2 = floatval($info2[4]) + floatval($info2[5]);
    $total1 = array_sum(array_slice($info1, 1));
    $total2 = array_sum(array_slice($info2, 1));

    $diff_total = $total2 - $total1;
    $diff_idle = $idle2 - $idle1;
    if ($diff_total <= 0) return 0;
    $pct = round((($diff_total - $diff_idle) / $diff_total) * 100, 1);
    return max(0, min(100, $pct));
}

$cpuPercent = getVpsCpu();

// CPU Model & Cores
$cpuModel = 'Intel(R) Xeon(R) CPU E5-2690 v4 @ 2.60GHz';
$cpuCores = 1;
if (@file_exists('/proc/cpuinfo')) {
    $cpuLines = @file('/proc/cpuinfo');
    $cCount = 0;
    foreach ($cpuLines as $cl) {
        if (strpos($cl, 'model name') !== false) {
            $cpuModel = trim(explode(':', $cl)[1]);
        }
        if (strpos($cl, 'processor') !== false) {
            $cCount++;
        }
    }
    if ($cCount > 0) $cpuCores = $cCount;
}

$loadAvg = function_exists('sys_getloadavg') ? sys_getloadavg() : array(0, 0, 0);

// 2. RAM Memory via /proc/meminfo
$mem = array();
if (@file_exists('/proc/meminfo')) {
    foreach (@file('/proc/meminfo') as $line) {
        if (preg_match('/^([a-zA-Z0-9_]+):\s+([0-9]+)/', $line, $m)) {
            $mem[$m[1]] = intval($m[2]) * 1024; // in bytes
        }
    }
}
$memTotal = $mem['MemTotal'] ?? 0;
$memAvail = $mem['MemAvailable'] ?? ($mem['MemFree'] + ($mem['Buffers'] ?? 0) + ($mem['Cached'] ?? 0));
$memUsed = max(0, $memTotal - $memAvail);
$memPercent = $memTotal > 0 ? round(($memUsed / $memTotal) * 100, 1) : 0;

// 3. Disk Usage of Root /
$diskTotal = @disk_total_space('/') ?: 0;
$diskFree = @disk_free_space('/') ?: 0;
$diskUsed = max(0, $diskTotal - $diskFree);
$diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

// 4. Bandwidth Traffic Calculation via /proc/net/dev
$reqIface = isset($_GET['iface']) ? trim($_GET['iface']) : 'eth0';
$allIfaces = array();
$rxBytes = 0;
$txBytes = 0;

if (@file_exists('/proc/net/dev')) {
    foreach (@file('/proc/net/dev') as $line) {
        if (strpos($line, ':') !== false) {
            $parts = explode(':', $line);
            $ifName = trim($parts[0]);
            if ($ifName === 'lo') continue;
            $stats = preg_split('/\s+/', trim($parts[1]));
            $rx = floatval($stats[0]);
            $tx = floatval($stats[8]);
            $allIfaces[] = $ifName;
            if ($ifName === $reqIface) {
                $rxBytes = $rx;
                $txBytes = $tx;
            }
        }
    }
}

if (!in_array($reqIface, $allIfaces) && !empty($allIfaces)) {
    $reqIface = $allIfaces[0];
}

// Calculate delta bps using /dev/shm cache
$cacheFile = '/dev/shm/pacenet_vps_net_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $reqIface) . '.json';
$nowTime = microtime(true);
$txRateBps = 0;
$rxRateBps = 0;

if (@file_exists($cacheFile)) {
    $prev = @json_decode(@file_get_contents($cacheFile), true);
    if ($prev && !empty($prev['time'])) {
        $dt = $nowTime - $prev['time'];
        if ($dt > 0.2 && $dt < 15) {
            $dRx = max(0, $rxBytes - $prev['rx']);
            $dTx = max(0, $txBytes - $prev['tx']);
            $rxRateBps = round(($dRx * 8) / $dt);
            $txRateBps = round(($dTx * 8) / $dt);
        }
    }
}

@file_put_contents($cacheFile, json_encode(array(
    'time' => $nowTime,
    'rx' => $rxBytes,
    'tx' => $txBytes
)), LOCK_EX);

// 5. Uptime & OS details
$uptimeSec = 0;
if (@file_exists('/proc/uptime')) {
    $upParts = explode(' ', @file_get_contents('/proc/uptime'));
    $uptimeSec = intval($upParts[0]);
}

$days = floor($uptimeSec / 86400);
$hours = floor(($uptimeSec % 86400) / 3600);
$mins = floor(($uptimeSec % 3600) / 60);
$uptimeStr = ($days > 0 ? "{$days}d " : "") . "{$hours}h {$mins}m";

$osName = 'Linux x86_64';
if (@file_exists('/etc/os-release')) {
    $osInfo = @parse_ini_file('/etc/os-release');
    if (!empty($osInfo['PRETTY_NAME'])) {
        $osName = $osInfo['PRETTY_NAME'];
    }
}

$response = array(
    'status' => 'ok',
    'timestamp' => round($nowTime * 1000),
    'vps_info' => array(
        'public_ip' => '202.10.46.222',
        'os_name' => $osName,
        'uptime' => $uptimeStr,
        'uptime_sec' => $uptimeSec
    ),
    'cpu' => array(
        'percent' => $cpuPercent,
        'model' => $cpuModel,
        'cores' => $cpuCores,
        'load_avg' => array(
            round($loadAvg[0] ?? 0, 2),
            round($loadAvg[1] ?? 0, 2),
            round($loadAvg[2] ?? 0, 2)
        )
    ),
    'mem' => array(
        'total' => $memTotal,
        'used' => $memUsed,
        'avail' => $memAvail,
        'percent' => $memPercent,
        'used_fmt' => fmtBytes($memUsed),
        'total_fmt' => fmtBytes($memTotal),
        'avail_fmt' => fmtBytes($memAvail)
    ),
    'disk' => array(
        'total' => $diskTotal,
        'used' => $diskUsed,
        'free' => $diskFree,
        'percent' => $diskPercent,
        'used_fmt' => fmtBytes($diskUsed),
        'total_fmt' => fmtBytes($diskTotal),
        'free_fmt' => fmtBytes($diskFree)
    ),
    'bandwidth' => array(
        'iface' => $reqIface,
        'available_ifaces' => $allIfaces,
        'tx_bps' => $txRateBps,
        'rx_bps' => $rxRateBps,
        'tx_fmt' => fmtBps($txRateBps),
        'rx_fmt' => fmtBps($rxRateBps),
        'total_tx_fmt' => fmtBytes($txBytes),
        'total_rx_fmt' => fmtBytes($rxBytes)
    )
);

echo json_encode($response);
