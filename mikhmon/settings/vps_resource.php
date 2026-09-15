<?php
/*
 *  Pacenet Billing System - Dedicated VPS Resource Graphs
 *  Independent Cloud Server Monitoring: CPU, RAM, Disk, and Bandwidth.
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}

// Find available VPS network interfaces
$vpsIfaces = array();
if (@file_exists('/proc/net/dev')) {
    foreach (@file('/proc/net/dev') as $line) {
        if (strpos($line, ':') !== false) {
            $parts = explode(':', $line);
            $ifN = trim($parts[0]);
            if ($ifN !== 'lo') $vpsIfaces[] = $ifN;
        }
    }
}
if (empty($vpsIfaces)) $vpsIfaces = array('eth0', 'wg0');
?>

<!-- Ensure Highcharts scripts are loaded -->
<script src="./js/highcharts/highcharts.js"></script>
<script src="./js/highcharts/themes/hc.<?= !empty($theme) ? $theme : 'dark'; ?>.js"></script>

<style>
.vps-stat-card {
  background: #23272e;
  border: 1px solid #373e47;
  border-radius: 8px;
  padding: 14px 16px;
  margin-bottom: 15px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
  transition: transform 0.2s ease, border-color 0.2s ease;
}
.vps-stat-card:hover {
  transform: translateY(-2px);
  border-color: #57606f;
}
.vps-stat-card .card-title-sm {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  font-weight: 700;
  color: #8b949e;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.vps-stat-card .main-val {
  font-size: 26px;
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 6px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
}
.vps-stat-card .sub-val {
  font-size: 12px;
  color: #a4b0be;
  font-weight: 500;
}
.vps-progress-bg {
  height: 6px;
  background: #2f3542;
  border-radius: 3px;
  overflow: hidden;
  margin-top: 8px;
}
.vps-progress-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s ease, background-color 0.4s ease;
}
.vps-view-btn {
  background: #2f3542;
  color: #ced6e0;
  border: 1px solid #57606f;
  padding: 6px 14px;
  border-radius: 5px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.vps-view-btn:hover {
  background: #57606f;
  color: #fff;
}
.vps-view-btn.active {
  background: #00d2d3;
  color: #1e272e;
  font-weight: 700;
  border-color: #00d2d3;
  box-shadow: 0 2px 8px rgba(0, 210, 211, 0.4);
}
.vps-chart-box {
  background: #23272e;
  border: 1px solid #373e47;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}
.vps-chart-container-sm {
  width: 100%;
  height: 280px;
}
.vps-chart-container-lg {
  width: 100%;
  height: 440px;
}
.vps-pulse-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #00d2d3;
  margin-right: 4px;
  animation: vpsPulseAnim 1.5s infinite;
}
@keyframes vpsPulseAnim {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 210, 211, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(0, 210, 211, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 210, 211, 0); }
}
</style>

<div class="row">
  <div class="col-12">
    <!-- Top Control Bar Card -->
    <div class="card" style="margin-bottom: 14px;">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
          <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
            <i class="fa fa-server" style="color: #00d2d3;"></i> 
            <span>Grafik Resource Server VPS: <b style="color: #00d2d3;">202.10.46.222</b></span>
          </h3>
          <span style="font-size: 11px; color: #8b949e;" id="vps_meta_info">
            <span class="vps-pulse-dot"></span> Live Monitoring Server VPS Cloud (Linux x86_64)
          </span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <span style="font-weight: 600; font-size: 12px; color: #ced6e0;">Interface VPS:</span>
          <select id="sel_vps_iface" class="dropd" style="padding: 5px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; background: #2f3542; color: #fff; border: 1px solid #57606f; cursor: pointer;">
            <?php foreach ($vpsIfaces as $vIf): ?>
              <?php
                $label = $vIf;
                if ($vIf === 'eth0') $label = 'eth0 (Publik IP: 202.10.46.222)';
                elseif ($vIf === 'wg0') $label = 'wg0 (WireGuard VPN Tunnel)';
                elseif ($vIf === 'eth1') $label = 'eth1 (Internal/Private)';
              ?>
              <option value="<?= htmlspecialchars($vIf); ?>" <?= ($vIf === 'eth0') ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>

          <span style="font-weight: 600; font-size: 12px; color: #ced6e0;">Interval:</span>
          <select id="sel_vps_interval" class="dropd" style="padding: 5px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; background: #2f3542; color: #fff; border: 1px solid #57606f; cursor: pointer;">
            <option value="1000">1 Detik</option>
            <option value="2000" selected>2 Detik</option>
            <option value="3000">3 Detik</option>
            <option value="5000">5 Detik</option>
          </select>

          <button type="button" id="btn_vps_pause" class="btn btn-sm" style="background: #57606f; color: #fff; font-size: 12px; padding: 5px 12px; border-radius: 4px;">
            <i class="fa fa-pause"></i> Pause
          </button>
          <a href="./admin.php?id=sessions" class="btn btn-sm" style="background: #1e272e; color: #00d2d3; border: 1px solid #57606f; font-size: 12px; padding: 5px 12px; border-radius: 4px; text-decoration: none;">
            <i class="fa fa-arrow-left"></i> Ke Router List
          </a>
        </div>
      </div>
    </div>

    <!-- 4 VPS Stat Cards Row -->
    <div class="row">
      <!-- VPS CPU Card -->
      <div class="col-3 col-box-6">
        <div class="vps-stat-card">
          <div class="card-title-sm"><i class="fa fa-microchip" style="color: #ff4757;"></i> CPU Load (VPS)</div>
          <div class="main-val" id="vps_cpu_pct" style="color: #ff4757;">-- %</div>
          <div class="sub-val" id="vps_cpu_detail">Load Avg: -- | Cores: --</div>
          <div class="vps-progress-bg">
            <div id="vps_bar_cpu" class="vps-progress-fill" style="width: 0%; background: #ff4757;"></div>
          </div>
        </div>
      </div>

      <!-- VPS RAM Card -->
      <div class="col-3 col-box-6">
        <div class="vps-stat-card">
          <div class="card-title-sm"><i class="fa fa-tasks" style="color: #70a1ff;"></i> RAM Memori (VPS)</div>
          <div class="main-val" id="vps_ram_pct" style="color: #70a1ff;">-- %</div>
          <div class="sub-val" id="vps_ram_detail">Terpakai: -- / --</div>
          <div class="vps-progress-bg">
            <div id="vps_bar_ram" class="vps-progress-fill" style="width: 0%; background: #70a1ff;"></div>
          </div>
        </div>
      </div>

      <!-- VPS Disk Card -->
      <div class="col-3 col-box-6">
        <div class="vps-stat-card">
          <div class="card-title-sm"><i class="fa fa-database" style="color: #ffa502;"></i> Disk Space (VPS SSD/HDD)</div>
          <div class="main-val" id="vps_disk_pct" style="color: #ffa502;">-- %</div>
          <div class="sub-val" id="vps_disk_detail">Terpakai: -- / --</div>
          <div class="vps-progress-bg">
            <div id="vps_bar_disk" class="vps-progress-fill" style="width: 0%; background: #ffa502;"></div>
          </div>
        </div>
      </div>

      <!-- VPS Bandwidth Card -->
      <div class="col-3 col-box-6">
        <div class="vps-stat-card">
          <div class="card-title-sm"><i class="fa fa-exchange" style="color: #00d2d3;"></i> Bandwidth VPS (<span id="vps_active_iface_badge">eth0</span>)</div>
          <div class="main-val" id="vps_bw_rx" style="color: #00d2d3; font-size: 20px;">Rx: --</div>
          <div class="sub-val" id="vps_bw_tx" style="color: #54a0ff; font-weight: 700;">Tx: --</div>
          <div class="vps-progress-bg">
            <div id="vps_bar_bw" class="vps-progress-fill" style="width: 100%; background: linear-gradient(90deg, #54a0ff, #00d2d3);"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- View Mode Selector -->
    <div style="margin-bottom: 14px; display: flex; gap: 8px; flex-wrap: wrap;">
      <button type="button" class="vps-view-btn active" data-view="grid">
        <i class="fa fa-th-large"></i> 4-in-1 Grid View
      </button>
      <button type="button" class="vps-view-btn" data-view="bw">
        <i class="fa fa-area-chart"></i> Bandwidth VPS
      </button>
      <button type="button" class="vps-view-btn" data-view="cpu">
        <i class="fa fa-microchip"></i> CPU Load VPS
      </button>
      <button type="button" class="vps-view-btn" data-view="ram">
        <i class="fa fa-tasks"></i> RAM Memori VPS
      </button>
      <button type="button" class="vps-view-btn" data-view="disk">
        <i class="fa fa-database"></i> Disk Storage VPS
      </button>
    </div>

    <!-- 4-in-1 Grid View Section -->
    <div id="vps_grid_view" class="row">
      <!-- Grid Bandwidth Chart -->
      <div class="col-6">
        <div class="vps-chart-box">
          <div style="font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #fff; display: flex; justify-content: space-between;">
            <span><i class="fa fa-exchange" style="color: #00d2d3;"></i> Traffic Bandwidth VPS (<span class="vps-lbl-iface">eth0</span>)</span>
            <span style="font-size: 11px; color: #8b949e;">Tx (Keluar) &amp; Rx (Masuk)</span>
          </div>
          <div id="vps_chart_grid_bw" class="vps-chart-container-sm"></div>
        </div>
      </div>

      <!-- Grid CPU Chart -->
      <div class="col-6">
        <div class="vps-chart-box">
          <div style="font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #fff; display: flex; justify-content: space-between;">
            <span><i class="fa fa-microchip" style="color: #ff4757;"></i> CPU Load VPS (%)</span>
            <span style="font-size: 11px; color: #8b949e;">Real-time Processor VPS</span>
          </div>
          <div id="vps_chart_grid_cpu" class="vps-chart-container-sm"></div>
        </div>
      </div>

      <!-- Grid RAM Chart -->
      <div class="col-6">
        <div class="vps-chart-box">
          <div style="font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #fff; display: flex; justify-content: space-between;">
            <span><i class="fa fa-tasks" style="color: #70a1ff;"></i> RAM Memori VPS (MB)</span>
            <span style="font-size: 11px; color: #8b949e;">Terpakai vs Tersedia</span>
          </div>
          <div id="vps_chart_grid_ram" class="vps-chart-container-sm"></div>
        </div>
      </div>

      <!-- Grid Disk Chart -->
      <div class="col-6">
        <div class="vps-chart-box">
          <div style="font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #fff; display: flex; justify-content: space-between;">
            <span><i class="fa fa-database" style="color: #ffa502;"></i> Disk Storage VPS (GB)</span>
            <span style="font-size: 11px; color: #8b949e;">Penyimpanan Partisi Root /</span>
          </div>
          <div id="vps_chart_grid_disk" class="vps-chart-container-sm"></div>
        </div>
      </div>
    </div>

    <!-- Single Focused View Section (Hidden by default) -->
    <div id="vps_single_view" style="display: none;">
      <div class="vps-chart-box">
        <div style="font-weight: 700; font-size: 14px; margin-bottom: 10px; color: #fff;" id="vps_single_title">
          Grafik Monitor VPS
        </div>
        <div id="vps_chart_single_focused" class="vps-chart-container-lg"></div>
      </div>
    </div>

  </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
  var currentIface = $('#sel_vps_iface').val() || 'eth0';
  var pollInterval = parseInt($('#sel_vps_interval').val()) || 2000;
  var isPaused = false;
  var timerId = null;
  var currentView = 'grid';
  var MAX_POINTS = 30;

  Highcharts.setOptions({
    global: { useUTC: false }
  });

  function fmtBps(val) {
    if (!val || val === 0) return '0 bps';
    var sizes = ['bps', 'kbps', 'Mbps', 'Gbps'];
    var i = parseInt(Math.floor(Math.log(val) / Math.log(1000)));
    i = Math.min(i, sizes.length - 1);
    return parseFloat((val / Math.pow(1000, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // 1. Init Grid Charts
  var chartGridBw = Highcharts.chart('vps_chart_grid_bw', {
    chart: { type: 'areaspline', animation: Highcharts.svg },
    title: { text: null },
    xAxis: { type: 'datetime', tickPixelInterval: 100 },
    yAxis: {
      title: { text: null },
      labels: { formatter: function() { return fmtBps(this.value); } }
    },
    tooltip: {
      shared: true,
      formatter: function() {
        var s = '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/>';
        $.each(this.points, function() {
          s += '<span style="color:' + this.series.color + '">●</span> ' + this.series.name + ': <b>' + fmtBps(this.y) + '</b><br/>';
        });
        return s;
      }
    },
    series: [
      { name: 'Tx (Keluar)', data: [], color: '#54a0ff' },
      { name: 'Rx (Masuk)', data: [], color: '#00d2d3' }
    ]
  });

  var chartGridCpu = Highcharts.chart('vps_chart_grid_cpu', {
    chart: { type: 'areaspline', animation: Highcharts.svg },
    title: { text: null },
    xAxis: { type: 'datetime', tickPixelInterval: 100 },
    yAxis: {
      min: 0,
      max: 100,
      title: { text: null },
      labels: { format: '{value}%' }
    },
    tooltip: {
      formatter: function() {
        return '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/>' +
               '<span style="color:#ff4757">●</span> CPU Load: <b>' + this.y + '%</b>';
      }
    },
    series: [{ name: 'CPU Load', data: [], color: '#ff4757' }]
  });

  var chartGridRam = Highcharts.chart('vps_chart_grid_ram', {
    chart: { type: 'areaspline', animation: Highcharts.svg },
    title: { text: null },
    xAxis: { type: 'datetime', tickPixelInterval: 100 },
    yAxis: {
      min: 0,
      title: { text: null },
      labels: { format: '{value} MB' }
    },
    tooltip: {
      shared: true,
      formatter: function() {
        var s = '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/>';
        $.each(this.points, function() {
          s += '<span style="color:' + this.series.color + '">●</span> ' + this.series.name + ': <b>' + this.y + ' MB</b><br/>';
        });
        return s;
      }
    },
    series: [
      { name: 'Terpakai (Used)', data: [], color: '#70a1ff' },
      { name: 'Tersedia (Avail)', data: [], color: '#00d2d3' }
    ]
  });

  var chartGridDisk = Highcharts.chart('vps_chart_grid_disk', {
    chart: { type: 'areaspline', animation: Highcharts.svg },
    title: { text: null },
    xAxis: { type: 'datetime', tickPixelInterval: 100 },
    yAxis: {
      min: 0,
      title: { text: null },
      labels: { format: '{value} GB' }
    },
    tooltip: {
      shared: true,
      formatter: function() {
        var s = '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/>';
        $.each(this.points, function() {
          s += '<span style="color:' + this.series.color + '">●</span> ' + this.series.name + ': <b>' + this.y + ' GB</b><br/>';
        });
        return s;
      }
    },
    series: [
      { name: 'Terpakai (Used)', data: [], color: '#ffa502' },
      { name: 'Sisa (Free)', data: [], color: '#2ed573' }
    ]
  });

  var chartSingle = null;

  function initSingleChart(type) {
    if (chartSingle) {
      chartSingle.destroy();
      chartSingle = null;
    }

    var options = {
      chart: { type: 'areaspline', animation: Highcharts.svg, renderTo: 'vps_chart_single_focused' },
      xAxis: { type: 'datetime', tickPixelInterval: 120 },
      tooltip: { shared: true }
    };

    if (type === 'bw') {
      $('#vps_single_title').html('<i class="fa fa-exchange" style="color: #00d2d3;"></i> Detail Bandwidth Interface VPS: ' + currentIface);
      options.title = { text: 'Traffic Bandwidth VPS: ' + currentIface };
      options.yAxis = {
        title: { text: null },
        labels: { formatter: function() { return fmtBps(this.value); } }
      };
      options.series = [
        { name: 'Tx (Keluar)', data: chartGridBw.series[0].data.map(p => [p.x, p.y]), color: '#54a0ff' },
        { name: 'Rx (Masuk)', data: chartGridBw.series[1].data.map(p => [p.x, p.y]), color: '#00d2d3' }
      ];
    } else if (type === 'cpu') {
      $('#vps_single_title').html('<i class="fa fa-microchip" style="color: #ff4757;"></i> Detail Riwayat Beban CPU VPS (%)');
      options.title = { text: 'Riwayat Beban CPU VPS (%)' };
      options.yAxis = { min: 0, max: 100, title: { text: null }, labels: { format: '{value}%' } };
      options.tooltip = {
        formatter: function() {
          return '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/><span style="color:#ff4757">●</span> CPU: <b>' + this.y + '%</b>';
        }
      };
      options.series = [
        { name: 'CPU Load', data: chartGridCpu.series[0].data.map(p => [p.x, p.y]), color: '#ff4757' }
      ];
    } else if (type === 'ram') {
      $('#vps_single_title').html('<i class="fa fa-tasks" style="color: #70a1ff;"></i> Detail Pemakaian RAM Memori VPS (MB)');
      options.title = { text: 'Pemakaian RAM VPS (MB)' };
      options.yAxis = { min: 0, title: { text: null }, labels: { format: '{value} MB' } };
      options.series = [
        { name: 'Terpakai (Used)', data: chartGridRam.series[0].data.map(p => [p.x, p.y]), color: '#70a1ff' },
        { name: 'Tersedia (Avail)', data: chartGridRam.series[1].data.map(p => [p.x, p.y]), color: '#00d2d3' }
      ];
    } else if (type === 'disk') {
      $('#vps_single_title').html('<i class="fa fa-database" style="color: #ffa502;"></i> Detail Alokasi Penyimpanan Disk VPS (GB)');
      options.title = { text: 'Alokasi Penyimpanan Partisi Root / (GB)' };
      options.yAxis = { min: 0, title: { text: null }, labels: { format: '{value} GB' } };
      options.series = [
        { name: 'Terpakai (Used)', data: chartGridDisk.series[0].data.map(p => [p.x, p.y]), color: '#ffa502' },
        { name: 'Sisa (Free)', data: chartGridDisk.series[1].data.map(p => [p.x, p.y]), color: '#2ed573' }
      ];
    }

    chartSingle = new Highcharts.Chart(options);
  }

  function fetchVpsMetrics() {
    if (isPaused) return;

    $.ajax({
      url: './settings/vps_resource_api.php?iface=' + encodeURIComponent(currentIface),
      dataType: 'json',
      cache: false,
      success: function(data) {
        if (!data || data.status !== 'ok') return;

        var now = data.timestamp || (new Date()).getTime();

        // 1. Update CPU
        var cpuPct = data.cpu.percent;
        $('#vps_cpu_pct').text(cpuPct + '%');
        $('#vps_cpu_detail').text('Load: ' + data.cpu.load_avg.join(', ') + ' | ' + data.cpu.cores + ' Core (' + data.cpu.model.split('@')[0] + ')');
        $('#vps_bar_cpu').css('width', Math.min(cpuPct, 100) + '%');
        var cpuColor = cpuPct < 60 ? '#00d2d3' : (cpuPct < 85 ? '#ffa502' : '#ff4757');
        $('#vps_cpu_pct').css('color', cpuColor);
        $('#vps_bar_cpu').css('background', cpuColor);

        var shiftCpu = chartGridCpu.series[0].data.length >= MAX_POINTS;
        chartGridCpu.series[0].addPoint([now, cpuPct], true, shiftCpu);

        // 2. Update RAM
        var ramPct = data.mem.percent;
        var ramUsedMb = Math.round(data.mem.used / (1024 * 1024));
        var ramAvailMb = Math.round(data.mem.avail / (1024 * 1024));
        $('#vps_ram_pct').text(ramPct + '%');
        $('#vps_ram_detail').text('Terpakai: ' + data.mem.used_fmt + ' / ' + data.mem.total_fmt + ' (Tersedia: ' + data.mem.avail_fmt + ')');
        $('#vps_bar_ram').css('width', Math.min(ramPct, 100) + '%');

        var shiftRam = chartGridRam.series[0].data.length >= MAX_POINTS;
        chartGridRam.series[0].addPoint([now, ramUsedMb], false);
        chartGridRam.series[1].addPoint([now, ramAvailMb], false);
        chartGridRam.redraw(shiftRam);

        // 3. Update Disk
        var diskPct = data.disk.percent;
        var diskUsedGb = parseFloat((data.disk.used / (1024 * 1024 * 1024)).toFixed(2));
        var diskFreeGb = parseFloat((data.disk.free / (1024 * 1024 * 1024)).toFixed(2));
        $('#vps_disk_pct').text(diskPct + '%');
        $('#vps_disk_detail').text('Terpakai: ' + data.disk.used_fmt + ' / ' + data.disk.total_fmt + ' (Sisa: ' + data.disk.free_fmt + ')');
        $('#vps_bar_disk').css('width', Math.min(diskPct, 100) + '%');

        var shiftDisk = chartGridDisk.series[0].data.length >= MAX_POINTS;
        chartGridDisk.series[0].addPoint([now, diskUsedGb], false);
        chartGridDisk.series[1].addPoint([now, diskFreeGb], false);
        chartGridDisk.redraw(shiftDisk);

        // 4. Update Bandwidth
        var tx = data.bandwidth.tx_bps;
        var rx = data.bandwidth.rx_bps;
        $('#vps_bw_tx').text('Tx (Keluar): ' + data.bandwidth.tx_fmt);
        $('#vps_bw_rx').text('Rx (Masuk): ' + data.bandwidth.rx_fmt);
        $('#vps_active_iface_badge').text(data.bandwidth.iface);

        var shiftBw = chartGridBw.series[0].data.length >= MAX_POINTS;
        chartGridBw.series[0].addPoint([now, tx], false);
        chartGridBw.series[1].addPoint([now, rx], false);
        chartGridBw.redraw(shiftBw);

        // 5. Update Single View if active
        if (chartSingle && currentView !== 'grid') {
          if (currentView === 'bw') {
            chartSingle.series[0].addPoint([now, tx], false);
            chartSingle.series[1].addPoint([now, rx], false);
            chartSingle.redraw(chartSingle.series[0].data.length >= MAX_POINTS);
          } else if (currentView === 'cpu') {
            chartSingle.series[0].addPoint([now, cpuPct], true, chartSingle.series[0].data.length >= MAX_POINTS);
          } else if (currentView === 'ram') {
            chartSingle.series[0].addPoint([now, ramUsedMb], false);
            chartSingle.series[1].addPoint([now, ramAvailMb], false);
            chartSingle.redraw(chartSingle.series[0].data.length >= MAX_POINTS);
          } else if (currentView === 'disk') {
            chartSingle.series[0].addPoint([now, diskUsedGb], false);
            chartSingle.series[1].addPoint([now, diskFreeGb], false);
            chartSingle.redraw(chartSingle.series[0].data.length >= MAX_POINTS);
          }
        }

        // Meta info
        $('#vps_meta_info').html(
          '<span class="vps-pulse-dot"></span> ' +
          '<b>' + data.vps_info.public_ip + '</b> | ' + data.vps_info.os_name + ' | Uptime: ' + data.vps_info.uptime
        );
      }
    });
  }

  function startPolling() {
    if (timerId) clearInterval(timerId);
    fetchVpsMetrics();
    timerId = setInterval(fetchVpsMetrics, pollInterval);
  }

  startPolling();

  // Interface Change
  $('#sel_vps_iface').change(function() {
    currentIface = $(this).val();
    $('.vps-lbl-iface').text(currentIface);
    $('#vps_active_iface_badge').text(currentIface);
    chartGridBw.series[0].setData([]);
    chartGridBw.series[1].setData([]);
    if (chartSingle && currentView === 'bw') {
      initSingleChart('bw');
    }
    fetchVpsMetrics();
  });

  // Interval Change
  $('#sel_vps_interval').change(function() {
    pollInterval = parseInt($(this).val()) || 2000;
    startPolling();
  });

  // Pause / Resume Button
  $('#btn_vps_pause').click(function() {
    isPaused = !isPaused;
    if (isPaused) {
      $(this).html('<i class="fa fa-play"></i> Resume').css('background', '#2ed573');
    } else {
      $(this).html('<i class="fa fa-pause"></i> Pause').css('background', '#57606f');
      fetchVpsMetrics();
    }
  });

  // View Mode Tabs
  $('.vps-view-btn').click(function() {
    $('.vps-view-btn').removeClass('active');
    $(this).addClass('active');

    var view = $(this).data('view');
    currentView = view;

    if (view === 'grid') {
      $('#vps_single_view').hide();
      $('#vps_grid_view').show();
      chartGridBw.reflow();
      chartGridCpu.reflow();
      chartGridRam.reflow();
      chartGridDisk.reflow();
    } else {
      $('#vps_grid_view').hide();
      $('#vps_single_view').show();
      initSingleChart(view);
      chartSingle.reflow();
    }
  });
});
</script>
