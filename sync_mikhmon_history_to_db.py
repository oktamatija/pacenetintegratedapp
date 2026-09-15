import paramiko
from deploy_config import VPS_HOST, VPS_PORT, VPS_USER, VPS_PASS

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(VPS_HOST, port=VPS_PORT, username=VPS_USER, password=VPS_PASS, timeout=10)

sync_php = """<?php
require_once '/var/www/pacenetintegratedapp/api/common.php';
global $data;

$pg = getPgDb();
if (!$pg) {
    die("Cannot connect to PostgreSQL database\\n");
}

$now = time();
$totalFound = 0;
$expiredCount = 0;
$activeCount = 0;
$updatedCount = 0;
$radcheckDeleted = 0;

$expiredUsers = array();

foreach ($data as $sName => $sCfg) {
    if ($sName === 'mikhmon' || empty($sName) || strpos($sName, 'new-') === 0 || empty($sCfg[1])) continue;
    $conn = connectMikrotik($sName, 10);
    if (!$conn) {
        echo "Failed to connect to $sName\\n";
        continue;
    }
    
    $api = $conn['api'];
    $scripts = $api->comm('/system/script/print', array('?comment' => 'mikhmon'));
    echo "$sName: found " . count($scripts) . " scripts\\n";
    
    foreach ($scripts as $sc) {
        $scName = $sc['name'] ?? '';
        $parts = explode('-|-', $scName);
        if (count($parts) < 3) continue;
        
        $totalFound++;
        
        // Format: date-|-time-|-username-|-price-|-ip-|-mac-|-validity-|-profile-|-comment-
        $sDate = $parts[0] ?? '';
        $sTime = $parts[1] ?? '';
        $uname = trim($parts[2] ?? '');
        $price = isset($parts[3]) ? floatval($parts[3]) : 0;
        $ip = $parts[4] ?? '';
        $mac = $parts[5] ?? '';
        $validityStr = $parts[6] ?? '12h';
        $profile = $parts[7] ?? '12-jam';
        $comment = $parts[8] ?? '';
        
        if (empty($uname)) continue;
        
        // Parse date e.g. 'sep/14/2026 22:14:18'
        $dateStr = str_replace('/', ' ', $sDate) . " $sTime";
        $loginTs = strtotime($dateStr);
        if ($loginTs === false) {
            $loginTs = time();
        }
        
        $durationParsed = parseBilingualDuration($validityStr);
        $valSec = $durationParsed['valid'] ? $durationParsed['seconds'] : 43200; // default 12h
        
        $expTs = $loginTs + $valSec;
        $isExpired = ($now >= $expTs);
        
        $status = $isExpired ? 'expired' : 'active';
        if ($isExpired) {
            $expiredCount++;
            $expiredUsers[] = $uname;
        } else {
            $activeCount++;
        }
        
        $firstLoginFormatted = date('Y-m-d H:i:s', $loginTs);
        
        // Update or insert into pacenet_vouchers
        // Check if exists
        $checkRes = pg_query_params($pg, "SELECT id, status FROM pacenet_vouchers WHERE username = $1", array($uname));
        if ($checkRes && pg_num_rows($checkRes) > 0) {
            $row = pg_fetch_assoc($checkRes);
            pg_query_params($pg, "
                UPDATE pacenet_vouchers 
                SET status = $1, first_login = $2, price = CASE WHEN price = 0 THEN $3 ELSE price END,
                    profile = CASE WHEN profile = '' THEN $4 ELSE profile END,
                    validity = $5, last_seen = $6
                WHERE username = $7
            ", array($status, $firstLoginFormatted, $price, $profile, $validityStr, date('Y-m-d H:i:s'), $uname));
            $updatedCount++;
        } else {
            pg_query_params($pg, "
                INSERT INTO pacenet_vouchers 
                (username, password, profile, price, validity, comment, status, first_login, created_at, router_origin)
                VALUES ($1, $1, $2, $3, $4, $5, $6, $7, $8, $9)
                ON CONFLICT (username) DO UPDATE
                SET status = EXCLUDED.status, first_login = EXCLUDED.first_login
            ", array($uname, $profile, $price, $validityStr, $comment, $status, $firstLoginFormatted, $firstLoginFormatted, $sName));
            $updatedCount++;
        }
    }
    
    $api->disconnect();
}

echo "=== Summary of Mikhmon History Sync ===\\n";
echo "Total scripts analyzed: $totalFound\\n";
echo "Marked expired: $expiredCount\\n";
echo "Marked active: $activeCount\\n";
echo "Database records updated: $updatedCount\\n";

// Now delete all expired users from radcheck and radusergroup
if (!empty($expiredUsers)) {
    echo "Purging " . count($expiredUsers) . " expired users from radcheck and radusergroup...\\n";
    $chunkSize = 100;
    $chunks = array_chunk($expiredUsers, $chunkSize);
    foreach ($chunks as $chunk) {
        $escList = "'" . implode("','", array_map('pg_escape_string', $chunk)) . "'";
        $delCheck = pg_query($pg, "DELETE FROM radcheck WHERE username IN ($escList)");
        $delGroup = pg_query($pg, "DELETE FROM radusergroup WHERE username IN ($escList)");
        $radcheckDeleted += pg_affected_rows($delCheck);
    }
    echo "Deleted $radcheckDeleted records from radcheck.\\n";
}

// Check voucher 3Z575P specifically
$res3z = pg_query_params($pg, "SELECT username, status, first_login, validity, profile FROM pacenet_vouchers WHERE username = '3Z575P'", array());
if ($res3z && pg_num_rows($res3z) > 0) {
    $row3z = pg_fetch_assoc($res3z);
    echo "\\nVoucher 3Z575P status now: " . json_encode($row3z) . "\\n";
}
$rad3z = pg_query($pg, "SELECT count(*) FROM radcheck WHERE username = '3Z575P'");
echo "Voucher 3Z575P in radcheck count: " . pg_fetch_result($rad3z, 0, 0) . "\\n";
"""

sftp = ssh.open_sftp()
with sftp.open('/tmp/sync_history.php', 'w') as f:
    f.write(sync_php)
sftp.close()

stdin, stdout, stderr = ssh.exec_command("php /tmp/sync_history.php")
print(stdout.read().decode())
print(stderr.read().decode())

ssh.close()
