<?php
/**
 * Pacenet REST API - Zero-Touch Onboarding & Router Management
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

$dataFile = '/var/www/pacenetintegratedapp/data/pending_routers.json';
$configFile = '/var/www/pacenetintegratedapp/include/config.php';
$serverPubIp = '202.10.46.222';
$bootstrapCmd = '/tool fetch url="http://202.10.46.222/join.php?action=bootstrap" mode=http dst-path=join.rsc; :delay 2s; /import join.rsc; /file remove join.rsc';

function loadPendingList($file) {
    if (!file_exists($file)) return array();
    $c = @file_get_contents($file);
    return json_decode($c, true) ?: array();
}

function checkPortFast($ip, $port = 8728, $timeout = 0.3) {
    if (empty($ip)) return false;
    $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');
$body = !empty($rawBody) ? json_decode($rawBody, true) : array();
$action = $_GET['action'] ?? ($body['action'] ?? 'info');

// 1. GET ONBOARDING INFO (BOOTSTRAP CMD, PENDING ROUTERS, REGISTERED ROUTERS)
if ($action === 'info' || ($method === 'GET' && empty($_GET['action']))) {
    $rawPending = loadPendingList($dataFile);
    $pendingRouters = array();

    foreach ($rawPending as $p) {
        $ip = $p['vpn_ip'] ?? '';
        $isOnline = checkPortFast($ip, 8728, 0.35);
        $pendingRouters[] = array(
            'identity' => $p['identity'] ?? 'MikroTik',
            'original_name' => $p['original_name'] ?? ($p['identity'] ?? 'MikroTik'),
            'vpn_ip' => $ip,
            'winbox_port' => intval($p['winbox_port'] ?? 0),
            'winbox_addr' => $serverPubIp . ':' . intval($p['winbox_port'] ?? 0),
            'model' => $p['model'] ?? 'RouterBOARD',
            'version' => $p['version'] ?? 'RouterOS',
            'online' => $isOnline,
            'timestamp' => $p['timestamp'] ?? date('Y-m-d H:i:s'),
            'pubkey' => $p['pubkey'] ?? ''
        );
    }

    // Read registered routers
    $registered = array();
    foreach ($data as $sessName => $cfg) {
        if ($sessName === 'mikhmon' || empty($sessName) || strpos($sessName, 'new-') === 0) continue;
        $ip = explode('!', $cfg[1] ?? '')[1] ?? '';
        $hsName = explode('%', $cfg[4] ?? '')[1] ?? $sessName;
        $registered[] = array(
            'session' => $sessName,
            'hotspot_name' => $hsName,
            'ip' => $ip,
            'online' => checkPortFast($ip, 8728, 0.35)
        );
    }

    jsonResponse(true, array(
        'bootstrap_command' => $bootstrapCmd,
        'server_ip' => $serverPubIp,
        'pending_routers' => $pendingRouters,
        'registered_routers' => $registered
    ));
}

// Guard mutating actions against read-only demo user
if (in_array($action, array('accept', 'reject', 'delete_pending', 'delete'))) {
    checkWritePermission();
}

// 2. ACCEPT ROUTER (FORWARD TO PROVEN ACCEPT ENGINE)
if ($action === 'accept') {
    $identity = trim($body['identity'] ?? $_POST['identity'] ?? '');
    $vpnIp = trim($body['vpn_ip'] ?? $_POST['vpn_ip'] ?? '');
    $user = trim($body['user'] ?? $_POST['user'] ?? 'admin');
    $pass = trim($body['pass'] ?? $_POST['pass'] ?? '');
    $hsIface = trim($body['hs_iface'] ?? $_POST['hs_iface'] ?? 'Vlan1');
    $winboxPort = intval($body['winbox_port'] ?? $_POST['winbox_port'] ?? 0);

    if (empty($identity) || empty($vpnIp)) {
        jsonResponse(false, null, 'Identity dan IP VPN wajib diisi', 400);
    }

    // Call accept_router.php internally via cURL or include
    $postFields = http_build_query(array(
        'action' => 'accept',
        'identity' => $identity,
        'vpn_ip' => $vpnIp,
        'user' => $user,
        'pass' => $pass,
        'hs_iface' => $hsIface,
        'winbox_port' => $winboxPort
    ));

    $ch = curl_init('https://hy0045.my.id/settings/accept_router.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $jsonResp = json_decode($resp, true);
    if ($jsonResp && isset($jsonResp['status'])) {
        if ($jsonResp['status'] === 'success') {
            jsonResponse(true, $jsonResp, $jsonResp['message'] ?? 'Router berhasil diterima dan dikonfigurasi otomatis!');
        } else {
            jsonResponse(false, $jsonResp, $jsonResp['message'] ?? 'Gagal menerima router', 400);
        }
    }

    jsonResponse(false, array('raw' => $resp), 'Respons tidak valid dari accept router engine', 500);
}

// 3. REJECT PENDING ROUTER
if ($action === 'reject') {
    $identity = trim($body['identity'] ?? $_POST['identity'] ?? '');
    $vpnIp = trim($body['vpn_ip'] ?? $_POST['vpn_ip'] ?? '');

    $postFields = http_build_query(array(
        'action' => 'reject',
        'identity' => $identity,
        'vpn_ip' => $vpnIp
    ));

    $ch = curl_init('https://hy0045.my.id/settings/accept_router.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    $resp = curl_exec($ch);
    curl_close($ch);

    $jsonResp = json_decode($resp, true);
    jsonResponse(true, $jsonResp, "Router {$identity} berhasil ditolak.");
}

// 4. DELETE REGISTERED ROUTER FROM CONFIG.PHP
if ($action === 'delete_registered') {
    $session = trim($body['session'] ?? $_POST['session'] ?? '');
    if (empty($session) || $session === 'mikhmon') {
        jsonResponse(false, null, 'Session tidak valid', 400);
    }

    $lines = file($configFile);
    $newLines = array();
    $deleted = false;

    foreach ($lines as $line) {
        if (strpos($line, "\$data['$session']") !== false) {
            $deleted = true;
            continue;
        }
        $newLines[] = $line;
    }

    if ($deleted) {
        file_put_contents($configFile, implode('', $newLines));
        jsonResponse(true, array('session' => $session), "Router {$session} berhasil dihapus dari sistem.");
    } else {
        jsonResponse(false, null, "Sesi {$session} tidak ditemukan di konfigurasi.", 404);
    }
}

jsonResponse(false, null, 'Invalid action', 400);
