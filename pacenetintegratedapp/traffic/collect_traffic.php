<?php
ini_set('display_errors', 0);
error_reporting(0);

$dbPath = '/var/www/pacenetintegratedapp/data/traffic_history.db';
$db = new SQLite3($dbPath);
$db->exec("CREATE TABLE IF NOT EXISTS traffic_samples (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session TEXT,
    interface TEXT,
    rx_bytes INTEGER,
    tx_bytes INTEGER,
    delta_rx INTEGER,
    delta_tx INTEGER,
    timestamp INTEGER
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_iface_ts ON traffic_samples(interface, timestamp)");

include_once('/var/www/pacenetintegratedapp/include/config.php');
include_once('/var/www/pacenetintegratedapp/lib/routeros_api.class.php');

$api = new RouterosAPI();
$api->debug = false;
$api->timeout = 2;
$api->attempts = 1;

foreach ($data as $sessKey => $sessVal) {
    if ($sessKey === 'mikhmon') continue;
    $iphost = explode("!", $sessVal[1] ?? '')[1] ?? '';
    $userhost = explode("@|@", $sessVal[2] ?? '')[1] ?? '';
    $passwdhost = explode("#|#", $sessVal[3] ?? '')[1] ?? '';
    if (empty($iphost) || empty($userhost)) continue;
    
    if ($api->connect($iphost, $userhost, decrypt($passwdhost))) {
        $ifaces = $api->comm('/interface/print');
        $now = time();
        
        $db->exec('BEGIN TRANSACTION');
        $stmtLast = $db->prepare("SELECT rx_bytes, tx_bytes FROM traffic_samples WHERE session = :sess AND interface = :iface ORDER BY id DESC LIMIT 1");
        $stmtIns = $db->prepare("INSERT INTO traffic_samples (session, interface, rx_bytes, tx_bytes, delta_rx, delta_tx, timestamp) VALUES (:sess, :iface, :rx, :tx, :drx, :dtx, :ts)");
        
        foreach ($ifaces as $if) {
            $name = $if['name'];
            $curRx = intval($if['rx-byte'] ?? 0);
            $curTx = intval($if['tx-byte'] ?? 0);
            
            $stmtLast->bindValue(':sess', $sessKey, SQLITE3_TEXT);
            $stmtLast->bindValue(':iface', $name, SQLITE3_TEXT);
            $lastRow = $stmtLast->execute()->fetchArray(SQLITE3_ASSOC);
            
            $drx = 0;
            $dtx = 0;
            if ($lastRow) {
                $lastRx = intval($lastRow['rx_bytes']);
                $lastTx = intval($lastRow['tx_bytes']);
                $drx = ($curRx >= $lastRx) ? ($curRx - $lastRx) : $curRx;
                $dtx = ($curTx >= $lastTx) ? ($curTx - $lastTx) : $curTx;
            }
            
            $stmtIns->bindValue(':sess', $sessKey, SQLITE3_TEXT);
            $stmtIns->bindValue(':iface', $name, SQLITE3_TEXT);
            $stmtIns->bindValue(':rx', $curRx, SQLITE3_INTEGER);
            $stmtIns->bindValue(':tx', $curTx, SQLITE3_INTEGER);
            $stmtIns->bindValue(':drx', $drx, SQLITE3_INTEGER);
            $stmtIns->bindValue(':dtx', $dtx, SQLITE3_INTEGER);
            $stmtIns->bindValue(':ts', $now, SQLITE3_INTEGER);
            $stmtIns->execute();
        }
        $db->exec('COMMIT');
        $api->disconnect();
    }
}
$db->close();
