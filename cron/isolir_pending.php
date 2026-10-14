#!/usr/bin/php
<?php
require_once __DIR__ . "/../config/database.php";

$pdo = Database::getInstance()->getConnection();

// Isolir pelanggan REGULER yang punya tagihan pending dan sudah melewati jatuh tempo
// EXCLUDE pelanggan VIP & FASUM
$update = $pdo->prepare("
    UPDATE pelanggan c
    SET c.status = 'isolir'
    WHERE c.status = 'active'
    AND (c.tipe_pelanggan = 'reguler' OR c.tipe_pelanggan IS NULL)
    AND EXISTS (
        SELECT 1 FROM invoice i
        WHERE i.pelanggan_id = c.id
        AND i.status = 'pending'
        AND i.tgl_jatuh_tempo < NOW()
    )
");
$update->execute();
$count = $update->rowCount();

echo date('[Y-m-d H:i:s]') . " - " . $count . " pelanggan diisolir karena tagihan pending\n";

// Cek sisa pelanggan dengan tagihan pending tapi masih aktif (REGULER only)
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM pelanggan c
    WHERE c.status = 'active'
    AND (c.tipe_pelanggan = 'reguler' OR c.tipe_pelanggan IS NULL)
    AND EXISTS (
        SELECT 1 FROM invoice i
        WHERE i.pelanggan_id = c.id
        AND i.status = 'pending'
        AND i.tgl_jatuh_tempo < NOW()
    )
");
$remaining = $stmt->fetchColumn();
echo date('[Y-m-d H:i:s]') . " - Sisa pelanggan aktif dengan tagihan pending: " . $remaining . "\n";

// Log pelanggan VIP & FASUM yang di-skip
$stmt_skip = $pdo->query("
    SELECT id_pelanggan, nama_lengkap, tipe_pelanggan 
    FROM pelanggan 
    WHERE tipe_pelanggan IN ('vip', 'fasum') 
    AND status = 'active'
");
$skip = $stmt_skip->fetchAll();
if (count($skip) > 0) {
    echo date('[Y-m-d H:i:s]') . " - SKIP VIP/FASUM (" . count($skip) . " pelanggan):\n";
    foreach ($skip as $s) {
        echo "  - {$s['id_pelanggan']} - {$s['nama_lengkap']} [{$s['tipe_pelanggan']}]\n";
    }
}
?>
