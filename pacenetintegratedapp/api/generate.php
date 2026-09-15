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
    $targetRouters[] = 'Rumah-DOLPHIN';
}

// 1. Get profile details from first connectable router
$price = '';
$validity = '';
$primarySession = $targetRouters[0] ?? 'Rumah-DOLPHIN';

foreach ($targetRouters as $sName) {
    $conn = connectMikrotik($sName, 5);
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

// Normalize validity if present
$normValidity = normalizeMikrotikDuration($validity, $validity);

// Generate voucher codes
$createdList = array();
$existingUsers = array();

$dnsname = explode('^', $data[$primarySession][5] ?? '')[1] ?? 'hotspot.yunus';
$hotspotname = explode('%', $data[$primarySession][4] ?? '')[1] ?? 'PACENET HOTSPOT';

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

// Store vouchers in Centralized Pacenet Database (PostgreSQL / FreeRADIUS) - Single Source of Truth!
// No import to MikroTik /ip/hotspot/user! Users authenticate dynamically via RADIUS.
$pg = getPgDb();
if ($pg) {
    @pg_query($pg, "BEGIN");
    @pg_prepare($pg, "gen_voucher", "
        INSERT INTO pacenet_vouchers (username, password, profile, price, validity, comment, status, uptime, bytes_total, router_origin)
        VALUES ($1, $2, $3, $4, $5, $6, 'unused', '0s', 0, $7)
        ON CONFLICT (username) DO NOTHING
    ");
    @pg_prepare($pg, "gen_radcheck", "
        INSERT INTO radcheck (username, attribute, op, value)
        VALUES ($1, 'Cleartext-Password', ':=', $2)
        ON CONFLICT DO NOTHING
    ");
    @pg_prepare($pg, "gen_radgroup", "
        INSERT INTO radusergroup (username, groupname, priority)
        VALUES ($1, $2, 1)
        ON CONFLICT DO NOTHING
    ");

    $numPrice = floatval(preg_replace('/[^0-9.]/', '', strval($price))) ?: 0;
    foreach ($createdList as $cu) {
        @pg_execute($pg, "gen_voucher", array(
            $cu['name'],
            $cu['password'],
            $profile,
            $numPrice,
            $normValidity ?: ($validity ?: '12h'),
            $comment,
            $targetRouter === 'all' ? 'Semua Router (Central)' : $targetRouter
        ));
        @pg_execute($pg, "gen_radcheck", array($cu['name'], $cu['password']));
        @pg_execute($pg, "gen_radgroup", array($cu['name'], $profile));
    }
    @pg_query($pg, "COMMIT");
} else {
    jsonResponse(false, null, 'Gagal terhubung ke database terpusat Pacenet (PostgreSQL)', 500);
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
