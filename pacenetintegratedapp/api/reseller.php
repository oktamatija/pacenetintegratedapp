<?php
/**
 * Pacenet REST API - Reseller Kiosk Voucher Inspector & Marking
 * Features:
 * - Check voucher validity & usage across all routers
 * - Show detailed statistics (profile, validity, uptime, time left, IP, MAC)
 * - Show first login timestamp (kapan mulai digunakan)
 * - Mark voucher as sold (tandai kapan voucher terjual & nama kios)
 * - Retrieve kiosk sales history
 */
require_once(__DIR__ . '/common.php');
$currentUser = checkAdminAuth(true);
session_write_close();

global $data;

$dataFile = __DIR__ . '/../data/reseller_sales.json';

function loadSales($file) {
    if (!file_exists($file)) return array();
    $raw = @file_get_contents($file);
    $arr = json_decode($raw, true);
    return is_array($arr) ? $arr : array();
}

function saveSales($file, $sales) {
    @file_put_contents($file, json_encode($sales, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Helper to get connected routers
function getAvailableRouters() {
    global $data;
    $routers = array();
    foreach ($data as $sName => $sCfg) {
        if ($sName !== 'mikhmon' && !empty($sName) && strpos($sName, 'new-') !== 0) {
            $ip = explode('!', $sCfg[1] ?? '')[1] ?? '';
            $user = explode('@|@', $sCfg[2] ?? '')[1] ?? '';
            $pass = explode('#|#', $sCfg[3] ?? '')[1] ?? '';
            $name = explode('%', $sCfg[4] ?? '')[1] ?? $sName;
            $routers[$sName] = array(
                'session' => $sName,
                'name' => $name,
                'ip' => $ip,
                'user' => $user,
                'pass' => $pass
            );
        }
    }
    return $routers;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON body if sent
$rawBody = file_get_contents('php://input');
$body = array();
if (!empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

if (empty($action) && isset($body['action'])) {
    $action = $body['action'];
}

// 1. CHECK VOUCHER STATUS
if ($action === 'check' || ($method === 'GET' && empty($action) && isset($_GET['code']))) {
    $code = trim($_GET['code'] ?? $body['code'] ?? '');
    $filterRouter = trim($_GET['router'] ?? $body['router'] ?? 'all');

    if (empty($code)) {
        jsonResponse(false, null, 'Kode voucher wajib diisi.', 400);
    }

    // Clean code: if user scanned a URL like http://.../login?username=1234, extract username
    if (preg_match('/[?&]username=([^&]+)/i', $code, $m)) {
        $code = urldecode($m[1]);
    }

    $allRouters = getAvailableRouters();
    $targetRouters = array();
    if ($filterRouter !== 'all' && isset($allRouters[$filterRouter])) {
        $targetRouters[$filterRouter] = $allRouters[$filterRouter];
    } else {
        $targetRouters = $allRouters;
    }

    $foundVoucher = null;
    $salesDb = loadSales($dataFile);

    // Look for sold tag in reseller_sales.json
    $soldInfo = null;
    foreach ($salesDb as $s) {
        if (strtolower($s['code'] ?? '') === strtolower($code)) {
            $soldInfo = $s;
            break;
        }
    }

    // 1. Check PostgreSQL pacenet_vouchers first (Single Source of Truth)
    $pg = getPgDb();
    if ($pg) {
        $pRes = pg_query_params($pg, "SELECT * FROM pacenet_vouchers WHERE LOWER(username) = LOWER($1) LIMIT 1", array($code));
        if ($pRes && pg_num_rows($pRes) > 0) {
            $v = pg_fetch_assoc($pRes);
            $vProfile = $v['profile'];
            $vPrice = floatval($v['price']);
            $vValidity = $v['validity'] ?: '12h';
            $vStatus = $v['status'] ?: 'unused';
            $vUptime = $v['uptime'] ?: '0s';
            $vBytes = intval($v['bytes_in'] ?? 0) + intval($v['bytes_out'] ?? 0);
            $rOrigin = $v['router_origin'] ?: 'Pacenet Cloud';

            // Check if active on any connected router
            $isActive = false;
            $actObj = null;
            $activeRouter = $rOrigin;

            foreach ($targetRouters as $sName => $rCfg) {
                $rApi = new RouterosAPI();
                $rApi->timeout = 2;
                $rApi->attempts = 1;
                $rApi->debug = false;
                if ($rApi->connect($rCfg['ip'], $rCfg['user'], decrypt($rCfg['pass']))) {
                    $act = $rApi->comm('/ip/hotspot/active/print', array('?user' => $code));
                    if (is_array($act) && count($act) > 0) {
                        $isActive = true;
                        $actObj = $act[0];
                        $activeRouter = $rCfg['name'] ?? $sName;
                        $rApi->disconnect();
                        break;
                    }
                    $rApi->disconnect();
                }
            }

            if ($isActive) {
                $vStatus = 'active';
                $vUptime = $actObj['uptime'] ?? $vUptime;
                $vBytes = intval($actObj['bytes-in'] ?? 0) + intval($actObj['bytes-out'] ?? 0);
            }

            if ($vStatus === 'active') {
                $statusLabel = 'SEDANG DIGUNAKAN (User Aktif Online)';
            } elseif ($vStatus === 'disabled') {
                $statusLabel = 'DINONAKTIFKAN (Nonaktif)';
            } elseif ($vStatus === 'expired') {
                $statusLabel = 'SUDAH HABIS / KEDALUWARSA';
            } else {
                $statusLabel = 'VALID & BELUM TERPAKAI (Siap Dijual)';
            }

            $foundVoucher = array(
                'code' => $code,
                'router_session' => $filterRouter !== 'all' ? $filterRouter : 'all',
                'router_name' => $activeRouter,
                'status' => $vStatus,
                'status_label' => $statusLabel,
                'is_valid' => ($vStatus === 'unused'),
                'profile' => $vProfile,
                'price' => $vPrice,
                'price_formatted' => 'Rp ' . number_format($vPrice, 0, ',', '.'),
                'validity' => $vValidity,
                'uptime' => $vUptime,
                'session_left' => $actObj['session-time-left'] ?? '-',
                'ip' => $actObj['address'] ?? '-',
                'mac' => $actObj['mac-address'] ?? '-',
                'bytes_human' => formatBytesReadable($vBytes),
                'first_login_at' => $isActive ? 'Sedang aktif (Login hari ini)' : ($vUptime !== '0s' ? "Uptime tercatat: $vUptime" : 'Belum pernah login'),
                'sold_info' => $soldInfo
            );
        }
    }

    if (!$foundVoucher) {
        foreach ($targetRouters as $sName => $rCfg) {
            $rApi = new RouterosAPI();
            $rApi->timeout = 2;
            $rApi->attempts = 1;
            $rApi->debug = false;

            if (!$rApi->connect($rCfg['ip'], $rCfg['user'], decrypt($rCfg['pass']))) {
                continue;
            }

        // Check active session
        $act = $rApi->comm('/ip/hotspot/active/print', array('?user' => $code));
        $isActive = (is_array($act) && count($act) > 0);

        // Check user in hotspot database
        $uList = $rApi->comm('/ip/hotspot/user/print', array('?name' => $code));
        $hasUser = (is_array($uList) && count($uList) > 0);

        // Check sales script (mikhmon script)
        $scripts = $rApi->comm('/system/script/print', array('?comment' => 'mikhmon'));
        $scriptMatch = null;
        if (is_array($scripts)) {
            foreach ($scripts as $sc) {
                $scName = $sc['name'] ?? '';
                if (strpos($scName, "-|-$code-|-") !== false) {
                    $scriptMatch = $sc;
                    break;
                }
            }
        }

        // Get profiles for price/validity lookup
        $profiles = $rApi->comm('/ip/hotspot/user/profile/print');
        $profLookup = array();
        if (is_array($profiles)) {
            foreach ($profiles as $p) {
                $pName = $p['name'] ?? '';
                $onlogin = $p['on-login'] ?? '';
                $price = 0;
                $validity = '';
                if (preg_match('/,\s*([^,]*)\s*,\s*([0-9]*)\s*,\s*([^,]*)\s*,\s*([0-9]*)/', $onlogin, $m)) {
                    $price = intval($m[2] ?: 0);
                    $validity = trim($m[3] ?: '');
                }
                $profLookup[$pName] = array('price' => $price, 'validity' => $validity);
            }
        }

        $rApi->disconnect();

        if ($hasUser || $isActive || $scriptMatch) {
            $userObj = $hasUser ? $uList[0] : null;
            $actObj = $isActive ? $act[0] : null;

            $pName = $userObj['profile'] ?? ($actObj['profile'] ?? 'default');
            $price = $profLookup[$pName]['price'] ?? 0;
            $validity = $profLookup[$pName]['validity'] ?? ($userObj['limit-uptime'] ?? 'Standar');

            $uptime = $actObj['uptime'] ?? ($userObj['uptime'] ?? '0s');
            $bytesTotal = intval($actObj['bytes-in'] ?? 0) + intval($actObj['bytes-out'] ?? 0);

            // Determine status
            $status = 'unused';
            $statusLabel = 'VALID & BELUM TERPAKAI (Siap Dijual)';
            $firstLoginAt = null;

            if ($scriptMatch) {
                $parts = explode('-|-', $scriptMatch['name'] ?? '');
                $sDate = $parts[0] ?? '';
                $sTime = $parts[1] ?? '';
                $firstLoginAt = "$sDate $sTime";
                if (!$price && isset($parts[3])) $price = floatval($parts[3]);
                if (isset($parts[6]) && !empty($parts[6])) $validity = $parts[6];
                if (isset($parts[7]) && !empty($parts[7])) $pName = $parts[7];
            }

            if ($isActive) {
                $status = 'active';
                $statusLabel = 'SEDANG DIGUNAKAN (User Aktif Online)';
            } elseif ($hasUser) {
                $comment = $userObj['comment'] ?? '';
                $expTs = parseHotspotExpirationTimestamp($comment);

                if ($expTs !== false && time() >= $expTs) {
                    $status = 'expired';
                    $statusLabel = 'SUDAH HABIS / KEDALUWARSA';
                } elseif ($uptime !== '0s' && !empty($uptime)) {
                    $status = 'active';
                    $statusLabel = 'SEDANG BERJALAN (Sesi Aktif Offline)';
                } else {
                    $status = 'unused';
                    $statusLabel = 'VALID & BELUM TERPAKAI (Siap Dijual)';
                }
            } else {
                $status = 'expired';
                $statusLabel = 'SUDAH HABIS / KEDALUWARSA';
            }

            $foundVoucher = array(
                'code' => $code,
                'router_session' => $sName,
                'router_name' => $rCfg['name'],
                'status' => $status,
                'status_label' => $statusLabel,
                'is_valid' => ($status === 'unused'),
                'profile' => $pName,
                'price' => $price,
                'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
                'validity' => $validity,
                'uptime' => $uptime,
                'session_left' => $actObj['session-time-left'] ?? ($userObj['limit-uptime'] ?? '-'),
                'ip' => $actObj['address'] ?? '-',
                'mac' => $actObj['mac-address'] ?? '-',
                'bytes_human' => formatBytesReadable($bytesTotal),
                'first_login_at' => $firstLoginAt ?: ($isActive ? 'Sedang aktif (Login hari ini)' : ($uptime !== '0s' ? "Uptime tercatat: $uptime" : 'Belum pernah login')),
                'sold_info' => $soldInfo
            );
            break;
        }
    }
    }

    if ($foundVoucher) {
        jsonResponse(true, $foundVoucher, 'Data voucher ditemukan.');
    } else {
        jsonResponse(true, array(
            'code' => $code,
            'status' => 'not_found',
            'status_label' => 'KODE TIDAK DITEMUKAN / TIDAK VALID',
            'is_valid' => false,
            'message' => 'Kode voucher ini tidak terdaftar di router manapun atau sudah terhapus permanen.',
            'sold_info' => $soldInfo
        ), 'Voucher tidak ditemukan.', 200);
    }
}

// 2. MARK VOUCHER AS SOLD
if ($action === 'mark_sold' || ($method === 'POST' && $action === 'mark_sold')) {
    $code = trim($body['code'] ?? '');
    $router = trim($body['router'] ?? '');
    $kioskName = trim($body['kiosk_name'] ?? '');
    $price = floatval($body['price'] ?? 0);
    $notes = trim($body['notes'] ?? '');

    if (empty($code)) {
        jsonResponse(false, null, 'Kode voucher wajib diisi.', 400);
    }

    $salesDb = loadSales($dataFile);

    // Check if already marked
    $found = false;
    for ($i = 0; $i < count($salesDb); $i++) {
        if (strtolower($salesDb[$i]['code'] ?? '') === strtolower($code)) {
            $salesDb[$i]['sold_at'] = date('Y-m-d H:i:s');
            $salesDb[$i]['kiosk_user'] = $currentUser;
            $salesDb[$i]['kiosk_name'] = !empty($kioskName) ? $kioskName : ($salesDb[$i]['kiosk_name'] ?? 'Kios Reseller');
            if ($price > 0) $salesDb[$i]['price'] = $price;
            $salesDb[$i]['notes'] = $notes;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $salesDb[] = array(
            'id' => 'sale_' . substr(md5(uniqid($code, true)), 0, 8),
            'code' => $code,
            'router' => $router,
            'kiosk_user' => $currentUser,
            'kiosk_name' => !empty($kioskName) ? $kioskName : 'Kios Reseller',
            'sold_at' => date('Y-m-d H:i:s'),
            'price' => $price,
            'price_formatted' => 'Rp ' . number_format($price, 0, ',', '.'),
            'notes' => $notes
        );
    }

    saveSales($dataFile, $salesDb);

    jsonResponse(true, array(
        'code' => $code,
        'sold_at' => date('Y-m-d H:i:s'),
        'kiosk_user' => $currentUser,
        'kiosk_name' => $kioskName
    ), "Voucher '$code' berhasil ditandai sebagai TERJUAL.");
}

// 3. GET MY KIOSK SALES
if ($action === 'my_sales') {
    $salesDb = loadSales($dataFile);
    $mySales = array();
    $todayStr = date('Y-m-d');
    $todayCount = 0;
    $todayRevenue = 0;
    $totalRevenue = 0;

    foreach ($salesDb as $s) {
        $sUser = $s['kiosk_user'] ?? '';
        // If logged in as reseller or admin, match user
        if ($sUser === $currentUser || in_array($currentUser, array('owner', 'admin', 'manager'))) {
            $mySales[] = $s;
            $soldAt = $s['sold_at'] ?? '';
            $pr = floatval($s['price'] ?? 0);
            $totalRevenue += $pr;
            if (strpos($soldAt, $todayStr) === 0) {
                $todayCount++;
                $todayRevenue += $pr;
            }
        }
    }

    // Sort newest first
    usort($mySales, function($a, $b) {
        return strcmp($b['sold_at'] ?? '', $a['sold_at'] ?? '');
    });

    jsonResponse(true, array(
        'sales' => array_slice($mySales, 0, 50),
        'summary' => array(
            'today_count' => $todayCount,
            'today_revenue' => $todayRevenue,
            'today_revenue_formatted' => 'Rp ' . number_format($todayRevenue, 0, ',', '.'),
            'total_count' => count($mySales),
            'total_revenue' => $totalRevenue,
            'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
        )
    ));
}

jsonResponse(false, null, 'Aksi tidak valid.', 400);
