<?php
// Reset quota pelanggan REGULER yang melewati jatuh tempo
// EXCLUDE pelanggan VIP & FASUM
require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();

// Ambil pelanggan REGULER dengan jatuh tempo sudah lewat
$stmt = $pdo->prepare("
    SELECT id, id_pelanggan, nama_lengkap, jatuh_tempo
    FROM pelanggan
    WHERE jatuh_tempo < NOW()
    AND status = 'active'
    AND (tipe_pelanggan = 'reguler' OR tipe_pelanggan IS NULL)
");
$stmt->execute();
$customers = $stmt->fetchAll();

$reset_count = 0;
$bulan = date('Y-m');

foreach ($customers as $cust) {
    // Reset traffic bulan ini
    $check = $pdo->prepare("SELECT id FROM pelanggan_traffic WHERE pelanggan_id = ? AND bulan = ?");
    $check->execute([$cust['id'], $bulan]);

    if ($check->fetch()) {
        $pdo->prepare("UPDATE pelanggan_traffic SET total_bytes = 0 WHERE pelanggan_id = ? AND bulan = ?")
            ->execute([$cust['id'], $bulan]);
    } else {
        $pdo->prepare("INSERT INTO pelanggan_traffic (pelanggan_id, total_bytes, bulan) VALUES (?, 0, ?)")
            ->execute([$cust['id'], $bulan]);
    }

    // Reset last_traffic
    $pdo->prepare("UPDATE pelanggan SET last_traffic = 0 WHERE id = ?")
        ->execute([$cust['id']]);

    $reset_count++;
    echo date('[Y-m-d H:i:s]') . " Reset quota untuk {$cust['id_pelanggan']} - {$cust['nama_lengkap']}\n";
}

echo date('[Y-m-d H:i:s]') . " Reset quota selesai. Total: $reset_count pelanggan (reguler only)\n";

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
