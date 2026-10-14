#!/usr/bin/php
<?php
require_once __DIR__ . "/../config/database.php";

$pdo = Database::getInstance()->getConnection();

// Reset waktu online untuk SEMUA pelanggan setiap tanggal 21
// (Setelah jatuh tempo tanggal 20)

// Cek apakah sudah direset bulan ini
$check = $pdo->query("
    SELECT COUNT(*) as total 
    FROM reset_log 
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
    AND keterangan LIKE '%RESET BULANAN%'
")->fetch()['total'];

if ($check > 0) {
    echo date('[Y-m-d H:i:s]') . " - Reset waktu online bulan ini sudah dilakukan\n";
    exit;
}

// Reset semua pelanggan aktif
$stmt = $pdo->prepare("
    UPDATE pelanggan 
    SET total_online_seconds = 0, last_uptime = 0 
    WHERE status = 'active'
");

$stmt->execute();
$count = $stmt->rowCount();

// Log reset
$log = $pdo->prepare("
    INSERT INTO reset_log (pelanggan_id, keterangan, created_at) 
    VALUES (0, ?, NOW())
");
$log->execute(['RESET BULANAN - Reset waktu online semua pelanggan aktif']);

echo date('[Y-m-d H:i:s]') . " - Reset waktu online untuk $count pelanggan (periode baru)\n";
?>
