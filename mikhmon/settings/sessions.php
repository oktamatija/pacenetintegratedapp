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

// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

// array color
  $color = array('1' => 'bg-blue', 'bg-indigo', 'bg-purple', 'bg-pink', 'bg-red', 'bg-yellow', 'bg-green', 'bg-teal', 'bg-cyan', 'bg-grey', 'bg-light-blue');

  if (isset($_POST['save'])) {

    $suseradm = ($_POST['useradm']);
    $spassadm = encrypt($_POST['passadm']);
    $logobt = ($_POST['logobt']);
    $qrbt = ($_POST['qrbt']);

    $cari = array('1' => "mikhmon<|<$useradm", "mikhmon>|>$passadm");
    $ganti = array('1' => "mikhmon<|<$suseradm", "mikhmon>|>$spassadm");

    for ($i = 1; $i < 3; $i++) {
      $file = file("./include/config.php");
      $content = file_get_contents("./include/config.php");
      $newcontent = str_replace((string)$cari[$i], (string)$ganti[$i], "$content");
      file_put_contents("./include/config.php", "$newcontent");
    }

  
  $gen = '<?php $qrbt="' . $qrbt . '";?>';
          $key = './include/quickbt.php';
          $handle = fopen($key, 'w') or die('Cannot open file:  ' . $key);
          $data = $gen;
          fwrite($handle, $data);
    echo "<script>window.location='./admin.php?id=sessions'</script>";
  }

}
?>
<script>
  function Pass(id){
    var x = document.getElementById(id);
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
    }}
</script>

<div class="row">
	<div class="col-12">
  	<div class="card">
  		<div class="card-header">
  			<h3 class="card-title"><i class="fa fa-gear"></i> <?= $_admin_settings ?> &nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer " title="Reload data"></i></h3>
  		</div>
      <div class="card-body">
        <!-- PENDING ROUTERS AUTO-DISCOVERY COMPONENT -->
        <div class="row">
          <div class="col-12" style="margin-bottom: 15px;">
            <?php
            // Direct server-side reject handler (guaranteed to execute with full admin session)
            if (isset($_GET['reject_router']) && !empty($_GET['reject_router'])) {
                $rejTarget = trim($_GET['reject_router']);
                $pendingFile = './data/pending_routers.json';
                if (file_exists($pendingFile)) {
                    $rawPending = @file_get_contents($pendingFile);
                    $allPending = json_decode($rawPending, true) ?: array();
                    $updated = array();
                    foreach ($allPending as $p) {
                        $match = false;
                        if (($p['identity'] ?? '') === $rejTarget || ($p['vpn_ip'] ?? '') === $rejTarget || ($p['original_name'] ?? '') === $rejTarget) {
                            $match = true;
                        }
                        if ($match) {
                            if (!empty($p['pubkey'])) {
                                @shell_exec("sudo /usr/bin/wg set wg0 peer " . escapeshellarg($p['pubkey']) . " remove 2>/dev/null");
                                @shell_exec("sudo /usr/bin/wg-quick save wg0 2>/dev/null");
                            }
                        } else {
                            $updated[] = $p;
                        }
                    }
                    @file_put_contents($pendingFile, json_encode($updated, JSON_PRETTY_PRINT));
                }
                echo "<script>window.location='./admin.php?id=sessions';</script>";
                exit;
            }

            $pendingFile = './data/pending_routers.json';
            $pendingData = array();
            if (file_exists($pendingFile)) {
                $rawPending = @file_get_contents($pendingFile);
                $allPending = json_decode($rawPending, true) ?: array();
                foreach ($allPending as $ap) {
                    if (($ap['status'] ?? '') === 'pending') {
                        $pendingData[] = $ap;
                    }
                }
            }
            $pendingCount = count($pendingData);
            ?>

            <?php if ($pendingCount > 0): ?>
              <div class="card" style="border: 2px solid #ffa502; box-shadow: 0 4px 15px rgba(255, 165, 2, 0.25); margin-bottom: 20px;">
                <div class="card-header" style="background: #2f3542; color: #ffa502; display: flex; justify-content: space-between; align-items: center;">
                  <h3 class="card-title" style="margin: 0; font-weight: 700;">
                    <i class="fa fa-plug"></i> Router Baru Meminta Bergabung (<?= $pendingCount ?> Menunggu Persetujuan)
                  </h3>
                  <span class="badge" style="background: #ffa502; color: #2f3542; font-weight: 700; padding: 4px 10px; border-radius: 12px;">Persetujuan Diperlukan</span>
                </div>
                <div class="card-body" style="background: #1e272e; padding: 18px;">
                  <p style="color: #ced6e0; font-size: 13px; margin-bottom: 15px;">
                    <i class="fa fa-info-circle text-warning"></i> Router MikroTik di bawah ini telah terhubung ke cloud WireGuard. Masukkan kredensial admin router dan klik <b>Setujui & Push Konfigurasi</b> untuk mengaktifkan IP 10.0.0.1/22, Hotspot Server, FreeRADIUS AAA, Captive Portal Login Page, dan mendaftarkannya ke Pacenet Billing System secara otomatis.
                  </p>

                  <div class="row">
                    <?php foreach ($pendingData as $idx => $pRouter): ?>
                      <div class="col-12" style="background: #2f3542; border: 1px solid #57606f; border-radius: 6px; padding: 15px; margin-bottom: 15px;" id="pending_box_<?= $idx ?>">
                        <div class="row align-items-center">
                          <div class="col-12 col-md-4" style="margin-bottom: 10px;">
                            <h4 style="margin: 0 0 5px 0; color: #7bed9f; font-weight: 700;">
                              <i class="fa fa-microchip"></i> <?= htmlspecialchars($pRouter['identity']) ?>
                            </h4>
                            <div style="font-size: 12px; color: #a4b0be;">
                              <span><b>IP WireGuard:</b> <span class="text-primary" style="font-weight: 600;"><?= htmlspecialchars($pRouter['vpn_ip']) ?></span></span><br>
                              <span><b>Winbox Remote:</b> 202.10.46.222:<?= htmlspecialchars($pRouter['winbox_port']) ?></span><br>
                              <span><b>Waktu Terhubung:</b> <?= htmlspecialchars($pRouter['created_at']) ?></span>
                            </div>
                          </div>

                          <div class="col-12 col-md-8">
                            <form id="form_accept_<?= $idx ?>" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end;">
                              <input type="hidden" name="identity" value="<?= htmlspecialchars($pRouter['identity']) ?>">
                              <input type="hidden" name="vpn_ip" value="<?= htmlspecialchars($pRouter['vpn_ip']) ?>">
                              <input type="hidden" name="winbox_port" value="<?= htmlspecialchars($pRouter['winbox_port']) ?>">

                              <div style="flex: 1; min-width: 110px;">
                                <label style="font-size: 11px; color: #a4b0be; margin-bottom: 3px; display: block;">User MikroTik</label>
                                <input type="text" name="user" value="admin" class="form-control" style="background: #1e272e; color: #fff; border: 1px solid #57606f; padding: 5px 8px; font-size: 12px; border-radius: 4px;" required>
                              </div>

                              <div style="flex: 1; min-width: 110px;">
                                <label style="font-size: 11px; color: #a4b0be; margin-bottom: 3px; display: block;">Password MikroTik</label>
                                <input type="password" name="pass" placeholder="Password" class="form-control" style="background: #1e272e; color: #fff; border: 1px solid #57606f; padding: 5px 8px; font-size: 12px; border-radius: 4px;">
                              </div>

                              <div style="flex: 1; min-width: 100px;">
                                <label style="font-size: 11px; color: #a4b0be; margin-bottom: 3px; display: block;">Interface Hotspot</label>
                                <input type="text" name="hs_iface" value="Vlan1" class="form-control" style="background: #1e272e; color: #fff; border: 1px solid #57606f; padding: 5px 8px; font-size: 12px; border-radius: 4px;" required>
                              </div>

                              <div style="display: flex; gap: 5px;">
                                <button type="button" class="btn bg-primary btn-sm" onclick="processJoin(<?= $idx ?>, 'accept')" style="padding: 6px 14px; font-weight: 600; border-radius: 4px;" id="btn_accept_<?= $idx ?>">
                                  <i class="fa fa-check-circle"></i> Setujui & Push
                                </button>
                                <a href="./admin.php?id=sessions&reject_router=<?= urlencode($pRouter['vpn_ip'] ?? $pRouter['identity']) ?>" class="btn bg-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin membuang dan menolak permintaan router <?= htmlspecialchars($pRouter['identity']) ?> ini?');" style="padding: 6px 12px; font-weight: 600; border-radius: 4px; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; color: #fff;" title="Buang dan hapus router ini">
                                  <i class="fa fa-trash"></i> Buang
                                </a>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <!-- HOW TO JOIN AUTO-CLI BOX -->
              <div style="background: #1e272e; border: 1px dashed #57606f; padding: 14px 18px; border-radius: 6px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                  <div>
                    <h4 style="margin: 0 0 5px 0; color: #7bed9f; font-weight: 600; font-size: 14px;">
                      <i class="fa fa-magic"></i> Hubungkan Router Baru Otomatis (Zero-Touch Onboarding)
                    </h4>
                    <span style="font-size: 12px; color: #a4b0be;">
                      Untuk menghubungkan router MikroTik baru ke <b>Pacenet Billing System</b>, jalankan perintah CLI berikut di <b>New Terminal</b> MikroTik:
                    </span>
                  </div>
                  <button type="button" class="btn bg-primary btn-sm" onclick="copyAutoCli(this)" style="padding: 5px 14px; font-size: 12px; border-radius: 4px; font-weight: 600;">
                    <i class="fa fa-copy"></i> Salin Perintah CLI
                  </button>
                </div>
                <div style="background: #2f3542; color: #2ed573; padding: 10px 12px; border-radius: 4px; font-family: Consolas, monospace; font-size: 12px; margin-top: 10px; word-break: break-all; border: 1px solid #373e47; line-height: 1.4;" id="cliCommandBox">/tool fetch url="http://202.10.46.222/join.php?action=bootstrap" mode=http dst-path=join.rsc; :delay 2s; /import join.rsc; /file remove join.rsc</div>
                <div style="font-size: 11px; color: #768390; margin-top: 6px;">
                  * Router akan otomatis membuat interface WireGuard, terhubung ke cloud VPN, dan muncul di daftar persetujuan ini secara instan.
                </div>
              </div>
            <?php endif; ?>

          </div>
        </div>

        <script>
        function copyText(text, btn, successMsg) {
          successMsg = successMsg || 'Tersalin!';
          function showSuccess(b, msg) {
            if (b) {
              var $b = $(b);
              var orig = $b.html();
              $b.html('<i class="fa fa-check"></i> ' + msg).css('background', '#2ed573').css('color', '#fff');
              setTimeout(function() {
                $b.html(orig).removeAttr('style');
              }, 2000);
            } else {
              alert(msg);
            }
          }
          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
              showSuccess(btn, successMsg);
            }, fallback);
          } else {
            fallback();
          }
          function fallback() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.top = '0';
            ta.style.left = '0';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
              document.execCommand('copy');
              showSuccess(btn, successMsg);
            } catch(e) {
              prompt('Salin teks secara manual:', text);
            }
            document.body.removeChild(ta);
          }
        }

        function copyAutoCli(btn) {
          var text = $('#cliCommandBox').text().trim();
          copyText(text, btn, 'Perintah CLI Disalin!');
        }

        function processJoin(idx, action) {
          if (action === 'reject') {
            if (!confirm('Apakah Anda yakin ingin menolak dan menghapus permintaan join router ini?')) return;
          }
          var form = $('#form_accept_' + idx);
          var postData = form.serialize() + '&action=' + action;
          var btnAccept = $('#btn_accept_' + idx);
          var btnReject = $('#btn_reject_' + idx);

          btnAccept.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memproses...');
          btnReject.prop('disabled', true);

          $.ajax({
            url: './settings/accept_router.php',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(res) {
              if (res.status === 'success') {
                alert(res.message);
                location.reload();
              } else {
                alert('Gagal: ' + res.message);
                btnAccept.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Setujui & Push');
                btnReject.prop('disabled', false);
              }
            },
            error: function(xhr, status, error) {
              alert('Terjadi kesalahan komunikasi server: ' + error);
              btnAccept.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Setujui & Push');
              btnReject.prop('disabled', false);
            }
          });
        }
        </script>

        <div class="row">
          <!-- ENLARGED ROUTER LIST (COL-12) -->
          <div class="col-12">
            <div class="card" style="border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 1px solid #373e47; margin-bottom: 25px;">
              <div class="card-header" style="background: linear-gradient(135deg, #1e272e 0%, #2f3542 100%); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding: 14px 18px; border-bottom: 1px solid #373e47;">
                <h3 class="card-title" style="margin: 0; font-size: 16px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px;">
                  <i class="fa fa-server" style="color: #70a1ff;"></i> DAFTAR ROUTER TERHUBUNG (MULTI-ROUTER)
                </h3>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                  <button type="button" onclick="loadRouterStats()" class="btn btn-sm" style="background: #2f3542; color: #70a1ff; border: 1px solid #70a1ff; font-weight: 700; font-size: 12px; padding: 6px 14px; border-radius: 5px; cursor: pointer; display: flex; align-items: center; gap: 6px;" title="Refresh Data Real-time">
                    <i class="fa fa-refresh"></i> Refresh
                  </button>
                  <a href="./admin.php?id=settings&router=new-<?= rand(1111,9999) ?>" class="btn btn-sm" style="background: #3742fa; color: #fff; font-weight: 700; font-size: 12px; padding: 6px 14px; border-radius: 5px; text-decoration: none; display: flex; align-items: center; gap: 6px;" title="Tambah Router MikroTik Baru">
                    <i class="fa fa-plus"></i> Tambah Router
                  </a>
                </div>
              </div>
            <div class="card-body" style="padding: 20px 15px; background: #1e272e;">
            <div class="row">

              <!-- NOC EXECUTIVE KPI SUMMARY METRICS (NO DUPLICATE MENUS) -->
              <div class="col-12" style="margin-bottom: 20px;">
                <div class="row" style="margin: 0 -6px;">
                  <!-- KPI 1: Router Terkoneksi -->
                  <div class="col-3 col-m-6 col-s-12" style="padding: 6px;">
                    <div style="background: linear-gradient(135deg, #1b2838 0%, #222f3e 100%); border: 1px solid #2ed573; border-radius: 8px; padding: 14px 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: space-between;">
                      <div>
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #a4b0be; letter-spacing: 0.5px;">Router Terkoneksi</div>
                        <div style="font-size: 24px; font-weight: 900; color: #2ed573; margin: 3px 0 2px 0;">
                          <span id="kpi_online_count"><?= count($data) ?></span> / <span id="kpi_total_count"><?= count($data) ?></span> <span style="font-size: 13px; font-weight: 700; color: #a4b0be;">Online</span>
                        </div>
                        <div style="font-size: 11px; color: #2ed573; display: flex; align-items: center; gap: 5px;">
                          <span style="width: 7px; height: 7px; border-radius: 50%; background: #2ed573; display: inline-block; box-shadow: 0 0 6px #2ed573;"></span> API Port 8728 Aktif
                        </div>
                      </div>
                      <div style="font-size: 32px; color: #2ed573; opacity: 0.8;"><i class="fa fa-server"></i></div>
                    </div>
                  </div>

                  <!-- KPI 2: Total Sesi Hotspot Aktif -->
                  <div class="col-3 col-m-6 col-s-12" style="padding: 6px;">
                    <div style="background: linear-gradient(135deg, #1b2838 0%, #222f3e 100%); border: 1px solid #0984e3; border-radius: 8px; padding: 14px 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: space-between;">
                      <div>
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #a4b0be; letter-spacing: 0.5px;">Total Hotspot Aktif</div>
                        <div style="font-size: 24px; font-weight: 900; color: #70a1ff; margin: 3px 0 2px 0;">
                          <span id="kpi_total_active">...</span> <span style="font-size: 13px; font-weight: 700; color: #a4b0be;">Sesi</span>
                        </div>
                        <div style="font-size: 11px; color: #70a1ff; display: flex; align-items: center; gap: 5px;">
                          <span style="width: 7px; height: 7px; border-radius: 50%; background: #70a1ff; display: inline-block; box-shadow: 0 0 6px #70a1ff;"></span> Live Multi-Router
                        </div>
                      </div>
                      <div style="font-size: 32px; color: #70a1ff; opacity: 0.8;"><i class="fa fa-users"></i></div>
                    </div>
                  </div>

                  <!-- KPI 3: Database Billing Terpusat -->
                  <div class="col-3 col-m-6 col-s-12" style="padding: 6px;">
                    <div style="background: linear-gradient(135deg, #1b2838 0%, #222f3e 100%); border: 1px solid #00d2d3; border-radius: 8px; padding: 14px 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: space-between;">
                      <div>
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #a4b0be; letter-spacing: 0.5px;">Database Billing</div>
                        <div style="font-size: 19px; font-weight: 900; color: #00d2d3; margin: 5px 0 3px 0;">
                          Terpusat & Sinkron
                        </div>
                        <div style="font-size: 11px; color: #00d2d3; display: flex; align-items: center; gap: 5px;">
                          <span style="width: 7px; height: 7px; border-radius: 50%; background: #00d2d3; display: inline-block; box-shadow: 0 0 6px #00d2d3;"></span> FreeRADIUS AAA Ready
                        </div>
                      </div>
                      <div style="font-size: 32px; color: #00d2d3; opacity: 0.8;"><i class="fa fa-database"></i></div>
                    </div>
                  </div>

                  <!-- KPI 4: Cloud Mesh VPN -->
                  <div class="col-3 col-m-6 col-s-12" style="padding: 6px;">
                    <div style="background: linear-gradient(135deg, #1b2838 0%, #222f3e 100%); border: 1px solid #ffa502; border-radius: 8px; padding: 14px 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: space-between;">
                      <div>
                        <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #a4b0be; letter-spacing: 0.5px;">Cloud Mesh VPN</div>
                        <div style="font-size: 19px; font-weight: 900; color: #ffa502; margin: 5px 0 3px 0;">
                          202.10.46.222
                        </div>
                        <div style="font-size: 11px; color: #ffa502; display: flex; align-items: center; gap: 5px;">
                          <span style="width: 7px; height: 7px; border-radius: 50%; background: #ffa502; display: inline-block; box-shadow: 0 0 6px #ffa502;"></span> WireGuard 51820/udp Hub
                        </div>
                      </div>
                      <div style="font-size: 32px; color: #ffa502; opacity: 0.8;"><i class="fa fa-shield"></i></div>
                    </div>
                  </div>
                </div>
              </div>

              <?php
              function isRouterOnline($ip, $port = 8728, $timeout = 0.4) {
                  if (empty($ip)) return false;
                  $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);
                  if ($fp) {
                      fclose($fp);
                      return true;
                  }
                  return false;
              }

              // Determine Public IP for remote Winbox access
              $publicIp = '202.10.46.222';
              if (!empty($_SERVER['SERVER_ADDR']) && filter_var($_SERVER['SERVER_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                  $publicIp = $_SERVER['SERVER_ADDR'];
              }

              // Load iptables PREROUTING port mapping for Winbox behind NAT
              $winboxPortMap = array();
              $iptOut = @shell_exec('sudo /usr/sbin/iptables -t nat -S PREROUTING 2>/dev/null');
              if ($iptOut && preg_match_all('/--dport\s+([0-9]+).*?-j\s+DNAT\s+--to-destination\s+([0-9.]+):8291/', $iptOut, $pMatches, PREG_SET_ORDER)) {
                  foreach ($pMatches as $pm) {
                      $winboxPortMap[$pm[2]] = intval($pm[1]);
                  }
              }

              foreach (file('./include/config.php') as $line) {
                $value = explode("'", $line)[1];
                if ($value == "" || $value == "mikhmon") {
                } else { 
                    $rIp = explode('!', $data[$value][1])[1] ?? '';
                    $rOnline = isRouterOnline($rIp, 8728, 0.4);
                    $rHsName = explode('%', $data[$value][4])[1] ?? $value;
                    
                    // Winbox open port forwarded on public IP
                    if (!empty($winboxPortMap[$rIp])) {
                        $rWinboxPort = $winboxPortMap[$rIp];
                    } else {
                        $octets = explode('.', $rIp);
                        $lastOctet = intval(end($octets));
                        $rWinboxPort = ($lastOctet >= 2) ? (18290 + ($lastOctet - 1)) : 8291;
                    }
                    $rWinboxAddr = $publicIp . ':' . $rWinboxPort;
                    ?>
                    <div class="col-6 col-s-12" style="margin-bottom: 18px;">
                        <div class="box box-bordered" style="background: #23272e; border: 1.5px solid <?= $rOnline ? '#2ed573' : '#ff4757'; ?>; border-radius: 8px; padding: 16px 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.25);">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                  <div>
                                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                      <h3 style="margin: 0; font-weight: 800; color: #fff; font-size: 16px;">
                                        <i class="fa fa-server" style="color: #70a1ff;"></i> <?= htmlspecialchars($rHsName); ?>
                                      </h3>
                                      <?php if ($rOnline): ?>
                                        <span class="badge" style="background: #2ed573; color: #1e272e; font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 10px;">
                                          <i class="fa fa-circle" style="font-size: 8px;"></i> Online
                                        </span>
                                      <?php else: ?>
                                        <span class="badge" style="background: #ff4757; color: #fff; font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 10px;">
                                          <i class="fa fa-circle" style="font-size: 8px;"></i> Offline
                                        </span>
                                      <?php endif; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #a4b0be; line-height: 1.6;">
                                      <span><b>Sesi:</b> <span style="color: #fff;"><?= htmlspecialchars($value); ?></span></span> | 
                                      <span><b>IP Router:</b> <span style="color: #70a1ff; font-weight: 700;"><?= htmlspecialchars($rIp); ?></span></span><br>
                                      <span><b>Winbox:</b> <code style="color: #7bed9f; font-weight: 800; font-size: 13px;"><?= htmlspecialchars($rWinboxAddr); ?></code></span>
                                    </div>
                                  </div>

                                  <!-- PROMINENT ACTIVE HOTSPOT SESSIONS BADGE -->
                                  <div style="text-align: right;">
                                    <?php if ($rOnline): ?>
                                      <div id="active_badge_<?= $value ?>" style="background: rgba(46, 213, 115, 0.15); border: 1.5px solid #2ed573; border-radius: 6px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 8px;">
                                        <i class="fa fa-wifi" style="color: #2ed573; font-size: 16px;"></i>
                                        <div style="text-align: left;">
                                          <div style="font-size: 9px; color: #a4b0be; text-transform: uppercase; font-weight: 700; line-height: 1;">HOTSPOT AKTIF</div>
                                          <div style="font-size: 18px; font-weight: 900; color: #2ed573; line-height: 1.2;">
                                            <span id="active_count_<?= $value ?>" class="active_count_val">...</span>
                                            <span style="font-size: 11px; font-weight: 700; color: #ced6e0;">User</span>
                                          </div>
                                        </div>
                                      </div>
                                    <?php else: ?>
                                      <div style="background: rgba(255, 71, 87, 0.1); border: 1px solid #ff4757; border-radius: 6px; padding: 6px 10px; color: #ff6b81; font-size: 11px; font-weight: 700;">
                                        <i class="fa fa-plug"></i> Terputus
                                      </div>
                                    <?php endif; ?>
                                  </div>
                                </div>

                                <!-- HARDWARE & LIVE STATS ROW -->
                                <div id="hw_info_<?= $value ?>" style="font-size: 11px; color: #a4b0be; background: #1e272e; padding: 6px 10px; border-radius: 4px; border: 1px solid #373e47; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                                  <span><i class="fa fa-microchip" style="color: #70a1ff;"></i> Memuat informasi router...</span>
                                  <span id="wan_quick_<?= $value ?>" style="color: #7bed9f; font-weight: 700;"></span>
                                </div>


                                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #373e47; padding-top: 12px; margin-top: 6px; flex-wrap: wrap; gap: 8px;">
                                  <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <a href="winbox:<?= htmlspecialchars($rWinboxAddr); ?>" class="btn btn-sm" style="background: #3742fa; color: #fff; padding: 5px 10px; font-size: 11px; border-radius: 4px; text-decoration: none; font-weight: 700;" title="Buka di Winbox (<?= htmlspecialchars($rWinboxAddr); ?>)">
                                      <i class="fa fa-desktop"></i> Winbox
                                    </a>
                                    <button type="button" class="btn btn-sm" style="background: #57606f; color: #fff; padding: 5px 10px; font-size: 11px; border-radius: 4px;" onclick="copyText('<?= htmlspecialchars($rWinboxAddr); ?>', this, 'Winbox Disalin!')" title="Salin IP Publik & Port Winbox">
                                      <i class="fa fa-copy"></i> Salin
                                    </button>
                                    <?php if ($rOnline): ?>
                                    <a href="./?system=resource-graph&session=<?= $value; ?>" class="btn btn-sm" style="background: #20bf6b; color: #fff; padding: 5px 10px; font-size: 11px; border-radius: 4px; text-decoration: none; font-weight: 600;" title="Buka Grafik Resource (CPU, RAM, Disk, Bandwidth)">
                                      <i class="fa fa-line-chart"></i> Graphs
                                    </a>
                                    <?php endif; ?>
                                  </div>
                                  <div>
                                    <span class="connect pointer btn btn-sm bg-primary" id="<?= $value; ?>" style="padding: 5px 14px; font-size: 12px; border-radius: 4px; font-weight: 800;"><i class="fa fa-external-link"></i> <?= $_open ?></span>&nbsp;
                                    <a href="./admin.php?id=settings&session=<?= $value; ?>" class="btn btn-sm btn-secondary" style="padding: 5px 9px; font-size: 11px; border-radius: 4px;" title="Pengaturan Sesi"><i class="fa fa-edit"></i></a>&nbsp;
                                    <a href="javascript:void(0)" onclick="if(confirm('Apakah Anda yakin ingin menghapus router <?= $value; ?>?')){loadpage('./admin.php?id=remove-session&session=<?= $value; ?>')}" class="btn btn-sm btn-danger" style="padding: 5px 9px; font-size: 11px; border-radius: 4px;" title="Hapus Router"><i class="fa fa-trash"></i></a>
                                  </div>
                                </div>
                          </div>
                      </div>
              <?php
            }
          }
          ?>
              </div>
            </div>
          </div>
        </div>

        <!-- BOTTOM ROW: ADMIN SETTINGS & CLOUD VPS QUICK STATUS -->
        <div class="row">
          <div class="col-6 col-s-12">
            <form autocomplete="off" method="post" action="">
              <div class="card" style="border-radius: 8px; border: 1px solid #373e47; overflow: hidden;">
                <div class="card-header" style="background: #1e272e; border-bottom: 1px solid #373e47;">
                  <h3 class="card-title" style="color: #fff; font-weight: 700; font-size: 14px;"><i class="fa fa-user-circle"></i> <?= $_admin ?> Settings</h3>
                </div>
                <div class="card-body">
                  <table class="table table-sm">
                    <tr>
                      <td class="align-middle"><?= $_user_name ?> </td><td><input class="form-control" id="useradm" type="text" size="10" name="useradm" title="User Admin" value="<?= $useradm; ?>" required="1"/></td>
                    </tr>
                    <tr>
                      <td class="align-middle"><?= $_password ?> </td>
                      <td>
                      <div class="input-group">
                      <div class="input-group-11 col-box-10">
                            <input class="group-item group-item-l" id="passadm" type="password" size="10" name="passadm" title="Password Admin" value="<?= decrypt($passadm); ?>" required="1"/>
                          </div>
                            <div class="input-group-1 col-box-2">
                              <div class="group-item group-item-r pd-2p5 text-center align-middle">
                                  <input title="Show/Hide Password" type="checkbox" onclick="Pass('passadm')">
                              </div>
                            </div>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td class="align-middle"><?= $_quick_print ?> QR</td>
                      <td>
                        <select class="form-control" name="qrbt">
                        <option><?= $qrbt ?></option>
                          <option>enable</option>
                          <option>disable</option>
                        </select>
                      </td>
                    </tr>
                    <tr>
                      <td></td><td class="text-right">
                          <div class="input-group-4">
                              <input class="group-item group-item-l" type="submit" style="cursor: pointer;" name="save" value="<?= $_save ?>"/>
                            </div>
                            <div class="input-group-2">
                              <div style="cursor: pointer;" class="group-item group-item-r pd-2p5 text-center" onclick="location.reload();" title="Reload Data"><i class="fa fa-refresh"></i></div>
                            </div>
                            </div>
                      </td>
                    </tr>
                  </table>
                  <div id="loadV">v<?= $_SESSION['v']; ?> </div>
                  <div><b id="newVer" class="text-green"></b></div>
                </div>
              </div>
            </form>
          </div>

          <div class="col-6 col-s-12">
            <div class="card" style="border-radius: 8px; border: 1px solid #373e47; overflow: hidden; background: #23272e;">
              <div class="card-header" style="background: #1e272e; border-bottom: 1px solid #373e47; display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title" style="color: #fff; font-weight: 700; font-size: 14px;"><i class="fa fa-cloud" style="color: #00d2d3;"></i> Server VPS Cloud Pacenet</h3>
                <span class="badge" style="background: #2ed573; color: #1e272e; font-weight: 800; font-size: 10px; padding: 2px 7px; border-radius: 10px;">Master Cloud</span>
              </div>
              <div class="card-body" style="padding: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                  <div>
                    <div style="font-size: 11px; color: #a4b0be; text-transform: uppercase; font-weight: 700;">IP Publik VPS</div>
                    <div style="font-size: 18px; font-weight: 800; color: #00d2d3;"><?= $publicIp; ?></div>
                  </div>
                  <div style="text-align: right;">
                    <a href="./admin.php?id=vps-resource" class="btn btn-sm" style="background: #00d2d3; color: #1e272e; font-weight: 800; font-size: 11px; padding: 5px 12px; border-radius: 4px; text-decoration: none;">
                      <i class="fa fa-line-chart"></i> Buka Grafik VPS
                    </a>
                  </div>
                </div>
                <div style="font-size: 12px; color: #a4b0be; line-height: 1.6; background: #1e272e; padding: 10px 14px; border-radius: 6px; border: 1px solid #373e47;">
                  <div><i class="fa fa-check-circle" style="color: #2ed573;"></i> WireGuard Cloud Hub: <b>10.10.10.1 (51820/udp)</b></div>
                  <div><i class="fa fa-check-circle" style="color: #2ed573;"></i> Multi-Router Central Billing & AAA Database Terpusat</div>
                  <div><i class="fa fa-check-circle" style="color: #2ed573;"></i> Port Forwarding Winbox Terbuka untuk Semua Router di Balik NAT</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <script>
        // Real-time active sessions & router health loader
        $(document).ready(function() {
          loadRouterStats();
          setInterval(loadRouterStats, 5000);
        });

        function loadRouterStats() {
          $.ajax({
            url: './settings/router_stats_api.php',
            type: 'GET',
            dataType: 'json',
            timeout: 6000,
            success: function(res) {
              if (res.status === 'success' && res.routers) {
                if (res.summary) {
                  $('#kpi_total_active').text(res.summary.total_active_sessions.toLocaleString());
                  $('#kpi_online_count').text(res.summary.routers_online);
                  $('#kpi_total_count').text(res.summary.routers_total);
                }
                $.each(res.routers, function(i, r) {
                  if (r.online) {
                    $('#active_count_' + r.session).text(r.active_sessions.toLocaleString());
                    var hwText = '<i class="fa fa-microchip" style="color: #70a1ff;"></i> <b>' + r.board_name + '</b> (v' + r.ros_version + ') | CPU: <b>' + r.cpu_load + '%</b> | Up: ' + r.uptime;
                    $('#hw_info_' + r.session).html(hwText);

                    if (r.wan_interfaces && r.wan_interfaces.length > 0) {
                      var wanSummary = '<i class="fa fa-globe" style="color: #00d2d3;"></i> WAN: ' + r.wan_interfaces[0].name + ' (' + r.wan_interfaces[0].rx_human + ')';
                      if (r.wan_interfaces.length > 1) {
                        wanSummary += ' + ' + r.wan_interfaces[1].name + ' (LB)';
                      }
                      $('#wan_quick_' + r.session).html(wanSummary);
                    }
                  } else {
                    $('#active_count_' + r.session).text('0');
                    $('#hw_info_' + r.session).html('<i class="fa fa-exclamation-triangle" style="color: #ff4757;"></i> Offline / Tidak terhubung');
                  }
                });
              }
            }
          });
        }
        </script>
</div>
</div>
</div>
</div>
<script>
  var _0x7470=["\x68\x6F\x73\x74\x6E\x61\x6D\x65","\x6C\x6F\x63\x61\x74\x69\x6F\x6E","\x2E","\x73\x70\x6C\x69\x74","\x6D\x69\x6B\x68\x6D\x6F\x6E\x2E\x6F\x6E\x6C\x69\x6E\x65","\x78\x62\x61\x6E\x2E\x78\x79\x7A","\x6C\x6F\x67\x61\x6D\x2E\x69\x64","\x6D\x69\x6E\x69\x73\x2E\x69\x64","\x69\x6E\x64\x65\x78\x4F\x66","\x3C\x73\x70\x61\x6E\x20\x3E\x3C\x69\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x77\x68\x69\x74\x65\x20\x66\x61\x20\x66\x61\x2D\x69\x6E\x66\x6F\x2D\x63\x69\x72\x63\x6C\x65\x22\x3E\x3C\x2F\x69\x3E\x20\x3C\x61\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x62\x6C\x75\x65\x22\x20\x68\x72\x65\x66\x3D\x22\x2E\x2F\x61\x64\x6D\x69\x6E\x2E\x70\x68\x70\x3F\x69\x64\x3D\x61\x62\x6F\x75\x74\x22\x3E\x43\x68\x65\x63\x6B\x20\x55\x70\x64\x61\x74\x65\x3C\x2F\x61\x3E\x3C\x2F\x73\x70\x61\x6E\x3E","\x68\x74\x6D\x6C","\x23\x6E\x65\x77\x56\x65\x72","\x68\x74\x74\x70\x73\x3A\x2F\x2F\x72\x61\x77\x2E\x67\x69\x74\x68\x75\x62\x75\x73\x65\x72\x63\x6F\x6E\x74\x65\x6E\x74\x2E\x63\x6F\x6D\x2F\x6C\x61\x6B\x73\x61\x31\x39\x2F\x6D\x69\x6B\x68\x6D\x6F\x6E\x76\x33\x2F\x6D\x61\x73\x74\x65\x72\x2F\x76\x65\x72\x73\x6F\x6E\x2E\x74\x78\x74\x3F\x74\x3D","\x72\x61\x6E\x64\x6F\x6D","\x66\x6C\x6F\x6F\x72","\x76","\x76\x65\x72\x73\x69\x6F\x6E","","\x72\x65\x70\x6C\x61\x63\x65","\x69\x6E\x6E\x65\x72\x48\x54\x4D\x4C","\x6C\x6F\x61\x64\x56","\x67\x65\x74\x45\x6C\x65\x6D\x65\x6E\x74\x42\x79\x49\x64","\x20","\x75\x70\x64\x61\x74\x65\x64","\x2D","\x4E\x65\x77\x20\x56\x65\x72\x73\x69\x6F\x6E\x20","\x3C\x62\x72\x3E\x3C\x73\x70\x61\x6E\x20\x3E\x3C\x69\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x77\x68\x69\x74\x65\x20\x66\x61\x20\x66\x61\x2D\x69\x6E\x66\x6F\x2D\x63\x69\x72\x63\x6C\x65\x22\x3E\x3C\x2F\x69\x3E\x20\x3C\x61\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x62\x6C\x75\x65\x22\x20\x68\x72\x65\x66\x3D\x22\x2E\x2F\x61\x64\x6D\x69\x6E\x2E\x70\x68\x70\x3F\x69\x64\x3D\x61\x62\x6F\x75\x74\x22\x3E\x43\x68\x65\x63\x6B\x20\x55\x70\x64\x61\x74\x65\x3C\x2F\x61\x3E\x3C\x2F\x73\x70\x61\x6E\x3E","\x67\x65\x74\x4A\x53\x4F\x4E"];var hname=window[_0x7470[1]][_0x7470[0]];var dom=hname[_0x7470[3]](_0x7470[2])[1]+ _0x7470[2]+ hname[_0x7470[3]](_0x7470[2])[2];var domArray=[_0x7470[4],_0x7470[5],_0x7470[6],_0x7470[7]];var a=domArray[_0x7470[8]](hname);var b=domArray[_0x7470[8]](dom);if(dom== _0x7470[4]){$(_0x7470[11])[_0x7470[10]](_0x7470[9])}else {if(a> 0|| b> 0){}else {$[_0x7470[27]](_0x7470[12]+ (Math[_0x7470[14]]((Math[_0x7470[13]]()* 999999999)+ 1))* 128,function(_0xc1b4x6){getNewVer= (_0xc1b4x6[_0x7470[16]])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4x7=parseInt(getNewVer[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4x8=document[_0x7470[21]](_0x7470[20])[_0x7470[19]];var _0xc1b4x9=(_0xc1b4x8[_0x7470[3]](_0x7470[22])[0])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4xa=parseInt(_0xc1b4x9[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4xb=(_0xc1b4x7- _0xc1b4xa);getNewVer= (_0xc1b4x6[_0x7470[16]])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4x7=parseInt(getNewVer[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4x8=document[_0x7470[21]](_0x7470[20])[_0x7470[19]];var _0xc1b4x9=(_0xc1b4x8[_0x7470[3]](_0x7470[22])[0])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4xa=parseInt(_0xc1b4x9[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4xb=(_0xc1b4x7- _0xc1b4xa);getNewD= (_0xc1b4x6[_0x7470[23]])[_0x7470[3]](_0x7470[22])[0];newD= parseInt((getNewD)[_0x7470[3]](_0x7470[24])[2]+ (getNewD)[_0x7470[3]](_0x7470[24])[0]+ (getNewD)[_0x7470[3]](_0x7470[24])[1]);var _0xc1b4xc=parseInt((_0xc1b4x8[_0x7470[3]](_0x7470[22])[1])[_0x7470[3]](_0x7470[24])[2]+ (_0xc1b4x8[_0x7470[3]](_0x7470[22])[1])[_0x7470[3]](_0x7470[24])[0]+ (_0xc1b4x8[_0x7470[3]](_0x7470[22])[1][_0x7470[3]](_0x7470[24]))[1]);var _0xc1b4xd=(newD- _0xc1b4xc);if(_0xc1b4xb> 0|| _0xc1b4xd> 0){$(_0x7470[11])[_0x7470[10]](_0x7470[25]+ _0xc1b4x6[_0x7470[16]]+ _0x7470[22]+ _0xc1b4x6[_0x7470[23]]+ _0x7470[26])}})}}
</script>









