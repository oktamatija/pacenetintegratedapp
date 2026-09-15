<?php
/*
 *  Pacenet Multi-Router Traffic Monitor
 *  Simultaneous real-time and historical bandwidth monitoring for all registered routers.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}

include_once(__DIR__ . '/../include/config.php');
include_once(__DIR__ . '/../include/readcfg.php');

$allRouters = array();
foreach ($data as $sessKey => $cfg) {
    if ($sessKey === 'mikhmon' || empty($sessKey)) continue;
    $ip = explode('!', $cfg[1] ?? '')[1] ?? '';
    if (empty($ip) || strpos($sessKey, 'new-') === 0) continue;
    $hsName = explode('%', $cfg[4] ?? '')[1] ?? $sessKey;
    if (empty($hsName)) $hsName = $sessKey;
    $allRouters[$sessKey] = array(
        'session' => $sessKey,
        'name' => $hsName,
        'ip' => $ip
    );
}

$activeSession = $_GET['session'] ?? 'all';
?>

<!-- Explicitly ensure Highcharts is loaded -->
<script src="./js/highcharts/highcharts.js"></script>
<script src="./js/highcharts/themes/hc.<?= !empty($theme) ? $theme : 'dark'; ?>.js"></script>

<style>
.tm-filter-pill {
  background: #22272e;
  color: #a4b0be;
  border: 1px solid #373e47;
  padding: 6px 14px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 700;
  font-size: 12px;
  transition: all 0.2s ease;
  user-select: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.tm-filter-pill:hover {
  background: #2f3542;
  color: #ffffff;
  border-color: #57606f;
}
.tm-filter-pill.active {
  background: linear-gradient(135deg, #0984e3 0%, #00d2d3 100%);
  color: #ffffff;
  border-color: #00d2d3;
  box-shadow: 0 2px 10px rgba(0, 210, 211, 0.35);
}
.tm-tab-btn {
  background: #22272e;
  color: #ced6e0;
  border: 1px solid #373e47;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 700;
  font-size: 12px;
  transition: all 0.2s ease;
  margin-right: 6px;
  margin-bottom: 8px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  user-select: none;
}
.tm-tab-btn:hover {
  background: #2f3542;
  color: #ffffff;
}
.tm-tab-btn.active {
  background: #3742fa;
  color: #ffffff;
  border-color: #3742fa;
  box-shadow: 0 2px 8px rgba(55, 66, 250, 0.4);
}
.router-traffic-card {
  background: #1e2430;
  border: 1.5px solid #373e47;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 15px;
  transition: all 0.25s ease;
  box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}
.router-traffic-card:hover {
  border-color: #00d2d3;
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 210, 211, 0.2);
}
.traffic-speed-val {
  font-size: 22px;
  font-weight: 900;
  line-height: 1.2;
}
.speed-bar-bg {
  background: #2f3542;
  height: 6px;
  border-radius: 3px;
  overflow: hidden;
  margin-top: 6px;
}
.speed-bar-fill-rx {
  background: linear-gradient(90deg, #2ed573, #00d2d3);
  height: 100%;
  width: 0%;
  transition: width 0.5s ease;
}
.speed-bar-fill-tx {
  background: linear-gradient(90deg, #1e90ff, #70a1ff);
  height: 100%;
  width: 0%;
  transition: width 0.5s ease;
}
#trafficChartContainer {
  width: 100%;
  height: 400px;
  min-height: 400px;
}
</style>

<div class="row">
  <div class="col-12">
    <div class="card" style="border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 1px solid #373e47; margin-bottom: 25px;">
      
      <!-- TOP HEADER & CONTROLS -->
      <div class="card-header" style="background: linear-gradient(135deg, #1e272e 0%, #2f3542 100%); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #373e47;">
        <div>
          <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px;">
            <i class="fa fa-area-chart" style="color: #00d2d3;"></i> TRAFFIC MONITOR MULTI-ROUTER
            <span class="badge" style="background: #2ed573; color: #1e272e; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 4px;">SEMUA ROUTER</span>
          </h3>
          <div style="font-size: 12px; color: #a4b0be; margin-top: 3px;">
            Pemantauan bandwidth real-time & riwayat penggunaan seluruh router MikroTik sekaligus tanpa perlu berpindah sesi.
          </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 6px; background: #1e272e; padding: 5px 12px; border-radius: 6px; border: 1px solid #57606f;">
            <label style="font-size: 11px; color: #a4b0be; margin: 0;">Refresh:</label>
            <select id="tm_refresh_rate" style="background: #2f3542; color: #fff; border: 1px solid #57606f; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 700;">
              <option value="2000">2 detik</option>
              <option value="3000" selected>3 detik</option>
              <option value="5000">5 detik</option>
              <option value="10000">10 detik</option>
            </select>
          </div>
          <button id="btn_toggle_poll" class="btn btn-sm" style="background: #3742fa; color: #fff; font-weight: 700; border-radius: 5px; padding: 6px 14px; font-size: 12px;">
            <i class="fa fa-pause"></i> Jeda
          </button>
          <a href="./admin.php?id=sessions" class="btn btn-sm" style="background: #57606f; color: #fff; font-weight: 700; border-radius: 5px; padding: 6px 14px; font-size: 12px; text-decoration: none;">
            <i class="fa fa-th-large"></i> Router List
          </a>
        </div>
      </div>

      <div class="card-body" style="padding: 20px 18px; background: #1a1e24;">
        
        <!-- ROUTER SELECTION PILLS (INSTANT ON-PAGE SWITCHING WITHOUT DROPDOWN) -->
        <div class="row" style="margin-bottom: 18px;">
          <div class="col-12" style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
            <span style="font-size: 12px; font-weight: 800; color: #ced6e0; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 4px;">
              <i class="fa fa-sliders"></i> Filter Tampilan:
            </span>
            <div class="tm-filter-pill active" data-router="all">
              <i class="fa fa-globe"></i> ★ SEMUA ROUTER SEKALIGUS
            </div>
            <?php foreach ($allRouters as $sKey => $r): ?>
              <div class="tm-filter-pill" data-router="<?= htmlspecialchars($sKey); ?>">
                <i class="fa fa-server"></i> <?= htmlspecialchars($r['name']); ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- 4 EXECUTIVE KPI SUMMARY CARDS -->
        <div class="row" style="margin-bottom: 20px;">
          <!-- KPI 1: TOTAL DOWNLOAD (RX) LIVE -->
          <div class="col-3 col-m-6 col-s-12">
            <div style="background: linear-gradient(135deg, #1b2838 0%, #1e3799 100%); border-radius: 8px; padding: 16px; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: 1px solid #2ed573;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #a4b0be; letter-spacing: 0.5px;">Total Download (Rx)</div>
                  <div id="kpi_total_rx" style="font-size: 24px; font-weight: 900; line-height: 1.2; margin: 3px 0; color: #2ed573;">⬇ -- bps</div>
                  <div style="font-size: 11px; color: #7bed9f;">Live multi-router incoming</div>
                </div>
                <div style="font-size: 34px; color: #2ed573; opacity: 0.7;"><i class="fa fa-arrow-circle-down"></i></div>
              </div>
            </div>
          </div>

          <!-- KPI 2: TOTAL UPLOAD (TX) LIVE -->
          <div class="col-3 col-m-6 col-s-12">
            <div style="background: linear-gradient(135deg, #1b2838 0%, #1e3799 100%); border-radius: 8px; padding: 16px; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: 1px solid #1e90ff;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #a4b0be; letter-spacing: 0.5px;">Total Upload (Tx)</div>
                  <div id="kpi_total_tx" style="font-size: 24px; font-weight: 900; line-height: 1.2; margin: 3px 0; color: #70a1ff;">⬆ -- bps</div>
                  <div style="font-size: 11px; color: #70a1ff;">Live multi-router outgoing</div>
                </div>
                <div style="font-size: 34px; color: #1e90ff; opacity: 0.7;"><i class="fa fa-arrow-circle-up"></i></div>
              </div>
            </div>
          </div>

          <!-- KPI 3: TOTAL THROUGHPUT REAL-TIME -->
          <div class="col-3 col-m-6 col-s-12">
            <div style="background: linear-gradient(135deg, #1b2838 0%, #2f3542 100%); border-radius: 8px; padding: 16px; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: 1px solid #00d2d3;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #a4b0be; letter-spacing: 0.5px;">Total Throughput WAN</div>
                  <div id="kpi_total_throughput" style="font-size: 24px; font-weight: 900; line-height: 1.2; margin: 3px 0; color: #00d2d3;">-- bps</div>
                  <div style="font-size: 11px; color: #00d2d3;">Akumulasi seluruh WAN</div>
                </div>
                <div style="font-size: 34px; color: #00d2d3; opacity: 0.7;"><i class="fa fa-exchange"></i></div>
              </div>
            </div>
          </div>

          <!-- KPI 4: ROUTER STATUS -->
          <div class="col-3 col-m-6 col-s-12">
            <div style="background: linear-gradient(135deg, #1b2838 0%, #2f3542 100%); border-radius: 8px; padding: 16px; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: 1px solid #ffa502;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; color: #a4b0be; letter-spacing: 0.5px;">Status Router Terdaftar</div>
                  <div id="kpi_router_status" style="font-size: 24px; font-weight: 900; line-height: 1.2; margin: 3px 0; color: #ffa502;">
                    <?= count($allRouters); ?> Router
                  </div>
                  <div style="font-size: 11px; color: #2ed573; display: flex; align-items: center; gap: 5px;">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #2ed573; display: inline-block;"></span> Multi-Router Live Sync
                  </div>
                </div>
                <div style="font-size: 34px; color: #ffa502; opacity: 0.7;"><i class="fa fa-server"></i></div>
              </div>
            </div>
          </div>
        </div>

        <!-- PERIOD NAVIGATION TABS -->
        <div class="row" style="margin-bottom: 15px;">
          <div class="col-12" style="display: flex; flex-wrap: wrap;">
            <div class="tm-tab-btn active" data-period="realtime"><i class="fa fa-bolt"></i> Live Real-time (Semua Router)</div>
            <div class="tm-tab-btn" data-period="hourly"><i class="fa fa-clock-o"></i> Per Jam (24 Jam)</div>
            <div class="tm-tab-btn" data-period="daily"><i class="fa fa-calendar"></i> Per Hari (30 Hari)</div>
            <div class="tm-tab-btn" data-period="weekly"><i class="fa fa-calendar-check-o"></i> Mingguan (12 Minggu)</div>
            <div class="tm-tab-btn" data-period="monthly"><i class="fa fa-calendar-o"></i> Bulanan (12 Bulan)</div>
            <div class="tm-tab-btn" data-period="yearly"><i class="fa fa-line-chart"></i> Tahunan</div>
          </div>
        </div>

        <!-- MAIN CHART AREA -->
        <div class="row" style="margin-bottom: 25px;">
          <div class="col-12">
            <div style="background: #1e2430; border: 1px solid #373e47; border-radius: 8px; padding: 16px;">
              <div id="chartLoading" style="display: none; text-align: center; padding: 80px 0; color: #ced6e0;">
                <i class="fa fa-spinner fa-spin fa-3x" style="color: #00d2d3; margin-bottom: 12px;"></i>
                <p style="font-size: 14px; font-weight: 600;">Memuat data grafik multi-router...</p>
              </div>
              <div id="trafficChartContainer"></div>
            </div>
          </div>
        </div>

        <!-- SIMULTANEOUS LIVE TRAFFIC CARDS FOR ALL REGISTERED ROUTERS -->
        <div id="liveRoutersSection" class="row">
          <div class="col-12" style="margin-bottom: 12px;">
            <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #ced6e0; display: flex; align-items: center; gap: 8px;">
              <i class="fa fa-cubes" style="color: #00d2d3;"></i>
              PANTAUAN BANDWIDTH REAL-TIME SETIAP ROUTER (SEKALIGUS)
            </h4>
          </div>

          <?php foreach ($allRouters as $sKey => $r): ?>
            <div class="col-4 col-m-6 col-s-12 router-col" id="col_router_<?= htmlspecialchars($sKey); ?>">
              <div class="router-traffic-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                  <div>
                    <h4 style="margin: 0; font-size: 15px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 6px;">
                      <i class="fa fa-server" style="color: #70a1ff;"></i> <?= htmlspecialchars($r['name']); ?>
                    </h4>
                    <div style="font-size: 11px; color: #a4b0be; margin-top: 3px;">
                      IP: <b style="color: #70a1ff;"><?= htmlspecialchars($r['ip']); ?></b> | Sesi: <?= htmlspecialchars($sKey); ?>
                    </div>
                  </div>
                  <span id="badge_status_<?= htmlspecialchars($sKey); ?>" class="badge" style="background: #2ed573; color: #1e272e; font-weight: 800; font-size: 10px; padding: 3px 8px; border-radius: 4px;">
                    ONLINE
                  </span>
                </div>

                <!-- DOWNLOAD SPEED -->
                <div style="margin-bottom: 10px;">
                  <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #a4b0be;">
                    <span><i class="fa fa-arrow-down" style="color: #2ed573;"></i> Download (Rx)</span>
                    <span id="card_rx_<?= htmlspecialchars($sKey); ?>" class="traffic-speed-val" style="color: #2ed573;">-- bps</span>
                  </div>
                  <div class="speed-bar-bg">
                    <div id="bar_rx_<?= htmlspecialchars($sKey); ?>" class="speed-bar-fill-rx"></div>
                  </div>
                </div>

                <!-- UPLOAD SPEED -->
                <div style="margin-bottom: 12px;">
                  <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #a4b0be;">
                    <span><i class="fa fa-arrow-up" style="color: #1e90ff;"></i> Upload (Tx)</span>
                    <span id="card_tx_<?= htmlspecialchars($sKey); ?>" class="traffic-speed-val" style="color: #70a1ff; font-size: 18px;">-- bps</span>
                  </div>
                  <div class="speed-bar-bg">
                    <div id="bar_tx_<?= htmlspecialchars($sKey); ?>" class="speed-bar-fill-tx"></div>
                  </div>
                </div>

                <!-- WAN INTERFACES & HARDWARE INFO -->
                <div style="font-size: 11px; color: #8c98a4; border-top: 1px solid #2f3542; padding-top: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
                  <div id="card_wan_<?= htmlspecialchars($sKey); ?>">
                    <i class="fa fa-globe" style="color: #00d2d3;"></i> WAN: Memuat...
                  </div>
                  <div id="card_hw_<?= htmlspecialchars($sKey); ?>">
                    <i class="fa fa-microchip" style="color: #ffa502;"></i> CPU: --
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- BREAKDOWN TABLE (FOR PERIODIC HISTORICAL VIEWS) -->
        <div id="tableSection" class="row" style="display: none; margin-top: 25px;">
          <div class="col-12">
            <h4 style="margin-bottom: 12px; font-weight: 800; color: #fff;"><i class="fa fa-table" style="color: #00d2d3;"></i> Rincian Penggunaan Bandwidth Seluruh Router</h4>
            <div class="table-responsive" style="background: #1e2430; border-radius: 8px; border: 1px solid #373e47; overflow: hidden;">
              <table class="table table-bordered table-hover" id="periodTable" style="width: 100%; margin-bottom: 0;">
                <thead>
                  <tr style="background: #2f3542; color: #fff; font-size: 12px;">
                    <th>Router MikroTik</th>
                    <th>Periode / Tanggal</th>
                    <th>Download (Rx)</th>
                    <th>Upload (Tx)</th>
                    <th>Total Bandwidth</th>
                    <th>Porsi (%)</th>
                  </tr>
                </thead>
                <tbody id="periodTableBody" style="font-size: 12px;">
                  <tr><td colspan="6" style="text-align: center; color: #a4b0be; padding: 20px;">Memuat rincian bandwidth...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
  var chart = null;
  var liveTimer = null;
  var isPollingActive = true;
  var currentRouter = "all";
  var currentPeriod = "realtime";
  var pollIntervalMs = 3000;

  // Configure Highcharts
  if (typeof Highcharts !== 'undefined') {
    Highcharts.setOptions({
      global: { useUTC: false }
    });
  }

  // Format bps helper
  function fmtBps(bps) {
    bps = Math.max(0, parseFloat(bps) || 0);
    var units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    var i = (bps > 0) ? Math.floor(Math.log(bps) / Math.log(1000)) : 0;
    i = Math.min(i, units.length - 1);
    var val = (bps > 0) ? (bps / Math.pow(1000, i)) : bps;
    return val.toFixed(1) + ' ' + units[i];
  }

  // 1. REAL-TIME MULTI-ROUTER CHART
  function initRealtimeChart() {
    if (liveTimer) { clearInterval(liveTimer); liveTimer = null; }
    $('#tableSection').hide();
    $('#liveRoutersSection').fadeIn(200);
    $('#chartLoading').hide();
    $('#trafficChartContainer').show();

    if (chart) {
      try { chart.destroy(); } catch(e) {}
      chart = null;
    }

    if (typeof Highcharts === 'undefined') {
      $('#trafficChartContainer').html('<div class="alert alert-danger" style="margin:20px; text-align:center;">Highcharts library gagal dimuat.</div>');
      return;
    }

    var chartTitle = (currentRouter === 'all') 
      ? 'Live Bandwidth Real-time: Seluruh Router (Total Jaringan)' 
      : 'Live Bandwidth Real-time: Router ' + currentRouter;

    chart = new Highcharts.Chart({
      chart: {
        renderTo: 'trafficChartContainer',
        type: 'areaspline',
        backgroundColor: 'transparent'
      },
      title: {
        text: chartTitle,
        style: { color: '#adbac7', fontSize: '15px', fontWeight: 'bold' }
      },
      xAxis: {
        type: 'datetime',
        tickPixelInterval: 140,
        maxZoom: 20 * 1000
      },
      yAxis: {
        minPadding: 0.2,
        maxPadding: 0.2,
        title: { text: null },
        labels: {
          formatter: function () {
            return fmtBps(this.value);
          }
        }
      },
      series: [
        { name: 'Download (Rx)', data: [], color: '#2ed573' },
        { name: 'Upload (Tx)', data: [], color: '#1e90ff' }
      ],
      tooltip: {
        formatter: function () {
          var s = '<b>Waktu: ' + Highcharts.dateFormat('%H:%M:%S', new Date(this.x)) + '</b><br/>';
          $.each(this.points, function (i, point) {
            s += '<span style="color:' + point.series.color + '">●</span> ' + point.series.name + ': <b>' + fmtBps(point.y) + '</b><br/>';
          });
          return s;
        },
        shared: true
      }
    });

    pollRealtime();
  }

  function scheduleNextPoll() {
    if (liveTimer) clearTimeout(liveTimer);
    if (isPollingActive && currentPeriod === 'realtime') {
      liveTimer = setTimeout(pollRealtime, pollIntervalMs);
    }
  }

  function pollRealtime() {
    if (!isPollingActive || currentPeriod !== 'realtime') return;

    $.ajax({
      url: './settings/router_stats_api.php',
      type: 'GET',
      dataType: 'json',
      timeout: 10000,
      success: function(res) {
        if (res && res.status === 'success') {
          // 1. Update Top Summary Cards
          if (res.summary) {
            var totRx = res.summary.total_rx_bps || 0;
            var totTx = res.summary.total_tx_bps || 0;
            var totBps = totRx + totTx;

            $('#kpi_total_rx').text('⬇ ' + res.summary.total_rx_human);
            $('#kpi_total_tx').text('⬆ ' + res.summary.total_tx_human);
            $('#kpi_total_throughput').text(fmtBps(totBps));
            $('#kpi_router_status').text(res.summary.routers_online + ' / ' + res.summary.routers_total + ' Online');

            // 2. Feed Real-time Chart
            if (chart && chart.series && chart.series.length >= 2) {
              var x = (new Date()).getTime();
              var shift = chart.series[0].data.length > 30;

              var plotRx = totRx;
              var plotTx = totTx;

              // If filtered to a specific router
              if (currentRouter !== 'all' && res.routers) {
                $.each(res.routers, function(i, r) {
                  if (r.session === currentRouter) {
                    plotRx = r.total_rx_bps || 0;
                    plotTx = r.total_tx_bps || 0;
                  }
                });
              }

              chart.series[0].addPoint([x, plotRx], true, shift);
              chart.series[1].addPoint([x, plotTx], true, shift);
            }
          }

          // 3. Update Simultaneous Per-Router Live Cards
          if (res.routers && res.routers.length > 0) {
            $.each(res.routers, function(i, r) {
              var sKey = r.session;
              var rRx = r.total_rx_bps || 0;
              var rTx = r.total_tx_bps || 0;

              $('#card_rx_' + sKey).text('⬇ ' + fmtBps(rRx));
              $('#card_tx_' + sKey).text('⬆ ' + fmtBps(rTx));

              // Visual bar percentage (cap at 100Mbps for bar visual scaling)
              var rxPct = Math.min(100, Math.max(3, (rRx / 100000000) * 100));
              var txPct = Math.min(100, Math.max(3, (rTx / 50000000) * 100));
              $('#bar_rx_' + sKey).css('width', rxPct + '%');
              $('#bar_tx_' + sKey).css('width', txPct + '%');

              if (r.online) {
                $('#badge_status_' + sKey).removeClass('bg-danger').addClass('badge').css({'background': '#2ed573', 'color': '#1e272e'}).text('ONLINE');
                
                if (r.wan_interfaces && r.wan_interfaces.length > 0) {
                  var wanStr = '<i class="fa fa-globe" style="color: #00d2d3;"></i> WAN: ' + r.wan_interfaces[0].name;
                  if (r.wan_interfaces.length > 1) {
                    wanStr += ' + ' + r.wan_interfaces[1].name + ' (LB)';
                  }
                  $('#card_wan_' + sKey).html(wanStr);
                }
                $('#card_hw_' + sKey).html('<i class="fa fa-microchip" style="color: #ffa502;"></i> CPU: <b>' + r.cpu_load + '%</b> | ' + r.board_name);
              } else {
                $('#badge_status_' + sKey).css({'background': '#ff4757', 'color': '#fff'}).text('OFFLINE');
                $('#card_rx_' + sKey).text('0 bps');
                $('#card_tx_' + sKey).text('0 bps');
                $('#bar_rx_' + sKey).css('width', '0%');
                $('#bar_tx_' + sKey).css('width', '0%');
                $('#card_wan_' + sKey).html('<i class="fa fa-exclamation-triangle" style="color: #ff4757;"></i> Offline');
              }
            });
          }
        }
        scheduleNextPoll();
      },
      error: function() {
        scheduleNextPoll();
      }
    });
  }

  // 2. PERIODIC HISTORICAL DATA (Hourly, Daily, Weekly, Monthly, Yearly)
  function loadPeriodicData(period) {
    if (liveTimer) { clearInterval(liveTimer); liveTimer = null; }
    $('#liveRoutersSection').hide();
    $('#tableSection').fadeIn(200);
    $('#trafficChartContainer').hide();
    $('#chartLoading').show();

    var periodLabels = {
      'hourly': 'Bandwidth Per Jam (24 Jam Terakhir)',
      'daily': 'Bandwidth Harian (30 Hari Terakhir)',
      'weekly': 'Bandwidth Mingguan (12 Minggu Terakhir)',
      'monthly': 'Bandwidth Bulanan (12 Bulan Terakhir)',
      'yearly': 'Bandwidth Tahunan'
    };

    var reqUrl = './traffic/traffic_api.php?router=' + encodeURIComponent(currentRouter) + '&period=' + encodeURIComponent(period);

    $.ajax({
      url: reqUrl,
      type: 'GET',
      dataType: 'json',
      cache: false,
      success: function(res) {
        $('#chartLoading').hide();
        $('#trafficChartContainer').show();

        if (!res || !res.categories) {
          $('#trafficChartContainer').html('<div class="alert alert-warning" style="margin:20px; text-align:center;">Data bandwidth belum tersedia untuk periode ini.</div>');
          return;
        }

        // Update Top Summary Cards
        if (res.summary) {
          $('#kpi_total_rx').text('⬇ ' + res.summary.total_rx_formatted);
          $('#kpi_total_tx').text('⬆ ' + res.summary.total_tx_formatted);
          $('#kpi_total_throughput').text(res.summary.grand_total_formatted);
          $('#kpi_router_status').text(res.summary.configured_routers_count + ' Router Terdaftar');
        }

        if (chart) {
          try { chart.destroy(); } catch(e) {}
          chart = null;
        }

        // Prepare Series for Highcharts
        var chartSeries = [];
        if (currentRouter === 'all' && res.router_series && res.router_series.length > 0) {
          // Show stacked / multi-column for each router
          $.each(res.router_series, function(idx, s) {
            chartSeries.push({
              name: s.name,
              data: s.data,
              color: s.color
            });
          });
        } else {
          // Show Rx and Tx
          chartSeries = [
            { name: 'Download (Rx)', data: res.rx_series, color: '#2ed573' },
            { name: 'Upload (Tx)', data: res.tx_series, color: '#1e90ff' }
          ];
        }

        var chartTitle = (periodLabels[period] || period);
        if (currentRouter !== 'all') {
          chartTitle += ' - Router: ' + currentRouter;
        } else {
          chartTitle += ' - Seluruh Router';
        }

        chart = new Highcharts.Chart({
          chart: {
            renderTo: 'trafficChartContainer',
            type: 'column',
            backgroundColor: 'transparent'
          },
          title: {
            text: chartTitle,
            style: { color: '#adbac7', fontSize: '15px', fontWeight: 'bold' }
          },
          xAxis: {
            categories: res.categories,
            crosshair: true
          },
          yAxis: {
            min: 0,
            title: { text: 'Total Data (' + res.unit + ')' }
          },
          tooltip: {
            headerFormat: '<span style="font-size:11px">{point.key}</span><table>',
            pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
              '<td style="padding:0"><b>{point.y:.2f} ' + res.unit + '</b></td></tr>',
            footerFormat: '</table>',
            shared: true,
            useHTML: true
          },
          plotOptions: {
            column: {
              pointPadding: 0.15,
              borderWidth: 0,
              borderRadius: 3
            }
          },
          series: chartSeries
        });

        // Populate Table
        var tbody = '';
        if (res.table && res.table.length > 0) {
          $.each(res.table, function(i, row) {
            var rowStyle = row.is_total_row ? 'background: rgba(0, 210, 211, 0.12); font-weight: bold;' : '';
            var rName = row.is_total_row ? '<span style="color: #00d2d3;">' + row.router_name + '</span>' : '<i class="fa fa-server" style="color: #70a1ff;"></i> ' + row.router_name;
            tbody += '<tr style="' + rowStyle + '">' +
              '<td>' + rName + '</td>' +
              '<td><b>' + row.period + '</b></td>' +
              '<td style="color:#2ed573; font-weight: 700;">' + row.rx_formatted + '</td>' +
              '<td style="color:#1e90ff; font-weight: 700;">' + row.tx_formatted + '</td>' +
              '<td><b>' + row.total_formatted + '</b></td>' +
              '<td><span class="badge" style="background:#3742fa; color:#fff; padding:3px 8px; border-radius:3px; font-weight:700;">' + row.percentage + '</span></td>' +
              '</tr>';
          });
        } else {
          tbody = '<tr><td colspan="6" style="text-align:center; color:#888; padding:20px;">Tidak ada riwayat traffic pada periode ini</td></tr>';
        }
        $('#periodTableBody').html(tbody);
      },
      error: function(xhr, status, err) {
        $('#chartLoading').hide();
        $('#trafficChartContainer').show();
        $('#trafficChartContainer').html('<div class="alert alert-danger" style="margin:20px; text-align:center;">Gagal mengambil data riwayat traffic: ' + err + '</div>');
      }
    });
  }

  function refreshView() {
    if (currentPeriod === 'realtime') {
      initRealtimeChart();
    } else {
      loadPeriodicData(currentPeriod);
    }
  }

  // Router Filter Pills Click
  $('.tm-filter-pill').click(function() {
    $('.tm-filter-pill').removeClass('active');
    $(this).addClass('active');
    currentRouter = $(this).data('router');

    // Filter live cards visibility
    if (currentRouter === 'all') {
      $('.router-col').show();
    } else {
      $('.router-col').hide();
      $('#col_router_' + currentRouter).fadeIn(150);
    }

    refreshView();
  });

  // Period Tabs Click
  $('.tm-tab-btn').click(function() {
    $('.tm-tab-btn').removeClass('active');
    $(this).addClass('active');
    currentPeriod = $(this).data('period');
    refreshView();
  });

  // Refresh Rate Change
  $('#tm_refresh_rate').change(function() {
    pollIntervalMs = parseInt($(this).val()) || 3000;
    if (currentPeriod === 'realtime') {
      if (liveTimer) { clearInterval(liveTimer); }
      liveTimer = setInterval(pollRealtime, pollIntervalMs);
    }
  });

  // Toggle Polling
  $('#btn_toggle_poll').click(function() {
    isPollingActive = !isPollingActive;
    if (isPollingActive) {
      $(this).html('<i class="fa fa-pause"></i> Jeda').css('background', '#3742fa');
      pollRealtime();
    } else {
      $(this).html('<i class="fa fa-play"></i> Lanjut').css('background', '#2ed573');
    }
  });

  // Initial load
  refreshView();
});
</script>
