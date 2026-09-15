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


// get MikroTik system clock
  $getclock = $API->comm("/system/clock/print");
  $clock = $getclock[0];
  $timezone = $getclock[0]['time-zone-name'];
  $_SESSION['timezone'] = $timezone;
  date_default_timezone_set($timezone);

// get system resource MikroTik
  $getresource = $API->comm("/system/resource/print");
  $resource = $getresource[0];

// get routeboard info
  $getrouterboard = $API->comm("/system/routerboard/print");
  $routerboard = $getrouterboard[0];
/*
// move hotspot log to disk *
  $getlogging = $API->comm("/system/logging/print", array("?prefix" => "->", ));
  $logging = $getlogging[0];
  if ($logging['prefix'] == "->") {
  } else {
    $API->comm("/system/logging/add", array("action" => "disk", "prefix" => "->", "topics" => "hotspot,info,debug", ));
  }

// get hotspot log
  $getlog = $API->comm("/log/print", array("?topics" => "hotspot,info,debug", ));
  $log = array_reverse($getlog);
  $THotspotLog = count($getlog);
*/
// get & counting hotspot users
  $countallusers = $API->comm("/ip/hotspot/user/print", array("count-only" => ""));
  if ($countallusers < 2) {
    $uunit = "item";
  } elseif ($countallusers > 1) {
    $uunit = "items";
  }

// get & counting hotspot active
  $counthotspotactive = $API->comm("/ip/hotspot/active/print", array("count-only" => ""));
  if ($counthotspotactive < 2) {
    $hunit = "item";
  } elseif ($counthotspotactive > 1) {
    $hunit = "items";
  }

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
  }
/*
// get selling report
    $thisD = date("d");
    $thisM = strtolower(date("M"));
    $thisY = date("Y");

    if (strlen($thisD) == 1) {
      $thisD = "0" . $thisD;
    } else {
      $thisD = $thisD;
    }

    $idhr = $thisM . "/" . $thisD . "/" . $thisY;
    $idbl = $thisM . $thisY;

    $getSRHr = $API->comm("/system/script/print", array(
      "?source" => "$idhr",
    ));
    $TotalRHr = count($getSRHr);
    $getSRBl = $API->comm("/system/script/print", array(
      "?owner" => "$idbl",
    ));
    $TotalRBl = count($getSRBl);

    for ($i = 0; $i < $TotalRHr; $i++) {

      $tHr += explode("-|-", $getSRHr[$i]['name'])[3];

    }
    for ($i = 0; $i < $TotalRBl; $i++) {

      $tBl += explode("-|-", $getSRBl[$i]['name'])[3];
    }
  }*/
}
?>
    
<div id="reloadHome">

    <div id="r_1" class="row">
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-calendar"></i></div>
              <div class="box-group-area">
                <span ><?= $_system_date_time ?><br>
                    <?php 
                    echo ucfirst($clock['date']) . " " . $clock['time'] . "<br>
                    ".$_uptime." : " . formatDTM($resource['uptime']);
                    $_SESSION[$session.'sdate'] = $clock['date'];
                    ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-info-circle"></i></div>
              <div class="box-group-area">
                <span >
                    <?php
                    echo $_board_name." : " . $resource['board-name'] . "<br/>
                    ".$_model." : " . $routerboard['model'] . "<br/>
                    Router OS : " . $resource['version'];
                    ?>
                </span>
              </div>
            </div>
          </div>
        </div>
    <div class="col-4">
      <div class="box bmh-75 box-bordered">
        <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-server"></i></div>
              <div class="box-group-area">
                <span >
                    <?php
                    echo $_cpu_load." : " . $resource['cpu-load'] . "%<br/>
                    ".$_free_memory." : " . formatBytes($resource['free-memory'], 2) . "<br/>
                    ".$_free_hdd." : " . formatBytes($resource['free-hdd-space'], 2);
                    ?>
                    <div style="margin-top: 4px;">
                      <a href="./?system=resource-graph&session=<?= $session; ?>" style="color: #2ed573; font-weight: 700; font-size: 11px; text-decoration: none;">
                        <i class="fa fa-line-chart"></i> Lihat Grafik Resource &rarr;
                      </a>
                    </div>
                </span>
                </div>
              </div>
            </div>
          </div> 
      </div>

        <div class="row">
          <div  class="col-8">
            <div id="r_2"class="row">
            <div class="card">
              <div class="card-header"><h3><i class="fa fa-wifi"></i> Hotspot</h3></div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-4 col-box-6">
                      <div class="box bg-blue bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot=active&session=<?= $session; ?>">
                          <h1><?= $counthotspotactive; ?>
                              <span style="font-size: 15px;"><?= $hunit; ?></span>
                            </h1>
                          <div>
                            <i class="fa fa-laptop"></i> <?= $_hotspot_active ?>
                          </div>
                        </a>
                      </div>
                    </div>
                    <div class="col-4 col-box-6">
                      <div class="box bg-green bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot=users&profile=all&session=<?= $session; ?>">
                              <h1><?= $countallusers; ?>
                                <span style="font-size: 15px;"><?= $uunit; ?></span>
                              </h1>
                        <div>
                              <i class="fa fa-users"></i> <?= $_hotspot_users ?>
                            </div>
                        </a>
                      </div>
                    </div>
                    <div class="col-4 col-box-6">
                      <div class="box bmh-75" style="background: #e67e22; color: #fff;">
                        <a onclick="cancelPage()" href="./?hotspot=users-by-profile&session=<?= $session; ?>">
                          <div>
                            <h1><i class="fa fa-ticket"></i>
                                <span style="font-size: 15px;">Voucher</span>
                            </h1>
                          </div>
                          <div>
                              <i class="fa fa-tags"></i> <?= $_vouchers ?>
                          </div>
                        </a>
                      </div>
                    </div>
                  </div>
                  <div class="row" style="margin-top: 5px;">
                    <div class="col-4 col-box-6">
                      <div class="box bmh-75" style="background: #8e44ad; color: #fff;">
                        <a onclick="cancelPage()" href="./?hotspot=quick-print&session=<?= $session; ?>">
                          <div>
                            <h1><i class="fa fa-print"></i>
                                <span style="font-size: 15px;">Print</span>
                            </h1>
                          </div>
                          <div>
                              <i class="fa fa-print"></i> <?= $_quick_print ?>
                          </div>
                        </a>
                      </div>
                    </div>
                    <div class="col-4 col-box-6">
                      <div class="box bg-yellow bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot-user=add&session=<?= $session; ?>">
                          <div>
                            <h1><i class="fa fa-user-plus"></i>
                                <span style="font-size: 15px;"><?= $_add ?></span>
                            </h1>
                          </div>
                          <div>
                              <i class="fa fa-user-plus"></i> <?= $_hotspot_users ?>
                          </div>
                        </a>
                      </div>
                    </div>
                    <div class="col-4 col-box-6">
                      <div class="box bg-red bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot-user=generate&session=<?= $session; ?>">
                          <div>
                            <h1><i class="fa fa-magic"></i>
                                <span style="font-size: 15px;"><?= $_generate ?></span>
                            </h1>
                          </div>
                          <div>
                              <i class="fa fa-user-plus"></i> <?= $_hotspot_users ?>
                          </div>
                      </a>
                    </div>
                  </div></div>
                </div>
              </div>
            </div>
          </div>
          </div>
            <div class="card">
              <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <h3 style="margin: 0;"><i class="fa fa-line-chart" style="color: #70a1ff;"></i> Resource &amp; Traffic Monitor</h3>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <select id="dash_interface" class="dropd" style="padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 4px; background: #2f3542; color: #fff; border: 1px solid #57606f; cursor: pointer;">
                    <?php 
                    $getinterface = $API->comm("/interface/print");
                    $TotalReg = count($getinterface);
                    $interface = $getinterface[$iface - 1]['name'] ?? ($getinterface[0]['name'] ?? 'ether1');
                    for ($i = 0; $i < $TotalReg; $i++) {
                      $ifName = $getinterface[$i]['name'];
                      $sel = ($ifName == $interface) ? 'selected' : '';
                      echo '<option value="' . htmlspecialchars($ifName) . '" ' . $sel . '>[' . ($i + 1) . '] ' . htmlspecialchars($ifName) . '</option>';
                    }
                    ?>
                  </select>
                  <a href="./?system=resource-graph&session=<?= htmlspecialchars($session); ?>" class="btn btn-sm" style="background: #3742fa; color: #fff; font-size: 11px; padding: 4px 10px; border-radius: 4px; text-decoration: none;" title="Buka Halaman Grafik Lengkap">
                    <i class="fa fa-expand"></i> Grafik Penuh
                  </a>
                </div>
              </div>

              <div class="card-body">
                <style>
                  .dash-quick-pills {
                    display: flex;
                    gap: 6px;
                    margin-bottom: 10px;
                    flex-wrap: wrap;
                  }
                  .dash-pill {
                    background: #23272e;
                    border: 1px solid #373e47;
                    border-radius: 4px;
                    padding: 4px 10px;
                    font-size: 11px;
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    cursor: pointer;
                    transition: border-color 0.2s;
                  }
                  .dash-pill:hover {
                    border-color: #70a1ff;
                  }
                  .dash-tab-btn {
                    background: #2f3542;
                    color: #ced6e0;
                    border: 1px solid #57606f;
                    padding: 4px 10px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                  }
                  .dash-tab-btn:hover {
                    background: #57606f;
                    color: #fff;
                  }
                  .dash-tab-btn.active {
                    background: #3742fa;
                    color: #fff;
                    border-color: #3742fa;
                  }
                </style>

                <!-- Quick Stat Pills -->
                <div class="dash-quick-pills">
                  <div class="dash-pill" onclick="$('.dash-tab-btn[data-type=\'cpu\']').click();">
                    <i class="fa fa-microchip" style="color: #ff4757;"></i> CPU: <span id="pill_dash_cpu" style="color: #ff4757; font-weight: 700;">-- %</span>
                  </div>
                  <div class="dash-pill" onclick="$('.dash-tab-btn[data-type=\'ram\']').click();">
                    <i class="fa fa-tasks" style="color: #70a1ff;"></i> RAM: <span id="pill_dash_ram" style="color: #70a1ff; font-weight: 700;">-- %</span>
                  </div>
                  <div class="dash-pill" onclick="$('.dash-tab-btn[data-type=\'disk\']').click();">
                    <i class="fa fa-database" style="color: #ffa502;"></i> Disk: <span id="pill_dash_disk" style="color: #ffa502; font-weight: 700;">-- %</span>
                  </div>
                  <div class="dash-pill" onclick="$('.dash-tab-btn[data-type=\'bw\']').click();">
                    <i class="fa fa-exchange" style="color: #2ed573;"></i> <span id="pill_dash_bw" style="color: #2ed573; font-weight: 700;">Tx: -- | Rx: --</span>
                  </div>
                </div>

                <!-- Tabs Selector -->
                <div style="display: flex; gap: 6px; margin-bottom: 12px; flex-wrap: wrap;">
                  <button type="button" class="dash-tab-btn active" data-type="bw"><i class="fa fa-area-chart"></i> Bandwidth (Tx/Rx)</button>
                  <button type="button" class="dash-tab-btn" data-type="cpu"><i class="fa fa-microchip"></i> CPU (%)</button>
                  <button type="button" class="dash-tab-btn" data-type="ram"><i class="fa fa-tasks"></i> RAM (Memori)</button>
                  <button type="button" class="dash-tab-btn" data-type="disk"><i class="fa fa-database"></i> Disk (Storage)</button>
                  <button type="button" class="dash-tab-btn" data-type="grid"><i class="fa fa-th-large"></i> 4-in-1 Grid</button>
                </div>

                <!-- Single Active Chart Container -->
                <div id="dashSingleChartContainer">
                  <div id="trafficMonitor" style="width: 100%; height: 320px;"></div>
                </div>

                <!-- 4-in-1 Grid Container (Hidden by default) -->
                <div id="dashGridChartContainer" class="row" style="display: none; margin: 0 -5px;">
                  <div class="col-6" style="padding: 0 5px; margin-bottom: 8px;">
                    <div style="font-size: 11px; font-weight: 700; color: #2ed573; margin-bottom: 2px;"><i class="fa fa-exchange"></i> Bandwidth (<span class="dash-current-iface"><?= htmlspecialchars($interface); ?></span>)</div>
                    <div id="dash_grid_bw" style="height: 160px;"></div>
                  </div>
                  <div class="col-6" style="padding: 0 5px; margin-bottom: 8px;">
                    <div style="font-size: 11px; font-weight: 700; color: #ff4757; margin-bottom: 2px;"><i class="fa fa-microchip"></i> CPU Load (%)</div>
                    <div id="dash_grid_cpu" style="height: 160px;"></div>
                  </div>
                  <div class="col-6" style="padding: 0 5px; margin-bottom: 8px;">
                    <div style="font-size: 11px; font-weight: 700; color: #70a1ff; margin-bottom: 2px;"><i class="fa fa-tasks"></i> RAM Memory (MB)</div>
                    <div id="dash_grid_ram" style="height: 160px;"></div>
                  </div>
                  <div class="col-6" style="padding: 0 5px; margin-bottom: 8px;">
                    <div style="font-size: 11px; font-weight: 700; color: #ffa502; margin-bottom: 2px;"><i class="fa fa-database"></i> Disk Storage (MB)</div>
                    <div id="dash_grid_disk" style="height: 160px;"></div>
                  </div>
                </div>

                <script type="text/javascript">
                  $(document).ready(function () {
                    var sessiondata = <?= json_encode($session); ?>;
                    var currentIface = "<?= htmlspecialchars($interface); ?>";
                    var savedIface = sessionStorage.getItem('DashInterface_' + sessiondata);
                    if (savedIface) {
                      currentIface = savedIface;
                      $('#dash_interface').val(savedIface);
                    }

                    var activeType = 'bw';
                    var MAX_POINTS = 25;
                    var dashMainChart;
                    var dashGridBw, dashGridCpu, dashGridRam, dashGridDisk;
                    var gridInitialized = false;

                    Highcharts.setOptions({
                      global: { useUTC: false }
                    });

                    function fmtBps(val) {
                      if (val === 0) return '0 bps';
                      var sizes = ['bps', 'kbps', 'Mbps', 'Gbps'];
                      var i = parseInt(Math.floor(Math.log(val) / Math.log(1000)));
                      i = Math.min(i, sizes.length - 1);
                      return parseFloat((val / Math.pow(1000, i)).toFixed(2)) + ' ' + sizes[i];
                    }

                    function buildMainChart(type) {
                      if (dashMainChart) {
                        dashMainChart.destroy();
                      }

                      var opts = {
                        chart: {
                          renderTo: 'trafficMonitor',
                          animation: Highcharts.svg,
                          type: 'areaspline'
                        },
                        xAxis: {
                          type: 'datetime',
                          tickPixelInterval: 120
                        }
                      };

                      if (type === 'bw') {
                        opts.title = { text: 'Bandwidth Traffic: ' + currentIface };
                        opts.yAxis = {
                          title: { text: null },
                          labels: { formatter: function () { return fmtBps(this.value); } }
                        };
                        opts.tooltip = {
                          shared: true,
                          formatter: function () {
                            var s = '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/>';
                            $.each(this.points, function () {
                              s += '<span style="color:' + this.series.color + '">●</span> ' + this.series.name + ': <b>' + fmtBps(this.y) + '</b><br/>';
                            });
                            return s;
                          }
                        };
                        opts.series = [
                          { name: 'Tx (Upload)', data: [], color: '#1e90ff' },
                          { name: 'Rx (Download)', data: [], color: '#2ed573' }
                        ];
                      } else if (type === 'cpu') {
                        opts.title = { text: 'CPU Load (%)' };
                        opts.yAxis = {
                          min: 0,
                          max: 100,
                          title: { text: null },
                          labels: { format: '{value}%' }
                        };
                        opts.tooltip = {
                          formatter: function () {
                            return '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/><span style="color:#ff4757">●</span> CPU Load: <b>' + this.y + '%</b>';
                          }
                        };
                        opts.series = [{ name: 'CPU Load', data: [], color: '#ff4757' }];
                      } else if (type === 'ram') {
                        opts.title = { text: 'RAM Memory Usage (MB)' };
                        opts.yAxis = { min: 0, title: { text: null }, labels: { format: '{value} MB' } };
                        opts.tooltip = {
                          shared: true,
                          formatter: function () {
                            var s = '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/>';
                            $.each(this.points, function () {
                              s += '<span style="color:' + this.series.color + '">●</span> ' + this.series.name + ': <b>' + this.y + ' MB</b><br/>';
                            });
                            return s;
                          }
                        };
                        opts.series = [
                          { name: 'Terpakai (Used)', data: [], color: '#70a1ff' },
                          { name: 'Bebas (Free)', data: [], color: '#2ed573' }
                        ];
                      } else if (type === 'disk') {
                        opts.title = { text: 'Disk Storage Space (MB)' };
                        opts.yAxis = { min: 0, title: { text: null }, labels: { format: '{value} MB' } };
                        opts.tooltip = {
                          shared: true,
                          formatter: function () {
                            var s = '<b>' + Highcharts.dateFormat('%H:%M:%S', this.x) + '</b><br/>';
                            $.each(this.points, function () {
                              s += '<span style="color:' + this.series.color + '">●</span> ' + this.series.name + ': <b>' + this.y + ' MB</b><br/>';
                            });
                            return s;
                          }
                        };
                        opts.series = [
                          { name: 'Terpakai (Used)', data: [], color: '#ffa502' },
                          { name: 'Sisa (Free)', data: [], color: '#2ed573' }
                        ];
                      }

                      dashMainChart = new Highcharts.Chart(opts);
                    }

                    function initGridCharts() {
                      if (gridInitialized) return;
                      gridInitialized = true;

                      dashGridBw = Highcharts.chart('dash_grid_bw', {
                        chart: { type: 'areaspline', animation: Highcharts.svg },
                        title: { text: null },
                        xAxis: { type: 'datetime', tickPixelInterval: 80 },
                        yAxis: { title: { text: null }, labels: { formatter: function () { return fmtBps(this.value); } } },
                        tooltip: { shared: true },
                        series: [
                          { name: 'Tx', data: [], color: '#1e90ff' },
                          { name: 'Rx', data: [], color: '#2ed573' }
                        ]
                      });

                      dashGridCpu = Highcharts.chart('dash_grid_cpu', {
                        chart: { type: 'areaspline', animation: Highcharts.svg },
                        title: { text: null },
                        xAxis: { type: 'datetime', tickPixelInterval: 80 },
                        yAxis: { min: 0, max: 100, title: { text: null }, labels: { format: '{value}%' } },
                        series: [{ name: 'CPU', data: [], color: '#ff4757' }]
                      });

                      dashGridRam = Highcharts.chart('dash_grid_ram', {
                        chart: { type: 'areaspline', animation: Highcharts.svg },
                        title: { text: null },
                        xAxis: { type: 'datetime', tickPixelInterval: 80 },
                        yAxis: { min: 0, title: { text: null }, labels: { format: '{value}M' } },
                        tooltip: { shared: true },
                        series: [
                          { name: 'Used', data: [], color: '#70a1ff' },
                          { name: 'Free', data: [], color: '#2ed573' }
                        ]
                      });

                      dashGridDisk = Highcharts.chart('dash_grid_disk', {
                        chart: { type: 'areaspline', animation: Highcharts.svg },
                        title: { text: null },
                        xAxis: { type: 'datetime', tickPixelInterval: 80 },
                        yAxis: { min: 0, title: { text: null }, labels: { format: '{value}M' } },
                        tooltip: { shared: true },
                        series: [
                          { name: 'Used', data: [], color: '#ffa502' },
                          { name: 'Free', data: [], color: '#2ed573' }
                        ]
                      });
                    }

                    buildMainChart('bw');

                    function pollResourceData() {
                      $.ajax({
                        url: './traffic/resource_api.php?session=' + encodeURIComponent(sessiondata) + '&iface=' + encodeURIComponent(currentIface),
                        dataType: 'json',
                        cache: false,
                        success: function (data) {
                          if (!data || data.status !== 'ok') return;

                          var now = data.timestamp || (new Date()).getTime();

                          // Update Pills
                          var cpuLoad = data.cpu.load;
                          var cpuColor = cpuLoad < 60 ? '#2ed573' : (cpuLoad < 85 ? '#ffa502' : '#ff4757');
                          $('#pill_dash_cpu').text(cpuLoad + '%').css('color', cpuColor);
                          $('#pill_dash_ram').text(data.ram.percent + '% (' + data.ram.used_fmt + ')');
                          $('#pill_dash_disk').text(data.disk.percent + '% (' + data.disk.used_fmt + ')');
                          $('#pill_dash_bw').text('Tx: ' + data.bandwidth.tx_fmt + ' | Rx: ' + data.bandwidth.rx_fmt);

                          var tx = data.bandwidth.tx;
                          var rx = data.bandwidth.rx;
                          var ramUsedMb = Math.round(data.ram.used / (1024 * 1024));
                          var ramFreeMb = Math.round(data.ram.free / (1024 * 1024));
                          var diskUsedMb = Math.round(data.disk.used / (1024 * 1024));
                          var diskFreeMb = Math.round(data.disk.free / (1024 * 1024));

                          // Update Active Main Chart
                          if (activeType !== 'grid' && dashMainChart) {
                            if (activeType === 'bw') {
                              var shiftBw = dashMainChart.series[0].data.length >= MAX_POINTS;
                              dashMainChart.series[0].addPoint([now, tx], false);
                              dashMainChart.series[1].addPoint([now, rx], false);
                              dashMainChart.redraw(shiftBw);
                            } else if (activeType === 'cpu') {
                              var shiftCpu = dashMainChart.series[0].data.length >= MAX_POINTS;
                              dashMainChart.series[0].addPoint([now, cpuLoad], true, shiftCpu);
                            } else if (activeType === 'ram') {
                              var shiftRam = dashMainChart.series[0].data.length >= MAX_POINTS;
                              dashMainChart.series[0].addPoint([now, ramUsedMb], false);
                              dashMainChart.series[1].addPoint([now, ramFreeMb], false);
                              dashMainChart.redraw(shiftRam);
                            } else if (activeType === 'disk') {
                              var shiftDisk = dashMainChart.series[0].data.length >= MAX_POINTS;
                              dashMainChart.series[0].addPoint([now, diskUsedMb], false);
                              dashMainChart.series[1].addPoint([now, diskFreeMb], false);
                              dashMainChart.redraw(shiftDisk);
                            }
                          }

                          // Update Grid if visible
                          if (activeType === 'grid' && gridInitialized) {
                            var sBw = dashGridBw.series[0].data.length >= MAX_POINTS;
                            dashGridBw.series[0].addPoint([now, tx], false);
                            dashGridBw.series[1].addPoint([now, rx], false);
                            dashGridBw.redraw(sBw);

                            var sCpu = dashGridCpu.series[0].data.length >= MAX_POINTS;
                            dashGridCpu.series[0].addPoint([now, cpuLoad], true, sCpu);

                            var sRam = dashGridRam.series[0].data.length >= MAX_POINTS;
                            dashGridRam.series[0].addPoint([now, ramUsedMb], false);
                            dashGridRam.series[1].addPoint([now, ramFreeMb], false);
                            dashGridRam.redraw(sRam);

                            var sDisk = dashGridDisk.series[0].data.length >= MAX_POINTS;
                            dashGridDisk.series[0].addPoint([now, diskUsedMb], false);
                            dashGridDisk.series[1].addPoint([now, diskFreeMb], false);
                            dashGridDisk.redraw(sDisk);
                          }
                        }
                      });
                    }

                    pollResourceData();
                    var pollTimer = setInterval(pollResourceData, 2500);

                    // Interface selector change
                    $('#dash_interface').change(function () {
                      var sel = $(this).val();
                      if (sel) {
                        currentIface = sel;
                        sessionStorage.setItem('DashInterface_' + sessiondata, sel);
                        $('.dash-current-iface').text(currentIface);
                        if (activeType === 'bw' && dashMainChart) {
                          dashMainChart.setTitle({ text: 'Bandwidth Traffic: ' + currentIface });
                          dashMainChart.series[0].setData([]);
                          dashMainChart.series[1].setData([]);
                        }
                        if (dashGridBw) {
                          dashGridBw.series[0].setData([]);
                          dashGridBw.series[1].setData([]);
                        }
                        pollResourceData();
                      }
                    });

                    // Tab Button Clicks
                    $('.dash-tab-btn').click(function () {
                      $('.dash-tab-btn').removeClass('active');
                      $(this).addClass('active');

                      var type = $(this).data('type');
                      activeType = type;

                      if (type === 'grid') {
                        $('#dashSingleChartContainer').hide();
                        $('#dashGridChartContainer').show();
                        initGridCharts();
                        dashGridBw.reflow();
                        dashGridCpu.reflow();
                        dashGridRam.reflow();
                        dashGridDisk.reflow();
                      } else {
                        $('#dashGridChartContainer').hide();
                        $('#dashSingleChartContainer').show();
                        buildMainChart(type);
                      }
                      pollResourceData();
                    });
                  });
                </script>
              </div>
            </div>      </div>  
            <div class="col-4">
            <div id="r_4" class="row">
              <div <?= $lreport; ?> class="box bmh-75 box-bordered">
                <div class="box-group">
                  <div class="box-group-icon"><i class="fa fa-money"></i></div>
                    <div class="box-group-area">
                      <span >
                        <div id="reloadLreport">
                          <?php 
                          if ($_SESSION[$session.'sdate'] == $_SESSION[$session.'idhr']){
                            echo $_income." <br/>" . "
                          ".$_today." " . $_SESSION[$session.'totalHr'] . "vcr : " . $currency . " " . $_SESSION[$session.'dincome']. "<br/>
                          ".$_this_month." " . $_SESSION[$session.'totalBl'] . "vcr : " . $currency . " " . $_SESSION[$session.'mincome']; 
                          }else{
                            echo "<div id='loader' ><i><span> <i class='fa fa-circle-o-notch fa-spin'></i> ". $_processing." </i></div>";
                          }
                          ?>                       
                        </div>
                    </span>
                </div>
              </div>
            </div>
            </div>
            <div id="r_3" class="row">
            <div class="card">
              <div class="card-header">
                <h3><a onclick="cancelPage()" href="./?hotspot=log&session=<?= $session; ?>" title="Open Hotspot Log" ><i class="fa fa-align-justify"></i> <?= $_hotspot_log ?></a></h3></div>
                  <div class="card-body">
                    <div style="padding: 5px; height: <?= $logh; ?> ;" class="mr-t-10 overflow">
                      <table class="table table-sm table-bordered table-hover" style="font-size: 12px; td.padding:2px;">
                        <thead>
                          <tr>
                            <th><?= $_time ?></th>
                            <th><?= $_users ?> (IP)</th>
                            <th><?= $_messages ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td colspan="3" class="text-center">
                            <div id="loader" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></div>
                            </td>
                          </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              </div>
            </div>
</div>
</div>
