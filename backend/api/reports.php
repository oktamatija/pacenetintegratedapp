<?php
/**
 * Pacenet REST API - Multi-Router Comprehensive Sales & Voucher Reports
 * Aggregates & synchronizes:
 * - Sementara Terpakai (Active / In-Use Sessions across all online routers)
 * - Belum Terpakai (Unused Voucher Stock)
 * - Habis Terpakai (Expired / Used up Vouchers & Sales Logs)
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

global $data;

$routerSession = trim($_GET['router'] ?? 'all'); // 'all' or specific session name (e.g. 'Rumah-DOLPHIN')
$statusFilter = strtolower(trim($_GET['status'] ?? 'all')); // 'all', 'active', 'unused', 'expired'
$filterMonth = $_GET['month'] ?? ''; // e.g. "sep2026" or "09"
$filterDay = $_GET['day'] ?? '';     // e.g. "sep/14/2026"
$search = strtolower(trim($_GET['search'] ?? ''));
$page = max(intval($_GET['page'] ?? 1), 1);
$limit = max(intval($_GET['limit'] ?? 25), 10);
$refresh = isset($_GET['refresh']) && ($_GET['refresh'] === '1' || $_GET['refresh'] === 'true');

// Identify all configured routers
$availableRouters = array();
foreach ($data as $sName => $sCfg) {
    if ($sName !== 'mikhmon' && !empty($sName) && strpos($sName, 'new-') !== 0) {
        $ip = explode('!', $sCfg[1] ?? '')[1] ?? '';
        $user = explode('@|@', $sCfg[2] ?? '')[1] ?? '';
        $pass = explode('#|#', $sCfg[3] ?? '')[1] ?? '';
        $name = explode('%', $sCfg[4] ?? '')[1] ?? $sName;
        $availableRouters[$sName] = array(
            'session' => $sName,
            'name' => $name,
            'ip' => $ip,
            'user' => $user,
            'pass' => $pass
        );
    }
}

// Decide which routers to query
$targetRouters = array();
if ($routerSession !== 'all' && isset($availableRouters[$routerSession])) {
    $targetRouters[$routerSession] = $availableRouters[$routerSession];
} else {
    $targetRouters = $availableRouters;
    $routerSession = 'all';
}

$cacheFile = sys_get_temp_dir() . '/pacenet_reports_cache_' . md5($routerSession) . '.json';
$cacheTtl = 25; // 25 seconds cache for instantaneous UI interactions

$rawCache = null;
if (!$refresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $cContent = @file_get_contents($cacheFile);
    if ($cContent) {
        $rawCache = json_decode($cContent, true);
    }
}

if (!$rawCache || !is_array($rawCache)) {
    $routersData = array();
    $routerCounts = array();

    foreach ($targetRouters as $sName => $rCfg) {
        $rApi = new RouterosAPI();
        $rApi->timeout = 2;
        $rApi->attempts = 1;
        $rApi->debug = false;

        if ($rApi->connect($rCfg['ip'], $rCfg['user'], decrypt($rCfg['pass']))) {
            // Clock
            $clock = $rApi->comm('/system/clock/print');
            $curDate = $clock[0]['date'] ?? date('M/d/Y');
            $curTime = $clock[0]['time'] ?? date('H:i:s');
            $routerTs = strtotime("$curDate $curTime") ?: time();

            // Active sessions
            $act = $rApi->comm('/ip/hotspot/active/print');
            if (!is_array($act)) $act = array();

            // Mikhmon sales scripts
            $scripts = $rApi->comm('/system/script/print', array('?comment' => 'mikhmon'));
            if (!is_array($scripts)) $scripts = array();

            // Hotspot Users (optimized proplist)
            $rApi->write('/ip/hotspot/user/print', false);
            $rApi->write('=.proplist=.id,name,password,profile,uptime,limit-uptime,comment,disabled');
            $users = $rApi->read();
            if (!is_array($users)) $users = array();

            // Profiles
            $profiles = $rApi->comm('/ip/hotspot/user/profile/print');
            if (!is_array($profiles)) $profiles = array();

            $rApi->disconnect();

            $routersData[$sName] = array(
                'session' => $sName,
                'name' => $rCfg['name'],
                'curDate' => $curDate,
                'curTime' => $curTime,
                'routerTs' => $routerTs,
                'act' => $act,
                'scripts' => $scripts,
                'users' => $users,
                'profiles' => $profiles
            );

            $routerCounts[$sName] = count($act);
        } else {
            $routerCounts[$sName] = 0;
        }
    }

    $rawCache = array(
        'timestamp' => time(),
        'routersData' => $routersData,
        'routerCounts' => $routerCounts
    );

    @file_put_contents($cacheFile, json_encode($rawCache));
}

$routersData = $rawCache['routersData'] ?? array();
$routerCounts = $rawCache['routerCounts'] ?? array();

// Prepare router selection list for frontend
$routerOptions = array();
$sumAllActive = 0;
foreach ($availableRouters as $sName => $rCfg) {
    $actC = $routerCounts[$sName] ?? 0;
    $sumAllActive += $actC;
    $routerOptions[] = array(
        'session' => $sName,
        'name' => $rCfg['name'],
        'active_count' => $actC
    );
}
array_unshift($routerOptions, array(
    'session' => 'all',
    'name' => 'Semua Router',
    'active_count' => $sumAllActive
));

// Aggregate data across all processed routers
$activeList = array();
$unusedList = array();
$expiredList = array();
$totalRevenue = 0;
$dailyBreakdown = array();
$profileBreakdown = array();

foreach ($routersData as $rSession => $rBundle) {
    $rName = $rBundle['name'] ?? $rSession;
    $curDate = $rBundle['curDate'] ?? date('M/d/Y');
    $curTime = $rBundle['curTime'] ?? date('H:i:s');
    $routerTs = $rBundle['routerTs'] ?? time();
    $act = $rBundle['act'] ?? array();
    $scripts = $rBundle['scripts'] ?? array();
    $users = $rBundle['users'] ?? array();
    $rawProfiles = $rBundle['profiles'] ?? array();

    // 1. Profile lookup
    $profileLookup = array();
    foreach ($rawProfiles as $p) {
        $pName = $p['name'] ?? '';
        $onlogin = $p['on-login'] ?? '';
        $price = 0;
        $validity = '';
        if (preg_match('/,\s*([^,]*)\s*,\s*([0-9]*)\s*,\s*([^,]*)\s*,\s*([0-9]*)/', $onlogin, $m)) {
            $price = intval($m[2] ?: 0);
            $validity = trim($m[3] ?: '');
        }
        if ($price === 0) {
            $parsedDur = parseBilingualDuration($pName);
            if ($parsedDur['valid']) {
                $validity = $parsedDur['normalized'];
            }
        }
        $profileLookup[$pName] = array(
            'price' => $price,
            'validity' => $validity
        );
    }

    // 2. Active map
    $activeMap = array();
    foreach ($act as $a) {
        $u = $a['user'] ?? '';
        if (!empty($u)) {
            $activeMap[$u] = $a;
        }
    }

    // 3. User map
    $userMap = array();
    foreach ($users as $u) {
        $name = $u['name'] ?? '';
        if (!empty($name) && $name !== 'default-trial') {
            $userMap[$name] = $u;
        }
    }

    // 4. Sementara Terpakai (Active)
    foreach ($activeMap as $uName => $a) {
        $u = $userMap[$uName] ?? null;
        $pName = $u['profile'] ?? 'default';
        $price = $profileLookup[$pName]['price'] ?? 0;
        $validity = $profileLookup[$pName]['validity'] ?? ($u['limit-uptime'] ?? '');

        $bytesTotal = intval($a['bytes-in'] ?? 0) + intval($a['bytes-out'] ?? 0);
        $itemKey = $rSession . '_' . $uName;
        $activeList[$itemKey] = array(
            'id' => $a['.id'] ?? ('act_' . $itemKey),
            'router_session' => $rSession,
            'router_name' => $rName,
            'username' => $uName,
            'password' => $u['password'] ?? $uName,
            'profile' => $pName,
            'price' => $price,
            'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
            'status' => 'active',
            'status_label' => 'Sementara Terpakai',
            'ip' => $a['address'] ?? '-',
            'mac' => $a['mac-address'] ?? '-',
            'uptime' => $a['uptime'] ?? '0s',
            'session_left' => $a['session-time-left'] ?? '-',
            'bytes_total' => $bytesTotal,
            'bytes_human' => formatBytesReadable($bytesTotal),
            'validity' => $validity,
            'batch' => $u['comment'] ?? '',
            'date' => $curDate,
            'time' => $curTime
        );
    }

    // 5. Belum Terpakai (Unused) or offline valid
    foreach ($userMap as $uName => $u) {
        $itemKey = $rSession . '_' . $uName;
        if (isset($activeList[$itemKey])) continue;

        $comment = $u['comment'] ?? '';
        $uptime = $u['uptime'] ?? '0s';
        $pName = $u['profile'] ?? 'default';
        $price = $profileLookup[$pName]['price'] ?? 0;
        $validity = $profileLookup[$pName]['validity'] ?? ($u['limit-uptime'] ?? '');

        // Check expiration in comment
        $expTs = false;
        if (preg_match('/([a-z]{3}\/\d{1,2}\/\d{4})\s+(\d{1,2}:\d{2}:\d{2})/i', $comment, $m) ||
            preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})\s+(\d{1,2}:\d{2}:\d{2})/i', $comment, $m)) {
            $expTs = strtotime($m[1] . ' ' . $m[2]);
        }

        if ($expTs !== false) {
            if ($routerTs >= $expTs) {
                // Expired in user database
                $expiredList[$itemKey] = array(
                    'id' => $u['.id'] ?? ('exp_' . $itemKey),
                    'router_session' => $rSession,
                    'router_name' => $rName,
                    'username' => $uName,
                    'password' => $u['password'] ?? $uName,
                    'profile' => $pName,
                    'price' => $price,
                    'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
                    'status' => 'expired',
                    'status_label' => 'Habis Terpakai',
                    'ip' => '-',
                    'mac' => '-',
                    'uptime' => $uptime,
                    'session_left' => 'Waktu Habis',
                    'bytes_total' => intval($u['bytes-in'] ?? 0) + intval($u['bytes-out'] ?? 0),
                    'bytes_human' => formatBytesReadable(intval($u['bytes-in'] ?? 0) + intval($u['bytes-out'] ?? 0)),
                    'validity' => $validity,
                    'batch' => $comment,
                    'date' => date('M/d/Y', $expTs),
                    'time' => date('H:i:s', $expTs)
                );
            } else {
                // Sesi sementara offline tapi masih berlaku
                $remSec = max(0, $expTs - $routerTs);
                $activeList[$itemKey] = array(
                    'id' => $u['.id'] ?? ('act_off_' . $itemKey),
                    'router_session' => $rSession,
                    'router_name' => $rName,
                    'username' => $uName,
                    'password' => $u['password'] ?? $uName,
                    'profile' => $pName,
                    'price' => $price,
                    'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
                    'status' => 'active',
                    'status_label' => 'Sementara Terpakai',
                    'ip' => '-',
                    'mac' => '-',
                    'uptime' => $uptime,
                    'session_left' => formatDurationHuman($remSec, 'id'),
                    'bytes_total' => intval($u['bytes-in'] ?? 0) + intval($u['bytes-out'] ?? 0),
                    'bytes_human' => formatBytesReadable(intval($u['bytes-in'] ?? 0) + intval($u['bytes-out'] ?? 0)),
                    'validity' => $validity,
                    'batch' => $comment,
                    'date' => $curDate,
                    'time' => $curTime
                );
            }
        } elseif ($uptime === '0s' || empty($uptime)) {
            // Belum Terpakai
            $batchDate = '-';
            if (preg_match('/(\d{2})\.(\d{2})\.(\d{2})/', $comment, $bm)) {
                $batchDate = $bm[2] . '/' . $bm[1] . '/20' . $bm[3];
            }

            $unusedList[$itemKey] = array(
                'id' => $u['.id'] ?? ('uns_' . $itemKey),
                'router_session' => $rSession,
                'router_name' => $rName,
                'username' => $uName,
                'password' => $u['password'] ?? $uName,
                'profile' => $pName,
                'price' => $price,
                'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
                'status' => 'unused',
                'status_label' => 'Belum Terpakai',
                'ip' => '-',
                'mac' => '-',
                'uptime' => '0s',
                'session_left' => $validity ?: ($u['limit-uptime'] ?? 'Siap Pakai'),
                'bytes_total' => 0,
                'bytes_human' => '0 B',
                'validity' => $validity ?: ($u['limit-uptime'] ?? 'Standar'),
                'batch' => $comment ?: 'Manual',
                'date' => $batchDate,
                'time' => '-'
            );
        }
    }

    // 6. Mikhmon Sales Scripts
    foreach ($scripts as $s) {
        $name = $s['name'] ?? '';
        $parts = explode('-|-', $name);
        if (count($parts) < 4) continue;

        $date = $parts[0] ?? '';
        $time = $parts[1] ?? '';
        $uName = $parts[2] ?? '';
        $price = floatval($parts[3] ?? 0);
        $ip = $parts[4] ?? '';
        $mac = $parts[5] ?? '';
        $validity = $parts[6] ?? '';
        $pName = $parts[7] ?? '';
        $batch = $parts[8] ?? '';

        $totalRevenue += $price;

        if (!isset($dailyBreakdown[$date])) {
            $dailyBreakdown[$date] = array('date' => $date, 'count' => 0, 'revenue' => 0);
        }
        $dailyBreakdown[$date]['count']++;
        $dailyBreakdown[$date]['revenue'] += $price;

        $profKey = !empty($pName) ? $pName : (!empty($validity) ? $validity : 'Standar');
        if (!isset($profileBreakdown[$profKey])) {
            $profileBreakdown[$profKey] = array('profile' => $profKey, 'count' => 0, 'revenue' => 0);
        }
        $profileBreakdown[$profKey]['count']++;
        $profileBreakdown[$profKey]['revenue'] += $price;

        $itemKey = $rSession . '_' . $uName;
        if (isset($activeList[$itemKey])) {
            $activeList[$itemKey]['price'] = $price;
            $activeList[$itemKey]['price_formatted'] = 'Rp ' . number_format($price, 0, ',', '.');
            $activeList[$itemKey]['date'] = $date;
            $activeList[$itemKey]['time'] = $time;
            if (!empty($validity)) $activeList[$itemKey]['validity'] = $validity;
            if (!empty($pName)) $activeList[$itemKey]['profile'] = $pName;
            if (!empty($ip) && $activeList[$itemKey]['ip'] === '-') $activeList[$itemKey]['ip'] = $ip;
            if (!empty($mac) && $activeList[$itemKey]['mac'] === '-') $activeList[$itemKey]['mac'] = $mac;
        } else {
            if (!isset($expiredList[$itemKey])) {
                $expiredList[$itemKey] = array(
                    'id' => $s['.id'] ?? ('exp_' . $itemKey),
                    'router_session' => $rSession,
                    'router_name' => $rName,
                    'username' => $uName,
                    'password' => '-',
                    'profile' => $pName ?: 'Hotspot',
                    'price' => $price,
                    'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
                    'status' => 'expired',
                    'status_label' => 'Habis Terpakai',
                    'ip' => $ip ?: '-',
                    'mac' => $mac ?: '-',
                    'uptime' => 'Selesai',
                    'session_left' => 'Habis',
                    'bytes_total' => 0,
                    'bytes_human' => '-',
                    'validity' => $validity ?: 'Standar',
                    'batch' => $batch,
                    'date' => $date,
                    'time' => $time
                );
            } else {
                $expiredList[$itemKey]['price'] = $price;
                $expiredList[$itemKey]['price_formatted'] = 'Rp ' . number_format($price, 0, ',', '.');
                if ($expiredList[$itemKey]['ip'] === '-') $expiredList[$itemKey]['ip'] = $ip;
                if ($expiredList[$itemKey]['mac'] === '-') $expiredList[$itemKey]['mac'] = $mac;
                $expiredList[$itemKey]['date'] = $date;
                $expiredList[$itemKey]['time'] = $time;
            }
        }
    }
}

// Calculate potential unused revenue
$potentialUnusedRevenue = 0;
foreach ($unusedList as $un) {
    $potentialUnusedRevenue += floatval($un['price'] ?? 0);
}

$countActive = count($activeList);
$countUnused = count($unusedList);
$countExpired = count($expiredList);
$totalVouchers = $countActive + $countUnused + $countExpired;

// Select pool according to status filter
$selectedPool = array();
if ($statusFilter === 'active') {
    $selectedPool = array_values($activeList);
} elseif ($statusFilter === 'unused') {
    $selectedPool = array_values($unusedList);
} elseif ($statusFilter === 'expired') {
    $selectedPool = array_values($expiredList);
} else {
    $selectedPool = array_merge(
        array_values($activeList),
        array_values($expiredList),
        array_values($unusedList)
    );
}

// Apply Search & Date Filters
$filteredItems = array();
foreach ($selectedPool as $item) {
    if (!empty($filterDay)) {
        if ($item['date'] !== $filterDay && strpos($item['date'], $filterDay) === false) {
            continue;
        }
    } elseif (!empty($filterMonth)) {
        if (strpos(strtolower($item['date']), strtolower($filterMonth)) === false) {
            continue;
        }
    }

    if (!empty($search)) {
        $matchUser = strpos(strtolower($item['username'] ?? ''), $search) !== false;
        $matchProf = strpos(strtolower($item['profile'] ?? ''), $search) !== false;
        $matchRouter = strpos(strtolower($item['router_name'] ?? ''), $search) !== false;
        $matchIp = strpos(strtolower($item['ip'] ?? ''), $search) !== false;
        $matchMac = strpos(strtolower($item['mac'] ?? ''), $search) !== false;
        $matchBatch = strpos(strtolower($item['batch'] ?? ''), $search) !== false;
        $matchStatus = strpos(strtolower($item['status_label'] ?? ''), $search) !== false;
        $matchDate = strpos(strtolower($item['date'] ?? ''), $search) !== false;

        if (!$matchUser && !$matchProf && !$matchRouter && !$matchIp && !$matchMac && !$matchBatch && !$matchStatus && !$matchDate) {
            continue;
        }
    }

    $filteredItems[] = $item;
}

// Sort items
if ($statusFilter === 'all') {
    usort($filteredItems, function($a, $b) {
        $statusWeight = array('active' => 3, 'expired' => 2, 'unused' => 1);
        $wA = $statusWeight[$a['status']] ?? 0;
        $wB = $statusWeight[$b['status']] ?? 0;
        if ($wA !== $wB) {
            return $wB - $wA;
        }
        return strcmp($b['date'] . ' ' . $b['time'], $a['date'] . ' ' . $a['time']);
    });
} elseif ($statusFilter === 'active') {
    usort($filteredItems, function($a, $b) {
        return strcmp($b['uptime'], $a['uptime']);
    });
} else {
    usort($filteredItems, function($a, $b) {
        return strcmp($b['date'] . ' ' . $b['time'], $a['date'] . ' ' . $a['time']);
    });
}

$totalFiltered = count($filteredItems);
$totalPages = ceil($totalFiltered / $limit);
$offset = ($page - 1) * $limit;
$slicedItems = array_slice($filteredItems, $offset, $limit);

jsonResponse(true, array(
    'current_router' => $routerSession,
    'routers' => $routerOptions,
    'summary' => array(
        'total_revenue' => $totalRevenue,
        'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
        'count_active' => $countActive,
        'count_unused' => $countUnused,
        'count_expired' => $countExpired,
        'total_vouchers' => $totalVouchers,
        'potential_unused_revenue' => $potentialUnusedRevenue,
        'potential_unused_revenue_formatted' => 'Rp ' . number_format($potentialUnusedRevenue, 0, ',', '.'),
        'filtered_count' => $totalFiltered
    ),
    'daily_breakdown' => array_values($dailyBreakdown),
    'profile_breakdown' => array_values($profileBreakdown),
    'pagination' => array(
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'total' => $totalFiltered
    ),
    'vouchers' => $slicedItems,
    'transactions' => $slicedItems
));
