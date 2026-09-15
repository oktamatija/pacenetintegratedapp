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

// 6. ADD SINGLE VOUCHER
if ($action === 'add') {
    $uname = trim($body['username'] ?? $body['name'] ?? $_POST['username'] ?? '');
    $pass = trim($body['password'] ?? $_POST['password'] ?? '');
    if (empty($pass)) $pass = $uname;
    $profile = trim($body['profile'] ?? $_POST['profile'] ?? '12-jam');
    $price = floatval($body['price'] ?? $_POST['price'] ?? 0);
    $validity = trim($body['validity'] ?? $_POST['validity'] ?? '');
    $comment = trim($body['comment'] ?? $_POST['comment'] ?? 'manual-entry');
    $routerOrigin = trim($body['router'] ?? $_POST['router'] ?? 'Pacenet Cloud');

    if (empty($uname)) {
        jsonResponse(false, null, 'Username/Kode voucher wajib diisi', 400);
    }

    if (empty($price)) {
        if (strpos($profile, '12-jam') !== false) $price = 4000;
        elseif (strpos($profile, '1minggu') !== false) $price = 40000;
        elseif (strpos($profile, '1bulan') !== false) $price = 100000;
        else $price = 5000;
    }
    if (empty($validity)) {
        if (strpos($profile, '12-jam') !== false) $validity = '12h';
        elseif (strpos($profile, '1minggu') !== false) $validity = '7d';
        elseif (strpos($profile, '1bulan') !== false) $validity = '30d';
        else $validity = '1d';
    }

    // Check duplicate
    $chk = pg_query_params($pg, "SELECT id FROM pacenet_vouchers WHERE username = $1", array($uname));
    if ($chk && pg_num_rows($chk) > 0) {
        jsonResponse(false, null, "Username '$uname' sudah terdaftar dalam sistem", 400);
    }

    pg_query_params($pg, "
        INSERT INTO pacenet_vouchers (username, password, profile, price, validity, comment, status, uptime, bytes_total, router_origin)
        VALUES ($1, $2, $3, $4, $5, $6, 'unused', '0s', 0, $7)
    ", array($uname, $pass, $profile, $price, $validity, $comment, $routerOrigin));

    pg_query_params($pg, "
        INSERT INTO radcheck (username, attribute, op, value)
        VALUES ($1, 'Cleartext-Password', ':=', $2)
        ON CONFLICT (username) DO UPDATE SET value = EXCLUDED.value
    ", array($uname, $pass));

    pg_query_params($pg, "
        INSERT INTO radusergroup (username, groupname, priority)
        VALUES ($1, $2, 1)
        ON CONFLICT DO NOTHING
    ", array($uname, $profile));

    jsonResponse(true, array('username' => $uname, 'profile' => $profile), 'Voucher baru berhasil ditambahkan.');
}

// 7. EDIT VOUCHER
if ($action === 'edit') {
    $id = $body['id'] ?? $_POST['id'] ?? '';
    $uname = trim($body['username'] ?? $body['name'] ?? $_POST['username'] ?? '');
    $pass = trim($body['password'] ?? $_POST['password'] ?? '');
    $profile = trim($body['profile'] ?? $_POST['profile'] ?? '');
    $price = floatval($body['price'] ?? $_POST['price'] ?? 0);
    $validity = trim($body['validity'] ?? $_POST['validity'] ?? '');
    $comment = trim($body['comment'] ?? $_POST['comment'] ?? '');
    $status = trim($body['status'] ?? $_POST['status'] ?? '');

    if (empty($uname) && !empty($id)) {
        $nRes = pg_query_params($pg, "SELECT username FROM pacenet_vouchers WHERE id = $1", array($id));
        $uname = $nRes ? pg_fetch_result($nRes, 0, 'username') : '';
    }

    if (empty($uname)) {
        jsonResponse(false, null, 'Username voucher tidak ditemukan', 400);
    }

    $curRes = pg_query_params($pg, "SELECT * FROM pacenet_vouchers WHERE username = $1", array($uname));
    if (!$curRes || pg_num_rows($curRes) === 0) {
        jsonResponse(false, null, 'Voucher tidak ditemukan di database', 404);
    }
    $cur = pg_fetch_assoc($curRes);

    $pass = !empty($pass) ? $pass : $cur['password'];
    $profile = !empty($profile) ? $profile : $cur['profile'];
    $price = $price > 0 ? $price : floatval($cur['price']);
    $validity = !empty($validity) ? $validity : $cur['validity'];
    $comment = $comment !== '' ? $comment : $cur['comment'];
    $status = !empty($status) ? $status : $cur['status'];

    pg_query_params($pg, "
        UPDATE pacenet_vouchers 
        SET password = $1, profile = $2, price = $3, validity = $4, comment = $5, status = $6
        WHERE username = $7
    ", array($pass, $profile, $price, $validity, $comment, $status, $uname));

    if ($status === 'disabled') {
        pg_query_params($pg, "DELETE FROM radcheck WHERE username = $1", array($uname));
    } else {
        pg_query_params($pg, "
            INSERT INTO radcheck (username, attribute, op, value)
            VALUES ($1, 'Cleartext-Password', ':=', $2)
            ON CONFLICT (username) DO UPDATE SET value = EXCLUDED.value
        ", array($uname, $pass));
    }

    if (!empty($profile)) {
        pg_query_params($pg, "DELETE FROM radusergroup WHERE username = $1", array($uname));
        pg_query_params($pg, "INSERT INTO radusergroup (username, groupname, priority) VALUES ($1, $2, 1)", array($uname, $profile));
    }

    jsonResponse(true, array('username' => $uname, 'status' => $status), 'Data voucher berhasil diperbarui.');
}

// 8. BULK DELETE VOUCHERS
if ($action === 'bulk_delete') {
    $usernames = $body['usernames'] ?? $_POST['usernames'] ?? array();
    if (!is_array($usernames) || empty($usernames)) {
        jsonResponse(false, null, 'Daftar username yang akan dihapus kosong', 400);
    }

    $chunkSize = 100;
    $chunks = array_chunk($usernames, $chunkSize);
    $deletedTotal = 0;

    foreach ($chunks as $chunk) {
        $escList = "'" . implode("','", array_map('pg_escape_string', $chunk)) . "'";
        $dRes = pg_query($pg, "DELETE FROM pacenet_vouchers WHERE username IN ($escList)");
        pg_query($pg, "DELETE FROM radcheck WHERE username IN ($escList)");
        pg_query($pg, "DELETE FROM radusergroup WHERE username IN ($escList)");
        $deletedTotal += pg_affected_rows($dRes);
    }

    jsonResponse(true, array('count' => $deletedTotal), "Berhasil menghapus $deletedTotal voucher dari sistem.");
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

// 9. PURGE EXPIRED VOUCHERS OLDER THAN 30 DAYS
if ($action === 'purge_expired_30days') {
    $qPurge30 = pg_query($pg, "
        SELECT username FROM pacenet_vouchers 
        WHERE status = 'expired' 
          AND (
            (expired_at IS NOT NULL AND expired_at < NOW() - INTERVAL '30 days')
            OR (expired_at IS NULL AND COALESCE(last_seen, first_login, created_at) < NOW() - INTERVAL '30 days')
          )
        LIMIT 10000
    ");
    $deletedTotal = 0;
    if ($qPurge30 && pg_num_rows($qPurge30) > 0) {
        $purgeList = array();
        while ($pRow = pg_fetch_assoc($qPurge30)) {
            $purgeList[] = $pRow['username'];
        }
        $chunks = array_chunk($purgeList, 200);
        foreach ($chunks as $chunk) {
            $escPurge = "'" . implode("','", array_map('pg_escape_string', $chunk)) . "'";
            pg_query($pg, "DELETE FROM pacenet_vouchers WHERE username IN ($escPurge)");
            pg_query($pg, "DELETE FROM radcheck WHERE username IN ($escPurge)");
            pg_query($pg, "DELETE FROM radusergroup WHERE username IN ($escPurge)");
            pg_query($pg, "DELETE FROM radreply WHERE username IN ($escPurge)");
            $deletedTotal += count($chunk);
        }
    }
    jsonResponse(true, array('purged_count' => $deletedTotal), "Pembersihan selesai: $deletedTotal voucher expired (> 30 hari) berhasil dihapus dari sistem.");
}

jsonResponse(false, null, 'Invalid action', 400);

