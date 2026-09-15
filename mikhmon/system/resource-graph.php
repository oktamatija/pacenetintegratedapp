<?php
/*
 *  Pacenet Billing System - Dedicated Resource Graphs
 *  Real-time interactive monitoring for CPU, RAM, Disk, and Bandwidth.
 */
session_start();
error_reporting(0);

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}

// Get interface list for dropdown
$getinterface = $API->comm("/interface/print");
$TotalReg = count($getinterface);
$defaultIface = $getinterface[0]['name'] ?? 'ether1';
foreach ($getinterface as $gi) {
    if ($gi['name'] === 'Vlan1' || $gi['name'] === 'ether1') {
        $defaultIface = $gi['name'];
        break;
    }
}
?>

<!-- Ensure Highcharts scripts are available -->
<script src="./js/highcharts/highcharts.js"></script>
<script src="./js/highcharts/themes/hc.<?= !empty($theme) ? $theme : 'dark'; ?>.js"></script>

<style>
.res-stat-card {
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
.res-stat-card:hover {
  transform: translateY(-2px);
  border-color: #57606f;
}
.res-stat-card .card-title-sm {
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
.res-stat-card .main-val {
  font-size: 26px;
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 6px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
}
.res-stat-card .sub-val {
  font-size: 12px;
  color: #a4b0be;
  font-weight: 500;
}
.res-progress-bg {
  height: 6px;
  background: #2f3542;
  border-radius: 3px;
  overflow: hidden;
  margin-top: 8px;
}
.res-progress-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s ease, background-color 0.4s ease;
}
.view-btn {
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
.view-btn:hover {
  background: #57606f;
  color: #fff;
}
.view-btn.active {
  background: #3742fa;
  color: #fff;
  border-color: #3742fa;
  box-shadow: 0 2px 8px rgba(55, 66, 250, 0.4);
}
.chart-box {
  background: #23272e;
  border: 1px solid #373e47;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}
.chart-container-sm {
  width: 100%;
  height: 280px;
}
.chart-container-lg {
  width: 100%;
  height: 440px;
}
.pulse-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #2ed573;
  margin-right: 4px;
  animation: pulseAnim 1.5s infinite;
}
@keyframes pulseAnim {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(46, 213, 115, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(46, 213, 115, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(46, 213, 115, 0); }
}
</style>

<div class="row">
  <div class="col-12">
    <!-- Top Control Bar Card -->
    <div class="card" style="margin-bottom: 14px;">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
          <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
            <i class="fa fa-line-chart" style="color: #70a1ff;"></i> 
            <span>Grafik Resource Router: <b style="color: #2ed573;"><?= htmlspecialchars($session); ?></b></span>
          </h3>
          <span style="font-size: 11px; color: #8b949e;" id="router_meta_info">
            <span class="pulse-dot"></span> Live Monitoring MikroTik RouterOS
          </span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <span style="font-weight: 600; font-size: 12px; color: #ced6e0;">Interface:</span>
          <select id="sel_iface" class="dropd" style="padding: 5px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; background: #2f3542; color: #fff; border: 1px solid #57606f; cursor: pointer;">
            <?php
            for ($i = 0; $i < $TotalReg; $i++) {
              $ifName = $getinterface[$i]['name'];
              $sel = ($ifName == $defaultIface) ? 'selected' : '';
              echo '<option value="' . htmlspecialchars($ifName) . '" ' . $sel . '>[' . ($i + 1) . '] ' . htmlspecialchars($ifName) . '</option>';
            }
            ?>
          </select>
          <span style="font-weight: 600; font-size: 12px; color: #ced6e0;">Interval:</span>
          <select id="sel_interval" class="dropd" style="padding: 5px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; background: #2f3542; color: #fff; border: 1px solid #57606f; cursor: pointer;">
            <option value="1000">1 Detik</option>
            <option value="2000" selected>2 Detik</option>
            <option value="3000">3 Detik</option>
            <option value="5000">5 Detik</option>
          </select>
          <button type="button" id="btn_pause_toggle" class="btn btn-sm" style="background: #57606f; color: #fff; font-size: 12px; padding: 5px 12px; border-radius: 4px;">
            <i class="fa fa-pause"></i> Pause
          </button>
          <a href="./?session=<?= htmlspecialchars($session); ?>" class="btn btn-sm" style="background: #1e272e; color: #7bed9f; border: 1px solid #57606f; font-size: 12px; padding: 5px 12px; border-radius: 4px; text-decoration: none;">
            <i class="fa fa-arrow-left"></i> Ke Dashboard
          </a>
        </div>
      </div>
    </div>

    <!-- 4 Key Stat Cards Row -->
    <div class="row">
      <!-- CPU Card -->
      <div class="col-3 col-box-6">
        <div class="res-stat-card">
          <div class="card-title-sm"><i class="fa fa-microchip" style="color: #ff4757;"></i> CPU Load</div>
          <div class="main-val" id="val_cpu_pct" style="color: #ff4757;">-- %</div>
          <div class="sub-val" id="val_cpu_detail">Cores: -- | Freq: --</div>
          <div class="res-progress-bg">
            <div id="bar_cpu" class="res-progress-fill" style="width: 0%; background: #ff4757;"></div>
          </div>
        </div>
      </div>

      <!-- RAM Card -->
      <div class="col-3 col-box-6">
        <div class="res-stat-card">
          <div class="card-title-sm"><i class="fa fa-tasks" style="color: #70a1ff;"></i> RAM (Memori)</div>
          <div class="main-val" id="val_ram_pct" style="color: #70a1ff;">-- %</div>
          <div class="sub-val" id="val_ram_detail">Terpakai: -- / --</div>
          <div class="res-progress-bg">
            <div id="bar_ram" class="res-progress-fill" style="width: 0%; background: #70a1ff;"></div>
          </div>
        </div>
      </div>

      <!-- Disk Card -->
      <div class="col-3 col-box-6">
        <div class="res-stat-card">
          <div class="card-title-sm"><i class="fa fa-database" style="color: #ffa502;"></i> Disk (HDD Space)</div>
          <div class="main-val" id="val_disk_pct" style="color: #ffa502;">-- %</div>
          <div class="sub-val" id="val_disk_detail">Terpakai: -- / --</div>
          <div class="res-progress-bg">
            <div id="bar_disk" class="res-progress-fill" style="width: 0%; background: #ffa502;"></div>
          </div>
        </div>
      </div>

      <!-- Bandwidth Card -->
      <div class="col-3 col-box-6">
        <div class="res-stat-card">
          <div class="card-title-sm"><i class="fa fa-exchange" style="color: #2ed573;"></i> Bandwidth Rate</div>
          <div class="main-val" id="val_bw_rx" style="color: #2ed573; font-size: 20px;">Rx: --</div>
          <div class="sub-val" id="val_bw_tx" style="color: #1e90ff; font-weight: 700;">Tx: --</div>
          <div class="res-progress-bg">
            <div id="bar_bw" class="res-progress-fill" style="width: 100%; background: linear-gradient(90deg, #1e90ff, #2ed573);"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- View Mode Selector -->
    <div style="margin-bottom: 14px; display: flex; gap: 8px; flex-wrap: wrap;">
      <button type="button" class="view-btn active" data-view="grid">
        <i class="fa fa-th-large"></i> 4-in-1 Grid View
      </button>
      <button type="button" class="view-btn" data-view="bw">
        <i class="fa fa-area-chart"></i> Bandwidth Usage
      </button>
      <button type="button" class="view-btn" data-view="cpu">
        <i class="fa fa-microchip"></i> CPU Usage
      </button>
      <button type="button" class="view-btn" data-view="ram">
        <i class="fa fa-tasks"></i> RAM Usage
      </button>
      <button type="button" class="view-btn" data-view="disk">
        <i class="fa fa-database"></i> Disk Usage
      </button>
    </div>

    <!-- 4-in-1 Grid View Section -->
    <div id="section_grid_view" class="row">
      <!-- Grid Bandwidth Chart -->
      <div class="col-6">
        <div class="chart-box">
          <div style="font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #fff; display: flex; justify-content: space-between;">
            <span><i class="fa fa-exchange" style="color: #2ed573;"></i> Bandwidth Traffic (<span class="current-iface-name"><?= htmlspecialchars($defaultIface); ?></span>)</span>
            <span style="font-size: 11px; color: #8b949e;">Tx (Upload) &amp; Rx (Download)</span>
          </div>
          <div id="chart_grid_bw" class="chart-container-sm"></div>
        </div>
      </div>

      <!-- Grid CPU Chart -->
      <div class="col-6">
        <div class="chart-box">
          <div style="font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #fff; display: flex; justify-content: space-between;">
            <span><i class="fa fa-microchip" style="color: #ff4757;"></i> CPU Load (%)</span>
            <span style="font-size: 11px; color: #8b949e;">Real-time Processor Usage</span>
          </div>
          <div id="chart_grid_cpu" class="chart-container-sm"></div>
        </div>
      </div>

      <!-- Grid RAM Chart -->
      <div class="col-6">
        <div class="chart-box">
          <div style="font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #fff; display: flex; justify-content: space-between;">
            <span><i class="fa fa-tasks" style="color: #70a1ff;"></i> RAM Memory (MB &amp; %)</span>
            <span style="font-size: 11px; color: #8b949e;">Used vs Free Memory</span>
          </div>
          <div id="chart_grid_ram" class="chart-container-sm"></div>
        </div>
      </div>

      <!-- Grid Disk Chart -->
      <div class="col-6">
        <div class="chart-box">
          <div style="font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #fff; display: flex; justify-content: space-between;">
            <span><i class="fa fa-database" style="color: #ffa502;"></i> Disk Storage (MB &amp; %)</span>
            <span style="font-size: 11px; color: #8b949e;">HDD Space Allocation</span>
          </div>
          <div id="chart_grid_disk" class="chart-container-sm"></div>
        </div>
      </div>
    </div>

    <!-- Single Focused View Section (Hidden by default) -->
    <div id="section_single_view" style="display: none;">
      <div class="chart-box">
        <div style="font-weight: 700; font-size: 14px; margin-bottom: 10px; color: #fff;" id="single_chart_title">
          Grafik Monitor
        </div>
        <div id="chart_single_focused" class="chart-container-lg"></div>
      </div>
    </div>

  </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
  var sessionName = <?= json_encode($session); ?>;
  var currentIface = $('#sel_iface').val() || 'ether1';
  var pollInterval = parseInt($('#sel_interval').val()) || 2000;
  var isPaused = false;
  var timerId = null;
  var currentView = 'grid';

  var MAX_POINTS = 30;

  Highcharts.setOptions({
    global: { useUTC: false }
  });

  function formatBpsTooltip(val) {
    if (val === 0) return '0 bps';
    var sizes = ['bps', 'kbps', 'Mbps', 'Gbps'];
    var i = parseInt(Math.floor(Math.log(val) / Math.log(1000)));
    i = Math.min(i, sizes.length - 1);
    return parseFloat((val / Math.pow(1000, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // 1. Init Grid Charts
  var chartGridBw = Highcharts.chart('chart_grid_bw', {
    chart: { type: 'areaspline', animation: Highcharts.svg },
    title: { text: null },
    xAxis: { type: 'datetime', tickPixelInterval: 100 },
    yAxis: {
      title: { text: null },
      labels: {
        formatter: function() { return formatBpsTooltip(this.value); }
      }
    },
    tooltip: {
      shared: true,
      formatter: function() {
        var s = '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/>';
        $.each(this.points, function() {
          s += '<span style="color:' + this.series.color + '">●</span> ' + this.series.name + ': <b>' + formatBpsTooltip(this.y) + '</b><br/>';
        });
        return s;
      }
    },
    series: [
      { name: 'Tx (Upload)', data: [], color: '#1e90ff' },
      { name: 'Rx (Download)', data: [], color: '#2ed573' }
    ]
  });

  var chartGridCpu = Highcharts.chart('chart_grid_cpu', {
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

  var chartGridRam = Highcharts.chart('chart_grid_ram', {
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
      { name: 'Bebas (Free)', data: [], color: '#2ed573' }
    ]
  });

  var chartGridDisk = Highcharts.chart('chart_grid_disk', {
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
      chart: { type: 'areaspline', animation: Highcharts.svg, renderTo: 'chart_single_focused' },
      xAxis: { type: 'datetime', tickPixelInterval: 120 },
      tooltip: { shared: true }
    };

    if (type === 'bw') {
      $('#single_chart_title').html('<i class="fa fa-exchange" style="color: #2ed573;"></i> Bandwidth Monitor Detail: ' + currentIface);
      options.title = { text: 'Bandwidth Traffic: ' + currentIface };
      options.yAxis = {
        title: { text: null },
        labels: { formatter: function() { return formatBpsTooltip(this.value); } }
      };
      options.tooltip = {
        shared: true,
        formatter: function() {
          var s = '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/>';
          $.each(this.points, function() {
            s += '<span style="color:' + this.series.color + '">●</span> ' + this.series.name + ': <b>' + formatBpsTooltip(this.y) + '</b><br/>';
          });
          return s;
        }
      };
      options.series = [
        { name: 'Tx (Upload)', data: chartGridBw.series[0].data.map(p => [p.x, p.y]), color: '#1e90ff' },
        { name: 'Rx (Download)', data: chartGridBw.series[1].data.map(p => [p.x, p.y]), color: '#2ed573' }
      ];
    } else if (type === 'cpu') {
      $('#single_chart_title').html('<i class="fa fa-microchip" style="color: #ff4757;"></i> Detail Beban CPU Load (%)');
      options.title = { text: 'CPU Load History (%)' };
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
      $('#single_chart_title').html('<i class="fa fa-tasks" style="color: #70a1ff;"></i> Detail Pemakaian RAM (Memori)');
      options.title = { text: 'RAM Memory Usage (MB)' };
      options.yAxis = { min: 0, title: { text: null }, labels: { format: '{value} MB' } };
      options.series = [
        { name: 'Terpakai (Used)', data: chartGridRam.series[0].data.map(p => [p.x, p.y]), color: '#70a1ff' },
        { name: 'Bebas (Free)', data: chartGridRam.series[1].data.map(p => [p.x, p.y]), color: '#2ed573' }
      ];
    } else if (type === 'disk') {
      $('#single_chart_title').html('<i class="fa fa-database" style="color: #ffa502;"></i> Detail Alokasi Penyimpanan Disk (HDD)');
      options.title = { text: 'Disk Storage (MB)' };
      options.yAxis = { min: 0, title: { text: null }, labels: { format: '{value} MB' } };
      options.series = [
        { name: 'Terpakai (Used)', data: chartGridDisk.series[0].data.map(p => [p.x, p.y]), color: '#ffa502' },
        { name: 'Sisa (Free)', data: chartGridDisk.series[1].data.map(p => [p.x, p.y]), color: '#2ed573' }
      ];
    }

    chartSingle = new Highcharts.Chart(options);
  }

  // Polling function
  function fetchMetrics() {
    if (isPaused) return;

    $.ajax({
      url: './traffic/resource_api.php?session=' + encodeURIComponent(sessionName) + '&iface=' + encodeURIComponent(currentIface),
      dataType: 'json',
      cache: false,
      success: function(data) {
        if (!data || data.status !== 'ok') return;

        var now = data.timestamp || (new Date()).getTime();

        // 1. Update CPU
        var cpuPct = data.cpu.load;
        $('#val_cpu_pct').text(cpuPct + '%');
        $('#val_cpu_detail').text('Cores: ' + data.cpu.count + ' | ' + data.cpu.freq + ' MHz');
        $('#bar_cpu').css('width', Math.min(cpuPct, 100) + '%');
        var cpuColor = cpuPct < 60 ? '#2ed573' : (cpuPct < 85 ? '#ffa502' : '#ff4757');
        $('#val_cpu_pct').css('color', cpuColor);
        $('#bar_cpu').css('background', cpuColor);

        var shiftCpu = chartGridCpu.series[0].data.length >= MAX_POINTS;
        chartGridCpu.series[0].addPoint([now, cpuPct], true, shiftCpu);

        // 2. Update RAM
        var ramPct = data.ram.percent;
        var ramUsedMb = Math.round(data.ram.used / (1024 * 1024));
        var ramFreeMb = Math.round(data.ram.free / (1024 * 1024));
        $('#val_ram_pct').text(ramPct + '%');
        $('#val_ram_detail').text('Terpakai: ' + data.ram.used_fmt + ' / ' + data.ram.total_fmt);
        $('#bar_ram').css('width', Math.min(ramPct, 100) + '%');

        var shiftRam = chartGridRam.series[0].data.length >= MAX_POINTS;
        chartGridRam.series[0].addPoint([now, ramUsedMb], false);
        chartGridRam.series[1].addPoint([now, ramFreeMb], false);
        chartGridRam.redraw(shiftRam);

        // 3. Update Disk
        var diskPct = data.disk.percent;
        var diskUsedMb = Math.round(data.disk.used / (1024 * 1024));
        var diskFreeMb = Math.round(data.disk.free / (1024 * 1024));
        $('#val_disk_pct').text(diskPct + '%');
        $('#val_disk_detail').text('Terpakai: ' + data.disk.used_fmt + ' / ' + data.disk.total_fmt);
        $('#bar_disk').css('width', Math.min(diskPct, 100) + '%');

        var shiftDisk = chartGridDisk.series[0].data.length >= MAX_POINTS;
        chartGridDisk.series[0].addPoint([now, diskUsedMb], false);
        chartGridDisk.series[1].addPoint([now, diskFreeMb], false);
        chartGridDisk.redraw(shiftDisk);

        // 4. Update Bandwidth
        var tx = data.bandwidth.tx;
        var rx = data.bandwidth.rx;
        $('#val_bw_tx').text('Tx: ' + data.bandwidth.tx_fmt);
        $('#val_bw_rx').text('Rx: ' + data.bandwidth.rx_fmt);

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
            chartSingle.series[1].addPoint([now, ramFreeMb], false);
            chartSingle.redraw(chartSingle.series[0].data.length >= MAX_POINTS);
          } else if (currentView === 'disk') {
            chartSingle.series[0].addPoint([now, diskUsedMb], false);
            chartSingle.series[1].addPoint([now, diskFreeMb], false);
            chartSingle.redraw(chartSingle.series[0].data.length >= MAX_POINTS);
          }
        }

        // Meta info
        if (data.board_name && data.uptime) {
          $('#router_meta_info').html(
            '<span class="pulse-dot"></span> ' +
            '<b>' + data.board_name + '</b> (RouterOS ' + data.version + ') | Uptime: ' + data.uptime
          );
        }
      }
    });
  }

  function startPolling() {
    if (timerId) clearInterval(timerId);
    fetchMetrics();
    timerId = setInterval(fetchMetrics, pollInterval);
  }

  startPolling();

  // Interface Change
  $('#sel_iface').change(function() {
    currentIface = $(this).val();
    $('.current-iface-name').text(currentIface);
    chartGridBw.series[0].setData([]);
    chartGridBw.series[1].setData([]);
    if (chartSingle && currentView === 'bw') {
      initSingleChart('bw');
    }
    fetchMetrics();
  });

  // Interval Change
  $('#sel_interval').change(function() {
    pollInterval = parseInt($(this).val()) || 2000;
    startPolling();
  });

  // Pause / Resume Button
  $('#btn_pause_toggle').click(function() {
    isPaused = !isPaused;
    if (isPaused) {
      $(this).html('<i class="fa fa-play"></i> Resume').css('background', '#2ed573');
    } else {
      $(this).html('<i class="fa fa-pause"></i> Pause').css('background', '#57606f');
      fetchMetrics();
    }
  });

  // View Mode Tabs
  $('.view-btn').click(function() {
    $('.view-btn').removeClass('active');
    $(this).addClass('active');

    var view = $(this).data('view');
    currentView = view;

    if (view === 'grid') {
      $('#section_single_view').hide();
      $('#section_grid_view').show();
      chartGridBw.reflow();
      chartGridCpu.reflow();
      chartGridRam.reflow();
      chartGridDisk.reflow();
    } else {
      $('#section_grid_view').hide();
      $('#section_single_view').show();
      initSingleChart(view);
      chartSingle.reflow();
    }
  });
});
</script>
