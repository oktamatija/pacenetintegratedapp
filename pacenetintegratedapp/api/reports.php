<?php
/**
 * Pacenet REST API - Multi-Router Comprehensive Sales & Voucher Reports
 * Supports time-based partitioning:
 * - Per-Hari (Daily, with hourly breakdown)
 * - Per-Minggu (Weekly, with daily breakdown)
 * - Per-Bulan (Monthly, with daily breakdown)
 * - Per-Tahun (Yearly, with monthly breakdown)
 * - Semua Waktu (All Time)
 * Aggregates & synchronizes:
 * - Sementara Terpakai (Active / In-Use Sessions across all online routers)
 * - Belum Terpakai (Unused Voucher Stock)
 * - Habis Terpakai (Expired / Used up Vouchers & Sales Logs)
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

global $data;

// Date normalization helper
function normalizeReportDate($dateStr, $timeStr = '00:00:00') {
    $dateStr = trim($dateStr);
    $timeStr = trim($timeStr);
    if (empty($dateStr) || $dateStr === '-') return null;

    // mon/dd/yyyy e.g. sep/15/2026 or Sep/15/2026
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

    // yyyy-mm-dd e.g. 2026-09-15
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $dateStr, $m)) {
        $y = intval($m[1]);
        $mIdx = intval($m[2]);
        $d = intval($m[3]);
        $ts = strtotime(sprintf('%04d-%02d-%02d %s', $y, $mIdx, $d, $timeStr));
        if ($ts !== false) {
            return array('ts' => $ts, 'iso_date' => sprintf('%04d-%02d-%02d', $y, $mIdx, $d), 'time' => $timeStr);
        }
    }

    // dd/mm/yyyy
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
$inputDate = trim($_GET['date'] ?? '');          // e.g. "2026-09-15"
$inputStartDate = trim($_GET['start_date'] ?? ''); // e.g. "2026-09-08"
$inputEndDate = trim($_GET['end_date'] ?? '');     // e.g. "2026-09-15"
$inputMonth = trim($_GET['month'] ?? '');        // e.g. "2026-09"
$inputYear = trim($_GET['year'] ?? '');          // e.g. "2026"
$search = strtolower(trim($_GET['search'] ?? ''));
$page = max(intval($_GET['page'] ?? 1), 1);
$limit = max(intval($_GET['limit'] ?? 25), 10);
$refresh = isset($_GET['refresh']) && ($_GET['refresh'] === '1' || $_GET['refresh'] === 'true');

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

// Determine Period Start & End Timestamps + Series Buckets
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
    // all time
    $periodType = 'all';
    $periodStart = 0;
    $periodEnd = 2147483647;
    $periodLabel = 'Semua Waktu';
}

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

// Target routers
$targetRouters = array();
if ($routerSession !== 'all' && isset($availableRouters[$routerSession])) {
    $targetRouters[$routerSession] = $availableRouters[$routerSession];
} else {
    $targetRouters = $availableRouters;
    $routerSession = 'all';
}

$cacheFile = sys_get_temp_dir() . '/pacenet_reports_cache_' . md5($routerSession) . '.json';
$cacheTtl = 25; // 25s cache

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
            $clock = $rApi->comm('/system/clock/print');
            $curDate = $clock[0]['date'] ?? date('M/d/Y');
            $curTime = $clock[0]['time'] ?? date('H:i:s');
            $routerTs = strtotime("$curDate $curTime") ?: time();

            $act = $rApi->comm('/ip/hotspot/active/print');
            if (!is_array($act)) $act = array();

            $scripts = $rApi->comm('/system/script/print', array('?comment' => 'mikhmon'));
            if (!is_array($scripts)) $scripts = array();

            $rApi->write('/ip/hotspot/user/print', false);
            $rApi->write('=.proplist=.id,name,password,profile,uptime,limit-uptime,comment,disabled');
            $users = $rApi->read();
            if (!is_array($users)) $users = array();

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

// Router options for frontend dropdown
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

// Aggregate lists & calculate revenues
$activeList = array();
$unusedList = array();
$expiredList = array();

$allTimeRevenue = 0;
$allTimeSalesCount = 0;
$periodRevenue = 0;
$periodSalesCount = 0;
$periodExpiredCount = 0;

$profileBreakdownAll = array();
$profileBreakdownPeriod = array();

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
            'time' => $curTime,
            'in_period' => true // Currently active sessions are ongoing
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
                $inPer = ($expTs >= $periodStart && $expTs <= $periodEnd);
                if ($inPer) $periodExpiredCount++;

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
                    'date' => date('Y-m-d', $expTs),
                    'time' => date('H:i:s', $expTs),
                    'ts' => $expTs,
                    'in_period' => $inPer
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
                    'time' => $curTime,
                    'in_period' => true
                );
            }
        } elseif ($uptime === '0s' || empty($uptime)) {
            // Belum Terpakai
            $batchDate = '-';
            if (preg_match('/(\d{2})\.(\d{2})\.(\d{2})/', $comment, $bm)) {
                $batchDate = '20' . $bm[3] . '-' . $bm[2] . '-' . $bm[1];
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
                'time' => '-',
                'in_period' => true
            );
        }
    }

    // 6. Mikhmon Sales Scripts
    foreach ($scripts as $s) {
        $name = $s['name'] ?? '';
        $parts = explode('-|-', $name);
        if (count($parts) < 4) continue;

        $rawDate = $parts[0] ?? '';
        $time = $parts[1] ?? '';
        $uName = $parts[2] ?? '';
        $price = floatval($parts[3] ?? 0);
        $ip = $parts[4] ?? '';
        $mac = $parts[5] ?? '';
        $validity = $parts[6] ?? '';
        $pName = $parts[7] ?? '';
        $batch = $parts[8] ?? '';

        $norm = normalizeReportDate($rawDate, $time);
        $itemTs = $norm['ts'] ?? $routerTs;
        $isoDate = $norm['iso_date'] ?? $rawDate;

        $allTimeRevenue += $price;
        $allTimeSalesCount++;

        // Profile key
        $profKey = !empty($pName) ? $pName : (!empty($validity) ? $validity : 'Standar');
        if (!isset($profileBreakdownAll[$profKey])) {
            $profileBreakdownAll[$profKey] = array('profile' => $profKey, 'count' => 0, 'revenue' => 0);
        }
        $profileBreakdownAll[$profKey]['count']++;
        $profileBreakdownAll[$profKey]['revenue'] += $price;

        // Check if inside period
        $inPeriod = ($itemTs >= $periodStart && $itemTs <= $periodEnd);
        if ($inPeriod) {
            $periodRevenue += $price;
            $periodSalesCount++;

            if (!isset($profileBreakdownPeriod[$profKey])) {
                $profileBreakdownPeriod[$profKey] = array('profile' => $profKey, 'count' => 0, 'revenue' => 0);
            }
            $profileBreakdownPeriod[$profKey]['count']++;
            $profileBreakdownPeriod[$profKey]['revenue'] += $price;

            // Fill time buckets
            if ($periodType === 'daily') {
                $hKey = sprintf('%02d:00', intval(date('H', $itemTs)));
                if (isset($buckets[$hKey])) {
                    $buckets[$hKey]['revenue'] += $price;
                    $buckets[$hKey]['count']++;
                }
            } elseif ($periodType === 'weekly' || $periodType === 'monthly') {
                $dKey = date('Y-m-d', $itemTs);
                if (isset($buckets[$dKey])) {
                    $buckets[$dKey]['revenue'] += $price;
                    $buckets[$dKey]['count']++;
                }
            } elseif ($periodType === 'yearly') {
                $mKey = date('Y-m', $itemTs);
                if (isset($buckets[$mKey])) {
                    $buckets[$mKey]['revenue'] += $price;
                    $buckets[$mKey]['count']++;
                }
            }
        }

        $itemKey = $rSession . '_' . $uName;
        if (isset($activeList[$itemKey])) {
            $activeList[$itemKey]['price'] = $price;
            $activeList[$itemKey]['price_formatted'] = 'Rp ' . number_format($price, 0, ',', '.');
            $activeList[$itemKey]['date'] = $isoDate;
            $activeList[$itemKey]['time'] = $time;
            $activeList[$itemKey]['ts'] = $itemTs;
            if (!empty($validity)) $activeList[$itemKey]['validity'] = $validity;
            if (!empty($pName)) $activeList[$itemKey]['profile'] = $pName;
            if (!empty($ip) && $activeList[$itemKey]['ip'] === '-') $activeList[$itemKey]['ip'] = $ip;
            if (!empty($mac) && $activeList[$itemKey]['mac'] === '-') $activeList[$itemKey]['mac'] = $mac;
        } else {
            if (!isset($expiredList[$itemKey])) {
                if ($inPeriod) $periodExpiredCount++;

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
                    'date' => $isoDate,
                    'time' => $time,
                    'ts' => $itemTs,
                    'in_period' => $inPeriod
                );
            } else {
                $expiredList[$itemKey]['price'] = $price;
                $expiredList[$itemKey]['price_formatted'] = 'Rp ' . number_format($price, 0, ',', '.');
                if ($expiredList[$itemKey]['ip'] === '-') $expiredList[$itemKey]['ip'] = $ip;
                if ($expiredList[$itemKey]['mac'] === '-') $expiredList[$itemKey]['mac'] = $mac;
                $expiredList[$itemKey]['date'] = $isoDate;
                $expiredList[$itemKey]['time'] = $time;
                $expiredList[$itemKey]['ts'] = $itemTs;
                $expiredList[$itemKey]['in_period'] = $inPeriod;
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

// Select pool according to status filter & period
$selectedPool = array();
if ($statusFilter === 'active') {
    $selectedPool = array_values($activeList);
} elseif ($statusFilter === 'unused') {
    $selectedPool = array_values($unusedList);
} elseif ($statusFilter === 'expired') {
    if ($periodType !== 'all') {
        $selectedPool = array_values(array_filter($expiredList, function($item) {
            return !empty($item['in_period']);
        }));
    } else {
        $selectedPool = array_values($expiredList);
    }
} else {
    // All status: active + expired (filtered by period if not all) + unused
    $expFiltered = ($periodType !== 'all') 
        ? array_filter($expiredList, function($item) { return !empty($item['in_period']); })
        : $expiredList;

    $selectedPool = array_merge(
        array_values($activeList),
        array_values($expFiltered),
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
        $tsA = $a['ts'] ?? (strtotime($a['date'] . ' ' . $a['time']) ?: 0);
        $tsB = $b['ts'] ?? (strtotime($b['date'] . ' ' . $b['time']) ?: 0);
        return $tsB - $tsA;
    });
} elseif ($statusFilter === 'active') {
    usort($filteredItems, function($a, $b) {
        return strcmp($b['uptime'], $a['uptime']);
    });
} else {
    usort($filteredItems, function($a, $b) {
        $tsA = $a['ts'] ?? (strtotime($a['date'] . ' ' . $a['time']) ?: 0);
        $tsB = $b['ts'] ?? (strtotime($b['date'] . ' ' . $b['time']) ?: 0);
        return $tsB - $tsA;
    });
}

$totalFiltered = count($filteredItems);
$totalPages = ceil($totalFiltered / $limit);
$offset = ($page - 1) * $limit;
$slicedItems = array_slice($filteredItems, $offset, $limit);

$activeBreakdown = ($periodType !== 'all' && !empty($profileBreakdownPeriod)) 
    ? $profileBreakdownPeriod 
    : $profileBreakdownAll;

// Prepare time_series categories and data arrays for charts
$chartCategories = array();
$chartSeriesRevenue = array();
$chartSeriesCount = array();
foreach ($buckets as $b) {
    $chartCategories[] = $b['label'];
    $chartSeriesRevenue[] = $b['revenue'];
    $chartSeriesCount[] = $b['count'];
}

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
        // Card Omzet displays period revenue when filtered, or all-time when 'all'
        'total_revenue' => ($periodType !== 'all') ? $periodRevenue : $allTimeRevenue,
        'total_revenue_formatted' => 'Rp ' . number_format(($periodType !== 'all') ? $periodRevenue : $allTimeRevenue, 0, ',', '.'),
        'count_active' => $countActive,
        'count_unused' => $countUnused,
        'count_expired' => ($periodType !== 'all') ? $periodExpiredCount : $countExpired,
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
    'profile_breakdown' => array_values($activeBreakdown),
    'pagination' => array(
        'page' => $page,
        'limit' => $limit,
        'total_pages' => $totalPages,
        'total' => $totalFiltered
    ),
    'vouchers' => $slicedItems,
    'transactions' => $slicedItems
));
