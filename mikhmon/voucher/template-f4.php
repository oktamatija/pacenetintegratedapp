<div class="v-f4">
  <div class="v-f4-header">
    <div style="width: 100%;">
      <div class="v-f4-title"><?= $hotspotname; ?> <span class="v-f4-num"><?= "#" . $num; ?></span></div>
      <div class="v-f4-phone">+62 813-4401-0045</div>
    </div>
  </div>
  <div class="v-f4-body">
    <?php if ($qr == "yes") { ?>
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 1mm; width: 100%;">
        <div style="flex: 1; min-width: 0;">
          <?php if ($usermode == "vc") { ?>
            <div class="v-f4-label">KODE VOUCHER</div>
            <div class="v-f4-code v-f4-code-qr"><?= $username; ?></div>
          <?php } else { ?>
            <div class="v-f4-label" style="font-size: 6.5pt;">User: <b style="font-size: 8pt;"><?= $username; ?></b></div>
            <div class="v-f4-label" style="font-size: 6.5pt;">Pass: <b style="font-size: 8pt;"><?= $password; ?></b></div>
          <?php } ?>
        </div>
        <div style="width: 18mm; height: 18mm; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
          <?= $qrcode; ?>
        </div>
      </div>
    <?php } else { ?>
      <?php if ($usermode == "vc") { ?>
        <div class="v-f4-label">KODE VOUCHER</div>
        <div class="v-f4-code"><?= $username; ?></div>
      <?php } else { ?>
        <div class="v-f4-up">
          <div class="v-f4-up-box">
            <div class="v-f4-label">Username</div>
            <div class="v-f4-val"><?= $username; ?></div>
          </div>
          <div class="v-f4-up-box">
            <div class="v-f4-label">Password</div>
            <div class="v-f4-val"><?= $password; ?></div>
          </div>
        </div>
      <?php } ?>
    <?php } ?>
  </div>
  <div class="v-f4-footer">
    <div class="v-f4-meta">
      <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%;"><?= trim("$validity $timelimit $datalimit"); ?></span>
      <span class="v-f4-price"><?= $price; ?></span>
    </div>
    <div class="v-f4-dns">Login: <?= $dnsname; ?></div>
  </div>
</div>
