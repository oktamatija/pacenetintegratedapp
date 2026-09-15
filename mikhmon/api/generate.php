<?php
/**
 * Pacenet REST API - Batch Voucher Generator & Thermal Print Engine
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
checkWritePermission();
session_write_close();

set_time_limit(300);

$rawBody = file_get_contents('php://input');
$body = !empty($rawBody) ? json_decode($rawBody, true) : $_POST;

$qty = max(min(intval($body['qty'] ?? 10), 500), 1);
$server = $body['server'] ?? 'all';
$userMode = $body['user_mode'] ?? 'up'; // 'up' = user=pass, 'vc' = user!=pass
$nameLength = max(min(intval($body['name_length'] ?? 4), 10), 3);
$prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $body['prefix'] ?? '');
$charType = $body['char_type'] ?? 'mix'; // 'lower', 'upper', 'num', 'mix'
$profile = $body['profile'] ?? 'default';
$timelimitRaw = trim($body['timelimit'] ?? '');
$datalimit = trim($body['datalimit'] ?? '');
$customComment = trim($body['comment'] ?? ('pn-' . date('ymd-His')));

// Parse bilingual timelimit
$parsedTime = parseBilingualDuration($timelimitRaw);
$timelimit = $parsedTime['valid'] ? $parsedTime['mikrotik'] : '';

// Ensure comment starts with vc- or up- so MikroTik on-login script recognizes it and triggers expiration scheduler
$prefixUcode = ($userMode === 'vc' ? 'vc-' : 'up-');
if (strpos($customComment, 'vc-') !== 0 && strpos($customComment, 'up-') !== 0) {
    $comment = $prefixUcode . $customComment;
} else {
    $comment = $customComment;
}

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

$conn = connectMikrotik('Rumah-DOLPHIN', 10);
if (!$conn) {
    jsonResponse(false, null, 'Gagal terhubung ke master MikroTik (Rumah-DOLPHIN)', 500);
}

$api = $conn['api'];

// Get profile details (price, validity)
$price = '';
$validity = '';
$profDetails = $api->comm('/ip/hotspot/user/profile/print', array('?name' => $profile));
if (!empty($profDetails[0])) {
    $onLogin = $profDetails[0]['on-login'] ?? '';
    if (!empty($onLogin)) {
        $parts = explode(',', $onLogin);
        if (isset($parts[2])) $price = trim($parts[2]);
        if (isset($parts[3])) $validity = trim($parts[3]);
    }
}

// Normalize validity if present
$normValidity = normalizeMikrotikDuration($validity, $validity);

$createdList = array();
$existingUsers = array();

// Quick check existing
$currUsers = $api->comm('/ip/hotspot/user/print', array('.proplist' => 'name'));
if (is_array($currUsers)) {
    foreach ($currUsers as $cu) {
        if (!empty($cu['name'])) $existingUsers[$cu['name']] = true;
    }
}

for ($i = 0; $i < $qty; $i++) {
    $attempt = 0;
    do {
        $uname = $prefix . generateRandomString($nameLength, $charType);
        $attempt++;
    } while (isset($existingUsers[$uname]) && $attempt < 20);

    $existingUsers[$uname] = true;

    if ($userMode === 'up') {
        $upass = $uname;
    } else {
        $upass = generateRandomString($nameLength, $charType);
    }

    $addParams = array(
        'server' => $server,
        'name' => $uname,
        'password' => $upass,
        'profile' => $profile,
        'comment' => $comment
    );

    // Apply normalized limit-uptime
    if (!empty($timelimit)) {
        $addParams['limit-uptime'] = $timelimit;
    } elseif (!empty($normValidity)) {
        $addParams['limit-uptime'] = $normValidity;
    }

    if (!empty($datalimit)) {
        $addParams['limit-bytes-total'] = $datalimit;
    }

    $api->comm('/ip/hotspot/user/add', $addParams);

    $dnsname = explode('^', $data['Rumah-DOLPHIN'][5] ?? '')[1] ?? 'hotspot.yunus';
    $hotspotname = explode('%', $data['Rumah-DOLPHIN'][4] ?? '')[1] ?? 'PACENET HAMADI';

    $createdList[] = array(
        'username' => $uname,
        'name' => $uname,
        'password' => $upass,
        'profile' => $profile,
        'price' => $price,
        'validity' => $normValidity ?: $validity,
        'validity_display' => formatDurationHuman($normValidity ?: $validity, 'id'),
        'timelimit' => $timelimit ?: $normValidity,
        'timelimit_display' => formatDurationHuman($timelimit ?: $normValidity, 'id'),
        'datalimit' => $datalimit,
        'comment' => $comment,
        'hotspot_name' => $hotspotname,
        'dns_name' => $dnsname
    );
}

$api->disconnect();

// Synchronize newly generated vouchers to PostgreSQL FreeRADIUS database
if (function_exists('pg_connect')) {
    $pg = @pg_connect("host=127.0.0.1 port=5432 dbname=radius user=radius password=RadiusPg2026");
    if ($pg) {
        @pg_query($pg, "BEGIN");
        @pg_prepare($pg, "gen_radcheck", "INSERT INTO radcheck (username, attribute, op, value) VALUES ($1, 'Cleartext-Password', ':=', $2) ON CONFLICT DO NOTHING");
        @pg_prepare($pg, "gen_radgroup", "INSERT INTO radusergroup (username, groupname, priority) VALUES ($1, $2, 1) ON CONFLICT DO NOTHING");
        foreach ($createdList as $cu) {
            @pg_execute($pg, "gen_radcheck", array($cu['name'], $cu['password']));
            @pg_execute($pg, "gen_radgroup", array($cu['name'], $profile));
        }
        @pg_query($pg, "COMMIT");
        @pg_close($pg);
    }
}


// Invalidate cache
$cacheFile = sys_get_temp_dir() . '/pacenet_users_cache.json';
@unlink($cacheFile);

jsonResponse(true, array(
    'count' => count($createdList),
    'batch_comment' => $comment,
    'profile' => $profile,
    'price' => $price,
    'validity' => $validity,
    'vouchers' => $createdList
), 'Berhasil membuat ' . count($createdList) . ' voucher');
