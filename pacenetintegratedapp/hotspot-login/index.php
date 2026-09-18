<?php
// Hotspot Yunus - Captive Portal Login Page
$mac = isset($_GET['mac']) ? htmlspecialchars($_GET['mac']) : '';
$ip = isset($_GET['ip']) ? htmlspecialchars($_GET['ip']) : '';
$username = isset($_GET['username']) ? htmlspecialchars($_GET['username']) : '';
$link_login_only = isset($_GET['link-login-only']) ? htmlspecialchars($_GET['link-login-only']) : 'http://10.0.0.1/login';
$link_orig = isset($_GET['link-orig']) ? htmlspecialchars($_GET['link-orig']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$chap_id = isset($_GET['chap-id']) ? htmlspecialchars($_GET['chap-id']) : '';
$chap_challenge = isset($_GET['chap-challenge']) ? htmlspecialchars($_GET['chap-challenge']) : '';

// Dynamic Portal Configuration
$portalFile = __DIR__ . '/../data/hotspot_portal.json';
$portalConfig = array(
    'brand_name' => 'Cibi Cibi Hotspot',
    'brand_subtitle' => 'WiFi Cepat, Stabil & Terjangkau',
    'whatsapp_number' => '+62 813-4401-0045',
    'whatsapp_link' => 'https://wa.me/6281344010045?text=Halo%20Admin%20Cibi%20Cibi%20Hotspot,%20saya%20mau%20beli%20voucher',
    'prices' => array(
        array('name' => '12 Jam', 'cost' => 'Rp 4.000'),
        array('name' => '1 Minggu', 'cost' => 'Rp 40.000'),
        array('name' => '1 Bulan', 'cost' => 'Rp 100.000'),
        array('name' => 'Reseller', 'cost' => 'Paket Khusus')
    )
);

if (file_exists($portalFile)) {
    $custom = json_decode(@file_get_contents($portalFile), true);
    if (is_array($custom)) {
        $portalConfig = array_merge($portalConfig, $custom);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($portalConfig['brand_name']); ?> - Login Portal</title>
    <link rel="icon" href="/app/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/css/font-awesome/css/font-awesome.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 10px;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .card {
            margin: auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 380px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .header {
            text-align: center;
            padding: 25px 20px 15px;
            background: #ffffff;
            border-bottom: 2px solid #f0f2f5;
        }
        .logo-img {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            object-fit: contain;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            margin-bottom: 8px;
        }
        .title {
            font-size: 20px;
            font-weight: 800;
            color: #1a2a6c;
            letter-spacing: 0.5px;
        }
        .subtitle {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }
        .contact-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            margin-top: 8px;
            text-decoration: none;
        }
        .contact-chip:hover {
            background: #c8e6c9;
        }
        .tabs {
            display: flex;
            background: #f0f2f5;
            padding: 4px;
            margin: 15px 20px 0;
            border-radius: 10px;
        }
        .tab-btn {
            flex: 1;
            padding: 8px;
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 700;
            color: #666;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: #ffffff;
            color: #1a2a6c;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .form-container {
            padding: 20px;
        }
        .input-group {
            margin-bottom: 14px;
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px 12px 38px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
            text-transform: uppercase;
        }
        .form-control:focus {
            border-color: #1a2a6c;
        }
        .btn-login {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #1a2a6c, #2b5876);
            color: white;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(26, 42, 108, 0.3);
            transition: transform 0.1s, box-shadow 0.2s;
        }
        .btn-login:active {
            transform: scale(0.98);
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #ffcdd2;
        }
        .pricing-section {
            background: #fafafa;
            border-top: 1px solid #eee;
            padding: 15px 20px;
        }
        .pricing-title {
            font-size: 12px;
            font-weight: 800;
            color: #444;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            text-align: center;
        }
        .pricing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .price-item {
            background: #fff;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            text-align: center;
        }
        .price-name {
            font-size: 12px;
            font-weight: 700;
            color: #333;
        }
        .price-cost {
            font-size: 13px;
            font-weight: 800;
            color: #e65100;
            margin-top: 2px;
        }
        .footer {
            text-align: center;
            padding: 12px 20px;
            font-size: 11px;
            color: #888;
            background: #f5f5f5;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <div style="width: 70px; height: 70px; margin: 0 auto 8px; border-radius: 50%; background: linear-gradient(135deg, #1a2a6c, #00d2d3); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,210,211,0.3);">
            <i class="fa fa-wifi" style="font-size: 32px; color: #ffffff;"></i>
        </div>
        <h1 class="title"><?= htmlspecialchars($portalConfig['brand_name']); ?></h1>
        <p class="subtitle"><?= htmlspecialchars($portalConfig['brand_subtitle']); ?></p>
        <?php if (!empty($portalConfig['whatsapp_number'])): ?>
            <a href="<?= htmlspecialchars($portalConfig['whatsapp_link'] ?? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $portalConfig['whatsapp_number'])); ?>" class="contact-chip" target="_blank">
                <i class="fa fa-whatsapp"></i> <?= htmlspecialchars($portalConfig['whatsapp_number']); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="tabs">
        <button class="tab-btn active" id="tab-voucher" onclick="switchTab('voucher')">
            <i class="fa fa-ticket"></i> Voucher
        </button>
        <button class="tab-btn" id="tab-member" onclick="switchTab('member')">
            <i class="fa fa-user"></i> Member
        </button>
    </div>

    <div class="form-container">
        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <i class="fa fa-exclamation-circle"></i>
                <span><?= $error; ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Login ke MikroTik -->
        <form name="sendin" action="<?= $link_login_only; ?>" method="post" onsubmit="return prepareLogin(this);">
            <input type="hidden" name="dst" value="<?= $link_orig; ?>">
            <input type="hidden" name="popup" value="true">

            <div class="input-group">
                <i class="fa fa-key"></i>
                <input type="text" name="username" id="input-user" class="form-control" placeholder="KODE VOUCHER" required autofocus autocomplete="off">
            </div>

            <div class="input-group" id="group-password" style="display: none;">
                <i class="fa fa-lock"></i>
                <input type="password" name="password" id="input-pass" class="form-control" placeholder="PASSWORD" autocomplete="off">
            </div>

            <button type="submit" class="btn-login" id="btn-submit">
                <i class="fa fa-sign-in"></i> MASUK
            </button>
        </form>
    </div>

    <?php if (!empty($portalConfig['prices']) && is_array($portalConfig['prices'])): ?>
    <div class="pricing-section">
        <div class="pricing-title"><i class="fa fa-tag"></i> Daftar Tarif Voucher</div>
        <div class="pricing-grid">
            <?php foreach ($portalConfig['prices'] as $p): ?>
                <div class="price-item">
                    <div class="price-name"><?= htmlspecialchars($p['name'] ?? ''); ?></div>
                    <div class="price-cost"><?= htmlspecialchars($p['cost'] ?? ''); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer">
        Hubungi Admin untuk pembelian voucher fisik / digital.<br>
        &copy; <?= date('Y'); ?> <?= htmlspecialchars($portalConfig['brand_name']); ?> - Powered by PACENET PRO
    </div>
</div>

<script>
let currentMode = 'voucher';

function switchTab(mode) {
    currentMode = mode;
    const tabVoucher = document.getElementById('tab-voucher');
    const tabMember = document.getElementById('tab-member');
    const groupPass = document.getElementById('group-password');
    const inputUser = document.getElementById('input-user');
    const inputPass = document.getElementById('input-pass');

    if (mode === 'voucher') {
        tabVoucher.classList.add('active');
        tabMember.classList.remove('active');
        groupPass.style.display = 'none';
        inputUser.placeholder = 'KODE VOUCHER';
        inputPass.required = false;
        inputPass.value = inputUser.value.trim().toUpperCase();
    } else {
        tabMember.classList.add('active');
        tabVoucher.classList.remove('active');
        groupPass.style.display = 'block';
        inputUser.placeholder = 'USERNAME';
        inputPass.required = true;
        inputPass.value = '';
    }
}

function syncVoucher() {
    const inputUser = document.getElementById('input-user');
    const inputPass = document.getElementById('input-pass');
    if (currentMode === 'voucher') {
        const val = inputUser.value.trim().toUpperCase();
        inputPass.value = val;
    }
}

const inputUser = document.getElementById('input-user');
inputUser.addEventListener('input', function() {
    if (currentMode === 'voucher') {
        this.value = this.value.toUpperCase();
        document.getElementById('input-pass').value = this.value;
    }
});
inputUser.addEventListener('paste', function() {
    setTimeout(syncVoucher, 20);
});
inputUser.addEventListener('change', syncVoucher);

function prepareLogin(form) {
    const rawUser = form.username.value || '';
    const cleanUser = rawUser.trim();
    if (!cleanUser) {
        alert('Silakan masukkan kode voucher Anda.');
        form.username.focus();
        return false;
    }
    if (currentMode === 'voucher') {
        const upper = cleanUser.toUpperCase();
        form.username.value = upper;
        form.password.value = upper;
    } else {
        form.username.value = cleanUser;
        if (!form.password.value) {
            alert('Silakan masukkan password member Anda.');
            form.password.focus();
            return false;
        }
    }
    const btn = document.getElementById('btn-submit');
    if (btn) {
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> MEMPROSES...';
        btn.disabled = true;
    }
    return true;
}
</script>

</body>
</html>
