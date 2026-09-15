<?php
/**
 * Pacenet REST API - Smart RouterOS Fleet Upgrade & Downgrade Manager
 * Supports individual and mass upgrade across architectures (CCR tile, arm, arm64, x86, mmips, mipsbe)
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);
session_write_close();

set_time_limit(300);

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');
$body = !empty($rawBody) ? json_decode($rawBody, true) : array();
$action = $_GET['action'] ?? ($body['action'] ?? 'list');

function getPackageUrl($version, $arch) {
    // Detect v6 vs v7
    $isV6 = strpos($version, '6.') === 0;
    if ($isV6) {
        return "https://download.mikrotik.com/routeros/{$version}/routeros-{$arch}-{$version}.npk";
    } else {
        return "https://download.mikrotik.com/routeros/{$version}/routeros-{$version}-{$arch}.npk";
    }
}

// 1. LIST ALL ROUTERS WITH ROS VERSION, ARCHITECTURE, AND UPDATE STATUS
if ($action === 'list') {
    $routersList = array();
    $checkUpdate = isset($_GET['check']) && $_GET['check'] === '1';

    foreach ($data as $sessName => $cfg) {
        if ($sessName === 'mikhmon' || empty($sessName) || strpos($sessName, 'new-') === 0) continue;

        $ip = explode('!', $cfg[1] ?? '')[1] ?? '';
        $user = explode('@|@', $cfg[2] ?? '')[1] ?? 'admin';
        $pass = decrypt(explode('#|#', $cfg[3] ?? '')[1] ?? '');
        $hsName = explode('%', $cfg[4] ?? '')[1] ?? $sessName;

        $item = array(
            'session' => $sessName,
            'hotspot_name' => $hsName,
            'ip' => $ip,
            'online' => false,
            'architecture' => 'unknown',
            'board_name' => 'N/A',
            'model' => 'N/A',
            'installed_version' => 'N/A',
            'latest_version' => 'N/A',
            'channel' => 'stable',
            'update_status' => '',
            'update_available' => false,
            'current_firmware' => 'N/A',
            'upgrade_firmware' => 'N/A',
            'firmware_upgrade_available' => false,
            'free_hdd_space' => 'N/A'
        );

        $api = new RouterosAPI();
        $api->timeout = 3;
        $api->attempts = 1;
        $api->debug = false;

        if ($api->connect($ip, $user, $pass)) {
            $item['online'] = true;

            // System resource
            $res = $api->comm('/system/resource/print');
            if (!empty($res[0])) {
                $r0 = $res[0];
                $item['architecture'] = $r0['architecture-name'] ?? $r0['architecture_name'] ?? 'unknown';
                $item['board_name'] = $r0['board-name'] ?? $r0['board_name'] ?? 'N/A';
                $item['installed_version'] = explode(' ', $r0['version'] ?? 'N/A')[0];
                $freeHdd = intval($r0['free-hdd-space'] ?? $r0['free_hdd_space'] ?? 0);
                $item['free_hdd_space'] = formatBytesReadable($freeHdd);
            }

            // Routerboard firmware
            $rb = $api->comm('/system/routerboard/print');
            if (!empty($rb[0])) {
                $rb0 = $rb[0];
                $item['model'] = $rb0['model'] ?? $item['board_name'];
                $item['current_firmware'] = $rb0['current-firmware'] ?? $rb0['current_firmware'] ?? 'N/A';
                $item['upgrade_firmware'] = $rb0['upgrade-firmware'] ?? $rb0['upgrade_firmware'] ?? 'N/A';
                if ($item['current_firmware'] !== 'N/A' && $item['upgrade_firmware'] !== 'N/A') {
                    $item['firmware_upgrade_available'] = ($item['current_firmware'] !== $item['upgrade_firmware']);
                }
            }

            // Package update check
            if ($checkUpdate) {
                $api->comm('/system/package/update/check-for-updates');
            }
            $upd = $api->comm('/system/package/update/print');
            if (!empty($upd[0])) {
                $u0 = $upd[0];
                $item['channel'] = $u0['channel'] ?? 'stable';
                $item['latest_version'] = $u0['latest-version'] ?? $u0['latest_version'] ?? 'N/A';
                $item['update_status'] = $u0['status'] ?? '';
                if (!empty($item['latest_version']) && $item['latest_version'] !== 'N/A') {
                    $item['update_available'] = ($item['installed_version'] !== $item['latest_version']);
                }
            }

            $api->disconnect();
        }

        $routersList[] = $item;
    }

    jsonResponse(true, array(
        'routers' => $routersList,
        'common_versions' => array(
            '7.15.3',
            '7.14.3',
            '7.13.5',
            '7.12.1',
            '7.10.2',
            '7.8',
            '6.49.13'
        ),
        'supported_architectures' => array('arm64', 'arm', 'tile', 'x86', 'mmips', 'mipsbe', 'smips')
    ));
}

// 2. CHECK UPDATES FOR A SPECIFIC ROUTER OR ALL ROUTERS
if ($action === 'check_updates') {
    $targetSession = $_GET['session'] ?? ($body['session'] ?? 'all');
    $results = array();

    foreach ($data as $sessName => $cfg) {
        if ($sessName === 'mikhmon' || empty($sessName) || strpos($sessName, 'new-') === 0) continue;
        if ($targetSession !== 'all' && $targetSession !== $sessName) continue;

        $conn = connectMikrotik($sessName, 4);
        if ($conn) {
            $conn['api']->comm('/system/package/update/check-for-updates');
            $upd = $conn['api']->comm('/system/package/update/print');
            $conn['api']->disconnect();
            $results[$sessName] = array(
                'success' => true,
                'channel' => $upd[0]['channel'] ?? 'stable',
                'installed_version' => $upd[0]['installed-version'] ?? 'N/A',
                'latest_version' => $upd[0]['latest-version'] ?? 'N/A',
                'status' => $upd[0]['status'] ?? ''
            );
        } else {
            $results[$sessName] = array('success' => false, 'message' => 'Gagal terhubung');
        }
    }

    jsonResponse(true, $results, 'Pengecekan update selesai');
}

// Guard all mutating operations against read-only demo user
checkWritePermission();

// 3. SET UPDATE CHANNEL
if ($action === 'set_channel') {
    $session = $body['session'] ?? $_GET['session'] ?? '';
    $channel = $body['channel'] ?? $_GET['channel'] ?? 'stable';

    if (empty($session)) {
        jsonResponse(false, null, 'Parameter session diperlukan', 400);
    }

    $conn = connectMikrotik($session, 4);
    if (!$conn) {
        jsonResponse(false, null, 'Gagal terhubung ke router', 500);
    }

    $api = $conn['api'];
    $api->comm('/system/package/update/set', array('channel' => $channel));
    $api->comm('/system/package/update/check-for-updates');
    $upd = $api->comm('/system/package/update/print');
    $api->disconnect();

    jsonResponse(true, array(
        'channel' => $channel,
        'update_info' => $upd[0] ?? []
    ), "Channel berhasil diubah ke {$channel}");
}

// 4. SMART UPGRADE (NATIVE MIKROTIK DOWNLOAD & INSTALL)
if ($action === 'smart_upgrade') {
    $session = $body['session'] ?? $_GET['session'] ?? '';
    if (empty($session)) {
        jsonResponse(false, null, 'Parameter session diperlukan', 400);
    }

    $conn = connectMikrotik($session, 6);
    if (!$conn) {
        jsonResponse(false, null, 'Gagal terhubung ke router', 500);
    }

    $api = $conn['api'];
    
    // Check update first
    $api->comm('/system/package/update/check-for-updates');
    $upd = $api->comm('/system/package/update/print');
    $latest = $upd[0]['latest-version'] ?? '';
    $installed = $upd[0]['installed-version'] ?? '';

    // Trigger install (MikroTik handles architecture-specific download and reboots to install)
    $res = $api->comm('/system/package/update/install');
    $api->disconnect();

    jsonResponse(true, array(
        'session' => $session,
        'from_version' => $installed,
        'target_version' => $latest,
        'result' => $res
    ), "Perintah upgrade berhasil dikirim ke router {$session}. Router sedang mengunduh paket dan akan segera restart.");
}

// 5. SMART TARGET VERSION (DOWNGRADE OR SPECIFIC VERSION UPGRADE)
if ($action === 'target_version') {
    $session = $body['session'] ?? $_GET['session'] ?? '';
    $targetVersion = trim($body['target_version'] ?? $_GET['target_version'] ?? '');

    if (empty($session) || empty($targetVersion)) {
        jsonResponse(false, null, 'Session dan target_version wajib diisi', 400);
    }

    $cfg = getRouterConfig($session);
    if (!$cfg) {
        jsonResponse(false, null, 'Konfigurasi router tidak ditemukan', 404);
    }

    $conn = connectMikrotik($session, 6);
    if (!$conn) {
        jsonResponse(false, null, 'Gagal terhubung ke router', 500);
    }

    $api = $conn['api'];
    $res = $api->comm('/system/resource/print');
    $arch = $res[0]['architecture-name'] ?? $res[0]['architecture_name'] ?? 'unknown';
    $curVer = explode(' ', $res[0]['version'] ?? '')[0];

    if ($arch === 'unknown') {
        $api->disconnect();
        jsonResponse(false, null, 'Arsitektur router tidak dapat dideteksi', 400);
    }

    $pkgUrl = getPackageUrl($targetVersion, $arch);
    $npkFileName = basename($pkgUrl);

    // 1. Try download via router's /tool/fetch directly
    $fetchSuccess = false;
    $fetchRes = $api->comm('/tool/fetch', array(
        'url' => $pkgUrl,
        'mode' => 'https',
        'dst-path' => $npkFileName
    ));

    // Check if file arrived in router's root storage
    $files = $api->comm('/file/print', array('?name' => $npkFileName));
    if (!empty($files)) {
        $fetchSuccess = true;
    }

    // 2. Fallback: If router fetch failed (e.g. DNS or cert issue), VPS downloads and uploads via FTP
    if (!$fetchSuccess) {
        $localTmp = "/tmp/{$npkFileName}";
        $cmdDownload = "curl -k -s -L -o '{$localTmp}' '{$pkgUrl}'";
        @shell_exec($cmdDownload);

        if (file_exists($localTmp) && filesize($localTmp) > 1000000) {
            // Upload to router via FTP
            $ftpConn = @ftp_connect($cfg['ip'], 21, 10);
            if ($ftpConn && @ftp_login($ftpConn, $cfg['user'], $cfg['pass'])) {
                @ftp_pasv($ftpConn, true);
                if (@ftp_put($ftpConn, $npkFileName, $localTmp, FTP_BINARY)) {
                    $fetchSuccess = true;
                }
                @ftp_close($ftpConn);
            }
            @unlink($localTmp);
        }
    }

    if (!$fetchSuccess) {
        $api->disconnect();
        jsonResponse(false, null, "Gagal mengunduh paket {$npkFileName} dari {$pkgUrl}. Pastikan router atau server dapat mengakses download.mikrotik.com", 500);
    }

    // Determine if downgrade or upgrade
    $isDowngrade = version_compare($targetVersion, $curVer, '<');
    if ($isDowngrade) {
        $actionRes = $api->comm('/system/package/downgrade');
        $msg = "Paket {$npkFileName} berhasil diunggah. Perintah downgrade ke {$targetVersion} telah dikirim, router akan segera reboot.";
    } else {
        $actionRes = $api->comm('/system/reboot');
        $msg = "Paket {$npkFileName} berhasil diunggah. Router sedang melakukan reboot untuk menginstal versi {$targetVersion}.";
    }

    $api->disconnect();

    jsonResponse(true, array(
        'session' => $session,
        'architecture' => $arch,
        'current_version' => $curVer,
        'target_version' => $targetVersion,
        'is_downgrade' => $isDowngrade,
        'package_file' => $npkFileName,
        'result' => $actionRes
    ), $msg);
}

// 6. UPGRADE ROUTERBOARD BOOTLOADER FIRMWARE
if ($action === 'upgrade_firmware') {
    $session = $body['session'] ?? $_GET['session'] ?? '';
    if (empty($session)) {
        jsonResponse(false, null, 'Parameter session diperlukan', 400);
    }

    $conn = connectMikrotik($session, 5);
    if (!$conn) {
        jsonResponse(false, null, 'Gagal terhubung ke router', 500);
    }

    $api = $conn['api'];
    $res = $api->comm('/system/routerboard/upgrade');
    $rb = $api->comm('/system/routerboard/print');
    $api->disconnect();

    jsonResponse(true, array(
        'session' => $session,
        'routerboard' => $rb[0] ?? [],
        'result' => $res
    ), 'Firmware RouterBOARD berhasil diupgrade. Silakan reboot router untuk menerapkan perubahan.');
}

// 7. MASS UPGRADE (ALL ONLINE ROUTERS)
if ($action === 'mass_upgrade') {
    $selectedSessions = $body['sessions'] ?? array();
    $targetChannel = $body['channel'] ?? 'stable';

    $results = array();
    $countSuccess = 0;
    $countFailed = 0;

    foreach ($data as $sessName => $cfg) {
        if ($sessName === 'mikhmon' || empty($sessName) || strpos($sessName, 'new-') === 0) continue;
        if (!empty($selectedSessions) && !in_array($sessName, $selectedSessions)) continue;

        $conn = connectMikrotik($sessName, 5);
        if (!$conn) {
            $results[] = array('session' => $sessName, 'status' => 'offline', 'message' => 'Router offline atau timeout');
            $countFailed++;
            continue;
        }

        $api = $conn['api'];
        // Set channel & check
        $api->comm('/system/package/update/set', array('channel' => $targetChannel));
        $api->comm('/system/package/update/check-for-updates');
        $upd = $api->comm('/system/package/update/print');
        $u0 = $upd[0] ?? array();

        $installed = $u0['installed-version'] ?? '';
        $latest = $u0['latest-version'] ?? '';

        if (!empty($latest) && $installed !== $latest) {
            // Trigger install
            $installRes = $api->comm('/system/package/update/install');
            $results[] = array(
                'session' => $sessName,
                'status' => 'upgrading',
                'from_version' => $installed,
                'to_version' => $latest,
                'channel' => $targetChannel
            );
            $countSuccess++;
        } else {
            $results[] = array(
                'session' => $sessName,
                'status' => 'already_latest',
                'version' => $installed,
                'message' => 'Versi sudah paling mutakhir'
            );
        }

        $api->disconnect();
    }

    jsonResponse(true, array(
        'total_processed' => count($results),
        'upgrading_count' => $countSuccess,
        'details' => $results
    ), "Mass upgrade dijalankan pada {$countSuccess} router.");
}

// 8. REBOOT ROUTER
if ($action === 'reboot') {
    $session = $body['session'] ?? $_GET['session'] ?? '';
    if (empty($session)) {
        jsonResponse(false, null, 'Parameter session diperlukan', 400);
    }

    $conn = connectMikrotik($session, 4);
    if (!$conn) {
        jsonResponse(false, null, 'Gagal terhubung ke router', 500);
    }

    $conn['api']->comm('/system/reboot');
    $conn['api']->disconnect();

    jsonResponse(true, array('session' => $session), "Router {$session} sedang direboot...");
}

jsonResponse(false, null, 'Invalid action', 400);
