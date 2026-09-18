<?php
/**
 * Pacenet REST API - Zero-Touch Onboarding & Router Management
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

$dataFile = '/var/www/pacenetintegratedapp/data/pending_routers.json';
$configFile = '/var/www/pacenetintegratedapp/include/config.php';
$serverPubIp = '202.10.47.76';
$bootstrapCmd = '/tool fetch url="http://' . $serverPubIp . ':8080/join.php" mode=http dst-path=join.rsc; :delay 2s; /import join.rsc; /file remove join.rsc';

function loadPendingList($file) {
    if (!file_exists($file)) return array();
    $c = @file_get_contents($file);
    return json_decode($c, true) ?: array();
}

/**
 * Multi-layer router online verification:
 * 1. Port 8728 (RouterOS API)
 * 2. Port 8291 (Winbox)
 * 3. ICMP Ping over WireGuard tunnel
 * 4. WireGuard kernel peer handshake within 90 seconds
 */
function checkRouterOnline($ip, $pubkey = '') {
    if (empty($ip)) return false;

    // 1. Port 8728 (API) fast check
    $fp = @fsockopen($ip, 8728, $errno, $errstr, 0.8);
    if ($fp) {
        fclose($fp);
        return true;
    }

    // 2. Port 8291 (Winbox) fast check
    $fp = @fsockopen($ip, 8291, $errno, $errstr, 0.8);
    if ($fp) {
        fclose($fp);
        return true;
    }

    // 3. Fast ICMP Ping over WireGuard tunnel
    $pOut = array();
    $pRet = 1;
    @exec("ping -c 1 -W 1 " . escapeshellarg($ip) . " 2>/dev/null", $pOut, $pRet);
    if ($pRet === 0) {
        return true;
    }

    // 4. WireGuard handshake check
    if (!empty($pubkey)) {
        $hs = @shell_exec("sudo /usr/bin/wg show wg0 latest-handshakes 2>/dev/null | grep " . escapeshellarg($pubkey));
        if ($hs && preg_match("/\s+([0-9]+)$/", trim($hs), $m)) {
            $lastHs = intval($m[1]);
            if ($lastHs > 0 && (time() - $lastHs) <= 90) {
                return true;
            }
        }
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
    $updatedPendingData = array();
    $dataModified = false;
    $now = time();

    foreach ($rawPending as $p) {
        $ip = $p['vpn_ip'] ?? '';
        $pub = $p['pubkey'] ?? '';
        $createdAtStr = $p['created_at'] ?? $p['timestamp'] ?? '';
        $createdTime = !empty($createdAtStr) ? strtotime($createdAtStr) : $now;
        $ageSeconds = $now - $createdTime;
        $isOnline = checkRouterOnline($ip, $pub);

        // ATURAN 1 MENIT: Jika dalam 1 menit router tidak masuk / offline,
        // maka harus dikeluarkan dari daftar pending onboarding agar tidak menumpuk!
        if ($ageSeconds >= 60 && !$isOnline) {
            // Bersihkan peer dari WireGuard wg0
            if (!empty($pub)) {
                @shell_exec("sudo /usr/bin/wg set wg0 peer " . escapeshellarg($pub) . " remove 2>/dev/null");
            } elseif (!empty($ip)) {
                @shell_exec("sudo /usr/bin/wg set wg0 peer $(wg show wg0 allowed-ips 2>/dev/null | grep '{$ip}/32' | awk '{print $1}') remove 2>/dev/null");
            }
            $dataModified = true;
            continue; // Skip dan jangan tampilkan di list pending!
        }

        // Router masih dalam batas 60 detik atau sudah aktif terhubung
        $expiresIn = max(0, 60 - $ageSeconds);
        $p['online'] = $isOnline;
        $updatedPendingData[] = $p;

        $pendingRouters[] = array(
            'identity' => $p['identity'] ?? 'MikroTik',
            'original_name' => $p['original_name'] ?? ($p['identity'] ?? 'MikroTik'),
            'vpn_ip' => $ip,
            'winbox_port' => intval($p['winbox_port'] ?? 0),
            'winbox_addr' => $serverPubIp . ':' . intval($p['winbox_port'] ?? 0),
            'model' => $p['model'] ?? 'RouterBOARD',
            'version' => $p['version'] ?? 'RouterOS',
            'online' => $isOnline,
            'created_at' => $createdAtStr,
            'age_seconds' => $ageSeconds,
            'expires_in' => $expiresIn,
            'timestamp' => $p['timestamp'] ?? $createdAtStr,
            'pubkey' => $pub
        );
    }

    if ($dataModified) {
        @file_put_contents($dataFile, json_encode($updatedPendingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @shell_exec("sudo /usr/bin/wg-quick save wg0 2>/dev/null");
    }

    // Read registered routers
    $registered = array();
    foreach ($data as $sessName => $cfg) {
        if ($sessName === 'mikhmon' || empty($sessName) || strpos($sessName, 'new-') === 0) continue;
        $ip = explode('!', $cfg[1] ?? '')[1] ?? '';
        $user = explode('@|@', $cfg[2] ?? '')[1] ?? 'admin';
        $hsName = explode('%', $cfg[4] ?? '')[1] ?? $sessName;
        $dnsName = explode('^', $cfg[5] ?? '')[1] ?? 'hotspot.yunus';
        $currency = explode('&', $cfg[6] ?? '')[1] ?? 'Rp';
        $registered[] = array(
            'session' => $sessName,
            'hotspot_name' => $hsName,
            'ip' => $ip,
            'user' => $user,
            'dns_name' => $dnsName,
            'currency' => $currency,
            'online' => checkRouterOnline($ip)
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
if (in_array($action, array('accept', 'reject', 'delete_pending', 'delete', 'delete_registered', 'delete_router', 'update_credentials', 'edit_router'))) {
    checkWritePermission();
}

// 2. ACCEPT ROUTER & AUTO-CONFIGURATION (RADIUS & HOTSPOT SETUP)
if ($action === 'accept') {
    $identity = trim($body['identity'] ?? $_POST['identity'] ?? '');
    $vpnIp = trim($body['vpn_ip'] ?? $_POST['vpn_ip'] ?? '');
    $user = trim($body['user'] ?? $_POST['user'] ?? 'admin');
    $pass = (string)($body['pass'] ?? $_POST['pass'] ?? '');
    $hsIface = trim($body['hs_iface'] ?? $_POST['hs_iface'] ?? 'Vlan1');
    $winboxPort = intval($body['winbox_port'] ?? $_POST['winbox_port'] ?? 0);

    if (empty($identity) || empty($vpnIp)) {
        jsonResponse(false, null, 'Identity dan IP VPN wajib diisi', 400);
    }

    $cleanSession = preg_replace('/[^a-zA-Z0-9_\-]/', '', $identity);
    if (empty($cleanSession)) $cleanSession = 'MikroTik-' . substr(str_replace('.', '', $vpnIp), -3);

    // Test API connection and push automated configuration to MikroTik
    $tApi = new RouterosAPI();
    $tApi->timeout = 4;
    $tApi->attempts = 1;
    $tApi->debug = false;
    $tApi->port = 8728;
    $hotspotName = $cleanSession;
    $dnsName = 'hotspot.yunus';
    $autoConfigured = false;
    $configDetails = array();

    if ($tApi->connect($vpnIp, $user, $pass)) {
        // Step A: Fetch actual system identity
        $identRes = $tApi->comm('/system/identity/print');
        if (!empty($identRes[0]['name'])) {
            $hotspotName = $identRes[0]['name'];
        }

        // Step B: Automatically configure FreeRADIUS Client (10.10.10.1)
        $radList = $tApi->comm('/radius/print');
        $radFound = false;
        if (is_array($radList)) {
            foreach ($radList as $rItem) {
                if (($rItem['address'] ?? '') === '10.10.10.1') {
                    $radFound = true;
                    $tApi->comm('/radius/set', array(
                        '.id' => $rItem['.id'],
                        'secret' => 'YunusRadius2026!',
                        'service' => 'hotspot',
                        'timeout' => '3s',
                        'comment' => 'Pacenet-Cloud-Radius'
                    ));
                    $configDetails[] = 'RADIUS Client (10.10.10.1) disinkronkan';
                    break;
                }
            }
        }
        if (!$radFound) {
            $tApi->comm('/radius/add', array(
                'address' => '10.10.10.1',
                'secret' => 'YunusRadius2026!',
                'service' => 'hotspot',
                'timeout' => '3s',
                'comment' => 'Pacenet-Cloud-Radius'
            ));
            $configDetails[] = 'RADIUS Client (10.10.10.1) berhasil dibuat';
        }

        // Step C: Automatically configure Hotspot Profiles (enable RADIUS)
        $hsProfiles = $tApi->comm('/ip/hotspot/profile/print');
        if (is_array($hsProfiles) && count($hsProfiles) > 0) {
            foreach ($hsProfiles as $prof) {
                if (!empty($prof['.id'])) {
                    $tApi->comm('/ip/hotspot/profile/set', array(
                        '.id' => $prof['.id'],
                        'use-radius' => 'yes',
                        'radius-accounting' => 'yes',
                        'radius-interim-update' => '1m'
                    ));
                }
                if (!empty($prof['dns-name'])) {
                    $dnsName = $prof['dns-name'];
                }
            }
            $configDetails[] = 'Hotspot Profile (use-radius=yes) aktif';
        }

        // Step D: Ensure API and WWW management services are active
        $tApi->comm('/ip/service/set', array('.id' => 'api', 'disabled' => 'no'));
        $tApi->comm('/ip/service/set', array('.id' => 'www', 'disabled' => 'no'));

        $autoConfigured = true;
        $tApi->disconnect();
    } else {
        $configDetails[] = 'Catatan: API MikroTik belum dapat diakses langsung dengan kredensial ini. Router tetap didaftarkan.';
    }

    $encPass = encrypt($pass, 128);
    $newLine = "\$data['{$cleanSession}'] = array ('1'=>'{$cleanSession}!{$vpnIp}','{$cleanSession}@|@{$user}','{$cleanSession}#|#{$encPass}','{$cleanSession}%{$hotspotName}','{$cleanSession}^{$dnsName}','{$cleanSession}&Rp','{$cleanSession}*10','{$cleanSession}(1','{$cleanSession})','{$cleanSession}=10','{$cleanSession}@!@disable');\n";

    // Write to config.php in both pacenetintegratedapp and mikhmon
    $cfgPaths = array(
        '/var/www/pacenetintegratedapp/include/config.php',
        '/var/www/mikhmon/include/config.php',
        __DIR__ . '/../include/config.php'
    );

    foreach (array_unique($cfgPaths) as $cfgFile) {
        if (!file_exists($cfgFile)) continue;
        $content = file_get_contents($cfgFile);
        $pattern = "/\\\$data\['" . preg_quote($cleanSession, '/') . "'\]\s*=\s*array\s*\([^;]+\);\s*/";
        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, $newLine, $content, 1);
        } else {
            $newContent = rtrim($content) . "\n\n" . $newLine;
        }
        @file_put_contents($cfgFile, $newContent);
    }

    // Remove from pending_routers.json
    $pending = loadPendingList($dataFile);
    $updatedPending = array();
    foreach ($pending as $p) {
        if (($p['identity'] ?? '') !== $identity && ($p['vpn_ip'] ?? '') !== $vpnIp) {
            $updatedPending[] = $p;
        }
    }
    @file_put_contents($dataFile, json_encode($updatedPending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    @shell_exec("sudo /usr/bin/wg-quick save wg0 2>/dev/null");

    $msg = "Router '{$cleanSession}' ({$vpnIp}) berhasil diterima! " . implode('. ', $configDetails);

    jsonResponse(true, array(
        'session' => $cleanSession,
        'ip' => $vpnIp,
        'hotspot_name' => $hotspotName,
        'auto_configured' => $autoConfigured,
        'details' => $configDetails
    ), $msg);
}

// 3. REJECT PENDING ROUTER
if ($action === 'reject') {
    $identity = trim($body['identity'] ?? $_POST['identity'] ?? '');
    $vpnIp = trim($body['vpn_ip'] ?? $_POST['vpn_ip'] ?? '');

    $pending = loadPendingList($dataFile);
    $updatedPending = array();
    $rejectedPub = '';

    foreach ($pending as $p) {
        if ((!empty($identity) && ($p['identity'] ?? '') === $identity) || 
            (!empty($vpnIp) && ($p['vpn_ip'] ?? '') === $vpnIp)) {
            $rejectedPub = $p['pubkey'] ?? '';
            continue;
        }
        $updatedPending[] = $p;
    }

    @file_put_contents($dataFile, json_encode($updatedPending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Clean WireGuard peer from wg0
    if (!empty($rejectedPub)) {
        @shell_exec("sudo /usr/bin/wg set wg0 peer " . escapeshellarg($rejectedPub) . " remove 2>/dev/null");
        @shell_exec("sudo /usr/bin/wg-quick save wg0 2>/dev/null");
    } elseif (!empty($vpnIp)) {
        @shell_exec("sudo /usr/bin/wg set wg0 peer $(wg show wg0 allowed-ips 2>/dev/null | grep '{$vpnIp}/32' | awk '{print $1}') remove 2>/dev/null");
        @shell_exec("sudo /usr/bin/wg-quick save wg0 2>/dev/null");
    }

    jsonResponse(true, array('identity' => $identity, 'vpn_ip' => $vpnIp), "Permintaan router '{$identity}' ({$vpnIp}) berhasil ditolak dan dibersihkan dari VPN.");
}

// 4. DELETE REGISTERED ROUTER FROM CONFIG.PHP
if ($action === 'delete_registered' || $action === 'delete_router' || $action === 'delete') {
    $session = trim($body['session'] ?? $_POST['session'] ?? '');
    if (empty($session) || strtolower($session) === 'mikhmon') {
        jsonResponse(false, null, 'Sesi tidak valid', 400);
    }

    $cfgPaths = array(
        '/var/www/pacenetintegratedapp/include/config.php',
        '/var/www/mikhmon/include/config.php',
        __DIR__ . '/../include/config.php'
    );

    $deleted = false;
    $deletedIp = '';

    foreach (array_unique($cfgPaths) as $cfgFile) {
        if (!file_exists($cfgFile)) continue;
        $lines = file($cfgFile);
        $newLines = array();

        foreach ($lines as $line) {
            if (preg_match("/\\\$data\['" . preg_quote($session, '/') . "'\]/i", $line) || 
                preg_match("/\\\$data\[\"" . preg_quote($session, '/') . "\"\]/i", $line)) {
                $deleted = true;
                if (preg_match("/!([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/", $line, $ipM)) {
                    $deletedIp = $ipM[1];
                }
                continue;
            }
            $newLines[] = $line;
        }

        if ($deleted) {
            @file_put_contents($cfgFile, implode('', $newLines));
        }
    }

    // Clean peer from WireGuard
    if (!empty($deletedIp)) {
        @shell_exec("sudo /usr/bin/wg set wg0 peer $(wg show wg0 allowed-ips 2>/dev/null | grep '{$deletedIp}/32' | awk '{print $1}') remove 2>/dev/null");
        @shell_exec("sudo /usr/bin/wg-quick save wg0 2>/dev/null");
    }

    // Also clean from pending_routers.json if exists
    $pending = loadPendingList($dataFile);
    $updatedPending = array();
    $pChanged = false;
    foreach ($pending as $p) {
        if (($p['identity'] ?? '') === $session || ($p['vpn_ip'] ?? '') === $deletedIp) {
            $pChanged = true;
            continue;
        }
        $updatedPending[] = $p;
    }
    if ($pChanged) {
        @file_put_contents($dataFile, json_encode($updatedPending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    jsonResponse(true, array('session' => $session), "Sesi router '{$session}' berhasil dihapus dari sistem.");
}

// 5. UPDATE CREDENTIALS / EDIT ROUTER
if ($action === 'update_credentials' || $action === 'edit_router' || $action === 'edit') {
    $session = trim($body['session'] ?? $_POST['session'] ?? '');
    $newSession = trim($body['new_session'] ?? $session);
    $newSession = preg_replace('/[^a-zA-Z0-9_\-]/', '', $newSession);
    $ip = trim($body['ip'] ?? $_POST['ip'] ?? '');
    $user = trim($body['user'] ?? $_POST['user'] ?? 'admin');
    $pass = (string)($body['pass'] ?? $_POST['pass'] ?? '');
    $hsName = trim($body['hotspot_name'] ?? $_POST['hotspot_name'] ?? $newSession);
    $dnsName = trim($body['dns_name'] ?? $_POST['dns_name'] ?? 'hotspot.yunus');
    $currency = trim($body['currency'] ?? $_POST['currency'] ?? 'Rp');

    if (empty($session) || empty($ip)) {
        jsonResponse(false, null, 'Session name dan IP router wajib diisi.', 400);
    }

    $cfgPaths = array(
        '/var/www/pacenetintegratedapp/include/config.php',
        '/var/www/mikhmon/include/config.php',
        __DIR__ . '/../include/config.php'
    );

    $oldCfg = $data[$session] ?? null;
    if (!$oldCfg) {
        foreach ($data as $k => $v) {
            if (strtolower($k) === strtolower($session)) {
                $oldCfg = $v;
                $session = $k;
                break;
            }
        }
    }

    if (!$oldCfg) {
        jsonResponse(false, null, "Sesi router '{$session}' tidak ditemukan dalam config.", 404);
    }

    if ($pass === '' || $pass === null) {
        $oldEncPass = explode('#|#', $oldCfg[3] ?? '')[1] ?? '';
    } else {
        $oldEncPass = encrypt($pass, 128);
    }

    $autoReload = explode('*', $oldCfg[7] ?? '')[1] ?? '10';
    $idleTimeout = explode('(', $oldCfg[8] ?? '')[1] ?? '1';
    $liveTraffic = explode(')', $oldCfg[9] ?? '')[1] ?? '';
    $trafficInt = explode('=', $oldCfg[10] ?? '')[1] ?? '10';
    $telegram = explode('@!@', $oldCfg[11] ?? '')[1] ?? 'disable';

    $newLine = "\$data['{$newSession}'] = array ('1'=>'{$newSession}!{$ip}','{$newSession}@|@{$user}','{$newSession}#|#{$oldEncPass}','{$newSession}%{$hsName}','{$newSession}^{$dnsName}','{$newSession}&{$currency}','{$newSession}*{$autoReload}','{$newSession}({$idleTimeout}','{$newSession}){$liveTraffic}','{$newSession}={$trafficInt}','{$newSession}@!@{$telegram}');";

    foreach (array_unique($cfgPaths) as $cfgFile) {
        if (!file_exists($cfgFile)) continue;
        $content = file_get_contents($cfgFile);
        $pattern = "/\\\$data\[['\"]" . preg_quote($session, '/') . "['\"]\]\s*=\s*array\s*\([^;]+\);/i";
        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, $newLine, $content, 1);
        } else {
            $newContent = rtrim($content) . "\n\n" . $newLine . "\n";
        }
        file_put_contents($cfgFile, $newContent);
        @chmod($cfgFile, 0664);
    }

    jsonResponse(true, array(
        'session' => $newSession,
        'ip' => $ip,
        'user' => $user,
        'hotspot_name' => $hsName,
        'dns_name' => $dnsName
    ), "Kredensial dan informasi router '{$newSession}' berhasil diperbarui.");
}

jsonResponse(false, null, 'Invalid action', 400);
