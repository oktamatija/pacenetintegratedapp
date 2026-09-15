<?php
/*
 *  Pacenet Billing System - Resource & Bandwidth API
 *  Provides real-time metrics for CPU, RAM, Disk, and Bandwidth usage.
 */
session_start();
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["mikhmon"])) {
    http_response_code(401);
    echo json_encode(array('status' => 'error', 'message' => 'Unauthorized'));
    exit;
}

$session = isset($_GET['session']) ? trim($_GET['session']) : '';
if (empty($session)) {
    echo json_encode(array('status' => 'error', 'message' => 'Missing session'));
    exit;
}

include_once('../include/config.php');
include_once('../include/readcfg.php');
include_once('../lib/routeros_api.class.php');
include_once('../lib/formatbytesbites.php');

$API = new RouterosAPI();
$API->debug = false;
$API->timeout = 2;
$API->attempts = 1;

if (!$API->connect($iphost, $userhost, decrypt($passwdhost))) {
    echo json_encode(array('status' => 'error', 'message' => 'Failed to connect to router'));
    exit;
}

// 1. Fetch System Resource
$resourcePrint = $API->comm('/system/resource/print');
$res = $resourcePrint[0] ?? array();

$cpuLoad = intval($res['cpu-load'] ?? 0);
$cpuCount = intval($res['cpu-count'] ?? 1);
$cpuFreq = intval($res['cpu-frequency'] ?? 0);

$totalMem = intval($res['total-memory'] ?? 0);
$freeMem = intval($res['free-memory'] ?? 0);
$usedMem = max(0, $totalMem - $freeMem);
$memPercent = $totalMem > 0 ? round(($usedMem / $totalMem) * 100, 1) : 0;

$totalHdd = intval($res['total-hdd-space'] ?? 0);
$freeHdd = intval($res['free-hdd-space'] ?? 0);
$usedHdd = max(0, $totalHdd - $freeHdd);
$hddPercent = $totalHdd > 0 ? round(($usedHdd / $totalHdd) * 100, 1) : 0;

// 2. Determine Interface & Fetch Bandwidth
$reqIface = isset($_GET['iface']) ? trim($_GET['iface']) : '';
if (empty($reqIface)) {
    $ifaces = $API->comm('/interface/print');
    $reqIface = $ifaces[0]['name'] ?? 'ether1';
    foreach ($ifaces as $if) {
        if ($if['name'] === 'Vlan1' || $if['name'] === 'ether1' || $if['name'] === 'sfp-sfpplus1') {
            $reqIface = $if['name'];
            break;
        }
    }
}

$trafficPrint = $API->comm('/interface/monitor-traffic', array(
    'interface' => $reqIface,
    'once' => ''
));

$tx = intval($trafficPrint[0]['tx-bits-per-second'] ?? 0);
$rx = intval($trafficPrint[0]['rx-bits-per-second'] ?? 0);

$API->disconnect();

function fmtBytes($bytes, $precision = 1) {
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

$response = array(
    'status' => 'ok',
    'timestamp' => round(microtime(true) * 1000),
    'uptime' => $res['uptime'] ?? '',
    'board_name' => $res['board-name'] ?? '',
    'version' => $res['version'] ?? '',
    'cpu' => array(
        'load' => $cpuLoad,
        'count' => $cpuCount,
        'freq' => $cpuFreq,
        'text' => $cpuLoad . '%'
    ),
    'ram' => array(
        'total' => $totalMem,
        'free' => $freeMem,
        'used' => $usedMem,
        'percent' => $memPercent,
        'used_fmt' => fmtBytes($usedMem),
        'total_fmt' => fmtBytes($totalMem),
        'free_fmt' => fmtBytes($freeMem)
    ),
    'disk' => array(
        'total' => $totalHdd,
        'free' => $freeHdd,
        'used' => $usedHdd,
        'percent' => $hddPercent,
        'used_fmt' => fmtBytes($usedHdd),
        'total_fmt' => fmtBytes($totalHdd),
        'free_fmt' => fmtBytes($freeHdd)
    ),
    'bandwidth' => array(
        'iface' => $reqIface,
        'tx' => $tx,
        'rx' => $rx,
        'tx_fmt' => fmtBps($tx),
        'rx_fmt' => fmtBps($rx)
    )
);

echo json_encode($response);
