<?php
/**
 * Unified Multi-Router "ALL" Dashboard
 * Antigravity IDE - Pacenet Billing System
 */
if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}
?>

<div class="row">
  <div class="col-12">
    <!-- TOP HEADER -->
    <div style="background: linear-gradient(135deg, #1e272e 0%, #2f3542 100%); border-radius: 8px; padding: 18px 22px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 1px solid #57606f;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
          <h2 style="margin: 0 0 6px 0; font-size: 20px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-tachometer" style="color: #00d2d3;"></i> MONITORING SELURUH ROUTER (ALL)
            <span class="badge" style="background: #2ed573; color: #1e272e; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 4px;">LIVE MULTI-ROUTER</span>
          </h2>
          <div style="font-size: 12px; color: #a4b0be;">
            Statistik terpusat seluruh router MikroTik, total sesi hotspot aktif, dan pemantauan bandwidth WAN real-time (termasuk jalur Load Balancing).
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
          <div style="display: flex; align-items: center; gap: 6px; background: #1e272e; padding: 6px 12px; border-radius: 6px; border: 1px solid #57606f;">
            <label style="font-size: 11px; color: #a4b0be; margin: 0;">Refresh:</label>
            <select id="sel_refresh" style="background: #2f3542; color: #fff; border: 1px solid #57606f; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
              <option value="1500">1.5 detik</option>
              <option value="2500" selected>2.5 detik</option>
              <option value="5000">5 detik</option>
              <option value="10000">10 detik</option>
            </select>
          </div>
          <button id="btn_toggle_refresh" class="btn btn-sm" style="background: #3742fa; color: #fff; font-weight: 700; border-radius: 6px; padding: 6px 14px; font-size: 12px;">
            <i class="fa fa-pause"></i> Jeda
          </button>
          <a href="./admin.php?id=sessions" class="btn btn-sm" style="background: #57606f; color: #fff; font-weight: 600; border-radius: 6px; padding: 6px 14px; font-size: 12px; text-decoration: none;">
            <i class="fa fa-th-large"></i> Router List
          </a>
        </div>
      </div>
    </div>

    <!-- 4 SUMMARY CARDS -->
    <div class="row" style="margin-bottom: 20px;">
      <!-- CARD 1: TOTAL ACTIVE HOTSPOT SESSIONS -->
      <div class="col-3 col-m-6 col-s-12">
        <div style="background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%); border-radius: 8px; padding: 16px; color: #fff; box-shadow: 0 4px 12px rgba(9,132,227,0.35); position: relative; overflow: hidden;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9;">Total Sesi Hotspot Aktif</div>
              <div id="stat_total_active" style="font-size: 32px; font-weight: 900; line-height: 1.2; margin: 4px 0;">--</div>
              <div style="font-size: 11px; opacity: 0.85;">Akumulasi seluruh router online</div>
            </div>
            <div style="font-size: 38px; opacity: 0.35;"><i class="fa fa-wifi"></i></div>
          </div>
        </div>
      </div>

      <!-- CARD 2: TOTAL REAL-TIME WAN BANDWIDTH -->
      <div class="col-3 col-m-6 col-s-12">
        <div style="background: linear-gradient(135deg, #00b894 0%, #55efc4 100%); border-radius: 8px; padding: 16px; color: #1e272e; box-shadow: 0 4px 12px rgba(0,184,148,0.35); position: relative; overflow: hidden;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <div style="font-size: 11px; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; opacity: 0.85;">Total Bandwidth Internet</div>
              <div id="stat_total_rx" style="font-size: 24px; font-weight: 900; line-height: 1.2; margin: 2px 0; color: #006266;">⬇ -- bps</div>
              <div id="stat_total_tx" style="font-size: 13px; font-weight: 700; opacity: 0.9; color: #2d3436;">⬆ -- bps</div>
            </div>
            <div style="font-size: 38px; opacity: 0.25; color: #006266;"><i class="fa fa-exchange"></i></div>
          </div>
        </div>
      </div>

      <!-- CARD 3: STATUS ROUTER ONLINE/OFFLINE -->
      <div class="col-3 col-m-6 col-s-12">
        <div style="background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%); border-radius: 8px; padding: 16px; color: #fff; box-shadow: 0 4px 12px rgba(108,92,231,0.35); position: relative; overflow: hidden;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; opacity: 0.9;">Router Terkoneksi</div>
              <div id="stat_routers_online" style="font-size: 32px; font-weight: 900; line-height: 1.2; margin: 4px 0;">-- / --</div>
              <div id="stat_routers_sub" style="font-size: 11px; opacity: 0.85;">Memuat status...</div>
            </div>
            <div style="font-size: 38px; opacity: 0.35;"><i class="fa fa-server"></i></div>
          </div>
        </div>
      </div>

      <!-- CARD 4: VPS SERVER HEALTH -->
      <div class="col-3 col-m-6 col-s-12">
        <div style="background: linear-gradient(135deg, #2d3436 0%, #636e72 100%); border-radius: 8px; padding: 16px; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.35); position: relative; overflow: hidden;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
              <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; opacity: 0.85;">Server Cloud VPS</div>
              <div style="font-size: 20px; font-weight: 800; line-height: 1.2; margin: 4px 0; color: #00d2d3;">202.10.46.222</div>
              <div id="stat_vps_health" style="font-size: 11px; opacity: 0.9;">Load: -- | RAM: --%</div>
            </div>
            <div style="font-size: 38px; opacity: 0.25;"><i class="fa fa-cloud"></i></div>
          </div>
        </div>
      </div>
    </div>

    <!-- LIVE BANDWIDTH GRAPH CONTAINER -->
    <div class="card" style="margin-bottom: 20px; background: #23272e; border: 1px solid #373e47; border-radius: 8px;">
      <div class="card-header" style="background: #1e272e; border-bottom: 1px solid #373e47; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title" style="margin: 0; font-size: 14px; font-weight: 700; color: #fff;">
          <i class="fa fa-line-chart" style="color: #2ed573;"></i> Grafik Bandwidth Real-Time Seluruh Router (Traffic In/Out)
        </h3>
        <span id="chart_updated_tag" style="font-size: 11px; color: #a4b0be;">Update: menunggu data...</span>
      </div>
      <div class="card-body" style="padding: 15px;">
        <div id="multi_router_chart" style="width: 100%; height: 260px;"></div>
      </div>
    </div>

    <!-- DETAILED ROUTER CARDS (GRID) -->
    <h3 style="font-size: 16px; font-weight: 800; color: #fff; margin: 25px 0 15px 0; display: flex; align-items: center; gap: 8px;">
      <i class="fa fa-cubes" style="color: #70a1ff;"></i> Rincian Status & Antarmuka Internet (WAN) per Router
    </h3>

    <div class="row" id="router_cards_container">
      <div class="col-12 text-center" style="padding: 40px; color: #a4b0be;">
        <i class="fa fa-spinner fa-spin fa-2x"></i><br><br>Memuat data seluruh router...
      </div>
    </div>

  </div>
</div>

<!-- Highcharts Script -->
<script src="./js/highcharts/highcharts.js"></script>
<script src="./js/highcharts/themes/hc.<?= !empty($theme) ? $theme : 'dark'; ?>.js"></script>
<script>
var isPaused = false;
var timerId = null;
var multiChart = null;

$(document).ready(function() {
  initChart();
  fetchStats();

  $('#sel_refresh').change(function() {
    restartTimer();
  });

  $('#btn_toggle_refresh').click(function() {
    isPaused = !isPaused;
    if (isPaused) {
      $(this).html('<i class="fa fa-play"></i> Lanjutkan').css('background', '#2ed573');
    } else {
      $(this).html('<i class="fa fa-pause"></i> Jeda').css('background', '#3742fa');
      fetchStats();
    }
  });
});

function initChart() {
  if (typeof Highcharts === 'undefined') {
    console.warn('Highcharts library not loaded yet');
    return;
  }

  Highcharts.setOptions({
    global: { useUTC: false }
  });

  multiChart = Highcharts.chart('multi_router_chart', {
    chart: {
      type: 'areaspline',
      backgroundColor: 'transparent',
      animation: Highcharts.svg,
      marginRight: 10
    },
    title: { text: '' },
    xAxis: {
      type: 'datetime',
      tickPixelInterval: 120,
      labels: { style: { color: '#a4b0be' } },
      lineColor: '#57606f',
      tickColor: '#57606f'
    },
    yAxis: {
      title: { text: 'Kecepatan', style: { color: '#a4b0be' } },
      labels: {
        style: { color: '#a4b0be' },
        formatter: function() {
          return formatBytesBps(this.value);
        }
      },
      gridLineColor: '#2f3542',
      min: 0
    },
    tooltip: {
      shared: true,
      backgroundColor: '#1e272e',
      style: { color: '#ffffff' },
      borderColor: '#57606f',
      formatter: function() {
        var s = '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b>';
        $.each(this.points, function() {
          s += '<br/><span style="color:' + this.color + '">\u25CF</span> ' + this.series.name + ': <b>' + formatBytesBps(this.y) + '</b>';
        });
        return s;
      }
    },
    legend: {
      itemStyle: { color: '#ced6e0' },
      itemHoverStyle: { color: '#ffffff' }
    },
    credits: { enabled: false },
    series: [
      {
        name: 'Total Download (Rx)',
        color: '#2ed573',
        fillColor: {
          linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
          stops: [
            [0, 'rgba(46, 213, 115, 0.4)'],
            [1, 'rgba(46, 213, 115, 0.0)']
          ]
        },
        data: []
      },
      {
        name: 'Total Upload (Tx)',
        color: '#00d2d3',
        fillColor: {
          linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
          stops: [
            [0, 'rgba(0, 210, 211, 0.4)'],
            [1, 'rgba(0, 210, 211, 0.0)']
          ]
        },
        data: []
      }
    ]
  });
}

function formatBytesBps(bits) {
  if (bits === 0 || !bits) return '0 bps';
  var k = 1000;
  var sizes = ['bps', 'kbps', 'Mbps', 'Gbps'];
  var i = Math.floor(Math.log(bits) / Math.log(k));
  if (i < 0) i = 0;
  if (i >= sizes.length) i = sizes.length - 1;
  return parseFloat((bits / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatBytes(bytes) {
  if (bytes === 0 || !bytes) return '0 B';
  var k = 1024;
  var sizes = ['B', 'KB', 'MB', 'GB'];
  var i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function fetchStats() {
  if (isPaused) return;

  $.ajax({
    url: './settings/router_stats_api.php',
    type: 'GET',
    dataType: 'json',
    timeout: 8000,
    success: function(res) {
      if (res.status === 'success') {
        renderStats(res);
      }
      restartTimer();
    },
    error: function() {
      restartTimer();
    }
  });
}

function restartTimer() {
  if (timerId) clearTimeout(timerId);
  var interval = parseInt($('#sel_refresh').val()) || 2500;
  if (!isPaused) {
    timerId = setTimeout(fetchStats, interval);
  }
}

function renderStats(data) {
  var s = data.summary;
  $('#stat_total_active').text(s.total_active_sessions.toLocaleString());
  $('#stat_total_rx').text('⬇ ' + s.total_rx_human);
  $('#stat_total_tx').text('⬆ ' + s.total_tx_human);
  $('#stat_routers_online').text(s.routers_online + ' / ' + s.routers_total);
  $('#stat_routers_sub').text(s.routers_online === s.routers_total ? 'Semua router online (100%)' : (s.routers_total - s.routers_online) + ' router offline');
  $('#stat_vps_health').text('Load: ' + s.vps_load.toFixed(2) + ' | RAM: ' + s.vps_ram_percent + '%');
  $('#chart_updated_tag').text('Update: ' + data.timestamp);

  // Update Chart
  if (multiChart && multiChart.series && multiChart.series.length >= 2) {
    var x = (new Date()).getTime();
    var shift = multiChart.series[0].data.length > 25;
    multiChart.series[0].addPoint([x, s.total_rx_bps], true, shift);
    multiChart.series[1].addPoint([x, s.total_tx_bps], true, shift);
  }

  // Render Router Cards
  var html = '';
  $.each(data.routers, function(idx, r) {
    var borderCol = r.online ? '#2ed573' : '#ff4757';
    var bgHead = r.online ? 'rgba(46, 213, 115, 0.1)' : 'rgba(255, 71, 87, 0.1)';
    var badgeOnline = r.online 
      ? '<span class="badge" style="background: #2ed573; color: #1e272e; font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 12px;"><i class="fa fa-circle"></i> ONLINE</span>'
      : '<span class="badge" style="background: #ff4757; color: #fff; font-weight: 800; font-size: 11px; padding: 2px 8px; border-radius: 12px;"><i class="fa fa-circle"></i> OFFLINE</span>';

    html += '<div class="col-6 col-s-12" style="margin-bottom: 20px;">';
    html += '  <div style="background: #23272e; border: 1.5px solid ' + borderCol + '; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 14px rgba(0,0,0,0.25);">';
    
    // Header
    html += '    <div style="background: ' + bgHead + '; border-bottom: 1px solid #373e47; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;">';
    html += '      <div>';
    html += '        <h4 style="margin: 0; color: #fff; font-size: 16px; font-weight: 800;"><i class="fa fa-server" style="color: #70a1ff;"></i> ' + escapeHtml(r.hotspot_name) + '</h4>';
    html += '        <div style="font-size: 12px; color: #a4b0be; margin-top: 3px;">Sesi: <b style="color: #7bed9f;">' + escapeHtml(r.session) + '</b> | VPN: <code style="color: #70a1ff;">' + escapeHtml(r.vpn_ip) + '</code></div>';
    html += '      </div>';
    html += '      <div>' + badgeOnline + '</div>';
    html += '    </div>';

    // Body
    html += '    <div style="padding: 14px 16px;">';
    if (r.online) {
      // 3 Metrics row
      html += '      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 14px;">';
      html += '        <div style="background: #1e272e; padding: 10px; border-radius: 6px; border: 1px solid #373e47; text-align: center;">';
      html += '          <div style="font-size: 10px; color: #a4b0be; text-transform: uppercase; font-weight: 700;">Hotspot Aktif</div>';
      html += '          <div style="font-size: 20px; font-weight: 900; color: #2ed573; margin-top: 2px;"><i class="fa fa-users"></i> ' + r.active_sessions + '</div>';
      html += '          <div style="font-size: 10px; color: #747d8c;">user terhubung</div>';
      html += '        </div>';

      html += '        <div style="background: #1e272e; padding: 10px; border-radius: 6px; border: 1px solid #373e47; text-align: center;">';
      html += '          <div style="font-size: 10px; color: #a4b0be; text-transform: uppercase; font-weight: 700;">Beban CPU</div>';
      html += '          <div style="font-size: 20px; font-weight: 900; color: ' + (r.cpu_load > 70 ? '#ff4757' : '#00d2d3') + '; margin-top: 2px;"><i class="fa fa-microchip"></i> ' + r.cpu_load + '%</div>';
      html += '          <div style="font-size: 10px; color: #747d8c;">' + escapeHtml(r.board_name) + '</div>';
      html += '        </div>';

      html += '        <div style="background: #1e272e; padding: 10px; border-radius: 6px; border: 1px solid #373e47; text-align: center;">';
      html += '          <div style="font-size: 10px; color: #a4b0be; text-transform: uppercase; font-weight: 700;">Uptime & ROS</div>';
      html += '          <div style="font-size: 13px; font-weight: 800; color: #ffa502; margin-top: 6px;">' + escapeHtml(r.uptime) + '</div>';
      html += '          <div style="font-size: 10px; color: #747d8c;">v' + escapeHtml(r.ros_version) + '</div>';
      html += '        </div>';
      html += '      </div>';

      // WAN INTERFACES (LOAD BALANCING AWARE)
      html += '      <div style="background: #1e272e; border: 1px solid #373e47; border-radius: 6px; padding: 12px; margin-bottom: 12px;">';
      var wanCount = r.wan_interfaces.length;
      var isLb = wanCount > 1;
      html += '        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">';
      html += '          <span style="font-size: 12px; font-weight: 800; color: #ced6e0;"><i class="fa fa-globe" style="color: #00d2d3;"></i> Antarmuka Internet (WAN):</span>';
      if (isLb) {
        html += '          <span class="badge" style="background: #ff9f43; color: #1e272e; font-weight: 800; font-size: 10px; padding: 2px 7px; border-radius: 4px;"><i class="fa fa-balance-scale"></i> ' + wanCount + ' Jalur Load Balancing Aktif</span>';
      } else {
        html += '          <span class="badge" style="background: #57606f; color: #fff; font-weight: 700; font-size: 10px; padding: 2px 6px; border-radius: 4px;">Single WAN</span>';
      }
      html += '        </div>';

      // Render each WAN interface
      $.each(r.wan_interfaces, function(wIdx, w) {
        var labelTitle = isLb ? 'Jalur Internet ' + (wIdx + 1) + ' (' + escapeHtml(w.name) + ')' : escapeHtml(w.name);
        html += '        <div style="background: #2f3542; border-radius: 5px; padding: 8px 12px; margin-bottom: 6px; border-left: 3px solid ' + (wIdx === 0 ? '#2ed573' : '#ffa502') + ';">';
        html += '          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">';
        html += '            <span style="font-size: 12px; font-weight: 700; color: #fff;">' + labelTitle + '</span>';
        html += '            <span style="font-size: 11px; color: #a4b0be;">Rx: <b style="color: #2ed573;">' + w.rx_human + '</b> | Tx: <b style="color: #00d2d3;">' + w.tx_human + '</b></span>';
        html += '          </div>';
        
        // Mini animated progress bar
        var rxPct = Math.min(100, Math.max(3, (w.rx_bps / 50000000) * 100)); // normalized to 50M
        html += '          <div style="width: 100%; height: 5px; background: #1e272e; border-radius: 3px; overflow: hidden;">';
        html += '            <div style="width: ' + rxPct + '%; height: 100%; background: linear-gradient(90deg, #2ed573, #00d2d3); border-radius: 3px; transition: width 0.3s ease;"></div>';
        html += '          </div>';
        html += '        </div>';
      });
      html += '      </div>';

    } else {
      // OFFLINE MESSAGE
      html += '      <div style="background: rgba(255, 71, 87, 0.08); border: 1px dashed #ff4757; border-radius: 6px; padding: 25px; text-align: center; color: #ff6b81; margin-bottom: 12px;">';
      html += '        <i class="fa fa-exclamation-triangle fa-2x"></i><br><br>';
      html += '        <b>Router Tidak Terhubung</b><br>';
      html += '        <span style="font-size: 12px; color: #a4b0be;">Port API 8728 di ' + escapeHtml(r.vpn_ip) + ' tidak merespons. Pastikan router menyala dan tunnel VPN aktif.</span>';
      html += '      </div>';
    }


    // Footer actions
    html += '      <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #373e47; padding-top: 10px; margin-top: 5px; flex-wrap: wrap; gap: 6px;">';
    html += '        <div style="display: flex; gap: 6px;">';
    html += '          <a href="winbox:' + escapeHtml(r.winbox_addr) + '" class="btn btn-sm" style="background: #3742fa; color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 4px; text-decoration: none;" title="Buka Winbox">';
    html += '            <i class="fa fa-desktop"></i> Winbox';
    html += '          </a>';
    html += '          <button type="button" class="btn btn-sm" style="background: #57606f; color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 4px;" onclick="copyAddr(\'' + escapeHtml(r.winbox_addr) + '\', this)" title="Salin Port Winbox">';
    html += '            <i class="fa fa-copy"></i> ' + escapeHtml(r.winbox_addr);
    html += '          </button>';
    if (r.online) {
      html += '          <a href="./?system=resource-graph&session=' + encodeURIComponent(r.session) + '" class="btn btn-sm" style="background: #20bf6b; color: #fff; font-size: 11px; padding: 3px 8px; border-radius: 4px; text-decoration: none;" title="Buka Grafik Resource Router">';
      html += '            <i class="fa fa-line-chart"></i> Graphs';
      html += '          </a>';
    }
    html += '        </div>';
    html += '        <div>';
    html += '          <a href="./?session=' + encodeURIComponent(r.session) + '" class="btn btn-sm bg-primary" style="font-size: 11px; padding: 3px 10px; border-radius: 4px; text-decoration: none; font-weight: 700;">';
    html += '            <i class="fa fa-external-link"></i> Buka Hotspot';
    html += '          </a>';
    html += '        </div>';
    html += '      </div>';

    html += '    </div>';
    html += '  </div>';
    html += '</div>';
  });

  $('#router_cards_container').html(html);
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function copyAddr(text, btn) {
  var $b = $(btn);
  var orig = $b.html();
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(function() {
      $b.html('<i class="fa fa-check"></i> Disalin').css('background', '#2ed573');
      setTimeout(function() { $b.html(orig).removeAttr('style'); }, 2000);
    });
  } else {
    prompt('Salin alamat:', text);
  }
}
</script>
