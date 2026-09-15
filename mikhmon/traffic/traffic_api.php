<?php
header('Content-Type: application/json');
error_reporting(0);

$dbPath = '/var/www/mikhmon/data/traffic_history.db';
if (!file_exists($dbPath)) {
    echo json_encode(array('status' => 'error', 'message' => 'Database traffic history not found'));
    exit;
}

include_once(__DIR__ . '/../include/config.php');
include_once(__DIR__ . '/../include/readcfg.php');

$configuredRouters = array();
foreach ($data as $sessKey => $cfg) {
    if ($sessKey === 'mikhmon' || empty($sessKey)) continue;
    $ip = explode('!', $cfg[1] ?? '')[1] ?? '';
    if (empty($ip) || strpos($sessKey, 'new-') === 0) continue;
    $hsName = explode('%', $cfg[4] ?? '')[1] ?? $sessKey;
    if (empty($hsName)) $hsName = $sessKey;
    $configuredRouters[$sessKey] = $hsName;
}

$db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);

$router = $_GET['router'] ?? 'all';
$iface = $_GET['iface'] ?? 'all';
$period = $_GET['period'] ?? 'hourly';
$now = time();

function formatBytes($bytes) {
    if ($bytes <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

$categories = array();
$rxSeries = array();
$txSeries = array();
$routerSeries = array();
$tableRows = array();

$totalRx = 0;
$totalTx = 0;

$buckets = array();

if ($period === 'hourly') {
    // 24 hours (1 hour per bucket)
    for ($i = 23; $i >= 0; $i--) {
        $tStart = $now - (($i + 1) * 3600);
        $tEnd = $now - ($i * 3600);
        $label = date('H:i', $tEnd);
        $buckets[] = array('start' => $tStart, 'end' => $tEnd, 'label' => $label, 'period_label' => date('H:i', $tStart) . ' - ' . date('H:i', $tEnd));
    }
    $divisor = 1024 * 1024; // MB
    $unit = 'MB';
} elseif ($period === 'daily') {
    // 30 days
    for ($i = 29; $i >= 0; $i--) {
        $dayStart = strtotime("-$i day midnight", $now);
        $dayEnd = $dayStart + 86399;
        $label = date('d M', $dayStart);
        $buckets[] = array('start' => $dayStart, 'end' => $dayEnd, 'label' => $label, 'period_label' => date('l, d M Y', $dayStart));
    }
    $divisor = 1024 * 1024 * 1024; // GB
    $unit = 'GB';
} elseif ($period === 'weekly') {
    // 12 weeks
    for ($i = 11; $i >= 0; $i--) {
        $wStart = strtotime("-$i week Monday", $now);
        $wEnd = $wStart + (7 * 86400) - 1;
        $label = 'W' . date('W', $wStart);
        $buckets[] = array('start' => $wStart, 'end' => $wEnd, 'label' => $label, 'period_label' => date('d M', $wStart) . ' - ' . date('d M Y', $wEnd));
    }
    $divisor = 1024 * 1024 * 1024; // GB
    $unit = 'GB';
} elseif ($period === 'monthly') {
    // 12 months
    for ($i = 11; $i >= 0; $i--) {
        $mStart = strtotime("-$i month first day of this month midnight", $now);
        $mEnd = strtotime("last day of this month 23:59:59", $mStart);
        $label = date('M Y', $mStart);
        $buckets[] = array('start' => $mStart, 'end' => $mEnd, 'label' => $label, 'period_label' => date('F Y', $mStart));
    }
    $divisor = 1024 * 1024 * 1024; // GB
    $unit = 'GB';
} elseif ($period === 'yearly') {
    // Last 3 years
    $curYear = intval(date('Y', $now));
    for ($y = $curYear - 2; $y <= $curYear; $y++) {
        $yStart = strtotime("$y-01-01 00:00:00");
        $yEnd = strtotime("$y-12-31 23:59:59");
        $label = (string)$y;
        $buckets[] = array('start' => $yStart, 'end' => $yEnd, 'label' => $label, 'period_label' => "Tahun " . $y);
    }
    $divisor = 1024 * 1024 * 1024; // GB
    $unit = 'GB';
}

$colors = array('#00d2d3', '#2ed573', '#ffa502', '#ff4757', '#9b59b6', '#3742fa');
$cIdx = 0;
foreach ($configuredRouters as $sKey => $sName) {
    $routerSeries[$sKey] = array(
        'session' => $sKey,
        'name' => $sName,
        'color' => $colors[$cIdx % count($colors)],
        'data' => array()
    );
    $cIdx++;
}

foreach ($buckets as $b) {
    $categories[] = $b['label'];
    $tStart = $b['start'];
    $tEnd = $b['end'];

    // Condition setup
    $whereParts = array("timestamp >= :ts AND timestamp <= :te");
    if ($iface !== 'all' && !empty($iface)) {
        $whereParts[] = "interface = :iface";
    } else {
        $whereParts[] = "interface NOT LIKE 'wg%' AND interface != 'lo'";
    }

    if ($router !== 'all' && !empty($router)) {
        $whereParts[] = "session = :router";
    }

    $whereClause = implode(" AND ", $whereParts);

    // Total query for this bucket
    $qTotal = "SELECT SUM(delta_rx) as s_rx, SUM(delta_tx) as s_tx FROM traffic_samples WHERE " . $whereClause;
    $stmtTot = $db->prepare($qTotal);
    $stmtTot->bindValue(':ts', $tStart, SQLITE3_INTEGER);
    $stmtTot->bindValue(':te', $tEnd, SQLITE3_INTEGER);
    if ($iface !== 'all' && !empty($iface)) {
        $stmtTot->bindValue(':iface', $iface, SQLITE3_TEXT);
    }
    if ($router !== 'all' && !empty($router)) {
        $stmtTot->bindValue(':router', $router, SQLITE3_TEXT);
    }
    $resTot = $stmtTot->execute()->fetchArray(SQLITE3_ASSOC);
    $rx = max(0, intval($resTot['s_rx'] ?? 0));
    $tx = max(0, intval($resTot['s_tx'] ?? 0));
    $bTot = $rx + $tx;

    $totalRx += $rx;
    $totalTx += $tx;

    $rxSeries[] = round($rx / $divisor, 2);
    $txSeries[] = round($tx / $divisor, 2);

    // Per-router breakdown for this bucket
    $qR = "SELECT session, SUM(delta_rx) as r_rx, SUM(delta_tx) as r_tx FROM traffic_samples WHERE " . $whereClause . " GROUP BY session";
    $stmtR = $db->prepare($qR);
    $stmtR->bindValue(':ts', $tStart, SQLITE3_INTEGER);
    $stmtR->bindValue(':te', $tEnd, SQLITE3_INTEGER);
    if ($iface !== 'all' && !empty($iface)) {
        $stmtR->bindValue(':iface', $iface, SQLITE3_TEXT);
    }
    if ($router !== 'all' && !empty($router)) {
        $stmtR->bindValue(':router', $router, SQLITE3_TEXT);
    }
    $resR = $stmtR->execute();
    $bucketRouterMap = array();
    while ($rRow = $resR->fetchArray(SQLITE3_ASSOC)) {
        $sKey = $rRow['session'];
        $rRx = max(0, intval($rRow['r_rx'] ?? 0));
        $rTx = max(0, intval($rRow['r_tx'] ?? 0));
        $bucketRouterMap[$sKey] = array('rx' => $rRx, 'tx' => $rTx, 'tot' => $rRx + $rTx);
    }

    foreach ($configuredRouters as $sKey => $sName) {
        $rData = $bucketRouterMap[$sKey] ?? array('rx' => 0, 'tx' => 0, 'tot' => 0);
        $routerSeries[$sKey]['data'][] = round($rData['tot'] / $divisor, 2);

        // Add to table if router matches or is all and has activity or total row
        if (($router === 'all' || $router === $sKey) && $rData['tot'] > 0) {
            $tableRows[] = array(
                'router_key' => $sKey,
                'router_name' => $sName,
                'period' => $b['period_label'],
                'rx_formatted' => formatBytes($rData['rx']),
                'tx_formatted' => formatBytes($rData['tx']),
                'total_formatted' => formatBytes($rData['tot']),
                'total_raw' => $rData['tot']
            );
        }
    }

    if ($router === 'all' && $bTot > 0) {
        $tableRows[] = array(
            'router_key' => 'ALL',
            'router_name' => '★ TOTAL SEMUA ROUTER',
            'period' => $b['period_label'],
            'rx_formatted' => formatBytes($rx),
            'tx_formatted' => formatBytes($tx),
            'total_formatted' => formatBytes($bTot),
            'total_raw' => $bTot,
            'is_total_row' => true
        );
    }
}

$db->close();

$grandTotal = $totalRx + $totalTx;

// Calculate percentage shares
foreach ($tableRows as &$tRow) {
    $pct = ($grandTotal > 0) ? round(($tRow['total_raw'] / $grandTotal) * 100, 1) : 0;
    $tRow['percentage'] = $pct . '%';
}
unset($tRow);

$tableRows = array_reverse($tableRows);

echo json_encode(array(
    'status' => 'success',
    'router' => $router,
    'interface' => $iface,
    'period' => $period,
    'unit' => $unit,
    'summary' => array(
        'total_rx_bytes' => $totalRx,
        'total_tx_bytes' => $totalTx,
        'grand_total_bytes' => $grandTotal,
        'total_rx_formatted' => formatBytes($totalRx),
        'total_tx_formatted' => formatBytes($totalTx),
        'grand_total_formatted' => formatBytes($grandTotal),
        'configured_routers_count' => count($configuredRouters)
    ),
    'categories' => $categories,
    'rx_series' => $rxSeries,
    'tx_series' => $txSeries,
    'router_series' => array_values($routerSeries),
    'table' => $tableRows
));
