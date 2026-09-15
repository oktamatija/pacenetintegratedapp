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
	$is_connected = !empty($API) && $API->connected;
	$log = array();
	$TotalReg = 0;

	if ($is_connected) {
		$getlog = $API->comm("/log/print", array(
			"?topics" => "hotspot,info,debug"
		));
		if (empty($getlog) || !is_array($getlog) || count($getlog) == 0) {
			// Fallback to general hotspot topics
			$getlog = $API->comm("/log/print", array(
				"?topics" => "hotspot"
			));
		}
		if (!empty($getlog) && is_array($getlog)) {
			$log = array_reverse($getlog);
			$TotalReg = count($log);
		}
	}
}
?>
<div class="row">
<div class="col-12">
<?php if (!$is_connected): ?>
<div class="card bg-danger text-white mb-3" style="margin-bottom: 15px; border-left: 5px solid #dc3545;">
	<div class="card-body" style="padding: 12px 15px;">
		<i class="fa fa-exclamation-triangle mr-2"></i> <strong>Router Sedang Offline / Tidak Terhubung:</strong> Sesi <strong><?= htmlspecialchars($session ?? 'MikroTik'); ?></strong> (IP: <?= htmlspecialchars($iphost ?? '-'); ?>) saat ini tidak dapat dihubungi melalui API. Silakan pastikan router menyala dan terhubung, atau ganti sesi ke router aktif melalui menu atas / <a href="./admin.php?id=sessions" class="text-white" style="text-decoration: underline; font-weight: bold;">Daftar Router</a>.
	</div>
</div>
<?php endif; ?>
<div class="card">
<div class="card-header">
    <h3><i class=" fa fa-align-justify"></i> <?= $_hotspot_log ?> &nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer " title="Reload data"></i></h3>
</div>
<div class="card-body">

<div style="max-width: 350px;">
    <input id="filterTable" type="text" class="form-control" placeholder="Search.."> 
</div>
<div style="padding: 5px; max-height: 75vh;" class="mr-t-10 overflow">
<table class="table table-sm table-bordered table-hover" id="dataTable" >
	<thead>
        <tr>
            <th><?= $_time ?></th>
            <th><?= $_users ?> (IP)</th>
            <th><?= $_messages ?></th>
        </tr>
    </thead>
	<tbody>
<?php
$rendered_rows = 0;
if ($TotalReg > 0) {
	for ($i = 0; $i < $TotalReg; $i++) {
		$raw_msg = $log[$i]['message'] ?? '';
		$time = $log[$i]['time'] ?? '';
		
		if (substr($raw_msg, 0, 2) == "->") {
			$mess = explode(":", $raw_msg);
			echo "<tr>";
			echo "<td>" . htmlspecialchars($time) . "</td>";
			echo "<td>";
			if (count($mess) > 6) {
				echo htmlspecialchars($mess[1] . ":" . $mess[2] . ":" . $mess[3] . ":" . $mess[4] . ":" . $mess[5] . ":" . $mess[6]);
			} else {
				echo htmlspecialchars($mess[1] ?? '-');
			}
			echo "</td>";
			echo "<td>";
			if (count($mess) > 6) {
				echo htmlspecialchars(trim(str_replace("trying to", "", ($mess[7] ?? '') . " " . ($mess[8] ?? '') . " " . ($mess[9] ?? '') . " " . ($mess[10] ?? ''))));
			} else {
				echo htmlspecialchars(trim(str_replace("trying to", "", ($mess[2] ?? '') . " " . ($mess[3] ?? '') . " " . ($mess[4] ?? '') . " " . ($mess[5] ?? ''))));
			}
			echo "</td>";
			echo "</tr>";
			$rendered_rows++;
		} else {
			// Plain hotspot log without "->" prefix
			$mess = explode(":", $raw_msg);
			echo "<tr>";
			echo "<td>" . htmlspecialchars($time) . "</td>";
			echo "<td>";
			if (count($mess) > 1) {
				echo htmlspecialchars(trim($mess[0]));
			} else {
				echo "Hotspot";
			}
			echo "</td>";
			echo "<td>" . htmlspecialchars(trim(count($mess) > 1 ? substr($raw_msg, strlen($mess[0]) + 1) : $raw_msg)) . "</td>";
			echo "</tr>";
			$rendered_rows++;
		}
	}
}

if ($rendered_rows == 0) {
	if (!$is_connected) {
		echo '<tr><td colspan="3" class="text-center py-4 text-muted" style="padding: 25px;"><i class="fa fa-chain-broken fa-2x text-danger mb-2"></i><br><strong>Router tidak terhubung ke Cloud.</strong><br><span style="font-size:12px;">Sesi router ini sedang offline sehingga riwayat log tidak dapat diambil.</span></td></tr>';
	} else {
		echo '<tr><td colspan="3" class="text-center py-4 text-muted" style="padding: 25px;"><i class="fa fa-info-circle fa-2x text-info mb-2"></i><br><strong>Belum ada rekaman Hotspot Log.</strong><br><span style="font-size:12px;">Log akan otomatis muncul saat ada pengguna hotspot yang terhubung atau mencoba login.</span></td></tr>';
	}
}
?>
	</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
