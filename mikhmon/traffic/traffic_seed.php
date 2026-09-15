<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dbPath = '/var/www/mikhmon/data/traffic_history.db';
$db = new SQLite3($dbPath);
$db->exec("CREATE TABLE IF NOT EXISTS traffic_samples (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session TEXT,
    interface TEXT,
    rx_bytes INTEGER,
    tx_bytes INTEGER,
    timestamp INTEGER
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_sess_iface ON traffic_samples(session, interface, timestamp)");

include_once('/var/www/mikhmon/include/config.php');
include_once('/var/www/mikhmon/lib/routeros_api.class.php');
$api = new RouterosAPI();
$api->connect('10.10.10.2', 'Yunus2026', 'Yunus2026');
$ifaces = $api->comm('/interface/print');
$api->disconnect();

$now = time();
$db->exec('BEGIN TRANSACTION');
$stmt = $db->prepare("INSERT INTO traffic_samples (session, interface, rx_bytes, tx_bytes, timestamp) VALUES (:sess, :iface, :rx, :tx, :ts)");

foreach ($ifaces as $if) {
    $name = $if['name'];
    $curRx = intval($if['rx-byte'] ?? 0);
    $curTx = intval($if['tx-byte'] ?? 0);
    if ($curRx == 0 && $curTx == 0) continue;
    
    // Generate 365 daily points backwards from $now
    // Daily transfer is a fraction of total
    $totalDays = 365;
    $dailyAvgRx = $curRx / $totalDays;
    $dailyAvgTx = $curTx / $totalDays;
    
    $runningRx = $curRx;
    $runningTx = $curTx;
    
    // Store current point
    $stmt->bindValue(':sess', 'Hotspot-Yunus', SQLITE3_TEXT);
    $stmt->bindValue(':iface', $name, SQLITE3_TEXT);
    $stmt->bindValue(':rx', $runningRx, SQLITE3_INTEGER);
    $stmt->bindValue(':tx', $runningTx, SQLITE3_INTEGER);
    $stmt->bindValue(':ts', $now, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Store hourly points for the last 48 hours
    for ($h = 1; $h <= 48; $h++) {
        $ts = $now - ($h * 3600);
        // hourly delta varies slightly
        $hourFactor = 0.5 + (sin($h) * 0.4);
        $deltaRx = max(1000, round(($dailyAvgRx / 24) * $hourFactor));
        $deltaTx = max(1000, round(($dailyAvgTx / 24) * $hourFactor));
        $runningRx = max(0, $runningRx - $deltaRx);
        $runningTx = max(0, $runningTx - $deltaTx);
        
        $stmt->bindValue(':sess', 'Hotspot-Yunus', SQLITE3_TEXT);
        $stmt->bindValue(':iface', $name, SQLITE3_TEXT);
        $stmt->bindValue(':rx', $runningRx, SQLITE3_INTEGER);
        $stmt->bindValue(':tx', $runningTx, SQLITE3_INTEGER);
        $stmt->bindValue(':ts', $ts, SQLITE3_INTEGER);
        $stmt->execute();
    }
    
    // Store daily points for the last 365 days
    for ($d = 3; $d <= 365; $d++) {
        $ts = $now - ($d * 86400);
        $dayFactor = 0.7 + (cos($d) * 0.3);
        $deltaRx = max(10000, round($dailyAvgRx * $dayFactor));
        $deltaTx = max(10000, round($dailyAvgTx * $dayFactor));
        $runningRx = max(0, $runningRx - $deltaRx);
        $runningTx = max(0, $runningTx - $deltaTx);
        
        $stmt->bindValue(':sess', 'Hotspot-Yunus', SQLITE3_TEXT);
        $stmt->bindValue(':iface', $name, SQLITE3_TEXT);
        $stmt->bindValue(':rx', $runningRx, SQLITE3_INTEGER);
        $stmt->bindValue(':tx', $runningTx, SQLITE3_INTEGER);
        $stmt->bindValue(':ts', $ts, SQLITE3_INTEGER);
        $stmt->execute();
    }
}
$db->exec('COMMIT');
$db->close();
echo "Seed completed successfully.\n";
