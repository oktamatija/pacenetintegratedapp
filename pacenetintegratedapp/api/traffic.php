<?php
/**
 * Pacenet REST API - Multi-Router Traffic History from SQLite
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

$dbPath = '/var/www/pacenetintegratedapp/data/traffic_history.db';
if (!file_exists($dbPath)) {
    // Check local fallback
    $dbPath = __DIR__ . '/../data/traffic_history.db';
}

if (!file_exists($dbPath)) {
    jsonResponse(false, null, 'Database traffic history belum tersedia di server.', 404);
}

$configuredRouters = array();
foreach ($data as $sessKey => $cfg) {
    if ($sessKey === 'mikhmon' || empty($sessKey) || strpos($sessKey, 'new-') === 0) continue;
    $ip = explode('!', $cfg[1] ?? '')[1] ?? '';
    if (empty($ip)) continue;
    $hsName = explode('%', $cfg[4] ?? '')[1] ?? $sessKey;
    if (empty($hsName)) $hsName = $sessKey;
    $configuredRouters[$sessKey] = $hsName;
}

$db = new SQLite3($dbPath, SQLITE3_OPEN_READONLY);
$db->busyTimeout(3000);

$router = $_GET['router'] ?? 'all';
$iface = $_GET['iface'] ?? 'all';
$period = $_GET['period'] ?? 'hourly';
$now = time();

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
        $wStart = strtotime("monday this week -$i week midnight", $now);
        $wEnd = $wStart + (7 * 86400) - 1;
        $label = 'W' . date('W', $wStart);
        $buckets[] = array('start' => $wStart, 'end' => $wEnd, 'label' => $label, 'period_label' => date('d M', $wStart) . ' - ' . date('d M Y', $wEnd));
    }
    $divisor = 1024 * 1024 * 1024; // GB
    $unit = 'GB';
} elseif ($period === 'monthly') {
    // 12 months
    for ($i = 11; $i >= 0; $i--) {
        $mStart = strtotime("first day of -$i month midnight", $now);
        $mEnd = strtotime("last day of -$i month 23:59:59", $now);
        $label = date('M Y', $mStart);
        $buckets[] = array('start' => $mStart, 'end' => $mEnd, 'label' => $label, 'period_label' => date('F Y', $mStart));
    }
    $divisor = 1024 * 1024 * 1024; // GB
    $unit = 'GB';
} elseif ($period === 'yearly') {
    // 5 years
    for ($i = 4; $i >= 0; $i--) {
        $y = date('Y', $now) - $i;
        $yStart = strtotime("$y-01-01 00:00:00");
        $yEnd = strtotime("$y-12-31 23:59:59");
        $label = (string)$y;
        $buckets[] = array('start' => $yStart, 'end' => $yEnd, 'label' => $label, 'period_label' => "Tahun $y");
    }
    $divisor = 1024 * 1024 * 1024 * 1024; // TB
    $unit = 'TB';
}

$whereClauses = array();
if ($router !== 'all') {
    $whereClauses[] = "router = '" . SQLite3::escapeString($router) . "'";
}
if ($iface !== 'all') {
    $whereClauses[] = "interface = '" . SQLite3::escapeString($iface) . "'";
}
$baseWhere = count($whereClauses) > 0 ? " AND " . implode(" AND ", $whereClauses) : "";

foreach ($configuredRouters as $rKey => $rName) {
    $routerSeries[$rKey] = array(
        'name' => $rName,
        'data' => array()
    );
}

foreach ($buckets as $b) {
    $categories[] = $b['label'];
    $q = "SELECT router, SUM(rx_delta) as tot_rx, SUM(tx_delta) as tot_tx 
          FROM traffic_samples 
          WHERE timestamp >= {$b['start']} AND timestamp <= {$b['end']} {$baseWhere}
          GROUP BY router";
    $res = $db->query($q);

    $bRx = 0;
    $bTx = 0;
    $routerValues = array();

    if ($res) {
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rRx = floatval($row['tot_rx'] ?? 0);
            $rTx = floatval($row['tot_tx'] ?? 0);
            $bRx += $rRx;
            $bTx += $rTx;
            $routerValues[$row['router']] = ($rRx + $rTx);
        }
    }

    $totalRx += $bRx;
    $totalTx += $bTx;

    $rxVal = round($bRx / $divisor, 2);
    $txVal = round($bTx / $divisor, 2);
    $rxSeries[] = $rxVal;
    $txSeries[] = $txVal;

    foreach ($configuredRouters as $rKey => $rName) {
        $val = isset($routerValues[$rKey]) ? round($routerValues[$rKey] / $divisor, 2) : 0;
        $routerSeries[$rKey]['data'][] = $val;
    }

    $tableRows[] = array(
        'period_label' => $b['period_label'],
        'rx_bytes' => $bRx,
        'tx_bytes' => $bTx,
        'total_bytes' => ($bRx + $bTx),
        'rx_formatted' => formatBytesReadable($bRx),
        'tx_formatted' => formatBytesReadable($bTx),
        'total_formatted' => formatBytesReadable($bRx + $bTx)
    );
}

$db->close();

$routerSeriesList = array();
foreach ($routerSeries as $k => $s) {
    $routerSeriesList[] = $s;
}

jsonResponse(true, array(
    'unit' => $unit,
    'categories' => $categories,
    'rxSeries' => $rxSeries,
    'txSeries' => $txSeries,
    'routerSeries' => $routerSeriesList,
    'tableRows' => array_reverse($tableRows),
    'totals' => array(
        'rx_bytes' => $totalRx,
        'tx_bytes' => $totalTx,
        'combined_bytes' => ($totalRx + $totalTx),
        'rx_formatted' => formatBytesReadable($totalRx),
        'tx_formatted' => formatBytesReadable($totalTx),
        'combined_formatted' => formatBytesReadable($totalRx + $totalTx)
    ),
    'configuredRouters' => $configuredRouters
));
