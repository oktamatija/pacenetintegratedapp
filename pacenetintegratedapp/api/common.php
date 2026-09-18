<?php
/**
 * Pacenet Modern REST API - Common Helpers & Configuration
 */
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', '604800');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 604800,
            'path' => '/',
            'domain' => '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(604800, '/; samesite=Lax', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
    }
    session_start();
}

// Global JSON & CORS Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


error_reporting(0);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Jayapura');

require_once(__DIR__ . '/../lib/routeros_api.class.php');
require_once(__DIR__ . '/../lib/formatbytesbites.php');
include_once(__DIR__ . '/../include/config.php');

function jsonResponse($success, $data = array(), $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode(array(
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendJsonResponse($success, $data = array(), $message = '', $code = 200) {
    return jsonResponse($success, $data, $message, $code);
}

function getMikroTikApi($sessionName = '', $timeout = 3.5) {
    $conn = connectMikrotik($sessionName, $timeout);
    if ($conn && !empty($conn['api'])) {
        return array('success' => true, 'api' => $conn['api'], 'config' => $conn['config']);
    }
    return array('success' => false, 'error' => 'Gagal terhubung ke router API. Pastikan IP dan service API aktif.');
}

function checkAdminAuth($dieIfUnauthorized = true) {
    if (php_sapi_name() === 'cli') {
        return 'admin';
    }

    // Check session or basic authorization
    if (!empty($_SESSION['pacenet_user'])) {
        return $_SESSION['pacenet_user'];
    }
    if (!empty($_SESSION['mikhmon'])) {
        return $_SESSION['mikhmon'];
    }
    
    // Check Bearer token or authorization header or query token
    $token = $_REQUEST['token'] ?? '';
    $headers = function_exists('getallheaders') ? getallheaders() : array();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    
    if ($token === 'pacenet_session_demo') {
        return 'demo';
    }

    if ($token === 'pacenet_session_active' || !empty($_SESSION['pacenet_user']) || !empty($_SESSION['mikhmon'])) {
        return $_SESSION['pacenet_user'] ?? $_SESSION['mikhmon'] ?? 'admin';
    }

    if ($dieIfUnauthorized) {
        jsonResponse(false, null, 'Unauthorized access. Please login.', 401);
    }
    return false;
}

function isReadOnlyUser() {
    if (isset($_SESSION['is_readonly']) && $_SESSION['is_readonly'] === true) return true;
    if (isset($_SESSION['mikhmon']) && $_SESSION['mikhmon'] === 'demo') return true;
    if (isset($_SESSION['role']) && stripos($_SESSION['role'], 'demo') !== false) return true;

    $headers = function_exists('getallheaders') ? getallheaders() : array();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (stripos($authHeader, 'demo') !== false) return true;
    $roleHeader = $headers['X-User-Role'] ?? $headers['x-user-role'] ?? '';
    if (strtolower($roleHeader) === 'demo') return true;
    $token = $_REQUEST['token'] ?? '';
    if ($token === 'pacenet_session_demo') return true;

    return false;
}

function getUserRole() {
    return $_SESSION['role'] ?? 'admin';
}

function getUserName() {
    return $_SESSION['name'] ?? ($_SESSION['pacenet_user'] ?? 'User');
}

function getUserKiosk() {
    return $_SESSION['kiosk_name'] ?? '';
}

function requireRoles($allowedRoles = array()) {
    $currentRole = getUserRole();
    if (!in_array($currentRole, $allowedRoles)) {
        jsonResponse(false, null, 'Akses ditolak: Anda tidak memiliki izin untuk mengakses resource ini.', 403);
    }
}

function checkWritePermission() {
    if (isReadOnlyUser()) {
        jsonResponse(false, null, 'Akses Ditolak: Akun Demo hanya memiliki hak akses lihat (Read-Only). Perubahan atau modifikasi data dinonaktifkan.', 403);
    }
}

function getRouterConfig($sessionName = 'Rumah-DOLPHIN') {
    global $data;
    if (empty($data[$sessionName])) {
        // Fallback to first router available
        foreach ($data as $k => $v) {
            if ($k !== 'mikhmon' && !empty($v[1])) {
                $sessionName = $k;
                break;
            }
        }
    }
    if (empty($data[$sessionName])) return null;

    $cfg = $data[$sessionName];
    return array(
        'session' => $sessionName,
        'ip' => explode('!', $cfg[1] ?? '')[1] ?? '',
        'user' => explode('@|@', $cfg[2] ?? '')[1] ?? 'admin',
        'pass' => decrypt(explode('#|#', $cfg[3] ?? '')[1] ?? ''),
        'hotspot_name' => explode('%', $cfg[4] ?? '')[1] ?? $sessionName,
        'dns_name' => explode('^', $cfg[5] ?? '')[1] ?? 'hotspot.yunus',
        'currency' => explode('&', $cfg[6] ?? '')[1] ?? 'Rp'
    );
}

function connectMikrotik($sessionName = 'Rumah-DOLPHIN', $timeout = 3.0) {
    $cfg = getRouterConfig($sessionName);
    if (!$cfg || empty($cfg['ip'])) return null;

    // Fast socket pre-check (1000ms) to ensure router TCP port 8728 is alive before opening API
    $sock = @fsockopen($cfg['ip'], (int)($cfg['port'] ?? 8728), $errno, $errstr, 1.0);
    if (!$sock) {
        return null;
    }
    fclose($sock);

    $api = new RouterosAPI();
    $api->timeout = max(3.0, (float)$timeout);
    $api->attempts = 2;
    $api->delay = 1;
    $api->debug = false;

    if ($api->connect($cfg['ip'], $cfg['user'], $cfg['pass'])) {
        return array('api' => $api, 'config' => $cfg);
    }
    return null;
}

function formatBpsRate($bps, $precision = 1) {
    $units = array('bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps');
    $bps = max(floatval($bps), 0);
    $pow = ($bps > 0) ? floor(log($bps) / log(1000)) : 0;
    $pow = min($pow, count($units) - 1);
    $pow = max($pow, 0);
    $val = ($pow > 0) ? ($bps / pow(1000, $pow)) : $bps;
    return round($val, $precision) . ' ' . $units[$pow];
}

function formatBytesReadable($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $pow = floor(log($bytes, 1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

/**
 * Universal Bilingual (Indonesian & English) Duration Parser
 * Parses strings like '12 jam', '12 hours', '12h', '12j', '1 hari', '1 day', '1d', '30 menit', '30m', '1 minggu', '1w', '1 bulan', '1mo'
 * Returns normalized MikroTik string ('12h', '1d', '30m', '7d', '30d'), seconds (for RADIUS / timestamps), and human-friendly labels.
 */
function parseBilingualDuration($input) {
    $str = trim(strtolower((string)$input));
    if ($str === '' || $str === '0' || $str === 'none' || $str === '-') {
        return array(
            'valid' => false,
            'mikrotik' => '',
            'seconds' => 0,
            'human_id' => '-',
            'human_en' => '-',
            'days' => 0,
            'hours' => 0,
            'minutes' => 0
        );
    }

    $days = 0;
    $hours = 0;
    $minutes = 0;
    $matched = false;

    // Check HH:MM:SS format
    if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $str, $m)) {
        $hours = intval($m[1]);
        $minutes = intval($m[2]);
        $matched = true;
    } elseif (preg_match('/^(\d{1,2}):(\d{2})$/', $str, $m)) {
        $hours = intval($m[1]);
        $minutes = intval($m[2]);
        $matched = true;
    } else {
        // Months: bulan / bln / month(s) / mo -> convert to 30 days
        if (preg_match('/(\d+)\s*(?:bulan|bln|months?|mo)(?![a-z])/i', $str, $m)) {
            $days += intval($m[1]) * 30;
            $matched = true;
            $str = preg_replace('/(\d+)\s*(?:bulan|bln|months?|mo)(?![a-z])/i', ' ', $str);
        }

        // Weeks: minggu / mgg / week(s) / w
        if (preg_match('/(\d+)\s*(?:minggu|mgg|weeks?|w)(?![a-z])/i', $str, $m)) {
            $days += intval($m[1]) * 7;
            $matched = true;
            $str = preg_replace('/(\d+)\s*(?:minggu|mgg|weeks?|w)(?![a-z])/i', ' ', $str);
        }

        // Days: hari / hr / day(s) / d
        if (preg_match('/(\d+)\s*(?:hari|days?|d)(?![a-z])/i', $str, $m)) {
            $days += intval($m[1]);
            $matched = true;
            $str = preg_replace('/(\d+)\s*(?:hari|days?|d)(?![a-z])/i', ' ', $str);
        }

        // Hours: jam / jm / j / hour(s) / hrs? / h
        if (preg_match('/(\d+)\s*(?:jam|jm|hours?|hrs?|h|j)(?![a-z])/i', $str, $m)) {
            $hours += intval($m[1]);
            $matched = true;
            $str = preg_replace('/(\d+)\s*(?:jam|jm|hours?|hrs?|h|j)(?![a-z])/i', ' ', $str);
        }

        // Minutes: menit / mnt / minute(s) / min(s) / m
        if (preg_match('/(\d+)\s*(?:menit|mnt|minutes?|mins?|m)(?![a-z])/i', $str, $m)) {
            $minutes += intval($m[1]);
            $matched = true;
            $str = preg_replace('/(\d+)\s*(?:menit|mnt|minutes?|mins?|m)(?![a-z])/i', ' ', $str);
        }

        // If purely a number without unit, default to hours (e.g. "12" -> 12 hours)
        if (!$matched && preg_match('/^(\d+)$/', $str, $m)) {
            $hours = intval($m[1]);
            $matched = true;
        }
    }

    if (!$matched || ($days === 0 && $hours === 0 && $minutes === 0)) {
        return array(
            'valid' => false,
            'mikrotik' => '',
            'seconds' => 0,
            'human_id' => '-',
            'human_en' => '-',
            'days' => 0,
            'hours' => 0,
            'minutes' => 0
        );
    }

    // Build MikroTik duration string: e.g. 1d12h or 12h or 30m
    $mtParts = array();
    if ($days > 0) $mtParts[] = $days . 'd';
    if ($hours > 0) $mtParts[] = $hours . 'h';
    if ($minutes > 0) $mtParts[] = $minutes . 'm';
    $mtStr = implode('', $mtParts);

    // Calculate total seconds
    $totalSec = ($days * 86400) + ($hours * 3600) + ($minutes * 60);

    // Build Human ID & EN
    $idParts = array();
    $enParts = array();
    if ($days > 0) {
        if ($days % 30 === 0) {
            $mo = $days / 30;
            $idParts[] = $mo . ' Bulan';
            $enParts[] = $mo . ($mo > 1 ? ' Months' : ' Month');
        } elseif ($days % 7 === 0) {
            $wk = $days / 7;
            $idParts[] = $wk . ' Minggu';
            $enParts[] = $wk . ($wk > 1 ? ' Weeks' : ' Week');
        } else {
            $idParts[] = $days . ' Hari';
            $enParts[] = $days . ($days > 1 ? ' Days' : ' Day');
        }
    }
    if ($hours > 0) {
        $idParts[] = $hours . ' Jam';
        $enParts[] = $hours . ($hours > 1 ? ' Hours' : ' Hour');
    }
    if ($minutes > 0) {
        $idParts[] = $minutes . ' Menit';
        $enParts[] = $minutes . ($minutes > 1 ? ' Minutes' : ' Minute');
    }

    return array(
        'valid' => true,
        'mikrotik' => $mtStr,
        'seconds' => $totalSec,
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes,
        'human_id' => implode(' ', $idParts),
        'human_en' => implode(' ', $enParts)
    );
}

/**
 * Normalizes any bilingual or shorthand duration string into a valid MikroTik format (e.g. '12h', '1d', '30m', '7d')
 */
function normalizeMikrotikDuration($input, $default = '') {
    $parsed = parseBilingualDuration($input);
    return $parsed['valid'] ? $parsed['mikrotik'] : $default;
}

/**
 * Formats duration into human readable string in Indonesian or English
 */
function formatDurationHuman($input, $lang = 'id') {
    $parsed = parseBilingualDuration($input);
    if (!$parsed['valid']) return (string)$input;
    return ($lang === 'en') ? $parsed['human_en'] : $parsed['human_id'];
}

/**
 * Robustly parses MikroTik hotspot expiration timestamps from user comments.
 * Supports:
 * - mon/dd/yyyy hh:mm:ss (e.g. sep/16/2026 06:16:21)
 * - yyyy-mm-dd hh:mm:ss (e.g. 2026-09-16 07:19:10)
 * - dd/mm/yyyy hh:mm:ss (e.g. 16/09/2026 07:19:10)
 * Returns integer UNIX timestamp or false if not an expiration comment.
 */
function parseHotspotExpirationTimestamp($comment) {
    $comment = trim($comment);
    if (empty($comment)) return false;

    // 1. mon/dd/yyyy hh:mm:ss (e.g. sep/16/2026 06:16:21)
    if (preg_match('/([a-z]{3})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}:\d{2}:\d{2})/i', $comment, $m)) {
        $months = array('jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12);
        $mIdx = $months[strtolower($m[1])] ?? 1;
        $d = intval($m[2]);
        $y = intval($m[3]);
        $time = $m[4];
        $ts = strtotime(sprintf('%04d-%02d-%02d %s', $y, $mIdx, $d, $time));
        return $ts !== false ? $ts : false;
    }

    // 2. yyyy-mm-dd hh:mm:ss (e.g. 2026-09-16 07:19:10)
    if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d{1,2}:\d{2}:\d{2})/', $comment, $m)) {
        $ts = strtotime($m[0]);
        return $ts !== false ? $ts : false;
    }

    // 3. dd/mm/yyyy hh:mm:ss
    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}:\d{2}:\d{2})/', $comment, $m)) {
        $d = intval($m[1]);
        $mIdx = intval($m[2]);
        $y = intval($m[3]);
        $time = $m[4];
        $ts = strtotime(sprintf('%04d-%02d-%02d %s', $y, $mIdx, $d, $time));
        return $ts !== false ? $ts : false;
    }

    // 4. Fallback if standard parsable string (excluding batch codes like vc-xxx)
    if (strpos($comment, 'vc-') === false && strpos($comment, 'up-') === false && strpos($comment, 'pn-') === false) {
        $ts = strtotime($comment);
        if ($ts !== false && $ts > 946684800) { // Year 2000+
            return $ts;
        }
    }

    return false;
}

/**
 * Connect to PostgreSQL radius database (Pacenet Single Source of Truth)
 */
function getPgDb() {
    static $pg = null;
    if ($pg !== null) return $pg;
    if (!function_exists('pg_connect')) return null;
    $pg = @pg_connect("host=127.0.0.1 port=5432 dbname=radius user=radius password=RadiusPg2026");
    return $pg ?: null;
}
