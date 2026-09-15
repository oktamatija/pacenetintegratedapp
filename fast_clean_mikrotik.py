import paramiko
from deploy_config import VPS_HOST, VPS_PORT, VPS_USER, VPS_PASS

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(VPS_HOST, port=VPS_PORT, username=VPS_USER, password=VPS_PASS, timeout=10)

php_clean = """<?php
require_once '/var/www/pacenetintegratedapp/api/common.php';
global $data;

foreach ($data as $sName => $sCfg) {
    if ($sName === 'mikhmon' || empty($sName) || strpos($sName, 'new-') === 0 || empty($sCfg[1])) continue;
    
    echo "Connecting to $sName...\\n";
    $conn = connectMikrotik($sName, 10);
    if (!$conn) {
        echo "Failed to connect to $sName\\n";
        continue;
    }
    
    $api = $conn['api'];
    
    // Check user count before
    $before = $api->comm('/ip/hotspot/user/print');
    $countBefore = count($before);
    echo "Current local users on $sName: $countBefore\\n";
    
    // Add lightning-fast MikroTik script to purge local users while keeping default-trial
    $scrName = 'pacenet_fast_purge';
    // Clean any old script
    $old = $api->comm('/system/script/print', array('?name' => $scrName));
    if (!empty($old)) {
        foreach ($old as $o) {
            $api->comm('/system/script/remove', array('.id' => $o['.id']));
        }
    }
    
    // Add script
    $api->comm('/system/script/add', array(
        'name' => $scrName,
        'source' => '/ip hotspot user remove [find name!="default-trial"]'
    ));
    
    // Run script natively on MikroTik CPU
    echo "Running native MikroTik purge script...\\n";
    $api->comm('/system/script/run', array('.id' => $scrName));
    
    // Sleep 3 seconds to let MikroTik finish internal delete
    sleep(3);
    
    // Clean up script
    $api->comm('/system/script/remove', array('.id' => $scrName));
    
    // Check user count after
    $after = $api->comm('/ip/hotspot/user/print');
    $countAfter = count($after);
    echo "Users remaining on $sName: $countAfter\\n";
    
    // Check active sessions (should be untouched!)
    $active = $api->comm('/ip/hotspot/active/print');
    echo "Active browsing sessions online on $sName: " . count($active) . "\\n\\n";
    
    $api->disconnect();
}
"""

sftp = ssh.open_sftp()
with sftp.open('/tmp/fast_clean.php', 'w') as f:
    f.write(php_clean)
sftp.close()

stdin, stdout, stderr = ssh.exec_command("php /tmp/fast_clean.php")
print(stdout.read().decode())
print(stderr.read().decode())

ssh.close()
