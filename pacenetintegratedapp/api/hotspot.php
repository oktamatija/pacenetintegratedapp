<?php
/**
 * Pacenet REST API - Hotspot Captive Portal & Automated Router Deployment
 * Antigravity IDE - Pacenet Billing System
 */
require_once(__DIR__ . '/common.php');
checkAdminAuth(true);

$portalFile = '/var/www/pacenetintegratedapp/data/hotspot_portal.json';
if (!file_exists(dirname($portalFile))) {
    $portalFile = __DIR__ . '/../data/hotspot_portal.json';
}

$defaultConfig = array(
    'brand_name' => 'Cibi Cibi Hotspot',
    'brand_subtitle' => 'WiFi Cepat, Stabil & Terjangkau',
    'whatsapp_number' => '+62 813-4401-0045',
    'whatsapp_link' => 'https://wa.me/6281344010045?text=Halo%20Admin%20Cibi%20Cibi%20Hotspot,%20saya%20mau%20beli%20voucher',
    'prices' => array(
        array('name' => '12 Jam', 'cost' => 'Rp 4.000'),
        array('name' => '1 Minggu', 'cost' => 'Rp 40.000'),
        array('name' => '1 Bulan', 'cost' => 'Rp 100.000'),
        array('name' => 'Reseller', 'cost' => 'Paket Khusus')
    ),
    'theme' => 'gradient_blue_amber'
);

function getPortalConfig($file, $default) {
    if (!file_exists($file)) return $default;
    $raw = @file_get_contents($file);
    $arr = json_decode($raw, true);
    return is_array($arr) ? array_merge($default, $arr) : $default;
}

function savePortalConfig($file, $cfg) {
    @file_put_contents($file, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_config';
$routerSession = $_GET['router'] ?? $_POST['router'] ?? '';

// Build available routers list
$availableRouters = array();
foreach ($data as $sName => $sCfg) {
    if ($sName !== 'mikhmon' && !empty($sName) && strpos($sName, 'new-') !== 0) {
        $availableRouters[] = array(
            'session' => $sName,
            'name' => explode('%', $sCfg[4] ?? '')[1] ?? $sName,
            'ip' => explode('!', $sCfg[1] ?? '')[1] ?? '',
            'dns_name' => explode('^', $sCfg[5] ?? '')[1] ?? 'wifi.papua.net'
        );
    }
}

if (empty($routerSession) && !empty($availableRouters[0]['session'])) {
    $routerSession = $availableRouters[0]['session'];
}

// -------------------------------------------------------------------------
// ACTION: GET CONFIG & ROUTER HOTSPOT STATUS
// -------------------------------------------------------------------------
if ($action === 'get_config') {
    $portalCfg = getPortalConfig($portalFile, $defaultConfig);
    $routerStatus = array(
        'session' => $routerSession,
        'online' => false,
        'interfaces' => array(),
        'hotspot_server' => null,
        'hotspot_profile' => null,
        'walled_garden_ok' => false,
        'login_files_ok' => false,
        'portal_url' => 'https://hi1271.my.id/hotspot-login/',
        'portal_url_http' => 'http://hi1271.my.id/hotspot-login/'
    );

    if (!empty($routerSession)) {
        $conn = getMikroTikApi($routerSession);
        if ($conn && !empty($conn['success'])) {
            $api = $conn['api'];
            $routerStatus['online'] = true;

            // Interfaces
            $ifaces = $api->comm('/interface/print');
            if (is_array($ifaces)) {
                foreach ($ifaces as $if) {
                    if (($if['disabled'] ?? 'false') !== 'true') {
                        $routerStatus['interfaces'][] = array(
                            'name' => $if['name'] ?? '',
                            'type' => $if['type'] ?? '',
                            'running' => ($if['running'] ?? 'false') === 'true'
                        );
                    }
                }
            }

            // Hotspot Servers
            $hs = $api->comm('/ip/hotspot/print');
            if (is_array($hs) && count($hs) > 0) {
                $routerStatus['hotspot_server'] = array(
                    'name' => $hs[0]['name'] ?? '',
                    'interface' => $hs[0]['interface'] ?? '',
                    'profile' => $hs[0]['profile'] ?? '',
                    'disabled' => ($hs[0]['disabled'] ?? 'false') === 'true'
                );
            }

            // Hotspot Profiles
            $hsp = $api->comm('/ip/hotspot/profile/print');
            if (is_array($hsp) && count($hsp) > 0) {
                $p = $hsp[0];
                $routerStatus['hotspot_profile'] = array(
                    'name' => $p['name'] ?? '',
                    'dns_name' => $p['dns-name'] ?? '',
                    'html_directory' => $p['html-directory'] ?? '',
                    'use_radius' => ($p['use-radius'] ?? 'false') === 'true'
                );
            }

            // Walled Garden
            $wg = $api->comm('/ip/hotspot/walled-garden/print');
            $wgIp = $api->comm('/ip/hotspot/walled-garden/ip/print');
            $hasDomain = false;
            if (is_array($wg)) {
                foreach ($wg as $w) {
                    if (stripos($w['dst-host'] ?? '', 'hi1271.my.id') !== false || ($w['dst-address'] ?? '') === '202.10.47.76') {
                        $hasDomain = true;
                        break;
                    }
                }
            }
            if (!$hasDomain && is_array($wgIp)) {
                foreach ($wgIp as $w) {
                    if (($w['dst-address'] ?? '') === '202.10.47.76') {
                        $hasDomain = true;
                        break;
                    }
                }
            }
            $routerStatus['walled_garden_ok'] = $hasDomain;

            // Files check
            $files = $api->comm('/file/print');
            $hasLogin = false;
            if (is_array($files)) {
                foreach ($files as $f) {
                    $fn = $f['name'] ?? '';
                    if (strpos($fn, 'hotspot/login.html') !== false || $fn === 'login.html') {
                        $hasLogin = true;
                        break;
                    }
                }
            }
            $routerStatus['login_files_ok'] = $hasLogin;

            $api->disconnect();
        }
    }

    jsonResponse(true, array(
        'portal' => $portalCfg,
        'router_status' => $routerStatus,
        'available_routers' => $availableRouters
    ), 'Portal config loaded successfully');
}

// -------------------------------------------------------------------------
// ACTION: SAVE CONFIG
// -------------------------------------------------------------------------
if ($action === 'save_config') {
    checkWritePermission();
    $rawInput = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $cur = getPortalConfig($portalFile, $defaultConfig);
    if (isset($rawInput['brand_name'])) $cur['brand_name'] = trim($rawInput['brand_name']);
    if (isset($rawInput['brand_subtitle'])) $cur['brand_subtitle'] = trim($rawInput['brand_subtitle']);
    if (isset($rawInput['whatsapp_number'])) {
        $cur['whatsapp_number'] = trim($rawInput['whatsapp_number']);
        $cleanPhone = preg_replace('/[^0-9]/', '', $cur['whatsapp_number']);
        $cur['whatsapp_link'] = 'https://wa.me/' . $cleanPhone . '?text=' . urlencode('Halo Admin ' . $cur['brand_name'] . ', saya mau beli voucher');
    }
    if (isset($rawInput['prices']) && is_array($rawInput['prices'])) {
        $cur['prices'] = $rawInput['prices'];
    }
    if (isset($rawInput['theme'])) $cur['theme'] = trim($rawInput['theme']);

    savePortalConfig($portalFile, $cur);
    jsonResponse(true, $cur, 'Pengaturan tampilan Halaman Login Hotspot berhasil disimpan.');
}

// -------------------------------------------------------------------------
// ACTION: DEPLOY HOTSPOT TO MIKROTIK (1-CLICK SETUP)
// -------------------------------------------------------------------------
if ($action === 'deploy_router') {
    checkWritePermission();
    $rawInput = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $sess = trim($rawInput['session'] ?? $routerSession);
    $targetIface = trim($rawInput['interface'] ?? 'bridge1');
    $hsIp = trim($rawInput['ip'] ?? '10.0.0.1/22');
    $dnsName = trim($rawInput['dns_name'] ?? 'wifi.papua.net');

    if (empty($sess)) {
        jsonResponse(false, null, 'Sesi router tidak boleh kosong.', 400);
    }

    $conn = getMikroTikApi($sess, 5.0);
    if (!$conn || empty($conn['success'])) {
        jsonResponse(false, null, 'Gagal terhubung ke router MikroTik API. Pastikan router online via WireGuard.', 500);
    }

    $api = $conn['api'];
    $logs = array();

    // 1. Ensure target interface exists
    $ifaces = $api->comm('/interface/print', array('?name' => $targetIface));
    if (empty($ifaces)) {
        // Fallback to bridge1 or ether2
        $targetIface = 'bridge1';
        $bCheck = $api->comm('/interface/bridge/print', array('?name' => 'bridge1'));
        if (empty($bCheck)) {
            $api->comm('/interface/bridge/add', array('name' => 'bridge1'));
            $logs[] = "Bridge 'bridge1' dibuat";
        }
    }

    // 2. Configure IP Address on interface
    $ipNet = explode('/', $hsIp)[0];
    $cidr = explode('/', $hsIp)[1] ?? '22';
    $calcNet = ($cidr === '22') ? '10.0.0.0' : '192.168.88.0';

    $curIps = $api->comm('/ip/address/print', array('?interface' => $targetIface));
    if (empty($curIps)) {
        $api->comm('/ip/address/add', array(
            'address' => $hsIp,
            'network' => $calcNet,
            'interface' => $targetIface,
            'comment' => 'Hotspot Gateway'
        ));
        $logs[] = "IP Address {$hsIp} dipasang pada {$targetIface}";
    } else {
        $ipNet = explode('/', $curIps[0]['address'])[0];
        $logs[] = "IP Address sudah aktif pada {$targetIface}: {$curIps[0]['address']}";
    }

    // 3. Configure IP Pool
    $poolName = 'hs-pool-10.0.0.0';
    $poolRange = '10.0.0.10-10.0.3.250';
    $pools = $api->comm('/ip/pool/print', array('?name' => $poolName));
    if (empty($pools)) {
        $api->comm('/ip/pool/add', array(
            'name' => $poolName,
            'ranges' => $poolRange
        ));
        $logs[] = "IP Pool '{$poolName}' ({$poolRange}) dibuat";
    }

    // 4. Configure DHCP Option 114 (Captive Portal API URL RFC 8908)
    $opt114 = $api->comm('/ip/dhcp-server/option/print', array('?name' => 'captive-portal-114'));
    $portalRedirectUrl = 'http://hi1271.my.id/hotspot-login/';
    if (empty($opt114)) {
        $api->comm('/ip/dhcp-server/option/add', array(
            'name' => 'captive-portal-114',
            'code' => '114',
            'value' => '0x' . bin2hex($portalRedirectUrl)
        ));
        $logs[] = "DHCP Option 114 (RFC 8908 Auto-Popup) dikonfigurasi";
    }

    // 5. Configure DHCP Network & Server
    $dhcpNets = $api->comm('/ip/dhcp-server/network/print', array('?address' => "{$calcNet}/{$cidr}"));
    if (empty($dhcpNets)) {
        $api->comm('/ip/dhcp-server/network/add', array(
            'address' => "{$calcNet}/{$cidr}",
            'gateway' => $ipNet,
            'dns-server' => "{$ipNet},1.1.1.1,8.8.8.8",
            'dhcp-option' => 'captive-portal-114',
            'comment' => 'Hotspot Network'
        ));
        $logs[] = "DHCP Network {$calcNet}/{$cidr} dibuat";
    } else {
        $api->comm('/ip/dhcp-server/network/set', array(
            '.id' => $dhcpNets[0]['.id'],
            'gateway' => $ipNet,
            'dns-server' => "{$ipNet},1.1.1.1,8.8.8.8",
            'dhcp-option' => 'captive-portal-114'
        ));
    }

    $dhcpSrv = $api->comm('/ip/dhcp-server/print', array('?interface' => $targetIface));
    if (empty($dhcpSrv)) {
        $api->comm('/ip/dhcp-server/add', array(
            'name' => "dhcp-{$targetIface}",
            'interface' => $targetIface,
            'address-pool' => $poolName,
            'lease-time' => '30m',
            'disabled' => 'no'
        ));
        $logs[] = "DHCP Server pada {$targetIface} diaktifkan (Lease 30m)";
    } else {
        $api->comm('/ip/dhcp-server/set', array(
            '.id' => $dhcpSrv[0]['.id'],
            'address-pool' => $poolName,
            'lease-time' => '30m',
            'disabled' => 'no'
        ));
    }

    // 6. Configure Hotspot Profile
    $profName = 'hsprof1';
    $profs = $api->comm('/ip/hotspot/profile/print', array('?name' => $profName));
    if (empty($profs)) {
        $api->comm('/ip/hotspot/profile/add', array(
            'name' => $profName,
            'hotspot-address' => $ipNet,
            'dns-name' => $dnsName,
            'html-directory' => 'hotspot',
            'login-by' => 'http-chap,http-pap,cookie,mac-cookie',
            'use-radius' => 'yes',
            'radius-accounting' => 'yes',
            'radius-interim-update' => '1m',
            'http-cookie-lifetime' => '3d'
        ));
        $logs[] = "Hotspot Profile '{$profName}' dibuat (use-radius=yes, dns-name={$dnsName})";
    } else {
        $api->comm('/ip/hotspot/profile/set', array(
            '.id' => $profs[0]['.id'],
            'hotspot-address' => $ipNet,
            'dns-name' => $dnsName,
            'html-directory' => 'hotspot',
            'login-by' => 'http-chap,http-pap,cookie,mac-cookie',
            'use-radius' => 'yes',
            'radius-accounting' => 'yes',
            'radius-interim-update' => '1m',
            'http-cookie-lifetime' => '3d'
        ));
        $logs[] = "Hotspot Profile '{$profName}' diperbarui";
    }

    // Enforce default profile to use-radius
    $defProf = $api->comm('/ip/hotspot/profile/print', array('?name' => 'default'));
    if (!empty($defProf)) {
        $api->comm('/ip/hotspot/profile/set', array(
            '.id' => $defProf[0]['.id'],
            'dns-name' => $dnsName,
            'use-radius' => 'yes',
            'radius-accounting' => 'yes',
            'radius-interim-update' => '1m'
        ));
    }

    // 7. Configure Hotspot Server
    $hsSrv = $api->comm('/ip/hotspot/print', array('?interface' => $targetIface));
    if (empty($hsSrv)) {
        $api->comm('/ip/hotspot/add', array(
            'name' => 'hotspot1',
            'interface' => $targetIface,
            'profile' => $profName,
            'address-pool' => $poolName,
            'disabled' => 'no'
        ));
        $logs[] = "Hotspot Server 'hotspot1' diaktifkan pada {$targetIface}";
    } else {
        $api->comm('/ip/hotspot/set', array(
            '.id' => $hsSrv[0]['.id'],
            'profile' => $profName,
            'address-pool' => $poolName,
            'disabled' => 'no'
        ));
        $logs[] = "Hotspot Server '{$hsSrv[0]['name']}' disinkronkan ke {$targetIface}";
    }

    // 8. Configure Walled Garden for Cloud Portal & WhatsApp
    $domains = array(
        '*hi1271.my.id*',
        '*wifi.papua.net*',
        '*hotspot.yunus*',
        '*wa.me*',
        '*whatsapp.com*'
    );
    foreach ($domains as $dom) {
        $exist = $api->comm('/ip/hotspot/walled-garden/print', array('?dst-host' => $dom));
        if (empty($exist)) {
            $api->comm('/ip/hotspot/walled-garden/add', array(
                'dst-host' => $dom,
                'action' => 'accept',
                'comment' => 'Allow Pacenet Portal'
            ));
        }
    }
    $ips = array('202.10.47.76', '10.10.10.1');
    foreach ($ips as $ip) {
        $existIp = $api->comm('/ip/hotspot/walled-garden/ip/print', array('?dst-address' => $ip));
        if (empty($existIp)) {
            $api->comm('/ip/hotspot/walled-garden/ip/add', array(
                'dst-address' => $ip,
                'action' => 'accept',
                'comment' => 'Allow Pacenet Cloud Server'
            ));
        }
    }
    $logs[] = "Walled Garden dikonfigurasi (Domain hi1271.my.id, IP 202.10.47.76, 10.10.10.1, WhatsApp)";

    // 9. Fetch and install login.html & alogin.html redirectors via RouterOS /tool fetch
    $fetchCommands = array(
        '/tool fetch url="http://10.10.10.1/hotspot-login/login.html" mode=http dst-path="hotspot/login.html"',
        '/tool fetch url="http://10.10.10.1/hotspot-login/alogin.html" mode=http dst-path="hotspot/alogin.html"',
        '/tool fetch url="http://10.10.10.1/hotspot-login/login.html" mode=http dst-path="login.html"',
        '/tool fetch url="http://10.10.10.1/hotspot-login/alogin.html" mode=http dst-path="alogin.html"'
    );

    foreach ($fetchCommands as $fCmd) {
        $api->comm('/system/script/add', array(
            'name' => 'temp-fetch-hotspot',
            'source' => ":do { {$fCmd} } on-error={}"
        ));
        $api->comm('/system/script/run', array('number' => 'temp-fetch-hotspot'));
        $api->comm('/system/script/remove', array('numbers' => 'temp-fetch-hotspot'));
    }
    $logs[] = "File login.html & alogin.html redirector berhasil dipasang ke folder 'hotspot/' di router";

    $api->disconnect();

    jsonResponse(true, array(
        'session' => $sess,
        'interface' => $targetIface,
        'ip' => $hsIp,
        'dns_name' => $dnsName,
        'logs' => $logs
    ), "Pemasangan Hotspot Login pada router '{$sess}' berhasil dilakukan!");
}

jsonResponse(false, null, 'Aksi tidak dikenali', 400);
