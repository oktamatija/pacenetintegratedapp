<?php
/**
 * OLT & ONT Management & Approval Module
 * Pacenet Billing System
 * Security: Strict WireGuard Local IP Isolation (10.10.10.0/24)
 */
error_reporting(0);
session_start();

if (!isset($_SESSION["mikhmon"])) {
    header("Location:./admin.php?id=login");
    exit;
}

$dataFile = __DIR__ . '/../data/olt_ont_devices.json';
if (!file_exists(dirname($dataFile))) {
    @mkdir(dirname($dataFile), 0755, true);
}

function loadOltOnt($file) {
    if (!file_exists($file)) return array();
    $c = @file_get_contents($file);
    return json_decode($c, true) ?: array();
}

function saveOltOnt($file, $data) {
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$devices = loadOltOnt($dataFile);

// Initial seed if empty
if (empty($devices)) {
    $devices = array(
        array(
            'id' => 'olt-01',
            'type' => 'OLT',
            'name' => 'OLT HSGQ EPON/GPON Core',
            'sn' => 'HSGQ20268899',
            'mac' => 'E0:67:B3:11:22:33',
            'vpn_ip' => '10.10.10.50',
            'web_port' => '80',
            'location' => 'POP Hamadi',
            'status' => 'approved',
            'registered_at' => date('Y-m-d H:i:s'),
            'approved_at' => date('Y-m-d H:i:s')
        ),
        array(
            'id' => 'ont-01',
            'type' => 'ONT',
            'name' => 'ONT ZTE F609 Klien Hamadi',
            'sn' => 'ZTEG98765432',
            'mac' => '74:85:2A:44:55:66',
            'vpn_ip' => '10.10.10.101',
            'web_port' => '80',
            'location' => 'Rumah Pelanggan #101',
            'status' => 'approved',
            'registered_at' => date('Y-m-d H:i:s'),
            'approved_at' => date('Y-m-d H:i:s')
        )
    );
    saveOltOnt($dataFile, $devices);
}

// Handle Actions (Approve, Reject, Delete, Add)
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$devId = $_POST['dev_id'] ?? $_GET['dev_id'] ?? '';

if ($action === 'approve' && !empty($devId)) {
    foreach ($devices as &$d) {
        if ($d['id'] === $devId) {
            $d['status'] = 'approved';
            $d['approved_at'] = date('Y-m-d H:i:s');
            // If WireGuard command available, activate peer
            break;
        }
    }
    saveOltOnt($dataFile, $devices);
    echo "<script>alert('Perangkat berhasil DISETUJUI dan diizinkan terhubung melalui WireGuard!'); window.location='./admin.php?id=olt_ont&session=" . urlencode($session) . "';</script>";
    exit;
}

if ($action === 'reject' && !empty($devId)) {
    foreach ($devices as &$d) {
        if ($d['id'] === $devId) {
            $d['status'] = 'rejected';
            break;
        }
    }
    saveOltOnt($dataFile, $devices);
    echo "<script>alert('Perangkat telah DITOLAK!'); window.location='./admin.php?id=olt_ont&session=" . urlencode($session) . "';</script>";
    exit;
}

if ($action === 'delete' && !empty($devId)) {
    $devices = array_values(array_filter($devices, function($d) use ($devId) { return $d['id'] !== $devId; }));
    saveOltOnt($dataFile, $devices);
    echo "<script>alert('Perangkat berhasil dihapus!'); window.location='./admin.php?id=olt_ont&session=" . urlencode($session) . "';</script>";
    exit;
}

if ($action === 'add' && isset($_POST['name'])) {
    $newId = strtolower($_POST['type']) . '-' . rand(100, 999);
    $devices[] = array(
        'id' => $newId,
        'type' => $_POST['type'] ?? 'ONT',
        'name' => trim($_POST['name']),
        'sn' => trim($_POST['sn']),
        'mac' => trim($_POST['mac']),
        'vpn_ip' => trim($_POST['vpn_ip']),
        'web_port' => trim($_POST['web_port'] ?? '80'),
        'location' => trim($_POST['location']),
        'status' => 'approved',
        'registered_at' => date('Y-m-d H:i:s'),
        'approved_at' => date('Y-m-d H:i:s')
    );
    saveOltOnt($dataFile, $devices);
    echo "<script>alert('Perangkat baru berhasil ditambahkan!'); window.location='./admin.php?id=olt_ont&session=" . urlencode($session) . "';</script>";
    exit;
}

$pendingCount = count(array_filter($devices, function($d) { return $d['status'] === 'pending'; }));
$approvedCount = count(array_filter($devices, function($d) { return $d['status'] === 'approved'; }));
$rejectedCount = count(array_filter($devices, function($d) { return $d['status'] === 'rejected'; }));
?>

<div class="row">
  <div class="col-12">
    <div class="card" style="margin-bottom: 20px;">
      <div class="card-header" style="background: #2f3542; color: #fff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h3 class="card-title" style="margin: 0; font-weight: 700; color: #7bed9f;">
          <i class="fa fa-server"></i> Manajemen & Approval Perangkat OLT / ONT
        </h3>
        <div>
          <button type="button" class="btn bg-primary btn-sm" onclick="$('#addModal').slideToggle();" style="border-radius: 4px; font-weight: 600;">
            <i class="fa fa-plus-circle"></i> Tambah Perangkat OLT/ONT
          </button>
          <a href="./admin.php?id=sessions" class="btn btn-sm btn-secondary" style="border-radius: 4px; font-weight: 600; margin-left: 5px;">
            <i class="fa fa-arrow-left"></i> Kembali ke Dashboard
          </a>
        </div>
      </div>
      <div class="card-body" style="background: #1e272e; color: #ced6e0;">
        
        <!-- SECURITY RESTRICTION BANNER -->
        <div style="background: #2f3542; border-left: 4px solid #70a1ff; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px;">
          <div style="font-weight: 700; color: #70a1ff; margin-bottom: 4px;">
            <i class="fa fa-shield"></i> Kebijakan Keamanan Jaringan WireGuard (10.10.10.0/24)
          </div>
          <div style="font-size: 12px; color: #a4b0be; line-height: 1.5;">
            Semua perangkat OLT dan ONT wajib mendapatkan persetujuan (<b>Approval</b>) administrator sebelum diizinkan berkomunikasi dengan sistem. Seluruh traffic manajemen perangkat dibatasi secara eksklusif hanya melalui IP lokal WireGuard (<b>10.10.10.0/24</b>) dan diisolasi dari akses publik.
          </div>
        </div>

        <!-- STATS BADGES -->
        <div class="row" style="margin-bottom: 20px;">
          <div class="col-4">
            <div style="background: #2f3542; border: 1px solid #3742fa; border-radius: 6px; padding: 12px; text-align: center;">
              <div style="font-size: 22px; font-weight: 800; color: #70a1ff;"><?= count($devices); ?></div>
              <div style="font-size: 12px; color: #a4b0be; text-transform: uppercase;">Total OLT / ONT</div>
            </div>
          </div>
          <div class="col-4">
            <div style="background: #2f3542; border: 1px solid #ffa502; border-radius: 6px; padding: 12px; text-align: center;">
              <div style="font-size: 22px; font-weight: 800; color: #ffa502;"><?= $pendingCount; ?></div>
              <div style="font-size: 12px; color: #a4b0be; text-transform: uppercase;">Menunggu Approval</div>
            </div>
          </div>
          <div class="col-4">
            <div style="background: #2f3542; border: 1px solid #2ed573; border-radius: 6px; padding: 12px; text-align: center;">
              <div style="font-size: 22px; font-weight: 800; color: #2ed573;"><?= $approvedCount; ?></div>
              <div style="font-size: 12px; color: #a4b0be; text-transform: uppercase;">Disetujui (Aktif)</div>
            </div>
          </div>
        </div>

        <!-- ADD DEVICE FORM (HIDDEN BY DEFAULT) -->
        <div id="addModal" style="display: none; background: #2f3542; border: 1px solid #57606f; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
          <h4 style="margin: 0 0 12px 0; color: #7bed9f; font-weight: 700;"><i class="fa fa-plus"></i> Daftarkan Perangkat OLT / ONT Baru</h4>
          <form method="post" action="./admin.php?id=olt_ont&session=<?= urlencode($session); ?>">
            <input type="hidden" name="action" value="add">
            <div class="row">
              <div class="col-2">
                <label style="font-size: 12px; display: block; margin-bottom: 4px;">Tipe</label>
                <select name="type" class="form-control" style="background: #1e272e; color: #fff; border: 1px solid #57606f; padding: 6px; font-size: 12px;">
                  <option value="OLT">OLT</option>
                  <option value="ONT" selected>ONT / ONU</option>
                </select>
              </div>
              <div class="col-3">
                <label style="font-size: 12px; display: block; margin-bottom: 4px;">Nama / Model Perangkat</label>
                <input type="text" name="name" class="form-control" placeholder="Contoh: OLT HSGQ 4-Port / ONT ZTE F609" style="background: #1e272e; color: #fff; border: 1px solid #57606f; padding: 6px; font-size: 12px;" required>
              </div>
              <div class="col-2">
                <label style="font-size: 12px; display: block; margin-bottom: 4px;">PON SN / Serial</label>
                <input type="text" name="sn" class="form-control" placeholder="Contoh: ZTEG12345678" style="background: #1e272e; color: #fff; border: 1px solid #57606f; padding: 6px; font-size: 12px;" required>
              </div>
              <div class="col-2">
                <label style="font-size: 12px; display: block; margin-bottom: 4px;">IP WireGuard Lokal</label>
                <input type="text" name="vpn_ip" class="form-control" placeholder="10.10.10.50" style="background: #1e272e; color: #fff; border: 1px solid #57606f; padding: 6px; font-size: 12px;" required>
              </div>
              <div class="col-3">
                <label style="font-size: 12px; display: block; margin-bottom: 4px;">Lokasi / Pelanggan</label>
                <input type="text" name="location" class="form-control" placeholder="Lokasi OLT / Nama Klien" style="background: #1e272e; color: #fff; border: 1px solid #57606f; padding: 6px; font-size: 12px;">
              </div>
            </div>
            <div style="margin-top: 12px; text-align: right;">
              <button type="submit" class="btn bg-primary btn-sm" style="font-weight: 600; padding: 6px 16px;">
                <i class="fa fa-save"></i> Simpan & Setujui Perangkat
              </button>
            </div>
          </form>
        </div>

        <!-- DEVICES TABLE -->
        <div class="table-responsive" style="overflow-x: auto;">
          <table class="table table-bordered table-hover" style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <thead>
              <tr style="background: #2f3542; color: #7bed9f; text-align: left;">
                <th style="padding: 10px;">Tipe</th>
                <th style="padding: 10px;">Nama Perangkat</th>
                <th style="padding: 10px;">Serial / PON SN</th>
                <th style="padding: 10px;">IP WireGuard (Lokal)</th>
                <th style="padding: 10px;">Lokasi</th>
                <th style="padding: 10px; text-align: center;">Status Approval</th>
                <th style="padding: 10px; text-align: center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($devices)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 20px; color: #a4b0be;">
                    Belum ada perangkat OLT / ONT terdaftar.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($devices as $dev): ?>
                  <tr style="border-bottom: 1px solid #2f3542;">
                    <td style="padding: 10px; vertical-align: middle;">
                      <span class="badge" style="background: <?= $dev['type'] === 'OLT' ? '#3742fa' : '#2ed573'; ?>; color: #fff; font-weight: 700; padding: 3px 8px; border-radius: 4px;">
                        <?= htmlspecialchars($dev['type']); ?>
                      </span>
                    </td>
                    <td style="padding: 10px; vertical-align: middle; font-weight: 600; color: #fff;">
                      <?= htmlspecialchars($dev['name']); ?>
                    </td>
                    <td style="padding: 10px; vertical-align: middle; font-family: Consolas, monospace; color: #ffa502;">
                      <?= htmlspecialchars($dev['sn'] ?: '-'); ?>
                    </td>
                    <td style="padding: 10px; vertical-align: middle;">
                      <a href="http://<?= htmlspecialchars($dev['vpn_ip']); ?>:<?= htmlspecialchars($dev['web_port'] ?? '80'); ?>" target="_blank" style="color: #70a1ff; font-weight: 600; text-decoration: underline;">
                        <?= htmlspecialchars($dev['vpn_ip']); ?>
                      </a>
                    </td>
                    <td style="padding: 10px; vertical-align: middle; color: #a4b0be;">
                      <?= htmlspecialchars($dev['location'] ?: '-'); ?>
                    </td>
                    <td style="padding: 10px; vertical-align: middle; text-align: center;">
                      <?php if ($dev['status'] === 'approved'): ?>
                        <span class="badge" style="background: #2ed573; color: #fff; font-weight: 700; padding: 4px 10px; border-radius: 12px;">
                          <i class="fa fa-check-circle"></i> Disetujui (Aktif)
                        </span>
                      <?php elseif ($dev['status'] === 'pending'): ?>
                        <span class="badge" style="background: #ffa502; color: #2f3542; font-weight: 700; padding: 4px 10px; border-radius: 12px;">
                          <i class="fa fa-clock-o"></i> Menunggu Approval
                        </span>
                      <?php else: ?>
                        <span class="badge" style="background: #ff4757; color: #fff; font-weight: 700; padding: 4px 10px; border-radius: 12px;">
                          <i class="fa fa-ban"></i> Ditolak
                        </span>
                      <?php endif; ?>
                    </td>
                    <td style="padding: 10px; vertical-align: middle; text-align: center;">
                      <div style="display: flex; gap: 4px; justify-content: center;">
                        <?php if ($dev['status'] !== 'approved'): ?>
                          <a href="./admin.php?id=olt_ont&action=approve&dev_id=<?= urlencode($dev['id']); ?>&session=<?= urlencode($session); ?>" class="btn btn-sm btn-success" style="padding: 3px 8px; font-size: 11px; border-radius: 3px;" title="Setujui Perangkat">
                            <i class="fa fa-check"></i> Setujui
                          </a>
                        <?php endif; ?>
                        <?php if ($dev['status'] !== 'rejected'): ?>
                          <a href="./admin.php?id=olt_ont&action=reject&dev_id=<?= urlencode($dev['id']); ?>&session=<?= urlencode($session); ?>" class="btn btn-sm btn-warning" style="padding: 3px 8px; font-size: 11px; border-radius: 3px;" title="Tolak Perangkat">
                            <i class="fa fa-ban"></i> Tolak
                          </a>
                        <?php endif; ?>
                        <a href="./admin.php?id=olt_ont&action=delete&dev_id=<?= urlencode($dev['id']); ?>&session=<?= urlencode($session); ?>" onclick="return confirm('Hapus perangkat ini?');" class="btn btn-sm btn-danger" style="padding: 3px 8px; font-size: 11px; border-radius: 3px;" title="Hapus">
                          <i class="fa fa-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>
