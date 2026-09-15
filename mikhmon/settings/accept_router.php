<?php
/**
 * Router Accept & Auto-Push Configuration Engine
 * Antigravity IDE - Pacenet Billing System Integration
 */
session_start();
header('Content-Type: application/json');
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    echo json_encode(array('status' => 'error', 'message' => 'Sesi login tidak valid. Silakan login kembali.'));
    exit;
}

include_once('/var/www/mikhmon/lib/routeros_api.class.php');
include_once('/var/www/mikhmon/include/config.php');

$dataFile = '/var/www/mikhmon/data/pending_routers.json';

function loadPending($file) {
    if (!file_exists($file)) return array();
    $c = @file_get_contents($file);
    return json_decode($c, true) ?: array();
}

function savePending($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Uses Mikhmon native encrypt($string, $key=128) from routeros_api.class.php

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$identity = trim($_POST['identity'] ?? $_GET['identity'] ?? '');
$vpnIp = trim($_POST['vpn_ip'] ?? $_GET['vpn_ip'] ?? '');
$user = trim($_POST['user'] ?? 'admin');
$pass = trim($_POST['pass'] ?? '');
$hsIface = trim($_POST['hs_iface'] ?? 'Vlan1');
$winboxPort = intval($_POST['winbox_port'] ?? 0);

if ($action === 'reject' || $action === 'delete') {
    if (empty($identity) && empty($vpnIp)) {
        echo json_encode(array('status' => 'error', 'message' => 'Parameter identity atau IP VPN kosong.'));
        exit;
    }
    $pending = loadPending($dataFile);
    $updated = array();
    $found = false;
    foreach ($pending as $p) {
        $match = false;
        if (!empty($identity) && (($p['identity'] ?? '') === $identity || ($p['original_name'] ?? '') === $identity)) {
            $match = true;
        }
        if (!empty($vpnIp) && (($p['vpn_ip'] ?? '') === $vpnIp)) {
            $match = true;
        }

        if ($match) {
            $found = true;
            // Remove peer from wireguard kernel
            if (!empty($p['pubkey'])) {
                @shell_exec("sudo /usr/bin/wg set wg0 peer " . escapeshellarg($p['pubkey']) . " remove 2>/dev/null");
                @shell_exec("sudo /usr/bin/wg-quick save wg0 2>/dev/null");
            }
        } else {
            $updated[] = $p;
        }
    }
    savePending($dataFile, $updated);
    echo json_encode(array('status' => 'success', 'message' => "Router " . ($identity ?: $vpnIp) . " berhasil ditolak dan dihapus."));
    exit;
}

if (empty($identity) || empty($vpnIp)) {
    echo json_encode(array('status' => 'error', 'message' => 'Parameter identity atau IP VPN kosong.'));
    exit;
}

if ($action === 'accept') {
    // 1. Test RouterOS API connection
    $api = new RouterosAPI();
    $api->debug = false;
    $api->timeout = 5;
    $api->attempts = 2;

    if (!$api->connect($vpnIp, $user, $pass)) {
        echo json_encode(array(
            'status' => 'error',
            'message' => "Gagal terhubung ke API MikroTik di {$vpnIp}:8728! Periksa username/password router dan pastikan service API aktif."
        ));
        exit;
    }

    $pushLog = array();

    // 2. Push DNS: allow-remote-requests=yes
    $api->comm('/ip/dns/set', array('allow-remote-requests' => 'yes'));
    $pushLog[] = 'DNS allow-remote-requests=yes';

    // 3. Static DNS: hotspot.yunus -> 10.0.0.1
    $sd = $api->comm('/ip/dns/static/print', array('?name' => 'hotspot.yunus'));
    if (!empty($sd)) {
        foreach ($sd as $item) {
            $api->comm('/ip/dns/static/set', array('.id' => $item['.id'], 'address' => '10.0.0.1'));
        }
    } else {
        $api->comm('/ip/dns/static/add', array('name' => 'hotspot.yunus', 'address' => '10.0.0.1'));
    }
    $pushLog[] = 'Static DNS hotspot.yunus -> 10.0.0.1';

    // 4. IP Address 10.0.0.1/22 on Hotspot Interface
    $ips = $api->comm('/ip/address/print', array('?interface' => $hsIface));
    $hasExactIp = false;
    foreach ($ips as $ipRow) {
        if ($ipRow['address'] === '10.0.0.1/22' && $ipRow['network'] === '10.0.0.0') {
            $hasExactIp = true;
        } else if (strpos($ipRow['address'], '10.0.0.1/') === 0) {
            $api->comm('/ip/address/remove', array('.id' => $ipRow['.id']));
        }
    }
    if (!$hasExactIp) {
        $api->comm('/ip/address/add', array(
            'address' => '10.0.0.1/22',
            'network' => '10.0.0.0',
            'interface' => $hsIface,
            'comment' => 'Hotspot Gateway 10.0.0.1/22'
        ));
    }
    $pushLog[] = "IP 10.0.0.1/22 on {$hsIface}";

    // 5. Proxy-ARP on Hotspot Interface
    $api->comm('/interface/vlan/set', array('numbers' => $hsIface, 'arp' => 'proxy-arp'));
    $api->comm('/interface/bridge/set', array('numbers' => $hsIface, 'arp' => 'proxy-arp'));
    $api->comm('/interface/ethernet/set', array('numbers' => $hsIface, 'arp' => 'proxy-arp'));
    $pushLog[] = "Proxy-ARP enabled on {$hsIface}";

    // 6. Local DHCP Pool & Server on Hotspot Interface
    $poolName = "hs-pool-" . preg_replace('/[^a-zA-Z0-9]/', '', $hsIface);
    $pools = $api->comm('/ip/pool/print', array('?name' => $poolName));
    if (empty($pools)) {
        $api->comm('/ip/pool/add', array(
            'name' => $poolName,
            'ranges' => '10.0.0.2-10.0.3.254',
            'comment' => "Hotspot Pool on {$hsIface}"
        ));
    }

    $dhcps = $api->comm('/ip/dhcp-server/print', array('?interface' => $hsIface));
    if (empty($dhcps)) {
        $api->comm('/ip/dhcp-server/add', array(
            'name' => "dhcp-{$hsIface}",
            'interface' => $hsIface,
            'address-pool' => $poolName,
            'lease-time' => '1d',
            'disabled' => 'no',
            'comment' => "Hotspot DHCP on {$hsIface}"
        ));
    } else {
        $api->comm('/ip/dhcp-server/set', array(
            '.id' => $dhcps[0]['.id'],
            'address-pool' => $poolName,
            'lease-time' => '1d',
            'disabled' => 'no'
        ));
    }

    $nets = $api->comm('/ip/dhcp-server/network/print', array('?address' => '10.0.0.0/22'));
    if (empty($nets)) {
        $api->comm('/ip/dhcp-server/network/add', array(
            'address' => '10.0.0.0/22',
            'gateway' => '10.0.0.1',
            'netmask' => '22',
            'dns-server' => '10.0.0.1',
            'comment' => 'Hotspot Network 10.0.0.0/22'
        ));
    } else {
        $api->comm('/ip/dhcp-server/network/set', array(
            '.id' => $nets[0]['.id'],
            'gateway' => '10.0.0.1',
            'netmask' => '22',
            'dns-server' => '10.0.0.1'
        ));
    }
    $pushLog[] = "Local DHCP server & pool active on {$hsIface}";

    // Disable DHCP relay if present
    $relays = $api->comm('/ip/dhcp-relay/print', array('?interface' => $hsIface));
    foreach ($relays as $r) {
        $api->comm('/ip/dhcp-relay/set', array('.id' => $r['.id'], 'disabled' => 'yes'));
    }

    // 8. Hotspot Profile & Server Setup
    $profiles = $api->comm('/ip/hotspot/profile/print', array('?name' => 'hsprof1'));
    if (empty($profiles)) {
        $api->comm('/ip/hotspot/profile/add', array(
            'name' => 'hsprof1',
            'hotspot-address' => '10.0.0.1',
            'dns-name' => 'hotspot.yunus',
            'html-directory' => 'hotspot',
            'login-by' => 'http-chap,http-pap,cookie,mac-cookie',
            'use-radius' => 'yes',
            'radius-accounting' => 'yes',
            'radius-interim-update' => '5m',
            'http-cookie-lifetime' => '3d'
        ));
    } else {
        $api->comm('/ip/hotspot/profile/set', array(
            '.id' => $profiles[0]['.id'],
            'hotspot-address' => '10.0.0.1',
            'dns-name' => 'hotspot.yunus',
            'html-directory' => 'hotspot',
            'login-by' => 'http-chap,http-pap,cookie,mac-cookie',
            'use-radius' => 'yes',
            'radius-accounting' => 'yes',
            'radius-interim-update' => '5m',
            'http-cookie-lifetime' => '3d'
        ));
    }

    // Enforce RADIUS on ALL hotspot profiles (including 'default')
    $allProfiles = $api->comm('/ip/hotspot/profile/print');
    foreach ($allProfiles as $prof) {
        $api->comm('/ip/hotspot/profile/set', array(
            '.id' => $prof['.id'],
            'dns-name' => 'hotspot.yunus',
            'use-radius' => 'yes',
            'radius-accounting' => 'yes',
            'radius-interim-update' => '5m',
            'login-by' => 'http-chap,http-pap,cookie,mac-cookie'
        ));
    }
    $pushLog[] = "All Hotspot Profiles configured to use RADIUS & Pacenet settings";

    // Configure RADIUS Client
    $rads = $api->comm('/radius/print', array('?address' => '10.10.10.1'));
    if (empty($rads)) {
        $api->comm('/radius/add', array(
            'service' => 'hotspot',
            'address' => '10.10.10.1',
            'src-address' => $vpnIp,
            'secret' => 'YunusRadius2026!',
            'authentication-port' => '1812',
            'accounting-port' => '1813',
            'timeout' => '3000ms',
            'comment' => 'Pacenet FreeRADIUS'
        ));
    } else {
        $api->comm('/radius/set', array(
            '.id' => $rads[0]['.id'],
            'service' => 'hotspot',
            'address' => '10.10.10.1',
            'src-address' => $vpnIp,
            'secret' => 'YunusRadius2026!',
            'authentication-port' => '1812',
            'accounting-port' => '1813',
            'timeout' => '3000ms',
            'comment' => 'Pacenet FreeRADIUS'
        ));
    }
    $api->comm('/radius/incoming/set', array('accept' => 'yes', 'port' => '3799'));
    $pushLog[] = "FreeRADIUS Client (10.10.10.1:1812/1813) configured";

    // DHCP Option 114 (Captive Portal API URL RFC 8908) & Enforce 30m Lease Time
    $opt114 = $api->comm('/ip/dhcp-server/option/print', array('?name' => 'captive-portal-114'));
    if (empty($opt114)) {
        $api->comm('/ip/dhcp-server/option/add', array(
            'name' => 'captive-portal-114',
            'code' => '114',
            'value' => '0x' . bin2hex('http://202.10.46.222/hotspot-login/')
        ));
    }
    $nets = $api->comm('/ip/dhcp-server/network/print');
    foreach ($nets as $net) {
        $api->comm('/ip/dhcp-server/network/set', array(
            '.id' => $net['.id'],
            'dhcp-option' => 'captive-portal-114'
        ));
    }
    $dhcpSrvs = $api->comm('/ip/dhcp-server/print');
    foreach ($dhcpSrvs as $ds) {
        $api->comm('/ip/dhcp-server/set', array(
            '.id' => $ds['.id'],
            'lease-time' => '30m'
        ));
    }
    $pushLog[] = "DHCP Option 114 & 30m Lease Time configured (Prevents IP Pool Exhaustion)";

    // Enable FTP for template uploads
    $ftpSvc = $api->comm('/ip/service/print', array('?name' => 'ftp'));
    if (!empty($ftpSvc)) {
        $api->comm('/ip/service/set', array('.id' => $ftpSvc[0]['.id'], 'disabled' => 'no'));
    }

    $hsServer = $api->comm('/ip/hotspot/print', array('?interface' => $hsIface));
    if (empty($hsServer)) {
        $api->comm('/ip/hotspot/add', array(
            'name' => 'hotspot1',
            'interface' => $hsIface,
            'profile' => 'hsprof1',
            'address-pool' => $poolName,
            'disabled' => 'no'
        ));
    } else {
        $api->comm('/ip/hotspot/set', array(
            '.id' => $hsServer[0]['.id'],
            'profile' => 'hsprof1',
            'address-pool' => $poolName,
            'disabled' => 'no'
        ));
    }
    $pushLog[] = "Hotspot Server on {$hsIface} active";

    // 9. Hotspot Walled Garden
    $wgIp = $api->comm('/ip/hotspot/walled-garden/ip/print', array('?dst-address' => '202.10.46.222'));
    if (empty($wgIp)) {
        $api->comm('/ip/hotspot/walled-garden/ip/add', array(
            'dst-address' => '202.10.46.222',
            'action' => 'accept',
            'comment' => 'Allow Hotspot Yunus VPS'
        ));
    }
    $wgHost = $api->comm('/ip/hotspot/walled-garden/print', array('?dst-host' => '202.10.46.222'));
    if (empty($wgHost)) {
        $api->comm('/ip/hotspot/walled-garden/add', array(
            'dst-host' => '202.10.46.222',
            'action' => 'accept',
            'comment' => 'Allow Hotspot Yunus VPS'
        ));
    }
    $wgDomain = $api->comm('/ip/hotspot/walled-garden/print', array('?dst-host' => '*hy0045.my.id*'));
    if (empty($wgDomain)) {
        $api->comm('/ip/hotspot/walled-garden/add', array(
            'dst-host' => '*hy0045.my.id*',
            'action' => 'accept',
            'comment' => 'Allow hy0045 Domain'
        ));
    }
    $wgVpnIp = $api->comm('/ip/hotspot/walled-garden/ip/print', array('?dst-address' => '10.10.10.1'));
    if (empty($wgVpnIp)) {
        $api->comm('/ip/hotspot/walled-garden/ip/add', array(
            'dst-address' => '10.10.10.1',
            'action' => 'accept',
            'comment' => 'Allow Pacenet VPN IP'
        ));
    }
    $wgDns = $api->comm('/ip/hotspot/walled-garden/print', array('?dst-host' => '*hotspot.yunus*'));
    if (empty($wgDns)) {
        $api->comm('/ip/hotspot/walled-garden/add', array(
            'dst-host' => '*hotspot.yunus*',
            'action' => 'accept',
            'comment' => 'Allow Hotspot Yunus Domain'
        ));
    }
    $pushLog[] = "Walled Garden for VPS 202.10.46.222 & hy0045.my.id configured";

    // 10. ECMP Load Balancing Port 1-4
    $defRoutes = $api->comm('/ip/route/print', array('?dst-address' => '0.0.0.0/0', '?active' => 'true'));
    $primaryGw = !empty($defRoutes) ? $defRoutes[0]['gateway'] : '192.168.1.1';
    
    $wgRoutes = $api->comm('/ip/route/print', array('?dst-address' => '202.10.46.222/32'));
    if (empty($wgRoutes)) {
        $api->comm('/ip/route/add', array(
            'dst-address' => '202.10.46.222/32',
            'gateway' => $primaryGw,
            'distance' => '1',
            'comment' => 'WG Tunnel Protection to VPS'
        ));
    }

    $wLists = $api->comm('/interface/list/print', array('?name' => 'WAN'));
    if (empty($wLists)) {
        $api->comm('/interface/list/add', array('name' => 'WAN', 'comment' => 'ECMP WAN Interface List'));
    }
    $wanPorts = array('ether1', 'ether2', 'ether3', 'ether4');
    $curMembers = $api->comm('/interface/list/member/print', array('?list' => 'WAN'));
    $curMemberIfaces = array();
    foreach ($curMembers as $cm) {
        $curMemberIfaces[] = $cm['interface'];
    }
    foreach ($wanPorts as $wp) {
        if (!in_array($wp, $curMemberIfaces)) {
            $api->comm('/interface/list/member/add', array('list' => 'WAN', 'interface' => $wp));
        }
    }

    $dhClients = $api->comm('/ip/dhcp-client/print');
    $curDhcp = array();
    foreach ($dhClients as $dc) {
        $curDhcp[$dc['interface']] = $dc;
    }
    foreach ($wanPorts as $wp) {
        if (!isset($curDhcp[$wp])) {
            $api->comm('/ip/dhcp-client/add', array(
                'interface' => $wp,
                'add-default-route' => 'yes',
                'default-route-distance' => '1',
                'disabled' => 'no',
                'comment' => "ECMP DHCP Client {$wp}"
            ));
        } else {
            $api->comm('/ip/dhcp-client/set', array(
                '.id' => $curDhcp[$wp]['.id'],
                'add-default-route' => 'yes',
                'default-route-distance' => '1',
                'disabled' => 'no'
            ));
        }
    }

    // NAT Masquerade for WAN Interface List
    $natRules = $api->comm('/ip/firewall/nat/print', array('?out-interface-list' => 'WAN'));
    if (empty($natRules)) {
        $api->comm('/ip/firewall/nat/add', array(
            'chain' => 'srcnat',
            'out-interface-list' => 'WAN',
            'action' => 'masquerade',
            'comment' => 'ECMP Masquerade WAN 1-4'
        ));
    }

    // NAT Masquerade for Hotspot Network 10.0.0.0/22 (guarantees internet access on all uplinks)
    $hsNat = $api->comm('/ip/firewall/nat/print', array('?src-address' => '10.0.0.0/22', '?action' => 'masquerade'));
    if (empty($hsNat)) {
        $api->comm('/ip/firewall/nat/add', array(
            'chain' => 'srcnat',
            'src-address' => '10.0.0.0/22',
            'action' => 'masquerade',
            'comment' => 'Hotspot Masquerade 10.0.0.0/22'
        ));
    }
    $pushLog[] = "ECMP Load Balancing & Hotspot NAT Masquerade configured";

    // User profiles & Recording on-login
    $profiles_meta = array(
        'reseller' => array('rate-limit' => '1/2', 'shared-users' => '1', 'price' => '0', 'sprice' => '0', 'validity' => 'noexp', 'expmode' => '0'),
        '12-jam' => array('rate-limit' => '3/5', 'shared-users' => '1', 'price' => '4000', 'sprice' => '4000', 'validity' => '12h', 'expmode' => 'remc'),
        '1minggu-40rb' => array('rate-limit' => '3M/5M', 'shared-users' => '1', 'price' => '40000', 'sprice' => '40000', 'validity' => '7d', 'expmode' => 'remc'),
        '1bulan-100rb' => array('rate-limit' => '3M/5M', 'shared-users' => '1', 'price' => '100000', 'sprice' => '100000', 'validity' => '30d', 'expmode' => 'remc')
    );
    foreach ($profiles_meta as $pname => $pdata) {
        $price = $pdata['price'];
        $sprice = $pdata['sprice'];
        $validity = $pdata['validity'];
        $expmode = $pdata['expmode'];
        $getlock = 'Disable';
        
        if ($expmode == 'remc') {
            $record = '; :local mac $"mac-address"; :local time [/system clock get time ]; /system script add name="$date-|-$time-|-$user-|-'.$price.'-|-$address-|-$mac-|-' . $validity . '-|-'.$pname.'-|-$comment" owner="$month$year" source="$date" comment="mikhmon"';
            $onlogin = ':put (",'.$expmode.',' . $price . ',' . $validity . ','.$sprice.',,' . $getlock . ',"); {:local comment [ /ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local ucode [:pic $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $comment = "") do={ :local date [ /system clock get date ];:local year [ :pick $date 7 11 ];:local month [ :pick $date 0 3 ]; /sys sch add name="$user" disable=no start-date=$date interval="' . $validity . '"; :delay 5s; :local exp [ /sys sch get [ /sys sch find where name="$user" ] next-run]; :local getxp [len $exp]; :if ($getxp = 15) do={ :local d [:pic $exp 0 6]; :local t [:pic $exp 7 16]; :local s ("/"); :local exp ("$d$s$year $t"); /ip hotspot user set comment="$exp" [find where name="$user"];}; :if ($getxp = 8) do={ /ip hotspot user set comment="$date $exp" [find where name="$user"];}; :if ($getxp > 15) do={ /ip hotspot user set comment="$exp" [find where name="$user"];};:delay 5s; /sys sch remove [find where name="$user"]' . $record . '}}';
        } else {
            $onlogin = ':put (",,gratis,,,noexp,Disable,")';
        }
        
        $up = $api->comm('/ip/hotspot/user/profile/print', array('?name' => $pname));
        if (empty($up)) {
            $api->comm('/ip/hotspot/user/profile/add', array(
                'name' => $pname,
                'rate-limit' => $pdata['rate-limit'],
                'shared-users' => $pdata['shared-users'],
                'status-autorefresh' => '1m',
                'on-login' => $onlogin
            ));
        } else {
            $api->comm('/ip/hotspot/user/profile/set', array(
                '.id' => $up[0]['.id'],
                'rate-limit' => $pdata['rate-limit'],
                'shared-users' => $pdata['shared-users'],
                'status-autorefresh' => '1m',
                'on-login' => $onlogin
            ));
        }
        
        if ($expmode == 'remc') {
            $bgservice = ':local dateint do={:local montharray ( "jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec" );:local days [ :pick $d 4 6 ];:local month [ :pick $d 0 3 ];:local year [ :pick $d 7 11 ];:local monthint ([ :find $montharray $month]);:local month ($monthint + 1);:if ( [len $month] = 1) do={:local zero ("0");:return [:tonum ("$year$zero$month$days")];} else={:return [:tonum ("$year$month$days")];}}; :local timeint do={ :local hours [ :pick $t 0 2 ]; :local minutes [ :pick $t 3 5 ]; :return ($hours * 60 + $minutes) ; }; :local date [ /system clock get date ]; :local time [ /system clock get time ]; :local today [$dateint d=$date] ; :local curtime [$timeint t=$time] ; :foreach i in [ /ip hotspot user find where profile="'.$pname.'" ] do={ :local comment [ /ip hotspot user get $i comment]; :local name [ /ip hotspot user get $i name]; :local gettime [:pic $comment 12 20]; :if ([:pic $comment 3] = "/" and [:pic $comment 6] = "/") do={:local expd [$dateint d=$comment] ; :local expt [$timeint t=$gettime] ; :if (($expd < $today and $expt < $curtime) or ($expd < $today and $expt > $curtime) or ($expd = $today and $expt < $curtime)) do={ [ /ip hotspot user remove $i ]; [ /ip hotspot active remove [find where user=$name] ];}}}';
            $randstarttime = "0".rand(1,5).":".rand(10,59).":".rand(10,59);
            $randinterval = "00:02:".rand(10,59);
            $sch = $api->comm('/system/scheduler/print', array('?name' => $pname));
            if (empty($sch)) {
                $api->comm('/system/scheduler/add', array(
                    'name' => $pname,
                    'start-time' => $randstarttime,
                    'interval' => $randinterval,
                    'on-event' => $bgservice,
                    'disabled' => 'no',
                    'comment' => "Monitor Profile $pname"
                ));
            } else {
                $api->comm('/system/scheduler/set', array(
                    '.id' => $sch[0]['.id'],
                    'on-event' => $bgservice,
                    'disabled' => 'no'
                ));
            }
        }
    }
    
    // Logging rules for Hotspot Log
    $hsLog = $api->comm('/system/logging/print', array('?topics' => 'hotspot,info,debug'));
    if (empty($hsLog)) {
        $api->comm('/system/logging/add', array(
            'topics' => 'hotspot,info,debug',
            'prefix' => '->',
            'action' => 'disk'
        ));
    }
    $pushLog[] = "User Profiles, Expiry Monitor & Logging Rules configured";

    // 10. Blackout Recovery Engine: SNTP Client & Auto-Reconnect Watchdog
    $api->comm('/system/ntp/client/set', array(
        'enabled' => 'yes',
        'mode' => 'unicast',
        'servers' => '162.159.200.1,216.239.35.0'
    ));
    $staleR = $api->comm('/ip/route/print', array('?comment' => 'WG Tunnel Protection to VPS'));
    foreach ($staleR as $sr) {
        if (!empty($sr['.id'])) $api->comm('/ip/route/remove', array('.id' => $sr['.id']));
    }

    $wdScript = ':local vpnGw "10.10.10.1"; :local pingCount [/ping $vpnGw count=2 interval=1s]; :if ($pingCount = 0) do={ /ping $vpnGw count=1; :if ([/ping 8.8.8.8 count=1] > 0) do={ :log warning "[Pacenet-Watchdog] VPN down but WAN online. Restarting WireGuard..."; /interface/wireguard/disable [find name=wg-vpn-remote]; :delay 1s; /interface/wireguard/enable [find name=wg-vpn-remote]; :delay 2s; :do { /tool fetch url="http://202.10.46.222/join.php?action=heartbeat&ip=' . $vpnIp . '" mode=http keep-result=no } on-error={} } }';
    $oldSc = $api->comm('/system/script/print', array('?name' => 'pacenet-watchdog'));
    foreach ($oldSc as $osc) {
        $api->comm('/system/script/remove', array('.id' => $osc['.id']));
    }
    $api->comm('/system/script/add', array(
        'name' => 'pacenet-watchdog',
        'source' => $wdScript,
        'comment' => 'Auto-reconnect WireGuard after blackout'
    ));

    $oldSch = $api->comm('/system/scheduler/print', array('?name' => 'pacenet-watchdog'));
    foreach ($oldSch as $osch) {
        $api->comm('/system/scheduler/remove', array('.id' => $osch['.id']));
    }
    $api->comm('/system/scheduler/add', array(
        'name' => 'pacenet-watchdog',
        'interval' => '15s',
        'start-time' => 'startup',
        'on-event' => '/system/script/run pacenet-watchdog',
        'comment' => 'Keep WireGuard connected 24/7 across blackouts'
    ));
    $pushLog[] = "Blackout Recovery Watchdog & SNTP Client configured";

    // Ensure FTP service is enabled before FTP upload
    $api->comm('/ip/service/set', array('numbers' => 'ftp', 'disabled' => 'no', 'address' => '0.0.0.0/0'));

    $api->disconnect();

    // 11. FTP Upload login.html & alogin.html redirector to router
    $ftp = @ftp_connect($vpnIp, 21, 6);
    if ($ftp && @ftp_login($ftp, $user, $pass)) {
        ftp_pasv($ftp, true);
        $localLogin = '/var/www/mikhmon/hotspot-login/login.html';
        $localAlogin = '/var/www/mikhmon/hotspot-login/alogin.html';

        if (file_exists($localLogin)) {
            @ftp_mkdir($ftp, 'hotspot');
            @ftp_put($ftp, 'hotspot/login.html', $localLogin, FTP_BINARY);
            @ftp_put($ftp, 'login.html', $localLogin, FTP_BINARY);
            @ftp_mkdir($ftp, 'flash');
            @ftp_mkdir($ftp, 'flash/hotspot');
            @ftp_put($ftp, 'flash/hotspot/login.html', $localLogin, FTP_BINARY);
            @ftp_put($ftp, 'flash/login.html', $localLogin, FTP_BINARY);
            $pushLog[] = "Login Redirector (login.html) uploaded via FTP";
        }

        if (file_exists($localAlogin)) {
            @ftp_put($ftp, 'hotspot/alogin.html', $localAlogin, FTP_BINARY);
            @ftp_put($ftp, 'alogin.html', $localAlogin, FTP_BINARY);
            @ftp_put($ftp, 'flash/hotspot/alogin.html', $localAlogin, FTP_BINARY);
            @ftp_put($ftp, 'flash/alogin.html', $localAlogin, FTP_BINARY);
            $pushLog[] = "Login Success Page (alogin.html) uploaded via FTP";
        }
        @ftp_close($ftp);
    } else {
        $pushLog[] = "Catatan: FTP upload dilewati / port FTP tidak merespons (akan menggunakan template bawaan router)";
    }

    // 8. Add Winbox Port Forwarding if port specified
    if ($winboxPort > 0) {
        shell_exec("sudo iptables -t nat -A PREROUTING -p tcp --dport {$winboxPort} -j DNAT --to-destination {$vpnIp}:8291 2>/dev/null");
        shell_exec("sudo iptables -A FORWARD -p tcp -d {$vpnIp} --dport 8291 -j ACCEPT 2>/dev/null");
        shell_exec("sudo service iptables save 2>/dev/null || sudo iptables-save > /etc/sysconfig/iptables 2>/dev/null");
        $pushLog[] = "Winbox Remote Port {$winboxPort} opened";
    }

    // 9. Register Router Session in Pacenet Billing System (include/config.php)
    $encPass = encrypt($pass);
    $sessionName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $identity);
    $cfgPath = '/var/www/mikhmon/include/config.php';
    $cfgContent = file_get_contents($cfgPath);

    // Ensure session name is strictly unique and does not collide with a different router IP
    $baseSession = $sessionName;
    $c = 2;
    while (isset($data[$sessionName])) {
        $existingIp = explode('!', $data[$sessionName][1] ?? '')[1] ?? '';
        if ($existingIp === $vpnIp) {
            // Re-accepting the same router
            break;
        }
        $sessionName = $baseSession . '-' . $c;
        $c++;
    }
    if ($sessionName !== $baseSession) {
        $identity = $identity . " ($sessionName)";
    }

    // Register or Update session in config.php
    $sessionEntry = "\$data['$sessionName'] = array ('1'=>'$sessionName!$vpnIp','$sessionName@|@$user','$sessionName#|#$encPass','$sessionName%$identity','$sessionName^hotspot.yunus','$sessionName&Rp','$sessionName*10','$sessionName(1','$sessionName)','$sessionName=10','$sessionName@!@disable');";
    if (strpos($cfgContent, "\$data['$sessionName']") === false) {
        file_put_contents($cfgPath, "\n" . $sessionEntry . "\n", FILE_APPEND);
        $pushLog[] = "Session '{$sessionName}' registered in Pacenet Billing System";
    } else {
        $pat = "/\\\$data\['" . preg_quote($sessionName, '/') . "'\]\s*=\s*array\s*\([^\)]*\);/";
        $cfgContent = preg_replace($pat, $sessionEntry, $cfgContent);
        file_put_contents($cfgPath, $cfgContent);
        $pushLog[] = "Session '{$sessionName}' updated in Pacenet Billing System";
    }

    // 10. Update status in pending_routers.json
    foreach ($pending as &$p) {
        if ($p['identity'] === $identity) {
            $p['status'] = 'accepted';
            $p['accepted_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    savePending($dataFile, $pending);

    echo json_encode(array(
        'status' => 'success',
        'message' => "Router '{$identity}' BERHASIL DITERIMA & DIKONFIGURASI!\n" . implode("\n- ", $pushLog),
        'session_name' => $sessionName,
        'winbox_port' => $winboxPort,
        'vpn_ip' => $vpnIp
    ));
    exit;
}

echo json_encode(array('status' => 'error', 'message' => 'Aksi tidak dikenal.'));
