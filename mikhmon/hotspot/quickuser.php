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

// time zone
date_default_timezone_set($_SESSION['timezone']);
	
// load session MikroTik
$session = $_GET['session'];

$quickprint = $_GET['quickprint'];
$qty = 1;
// lang
include('../include/lang.php');
include('../lang/'.$langid.'.php');
// quick bt
include('../include/quickbt.php');
// load config
include('../include/config.php');
include('../include/readcfg.php');

// routeros api
include_once('../lib/routeros_api.class.php');
include_once('../lib/formatbytesbites.php');
$API = new RouterosAPI();
$API->debug = false;
$API->connect($iphost, $userhost, decrypt($passwdhost));
	// get quick print
$getquickprint = $API->comm("/system/script/print", array("?name" => "$quickprint"));

  $quickprintdetails = $getquickprint[0];
  $qpid = $quickprintdetails['.id'];
  $quickprintsource = explode("#",$quickprintdetails['source']);
  $package = $quickprintsource[1];
  $server = $quickprintsource[2];
  $usermode = $quickprintsource[3];
  $userl = $quickprintsource[4];
  $prefix = $quickprintsource[5];
  $char = $quickprintsource[6];
  $profile = $quickprintsource[7];
  $timelimit = $quickprintsource[8];
  $datalimit = $quickprintsource[9];
  $comment = $quickprintsource[10];
  $getvalid = $quickprintsource[11];
  $getprice = explode("_",$quickprintsource[12])[0];
  $getsprice = explode("_",$quickprintsource[12])[1];
  $userlock = $quickprintsource[13];

  if($getsprice == "" && $getprice != ""){
	  $price = $getprice;
  }else if($getsprice != ""){
	  $price = $getsprice;
  }else if ($getsprice == "") {
	$price = "";
  }

		$commt = $usermode . "-" . rand(100, 999) . "-" . date("m.d.y") . "-" . $comment;

		$a = array("1" => "", "", 1, 2, 2, 3, 3, 4);

		if ($usermode == "up") {
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

			for ($i = 1; $i <= $qty; $i++) {
				$API->comm("/ip/hotspot/user/add", array(
					"server" => "$server",
					"name" => "$u[$i]",
					"password" => "$p[$i]",
					"profile" => "$profile",
					"limit-uptime" => "$timelimit",
					"limit-bytes-total" => "$datalimit",
					"comment" => "$commt",
				));
			}
		}

		if ($usermode == "vc") {
			$shuf = ($userl - ($a[$userl] ?? 4));
			if ($shuf < 1) $shuf = 4;
			for ($i = 1; $i <= $qty; $i++) {
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
					$p[$i] = ($userl == 3) ? randN(1) : (($userl <= 5) ? randN(2) : (($userl <= 7) ? randN(3) : randN(4)));
					$u[$i] = "$prefix$u[$i]$p[$i]";
				} elseif ($char == "upper") {
					$u[$i] = randUC($shuf);
					$p[$i] = ($userl == 3) ? randN(1) : (($userl <= 5) ? randN(2) : (($userl <= 7) ? randN(3) : randN(4)));
					$u[$i] = "$prefix$u[$i]$p[$i]";
				} elseif ($char == "upplow") {
					$u[$i] = randULC($shuf);
					$p[$i] = ($userl == 3) ? randN(1) : (($userl <= 5) ? randN(2) : (($userl <= 7) ? randN(3) : randN(4)));
					$u[$i] = "$prefix$u[$i]$p[$i]";
				} else {
					$p[$i] = randHex($userl);
					$u[$i] = "$prefix$p[$i]";
				}
			}
			for ($i = 1; $i <= $qty; $i++) {
				$API->comm("/ip/hotspot/user/add", array(
					"server" => "$server",
					"name" => "$u[$i]",
					"password" => "$u[$i]",
					"profile" => "$profile",
					"limit-uptime" => "$timelimit",
					"limit-bytes-total" => "$datalimit",
					"comment" => "$commt",
				));
			}
		}

        $getuser = $API->comm("/ip/hotspot/user/print", array(
            "?name" => "$u[1]",
          ));
          $userdetails = $getuser[0];
          $uid = $userdetails['.id'];
          $uname = $userdetails['name'];
          $upass = $userdetails['password'];
          $uprofile = $userdetails['profile'];
					$uuptime = formatDTM($userdetails['uptime']);
					$utimelimit = $userdetails['limit-uptime'];
          $udatalimit = $userdetails['limit-bytes-total'];
          $ucomment = $userdetails['comment'];
        
          if (substr(formatBytes2($udatalimit, 2), -2) == "MB") {
            $udatalimit = $udatalimit / 1048576;
            $MG = "MB";
          } elseif (substr(formatBytes2($udatalimit, 2), -2) == "GB") {
            $udatalimit = $udatalimit / 1073741824;
            $MG = "GB";
          } elseif ($udatalimit == "") {
            $udatalimit = "";
            $MG = "MB";
          }
          $_SESSION['sss'] = $uname;
         
// Print BT
  $chl = urlencode("http://$dnsname/login?username=$uname&password=$upass");
	$qrcode = 'https://chart.googleapis.com/chart?cht=qr&chs=100x100&chld=L|0&chl=' . $chl . '&choe=utf-8';
	//$qrcode = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.$chl;

if ($currency == in_array($currency, $cekindo['indo'])) {
  $pricebt = $currency . " " . number_format($price, 0, ",", ".");
  if (substr($getvalid, -1) == "d") {
    $validity = substr($getvalid, 0, -1) . "Hari";
  } else if (substr($getvalid, -1) == "h") {
    $validity = substr($getvalid, 0, -1) . "Jam";
  }
  if (substr($utimelimit, -1) == "d" & strlen($utimelimit) > 3) {
    $timelimit = ((substr($utimelimit, 0, -1) * 7) + substr($utimelimit, 2, 1)) . "Hari";
  } else if (substr($utimelimit, -1) == "d") {
    $timelimit = substr($utimelimit, 0, -1) . "Hari";
  } else if (substr($utimelimit, -1) == "h") {
    $timelimit = substr($utimelimit, 0, -1) . "Jam";
  } else if (substr($utimelimit, -1) == "w") {
    $timelimit = (substr($utimelimit, 0, -1) * 7) . "Hari";
  }

  } else {
    $pricebt = $currency . " " . number_format($price);
    $timelimit = $utimelimit;
    $validity = $getvalid;
  }
	if($qrbt == "enable"){$qr = "yes";}	
	include('../voucher/printbt.php');
?>

<script>
    $(document).ready(function(){
			var w = window.innerWidth;
  			if (w < 800) {
					sendToQuickPrinterChrome();
  			} else if (w > 800) {

					window.open('./voucher/print.php?user=<?= $usermode ?>-<?= $uname ?>&qr=<?= $qr ?>&session=<?= $session ?>','_blank','width=310,height=450').print();
					//window.location.href="./?hotspot-user=<?= $u[1] ?>&session=<?= $session ?>";
  			}
    //sendToQuickPrinterChrome();
});
</script>
<?php } ?>