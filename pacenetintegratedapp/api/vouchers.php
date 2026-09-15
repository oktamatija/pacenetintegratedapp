<?php
/**
 * Pacenet REST API - Centralized Voucher & User Management (Multi-Router Synchronized)
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
    global $data, $cacheFile, $cacheTtl;

    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $cached = @file_get_contents($cacheFile);
        if ($cached) {
            $parsed = json_decode($cached, true);
            if (is_array($parsed) && !empty($parsed['users'])) return $parsed;
        }
    }

    $allUsers = array();
    $allProfiles = array();
    $profileSeen = array();
    $connectedRouters = array();

    foreach ($data as $sName => $sCfg) {
        if ($sName === 'mikhmon' || empty($sName) || strpos($sName, 'new-') === 0 || empty($sCfg[1])) {
            continue;
        }

        $rName = explode('%', $sCfg[4] ?? '')[1] ?? $sName;
        $conn = connectMikrotik($sName, 4);
        if (!$conn) continue;

        $api = $conn['api'];
        $rawUsers = $api->comm('/ip/hotspot/user/print');
        $rawProfiles = $api->comm('/ip/hotspot/user/profile/print');
        $api->disconnect();

        $connectedRouters[] = array(
            'session' => $sName,
            'name' => $rName,
            'user_count' => is_array($rawUsers) ? count($rawUsers) : 0
        );

        if (is_array($rawProfiles)) {
            foreach ($rawProfiles as $p) {
                $pName = $p['name'] ?? '';
                if (empty($pName) || isset($profileSeen[$pName])) continue;
                $profileSeen[$pName] = true;
                $allProfiles[] = array(
                    'name' => $pName,
                    'shared_users' => $p['shared-users'] ?? '1',
                    'rate_limit' => $p['rate-limit'] ?? '-',
                    'on_login' => $p['on-login'] ?? ''
                );
            }
        }

        if (is_array($rawUsers)) {
            foreach ($rawUsers as $u) {
                $uname = $u['name'] ?? '';
                if ($uname === 'default-encryption' || empty($uname)) continue;

                $bIn = intval($u['bytes-in'] ?? 0);
                $bOut = intval($u['bytes-out'] ?? 0);
                $bTotal = $bIn + $bOut;

                $allUsers[] = array(
                    'id' => $u['.id'] ?? '',
                    'router_session' => $sName,
                    'router_name' => $rName,
                    'name' => $uname,
                    'password' => $u['password'] ?? '',
                    'profile' => $u['profile'] ?? 'default',
                    'uptime' => $u['uptime'] ?? '0s',
                    'bytes_in' => $bIn,
                    'bytes_out' => $bOut,
                    'bytes_total' => $bTotal,
                    'bytes_human' => formatBytesReadable($bTotal),
                    'limit_uptime' => $u['limit-uptime'] ?? '',
                    'limit_bytes_total' => $u['limit-bytes-total'] ?? '',
                    'disabled' => ($u['disabled'] ?? 'false') === 'true',
                    'comment' => $u['comment'] ?? '',
                    'server' => $u['server'] ?? 'all'
                );
            }
        }
    }

    if (empty($connectedRouters) && empty($allUsers)) {
        return null;
    }

    $payload = array(
        'timestamp' => time(),
        'routers' => $connectedRouters,
        'profiles' => $allProfiles,
        'users' => $allUsers
    );

    @file_put_contents($cacheFile, json_encode($payload));
    return $payload;
}

function getRouterForUserAction($body) {
    global $cacheFile, $data;
    $targetRouter = $body['router'] ?? ($body['router_session'] ?? ($_POST['router'] ?? ''));
    $id = $body['id'] ?? ($_POST['id'] ?? '');
    $name = $body['name'] ?? ($_POST['name'] ?? '');

    if (!empty($targetRouter) && isset($data[$targetRouter])) {
        return $targetRouter;
    }

    // Lookup router from cache file
    if (file_exists($cacheFile)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if (!empty($cached['users'])) {
            foreach ($cached['users'] as $u) {
                if ((!empty($id) && ($u['id'] ?? '') === $id) || (!empty($name) && ($u['name'] ?? '') === $name)) {
                    if (!empty($u['router_session'])) {
                        return $u['router_session'];
                    }
                }
            }
        }
    }

    // Default fallback
    return 'Rumah-DOLPHIN';
}

// 1. LIST USERS / PROFILES
if ($action === 'list') {
    $refresh = isset($_GET['refresh']) && ($_GET['refresh'] === '1' || $_GET['refresh'] === 'true');
    $search = strtolower(trim($_GET['search'] ?? ''));
    $profileFilter = trim($_GET['profile'] ?? 'all');
    $routerFilter = trim($_GET['router'] ?? 'all');
    $page = max(intval($_GET['page'] ?? 1), 1);
    $limit = max(intval($_GET['limit'] ?? 25), 5);

    $cached = fetchMasterUsers($refresh);
    if (!$cached) {
        jsonResponse(false, null, 'Gagal terhubung ke router MikroTik', 500);
    }

    $allUsers = $cached['users'];
    $profiles = $cached['profiles'];
    $routers = $cached['routers'] ?? array();

    // Filter
    $filtered = array();
    foreach ($allUsers as $u) {
        if ($routerFilter !== 'all' && $u['router_session'] !== $routerFilter) {
            continue;
        }
        if ($profileFilter !== 'all' && $u['profile'] !== $profileFilter) {
            continue;
        }
        if (!empty($search)) {
            $matchName = strpos(strtolower($u['name']), $search) !== false;
            $matchComment = strpos(strtolower($u['comment']), $search) !== false;
            $matchRouter = strpos(strtolower($u['router_name'] . ' ' . $u['router_session']), $search) !== false;
            if (!$matchName && !$matchComment && !$matchRouter) {
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
        'routers' => $routers,
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

    $targetRouter = getRouterForUserAction($body);
    $conn = connectMikrotik($targetRouter, 4);
    if (!$conn) jsonResponse(false, null, "Koneksi RouterOS [{$targetRouter}] gagal", 500);

    $conn['api']->comm('/ip/hotspot/user/set', array(
        '.id' => $id,
        'disabled' => $disabled
    ));
    $conn['api']->disconnect();

    @unlink($cacheFile);
    jsonResponse(true, array('id' => $id, 'router' => $targetRouter, 'disabled' => $disabled === 'yes'), 'Status voucher berhasil diperbarui');
}

// 3. DELETE USER
if ($action === 'delete') {
    $id = $body['id'] ?? $_POST['id'] ?? '';
    if (empty($id)) {
        jsonResponse(false, null, 'ID user tidak ditemukan', 400);
    }

    $targetRouter = getRouterForUserAction($body);
    $conn = connectMikrotik($targetRouter, 4);
    if (!$conn) jsonResponse(false, null, "Koneksi RouterOS [{$targetRouter}] gagal", 500);

    $conn['api']->comm('/ip/hotspot/user/remove', array(
        '.id' => $id
    ));
    $conn['api']->disconnect();

    @unlink($cacheFile);
    jsonResponse(true, array('id' => $id, 'router' => $targetRouter), 'Voucher berhasil dihapus');
}

// 4. RESET COUNTERS
if ($action === 'reset_counters') {
    $id = $body['id'] ?? $_POST['id'] ?? '';
    if (empty($id)) {
        jsonResponse(false, null, 'ID user tidak ditemukan', 400);
    }

    $targetRouter = getRouterForUserAction($body);
    $conn = connectMikrotik($targetRouter, 4);
    if (!$conn) jsonResponse(false, null, "Koneksi RouterOS [{$targetRouter}] gagal", 500);

    $conn['api']->comm('/ip/hotspot/user/reset-counters', array(
        '.id' => $id
    ));
    $conn['api']->disconnect();

    @unlink($cacheFile);
    jsonResponse(true, array('id' => $id, 'router' => $targetRouter), 'Counter voucher berhasil direset');
}

jsonResponse(false, null, 'Invalid action', 400);
