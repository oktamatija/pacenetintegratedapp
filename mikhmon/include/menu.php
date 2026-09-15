<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
// hide all error
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  include ('./include/version.php');

  $btnmenuactive = "font-weight: bold;background-color: #f9f9f9; color: #000000";
  if (isset($_GET['hotspot-log'])) {
    $hotspot = "log";
  }
  if ($hotspot == "dashboard" || substr(end(explode("/", $url)), 0, 8) == "?session") {
    $shome = "active";
    $mpage = $_dashboard;
  } elseif ($hotspot == "quick-print" || $hotspot == "list-quick-print") {
    $squick = "active";
    $mpage = $_quick_print;   
  } elseif ($hotspot == "users" || $userbyprofile != "" || $hotspot == "export-users" || $removehotspotuserbycomment != "" || $removehotspotuser != "" || $removehotspotusers != "" || $disablehotspotuser || $enablehotspotuser != "") {
    $susersl = "active";
    $susers = "active";
    $mpage = $_users;
    $umenu = "menu-open";
  } elseif ($hotspotuser == "add") {
    $sadduser = "active";
    $mpage = $_users;
    $susers = "active";
    $umenu = "menu-open";
  } elseif ($hotspotuser == "generate") {
    $sgenuser = "active";
    $mpage = $_users;
    $susers = "active";
    $umenu = "menu-open";
  } elseif ($userbyname != ""  || $resethotspotuser != "") {
    $susers = "active";
    $mpage = $_users;
    $umenu = "menu-open";
  } elseif ($hotspot == "user-profiles") {
    $suserprofiles = "active";
    $suserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($hotspot == "active" || $removeuseractive != "") {
    $sactive = "active";
    $mpage = $_hotspot_active;
    $hamenu = "menu-open";
  } elseif ($hotspot == "hosts" || $hotspot == "hostp" || $hotspot == "hosta" || $removehost != "") {
    $shosts = "active";
    $mpage = $_hosts;
    $hmenu = "menu-open";
  } elseif ($hotspot == "dhcp-leases") {
    $slease = "active";
    $mpage = $_dhcp_leases;
  } elseif ($sys == "resource-graph" || $minterface == "resource-graph") {
    $sresgraph = "active";
    $mpage = "Resource Graphs";
  } elseif ($minterface == "traffic-monitor") {
    $strafficmonitor = "active";
    $mpage = $_traffic_monitor;  
  } elseif ($hotspot == "ipbinding" || $hotspot == "binding" || $removeipbinding != "" || $enableipbinding != "" || $disableipbinding != "") {
    $sipbind = "active";
    $mpage = $_ip_bindings;
    $ibmenu = "menu-open";
  } elseif ($hotspot == "template-editor") {
    $ssett = "active";
    $teditor = "active";
    $mpage = $_template_editor;
    $settmenu = "menu-open";
  } elseif ($hotspot == "uplogo") {
    $ssett = "active";
    $uplogo = "active";
    $mpage = $_upload_logo;
    $settmenu = "menu-open";
  } elseif ($hotspot == "cookies" || $removecookie != "") {
    $scookies = "active";
    $mpage = $_hotspot_cookies;
    $cmenu = "menu-open";
  } elseif ($hotspot == "log") {
    $log = "active";
    $slog = "active";
    $mpage = $_hotspot_log;
    $lmenu = "menu-open";
  } elseif ($report == "userlog") {
    $log = "active";
    $sulog = "active";
    $mpage = $_user_log;
    $lmenu = "menu-open";
  } elseif ($ppp == "secrets" || $ppp == "addsecret" || $enablesecr != "" || $disablesecr != "" || $removesecr != "" || $secretbyname != "") {
    $mppp = "active";
    $ssecrets = "active";
    $mpage = $_ppp_secrets;
    $pppmenu = "menu-open";
  } elseif ($ppp == "profiles" || $removepprofile != "" || $ppp == "add-profile" || $ppp == "edit-profile"  ) {
    $mppp = "active";
    $spprofile = "active";
    $mpage = $_ppp_profiles;
    $pppmenu = "menu-open";
  } elseif ($ppp == "active" || $removepactive != "") {
    $mppp = "active";
    $spactive = "active";
    $mpage = $_ppp_active;
    $pppmenu = "menu-open";
  } elseif ($sys == "scheduler" || $enablesch != "" || $disablesch != "" || $removesch != "") {
    $sysmenu = "active";
    $ssch = "active";
    $mpage = $_system_scheduler;
    $schmenu = "menu-open";
  } elseif ($report == "selling" || $report == "resume-report") {
    $sselling = "active";
    $mpage = $_report;
  } elseif ($userprofile == "add") {
    $suserprof = "active";
    $sadduserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($userprofilebyname != "") {
    $suserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($hotspot == "users-by-profile") {
    $susersbp = "active";
    $mpage = $_vouchers;
  } elseif ($userbyname != "") {
    $mpage = $_users;
    $susers = "active";
  } elseif ($hotspot == "about") {
    $mpage = $_about;
    $sabout = "active";
  } elseif ($id == "sessions" || $id == "remove" || $router == "new") {
    $ssesslist = "active";
    $mpage = $_admin_settings;
  } elseif ($id == "settings" && $session == "new") {
    $snsettings = "active";
    $mpage = $_add_router;
  } elseif ($id == "settings" || $id == "connect") {
    $ssettings = "active";
    $mpage = $_session_settings;
  } elseif ($id == "about") {
    $sabout = "active";
    $mpage = $_about;
  } elseif ($id == "uplogo") {
    $suplogo = "active";
    $mpage = $_upload_logo;
  } elseif ($id == "vps-resource" || $id == "vps_resource") {
    $svpsres = "active";
    $mpage = "VPS Resource Graphs";
  } elseif ($id == "all" || $id == "all-routers") {
    $sall = "active";
    $mpage = "ALL Routers Dashboard";
  } elseif ($id == "editor") {
    $seditor = "active";
    $mpage = $_template_editor;
  }
}

if($idleto != "disable"){
  $didleto = 'display:block;';
}else{
  $didleto = 'display:none;';
}
?>
<span style="display:none;" id="idto"><?= $idleto ;?></span>


<?php 
// Load all configured routers for quick navbar menus on Dashboard Utama
$adminRouters = array();
if (file_exists('./include/config.php')) {
    include_once('./include/config.php');
    if (!empty($data) && is_array($data)) {
        foreach ($data as $sessKey => $sessVal) {
            if ($sessKey === 'mikhmon' || empty($sessKey)) continue;
            $rName = explode('%', $sessVal[4] ?? '')[1] ?? $sessKey;
            $adminRouters[$sessKey] = $rName;
        }
    }
}
$masterSession = isset($adminRouters['Rumah-DOLPHIN']) ? 'Rumah-DOLPHIN' : (!empty($adminRouters) ? array_key_first($adminRouters) : 'Rumah-DOLPHIN');
?>

<style>
/* Enterprise NOC Navigation Styles */
#navbar {
  height: 50px;
  background: #1a1e27 !important;
  border-bottom: 1px solid #2d343f !important;
  box-shadow: 0 2px 10px rgba(0,0,0,0.35);
  z-index: 1000;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  padding: 0 16px !important;
}
#sidenav {
  background: #161922 !important;
  border-right: 1px solid #282f3c !important;
  overflow-y: auto !important;
  scrollbar-width: thin;
  scrollbar-color: #373e47 transparent;
}
#sidenav::-webkit-scrollbar {
  width: 5px;
}
#sidenav::-webkit-scrollbar-thumb {
  background: #373e47;
  border-radius: 3px;
}
.side-brand-box {
  padding: 14px 16px;
  background: #11141c;
  border-bottom: 1px solid #252b37;
  display: flex;
  align-items: center;
  gap: 10px;
}
.side-section-title {
  padding: 12px 16px 4px 16px;
  font-size: 10px;
  font-weight: 800;
  color: #718093;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  border-top: 1px solid rgba(255,255,255,0.05);
  margin-top: 6px;
}
#sidenav a.menu {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 16px;
  font-size: 12px;
  color: #ced6e0;
  text-decoration: none;
  border-left: 3px solid transparent;
  transition: all 0.2s ease;
  font-weight: 600;
}
#sidenav a.menu:hover {
  background: #1f2430;
  color: #00d2d3 !important;
  border-left-color: #00d2d3;
}
#sidenav a.menu.active-side {
  background: rgba(0, 210, 211, 0.12);
  color: #00d2d3 !important;
  font-weight: 700;
  border-left-color: #00d2d3;
}
#sidenav a.menu i {
  width: 16px;
  text-align: center;
  font-size: 13px;
}
.nav-badge-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
</style>

<?php if ($id != "") { ?>

<!-- ========================================== -->
<!-- 1. ADMIN / DASHBOARD UTAMA NAVIGATION      -->
<!-- ========================================== -->

<div id="navbar" class="navbar">
  <div class="navbar-left" style="display: flex; align-items: center; gap: 8px;">
    <a id="brand" class="text-center" href="./admin.php?id=sessions" style="margin-right: 4px; font-weight: 900; font-size: 15px; letter-spacing: 0.5px; color: #fff; text-decoration: none;">
      PACENET <span style="background: linear-gradient(135deg, #00d2d3, #0984e3); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 4px;">BILLING</span>
    </a>

    <a id="openNav" class="navbar-hover" href="javascript:void(0)" style="font-size: 16px;"><i class="fa fa-bars"></i></a>
    <a id="closeNav" class="navbar-hover" href="javascript:void(0)" style="font-size: 16px;"><i class="fa fa-bars"></i></a>

    <!-- EXECUTIVE DYNAMIC BREADCRUMB (CONTEXT ONLY, NOT REPEATED BUTTONS) -->
    <div style="display: flex; align-items: center; gap: 6px; margin-left: 8px; font-size: 13px; color: #ced6e0; font-weight: 600;">
      <?php if ($id == 'sessions'): ?>
        <span style="color: #70a1ff;"><i class="fa fa-th-large"></i> Multi-Router Control Panel</span>
      <?php elseif ($id == 'all' || $id == 'all-routers'): ?>
        <span style="color: #7bed9f;"><i class="fa fa-tachometer"></i> Monitoring Seluruh Router (ALL)</span>
      <?php elseif ($id == 'traffic-monitor' || $id == 'traffic_monitor' || $minterface == 'traffic-monitor'): ?>
        <span style="color: #00d2d3;"><i class="fa fa-area-chart"></i> Traffic Monitor (Multi-Router)</span>
      <?php elseif ($id == 'vps-resource' || $id == 'vps_resource'): ?>
        <span style="color: #00d2d3;"><i class="fa fa-server"></i> Grafik Resource VPS Cloud</span>
      <?php elseif ($id == 'settings'): ?>
        <span style="color: #f1c40f;"><i class="fa fa-gear"></i> Pengaturan Sesi</span>
      <?php elseif ($id == 'editor'): ?>
        <span style="color: #e056fd;"><i class="fa fa-edit"></i> Template Editor</span>
      <?php else: ?>
        <span><i class="fa fa-dashboard"></i> <?= htmlspecialchars($mpage ?? 'Dashboard'); ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="navbar-right" style="display: flex; align-items: center; gap: 8px;">
    <!-- LIVE SYSTEM STATUS PILLS -->
    <span class="nav-badge-pill" style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573;" title="Status Server Cloud VPS">
      <i class="fa fa-circle" style="font-size: 7px;"></i> VPS 202.10.46.222
    </span>
    <span class="nav-badge-pill" style="background: rgba(112, 161, 255, 0.15); border: 1px solid #70a1ff; color: #70a1ff;" title="Jumlah Router Terdaftar">
      <i class="fa fa-server"></i> <?= count($adminRouters); ?> Router
    </span>

    <a title="Idle Timeout" style="<?= $didleto; ?>"><span style="width:70px; background: #2f3542; border: 1px solid #57606f;" class="pd-5 radius-3"><i class="fa fa-clock-o mr-1"></i> <span class="mr-1" id="timer"></span></span></a>
    
    <select class="stheme ses text-right pd-5" style="background: #2f3542; color: #fff; border: 1px solid #57606f; border-radius: 4px; height: 28px;">
      <option> <?= $_theme?></option>
      <?php for ($i = 0; $i < count($mtheme); $i++) {
        echo '<option value="'.$url.'&set-theme='.$mtheme[$i],'">'.ucfirst($mtheme[$i]),'</option>';
      }
      ?>
    </select>
    <select class="slang ses text-right pd-5" style="background: #2f3542; color: #fff; border: 1px solid #57606f; border-radius: 4px; height: 28px;">
      <option> <?= $language ?></option>
      <?php 
        $fileList = glob('lang/*');
        foreach($fileList as $filename){
          if(is_file($filename)){
            $filename = substr(explode("/",$filename)[1],0,-4);
            if($filename != "isocodelang"){
              echo '<option value="'.$url.'&setlang=' . $filename . '">'. $isocodelang[$filename]. '</option>'; 
           }   
          }
        }
      ?>
    </select>
    <a id="logout" href="./admin.php?id=logout" style="background: #eb4d4b; color: #fff; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Logout Admin"><i class="fa fa-sign-out"></i> <?= $_logout ?></a>
  </div>
</div>

<!-- ========================================== -->
<!-- 1. ADMIN SIDEBAR (SINGLE NAVIGATION HUB)   -->
<!-- ========================================== -->

<div id="sidenav" class="sidenav">
  <!-- SIDEBAR BRAND HEADER -->
  <div class="side-brand-box">
    <div style="width: 32px; height: 32px; border-radius: 6px; background: linear-gradient(135deg, #00d2d3, #0984e3); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; font-weight: 800;">
      <i class="fa fa-globe"></i>
    </div>
    <div>
      <div style="font-size: 13px; font-weight: 800; color: #fff; line-height: 1.2;">PACENET NOC</div>
      <div style="font-size: 10px; color: #7bed9f; font-weight: 700; display: flex; align-items: center; gap: 4px; margin-top: 2px;">
        <i class="fa fa-circle" style="font-size: 6px;"></i> Central System Active
      </div>
    </div>
  </div>

  <!-- GROUP 1: MONITORING & NETWORK -->
  <div class="side-section-title">DASHBOARD & NETWORK</div>
  <a href="./admin.php?id=sessions" class="menu <?= ($id == 'sessions') ? 'active-side' : ''; ?>">
    <i class="fa fa-th-large" style="color: #70a1ff;"></i> Router List
  </a>
  <a href="./admin.php?id=all" class="menu <?= ($id == 'all' || $id == 'all-routers') ? 'active-side' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center;">
    <span><i class="fa fa-tachometer" style="color: #7bed9f;"></i> Monitoring ALL</span>
    <span class="badge" style="background: #2ed573; color: #1e272e; font-size: 9px; font-weight: 800; padding: 2px 5px; border-radius: 3px;">LIVE</span>
  </a>
  <a href="./admin.php?id=traffic-monitor" class="menu <?= ($id == 'traffic-monitor' || $minterface == 'traffic-monitor') ? 'active-side' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center;">
    <span><i class="fa fa-area-chart" style="color: #00d2d3;"></i> Traffic Monitor</span>
    <span class="badge" style="background: #00d2d3; color: #1e272e; font-size: 9px; font-weight: 800; padding: 2px 5px; border-radius: 3px;">MULTI</span>
  </a>
  <a href="./admin.php?id=vps-resource" class="menu <?= ($id == 'vps-resource' || $id == 'vps_resource') ? 'active-side' : ''; ?>">
    <i class="fa fa-line-chart" style="color: #00d2d3;"></i> Resource Graphs VPS
  </a>

  <!-- GROUP 2: USER & VOUCHER (DATABASE TERPUSAT) -->
  <div class="side-section-title">USER & VOUCHER TERPUSAT</div>
  <a href="./?hotspot=users-by-profile&session=<?= urlencode($masterSession); ?>" class="menu">
    <i class="fa fa-ticket" style="color: #ffa502;"></i> Voucher Hotspot
  </a>
  <a href="./?hotspot=users&profile=all&session=<?= urlencode($masterSession); ?>" class="menu">
    <i class="fa fa-users" style="color: #2ed573;"></i> Data User Hotspot
  </a>
  <a href="./?hotspot=quick-print&session=<?= urlencode($masterSession); ?>" class="menu">
    <i class="fa fa-print" style="color: #a55eea;"></i> Quick Print (Cetak)
  </a>
  <a href="./?hotspot-user=generate&session=<?= urlencode($masterSession); ?>" class="menu">
    <i class="fa fa-user-plus" style="color: #ff6b81;"></i> Generate Voucher
  </a>
  <a href="./?report=selling&idbl=<?= strtolower(date("M")).date("Y"); ?>&session=<?= urlencode($masterSession); ?>" class="menu">
    <i class="fa fa-money" style="color: #f1c40f;"></i> Laporan Pendapatan
  </a>

  <!-- GROUP 3: SISTEM & PENGATURAN -->
  <div class="side-section-title">PENGATURAN SISTEM</div>
  <a href="./admin.php?id=editor&template=default&session=<?= urlencode($masterSession); ?>" class="menu <?= ($id == 'editor') ? 'active-side' : ''; ?>">
    <i class="fa fa-paint-brush" style="color: #45aaf2;"></i> Template Editor
  </a>
  <a href="./admin.php?id=uplogo&session=<?= urlencode($masterSession); ?>" class="menu <?= ($id == 'uplogo') ? 'active-side' : ''; ?>">
    <i class="fa fa-upload" style="color: #fd9644;"></i> Upload Logo Hotspot
  </a>
  <a href="./admin.php?id=settings&session=<?= urlencode($masterSession); ?>" class="menu <?= ($id == 'settings') ? 'active-side' : ''; ?>">
    <i class="fa fa-sliders" style="color: #a4b0be;"></i> Pengaturan Sesi
  </a>
</div>

<script>
$(document).ready(function(){
  $(".connect").click(function(){
    notify("<?= $_connecting ?>");
    connect(this.id)
  });
  $(".stheme").change(function(){
    notify("<?= $_loading_theme ?>");
    stheme(this.value)
  });
  $(".slang").change(function(){
    notify("<?= $_loading ?>");
    stheme(this.value)
  });
});
</script>
<div id="notify"><div class="message"></div></div>
<div id="temp"></div>
<?php 
include('./info.php');
} else { ?>

<!-- ========================================== -->
<!-- 2. ROUTER SESSION NAVIGATION               -->
<!-- ========================================== -->

<div id="navbar" class="navbar">
  <div class="navbar-left" style="display: flex; align-items: center; gap: 8px;">
    <a id="brand" class="text-center" href="./admin.php?id=sessions" style="margin-right: 4px; font-weight: 900; font-size: 15px; letter-spacing: 0.5px; color: #fff; text-decoration: none;">
      PACENET <span style="background: linear-gradient(135deg, #00d2d3, #0984e3); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 4px;">BILLING</span>
    </a>

    <a id="openNav" class="navbar-hover" href="javascript:void(0)" style="font-size: 16px;"><i class="fa fa-bars"></i></a>
    <a id="closeNav" class="navbar-hover" href="javascript:void(0)" style="font-size: 16px;"><i class="fa fa-bars"></i></a>

    <!-- ROUTER SESSION BREADCRUMB -->
    <div style="display: flex; align-items: center; gap: 6px; margin-left: 8px; font-size: 13px; color: #ced6e0; font-weight: 600;">
      <span style="color: #70a1ff;"><i class="fa fa-server"></i> <?= htmlspecialchars($session); ?></span>
      <span style="color: #57606f;">&rsaquo;</span>
      <span style="color: #7bed9f;"><?= htmlspecialchars($mpage); ?></span>
    </div>
  </div>

  <div class="navbar-right" style="display: flex; align-items: center; gap: 8px;">
    <!-- RETURN TO MULTI-ROUTER DASHBOARD -->
    <a href="./admin.php?id=sessions" class="btn btn-sm" style="background: #1e3799; color: #fff; font-weight: 700; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; display: inline-flex; align-items: center; gap: 5px;" title="Kembali ke Dashboard Multi-Router">
      <i class="fa fa-th-large"></i> Router List
    </a>

    <!-- ROUTER SESSION SWITCHER -->
    <select class="connect optfa ses text-right pd-5" style="background: #2f3542; color: #fff; border: 1px solid #57606f; border-radius: 4px; height: 28px; font-weight: 700;">
      <option id="MikhmonSession" value="<?= $session; ?>"><?= $hotspotname; ?> &#x2666;</option>
      <?php
      foreach (file('./include/config.php') as $line) {
        $sesname = explode("'", $line)[1];
        if ($sesname != "" && $sesname != "mikhmon" && $sesname != $session) {
          echo '<option value="' . $sesname. '">'.$sesname.'</option>';
        }
      }
      ?>
    </select>

    <a title="Idle Timeout" style="<?= $didleto; ?>"><span style="width:70px; background: #2f3542; border: 1px solid #57606f;" class="pd-5 radius-3"><i class="fa fa-clock-o mr-1"></i> <span class="mr-1" id="timer"></span></span></a>
    
    <select class="stheme ses text-right pd-5" style="background: #2f3542; color: #fff; border: 1px solid #57606f; border-radius: 4px; height: 28px;">
      <option> <?= $_theme?></option>
      <?php for ($i = 0; $i < count($mtheme); $i++) {
        echo '<option value="'.$url.'&set-theme='.$mtheme[$i],'">'.ucfirst($mtheme[$i]),'</option>';
      }
      ?>
    </select>

    <a id="logout" href="./?hotspot=logout&session=<?= $session; ?>" style="background: #eb4d4b; color: #fff; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;" title="Logout Sesi"><i class="fa fa-sign-out"></i> <?= $_logout ?></a>
  </div>
</div>

<!-- ========================================== -->
<!-- 2. ROUTER SESSION SIDEBAR                  -->
<!-- ========================================== -->

<div id="sidenav" class="sidenav">
  <!-- SIDEBAR ROUTER HEADER -->
  <div class="side-brand-box">
    <div style="width: 32px; height: 32px; border-radius: 6px; background: linear-gradient(135deg, #2ed573, #1e90ff); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; font-weight: 800;">
      <i class="fa fa-server"></i>
    </div>
    <div>
      <div style="font-size: 13px; font-weight: 800; color: #fff; line-height: 1.2;"><?= htmlspecialchars($identity); ?></div>
      <div style="font-size: 10px; color: #7bed9f; font-weight: 700; display: flex; align-items: center; gap: 4px; margin-top: 2px;">
        <i class="fa fa-circle" style="font-size: 6px;"></i> Sesi: <?= htmlspecialchars($session); ?>
      </div>
    </div>
  </div>

  <!-- GROUP 1: GLOBAL DASHBOARDS -->
  <div class="side-section-title">GLOBAL DASHBOARD</div>
  <a href="./admin.php?id=sessions" class="menu">
    <i class="fa fa-th-large" style="color: #70a1ff;"></i> Router List
  </a>
  <a href="./admin.php?id=all" class="menu">
    <i class="fa fa-tachometer" style="color: #7bed9f;"></i> Monitoring ALL Router
  </a>
  <a href="./admin.php?id=traffic-monitor" class="menu <?= ($id == 'traffic-monitor' || $minterface == 'traffic-monitor') ? 'active-side' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center;">
    <span><i class="fa fa-area-chart" style="color: #00d2d3;"></i> Traffic Monitor</span>
    <span class="badge" style="background: #00d2d3; color: #1e272e; font-size: 9px; font-weight: 800; padding: 2px 5px; border-radius: 3px;">MULTI</span>
  </a>
  <a href="./admin.php?id=vps-resource" class="menu">
    <i class="fa fa-server" style="color: #00d2d3;"></i> Grafik Resource VPS
  </a>

  <!-- GROUP 2: KONTROL ROUTER SPESIFIK -->
  <div class="side-section-title">KONTROL ROUTER: <?= htmlspecialchars($session); ?></div>
  <a href="./?session=<?= $session; ?>" class="menu <?= $shome ? 'active-side' : ''; ?>">
    <i class="fa fa-dashboard" style="color: #70a1ff;"></i> Beranda Router
  </a>
  <a href="./?hotspot=active&session=<?= $session; ?>" class="menu <?= $sactive ? 'active-side' : ''; ?>">
    <i class="fa fa-wifi" style="color: #2ed573;"></i> Hotspot Aktif
  </a>
  <a href="./?hotspot=dhcp-leases&session=<?= $session; ?>" class="menu <?= $slease ? 'active-side' : ''; ?>">
    <i class="fa fa-sitemap" style="color: #1e90ff;"></i> DHCP Leases
  </a>
  <a href="./?hotspot=ipbinding&session=<?= $session; ?>" class="menu <?= $sipbind ? 'active-side' : ''; ?>">
    <i class="fa fa-address-book" style="color: #ff9f43;"></i> IP Bindings
  </a>
  <a href="./admin.php?id=traffic-monitor" class="menu <?= $strafficmonitor ? 'active-side' : ''; ?>">
    <i class="fa fa-area-chart" style="color: #00d2d3;"></i> Traffic Monitor
  </a>
  <a href="./?system=resource-graph&session=<?= $session; ?>" class="menu <?= $sresgraph ? 'active-side' : ''; ?>">
    <i class="fa fa-line-chart" style="color: #20bf6b;"></i> Grafik Resource Router
  </a>
  <a href="./admin.php?id=olt_ont&session=<?= $session; ?>" class="menu">
    <i class="fa fa-cubes" style="color: #9b59b6;"></i> OLT / ONT Approval
  </a>

  <!-- GROUP 3: USER & VOUCHER TERPUSAT -->
  <div class="side-section-title">USER & VOUCHER TERPUSAT</div>
  <a href="./?hotspot=users-by-profile&session=<?= $session; ?>" class="menu <?= $susersbp ? 'active-side' : ''; ?>">
    <i class="fa fa-ticket" style="color: #ffa502;"></i> Voucher Hotspot
  </a>
  <a href="./?hotspot=users&profile=all&session=<?= $session; ?>" class="menu <?= $susersl ? 'active-side' : ''; ?>">
    <i class="fa fa-users" style="color: #2ed573;"></i> Daftar User Hotspot
  </a>
  <a href="./?hotspot=user-profiles&session=<?= $session; ?>" class="menu <?= $suserprofiles ? 'active-side' : ''; ?>">
    <i class="fa fa-pie-chart" style="color: #00d2d3;"></i> User Profiles
  </a>
  <a href="./?hotspot=quick-print&session=<?= $session; ?>" class="menu <?= $squick ? 'active-side' : ''; ?>">
    <i class="fa fa-print" style="color: #a55eea;"></i> Quick Print (Cetak)
  </a>
  <a href="./?hotspot-user=generate&session=<?= $session; ?>" class="menu <?= $sgenuser ? 'active-side' : ''; ?>">
    <i class="fa fa-user-plus" style="color: #ff6b81;"></i> Generate Voucher
  </a>
  <a href="./?report=selling&idbl=<?= strtolower(date("M")) . date("Y"); ?>&session=<?= $session; ?>" class="menu <?= $sselling ? 'active-side' : ''; ?>">
    <i class="fa fa-money" style="color: #f1c40f;"></i> Laporan Penjualan
  </a>

  <!-- GROUP 4: SISTEM & PENGATURAN -->
  <div class="side-section-title">PENGATURAN SISTEM</div>
  <a href="./?hotspot=log&session=<?= $session; ?>" class="menu <?= $slog ? 'active-side' : ''; ?>">
    <i class="fa fa-history" style="color: #a4b0be;"></i> Log Aktivitas
  </a>
  <a href="./admin.php?id=settings&session=<?= $session; ?>" class="menu">
    <i class="fa fa-sliders" style="color: #70a1ff;"></i> Pengaturan Sesi
  </a>
  <a href="./?hotspot=template-editor&template=default&session=<?= $session; ?>" class="menu <?= $teditor ? 'active-side' : ''; ?>">
    <i class="fa fa-paint-brush" style="color: #45aaf2;"></i> Template Editor
  </a>
  <a href="./admin.php?id=uplogo&session=<?= $session; ?>" class="menu">
    <i class="fa fa-upload" style="color: #fd9644;"></i> Upload Logo
  </a>
  <a href="./admin.php?id=reboot&session=<?= $session; ?>" class="menu" onclick="return confirm('Apakah Anda yakin ingin me-reboot router <?= htmlspecialchars($session); ?>?');">
    <i class="fa fa-power-off" style="color: #ff4757;"></i> Reboot Router
  </a>
</div>

<script>
$(document).ready(function(){
  $(".connect").change(function(){
    notify("<?= $_connecting ?>");
    connect(this.value)
  });
  $(".stheme").change(function(){
    notify("<?= $_loading_theme ?>");
    stheme(this.value)
  });
});
</script>
<div id="notify"><div class="message"></div></div>
<div id="temp"></div>
<?php 
include('./include/info.php');
} ?>

<div id="main">  
<div id="loading" class="lds-dual-ring"></div>
<?php if($hotspot == 'template-editor' || $id == 'editor'){
echo '<div class="main-container">';
}else{
  echo '<div class="main-container" style="display:none">';
}
?>

