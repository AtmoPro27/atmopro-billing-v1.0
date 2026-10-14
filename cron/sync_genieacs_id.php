<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/genieacs_functions.php';

date_default_timezone_set('Asia/Jakarta');

$pdo = Database::getInstance()->getConnection();
echo date('[Y-m-d H:i:s]') . " START - Sync GenieACS Device ID\n";

$devices = getGenieACSDevices(2000);
echo "📦 Total device GenieACS: " . count($devices) . "\n";

$updated = 0;
$not_found = 0;

foreach ($devices as $d) {
    $idPel = $d['id'] ?? '';
    $devId = $d['device_id'] ?? '';
    
    if (empty($idPel) || empty($devId)) continue;
    
    // Cek pelanggan ada di billing
    $stmt = $pdo->prepare("SELECT id, genieacs_device_id FROM pelanggan WHERE id_pelanggan = ?");
    $stmt->execute([$idPel]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$p) {
        $not_found++;
        continue;
    }
    
    // Update kalau beda
    if ($p['genieacs_device_id'] != $devId) {
        $upd = $pdo->prepare("UPDATE pelanggan SET genieacs_device_id = ? WHERE id = ?");
        $upd->execute([$devId, $p['id']]);
        $updated++;
        echo "  ✅ {$idPel} → {$devId}\n";
    }
}

echo date('[Y-m-d H:i:s]') . " SELESAI\n";
echo "Updated: {$updated} | Tidak ada di billing: {$not_found}\n";
