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

error_reporting(0);

ob_start("ob_gzhandler");

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
} else {
  
  date_default_timezone_set($_SESSION['timezone'] ?? 'Asia/Jayapura');
  
  // load session MikroTik
  $session = $_GET['session'];

  // load config
  include('../include/config.php');
  include('../include/readcfg.php');
  include('../lib/formatbytesbites.php');

  $id = $_GET['id'] ?? '';
  $qr = $_GET['qr'] ?? 'no';
  $small = $_GET['small'] ?? 'no';
  $userp = $_GET['user'] ?? '';
  $paper = $_GET['paper'] ?? '';

  require('../lib/routeros_api.class.php');
  $API = new RouterosAPI();
  $API->debug = false;
  $API->connect($iphost, $userhost, decrypt($passwdhost));

  if ($userp != "") {
    $usermode = explode('-', $userp)[0];
    $pulluser = explode('-', $userp);
    $iuser = count($pulluser);
    $prefix = explode('-', $userp)[$iuser - 2];
    $user = explode('-', $userp)[$iuser - 1];
    if ($iuser == 3) {
      $user = $prefix . "-" . $user;
    } else {
      $user = $user;
    }
    $getuser = $API->comm("/ip/hotspot/user/print", array("?name" => "$user"));
    $TotalReg = count($getuser);
  } elseif ($id != "") {
    $usermode = explode('-', $id)[0];
    $getuser = $API->comm('/ip/hotspot/user/print', array("?comment" => "$id", "?uptime" => "0s"));
    $TotalReg = count($getuser);
  }
  
  $getuprofile = $getuser[0]['profile'] ?? 'default';

  $getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$getuprofile"));
  $getsharedu = $getprofile[0]['shared-users'];
  $ponlogin = $getprofile[0]['on-login'];
  $validity = explode(",", $ponlogin)[3];
  $getprice = explode(",", $ponlogin)[2];
  $getsprice = explode(",", $ponlogin)[4];

  if ($getsprice == "0" && $getprice != "0") {
    if (in_array($currency, $cekindo['indo'])) {
      $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
    } else {
      $price = $currency . " " . number_format((float)$getprice, 2);
    }
  } else if ($getsprice != "0") {
    if (in_array($currency, $cekindo['indo'])) {
      $price = $currency . " " . number_format((float)$getsprice, 0, ",", ".");
    } else {
      $price = $currency . " " . number_format((float)$getsprice, 2);
    }
  } else if ($getsprice == "0") {
    $price = "";
  }

  $logo = "../img/logo-" . $session . ".png";
  if (file_exists($logo)) {
    $logo = "../img/logo-" . $session . ".png?t=" . str_replace(" ", "_", date("Y-m-d H:i:s"));
  } else {
    $logo = "../img/logo.png?t=" . str_replace(" ", "_", date("Y-m-d H:i:s"));
  }

  $pagesCountF4 = ceil($TotalReg / 55);
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Voucher-<?= $hotspotname . "-" . $getuprofile . "-" . $id; ?></title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta http-equiv="pragma" content="no-cache" />
  <link rel="icon" href="../img/favicon.png" />
  <link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
  <script src="../js/qrious.min.js"></script>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      color: #000000;
      background-color: #f1f5f9;
      font-size: 14px;
      font-family: 'Helvetica', Arial, sans-serif;
      margin: 0px;
      -webkit-print-color-adjust: exact;
      padding-top: 65px;
    }

    /* Standard Voucher Table */
    table.voucher {
      display: inline-block;
      border: 2px solid black;
      margin: 2px;
      background: #fff;
    }

    /* Print Adjustment Toolbar (Screen Only) */
    .print-adjustment-bar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 52px;
      background: #0f172a;
      color: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 16px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.35);
      z-index: 999999;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      font-size: 12.5px;
    }
    .bar-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .bar-title {
      font-weight: 700;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 6px;
      color: #38bdf8;
    }
    .bar-badge {
      background: #334155;
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 11px;
      color: #cbd5e1;
      font-weight: 600;
    }
    .bar-center {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .control-group {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .control-group label {
      color: #94a3b8;
      font-size: 11.5px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .bar-select {
      background: #1e293b;
      border: 1px solid #475569;
      color: #f1f5f9;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      outline: none;
    }
    .bar-select:focus {
      border-color: #38bdf8;
    }
    .btn-scale-group {
      display: flex;
      align-items: center;
      background: #1e293b;
      border: 1px solid #475569;
      border-radius: 6px;
      overflow: hidden;
    }
    .btn-scale {
      background: transparent;
      border: none;
      color: #f1f5f9;
      width: 26px;
      height: 26px;
      cursor: pointer;
      font-weight: bold;
      font-size: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .btn-scale:hover {
      background: #334155;
    }
    #scale-label {
      padding: 0 6px;
      font-size: 11.5px;
      font-weight: 700;
      color: #38bdf8;
      min-width: 44px;
      text-align: center;
    }
    .bar-right {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .btn-print {
      background: #0284c7;
      color: #fff;
      border: none;
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 12.5px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: background 0.15s;
    }
    .btn-print:hover {
      background: #0369a1;
    }
    .btn-close {
      background: #475569;
      color: #fff;
      border: none;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .btn-close:hover {
      background: #64748b;
    }

    /* Screen Preview Container for F4 Sheets */
    @media screen {
      .f4-page {
        margin: 20px auto;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.18);
        background: #fff;
      }
      .default-container {
        max-width: 1000px;
        margin: 15px auto;
        padding: 15px;
        background: #fff;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        border-radius: 6px;
      }
    }

    /* ========================================================
       EXACT F4 (FOLIO) GRID LAYOUT - 55 VOUCHERS (5 COL x 11 ROW)
       Paper: 215mm x 330mm
       ======================================================== */
    .f4-page {
      width: 215mm;
      height: 330mm;
      max-height: 330mm;
      padding: 3.5mm 4mm;
      overflow: hidden;
      display: grid;
      grid-template-columns: repeat(5, 40.2mm);
      grid-template-rows: repeat(11, 28.6mm);
      grid-gap: 1.2mm 1.2mm;
      box-sizing: border-box;
      background: #fff;
    }

    .v-f4 {
      width: 100%;
      height: 100%;
      border: 1px dashed #333;
      border-radius: 2px;
      padding: 1.5mm 1.5mm 1mm 1.5mm;
      background: #fff;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
      position: relative;
      page-break-inside: avoid;
      break-inside: avoid;
      box-sizing: border-box;
    }

    .v-f4-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 0.6px solid #222;
      padding-bottom: 0.8mm;
      line-height: 1.1;
    }
    .v-f4-title {
      font-size: 7.5pt;
      font-weight: 800;
      color: #000;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      letter-spacing: -0.2px;
    }
    .v-f4-phone {
      font-size: 5.5pt;
      font-weight: 700;
      color: #111;
      white-space: nowrap;
      line-height: 1;
    }
    .v-f4-num {
      font-size: 5.5pt;
      color: #555;
      font-weight: normal;
    }

    .v-f4-body {
      text-align: center;
      padding: 0.5mm 0;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      flex: 1;
    }
    .v-f4-label {
      font-size: 6pt;
      font-weight: 800;
      text-transform: uppercase;
      color: #000;
      letter-spacing: 0.4px;
      margin-bottom: 0.4mm;
      line-height: 1;
    }
    .v-f4-code {
      display: block;
      width: 100%;
      border: 1.6px solid #000;
      background: #fff;
      font-size: 13pt;
      font-weight: 900;
      letter-spacing: 1.5px;
      font-family: Arial, 'Segoe UI', 'Helvetica Neue', Consolas, sans-serif;
      padding: 1.2mm 0;
      line-height: 1.1;
      border-radius: 2px;
      color: #000;
      box-sizing: border-box;
    }
    .v-f4-code-qr {
      font-size: 10.5pt;
      padding: 0.8mm 0;
      letter-spacing: 1px;
    }
    .v-f4-up {
      display: flex;
      justify-content: space-between;
      gap: 1mm;
      width: 100%;
    }
    .v-f4-up-box {
      flex: 1;
      border: 1.4px solid #000;
      font-family: Arial, 'Segoe UI', sans-serif;
      padding: 0.6mm 0.4mm;
      border-radius: 2px;
    }
    .v-f4-val {
      font-size: 9.5pt;
      font-weight: 900;
      color: #000;
      letter-spacing: 0.5px;
    }

    .v-f4-footer {
      border-top: 0.6px solid #222;
      padding-top: 0.5mm;
      line-height: 1.1;
    }
    .v-f4-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 6.5pt;
      font-weight: 800;
    }
    .v-f4-price {
      color: #000;
    }
    .v-f4-dns {
      font-size: 5.2pt;
      color: #333;
      text-align: center;
      margin-top: 0.3mm;
      font-weight: 600;
    }

    .v-f4 .qrcode {
      width: 20mm !important;
      height: 20mm !important;
    }

    #num {
      float: right;
      display: inline-block;
    }
    .qrc {
      width: 30px;
      height: 30px;
      margin-top: 1px;
    }

    /* Print Media Rules */
    <?php if ($paper == 'f4') { ?>
    @page {
      size: 215mm 330mm;
      margin: 0;
    }
    <?php } else { ?>
    @page {
      size: auto;
      margin-left: 7mm;
      margin-right: 3mm;
      margin-top: 9mm;
      margin-bottom: 3mm;
    }
    <?php } ?>

    @media print {
      body {
        padding-top: 0 !important;
        background: transparent !important;
      }
      .no-print, .print-adjustment-bar {
        display: none !important;
      }
      .f4-page {
        margin: 0 !important;
        box-shadow: none !important;
        page-break-after: always;
        break-after: page;
      }
      .default-container {
        padding: 0 !important;
        box-shadow: none !important;
        margin: 0 !important;
      }
      table { page-break-after: auto; }
      tr    { page-break-inside: avoid; page-break-after: auto; }
      td    { page-break-inside: avoid; page-break-after: auto; }
      thead { display: table-header-group; }
      tfoot { display: table-footer-group; }
    }
  </style>
</head>
<body onload="<?= ($paper == 'f4' || !empty($_GET['autoprint'])) ? 'window.print()' : 'window.print()'; ?>">

  <!-- TOP PRINT ADJUSTMENT TOOLBAR -->
  <div class="print-adjustment-bar no-print">
    <div class="bar-left">
      <span class="bar-title"><i class="fa fa-sliders"></i> Pengaturan Cetak</span>
      <span class="bar-badge" id="voucher-stats">Total: <?= $TotalReg; ?> Voucher<?= ($paper == 'f4') ? " ({$pagesCountF4} Lembar F4)" : ""; ?></span>
    </div>
    <div class="bar-center">
      <div class="control-group">
        <label>Ukuran Kertas:</label>
        <select id="sel-paper" class="bar-select" onchange="changeFormat(this.value)">
          <option value="f4" <?= ($paper == 'f4') ? 'selected' : ''; ?>>F4 / Folio (55 Voucher / Lembar)</option>
          <option value="default" <?= ($paper != 'f4' && $small != 'yes') ? 'selected' : ''; ?>>Default (A4 - 220px)</option>
          <option value="small" <?= ($small == 'yes') ? 'selected' : ''; ?>>Small (160px)</option>
        </select>
      </div>

      <div class="control-group">
        <label>Garis Potong:</label>
        <select id="sel-border" class="bar-select" onchange="changeBorder(this.value)">
          <option value="dashed">Garis Putus (Dashed)</option>
          <option value="solid">Garis Solid</option>
          <option value="dotted">Titik (Dotted)</option>
          <option value="none">Tanpa Garis</option>
        </select>
      </div>

      <div class="control-group">
        <label>QR Code:</label>
        <select id="sel-qr" class="bar-select" onchange="toggleQR(this.value)">
          <option value="no" <?= ($qr != 'yes') ? 'selected' : ''; ?>>Tanpa QR</option>
          <option value="yes" <?= ($qr == 'yes') ? 'selected' : ''; ?>>Pakai QR</option>
        </select>
      </div>

      <div class="control-group">
        <label>Skala:</label>
        <div class="btn-scale-group">
          <button type="button" class="btn-scale" onclick="adjustScale(-0.02)" title="Perkecil">-</button>
          <span id="scale-label">100%</span>
          <button type="button" class="btn-scale" onclick="adjustScale(0.02)" title="Perbesar">+</button>
        </div>
      </div>
    </div>
    <div class="bar-right">
      <button type="button" class="btn-print" onclick="window.print()"><i class="fa fa-print"></i> Cetak Sekarang</button>
      <button type="button" class="btn-close" onclick="window.close()"><i class="fa fa-times"></i> Tutup</button>
    </div>
  </div>

  <!-- VOUCHER CONTENT -->
  <?php if ($paper == 'f4') { ?>
    <?php
    // Chunk vouchers into pages of exactly 55
    $chunks = array_chunk($getuser, 55);
    $globalIndex = 0;
    foreach ($chunks as $chunkIndex => $pageVouchers) {
    ?>
      <div class="f4-page">
        <?php 
        foreach ($pageVouchers as $regtable) {
          $globalIndex++;
          $num = $globalIndex;
          $uid = str_replace("=", "", base64_encode($regtable['.id']));
          $username = $regtable['name'];
          $password = $regtable['password'];
          $profile = $regtable['profile'];
          $timelimit = $regtable['limit-uptime'];
          $getdatalimit = $regtable['limit-bytes-total'];
          $datalimit = ($getdatalimit == 0) ? "" : formatBytes($getdatalimit, 2);
          
          $urilogin = "http://$dnsname/login?username=$username&password=$password";
          $qrcode = "
          <canvas class='qrcode' id='".$uid."'></canvas>
          <script>
            (function() {
              new QRious({
                element: document.getElementById('".$uid."'),
                value: '".$urilogin."',
                size: '128'
              });
            })();
          </script>
          ";
          include('./template-f4.php');
        }
        ?>
      </div>
    <?php } ?>
  <?php } else { ?>
    <div class="default-container">
      <?php for ($i = 0; $i < $TotalReg; $i++) {
        $regtable = $getuser[$i];
        $uid = str_replace("=", "", base64_encode($regtable['.id']));
        $username = $regtable['name'];
        $password = $regtable['password'];
        $profile = $regtable['profile'];
        $timelimit = $regtable['limit-uptime'];
        $getdatalimit = $regtable['limit-bytes-total'];
        $datalimit = ($getdatalimit == 0) ? "" : formatBytes($getdatalimit, 2);
        
        $urilogin = "http://$dnsname/login?username=$username&password=$password";
        $qrcode = "
        <canvas class='qrcode' id='".$uid."'></canvas>
        <script>
          (function() {
            new QRious({
              element: document.getElementById('".$uid."'),
              value: '".$urilogin."',
              size: '256'
            });
          })();
        </script>
        ";
        $num = $i + 1;
        if ($userp != "") {
          include('./template-thermal.php');
        } else {
          if ($small == "yes") {
            include('./template-small.php');
          } else {
            include('./template.php');
          }
        }
      } ?>
    </div>
  <?php } ?>

  <script>
    var currentScale = 1.0;

    function changeFormat(fmt) {
      var url = new URL(window.location.href);
      if (fmt === 'f4') {
        url.searchParams.set('paper', 'f4');
        url.searchParams.delete('small');
      } else if (fmt === 'small') {
        url.searchParams.delete('paper');
        url.searchParams.set('small', 'yes');
      } else {
        url.searchParams.delete('paper');
        url.searchParams.delete('small');
      }
      window.location.href = url.toString();
    }

    function toggleQR(val) {
      var url = new URL(window.location.href);
      url.searchParams.set('qr', val);
      window.location.href = url.toString();
    }

    function changeBorder(style) {
      var vouchers = document.querySelectorAll('.v-f4, table.voucher');
      vouchers.forEach(function(v) {
        if (style === 'none') {
          v.style.border = 'none';
        } else {
          v.style.border = '1px ' + style + ' #222';
        }
      });
      localStorage.setItem('mikhmon_voucher_border', style);
    }

    function adjustScale(delta) {
      currentScale = Math.round((currentScale + delta) * 100) / 100;
      if (currentScale < 0.75) currentScale = 0.75;
      if (currentScale > 1.25) currentScale = 1.25;
      document.getElementById('scale-label').innerText = Math.round(currentScale * 100) + '%';
      var targets = document.querySelectorAll('.f4-page, .default-container');
      targets.forEach(function(p) {
        p.style.transform = 'scale(' + currentScale + ')';
        p.style.transformOrigin = 'top center';
      });
      localStorage.setItem('mikhmon_voucher_scale', currentScale);
    }

    window.addEventListener('DOMContentLoaded', function() {
      var savedBorder = localStorage.getItem('mikhmon_voucher_border');
      if (savedBorder && document.getElementById('sel-border')) {
        document.getElementById('sel-border').value = savedBorder;
        changeBorder(savedBorder);
      }
      var savedScale = localStorage.getItem('mikhmon_voucher_scale');
      if (savedScale && document.getElementById('scale-label')) {
        currentScale = parseFloat(savedScale);
        document.getElementById('scale-label').innerText = Math.round(currentScale * 100) + '%';
        adjustScale(0);
      }
    });
  </script>
</body>
</html>
