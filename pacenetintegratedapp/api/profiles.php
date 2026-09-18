<?php
/**
 * Pacenet REST API - Hotspot User Profile Management & Quick Print Support
 * Antigravity IDE - Pacenet Billing System
 * 
 * Supports both MikroTik Local Hotspot User Profiles & FreeRADIUS Cloud Profiles (Single Source of Truth)
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$routerSession = $_GET['router'] ?? $_POST['router'] ?? '';

// Build available routers list
$availableRouters = array();
foreach ($data as $sName => $sCfg) {
    if ($sName !== 'mikhmon' && !empty($sName) && strpos($sName, 'new-') !== 0) {
        $availableRouters[] = array(
            'session' => $sName,
            'name' => explode('%', $sCfg[4] ?? '')[1] ?? $sName,
            'ip' => explode('!', $sCfg[1] ?? '')[1] ?? '',
            'active' => ($sName === $routerSession)
        );
    }
}

// Determine active router session
$conn = null;
if (empty($routerSession) && !empty($availableRouters[0]['session'])) {
    $routerSession = $availableRouters[0]['session'];
}

if (!empty($routerSession)) {
    $conn = getMikroTikApi($routerSession);
}

$isRouterConnected = ($conn && !empty($conn['success']));
$api = $isRouterConnected ? $conn['api'] : null;
$connError = (!$isRouterConnected && $conn) ? ($conn['error'] ?? 'Router tidak dapat dihubungi') : '';

/**
 * Helper to parse Mikhmon on-login string
 */
function parseMikhmonOnLogin($onlogin) {
    $res = array(
        'expmode' => '0',
        'price' => 0,
        'validity' => '',
        'validity_display' => '-',
        'validity_display_en' => '-',
        'sprice' => 0,
        'lock' => 'Disable'
    );
    if (empty($onlogin)) return $res;

    if (preg_match('/,\s*([^,]*)\s*,\s*([0-9]*)\s*,\s*([^,]*)\s*,\s*([0-9]*)\s*,\s*,\s*([^,]*)\s*,/', $onlogin, $m)) {
        $res['expmode'] = trim($m[1]) ?: '0';
        $res['price'] = intval($m[2] ?: 0);
        $res['validity'] = trim($m[3]);
        $res['validity_display'] = formatDurationHuman($res['validity'], 'id');
        $res['validity_display_en'] = formatDurationHuman($res['validity'], 'en');
        $res['sprice'] = intval($m[4] ?: 0);
        $res['lock'] = trim($m[5]) ?: 'Disable';
    }
    return $res;
}

/**
 * Helper to construct Mikhmon on-login script with bilingual duration support
 */
function buildMikhmonOnLogin($name, $expmode, $price, $validity, $sprice, $lock) {
    $validity = normalizeMikrotikDuration($validity, $validity ?: '1d');
    $getlock = ($lock === 'Enable') ? 'Enable' : 'Disable';
    $lockScript = ($getlock === 'Enable') ? '; [:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]' : '';

    $priceVal = intval($price);
    $spriceVal = intval($sprice);
    if ($spriceVal === 0 && $priceVal > 0) $spriceVal = $priceVal;

    if ($expmode === '0' || empty($expmode)) {
        if ($priceVal > 0) {
            return ':put (",,' . $priceVal . ',,' . $spriceVal . ',noexp,' . $getlock . ',")' . $lockScript;
        }
        return '';
    }

    $record = '; :local mac $"mac-address"; :local time [/system clock get time ]; /system script add name="$date-|-$time-|-$user-|-'.$priceVal.'-|-$address-|-$mac-|-' . $validity . '-|-'.$name.'-|-$comment" owner="$month$year" source="$date" comment="mikhmon"';

    $baseScript = ':put (",'.$expmode.',' . $priceVal . ',' . $validity . ','.$spriceVal.',,' . $getlock . ',"); {:local comment [ /ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local ucode [:pic $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $ucode = "pn" or [:find $comment "/"] = "" or $comment = "") do={ :local date [ /system clock get date ];:local year [ :pick $date 7 11 ];:local month [ :pick $date 0 3 ]; /sys sch add name="$user" disable=no start-date=$date interval="' . $validity . '"; :delay 5s; :local exp [ /sys sch get [ /sys sch find where name="$user" ] next-run]; :local getxp [len $exp]; :if ($getxp = 15) do={ :local d [:pic $exp 0 6]; :local t [:pic $exp 7 16]; :local s ("/"); :local exp ("$d$s$year $t"); /ip hotspot user set comment="$exp" [find where name="$user"];}; :if ($getxp = 8) do={ /ip hotspot user set comment="$date $exp" [find where name="$user"];}; :if ($getxp > 15) do={ /ip hotspot user set comment="$exp" [find where name="$user"];};:delay 5s; /sys sch remove [find where name="$user"]';

    if ($expmode === 'rem') {
        return $baseScript . $lockScript . "}}";
    } elseif ($expmode === 'ntf') {
        return $baseScript . $lockScript . "}}";
    } elseif ($expmode === 'remc') {
        return $baseScript . $record . $lockScript . "}}";
    } elseif ($expmode === 'ntfc') {
        return $baseScript . $record . $lockScript . "}}";
    }

    return ':put (",,' . $priceVal . ',,' . $spriceVal . ',noexp,' . $getlock . ',")' . $lockScript;
}

/**
 * Synchronize profile attributes to FreeRADIUS PostgreSQL (radgroupreply)
 */
function syncProfileToRadius($pg, $groupname, $rateLimit, $sharedUsers, $validitySec) {
    if (!$pg || empty($groupname)) return;
    
    // Clean old entries for this group
    @pg_query_params($pg, "DELETE FROM radgroupreply WHERE groupname = $1", array($groupname));
    
    // 1. Rate limit
    if (!empty($rateLimit)) {
        @pg_query_params($pg, 
            "INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES ($1, 'MikroTik-Rate-Limit', ':=', $2)",
            array($groupname, $rateLimit)
        );
    }
    
    // 2. Port limit / Shared users
    if (!empty($sharedUsers)) {
        @pg_query_params($pg, 
            "INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES ($1, 'Port-Limit', ':=', $2)",
            array($groupname, (string)$sharedUsers)
        );
    }
    
    // 3. Session timeout / Validity
    if (!empty($validitySec) && $validitySec > 0) {
        @pg_query_params($pg, 
            "INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES ($1, 'Session-Timeout', ':=', $2)",
            array($groupname, (string)$validitySec)
        );
    }
}

/**
 * Delete profile from FreeRADIUS PostgreSQL (radgroupreply & radgroupcheck)
 */
function deleteProfileFromRadius($pg, $groupname) {
    if (!$pg || empty($groupname)) return;
    @pg_query_params($pg, "DELETE FROM radgroupreply WHERE groupname = $1", array($groupname));
    @pg_query_params($pg, "DELETE FROM radgroupcheck WHERE groupname = $1", array($groupname));
}


// -------------------------------------------------------------------------
// ACTION: LIST PROFILES
// -------------------------------------------------------------------------
if ($action === 'list') {
    $profiles = array();
    $pg = getPgDb();

    if ($isRouterConnected && $api) {
        // Fetch from connected MikroTik
        $rawProfiles = $api->comm('/ip/hotspot/user/profile/print');
        if (!is_array($rawProfiles)) $rawProfiles = array();

        // Collect user counts per profile from router
        $allUsers = $api->comm('/ip/hotspot/user/print');
        $userCountMap = array();
        if (is_array($allUsers)) {
            foreach ($allUsers as $u) {
                $pName = $u['profile'] ?? 'default';
                $userCountMap[$pName] = ($userCountMap[$pName] ?? 0) + 1;
            }
        }

        // Collect active user counts per profile
        $activeUsers = $api->comm('/ip/hotspot/active/print');
        $activeCountMap = array();
        if (is_array($activeUsers)) {
            $userProfileLookup = array();
            if (is_array($allUsers)) {
                foreach ($allUsers as $u) {
                    if (!empty($u['name'])) $userProfileLookup[$u['name']] = $u['profile'] ?? 'default';
                }
            }
            foreach ($activeUsers as $act) {
                $uName = $act['user'] ?? '';
                $pName = $userProfileLookup[$uName] ?? 'default';
                $activeCountMap[$pName] = ($activeCountMap[$pName] ?? 0) + 1;
            }
        }

        foreach ($rawProfiles as $p) {
            $pName = $p['name'] ?? '';
            $onlogin = $p['on-login'] ?? '';
            $parsed = parseMikhmonOnLogin($onlogin);

            // Calculate duration in seconds and sync to FreeRADIUS
            $durParsed = parseBilingualDuration($parsed['validity']);
            $valSec = $durParsed['valid'] ? $durParsed['seconds'] : 0;
            if ($pg && !empty($pName)) {
                syncProfileToRadius($pg, $pName, $p['rate-limit'] ?? '', $p['shared-users'] ?? '1', $valSec);
            }

            $profiles[] = array(
                'id' => $p['.id'],
                'name' => $pName,
                'shared_users' => $p['shared-users'] ?? '1',
                'rate_limit' => $p['rate-limit'] ?? '',
                'address_pool' => $p['address-pool'] ?? 'none',
                'parent_queue' => $p['parent-queue'] ?? 'none',
                'expmode' => $parsed['expmode'],
                'price' => $parsed['price'],
                'validity' => $parsed['validity'],
                'validity_display' => $parsed['validity_display'],
                'validity_display_en' => $parsed['validity_display_en'],
                'sprice' => $parsed['sprice'],
                'lock' => $parsed['lock'],
                'total_users' => $userCountMap[$pName] ?? 0,
                'active_users' => $activeCountMap[$pName] ?? 0,
                'source' => 'mikrotik'
            );
        }
    } else {
        // Router is currently offline or unreachable: read profiles from FreeRADIUS database
        if ($pg) {
            $qGroups = @pg_query($pg, "
                SELECT groupname, 
                       MAX(CASE WHEN attribute = 'MikroTik-Rate-Limit' THEN value END) AS rate_limit,
                       MAX(CASE WHEN attribute = 'Session-Timeout' THEN value END) AS session_timeout,
                       MAX(CASE WHEN attribute = 'Port-Limit' THEN value END) AS shared_users
                FROM radgroupreply
                GROUP BY groupname
                ORDER BY groupname ASC
            ");

            // Voucher count from PostgreSQL
            $vCounts = array();
            $qVc = @pg_query($pg, "SELECT profile, count(*) AS total_count FROM pacenet_vouchers GROUP BY profile");
            if ($qVc) {
                while ($vc = pg_fetch_assoc($qVc)) {
                    $vCounts[$vc['profile']] = intval($vc['total_count']);
                }
            }

            if ($qGroups) {
                while ($g = pg_fetch_assoc($qGroups)) {
                    $gName = $g['groupname'];
                    $sec = intval($g['session_timeout'] ?? 0);
                    $valHuman = $sec > 0 ? formatDurationHuman($sec . 's', 'id') : '-';
                    $valHumanEn = $sec > 0 ? formatDurationHuman($sec . 's', 'en') : '-';
                    
                    $profiles[] = array(
                        'id' => $gName,
                        'name' => $gName,
                        'shared_users' => $g['shared_users'] ?: '1',
                        'rate_limit' => $g['rate_limit'] ?: '',
                        'address_pool' => 'none',
                        'parent_queue' => 'none',
                        'expmode' => 'remc',
                        'price' => 0,
                        'validity' => $sec > 0 ? ($sec . 's') : '',
                        'validity_display' => $valHuman,
                        'validity_display_en' => $valHumanEn,
                        'sprice' => 0,
                        'lock' => 'Disable',
                        'total_users' => $vCounts[$gName] ?? 0,
                        'active_users' => 0,
                        'source' => 'radius_cloud'
                    );
                }
            }
        }
    }

    sendJsonResponse(true, array(
        'current_router' => $routerSession,
        'routers' => $availableRouters,
        'profiles' => $profiles,
        'router_connected' => $isRouterConnected
    ), $isRouterConnected ? '' : "Router [{$routerSession}] sedang offline. Menampilkan profil dari database FreeRADIUS cloud.");
}

// -------------------------------------------------------------------------
// ACTION: GET POOLS & QUEUES (For Form Select Options)
// -------------------------------------------------------------------------
if ($action === 'get_options' || $action === 'options') {
    $poolList = array('none');
    $queueList = array('none');

    if ($isRouterConnected && $api) {
        $pools = $api->comm('/ip/pool/print');
        if (is_array($pools)) {
            foreach ($pools as $pl) {
                if (!empty($pl['name'])) $poolList[] = $pl['name'];
            }
        }

        $queues = $api->comm('/queue/simple/print', array('?dynamic' => 'false'));
        if (is_array($queues)) {
            foreach ($queues as $q) {
                if (!empty($q['name'])) $queueList[] = $q['name'];
            }
        }
    }

    sendJsonResponse(true, array(
        'pools' => array_values(array_unique($poolList)),
        'parent_queues' => array_values(array_unique($queueList))
    ));
}

// Guard mutating actions against read-only demo user
if (in_array($action, array('create', 'update', 'delete', 'save'))) {
    checkWritePermission();
}

// -------------------------------------------------------------------------
// ACTION: CREATE USER PROFILE
// -------------------------------------------------------------------------
if ($action === 'create') {
    $rawInput = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $name = trim($rawInput['name'] ?? '');
    $name = preg_replace('/\s+/', '-', $name);

    if (empty($name)) {
        sendJsonResponse(false, null, 'Nama profil wajib diisi.');
    }

    $sharedUsers = trim($rawInput['shared_users'] ?? '1');
    $rateLimit = trim($rawInput['rate_limit'] ?? '');
    $expmode = trim($rawInput['expmode'] ?? 'remc');
    $validityRaw = trim($rawInput['validity'] ?? '1d');
    $validity = normalizeMikrotikDuration($validityRaw, '1d');
    $price = intval($rawInput['price'] ?? 0);
    $sprice = intval($rawInput['sprice'] ?? $price);
    $lock = trim($rawInput['lock'] ?? 'Disable');
    $addrPool = trim($rawInput['address_pool'] ?? 'none');
    $parentQueue = trim($rawInput['parent_queue'] ?? 'none');

    // Parse validity to seconds for FreeRADIUS
    $durParsed = parseBilingualDuration($validityRaw);
    $validitySeconds = $durParsed['valid'] ? $durParsed['seconds'] : 86400;

    // 1. Always save to PostgreSQL FreeRADIUS
    $pg = getPgDb();
    if ($pg) {
        syncProfileToRadius($pg, $name, $rateLimit, $sharedUsers, $validitySeconds);
    }

    // 2. If MikroTik router is connected, also push to MikroTik
    $createdOnRouter = false;
    $routerTrapError = '';

    if ($isRouterConnected && $api) {
        // Check duplicate on router
        $existing = $api->comm('/ip/hotspot/user/profile/print', array('?name' => $name));
        if (!empty($existing)) {
            // If already exists, update it
            $targetId = $existing[0]['.id'] ?? '';
            $onlogin = buildMikhmonOnLogin($name, $expmode, $price, $validity, $sprice, $lock);
            $params = array(
                '.id' => $targetId,
                'shared-users' => $sharedUsers ?: '1',
                'rate-limit' => $rateLimit,
                'idle-timeout' => 'none',
                'on-login' => $onlogin
            );
            if (!empty($addrPool) && $addrPool !== 'none') $params['address-pool'] = $addrPool;
            if (!empty($parentQueue) && $parentQueue !== 'none') $params['parent-queue'] = $parentQueue;
            $api->comm('/ip/hotspot/user/profile/set', $params);
            $createdOnRouter = true;
        } else {
            $onlogin = buildMikhmonOnLogin($name, $expmode, $price, $validity, $sprice, $lock);
            $params = array(
                'name' => $name,
                'shared-users' => $sharedUsers ?: '1',
                'rate-limit' => $rateLimit,
                'idle-timeout' => 'none',
                'status-autorefresh' => '1m',
                'on-login' => $onlogin
            );
            if (!empty($addrPool) && $addrPool !== 'none') $params['address-pool'] = $addrPool;
            if (!empty($parentQueue) && $parentQueue !== 'none') $params['parent-queue'] = $parentQueue;

            $addRes = $api->comm('/ip/hotspot/user/profile/add', $params);
            if (isset($addRes['!trap'])) {
                $routerTrapError = $addRes['!trap'][0]['message'] ?? 'MikroTik API error';
            } else {
                $createdOnRouter = true;
            }

            // Setup background expired scheduler if expmode != 0
            if ($expmode !== '0' && !empty($validity)) {
                $mode = ($expmode === 'ntf' || $expmode === 'ntfc') ? 'set limit-uptime=1s' : 'remove';
                $randTime = '0' . rand(1, 5) . ':' . rand(10, 59) . ':' . rand(10, 59);
                $randInterval = '00:02:' . rand(10, 59);

                $bgservice = ':local dateint do={:local montharray ( "jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec" );:local days [ :pick $d 4 6 ];:local month [ :pick $d 0 3 ];:local year [ :pick $d 7 11 ];:local monthint ([ :find $montharray $month]);:local month ($monthint + 1);:if ( [len $month] = 1) do={:local zero ("0");:return [:tonum ("$year$zero$month$days")];} else={:return [:tonum ("$year$month$days")];}}; :local timeint do={ :local hours [ :pick $t 0 2 ]; :local minutes [ :pick $t 3 5 ]; :return ($hours * 60 + $minutes) ; }; :local date [ /system clock get date ]; :local time [ /system clock get time ]; :local today [$dateint d=$date] ; :local curtime [$timeint t=$time] ; :foreach i in [ /ip hotspot user find where profile="' . $name . '" ] do={ :local comment [ /ip hotspot user get $i comment]; :local name [ /ip hotspot user get $i name]; :local gettime [:pic $comment 12 20]; :if ([:pic $comment 3] = "/" and [:pic $comment 6] = "/") do={:local expd [$dateint d=$comment] ; :local expt [$timeint t=$gettime] ; :if (($expd < $today and $expt < $curtime) or ($expd < $today and $expt > $curtime) or ($expd = $today and $expt < $curtime)) do={ [ /ip hotspot user ' . $mode . ' $i ]; [ /ip hotspot active remove [find where user=$name] ]; [ /ip hotspot cookie remove [find where user=$name] ];}}}';

                $api->comm('/system/scheduler/add', array(
                    'name' => $name,
                    'start-time' => $randTime,
                    'interval' => $randInterval,
                    'on-event' => $bgservice,
                    'disabled' => 'no',
                    'comment' => 'Monitor Expired ' . $name
                ));
            }
        }
    }

    $msg = "User profile '{$name}' berhasil disimpan";
    if ($createdOnRouter) {
        $msg .= " di router {$routerSession} dan database FreeRADIUS cloud.";
    } elseif (!empty($routerTrapError)) {
        $msg .= " di FreeRADIUS cloud (Peringatan MikroTik: {$routerTrapError}).";
    } else {
        $msg .= " di database FreeRADIUS cloud (akan disinkronkan saat router online).";
    }

    sendJsonResponse(true, array(
        'name' => $name,
        'created_on_router' => $createdOnRouter
    ), $msg);
}

// -------------------------------------------------------------------------
// ACTION: UPDATE USER PROFILE
// -------------------------------------------------------------------------
if ($action === 'update') {
    $rawInput = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = trim($rawInput['id'] ?? '');
    $name = trim($rawInput['name'] ?? '');

    if (empty($id) && empty($name)) {
        sendJsonResponse(false, null, 'ID atau nama profil wajib diisi.');
    }

    $sharedUsers = trim($rawInput['shared_users'] ?? '1');
    $rateLimit = trim($rawInput['rate_limit'] ?? '');
    $expmode = trim($rawInput['expmode'] ?? 'remc');
    $validityRaw = trim($rawInput['validity'] ?? '1d');
    $validity = normalizeMikrotikDuration($validityRaw, '1d');
    $price = intval($rawInput['price'] ?? 0);
    $sprice = intval($rawInput['sprice'] ?? $price);
    $lock = trim($rawInput['lock'] ?? 'Disable');
    $addrPool = trim($rawInput['address_pool'] ?? 'none');
    $parentQueue = trim($rawInput['parent_queue'] ?? 'none');

    $durParsed = parseBilingualDuration($validityRaw);
    $validitySeconds = $durParsed['valid'] ? $durParsed['seconds'] : 86400;

    // 1. Sync FreeRADIUS
    $pg = getPgDb();
    if ($pg) {
        syncProfileToRadius($pg, $name, $rateLimit, $sharedUsers, $validitySeconds);
    }

    // 2. If router connected, set on MikroTik
    if ($isRouterConnected && $api) {
        $targetId = $id;
        if (empty($targetId) || strpos($targetId, '*') === false) {
            $find = $api->comm('/ip/hotspot/user/profile/print', array('?name' => $name));
            if (!empty($find[0]['.id'])) $targetId = $find[0]['.id'];
        }

        if (!empty($targetId)) {
            $onlogin = buildMikhmonOnLogin($name, $expmode, $price, $validity, $sprice, $lock);
            $params = array(
                '.id' => $targetId,
                'shared-users' => $sharedUsers ?: '1',
                'rate-limit' => $rateLimit,
                'idle-timeout' => 'none',
                'on-login' => $onlogin
            );
            if (!empty($addrPool) && $addrPool !== 'none') $params['address-pool'] = $addrPool;
            else $params['address-pool'] = 'none';

            if (!empty($parentQueue) && $parentQueue !== 'none') $params['parent-queue'] = $parentQueue;
            else $params['parent-queue'] = 'none';

            $api->comm('/ip/hotspot/user/profile/set', $params);

            // Update scheduler if exists
            $sch = $api->comm('/system/scheduler/print', array('?name' => $name));
            if ($expmode !== '0' && !empty($validity)) {
                $mode = ($expmode === 'ntf' || $expmode === 'ntfc') ? 'set limit-uptime=1s' : 'remove';
                $bgservice = ':local dateint do={:local montharray ( "jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec" );:local days [ :pick $d 4 6 ];:local month [ :pick $d 0 3 ];:local year [ :pick $d 7 11 ];:local monthint ([ :find $montharray $month]);:local month ($monthint + 1);:if ( [len $month] = 1) do={:local zero ("0");:return [:tonum ("$year$zero$month$days")];} else={:return [:tonum ("$year$month$days")];}}; :local timeint do={ :local hours [ :pick $t 0 2 ]; :local minutes [ :pick $t 3 5 ]; :return ($hours * 60 + $minutes) ; }; :local date [ /system clock get date ]; :local time [ /system clock get time ]; :local today [$dateint d=$date] ; :local curtime [$timeint t=$time] ; :foreach i in [ /ip hotspot user find where profile="' . $name . '" ] do={ :local comment [ /ip hotspot user get $i comment]; :local name [ /ip hotspot user get $i name]; :local gettime [:pic $comment 12 20]; :if ([:pic $comment 3] = "/" and [:pic $comment 6] = "/") do={:local expd [$dateint d=$comment] ; :local expt [$timeint t=$gettime] ; :if (($expd < $today and $expt < $curtime) or ($expd < $today and $expt > $curtime) or ($expd = $today and $expt < $curtime)) do={ [ /ip hotspot user ' . $mode . ' $i ]; [ /ip hotspot active remove [find where user=$name] ]; [ /ip hotspot cookie remove [find where user=$name] ];}}}';

                if (!empty($sch[0]['.id'])) {
                    $api->comm('/system/scheduler/set', array(
                        '.id' => $sch[0]['.id'],
                        'on-event' => $bgservice,
                        'disabled' => 'no'
                    ));
                } else {
                    $randTime = '0' . rand(1, 5) . ':' . rand(10, 59) . ':' . rand(10, 59);
                    $randInterval = '00:02:' . rand(10, 59);
                    $api->comm('/system/scheduler/add', array(
                        'name' => $name,
                        'start-time' => $randTime,
                        'interval' => $randInterval,
                        'on-event' => $bgservice,
                        'disabled' => 'no',
                        'comment' => 'Monitor Expired ' . $name
                    ));
                }
            } else {
                if (!empty($sch[0]['.id'])) {
                    $api->comm('/system/scheduler/remove', array('.id' => $sch[0]['.id']));
                }
            }
        }
    }

    sendJsonResponse(true, array('name' => $name), "User profile '{$name}' berhasil diperbarui.");
}

// -------------------------------------------------------------------------
// ACTION: DELETE USER PROFILE
// -------------------------------------------------------------------------
if ($action === 'delete') {
    $rawInput = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = trim($rawInput['id'] ?? '');
    $name = trim($rawInput['name'] ?? '');

    if (empty($id) && empty($name)) {
        sendJsonResponse(false, null, 'ID atau nama profil wajib diisi.');
    }

    if (strtolower($name) === 'default') {
        sendJsonResponse(false, null, "Profil 'default' bawaan sistem MikroTik tidak boleh dihapus.");
    }

    // 1. Delete from FreeRADIUS
    $pg = getPgDb();
    if ($pg) {
        deleteProfileFromRadius($pg, $name);
    }

    // 2. Delete from MikroTik if connected
    if ($isRouterConnected && $api) {
        $targetId = $id;
        if (empty($targetId) || strpos($targetId, '*') === false) {
            $find = $api->comm('/ip/hotspot/user/profile/print', array('?name' => $name));
            if (!empty($find[0]['.id'])) $targetId = $find[0]['.id'];
        }

        if (!empty($targetId)) {
            $api->comm('/ip/hotspot/user/profile/remove', array('.id' => $targetId));
        }

        // Remove scheduler
        if (!empty($name)) {
            $sch = $api->comm('/system/scheduler/print', array('?name' => $name));
            if (!empty($sch[0]['.id'])) {
                $api->comm('/system/scheduler/remove', array('.id' => $sch[0]['.id']));
            }
        }
    }

    sendJsonResponse(true, array('name' => $name), "User profile '{$name}' berhasil dihapus.");
}

// -------------------------------------------------------------------------
// ACTION: QUICK VOUCHERS FOR PROFILE (Ready to print)
// -------------------------------------------------------------------------
if ($action === 'quick_vouchers') {
    $profileName = trim($_GET['profile'] ?? '');
    $commentFilter = trim($_GET['comment'] ?? '');
    $limit = intval($_GET['limit'] ?? 55);
    if ($limit <= 0 || $limit > 550) $limit = 55;

    $pg = getPgDb();
    if ($pg) {
        $whereParts = array("status = 'unused'");
        $params = array();
        $pIdx = 1;
        if (!empty($profileName) && $profileName !== 'all') {
            $whereParts[] = "profile = $" . $pIdx++;
            $params[] = $profileName;
        }
        $whereSql = "WHERE " . implode(' AND ', $whereParts);
        $vSql = "SELECT id, username, password, profile, price, validity, comment FROM pacenet_vouchers $whereSql ORDER BY id DESC LIMIT $limit";
        $vRes = !empty($params) ? pg_query_params($pg, $vSql, $params) : pg_query($pg, $vSql);
        if ($vRes && pg_num_rows($vRes) > 0) {
            $vouchers = array();
            while ($r = pg_fetch_assoc($vRes)) {
                $vouchers[] = array(
                    'id' => strval($r['id']),
                    'username' => $r['username'],
                    'password' => $r['password'] ?: $r['username'],
                    'profile' => $r['profile'],
                    'uptime' => '0s',
                    'limit_uptime' => $r['validity'] ?: '12h',
                    'comment' => $r['comment'] ?: '',
                    'price' => floatval($r['price'] ?: 5000),
                    'sprice' => floatval($r['price'] ?: 5000),
                    'dns_name' => 'hotspot.yunus',
                    'hotspot_name' => 'PACENET HOTSPOT',
                    'currency' => 'Rp'
                );
            }
            sendJsonResponse(true, array(
                'profile' => $profileName,
                'dns_name' => 'hotspot.yunus',
                'hotspot_name' => 'PACENET HOTSPOT',
                'currency' => 'Rp',
                'vouchers' => $vouchers
            ));
        }
    }

    if ($isRouterConnected && $api) {
        $query = array();
        if (!empty($profileName)) {
            $query['?profile'] = $profileName;
        }
        if (!empty($commentFilter)) {
            $query['?comment'] = $commentFilter;
        }

        $users = $api->comm('/ip/hotspot/user/print', $query);
        if (!is_array($users)) $users = array();

        $cfg = $data[$routerSession] ?? array();
        $dnsName = explode('^', $cfg[5] ?? '')[1] ?? 'hotspot.yunus';
        $hsName = explode('%', $cfg[4] ?? '')[1] ?? $routerSession;
        $currency = explode('&', $cfg[6] ?? '')[1] ?? 'Rp';

        $pFind = $api->comm('/ip/hotspot/user/profile/print', array('?name' => $profileName));
        $parsedMeta = !empty($pFind[0]['on-login']) ? parseMikhmonOnLogin($pFind[0]['on-login']) : array('price' => 0, 'validity' => '', 'sprice' => 0);

        $vouchers = array();
        $count = 0;
        for ($i = count($users) - 1; $i >= 0; $i--) {
            if ($count >= $limit) break;
            $u = $users[$i];
            if (($u['name'] ?? '') === 'default-encryption') continue;

            $vouchers[] = array(
                'id' => $u['.id'] ?? '',
                'username' => $u['name'] ?? '',
                'password' => $u['password'] ?? $u['name'] ?? '',
                'profile' => $u['profile'] ?? $profileName,
                'uptime' => $u['uptime'] ?? '0s',
                'limit_uptime' => $u['limit-uptime'] ?? $parsedMeta['validity'],
                'comment' => $u['comment'] ?? '',
                'price' => $parsedMeta['price'],
                'sprice' => $parsedMeta['sprice'] ?: $parsedMeta['price'],
                'dns_name' => $dnsName,
                'hotspot_name' => $hsName,
                'currency' => $currency
            );
            $count++;
        }

        sendJsonResponse(true, array(
            'profile' => $profileName,
            'router' => $routerSession,
            'dns_name' => $dnsName,
            'hotspot_name' => $hsName,
            'currency' => $currency,
            'price' => $parsedMeta['price'],
            'sprice' => $parsedMeta['sprice'] ?: $parsedMeta['price'],
            'validity' => $parsedMeta['validity'],
            'vouchers' => $vouchers
        ));
    }

    sendJsonResponse(true, array(
        'profile' => $profileName,
        'vouchers' => array()
    ), "Belum ada voucher aktif yang siap cetak untuk profil '{$profileName}'.");
}

sendJsonResponse(false, null, 'Aksi tidak dikenal.');
