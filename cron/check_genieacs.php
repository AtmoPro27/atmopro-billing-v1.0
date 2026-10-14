#!/usr/bin/php
<?php
require_once __DIR__ . '/../includes/settings_helper.php';

$result = testGenieACSConnection();
$log = date('[Y-m-d H:i:s]') . ' ';

if ($result['success']) {
    $log .= "✅ GenieACS OK ({$result['time']}ms)";
    // Hapus flag down
    @unlink('/tmp/genieacs_down.flag');
} else {
    $log .= "❌ GenieACS DOWN: {$result['message']}";
    
    // Cek apakah sudah pernah kirim notif (biar tidak spam)
    $flagFile = '/tmp/genieacs_down.flag';
    $lastNotif = file_exists($flagFile) ? (int)file_get_contents($flagFile) : 0;
    
    // Kirim notif max 1x per jam
    if (time() - $lastNotif > 3600) {
        // Kirim WA (sesuaikan dengan sistem WA Anda)
        $pesan = "⚠️ *GENIEACS DOWN*\n\n";
        $pesan .= "Waktu: " . date('d/m/Y H:i') . "\n";
        $pesan .= "Error: {$result['message']}\n";
        $pesan .= "URL: " . getSetting('genieacs_url') . "\n\n";
        $pesan .= "Mohon cek server GenieACS!";
        
        // TODO: sesuaikan dengan fungsi WA Anda
        // kirim_wa_admin($pesan);
        
        file_put_contents($flagFile, time());
        $log .= " [NOTIF DIKIRIM]";
    }
}

file_put_contents('/var/log/genieacs_check.log', $log . PHP_EOL, FILE_APPEND);
echo $log . PHP_EOL;
