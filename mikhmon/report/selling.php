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

	$idhr = $_GET['idhr'];
	$idbl = $_GET['idbl'];
	$idbl2 = explode("/",$idhr)[0].explode("/",$idhr)[2];
	if ($idhr != ""){
		$_SESSION['report'] = "&idhr=".$idhr;
	} elseif ($idbl != ""){
		$_SESSION['report'] = "&idbl=".$idbl;
	} else {
		$_SESSION['report'] = "";
	}
	$_SESSION['idbl'] = $idbl;
	$remdata = ($_POST['remdata']);
	$prefix = $_GET['prefix'];
	

	$gettimezone = $API->comm("/system/clock/print");
	$timezone = $gettimezone[0]['time-zone-name'];
	date_default_timezone_set($timezone);

	if (isset($remdata)) {
		if (strlen($idhr) > "0") {
			if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
				$API->write('/system/script/print', false);
				$API->write('?source=' . $idhr . '', false);
				$API->write('=.proplist=.id');
				$ARREMD = $API->read();
				for ($i = 0; $i < count($ARREMD); $i++) {
					$API->write('/system/script/remove', false);
					$API->write('=.id=' . $ARREMD[$i]['.id']);
					$READ = $API->read();

				}
			}
		} elseif (strlen($idbl) > "0") {
			if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
				$API->write('/system/script/print', false);
				$API->write('?owner=' . $idbl . '', false);
				$API->write('=.proplist=.id');
				$ARREMD = $API->read();
				for ($i = 0; $i < count($ARREMD); $i++) {
					$API->write('/system/script/remove', false);
					$API->write('=.id=' . $ARREMD[$i]['.id']);
					$READ = $API->read();

				}
			}

		}
		echo "<script>window.location='./?report=selling&session=" . $session . "'</script>";
	}

	if ($prefix != "") {
		$fprefix = "-prefix-[" . $prefix . "]";
	} else {
		$fprefix = "";
	}
	if (strlen($idhr) > "0") {
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			$getData = $API->comm("/system/script/print", array(
				"?source" => "$idhr",
			));
			$TotalReg = count($getData);
		}
		$filedownload = $idhr;
		$shf = "hidden";
		$shd = "inline-block";
	} elseif (strlen($idbl) > "0") {
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			$getData = $API->comm("/system/script/print", array(
				"?owner" => "$idbl",
			));
			$TotalReg = count($getData);
		}
		$filedownload = $idbl;
		$shf = "hidden";
		$shd = "inline-block";
	} elseif ($idhr == "" || $idbl == "") {
		if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
			$getData = $API->comm("/system/script/print", array(
				"?comment" => "mikhmon",
			));
			$TotalReg = count($getData);
		}
		$filedownload = "all";
		$shf = "text";
		$shd = "none";
	}

	if (!is_array($getData)) {
		$getData = array();
	}
	$TotalReg = count($getData);

	$totalRevenue = 0;
	$totalCount = 0;
	$reportRows = array();
	$dataresume = "";

	for ($i = 0; $i < $TotalReg; $i++) {
		$getname = explode("-|-", $getData[$i]['name'] ?? "");
		$username = $getname[2] ?? "";
		if ($prefix != "" && substr($username, 0, strlen($prefix)) !== $prefix) {
			continue;
		}
		$price = floatval($getname[3] ?? 0);
		$totalRevenue += $price;
		$totalCount++;
		$dataresume .= ($getname[0] ?? "") . ($getname[3] ?? "");
		$reportRows[] = $getname;
	}

	$_SESSION['dataresume'] = $dataresume;
	$_SESSION['totalresume'] = $totalCount . '/' . $totalRevenue;

	if ($currency == in_array($currency, $cekindo['indo'])) {
		$formattedTotal = $currency . " " . number_format($totalRevenue, 0, "", ".");
	} else {
		$formattedTotal = $currency . " " . number_format($totalRevenue, 2, ".", ",");
	}
	
}
?>
		<script>
			function downloadCSV(csv, filename) {
			  var csvFile;
			  var downloadLink;
			  csvFile = new Blob([csv], {type: "text/csv"});
			  downloadLink = document.createElement("a");
			  downloadLink.download = filename;
			  downloadLink.href = window.URL.createObjectURL(csvFile);
			  downloadLink.style.display = "none";
			  document.body.appendChild(downloadLink);
			  downloadLink.click();
			}
			  
			function exportTableToCSV(filename) {
			  var csv = [];
			  var rows = document.querySelectorAll("#dataTable tr");
			    
			  for (var i = 0; i < rows.length; i++) {
			    var row = [], cols = rows[i].querySelectorAll("td, th");
			    for (var j = 0; j < cols.length; j++) {
			      var text = cols[j].innerText.trim().replace(/"/g, '""');
			      row.push('"' + text + '"');
			    }
			    csv.push(row.join(","));
			  }
			  downloadCSV(csv.join("\n"), filename);
			}

			function number_format(number, decimals, dec_point, thousands_sep) {
			  number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
			  var n = !isFinite(+number) ? 0 : +number,
			    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
			    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
			    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
			    s = '',
			    toFixedFix = function(n, prec) {
			      var k = Math.pow(10, prec);
			      return '' + (Math.round(n * k) / k).toFixed(prec);
			    };
			  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
			  if (s[0].length > 3) {
			    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
			  }
			  if ((s[1] || '').length < prec) {
			    s[1] = s[1] || '';
			    s[1] += new Array(prec - s[1].length + 1).join('0');
			  }
			  return s.join(dec);
			}

			function calculateTotal() {
			  var sum = 0;
			  var cells = document.querySelectorAll(".row-price");
			  cells.forEach(function(cell) {
			    var row = cell.closest('tr');
			    if (!row || row.style.display !== 'none') {
			      sum += parseFloat(cell.getAttribute('data-price') || 0);
			    }
			  });
			  var th = document.getElementById('total');
			  if (th) {
			    <?php if ($currency == in_array($currency, $cekindo['indo'])) { ?>
			      th.innerHTML = "<?= $currency ?> " + number_format(sum, 0, "", ".");
			    <?php } else { ?>
			      th.innerHTML = "<?= $currency ?> " + number_format(sum, 2, ".", ",");
			    <?php } ?>
			  }
			}

			$(document).ready(function(){
			  $("#filterTable").on("keyup", function() {
			    var value = $(this).val().toLowerCase();
			    $("#dataTable tbody tr").filter(function() {
			      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
			    });
			    calculateTotal();
			  });

			  $("#openResume").click(function(){
			    notify("Calculating data");
			    window.location = "./?report=resume-report&idbl=<?= $idbl;?>&session=<?= $session;?>";
			  });
			});
		</script>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-money"></i> <?= $_selling_report ?> <?= ucfirst($idhr) . ucfirst(substr($idbl,0,3).' '.substr($idbl,3,5));	if ($prefix != "") {echo " prefix [" . $prefix . "]";} ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3>
</div>
<div class="card-body">
<div class="row">
	<div class="row">
	<div class="col-12">
		<div style="padding-bottom: 5px; padding-top: 5px;">
		  <input id="filterTable" type="text" class="form-control" style="float:left; margin-top: 6px; max-width: 150px;" placeholder="<?= $_search ?>">&nbsp;
		  <button name="help" class="btn bg-primary" onclick="location.href='#help';" title="Help"><i class="fa fa-question"></i> <?= $_help ?></button>
		  <button class="btn bg-primary" onclick="exportTableToCSV('report-pacenet-<?= $filedownload . $fprefix; ?>.csv')" title="Download selling report"><i class="fa fa-download"></i> CSV</button>
			<button class="btn bg-primary" onclick="location.href='./?report=selling&session=<?= $session; ?>';" title="Reload all data"><i class="fa fa-search"></i> <?= $_all ?></button>
			<?php if(!empty($idbl)){echo '<button name="resume" id="openResume" class="btn bg-primary"title="Resume Report"><i class="fa fa-area-chart"></i> '.$_resume.'</button>';}else{
				echo '<a class="btn bg-primary" href="./?report=selling&idbl='.$idbl2.'&session='.$session.'" title="Show '.ucfirst(substr($idbl2,0,3).' '.substr($idbl2,3,5)).'"><i class="fa fa-search"></i> '.ucfirst(substr($idbl2,0,3).' '.substr($idbl2,3,5)).'</a>';}?>
		  <button name="print" class="btn bg-primary" onclick="window.open('./report/print.php?<?= explode("?report=selling&",$url)[1] ?>','_blank');" title="Print"><i class="fa fa-print"></i> <?= $_print ?></button>
		  <button style="display: <?= $shd; ?>;" name="remdata" class="btn bg-danger" onclick="location.href='#remdata';" title="Delete Data <?= $filedownload; ?>"><i class="fa fa-trash"></i> <?= $_delete_data.' '. $filedownload; ?></button>
		  <button  id="remSelected" style="display: none;" class="btn bg-red" onclick="MikhmonRemoveReportSelected()"><i class="fa fa-trash"></i> <span id="selected"></span> <?= $_selected ?></button>
		</div>
	</div>
	</div>
		<div class="input-group mr-b-10">
			<div class="input-group-1 col-box-2">
			<select style="padding:5px;" class="group-item group-item-l" title="<?= $_days ?>" id="D">
        			<?php
										$day = explode("/", $idhr)[1];
										if ($day != "") {
											echo "<option value='" . $day . "'>" . $day . "</option>";
										}
										echo "<option value=''>Day</option>";

										for ($x = 1; $x <= 31; $x++) {
											if (strlen($x) == 1) {
												$x = "0" . $x;
											} else {
												$x = $x;
											}
											echo "<option value='" . $x . "'>" . $x . "</option>";
										}
										?>
    		</select>
			</div>
			<div class="input-group-2 col-box-4">
			<select style="padding:5px;" class="group-item group-item-md" title="Month" id="M">
        			<?php
										$idbls = array(1 => "jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec");
										$idblf = array(1 => "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
										$month = explode("/", $idhr)[0];
										$month1 = substr($idbl, 0, 3);

										if ($month != "") {
											$fm = array_search($month, $idbls);
											echo "<option value='" . $month . "'>" . $idblf[$fm] . "</option>";
										} elseif ($month1 != "") {
											$fm = array_search($month1, $idbls);
											echo "<option value=" . $month1 . ">" . $idblf[$fm] . "</option>";
										} else {
											echo "<option value=" . $idbls[date("n")] . ">" . $idblf[date("n")] . "</option>";
										}
										for ($x = 1; $x <= 12; $x++) {
											echo "<option value='" . $idbls[$x] . "''>" . $idblf[$x] . "</option>";
										}
										?>
    		</select>
			</div>
			<div class="input-group-2 col-box-3">
			<select style="padding:5px;" class="group-item group-item-md" title="Year" id="Y">
        			<?php
										$year = explode("/", $idhr)[2];
										$year1 = substr($idbl, 3, 4);

										if ($year != "") {
											echo "<option>" . $year . "</option>";
										} elseif ($year1 != "") {
											echo "<option>" . $year1 . "</option>";
										}
											echo "<option>" . date("Y") . "</option>";
										
										for ($Y = 2018; $Y <= date("Y"); $Y++) {
											if ($Y == date("Y")) {
											} else {
												echo "<option value='" . $Y . "''>" . $Y . "</option>";
											}
										}
										?>
    		</select>
			</div>
            <div class="input-group-2 col-box-3">
				<div style="padding:3.5px;"  class="group-item group-item-r text-center pointer" onclick="filterR(); loader();"><i class="fa fa-search"></i> Filter</div>
			</div>
			<script type="text/javascript">
				
				function filterR(){
					var D = document.getElementById('D').value;
					var M = document.getElementById('M').value;
					var Y = document.getElementById('Y').value;
					var X = document.getElementById('filterTable').value;

					if(D !== ""){
						window.location='./?report=selling&idhr='+M+'/'+D+'/'+Y+'&prefix='+X+'&session=<?= $session; ?>';
					}else if(D === ""){
						window.location='./?report=selling&idbl='+M+Y+'&prefix='+X+'&session=<?= $session; ?>';
					}
					
				}
			</script>
		</div>
		  <div class="overflow box-bordered" style="max-height: 70vh">
			<table id="dataTable" class="table table-bordered table-hover text-nowrap">
				<thead class="thead-light">
				<tr>
				  <th colspan=6><i class="fa fa-money"></i> Laporan Penjualan (<?= htmlspecialchars($hotspotname ?: $session); ?>) <?= htmlspecialchars($filedownload . $fprefix); ?><b style="font-size:0;">,,,,</b></th>
				  <th style="text-align:right; font-weight: bold;"><?= $_total ?></th>
				  <th style="text-align:right; font-weight: bold; color: #2ed573; font-size: 1.1em;" id="total"><?= $formattedTotal; ?></th>
				</tr>
				<tr>
				  <th style="width: 50px;">&#8470;</th>
					<th>Kode Voucher</th>
					<th>Waktu</th>
					<th>IP Address</th>
					<th>Mac Address</th>
					<th>Lokasi (Router)</th>
					<th>Profile / Paket</th>
					<th style="text-align:right;">Harga</th>
				</tr>
				</thead>
				<tbody>
				<?php
				if (empty($reportRows)) {
					echo "<tr><td colspan='8' class='text-center text-muted' style='padding: 25px;'><i class='fa fa-info-circle fa-2x text-info mb-2'></i><br>Tidak ada rekaman data penjualan untuk periode ini.</td></tr>";
				} else {
					foreach ($reportRows as $idx => $row) {
						$tgl = $row[0] ?? "-";
						$ltime = $row[1] ?? "-";
						$username = $row[2] ?? "-";
						$price = floatval($row[3] ?? 0);
						$ip = (!empty($row[4]) && $row[4] != "0") ? $row[4] : "-";
						$mac = (!empty($row[5]) && $row[5] != "0") ? $row[5] : "-";
						$lokasi = $hotspotname ?: $session;
						$profile = !empty($row[7]) ? $row[7] : "Default";

						$priceDisplay = ($currency == in_array($currency, $cekindo['indo'])) 
							? number_format($price, 0, "", ".") 
							: number_format($price, 2, ".", ",");

						echo "<tr>";
						echo "<td>" . ($idx + 1) . "</td>";
						echo "<td><b style='font-family: Consolas, monospace; color: #2ed573;'>" . htmlspecialchars($username) . "</b></td>";
						echo "<td>" . htmlspecialchars($tgl . " " . $ltime) . "</td>";
						echo "<td>" . htmlspecialchars($ip) . "</td>";
						echo "<td>" . htmlspecialchars($mac) . "</td>";
						echo "<td><span class='badge' style='background:#3742fa; color:#fff;'>" . htmlspecialchars($lokasi) . "</span></td>";
						echo "<td>" . htmlspecialchars($profile) . "</td>";
						echo "<td class='row-price' data-price='" . $price . "' style='text-align:right; font-weight: bold;'>" . htmlspecialchars($priceDisplay) . "</td>";
						echo "</tr>";
					}
				}
				?>
				</tbody>
			</table>
		</div>
</div>
</div>
</div>

<!-- Modal -->
<div class="modal-window" id="remdata" aria-hidden="true">
  <div>
  	<header><h1><?= $_confirm ?></h1></header>
  	<a style="font-weight:bold;" href="#" title="Close" class="modal-close">X</a>
	<p>
			<?= $_delete_report ?>
	</p>
	<form autocomplete="off" method="post" action="">
	<center>
	<button type="submit" name="remdata" title="Yes" class="btn bg-primary">Yes</button>&nbsp;
	<a class="btn bg-secondary" href="#" title="Close" class="modal-close">No</a>
	</center>
	</form>
  </div>
</div>
<div class="modal-window" id="help" aria-hidden="true">
  <div>
  	<header><h1><?= $_help ?></h1></header>
  	<a style="font-weight:bold;" href="#" title="Close" class="modal-close">X</a>
	<p>
			<?= $_help_report ?>
	</p>
  </div>
</div>
</div>
