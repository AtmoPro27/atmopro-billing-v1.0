<?php
// Reset akumulasi untuk pelanggan REGULER (periode baru tgl 21)
// EXCLUDE pelanggan VIP & FASUM
require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();

// Ambil pelanggan REGULER saja
// Dijalankan tgl 21 jam 00:05 oleh cron
$stmt = $pdo->query("
    SELECT id, id_pelanggan, jatuh_tempo, tipe_pelanggan 
    FROM pelanggan
    WHERE tipe_pelanggan = 'reguler' OR tipe_pelanggan IS NULL
");

$reset = 0;
while ($row = $stmt->fetch()) {
    // Reset total_online_seconds
    $pdo->prepare("UPDATE pelanggan SET total_online_seconds = 0 WHERE id = ?")
        ->execute([$row['id']]);

    // Reset total_bytes untuk bulan ini
    $pdo->prepare("UPDATE pelanggan_traffic SET total_bytes = 0 WHERE pelanggan_id = ? AND bulan = DATE_FORMAT(NOW(), '%Y-%m')")
        ->execute([$row['id']]);

    echo date('[Y-m-d H:i:s]') . " Reset akumulasi untuk {$row['id_pelanggan']} (jatuh tempo: {$row['jatuh_tempo']})\n";
    $reset++;
}

echo "Selesai! Total reset: $reset pelanggan (reguler only)\n";

// Log pelanggan VIP & FASUM yang di-skip
$stmt_skip = $pdo->query("
    SELECT id_pelanggan, nama_lengkap, tipe_pelanggan 
    FROM pelanggan 
    WHERE tipe_pelanggan IN ('vip', 'fasum')
");
$skip = $stmt_skip->fetchAll();
if (count($skip) > 0) {
    echo date('[Y-m-d H:i:s]') . " SKIP VIP/FASUM (" . count($skip) . " pelanggan):\n";
    foreach ($skip as $s) {
        echo "  - {$s['id_pelanggan']} - {$s['nama_lengkap']} [{$s['tipe_pelanggan']}]\n";
    }
}
?>
