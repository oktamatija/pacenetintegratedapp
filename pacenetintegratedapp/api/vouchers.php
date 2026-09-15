<?php
/**
 * Pacenet REST API - Centralized Voucher & User Management
 * Single Source of Truth: PostgreSQL pacenet_vouchers & FreeRADIUS
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');
$body = !empty($rawBody) ? json_decode($rawBody, true) : array();
$action = $_GET['action'] ?? ($body['action'] ?? 'list');

$pg = getPgDb();
if (!$pg) {
    jsonResponse(false, null, 'Gagal terhubung ke database PostgreSQL Pacenet', 500);
}

// 1. LIST VOUCHERS
if ($action === 'list') {
    $search = strtolower(trim($_GET['search'] ?? ''));
    $profileFilter = trim($_GET['profile'] ?? 'all');
    $statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
    $page = max(intval($_GET['page'] ?? 1), 1);
    $limit = max(intval($_GET['limit'] ?? 25), 5);

    // Fetch real-time active users from MikroTik routers (fast: ~300 active users total)
    $activeSessions = array();
    $connectedRouters = array();
    foreach ($data as $sName => $sCfg) {
        if ($sName === 'mikhmon' || empty($sName) || strpos($sName, 'new-') === 0 || empty($sCfg[1])) {
            continue;
        }
        $rName = explode('%', $sCfg[4] ?? '')[1] ?? $sName;
        $conn = connectMikrotik($sName, 3);
        if ($conn) {
            $act = $conn['api']->comm('/ip/hotspot/active/print');
            $conn['api']->disconnect();
            if (is_array($act)) {
                foreach ($act as $a) {
                    $u = $a['user'] ?? '';
                    if (!empty($u)) {
                        $activeSessions[$u] = array(
                            'router_session' => $sName,
                            'router_name' => $rName,
                            'uptime' => $a['uptime'] ?? '0s',
                            'bytes_in' => intval($a['bytes-in'] ?? 0),
                            'bytes_out' => intval($a['bytes-out'] ?? 0),
                            'ip' => $a['address'] ?? '-',
                            'mac' => $a['mac-address'] ?? '-'
                        );
                    }
                }
            }
            $connectedRouters[] = array(
                'session' => $sName,
                'name' => $rName,
                'active_count' => is_array($act) ? count($act) : 0
            );
        }
    }

    // Build SQL query
    $whereParts = array();
    $params = array();
    $pIdx = 1;

    if ($profileFilter !== 'all' && !empty($profileFilter)) {
        $whereParts[] = "profile = $" . $pIdx++;
        $params[] = $profileFilter;
    }

    if ($statusFilter !== 'all' && !empty($statusFilter)) {
        $whereParts[] = "status = $" . $pIdx++;
        $params[] = $statusFilter;
    }

    if (!empty($search)) {
        $whereParts[] = "(LOWER(username) LIKE $" . $pIdx . " OR LOWER(comment) LIKE $" . $pIdx . ")";
        $params[] = '%' . $search . '%';
        $pIdx++;
    }

    $whereClause = !empty($whereParts) ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

    // Total counts
    $countSql = "SELECT count(*) as total FROM pacenet_vouchers $whereClause";
    $countRes = pg_query_params($pg, $countSql, $params);
    $total = $countRes ? intval(pg_fetch_result($countRes, 0, 'total')) : 0;

    $totalAllSql = "SELECT count(*) as total_all FROM pacenet_vouchers";
    $totalAllRes = pg_query($pg, $totalAllSql);
    $totalAll = $totalAllRes ? intval(pg_fetch_result($totalAllRes, 0, 'total_all')) : 0;

    // Fetch distinct profiles
    $profSql = "SELECT DISTINCT profile FROM pacenet_vouchers WHERE profile IS NOT NULL AND profile != '' ORDER BY profile";
    $profRes = pg_query($pg, $profSql);
    $profiles = array();
    if ($profRes) {
        while ($pRow = pg_fetch_assoc($profRes)) {
            $profiles[] = array(
                'name' => $pRow['profile'],
                'shared_users' => '1',
                'rate_limit' => ($pRow['profile'] === '12-jam') ? '3M/5M' : '-'
            );
        }
    }

    // Fetch paginated vouchers
    $offset = ($page - 1) * $limit;
    $vSql = "SELECT * FROM pacenet_vouchers $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset";
    $vRes = pg_query_params($pg, $vSql, $params);

    $users = array();
    if ($vRes) {
        while ($v = pg_fetch_assoc($vRes)) {
            $uname = $v['username'];
            $isActive = isset($activeSessions[$uname]);
            $liveAct = $isActive ? $activeSessions[$uname] : null;

            $uptime = $liveAct ? $liveAct['uptime'] : ($v['uptime'] ?: '0s');
            $bIn = $liveAct ? $liveAct['bytes_in'] : intval($v['bytes_in'] ?? 0);
            $bOut = $liveAct ? $liveAct['bytes_out'] : intval($v['bytes_out'] ?? 0);
            $bTotal = $bIn + $bOut;

            $status = $v['status'];
            if ($isActive) {
                $status = 'active';
            }

            $users[] = array(
                'id' => strval($v['id']),
                'router_session' => $liveAct ? $liveAct['router_session'] : ($v['router_origin'] ?: 'Pacenet Cloud'),
                'router_name' => $liveAct ? $liveAct['router_name'] : ($v['router_origin'] ?: 'Pacenet Cloud'),
                'name' => $uname,
                'password' => $v['password'],
                'profile' => $v['profile'],
                'price' => floatval($v['price']),
                'price_formatted' => 'Rp ' . number_format(floatval($v['price']), 0, ',', '.'),
                'status' => $status,
                'uptime' => $uptime,
                'bytes_in' => $bIn,
                'bytes_out' => $bOut,
                'bytes_total' => $bTotal,
                'bytes_human' => formatBytesReadable($bTotal),
                'limit_uptime' => $v['validity'] ?: '12h',
                'limit_bytes_total' => '',
                'disabled' => ($status === 'disabled'),
                'comment' => $v['comment'] ?: '-',
                'server' => 'all',
                'created_at' => $v['created_at'],
                'ip' => $liveAct ? $liveAct['ip'] : '-',
                'mac' => $liveAct ? $liveAct['mac'] : '-'
            );
        }
    }

    $totalPages = max(ceil($total / $limit), 1);

    jsonResponse(true, array(
        'total' => $total,
        'total_all' => $totalAll,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'routers' => $connectedRouters,
        'profiles' => $profiles,
        'users' => $users
    ));
}

// Guard all mutating operations against read-only/demo users
checkWritePermission();

// 2. TOGGLE ENABLE / DISABLE
if ($action === 'toggle') {
    $id = $body['id'] ?? $_POST['id'] ?? '';
    $name = $body['name'] ?? $_POST['name'] ?? '';
    $disabled = ($body['disabled'] ?? $_POST['disabled'] ?? false);

    if (empty($id) && empty($name)) {
        jsonResponse(false, null, 'ID atau username voucher tidak ditemukan', 400);
    }

    // Get username
    if (empty($name)) {
        $nRes = pg_query_params($pg, "SELECT username, password, profile FROM pacenet_vouchers WHERE id = $1", array($id));
        $vData = pg_fetch_assoc($nRes);
        $name = $vData['username'] ?? '';
    } else {
        $nRes = pg_query_params($pg, "SELECT username, password, profile FROM pacenet_vouchers WHERE username = $1", array($name));
        $vData = pg_fetch_assoc($nRes);
    }

    if (empty($name)) {
        jsonResponse(false, null, 'Voucher tidak ditemukan di database', 404);
    }

    $newStatus = $disabled ? 'disabled' : 'unused';
    pg_query_params($pg, "UPDATE pacenet_vouchers SET status = $1 WHERE username = $2", array($newStatus, $name));

    if ($disabled) {
        // Remove from radcheck to disable authentication
        pg_query_params($pg, "DELETE FROM radcheck WHERE username = $1", array($name));

        // Disconnect if currently active on any router
        foreach ($data as $sName => $sCfg) {
            if ($sName === 'mikhmon' || empty($sName) || strpos($sName, 'new-') === 0 || empty($sCfg[1])) continue;
            $conn = connectMikrotik($sName, 2);
            if ($conn) {
                $act = $conn['api']->comm('/ip/hotspot/active/print', array('?user' => $name));
                if (!empty($act[0]['.id'])) {
                    $conn['api']->comm('/ip/hotspot/active/remove', array('.id' => $act[0]['.id']));
                }
                $conn['api']->disconnect();
            }
        }
    } else {
        // Re-enable in radcheck
        $pass = $vData['password'] ?? $name;
        pg_query_params($pg, "INSERT INTO radcheck (username, attribute, op, value) VALUES ($1, 'Cleartext-Password', ':=', $2) ON CONFLICT (username) DO UPDATE SET attribute = EXCLUDED.attribute, value = EXCLUDED.value", array($name, $pass));
    }

    jsonResponse(true, array('username' => $name, 'disabled' => $disabled), 'Status voucher berhasil diperbarui di Pacenet');
}

// 3. DELETE VOUCHER
if ($action === 'delete') {
    $id = $body['id'] ?? $_POST['id'] ?? '';
    $name = $body['name'] ?? $_POST['name'] ?? '';

    if (empty($id) && empty($name)) {
        jsonResponse(false, null, 'ID atau username voucher tidak ditemukan', 400);
    }

    if (empty($name)) {
        $nRes = pg_query_params($pg, "SELECT username FROM pacenet_vouchers WHERE id = $1", array($id));
        $name = $nRes ? pg_fetch_result($nRes, 0, 'username') : '';
    }

    if (!empty($name)) {
        pg_query_params($pg, "DELETE FROM pacenet_vouchers WHERE username = $1", array($name));
        pg_query_params($pg, "DELETE FROM radcheck WHERE username = $1", array($name));
        pg_query_params($pg, "DELETE FROM radusergroup WHERE username = $1", array($name));

        // Disconnect active session if online
        foreach ($data as $sName => $sCfg) {
            if ($sName === 'mikhmon' || empty($sName) || strpos($sName, 'new-') === 0 || empty($sCfg[1])) continue;
            $conn = connectMikrotik($sName, 2);
            if ($conn) {
                $act = $conn['api']->comm('/ip/hotspot/active/print', array('?user' => $name));
                if (!empty($act[0]['.id'])) {
                    $conn['api']->comm('/ip/hotspot/active/remove', array('.id' => $act[0]['.id']));
                }
                $conn['api']->disconnect();
            }
        }
    }

    jsonResponse(true, array('username' => $name), 'Voucher berhasil dihapus dari sistem Pacenet');
}

// 4. RESET COUNTERS
if ($action === 'reset_counters') {
    $id = $body['id'] ?? $_POST['id'] ?? '';
    $name = $body['name'] ?? $_POST['name'] ?? '';

    if (!empty($name)) {
        pg_query_params($pg, "UPDATE pacenet_vouchers SET uptime = '0s', bytes_in = 0, bytes_out = 0, bytes_total = 0, status = 'unused' WHERE username = $1", array($name));
        pg_query_params($pg, "DELETE FROM radacct WHERE username = $1", array($name));
    } elseif (!empty($id)) {
        pg_query_params($pg, "UPDATE pacenet_vouchers SET uptime = '0s', bytes_in = 0, bytes_out = 0, bytes_total = 0, status = 'unused' WHERE id = $1", array($id));
    }

    jsonResponse(true, array('id' => $id, 'username' => $name), 'Counter voucher berhasil direset');
}

// 5. CLEANUP MIKROTIK LOCAL USERS (Safely removes offline voucher users from MikroTik since Pacenet is Single Source of Truth)
if ($action === 'cleanup_mikrotik_local_users') {
    $results = array();
    foreach ($data as $sName => $sCfg) {
        if ($sName === 'mikhmon' || empty($sName) || strpos($sName, 'new-') === 0 || empty($sCfg[1])) continue;
        $conn = connectMikrotik($sName, 10);
        if (!$conn) continue;

        $api = $conn['api'];
        $before = $api->comm('/ip/hotspot/user/print');
        $countBefore = is_array($before) ? count($before) : 0;

        $scrName = 'pacenet_fast_purge';
        $old = $api->comm('/system/script/print', array('?name' => $scrName));
        if (!empty($old)) {
            foreach ($old as $o) {
                if (!empty($o['.id'])) $api->comm('/system/script/remove', array('.id' => $o['.id']));
            }
        }

        $api->comm('/system/script/add', array(
            'name' => $scrName,
            'source' => '/ip hotspot user remove [find name!="default-trial"]'
        ));
        $api->comm('/system/script/run', array('.id' => $scrName));
        sleep(2);
        $api->comm('/system/script/remove', array('.id' => $scrName));

        $after = $api->comm('/ip/hotspot/user/print');
        $countAfter = is_array($after) ? count($after) : 0;
        $api->disconnect();

        $results[$sName] = array(
            'before' => $countBefore,
            'after' => $countAfter,
            'purged' => max(0, $countBefore - $countAfter)
        );
    }
    jsonResponse(true, $results, 'Pembersihan user lokal MikroTik selesai. MikroTik kini bersih dan mengandalkan RADIUS Pacenet.');
}

jsonResponse(false, null, 'Invalid action', 400);
