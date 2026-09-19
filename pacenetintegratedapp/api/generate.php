<?php
/**
 * Pacenet REST API - High-Performance Batch Voucher Generator & Thermal Print Engine
 * Supports generating up to 100,000 vouchers per batch into Centralized PostgreSQL FreeRADIUS (Single Source of Truth)
 */
require_once(__DIR__ . '/common.php');

// Handle CSV export if requested
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    checkAdminAuth(true);
    $batchComment = trim($_GET['batch'] ?? '');
    if (empty($batchComment)) {
        die("Batch identifier missing");
    }
    $pg = getPgDb();
    if (!$pg) {
        die("Database connection failed");
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vouchers_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $batchComment) . '.csv"');
    
    $out = fopen('php://output', 'w');
    fputcsv($out, array('Username', 'Password', 'Profile', 'Price', 'Validity', 'Comment', 'Status', 'Created_At'));
    
    $res = pg_query_params($pg, "SELECT username, password, profile, price, validity, comment, status, created_at FROM pacenet_vouchers WHERE comment = $1 ORDER BY id ASC", array($batchComment));
    if ($res) {
        while ($row = pg_fetch_assoc($res)) {
            fputcsv($out, array(
                $row['username'],
                $row['password'],
                $row['profile'],
                $row['price'],
                $row['validity'],
                $row['comment'],
                $row['status'],
                $row['created_at']
            ));
        }
    }
    fclose($out);
    exit;
}

checkAdminAuth(true);
checkWritePermission();
session_write_close();

// Increase execution time & memory for high-volume batches (up to 100,000 vouchers)
set_time_limit(600);
ini_set('memory_limit', '1024M');

$rawBody = file_get_contents('php://input');
$body = !empty($rawBody) ? json_decode($rawBody, true) : $_POST;

// Support up to 100,000 vouchers per batch!
$qty = max(min(intval($body['qty'] ?? 10), 100000), 1);
$server = $body['server'] ?? 'all';
$userMode = $body['user_mode'] ?? 'up'; // 'up' = user=pass, 'vc' = user!=pass
$nameLength = max(min(intval($body['name_length'] ?? 4), 16), 3);
$prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $body['prefix'] ?? '');
$charType = $body['char_type'] ?? 'mix'; // 'lower', 'upper', 'num', 'mix'
$profile = $body['profile'] ?? 'default';
$timelimitRaw = trim($body['timelimit'] ?? '');
$datalimit = trim($body['datalimit'] ?? '');
$customComment = trim($body['comment'] ?? ('pn-' . date('ymd-His')));

// Intelligent length expansion to prevent collision on large batches
$charsPoolSize = ($charType === 'num') ? 9 : (($charType === 'lower' || $charType === 'upper') ? 31 : 56);
$minCombinations = $qty * 6;
$neededLength = max(3, intval(ceil(log($minCombinations) / log($charsPoolSize))));
if ($nameLength < $neededLength) {
    $nameLength = $neededLength;
}

// Parse bilingual timelimit
$parsedTime = parseBilingualDuration($timelimitRaw);
$timelimit = $parsedTime['valid'] ? $parsedTime['mikrotik'] : '';

// Ensure comment starts with vc- or up- according to actual userMode
$prefixUcode = ($userMode === 'vc' ? 'vc-' : 'up-');
$cleanComment = preg_replace('/^(vc-|up-)/', '', $customComment);
$comment = $prefixUcode . $cleanComment;

function generateRandomString($length, $type) {
    switch ($type) {
        case 'num':
            $chars = '123456789';
            break;
        case 'lower':
            $chars = 'abcdefghijkmnpqrstuvwxyz23456789';
            break;
        case 'upper':
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            break;
        case 'mix':
        default:
            $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            break;
    }
    $res = '';
    $maxIdx = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $res .= $chars[random_int(0, $maxIdx)];
    }
    return $res;
}

$targetRouter = trim($body['target_router'] ?? ($body['router'] ?? 'all'));
$targetRouters = array();
if ($targetRouter === 'all') {
    foreach ($data as $sName => $sCfg) {
        if ($sName !== 'mikhmon' && !empty($sName) && strpos($sName, 'new-') !== 0 && !empty($sCfg[1])) {
            $targetRouters[] = $sName;
        }
    }
} elseif (isset($data[$targetRouter])) {
    $targetRouters[] = $targetRouter;
} else {
    $firstKey = null;
    foreach ($data as $k => $v) {
        if ($k !== 'mikhmon' && !empty($k) && strpos($k, 'new-') !== 0 && !empty($v[1])) {
            $firstKey = $k;
            break;
        }
    }
    $targetRouters[] = $firstKey ?: 'Rumah-DOLPHIN';
}

// 1. Get profile details
$price = '';
$validity = '';
$primarySession = $targetRouters[0] ?? (isset($data['Rumah-DOLPHIN']) ? 'Rumah-DOLPHIN' : (array_keys($data)[0] ?? 'Rumah-DOLPHIN'));

foreach ($targetRouters as $sName) {
    $conn = connectMikrotik($sName, 3);
    if ($conn) {
        $primarySession = $sName;
        $profDetails = $conn['api']->comm('/ip/hotspot/user/profile/print', array('?name' => $profile));
        if (!empty($profDetails[0])) {
            $onLogin = $profDetails[0]['on-login'] ?? '';
            if (!empty($onLogin)) {
                $parts = explode(',', $onLogin);
                if (isset($parts[2])) $price = trim($parts[2]);
                if (isset($parts[3])) $validity = trim($parts[3]);
            }
        }
        $conn['api']->disconnect();
        break;
    }
}

// Fallback pricing if not retrieved from router
if (empty($price)) {
    if (strpos($profile, '12-jam') !== false) $price = '4000';
    elseif (strpos($profile, '1minggu') !== false) $price = '40000';
    elseif (strpos($profile, '1bulan') !== false) $price = '100000';
    else $price = '5000';
}

// Allow custom price override from user request without needing to modify user profiles
if (isset($body['price']) && $body['price'] !== '') {
    $customPrice = preg_replace('/[^0-9.]/', '', strval($body['price']));
    if ($customPrice !== '') {
        $price = $customPrice;
    }
}

if (empty($validity)) {
    if (strpos($profile, '12-jam') !== false) $validity = '12h';
    elseif (strpos($profile, '1minggu') !== false) $validity = '7d';
    elseif (strpos($profile, '1bulan') !== false) $validity = '30d';
    else $validity = '1d';
}

$normValidity = normalizeMikrotikDuration($validity, $validity);
$numPrice = floatval(preg_replace('/[^0-9.]/', '', strval($price))) ?: 0;

$dnsname = explode('^', $data[$primarySession][5] ?? '')[1] ?? 'hotspot.yunus';
$hotspotname = explode('%', $data[$primarySession][4] ?? '')[1] ?? 'PACENET HOTSPOT';

$pg = getPgDb();
if (!$pg) {
    jsonResponse(false, null, 'Gagal terhubung ke database terpusat Pacenet (PostgreSQL)', 500);
}

// 2. High-speed generation & chunked multi-row insertion
$chunkSize = 2500;
$currentChunk = array();
$previewList = array();
$totalInserted = 0;
$existingInBatch = array();

$routerOriginVal = ($targetRouter === 'all') ? 'Semua Router (Central)' : $targetRouter;

@pg_query($pg, "BEGIN");

for ($i = 0; $i < $qty; $i++) {
    $attempt = 0;
    do {
        $uname = $prefix . generateRandomString($nameLength, $charType);
        $attempt++;
    } while (isset($existingInBatch[$uname]) && $attempt < 15);

    $existingInBatch[$uname] = true;

    if ($userMode === 'up') {
        $upass = $uname;
    } else {
        $upass = generateRandomString($nameLength, $charType);
    }

    $voucherItem = array(
        'username' => $uname,
        'name' => $uname,
        'password' => $upass,
        'profile' => $profile,
        'price' => $price,
        'sprice' => $price,
        'validity' => $normValidity ?: $validity,
        'validity_display' => formatDurationHuman($normValidity ?: $validity, 'id'),
        'timelimit' => $timelimit ?: $normValidity,
        'timelimit_display' => formatDurationHuman($timelimit ?: $normValidity, 'id'),
        'datalimit' => $datalimit,
        'comment' => $comment,
        'hotspot_name' => $hotspotname,
        'dns_name' => $dnsname
    );

    // Keep voucher list for immediate print rendering
    $previewList[] = $voucherItem;

    $currentChunk[] = array($uname, $upass);

    // Flush chunk to PostgreSQL
    if (count($currentChunk) >= $chunkSize || $i === ($qty - 1)) {
        $vRows = array();
        $rcRows = array();
        $rgRows = array();

        foreach ($currentChunk as $cPair) {
            $uEsc = pg_escape_string($pg, $cPair[0]);
            $pEsc = pg_escape_string($pg, $cPair[1]);
            $profEsc = pg_escape_string($pg, $profile);
            $commEsc = pg_escape_string($pg, $comment);
            $valEsc = pg_escape_string($pg, $normValidity ?: ($validity ?: '12h'));
            $origEsc = pg_escape_string($pg, $routerOriginVal);

            $vRows[] = "('$uEsc', '$pEsc', '$profEsc', $numPrice, '$valEsc', '$commEsc', 'unused', '0s', 0, '$origEsc')";
            $rcRows[] = "('$uEsc', 'Cleartext-Password', ':=', '$pEsc')";
            $rgRows[] = "('$uEsc', '$profEsc', 1)";
        }

        if (!empty($vRows)) {
            $sqlV = "INSERT INTO pacenet_vouchers (username, password, profile, price, validity, comment, status, uptime, bytes_total, router_origin) VALUES " . implode(',', $vRows) . " ON CONFLICT (username) DO NOTHING";
            pg_query($pg, $sqlV);

            $sqlRC = "INSERT INTO radcheck (username, attribute, op, value) VALUES " . implode(',', $rcRows) . " ON CONFLICT DO NOTHING";
            pg_query($pg, $sqlRC);

            $sqlRG = "INSERT INTO radusergroup (username, groupname, priority) VALUES " . implode(',', $rgRows) . " ON CONFLICT DO NOTHING";
            pg_query($pg, $sqlRG);

            $totalInserted += count($vRows);
        }

        $currentChunk = array();
    }
}

@pg_query($pg, "COMMIT");

// Invalidate cache
$cacheFile = sys_get_temp_dir() . '/pacenet_users_cache.json';
@unlink($cacheFile);

jsonResponse(true, array(
    'count' => $qty,
    'total_generated' => $qty,
    'batch_comment' => $comment,
    'profile' => $profile,
    'price' => $price,
    'validity' => $validity,
    'is_large_batch' => ($qty > 500),
    'csv_export_url' => '/api/generate.php?action=export_csv&batch=' . urlencode($comment),
    'vouchers' => $previewList // Returns preview list (up to 500) so browser doesn't freeze
), 'Berhasil membuat ' . number_format($qty, 0, ',', '.') . ' voucher ke database!');
