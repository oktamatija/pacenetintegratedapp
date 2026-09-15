<style>
	.qrcode{
		height:80px;
		width:80px;
	}
</style>

<table class="voucher" style=" width: 220px;">
  <tbody>
<!-- Logo Hotspotname & Phone -->
    <tr>
      <td style="text-align: left; font-size: 14px; font-weight:bold; border-bottom: 1px black solid; padding: 3px 2px;">
        <table style="width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;">
          <tr>
            <td style="width: 35px; vertical-align: middle; text-align: left; border: none; padding: 0;">
              <img src="<?= $logo; ?>" alt="logo" style="height:32px; border:0; display:block;">
            </td>
            <td style="vertical-align: middle; text-align: left; border: none; padding-left: 5px;">
              <div style="font-size: 13px; font-weight: bold; line-height: 1.2;"><?= $hotspotname; ?> <span id="num" style="font-size:10px; font-weight:normal;"><?= " [$num]"; ?></span></div>
              <div style="font-size: 10px; font-weight: bold; color: #111; line-height: 1.2; margin-top: 1px;">+62 813-4401-0045</div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
<!-- /  -->
    <tr>
      <td>
    <table style=" text-align: center; width: 210px; font-size: 12px;">
  <tbody>
<!-- Username Password QR    -->
    <tr>
      <td>
        <table style="width:100%;">
<!-- Username = Password    -->
<?php if ($usermode == "vc") { ?>
        <tr>
          <td font-size: 12px;>Kode Voucher</td>
        </tr>
        <tr>
          <td style="width:100%; border: 1px solid black; font-weight:bold; font-size:16px;"><?= $username; ?></td>
        </tr>
<!-- /  -->
<!-- Username & Password  -->
<?php 
} elseif ($usermode == "up") { ?>
<!-- Check QR  -->
<?php if ($qr == "yes") { ?>
        <tr>
          <td>Username</td>
        </tr>
        <tr>
          <td style="border: 1px solid black; font-weight:bold;"><?= $username; ?></td>
        </tr>
        <tr>
          <td>Password</td>
        </tr>
        <tr>
          <td style="border: 1px solid black; font-weight:bold;"><?= $password; ?></td>
        </tr>
<?php 
} else { ?>
        <tr>
          <td style="width: 50%">Username</td>
          <td >Password</td>
        </tr>
        <tr style="font-size: 14px;">
          <td style="border: 1px solid black; font-weight:bold;"><?= $username; ?></td>
          <td style="border: 1px solid black; font-weight:bold;"><?= $password; ?></td>
        </tr>
<?php 
}
} ?>
<!-- /  -->
        </table>
      </td>
<!-- QR Code    -->
<?php if ($qr == "yes") { ?>
      <td>
	<?= $qrcode ?>
      </td>
<?php 
} ?>
<!-- /  -->
    <tr>
      <!-- Price  -->
      <td colspan="2" style="border-top: 1px solid black;font-weight:bold; font-size:16px"><?= $validity; ?> <?= $timelimit; ?> <?= $datalimit; ?> <?= $price; ?></td>
<!-- /  -->
    </tr>
    <tr>
      <!-- Note  -->
      <td colspan="2" style="font-weight:bold; font-size:12px">Login: http://<?= $dnsname; ?></td>
<!-- /  -->
    </tr>
<!-- /  -->
  </tbody>
    </table>
      </td>
    </tr>
  </tbody>
</table>
