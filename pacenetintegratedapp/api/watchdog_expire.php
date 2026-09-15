<?php
/**
 * Pacenet High-Reliability Hotspot Expiration Watchdog
 * Ensures strict enforcement of voucher expiration for both FreeRADIUS & MikroTik Local Hotspot.
 * 
 * Runs via CLI / Cron every minute:
 * * * * * /usr/bin/php /var/www/pacenetintegratedapp/api/watchdog_expire.php >/dev/null 2>&1
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['run_now'])) {
    require_once(__DIR__ . '/common.php');
    checkAdminAuth(true);
    checkWritePermission();
} else {
    require_once(__DIR__ . '/common.php');
}

$startTime = microtime(true);
$results = array(
    'timestamp' => date('Y-m-d H:i:s'),
    'radius_expired_purged' => 0,
    'mikrotik_expired_purged' => 0,
    'active_sessions_killed' => 0,
    'cookies_purged' => 0,
    'errors' => array()
);

// -------------------------------------------------------------------------
// 1. FREERADIUS POSTGRESQL EXPIRATION ENFORCER
// -------------------------------------------------------------------------
if (function_exists('pg_connect')) {
    $pg = @pg_connect("host=127.0.0.1 port=5432 dbname=radius user=radius password=RadiusPg2026");
    if ($pg) {
        // Build map of group validity (seconds) from radgroupreply
        $groupValidity = array(
            '12-jam' => 43200,          // 12 hours
            'reseller' => 86400,        // 1 day default if set
            'default' => 86400,
            '5K-1Day' => 86400,
            '1minggu-40rb' => 604800,   // 7 days
            '1bulan-100rb' => 2592000   // 30 days
        );

        $qReplies = @pg_query($pg, "SELECT groupname, value FROM radgroupreply WHERE attribute = 'Session-Timeout'");
        if ($qReplies) {
            while ($row = pg_fetch_assoc($qReplies)) {
                $sec = intval($row['value']);
                if ($sec > 0) {
                    $groupValidity[$row['groupname']] = $sec;
                }
            }
        }

        // Also check if any group name has duration in its name (e.g. '12-jam' -> 12h, '2minggu' -> 14d)
        foreach ($groupValidity as $gName => $sec) {
            $parsed = parseBilingualDuration($gName);
            if ($parsed['valid'] && $parsed['seconds'] > 0) {
                $groupValidity[$gName] = $parsed['seconds'];
            }
        }

        // Query all users in radusergroup who have logged in (have radacct entry)
        $qUsers = @pg_query($pg, "
            SELECT 
                u.username,
                u.groupname,
                MIN(a.acctstarttime) as first_login,
                EXTRACT(EPOCH FROM (NOW() - MIN(a.acctstarttime))) as elapsed_seconds
            FROM radusergroup u
            JOIN radacct a ON u.username = a.username
            GROUP BY u.username, u.groupname
        ");

        $expiredRadiusUsers = array();

        if ($qUsers) {
            while ($u = pg_fetch_assoc($qUsers)) {
                $grp = $u['groupname'] ?? 'default';
                $elapsed = floatval($u['elapsed_seconds'] ?? 0);
                
                // Determine validity limit for this group
                $limitSec = $groupValidity[$grp] ?? 0;
                if ($limitSec === 0) {
                    $parsedGrp = parseBilingualDuration($grp);
                    $limitSec = $parsedGrp['valid'] ? $parsedGrp['seconds'] : 86400;
                }

                // If elapsed time exceeds validity limit, voucher has EXPIRED!
                if ($limitSec > 0 && $elapsed >= $limitSec) {
                    $expiredRadiusUsers[] = $u['username'];
                }
            }
        }

        // Purge expired users from radcheck and radusergroup
        if (!empty($expiredRadiusUsers)) {
            @pg_query($pg, "BEGIN");
            $chunkSize = 100;
            $chunks = array_chunk($expiredRadiusUsers, $chunkSize);
            foreach ($chunks as $chunk) {
                $escList = "'" . implode("','", array_map('pg_escape_string', $chunk)) . "'";
                @pg_query($pg, "DELETE FROM radcheck WHERE username IN ($escList)");
                @pg_query($pg, "DELETE FROM radusergroup WHERE username IN ($escList)");
                @pg_query($pg, "DELETE FROM radreply WHERE username IN ($escList)");
            }
            @pg_query($pg, "COMMIT");

            $results['radius_expired_purged'] = count($expiredRadiusUsers);
        }

        @pg_close($pg);
    } else {
        $results['errors'][] = 'Could not connect to PostgreSQL FreeRADIUS database.';
    }
}

// -------------------------------------------------------------------------
// 2. MIKROTIK LOCAL & ACTIVE SESSION DISCONNECT ENFORCER
// -------------------------------------------------------------------------
$routerSessions = array();
foreach ($data as $sName => $sCfg) {
    if ($sName !== 'mikhmon' && !empty($sName) && strpos($sName, 'new-') !== 0) {
        $routerSessions[] = $sName;
    }
}

foreach ($routerSessions as $rSession) {
    $conn = connectMikrotik($rSession, 4);
    if (!$conn) continue;

    $api = $conn['api'];

    // Get Router Clock
    $clock = $api->comm('/system/clock/print');
    $curDate = $clock[0]['date'] ?? date('M/d/Y');
    $curTime = $clock[0]['time'] ?? date('H:i:s');
    $routerTimestamp = strtotime("$curDate $curTime") ?: time();

    // 2a. If there were RADIUS expired users, kick their active sessions & cookies on this router
    if (!empty($expiredRadiusUsers)) {
        foreach ($expiredRadiusUsers as $expUser) {
            $actFind = $api->comm('/ip/hotspot/active/print', array('?user' => $expUser));
            if (!empty($actFind)) {
                foreach ($actFind as $act) {
                    if (!empty($act['.id'])) {
                        $api->comm('/ip/hotspot/active/remove', array('.id' => $act['.id']));
                        $results['active_sessions_killed']++;
                    }
                }
            }

            $cookFind = $api->comm('/ip/hotspot/cookie/print', array('?user' => $expUser));
            if (!empty($cookFind)) {
                foreach ($cookFind as $cook) {
                    if (!empty($cook['.id'])) {
                        $api->comm('/ip/hotspot/cookie/remove', array('.id' => $cook['.id']));
                        $results['cookies_purged']++;
                    }
                }
            }
        }
    }

    // 2b. Check MikroTik Local Hotspot Users (/ip/hotspot/user)
    $allLocalUsers = $api->comm('/ip/hotspot/user/print');
    if (is_array($allLocalUsers)) {
        foreach ($allLocalUsers as $lu) {
            $uName = $lu['name'] ?? '';
            $comment = $lu['comment'] ?? '';
            $uid = $lu['.id'] ?? '';

            if (empty($uName) || $uName === 'default-encryption') continue;

            // Robust expiration timestamp check via parseHotspotExpirationTimestamp
            $expTs = parseHotspotExpirationTimestamp($comment);
            if ($expTs !== false && $routerTimestamp >= $expTs) {
                // USER HAS EXPIRED!
                $api->comm('/ip/hotspot/user/remove', array('.id' => $uid));
                $results['mikrotik_expired_purged']++;

                    // Disconnect active
                    $act = $api->comm('/ip/hotspot/active/print', array('?user' => $uName));
                    if (!empty($act)) {
                        foreach ($act as $a) {
                            if (!empty($a['.id'])) {
                                $api->comm('/ip/hotspot/active/remove', array('.id' => $a['.id']));
                                $results['active_sessions_killed']++;
                            }
                        }
                    }

                    // Remove cookie
                    $cook = $api->comm('/ip/hotspot/cookie/print', array('?user' => $uName));
                    if (!empty($cook)) {
                        foreach ($cook as $c) {
                            if (!empty($c['.id'])) {
                                $api->comm('/ip/hotspot/cookie/remove', array('.id' => $c['.id']));
                                $results['cookies_purged']++;
                            }
                        }
                    }
                }
            }
        }

    $api->disconnect();
}

$results['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

if (php_sapi_name() === 'cli') {
    echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    jsonResponse(true, $results, 'Watchdog execution completed.');
}
