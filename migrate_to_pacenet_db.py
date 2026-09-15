import paramiko
from deploy_config import VPS_HOST, VPS_PORT, VPS_USER, VPS_PASS

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(VPS_HOST, port=VPS_PORT, username=VPS_USER, password=VPS_PASS, timeout=10)

php_migration = """<?php
require_once('/var/www/pacenetintegratedapp/include/config.php');
require_once('/var/www/pacenetintegratedapp/lib/routeros_api.class.php');
require_once('/var/www/pacenetintegratedapp/api/common.php');

$pg = pg_connect("host=127.0.0.1 port=5432 dbname=radius user=radius password=RadiusPg2026");
if (!$pg) {
    die("Error connecting to PostgreSQL radius database\\n");
}

echo "1. Creating pacenet_vouchers table...\\n";
$schemaSql = "
CREATE TABLE IF NOT EXISTS pacenet_vouchers (
    id SERIAL PRIMARY KEY,
    username VARCHAR(64) UNIQUE NOT NULL,
    password VARCHAR(64) NOT NULL,
    profile VARCHAR(64) DEFAULT '12-jam',
    price NUMERIC DEFAULT 0,
    validity VARCHAR(32) DEFAULT '12h',
    comment VARCHAR(128) DEFAULT '',
    status VARCHAR(32) DEFAULT 'unused',
    uptime VARCHAR(32) DEFAULT '0s',
    bytes_in BIGINT DEFAULT 0,
    bytes_out BIGINT DEFAULT 0,
    bytes_total BIGINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    first_login TIMESTAMP NULL,
    last_seen TIMESTAMP NULL,
    kiosk_name VARCHAR(64) NULL,
    router_origin VARCHAR(64) DEFAULT 'Pacenet Cloud'
);
CREATE INDEX IF NOT EXISTS idx_pacenet_vouchers_status ON pacenet_vouchers(status);
CREATE INDEX IF NOT EXISTS idx_pacenet_vouchers_profile ON pacenet_vouchers(profile);
CREATE INDEX IF NOT EXISTS idx_pacenet_vouchers_comment ON pacenet_vouchers(comment);
";
pg_query($pg, $schemaSql) or die("Schema error: " . pg_last_error($pg));

echo "2. Fetching existing radcheck and radusergroup data...\\n";
$radCheckRes = pg_query($pg, "SELECT r.username, r.value as password, COALESCE(g.groupname, '12-jam') as profile FROM radcheck r LEFT JOIN radusergroup g ON r.username = g.username");
$existingInPg = array();
while ($row = pg_fetch_assoc($radCheckRes)) {
    $existingInPg[$row['username']] = array(
        'password' => $row['password'],
        'profile' => $row['profile']
    );
}
echo "Total users currently in radcheck: " . count($existingInPg) . "\\n";

echo "3. Collecting user data and active sessions from MikroTik routers...\\n";
$targets = array('Rumah-DOLPHIN', 'Dolphin-Hamadi');
$allMikrotikUsers = array();
$allActiveUsers = array();

foreach ($targets as $s) {
    $conn = connectMikrotik($s, 5);
    if (!$conn) continue;
    $api = $conn['api'];

    $act = $api->comm('/ip/hotspot/active/print');
    if (is_array($act)) {
        foreach ($act as $a) {
            $u = $a['user'] ?? '';
            if (!empty($u)) $allActiveUsers[$u] = true;
        }
    }

    $users = $api->comm('/ip/hotspot/user/print');
    if (is_array($users)) {
        foreach ($users as $u) {
            $name = $u['name'] ?? '';
            if (empty($name) || $name === 'default-encryption' || $name === 'default-trial') continue;
            if (!isset($allMikrotikUsers[$name])) {
                $allMikrotikUsers[$name] = array(
                    'username' => $name,
                    'password' => $u['password'] ?? $name,
                    'profile' => $u['profile'] ?? '12-jam',
                    'uptime' => $u['uptime'] ?? '0s',
                    'bytes_in' => intval($u['bytes-in'] ?? 0),
                    'bytes_out' => intval($u['bytes-out'] ?? 0),
                    'comment' => $u['comment'] ?? '',
                    'router' => $s
                );
            }
        }
    }
    $api->disconnect();
}
echo "Collected " . count($allMikrotikUsers) . " local users and " . count($allActiveUsers) . " active users from MikroTik.\\n";

echo "4. Migrating & inserting into pacenet_vouchers and radcheck...\\n";
pg_query($pg, "BEGIN");

$insVoucherStmt = pg_prepare($pg, "ins_voucher", "
    INSERT INTO pacenet_vouchers (username, password, profile, price, validity, comment, status, uptime, bytes_in, bytes_out, bytes_total, router_origin)
    VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
    ON CONFLICT (username) DO UPDATE SET
        uptime = EXCLUDED.uptime,
        bytes_in = EXCLUDED.bytes_in,
        bytes_out = EXCLUDED.bytes_out,
        bytes_total = EXCLUDED.bytes_total,
        status = CASE WHEN pacenet_vouchers.status = 'unused' AND EXCLUDED.status != 'unused' THEN EXCLUDED.status ELSE pacenet_vouchers.status END
");

$insRadcheckStmt = pg_prepare($pg, "ins_radcheck", "
    INSERT INTO radcheck (username, attribute, op, value)
    VALUES ($1, 'Cleartext-Password', ':=', $2)
    ON CONFLICT DO NOTHING
");

$insRadgroupStmt = pg_prepare($pg, "ins_radgroup", "
    INSERT INTO radusergroup (username, groupname, priority)
    VALUES ($1, $2, 1)
    ON CONFLICT DO NOTHING
");

// Insert from MikroTik users first
$migratedCount = 0;
foreach ($allMikrotikUsers as $name => $u) {
    $uptime = $u['uptime'];
    $bIn = $u['bytes_in'];
    $bOut = $u['bytes_out'];
    $bTotal = $bIn + $bOut;
    $comment = $u['comment'];
    $prof = $u['profile'] ?: '12-jam';
    $price = ($prof === '12-jam') ? 4000 : (($prof === '1minggu-40rb') ? 40000 : (($prof === '1bulan-100rb') ? 100000 : 0));

    // Determine status
    $status = 'unused';
    if (isset($allActiveUsers[$name])) {
        $status = 'active';
    } elseif ($uptime !== '0s' && !empty($uptime)) {
        $expTs = parseHotspotExpirationTimestamp($comment);
        if ($expTs !== false && time() >= $expTs) {
            $status = 'expired';
        } else {
            $status = 'active';
        }
    }

    pg_execute($pg, "ins_voucher", array(
        $name,
        $u['password'],
        $prof,
        $price,
        '12h',
        $comment,
        $status,
        $uptime,
        $bIn,
        $bOut,
        $bTotal,
        $u['router']
    ));

    // Ensure present in radcheck and radusergroup
    pg_execute($pg, "ins_radcheck", array($name, $u['password']));
    pg_execute($pg, "ins_radgroup", array($name, $prof));
    $migratedCount++;
}

// Now insert remaining from radcheck that might not be in MikroTik
$fromRadCount = 0;
foreach ($existingInPg as $username => $r) {
    if (isset($allMikrotikUsers[$username])) continue;
    if ($username === 'default-trial') continue;

    $prof = $r['profile'] ?: '12-jam';
    $price = ($prof === '12-jam') ? 4000 : (($prof === '1minggu-40rb') ? 40000 : (($prof === '1bulan-100rb') ? 100000 : 0));

    pg_execute($pg, "ins_voucher", array(
        $username,
        $r['password'],
        $prof,
        $price,
        '12h',
        'radcheck-stock',
        'unused',
        '0s',
        0,
        0,
        0,
        'FreeRADIUS Import'
    ));
    $fromRadCount++;
}

pg_query($pg, "COMMIT");
echo "Migration complete! Migrated $migratedCount users from MikroTik and $fromRadCount remaining users from radcheck.\\n";

$finalRes = pg_query($pg, "SELECT count(*) as total, count(*) FILTER (WHERE status='unused') as unused, count(*) FILTER (WHERE status='active') as active, count(*) FILTER (WHERE status='expired') as expired FROM pacenet_vouchers");
$row = pg_fetch_assoc($finalRes);
echo "Final pacenet_vouchers summary: Total=" . $row['total'] . " (Unused=" . $row['unused'] . ", Active=" . $row['active'] . ", Expired=" . $row['expired'] . ")\\n";
pg_close($pg);
"""

sftp = ssh.open_sftp()
with sftp.file('/tmp/migrate_pacenet_db.php', 'w') as f:
    f.write(php_migration)
sftp.close()

_, stdout, stderr = ssh.exec_command('php /tmp/migrate_pacenet_db.php && rm -f /tmp/migrate_pacenet_db.php')
print(stdout.read().decode('utf-8', errors='replace'))
print(stderr.read().decode('utf-8', errors='replace'))

ssh.close()
