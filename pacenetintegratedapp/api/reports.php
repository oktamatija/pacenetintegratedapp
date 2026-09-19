<?php
/**
 * Pacenet REST API - Multi-Router Comprehensive Sales & Voucher Reports
 * Supports time-based partitioning:
 * - Per-Hari (Daily, with 24 hourly breakdown)
 * - Per-Minggu (Weekly, with 7 daily breakdown)
 * - Per-Bulan (Monthly, with daily breakdown)
 * - Per-Tahun (Yearly, with 12 monthly breakdown)
 * - Semua Waktu (All Time)
 * 
 * Aggregates & synchronizes Single Source of Truth:
 * - FreeRADIUS radacct & PostgreSQL pacenet_vouchers
 * - Real-time active sessions across all online MikroTik routers
 * - Legacy Mikhmon sales logs preservation
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

// Increase execution time & memory for high-volume database queries
set_time_limit(120);
ini_set('memory_limit', '512M');

global $data;

// Indonesian labels dictionary
$idMonths = array(
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
);
$idDays = array(
    'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu',
    'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu', 'Sun' => 'Minggu'
);
$shortMonths = array(
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
    5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
    9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
);

// Date normalization helper
function normalizeReportDate($dateStr, $timeStr = '00:00:00') {
    $dateStr = trim($dateStr);
    $timeStr = trim($timeStr);
    if (empty($dateStr) || $dateStr === '-') return null;

    if (preg_match('/^([a-z]{3})\/(\d{1,2})\/(\d{4})$/i', $dateStr, $m)) {
        $months = array('jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12);
        $mIdx = $months[strtolower($m[1])] ?? 9;
        $d = intval($m[2]);
        $y = intval($m[3]);
        $ts = strtotime(sprintf('%04d-%02d-%02d %s', $y, $mIdx, $d, $timeStr));
        if ($ts !== false) {
            return array('ts' => $ts, 'iso_date' => sprintf('%04d-%02d-%02d', $y, $mIdx, $d), 'time' => $timeStr);
        }
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $dateStr, $m)) {
        $y = intval($m[1]);
        $mIdx = intval($m[2]);
        $d = intval($m[3]);
        $ts = strtotime(sprintf('%04d-%02d-%02d %s', $y, $mIdx, $d, $timeStr));
        if ($ts !== false) {
            return array('ts' => $ts, 'iso_date' => sprintf('%04d-%02d-%02d', $y, $mIdx, $d), 'time' => $timeStr);
        }
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $m)) {
        $d = intval($m[1]);
        $mIdx = intval($m[2]);
        $y = intval($m[3]);
        $ts = strtotime(sprintf('%04d-%02d-%02d %s', $y, $mIdx, $d, $timeStr));
        if ($ts !== false) {
            return array('ts' => $ts, 'iso_date' => sprintf('%04d-%02d-%02d', $y, $mIdx, $d), 'time' => $timeStr);
        }
    }

    $ts = strtotime("$dateStr $timeStr");
    if ($ts !== false) {
        return array('ts' => $ts, 'iso_date' => date('Y-m-d', $ts), 'time' => date('H:i:s', $ts));
    }
    return null;
}

$routerSession = trim($_GET['router'] ?? 'all'); // 'all' or specific session name
$statusFilter = strtolower(trim($_GET['status'] ?? 'all')); // 'all', 'active', 'unused', 'expired'
$periodType = strtolower(trim($_GET['period_type'] ?? 'daily')); // 'daily', 'weekly', 'monthly', 'yearly', 'all'
$inputDate = trim($_GET['date'] ?? '');          // e.g. "2026-09-19"
$inputStartDate = trim($_GET['start_date'] ?? ''); // e.g. "2026-09-15"
$inputEndDate = trim($_GET['end_date'] ?? '');     // e.g. "2026-09-21"
$inputMonth = trim($_GET['month'] ?? '');        // e.g. "2026-09"
$inputYear = trim($_GET['year'] ?? '');          // e.g. "2026"
$search = strtolower(trim($_GET['search'] ?? ''));
$page = max(intval($_GET['page'] ?? 1), 1);
$limit = max(intval($_GET['limit'] ?? 25), 10);
$refresh = isset($_GET['refresh']) && ($_GET['refresh'] === '1' || $_GET['refresh'] === 'true');

// Connect to Centralized PostgreSQL (Single Source of Truth)
$pg = getPgDb();

// Identify all configured routers
$availableRouters = array();
$routerIpMap = array();
$routerNameMap = array();

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
        if (!empty($ip)) {
            $routerIpMap[$ip] = $sName;
        }
        $routerNameMap[$sName] = $name;
    }
}

// Target routers
$targetRouters = array();
$targetIp = null;
if ($routerSession !== 'all' && isset($availableRouters[$routerSession])) {
    $targetRouters[$routerSession] = $availableRouters[$routerSession];
    $targetIp = $availableRouters[$routerSession]['ip'] ?? null;
} else {
    $targetRouters = $availableRouters;
    $routerSession = 'all';
}

// -----------------------------------------------------------------------------
// 1. Time-Based Partitioning & Buckets
// -----------------------------------------------------------------------------
$buckets = array();
$periodLabel = '';
$todayStr = date('Y-m-d');

if ($periodType === 'daily') {
    $selDate = (!empty($inputDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $inputDate)) ? $inputDate : $todayStr;
    $periodStart = strtotime($selDate . ' 00:00:00');
    $periodEnd = strtotime($selDate . ' 23:59:59');
    $dNum = intval(date('j', $periodStart));
    $mNum = intval(date('n', $periodStart));
    $yNum = date('Y', $periodStart);
    $periodLabel = $dNum . ' ' . ($idMonths[$mNum] ?? date('M', $periodStart)) . ' ' . $yNum;

    // 24 Hourly Buckets
    for ($h = 0; $h < 24; $h++) {
        $hKey = sprintf('%02d:00', $h);
        $bStart = $periodStart + ($h * 3600);
        $bEnd = $bStart + 3599;
        $buckets[$hKey] = array(
            'label' => $hKey,
            'start' => $bStart,
            'end' => $bEnd,
            'revenue' => 0,
            'count' => 0
        );
    }
} elseif ($periodType === 'weekly') {
    if (!empty($inputStartDate) && !empty($inputEndDate)) {
        $periodStart = strtotime($inputStartDate . ' 00:00:00');
        $periodEnd = strtotime($inputEndDate . ' 23:59:59');
    } else {
        $refTs = strtotime($inputDate ?: $todayStr);
        $dayOfWeek = intval(date('N', $refTs)); // 1 (Mon) to 7 (Sun)
        $periodStart = strtotime('-' . ($dayOfWeek - 1) . ' days 00:00:00', $refTs);
        $periodEnd = strtotime('+' . (7 - $dayOfWeek) . ' days 23:59:59', $refTs);
    }
    $periodLabel = date('d M', $periodStart) . ' - ' . date('d M Y', $periodEnd);

    // 7 Daily Buckets (Mon to Sun)
    for ($d = 0; $d < 7; $d++) {
        $bStart = $periodStart + ($d * 86400);
        $bEnd = $bStart + 86399;
        $dKey = date('Y-m-d', $bStart);
        $dayEn = date('D', $bStart);
        $dayId = $idDays[$dayEn] ?? $dayEn;
        $lbl = $dayId . ' ' . date('d/m', $bStart);
        $buckets[$dKey] = array(
            'label' => $lbl,
            'date_key' => $dKey,
            'start' => $bStart,
            'end' => $bEnd,
            'revenue' => 0,
            'count' => 0
        );
    }
} elseif ($periodType === 'monthly') {
    $selMonth = (!empty($inputMonth) && preg_match('/^\d{4}-\d{2}$/', $inputMonth)) ? $inputMonth : date('Y-m');
    $periodStart = strtotime($selMonth . '-01 00:00:00');
    $daysInMonth = intval(date('t', $periodStart));
    $periodEnd = strtotime(date('Y-m-t 23:59:59', $periodStart));
    $mNum = intval(date('n', $periodStart));
    $periodLabel = ($idMonths[$mNum] ?? date('F', $periodStart)) . ' ' . date('Y', $periodStart);

    // 1 to N Daily Buckets
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dKey = sprintf('%s-%02d', $selMonth, $day);
        $bStart = strtotime($dKey . ' 00:00:00');
        $bEnd = strtotime($dKey . ' 23:59:59');
        $lbl = sprintf('%02d', $day);
        $buckets[$dKey] = array(
            'label' => $lbl,
            'date_key' => $dKey,
            'start' => $bStart,
            'end' => $bEnd,
            'revenue' => 0,
            'count' => 0
        );
    }
} elseif ($periodType === 'yearly') {
    $selYear = (!empty($inputYear) && preg_match('/^\d{4}$/', $inputYear)) ? $inputYear : date('Y');
    $periodStart = strtotime($selYear . '-01-01 00:00:00');
    $periodEnd = strtotime($selYear . '-12-31 23:59:59');
    $periodLabel = 'Tahun ' . $selYear;

    // 12 Monthly Buckets
    for ($m = 1; $m <= 12; $m++) {
        $mKey = sprintf('%s-%02d', $selYear, $m);
        $bStart = strtotime($mKey . '-01 00:00:00');
        $bEnd = strtotime(date('Y-m-t 23:59:59', $bStart));
        $lbl = $shortMonths[$m] ?? date('M', $bStart);
        $buckets[$mKey] = array(
            'label' => $lbl,
            'month_key' => $mKey,
            'start' => $bStart,
            'end' => $bEnd,
            'revenue' => 0,
            'count' => 0
        );
    }
} else {
    // Semua Waktu (All Time)
    $periodType = 'all';
    $periodStart = 0;
    $periodEnd = 2147483647;
    $periodLabel = 'Semua Waktu';
}

$startTsStr = ($periodStart > 0) ? date('Y-m-d H:i:s', $periodStart) : '1970-01-01 00:00:00';
$endTsStr = ($periodEnd < 2147483647) ? date('Y-m-d H:i:s', $periodEnd) : '2099-12-31 23:59:59';

// -----------------------------------------------------------------------------
// 2. Query Live Active Sessions from MikroTik Routers (Fast Cacheable)
// -----------------------------------------------------------------------------
$cacheFile = sys_get_temp_dir() . '/pacenet_router_act_' . md5($routerSession) . '.json';
$cacheTtl = 15; // 15 seconds cache for real-time active users
$routerActData = null;

if (!$refresh && file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $routerActData = json_decode(@file_get_contents($cacheFile), true);
}

if (!$routerActData || !is_array($routerActData)) {
    $routerActData = array('active' => array(), 'counts' => array());
    foreach ($targetRouters as $sName => $rCfg) {
        $rApi = new RouterosAPI();
        $rApi->timeout = 2.5;
        $rApi->attempts = 1;
        $rApi->debug = false;

        $actList = array();
        if ($rApi->connect($rCfg['ip'], $rCfg['user'], decrypt($rCfg['pass']))) {
            $act = $rApi->comm('/ip/hotspot/active/print');
            if (is_array($act)) {
                foreach ($act as $a) {
                    $u = $a['user'] ?? '';
                    if (!empty($u)) {
                        $a['router_session'] = $sName;
                        $a['router_name'] = $rCfg['name'];
                        $actList[] = $a;
                    }
                }
            }
            $rApi->disconnect();
            $routerActData['counts'][$sName] = count($actList);
            $routerActData['active'][$sName] = $actList;
        } else {
            $routerActData['counts'][$sName] = 0;
            $routerActData['active'][$sName] = array();
        }
    }
    @file_put_contents($cacheFile, json_encode($routerActData));
}

// Router options for frontend dropdown
$routerOptions = array();
$sumAllActive = 0;
foreach ($availableRouters as $sName => $rCfg) {
    $actC = $routerActData['counts'][$sName] ?? 0;
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

// -----------------------------------------------------------------------------
// 3. PostgreSQL Database Aggregation (Single Source of Truth)
// -----------------------------------------------------------------------------
$activeList = array();
$unusedList = array();
$expiredList = array();

$countUnused = 0;
$potentialUnusedRevenue = 0;
$periodRevenue = 0;
$periodSalesCount = 0;
$allTimeRevenue = 0;
$allTimeSalesCount = 0;
$periodExpiredCount = 0;

$profileBreakdownPeriod = array();
$profileBreakdownAll = array();

if ($pg) {
    // A. STOCK BELUM TERPAKAI (Unused Voucher Stock)
    $whereUnused = "WHERE status = 'unused'";
    $paramsUnused = array();
    if ($routerSession !== 'all') {
        $whereUnused .= " AND (router_origin = $1 OR router_origin = 'Semua Router (Central)' OR router_origin IS NULL)";
        $paramsUnused[] = $routerSession;
    }
    $qUnusedStats = !empty($paramsUnused)
        ? pg_query_params($pg, "SELECT count(1) as cnt, COALESCE(sum(price), 0) as total FROM pacenet_vouchers $whereUnused", $paramsUnused)
        : pg_query($pg, "SELECT count(1) as cnt, COALESCE(sum(price), 0) as total FROM pacenet_vouchers $whereUnused");
    if ($qUnusedStats && ($rUn = pg_fetch_assoc($qUnusedStats))) {
        $countUnused = intval($rUn['cnt'] ?? 0);
        $potentialUnusedRevenue = floatval($rUn['total'] ?? 0);
    }

    // B. SEMENTARA TERPAKAI (Live Router Active Sessions matched with DB)
    $activeUsernames = array();
    $rawActiveSessions = array();

    foreach ($routerActData['active'] as $sName => $aList) {
        if ($routerSession !== 'all' && $sName !== $routerSession) continue;
        foreach ($aList as $a) {
            $u = $a['user'] ?? '';
            if (!empty($u)) {
                $activeUsernames[$u] = true;
                $rawActiveSessions[$u] = $a;
            }
        }
    }

    // Also check radacct for active sessions where acctstoptime IS NULL
    $whereRadAct = "WHERE acctstoptime IS NULL";
    $paramsRadAct = array();
    if (!empty($targetIp)) {
        $whereRadAct .= " AND nasipaddress = $1";
        $paramsRadAct[] = $targetIp;
    }
    $qRadAct = !empty($paramsRadAct)
        ? pg_query_params($pg, "SELECT username, nasipaddress::text, framedipaddress::text, callingstationid::text, acctstarttime, acctsessiontime, (acctinputoctets + acctoutputoctets) as bytes_tot FROM radacct $whereRadAct", $paramsRadAct)
        : pg_query($pg, "SELECT username, nasipaddress::text, framedipaddress::text, callingstationid::text, acctstarttime, acctsessiontime, (acctinputoctets + acctoutputoctets) as bytes_tot FROM radacct $whereRadAct");

    if ($qRadAct) {
        while ($ra = pg_fetch_assoc($qRadAct)) {
            $u = $ra['username'];
            if (!isset($rawActiveSessions[$u])) {
                $rSess = $routerIpMap[$ra['nasipaddress']] ?? 'Rumah-DOLPHIN';
                $rawActiveSessions[$u] = array(
                    'user' => $u,
                    'address' => $ra['framedipaddress'] ?: '-',
                    'mac-address' => $ra['callingstationid'] ?: '-',
                    'uptime' => formatDurationHuman(intval($ra['acctsessiontime'] ?? 0), 'id'),
                    'bytes-in' => intval($ra['bytes_tot'] ?? 0),
                    'bytes-out' => 0,
                    'router_session' => $rSess,
                    'router_name' => $routerNameMap[$rSess] ?? $rSess
                );
            }
        }
    }

    // Lookup metadata for active sessions from pacenet_vouchers
    $activeMeta = array();
    if (!empty($rawActiveSessions)) {
        $activeKeys = array_keys($rawActiveSessions);
        $chunkedKeys = array_chunk($activeKeys, 500);
        foreach ($chunkedKeys as $cKeys) {
            $escList = "'" . implode("','", array_map('pg_escape_string', $cKeys)) . "'";
            $qMeta = @pg_query($pg, "SELECT username, password, profile, price, validity, comment FROM pacenet_vouchers WHERE username IN ($escList)");
            if ($qMeta) {
                while ($mRow = pg_fetch_assoc($qMeta)) {
                    $activeMeta[$mRow['username']] = $mRow;
                }
            }
        }
    }

    foreach ($rawActiveSessions as $uName => $a) {
        $meta = $activeMeta[$uName] ?? array();
        $price = floatval($meta['price'] ?? 4000);
        $profile = $meta['profile'] ?? '12-jam';
        $validity = $meta['validity'] ?? '12h';
        $comment = $meta['comment'] ?? 'radius';
        $rSess = $a['router_session'] ?? 'Rumah-DOLPHIN';
        $rName = $a['router_name'] ?? ($routerNameMap[$rSess] ?? $rSess);
        $bytesTotal = intval($a['bytes-in'] ?? 0) + intval($a['bytes-out'] ?? 0);

        $activeList[$uName] = array(
            'id' => 'act_' . $uName,
            'router_session' => $rSess,
            'router_name' => $rName,
            'username' => $uName,
            'password' => $meta['password'] ?? $uName,
            'profile' => $profile,
            'price' => $price,
            'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
            'status' => 'active',
            'status_label' => 'Sementara Terpakai',
            'ip' => $a['address'] ?? '-',
            'mac' => $a['mac-address'] ?? '-',
            'uptime' => $a['uptime'] ?? '0s',
            'session_left' => $a['session-time-left'] ?? $validity,
            'bytes_total' => $bytesTotal,
            'bytes_human' => formatBytesReadable($bytesTotal),
            'validity' => $validity,
            'batch' => $comment,
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'ts' => time(),
            'in_period' => true
        );
    }

    // C. OMZET & SALES IN PERIOD (from radacct & pacenet_vouchers)
    // A voucher sale is recognized when the voucher is activated / first used in radacct
    $whereSales = "";
    $paramsSales = array();
    $pIdx = 1;

    if ($periodType !== 'all') {
        $whereSales = "HAVING MIN(acctstarttime) >= $" . $pIdx++ . " AND MIN(acctstarttime) <= $" . $pIdx++;
        $paramsSales[] = $startTsStr;
        $paramsSales[] = $endTsStr;
    }

    $routerNasFilter = "";
    if (!empty($targetIp)) {
        $routerNasFilter = ($whereSales ? " AND " : "HAVING ") . "MAX(nasipaddress::text) = $" . $pIdx++;
        $paramsSales[] = $targetIp;
    }

    $sqlSales = "
        SELECT 
            a.username,
            a.first_time,
            a.nasipaddress,
            a.framedipaddress,
            a.callingstationid,
            COALESCE(v.profile, '12-jam') as profile,
            COALESCE(v.price, 4000) as price,
            COALESCE(v.validity, '12h') as validity,
            COALESCE(v.comment, 'radius') as batch,
            COALESCE(v.status, 'expired') as status
        FROM (
            SELECT 
                username, 
                MIN(acctstarttime) as first_time,
                MAX(nasipaddress::text) as nasipaddress,
                MAX(framedipaddress::text) as framedipaddress,
                MAX(callingstationid::text) as callingstationid
            FROM radacct
            GROUP BY username
            $whereSales
            $routerNasFilter
        ) a
        LEFT JOIN pacenet_vouchers v ON a.username = v.username
        ORDER BY a.first_time DESC
    ";

    $qPeriodSales = !empty($paramsSales) ? pg_query_params($pg, $sqlSales, $paramsSales) : pg_query($pg, $sqlSales);

    if ($qPeriodSales) {
        while ($row = pg_fetch_assoc($qPeriodSales)) {
            $uName = $row['username'];
            $price = floatval($row['price']);
            $firstTimeTs = strtotime($row['first_time']);
            $rSess = $routerIpMap[$row['nasipaddress']] ?? 'Rumah-DOLPHIN';
            $rName = $routerNameMap[$rSess] ?? $rSess;
            $prof = $row['profile'] ?: '12-jam';

            $periodRevenue += $price;
            $periodSalesCount++;

            // Profile breakdown for period
            if (!isset($profileBreakdownPeriod[$prof])) {
                $profileBreakdownPeriod[$prof] = array('profile' => $prof, 'count' => 0, 'revenue' => 0);
            }
            $profileBreakdownPeriod[$prof]['count']++;
            $profileBreakdownPeriod[$prof]['revenue'] += $price;

            // Fill Chart Time Buckets
            if ($periodType === 'daily') {
                $hKey = sprintf('%02d:00', intval(date('H', $firstTimeTs)));
                if (isset($buckets[$hKey])) {
                    $buckets[$hKey]['revenue'] += $price;
                    $buckets[$hKey]['count']++;
                }
            } elseif ($periodType === 'weekly' || $periodType === 'monthly') {
                $dKey = date('Y-m-d', $firstTimeTs);
                if (isset($buckets[$dKey])) {
                    $buckets[$dKey]['revenue'] += $price;
                    $buckets[$dKey]['count']++;
                }
            } elseif ($periodType === 'yearly') {
                $mKey = date('Y-m', $firstTimeTs);
                if (isset($buckets[$mKey])) {
                    $buckets[$mKey]['revenue'] += $price;
                    $buckets[$mKey]['count']++;
                }
            }

            // Also populate into expiredList if session has ended and not currently active
            if (!isset($activeList[$uName]) && !isset($expiredList[$uName])) {
                $expiredList[$uName] = array(
                    'id' => 'exp_' . $uName,
                    'router_session' => $rSess,
                    'router_name' => $rName,
                    'username' => $uName,
                    'password' => '-',
                    'profile' => $prof,
                    'price' => $price,
                    'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
                    'status' => 'expired',
                    'status_label' => 'Habis Terpakai',
                    'ip' => $row['framedipaddress'] ?: '-',
                    'mac' => $row['callingstationid'] ?: '-',
                    'uptime' => 'Selesai',
                    'session_left' => 'Habis',
                    'bytes_total' => 0,
                    'bytes_human' => '-',
                    'validity' => $row['validity'] ?: '12h',
                    'batch' => $row['batch'] ?: 'radius',
                    'date' => date('Y-m-d', $firstTimeTs),
                    'time' => date('H:i:s', $firstTimeTs),
                    'ts' => $firstTimeTs,
                    'in_period' => true
                );
            }
        }
    }

    // D. HABIS TERPAKAI COUNT (Sessions completed / expired in period)
    $whereExpCount = "";
    $paramsExp = array();
    $pIdxExp = 1;
    if ($periodType !== 'all') {
        $whereExpCount = "WHERE acctstoptime >= $" . $pIdxExp++ . " AND acctstoptime <= $" . $pIdxExp++;
        $paramsExp[] = $startTsStr;
        $paramsExp[] = $endTsStr;
    }
    if (!empty($targetIp)) {
        $whereExpCount .= ($whereExpCount ? " AND " : "WHERE ") . "nasipaddress = $" . $pIdxExp++;
        $paramsExp[] = $targetIp;
    }
    $qExpCount = !empty($paramsExp)
        ? pg_query_params($pg, "SELECT count(DISTINCT username) as cnt FROM radacct $whereExpCount", $paramsExp)
        : pg_query($pg, "SELECT count(DISTINCT username) as cnt FROM radacct $whereExpCount");
    if ($qExpCount && ($rExp = pg_fetch_assoc($qExpCount))) {
        $periodExpiredCount = intval($rExp['cnt'] ?? 0);
    }
    if ($periodExpiredCount === 0) {
        $periodExpiredCount = count($expiredList);
    }

    // E. ALL-TIME REVENUE & TOTAL ACCUMULATION
    $whereAllTime = "";
    $paramsAll = array();
    if (!empty($targetIp)) {
        $whereAllTime = "WHERE nasipaddress = $1";
        $paramsAll[] = $targetIp;
    }
    $qAllTime = !empty($paramsAll)
        ? pg_query_params($pg, "
            SELECT 
                count(DISTINCT a.username) as all_sales_count,
                COALESCE(sum(COALESCE(v.price, 4000)), 0) as all_revenue
            FROM (
                SELECT username
                FROM radacct
                $whereAllTime
                GROUP BY username
            ) a
            LEFT JOIN pacenet_vouchers v ON a.username = v.username
        ", $paramsAll)
        : pg_query($pg, "
            SELECT 
                count(DISTINCT a.username) as all_sales_count,
                COALESCE(sum(COALESCE(v.price, 4000)), 0) as all_revenue
            FROM (
                SELECT username
                FROM radacct
                GROUP BY username
            ) a
            LEFT JOIN pacenet_vouchers v ON a.username = v.username
        ");

    if ($qAllTime && ($rAll = pg_fetch_assoc($qAllTime))) {
        $allTimeRevenue = floatval($rAll['all_revenue'] ?? 0);
        $allTimeSalesCount = intval($rAll['all_sales_count'] ?? 0);
    }

    // Preserve historical Mikhmon script revenue baseline from before migration
    // (Historical baseline: 1704 sales, Rp 4.988.000)
    $legacyMikhmonBaseline = ($routerSession === 'all' || $routerSession === 'Rumah-DOLPHIN') ? 4988000 : 0;
    $legacyMikhmonCount = ($routerSession === 'all' || $routerSession === 'Rumah-DOLPHIN') ? 1704 : 0;
    $allTimeRevenue += $legacyMikhmonBaseline;
    $allTimeSalesCount += $legacyMikhmonCount;
}

// -----------------------------------------------------------------------------
// 4. Populate Unused Table Pool (When viewing 'unused' or 'all')
// -----------------------------------------------------------------------------
if ($statusFilter === 'unused' || $statusFilter === 'all') {
    $whereUnPool = "WHERE status = 'unused'";
    $paramsUnPool = array();
    if ($routerSession !== 'all') {
        $whereUnPool .= " AND (router_origin = $1 OR router_origin = 'Semua Router (Central)' OR router_origin IS NULL)";
        $paramsUnPool[] = $routerSession;
    }
    $sqlUnPool = "SELECT id, username, password, profile, price, validity, comment, created_at, router_origin FROM pacenet_vouchers $whereUnPool ORDER BY id DESC LIMIT 500";
    $qUnPool = !empty($paramsUnPool) ? pg_query_params($pg, $sqlUnPool, $paramsUnPool) : pg_query($pg, $sqlUnPool);

    if ($qUnPool) {
        while ($u = pg_fetch_assoc($qUnPool)) {
            $uName = $u['username'];
            $rOrigin = $u['router_origin'] ?: 'Semua Router (Central)';
            $unusedList[$uName] = array(
                'id' => strval($u['id']),
                'router_session' => $rOrigin,
                'router_name' => $routerNameMap[$rOrigin] ?? $rOrigin,
                'username' => $uName,
                'password' => $u['password'] ?? $uName,
                'profile' => $u['profile'] ?: '12-jam',
                'price' => floatval($u['price'] ?: 4000),
                'price_formatted' => 'Rp ' . number_format(floatval($u['price'] ?: 4000), 0, ',', '.'),
                'status' => 'unused',
                'status_label' => 'Belum Terpakai',
                'ip' => '-',
                'mac' => '-',
                'uptime' => '0s',
                'session_left' => $u['validity'] ?: 'Siap Pakai',
                'bytes_total' => 0,
                'bytes_human' => '0 B',
                'validity' => $u['validity'] ?: '12h',
                'batch' => $u['comment'] ?: 'Batch',
                'date' => substr($u['created_at'] ?? date('Y-m-d'), 0, 10),
                'time' => substr($u['created_at'] ?? '00:00:00', 11, 8) ?: '-',
                'ts' => strtotime($u['created_at'] ?? 'now'),
                'in_period' => true
            );
        }
    }
}

// -----------------------------------------------------------------------------
// 5. Select Pool according to Status Tab & Apply Search Filter
// -----------------------------------------------------------------------------
$countActive = count($activeList);
$countExpired = ($periodType !== 'all') ? $periodExpiredCount : ($allTimeSalesCount ?: count($expiredList));
$totalVouchers = $countActive + $countUnused + $countExpired;

$selectedPool = array();
if ($statusFilter === 'active') {
    $selectedPool = array_values($activeList);
} elseif ($statusFilter === 'unused') {
    $selectedPool = array_values($unusedList);
} elseif ($statusFilter === 'expired') {
    $selectedPool = array_values($expiredList);
} else {
    // 'all': active first, then expired, then unused
    $selectedPool = array_merge(
        array_values($activeList),
        array_values($expiredList),
        array_values($unusedList)
    );
}

// Apply Search Filter
$filteredItems = array();
foreach ($selectedPool as $item) {
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
        $tsA = $a['ts'] ?? 0;
        $tsB = $b['ts'] ?? 0;
        return $tsB - $tsA;
    });
} elseif ($statusFilter === 'active') {
    usort($filteredItems, function($a, $b) {
        return strcmp($b['uptime'], $a['uptime']);
    });
} else {
    usort($filteredItems, function($a, $b) {
        $tsA = $a['ts'] ?? 0;
        $tsB = $b['ts'] ?? 0;
        return $tsB - $tsA;
    });
}

// Pagination slicing
$totalFiltered = count($filteredItems);
$totalPages = ceil($totalFiltered / $limit) ?: 1;
$offset = ($page - 1) * $limit;
$slicedItems = array_slice($filteredItems, $offset, $limit);

// Prepare Chart Time-Series Arrays
$chartCategories = array();
$chartSeriesRevenue = array();
$chartSeriesCount = array();
foreach ($buckets as $b) {
    $chartCategories[] = $b['label'];
    $chartSeriesRevenue[] = $b['revenue'];
    $chartSeriesCount[] = $b['count'];
}

$displayRevenue = ($periodType !== 'all') ? $periodRevenue : $allTimeRevenue;

// -----------------------------------------------------------------------------
// 6. JSON Response
// -----------------------------------------------------------------------------
jsonResponse(true, array(
    'current_router' => $routerSession,
    'routers' => $routerOptions,
    'period' => array(
        'type' => $periodType,
        'label' => $periodLabel,
        'date' => ($periodType === 'daily') ? date('Y-m-d', $periodStart) : null,
        'month' => ($periodType === 'monthly') ? date('Y-m', $periodStart) : null,
        'year' => ($periodType === 'yearly') ? date('Y', $periodStart) : null,
        'start_date' => date('Y-m-d', $periodStart),
        'end_date' => date('Y-m-d', $periodEnd),
        'start_ts' => $periodStart,
        'end_ts' => $periodEnd
    ),
    'summary' => array(
        'period_type' => $periodType,
        'period_label' => $periodLabel,
        'period_revenue' => $periodRevenue,
        'period_revenue_formatted' => 'Rp ' . number_format($periodRevenue, 0, ',', '.'),
        'period_sales_count' => $periodSalesCount,
        'all_time_revenue' => $allTimeRevenue,
        'all_time_revenue_formatted' => 'Rp ' . number_format($allTimeRevenue, 0, ',', '.'),
        'all_time_sales_count' => $allTimeSalesCount,
        'total_revenue' => $displayRevenue,
        'total_revenue_formatted' => 'Rp ' . number_format($displayRevenue, 0, ',', '.'),
        'count_active' => $countActive,
        'count_unused' => $countUnused,
        'count_expired' => $countExpired,
        'total_vouchers' => $totalVouchers,
        'potential_unused_revenue' => $potentialUnusedRevenue,
        'potential_unused_revenue_formatted' => 'Rp ' . number_format($potentialUnusedRevenue, 0, ',', '.'),
        'filtered_count' => $totalFiltered
    ),
    'chart' => array(
        'categories' => $chartCategories,
        'revenue_series' => $chartSeriesRevenue,
        'count_series' => $chartSeriesCount,
        'raw_buckets' => array_values($buckets)
    ),
    'profile_breakdown' => array_values($profileBreakdownPeriod),
    'pagination' => array(
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'total' => $totalFiltered
    ),
    'vouchers' => $slicedItems,
    'transactions' => $slicedItems
));
