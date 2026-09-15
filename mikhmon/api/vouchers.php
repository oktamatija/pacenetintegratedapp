<?php
/**
 * Pacenet REST API - Centralized Voucher & User Management
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');
$body = !empty($rawBody) ? json_decode($rawBody, true) : array();
$action = $_GET['action'] ?? ($body['action'] ?? 'list');

$cacheFile = sys_get_temp_dir() . '/pacenet_users_cache.json';
$cacheTtl = 20; // 20 seconds cache for instant search/pagination

function fetchMasterUsers($forceRefresh = false) {
    global $cacheFile, $cacheTtl;

    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached) {
            $data = json_decode($cached, true);
            if (is_array($data)) return $data;
        }
    }

    $conn = connectMikrotik('Rumah-DOLPHIN', 5);
    if (!$conn) return null;

    $api = $conn['api'];
    $rawUsers = $api->comm('/ip/hotspot/user/print');
    $rawProfiles = $api->comm('/ip/hotspot/user/profile/print');
    $api->disconnect();

    $profiles = array();
    if (is_array($rawProfiles)) {
        foreach ($rawProfiles as $p) {
            $profiles[] = array(
                'name' => $p['name'] ?? '',
                'shared_users' => $p['shared-users'] ?? '1',
                'rate_limit' => $p['rate-limit'] ?? '-',
                'on_login' => $p['on-login'] ?? ''
            );
        }
    }

    $users = array();
    if (is_array($rawUsers)) {
        foreach ($rawUsers as $u) {
            $users[] = array(
                'id' => $u['.id'] ?? '',
                'name' => $u['name'] ?? '',
                'password' => $u['password'] ?? '',
                'profile' => $u['profile'] ?? 'default',
                'uptime' => $u['uptime'] ?? '0s',
                'bytes_in' => intval($u['bytes-in'] ?? 0),
                'bytes_out' => intval($u['bytes-out'] ?? 0),
                'bytes_total' => intval($u['bytes-in'] ?? 0) + intval($u['bytes-out'] ?? 0),
                'bytes_human' => formatBytesReadable(intval($u['bytes-in'] ?? 0) + intval($u['bytes-out'] ?? 0)),
                'limit_uptime' => $u['limit-uptime'] ?? '',
                'limit_bytes_total' => $u['limit-bytes-total'] ?? '',
                'disabled' => ($u['disabled'] ?? 'false') === 'true',
                'comment' => $u['comment'] ?? '',
                'server' => $u['server'] ?? 'all'
            );
        }
    }

    $payload = array(
        'timestamp' => time(),
        'profiles' => $profiles,
        'users' => $users
    );

    @file_put_contents($cacheFile, json_encode($payload));
    return $payload;
}

// 1. LIST USERS / PROFILES
if ($action === 'list') {
    $refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
    $search = strtolower(trim($_GET['search'] ?? ''));
    $profileFilter = trim($_GET['profile'] ?? 'all');
    $page = max(intval($_GET['page'] ?? 1), 1);
    $limit = max(intval($_GET['limit'] ?? 25), 5);

    $cached = fetchMasterUsers($refresh);
    if (!$cached) {
        jsonResponse(false, null, 'Gagal terhubung ke master MikroTik (Rumah-DOLPHIN)', 500);
    }

    $allUsers = $cached['users'];
    $profiles = $cached['profiles'];

    // Filter
    $filtered = array();
    foreach ($allUsers as $u) {
        if ($profileFilter !== 'all' && $u['profile'] !== $profileFilter) {
            continue;
        }
        if (!empty($search)) {
            $matchName = strpos(strtolower($u['name']), $search) !== false;
            $matchComment = strpos(strtolower($u['comment']), $search) !== false;
            if (!$matchName && !$matchComment) {
                continue;
            }
        }
        $filtered[] = $u;
    }

    $total = count($filtered);
    $totalPages = ceil($total / $limit);
    $offset = ($page - 1) * $limit;
    $sliced = array_slice($filtered, $offset, $limit);

    jsonResponse(true, array(
        'total' => $total,
        'total_all' => count($allUsers),
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'profiles' => $profiles,
        'users' => $sliced
    ));
}

// Guard all mutating operations against read-only/demo users
checkWritePermission();

// 2. TOGGLE ENABLE / DISABLE
if ($action === 'toggle') {
    $id = $body['id'] ?? $_POST['id'] ?? '';
    $disabled = ($body['disabled'] ?? $_POST['disabled'] ?? false) ? 'yes' : 'no';

    if (empty($id)) {
        jsonResponse(false, null, 'ID user tidak ditemukan', 400);
    }

    $conn = connectMikrotik('Rumah-DOLPHIN', 4);
    if (!$conn) jsonResponse(false, null, 'Koneksi RouterOS gagal', 500);

    $conn['api']->comm('/ip/hotspot/user/set', array(
        '.id' => $id,
        'disabled' => $disabled
    ));
    $conn['api']->disconnect();

    @unlink($cacheFile);
    jsonResponse(true, array('id' => $id, 'disabled' => $disabled === 'yes'), 'Status voucher berhasil diperbarui');
}

// 3. DELETE USER
if ($action === 'delete') {
    $id = $body['id'] ?? $_POST['id'] ?? '';
    if (empty($id)) {
        jsonResponse(false, null, 'ID user tidak ditemukan', 400);
    }

    $conn = connectMikrotik('Rumah-DOLPHIN', 4);
    if (!$conn) jsonResponse(false, null, 'Koneksi RouterOS gagal', 500);

    $conn['api']->comm('/ip/hotspot/user/remove', array(
        '.id' => $id
    ));
    $conn['api']->disconnect();

    @unlink($cacheFile);
    jsonResponse(true, array('id' => $id), 'Voucher berhasil dihapus');
}

// 4. RESET COUNTERS
if ($action === 'reset_counters') {
    $id = $body['id'] ?? $_POST['id'] ?? '';
    if (empty($id)) {
        jsonResponse(false, null, 'ID user tidak ditemukan', 400);
    }

    $conn = connectMikrotik('Rumah-DOLPHIN', 4);
    if (!$conn) jsonResponse(false, null, 'Koneksi RouterOS gagal', 500);

    $conn['api']->comm('/ip/hotspot/user/reset-counters', array(
        '.id' => $id
    ));
    $conn['api']->disconnect();

    @unlink($cacheFile);
    jsonResponse(true, array('id' => $id), 'Counter voucher berhasil direset');
}

jsonResponse(false, null, 'Invalid action', 400);
