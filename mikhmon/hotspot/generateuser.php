<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *  Enhanced Batch RSC Engine & Performance Optimization 2026.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 */
session_start();
error_reporting(0);

ini_set('max_execution_time', 600);
set_time_limit(600);

if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {
date_default_timezone_set($_SESSION['timezone']);

	$genprof = $_GET['genprof'];
	if ($genprof != "") {
		$getprofile = $API->comm("/ip/hotspot/user/profile/print", array(
			"?name" => "$genprof",
		));
		$ponlogin = $getprofile[0]['on-login'];
		$getprice = explode(",", $ponlogin)[2];
		if ($getprice == "0") {
			$getprice = "";
		}

		$getvalid = explode(",", $ponlogin)[3];

		$getlocku = explode(",", $ponlogin)[6];
		if ($getlocku == "") {
			$getprice = "Disable";
		}

		if ($currency == in_array($currency, $cekindo['indo'])) {
			$getprice = $currency . " " . number_format((float)$getprice, 0, ",", ".");
		} else {
			$getprice = $currency . " " . number_format((float)$getprice);
		}
		$ValidPrice = "<b>Validity : " . $getvalid . " | Price : " . $getprice . " | Lock User : " . $getlocku . "</b>";
	}

	$srvlist = $API->comm("/ip/hotspot/print");

	if (isset($_POST['qty'])) {
		@ini_set('max_execution_time', 900);
		@ini_set('memory_limit', '1024M');
		
		$qty = intval($_POST['qty']);
		if ($qty > 100000) {
			$qty = 100000;
		}
		if ($qty < 1) {
			$qty = 1;
		}
		$server = ($_POST['server']);
		$user = ($_POST['user']);
		$userl = intval($_POST['userl']);
		if ($userl < 3) $userl = 8;
		$prefix = ($_POST['prefix']);
		$char = ($_POST['char']);
		$profile = ($_POST['profile']);
		$timelimit = ($_POST['timelimit']);
		$datalimit = ($_POST['datalimit']);
		$adcomment = ($_POST['adcomment']);
		$mbgb = ($_POST['mbgb']);
		if ($timelimit == "") {
			$timelimit = "0";
		}
		if ($datalimit == "") {
			$datalimit = "0";
		} else {
			$datalimit = $datalimit * $mbgb;
		}
		if ($adcomment == "") {
			$adcomment = "";
		}
		$getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$profile"));
		$ponlogin = $getprofile[0]['on-login'];
		$getvalid = explode(",", $ponlogin)[3];
		$getprice = explode(",", $ponlogin)[2];
		$getsprice = explode(",", $ponlogin)[4];
		$getlock = explode(",", $ponlogin)[6];
		$_SESSION['ubp'] = $profile;
		$commt = $user . "-" . rand(100, 999) . "-" . date("m.d.y") . "-" . $adcomment;
		$gentemp = $commt . "|~" . $profile . "~" . $getvalid . "~" . $getprice . "!".$getsprice."~" . $timelimit . "~" . $datalimit . "~" . $getlock;
		$gen = '<?php $genu="'.encrypt($gentemp).'";?>';
		$temp = './voucher/temp.php';
		$handle = fopen($temp, 'w') or die('Cannot open file:  ' . $temp);
		$data = $gen;
		fwrite($handle, $data);
		fclose($handle);

		$a = array("1" => "", "", 1, 2, 2, 3, 3, 4, 4, 5, 5, 6);

		$u = array();
		$p = array();

		if ($user == "up") {
			for ($i = 1; $i <= $qty; $i++) {
				if ($char == "hex") {
					$u[$i] = randHex($userl);
				} elseif ($char == "lower") {
					$u[$i] = randLC($userl);
				} elseif ($char == "upper") {
					$u[$i] = randUC($userl);
				} elseif ($char == "upplow") {
					$u[$i] = randULC($userl);
				} elseif ($char == "mix") {
					$u[$i] = randNLC($userl);
				} elseif ($char == "mix1") {
					$u[$i] = randNUC($userl);
				} elseif ($char == "mix2") {
					$u[$i] = randNULC($userl);
				} else {
					$u[$i] = randHex($userl);
				}
				if ($userl <= 8) {
					$p[$i] = randN($userl);
				} else {
					$p[$i] = randN($userl);
				}

				$u[$i] = "$prefix$u[$i]";
			}
			$p_pass = $p;
		}

		if ($user == "vc") {
			$shuf = ($userl - ($a[$userl] ?? 4));
			if ($shuf < 1) $shuf = 4;
			for ($i = 1; $i <= $qty; $i++) {
				if ($userl == 3) {
					$p[$i] = randN(1);
				} elseif ($userl == 4 || $userl == 5) {
					$p[$i] = randN(2);
				} elseif ($userl == 6 || $userl == 7) {
					$p[$i] = randN(3);
				} elseif ($userl >= 8) {
					$p[$i] = randN(4);
				}

				if ($char == "hex") {
					$p[$i] = randHex($userl);
					$u[$i] = "$prefix$p[$i]";
				} elseif ($char == "num") {
					$p[$i] = randN($userl);
					$u[$i] = "$prefix$p[$i]";
				} elseif ($char == "mix") {
					$p[$i] = randNLC($userl);
					$u[$i] = "$prefix$p[$i]";
				} elseif ($char == "mix1") {
					$p[$i] = randNUC($userl);
					$u[$i] = "$prefix$p[$i]";
				} elseif ($char == "mix2") {
					$p[$i] = randNULC($userl);
					$u[$i] = "$prefix$p[$i]";
				} elseif ($char == "lower") {
					$u[$i] = randLC($shuf);
					$u[$i] = "$prefix$u[$i]$p[$i]";
				} elseif ($char == "upper") {
					$u[$i] = randUC($shuf);
					$u[$i] = "$prefix$u[$i]$p[$i]";
				} elseif ($char == "upplow") {
					$u[$i] = randULC($shuf);
					$u[$i] = "$prefix$u[$i]$p[$i]";
				} else {
					$p[$i] = randHex($userl);
					$u[$i] = "$prefix$p[$i]";
				}
			}
			$p_pass = $u;
		}

		// High-Performance Streaming Batch RSC Engine (supports up to 100,000 vouchers)
		$batch_executed = false;
		if ($qty > 5) {
			$tmpFile = tempnam(sys_get_temp_dir(), 'mkrsc');
			$fp = fopen($tmpFile, 'w');
			fwrite($fp, "/ip hotspot user\n");
			for ($i = 1; $i <= $qty; $i++) {
				$escName = addcslashes($u[$i], "\\\"");
				$escPass = addcslashes($p_pass[$i], "\\\"");
				$escProf = addcslashes($profile, "\\\"");
				$escServer = addcslashes($server, "\\\"");
				$escCommt = addcslashes($commt, "\\\"");
				fwrite($fp, "add server=\"$escServer\" name=\"$escName\" password=\"$escPass\" profile=\"$escProf\" limit-uptime=\"$timelimit\" limit-bytes-total=\"$datalimit\" comment=\"$escCommt\"\n");
			}
			fclose($fp);

			// Push to current router via FTP + /import
			$batch_file = "batch_gen_" . time() . "_" . rand(100, 999) . ".rsc";
			$ftp = @ftp_connect($iphost, 21, 5);
			if ($ftp && @ftp_login($ftp, $userhost, decrypt($passwdhost))) {
				@ftp_pasv($ftp, true);
				if (@ftp_put($ftp, $batch_file, $tmpFile, FTP_BINARY)) {
					@ftp_close($ftp);
					$API->comm("/import", array("file-name" => $batch_file));
					$delFile = $API->comm("/file/print", array("?name" => $batch_file));
					if (!empty($delFile)) {
						$API->comm("/file/remove", array(".id" => $delFile[0]['.id']));
					}
					$batch_executed = true;
				} else {
					@ftp_close($ftp);
				}
			}

			// Push to ALL other connected routers in config.php (cross-router voucher sync)
			if (file_exists('./include/config.php')) {
				include_once('./lib/routeros_api.class.php');
				foreach (file('./include/config.php') as $cline) {
					$c_ses = explode("'", $cline)[1];
					if (!empty($c_ses) && $c_ses !== 'mikhmon' && $c_ses !== $session && isset($data[$c_ses])) {
						$otherIp = explode('!', $data[$c_ses][1])[1] ?? '';
						$otherUser = explode('@|@', $data[$c_ses][2])[1] ?? '';
						$otherPass = decrypt(explode('#|#', $data[$c_ses][3])[1] ?? '');
						if (!empty($otherIp) && !empty($otherUser)) {
							$oFtp = @ftp_connect($otherIp, 21, 3);
							if ($oFtp && @ftp_login($oFtp, $otherUser, $otherPass)) {
								@ftp_pasv($oFtp, true);
								$oBatchFile = "batch_sync_" . time() . "_" . rand(100, 999) . ".rsc";
								if (@ftp_put($oFtp, $oBatchFile, $tmpFile, FTP_BINARY)) {
									@ftp_close($oFtp);
									$oApi = new RouterosAPI();
									$oApi->timeout = 5;
									if ($oApi->connect($otherIp, $otherUser, $otherPass)) {
										$oApi->comm("/import", array("file-name" => $oBatchFile));
										$oDel = $oApi->comm("/file/print", array("?name" => $oBatchFile));
										if (!empty($oDel)) {
											$oApi->comm("/file/remove", array(".id" => $oDel[0]['.id']));
										}
										$oApi->disconnect();
									}
								} else {
									@ftp_close($oFtp);
								}
							}
						}
					}
				}
			}

			@unlink($tmpFile);
		}

		// Fallback to standard API if batch was not used or failed
		if (!$batch_executed) {
			for ($i = 1; $i <= $qty; $i++) {
				$API->comm("/ip/hotspot/user/add", array(
					"server" => "$server",
					"name" => "$u[$i]",
					"password" => "$p_pass[$i]",
					"profile" => "$profile",
					"limit-uptime" => "$timelimit",
					"limit-bytes-total" => "$datalimit",
					"comment" => "$commt",
				));
			}
		}

		// Synchronize newly generated vouchers to PostgreSQL FreeRADIUS database
		if (function_exists('pg_connect')) {
			$pg = @pg_connect("host=127.0.0.1 port=5432 dbname=radius user=radius password=RadiusPg2026");
			if ($pg) {
				@pg_query($pg, "BEGIN");
				@pg_prepare($pg, "gen_radcheck", "INSERT INTO radcheck (username, attribute, op, value) VALUES ($1, 'Cleartext-Password', ':=', $2) ON CONFLICT DO NOTHING");
				@pg_prepare($pg, "gen_radgroup", "INSERT INTO radusergroup (username, groupname, priority) VALUES ($1, $2, 1) ON CONFLICT DO NOTHING");
				for ($i = 1; $i <= $qty; $i++) {
					@pg_execute($pg, "gen_radcheck", array($u[$i], $p_pass[$i]));
					@pg_execute($pg, "gen_radgroup", array($u[$i], $profile));
				}
				@pg_query($pg, "COMMIT");
				@pg_close($pg);
			}
		}

		if ($qty < 2) {
			echo "<script>window.location='./?hotspot-user=" . $u[1] . "&session=" . $session . "'</script>";
		} else {
			echo "<script>window.location='./?hotspot-user=generate&session=" . $session . "'</script>";
		}
	}

	$getprofile = $API->comm("/ip/hotspot/user/profile/print");
	include_once('./voucher/temp.php');
	$genuser = explode("-", decrypt($genu));
	$genuser1 = explode("~", decrypt($genu));
	$umode = $genuser[0];
	$ucode = $genuser[1];
	$udate = $genuser[2];
	$uprofile = $genuser1[1];
	$uvalid = $genuser1[2];
	$ucommt = $genuser[3];
	if ($uvalid == "") {
		$uvalid = "-";
	}
	$uprice = explode("!",$genuser1[3])[0];
	if ($uprice == "0") {
		$uprice = "-";
	}
	$suprice = explode("!",$genuser1[3])[1];
	if ($suprice == "0") {
		$suprice = "-";
	}
	$utlimit = $genuser1[4];
	if ($utlimit == "0") {
		$utlimit = "-";
	}
	$udlimit = $genuser1[5];
	if ($udlimit == "0") {
		$udlimit = "-";
	} else {
		$udlimit = formatBytes($udlimit, 2);
	}
	$ulock = $genuser1[6];
	$urlprint = explode("|", decrypt($genu))[0];
	if ($currency == in_array($currency, $cekindo['indo'])) {
		$uprice = $currency . " " . number_format((float)$uprice, 0, ",", ".");
		$suprice = $currency . " " . number_format((float)$suprice, 0, ",", ".");
	} else {
		$uprice = $currency . " " . number_format((float)$uprice);
		$suprice = $currency . " " . number_format((float)$suprice);
	}

}
?>
<div class="row">
	
<div class="col-8">
<div class="card box-bordered">
	<div class="card-header">
	<h3><i class="fa fa-user-plus"></i> <?= $_generate_user ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3> 
	</div>
	<div class="card-body">
<form autocomplete="off" method="post" action="">
	<div>
		<?php if ($_SESSION['ubp'] != "") {
		echo "    <a class='btn bg-warning' href='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
	} elseif ($_SESSION['vcr'] = "active") {
		echo "    <a class='btn bg-warning' href='./?hotspot=users-by-profile&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
	} else {
		echo "    <a class='btn bg-warning' href='./?hotspot=users&profile=all&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
	}

	?>
	<a class="btn bg-pink" title="Open User List by Profile 
<?php if ($_SESSION['ubp'] == "") {
	echo "all";
} else {
	echo $uprofile;
} ?>" href="./?hotspot=users&profile=
<?php if ($_SESSION['ubp'] == "") {
	echo "all";
} else {
	echo $uprofile;
} ?>&session=<?= $session; ?>"> <i class="fa fa-users"></i> <?= $_user_list ?></a>
    <button type="submit" name="save" onclick="loader()" class="btn bg-primary" title="Generate User"> <i class="fa fa-save"></i> <?= $_generate ?></button>
    <a class="btn bg-secondary" title="Print Default" href="./voucher/print.php?id=<?= $urlprint; ?>&qr=no&session=<?= $session; ?>" target="_blank"> <i class="fa fa-print"></i> <?= $_print ?></a>
    <a class="btn bg-danger" title="Print QR" href="./voucher/print.php?id=<?= $urlprint; ?>&qr=yes&session=<?= $session; ?>" target="_blank"> <i class="fa fa-qrcode"></i> <?= $_print_qr ?></a>
    <a class="btn bg-info" title="Print Small" href="./voucher/print.php?id=<?= $urlprint; ?>&small=yes&session=<?= $session; ?>" target="_blank"> <i class="fa fa-print"></i> <?= $_print_small ?></a>
    <a class="btn bg-success" title="Print Kertas F4 (55 Voucher / Lembar)" href="./voucher/print.php?id=<?= $urlprint; ?>&paper=f4&session=<?= $session; ?>" target="_blank"> <i class="fa fa-print"></i> Print F4 (55)</a>
</div>
<table class="table">
  <tr>
    <td class="align-middle"><?= $_qty ?></td>
    <td>
      <div>
        <input class="form-control" type="number" name="qty" id="qtyInput" min="1" max="100000" value="1" placeholder="Maks. 100000" required="1">
        <div style="margin-top: 5px; display: flex; flex-wrap: wrap; gap: 4px;">
          <button type="button" class="btn btn-sm btn-secondary" style="padding: 2px 8px; font-size: 11px;" onclick="$('#qtyInput').val(500)">500</button>
          <button type="button" class="btn btn-sm btn-secondary" style="padding: 2px 8px; font-size: 11px;" onclick="$('#qtyInput').val(1000)">1.000</button>
          <button type="button" class="btn btn-sm btn-secondary" style="padding: 2px 8px; font-size: 11px;" onclick="$('#qtyInput').val(5000)">5.000</button>
          <button type="button" class="btn btn-sm btn-secondary" style="padding: 2px 8px; font-size: 11px;" onclick="$('#qtyInput').val(10000)">10.000</button>
          <button type="button" class="btn btn-sm btn-secondary" style="padding: 2px 8px; font-size: 11px;" onclick="$('#qtyInput').val(50000)">50.000</button>
          <button type="button" class="btn btn-sm bg-primary" style="padding: 2px 8px; font-size: 11px; font-weight: bold;" onclick="$('#qtyInput').val(100000)">100.000</button>
        </div>
      </div>
    </td>
  </tr>
  <tr>
    <td class="align-middle">Server</td>
    <td>
		<select class="form-control " name="server" required="1">
			<option>all</option>
				<?php $TotalReg = count($srvlist);
			for ($i = 0; $i < $TotalReg; $i++) {
				echo "<option>" . $srvlist[$i]['name'] . "</option>";
			}
			?>
		</select>
	</td>
	</tr>
	<tr>
    <td class="align-middle"><?= $_user_mode ?></td><td>
			<select class="form-control " onchange="defUserl();" id="user" name="user" required="1">
				<option value="up"><?= $_user_pass ?></option>
				<option value="vc" selected><?= $_user_user ?></option>
			</select>
		</td>
	</tr>
  <tr>
    <td class="align-middle"><?= $_user_length ?></td><td>
      <select class="form-control " id="userl" name="userl" required="1">
        <option value="6" selected>6</option>
        <option value="3">3</option>
        <option value="4">4</option>
        <option value="5">5</option>
        <option value="6">6</option>
        <option value="7">7</option>
        <option value="8">8</option>
        <option value="9">9</option>
        <option value="10">10</option>
        <option value="12">12</option>
	  </select>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_prefix ?></td><td><input class="form-control " type="text" size="6" maxlength="6" autocomplete="off" name="prefix" value=""></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_character ?></td><td>
      <select class="form-control " name="char" id="char" required="1">
        <option id="hex" style="display:block;" value="hex" selected><?= $_random ?> 4A2B9C (Hexadecimal Kapital & Angka)</option>
        <option id="mix1" style="display:block;" value="mix1"><?= $_random ?> 5AB2C34D (Alfanumerik Kapital & Angka)</option>
        <option id="mix2" style="display:block;" value="mix2"><?= $_random ?> 5aB2c34D (Angka, Huruf & Huruf Kapital)</option>
        <option id="mix" style="display:block;" value="mix"><?= $_random ?> 5ab2c34d (Huruf Kecil & Angka)</option>
        <option id="upper" style="display:block;" value="upper"><?= $_random ?> ABCD</option>
        <option id="lower" style="display:block;" value="lower"><?= $_random ?> abcd</option>
        <option id="upplow" style="display:block;" value="upplow"><?= $_random ?> aBcD</option>
        <option id="lower1" style="display:none;" value="lower"><?= $_random ?> abcd2345</option>
        <option id="upper1" style="display:none;" value="upper"><?= $_random ?> ABCD2345</option>
        <option id="upplow1" style="display:none;" value="upplow"><?= $_random ?> aBcD2345</option>
        <option id="num" style="display:none;" value="num"><?= $_random ?> 1234</option>
	  </select>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_profile ?></td><td>
			<select class="form-control " onchange="GetVP();" id="uprof" name="profile" required="1">
				<?php if ($genprof != "") {
				echo "<option>" . $genprof . "</option>";
			}
			$TotalReg = count($getprofile);
			for ($i = 0; $i < $TotalReg; $i++) {
				echo "<option>" . $getprofile[$i]['name'] . "</option>";
			}
			?>
			</select>
		</td>
	</tr>
	<tr>
    <td class="align-middle"><?= $_time_limit ?></td><td><input class="form-control " type="text" size="4" autocomplete="off" name="timelimit" value=""></td>
  </tr>
	<tr>
    <td class="align-middle"><?= $_data_limit ?></td><td>
      <div class="input-group">
      	<div class="input-group-10 col-box-9">
        	<input class="group-item group-item-l" type="number" min="0" max="9999" name="datalimit" value="<?= $udatalimit; ?>">
    	</div>
          <div class="input-group-2 col-box-3">
              <select style="padding:4.2px;" class="group-item group-item-r" name="mbgb" required="1">
				        <option value=1048576>MB</option>
				        <option value=1073741824>GB</option>
			        </select>
          </div>
      </div>
    </td>
  </tr>
	<tr>
    <td class="align-middle"><?= $_comment ?></td><td><input class="form-control " type="text" title="No special characters" id="comment" autocomplete="off" name="adcomment" value=""></td>
  </tr>
   <tr >
    <td  colspan="4" class="align-middle w-12"  id="GetValidPrice">
    	<?php if ($genprof != "") {
					echo $ValidPrice;
				} ?>
    </td>
  </tr>
</table>
</form>
</div>
</div>
</div>

<div class="col-4">
	<div class="card">
		<div class="card-header">
			<h3><i class="fa fa-ticket"></i> <?= $_last_generate ?></h3>
		</div>
		<div class="card-body">
<table class="table table-bordered">
  <tr>
  	<td><?= $_generate_code ?></td><td><?= $ucode ?></td>
  </tr>
  <tr>
  	<td><?= $_date ?></td><td><?= $udate ?></td>
  </tr>
  <tr>
  	<td><?= $_profile ?></td><td><?= $uprofile ?></td>
  </tr>
  <tr>
  	<td><?= $_validity ?></td><td><?= $uvalid ?></td>
  <tr>
  	<td><?= $_time_limit ?></td><td><?= $utlimit ?></td>
  </tr>
  <tr>
  	<td><?= $_data_limit ?></td><td><?= $udlimit ?></td>
  </tr>
  <tr>
  	<td><?= $_price ?></td><td><?= $uprice ?></td>
  </tr>
  <tr>
  	<td><?= $_selling_price ?></td><td><?= $suprice ?></td>
  </tr>
  <tr>
  	<td><?= $_lock_user ?></td><td><?= $ulock ?></td>
  </tr>
  <tr>
    <td colspan="2">
		<p style="padding:0px 5px;">
      <?= $_format_time_limit ?>
    </p>
    <p style="padding:0px 5px;">
      <?= $_details_add_user ?>
    </p>
    </td>
  </tr>
</table>
</div>
</div>
</div>
<script>
function GetVP(){
  var prof = document.getElementById('uprof').value;
  $("#GetValidPrice").load("./process/getvalidprice.php?name="+prof+"&session=<?= $session; ?> #getdata");
} 
</script>
</div>
