#!/usr/bin/php
<?php
require_once __DIR__ . "/../config/database.php";

$pdo = Database::getInstance()->getConnection();

// Isolir pelanggan REGULER yang expired (jatuh tempo < hari ini)
// EXCLUDE VIP & FASUM
$update = $pdo->prepare("
    UPDATE pelanggan 
    SET status = 'blocked' 
    WHERE status = 'active' 
    AND (tipe_pelanggan = 'reguler' OR tipe_pelanggan IS NULL) 
    AND DATE(jatuh_tempo) < CURDATE()
");
$update->execute();
$count = $update->rowCount();

echo date('[Y-m-d H:i:s]') . " - " . $count . " pelanggan diisolir\n";

// Cek sisa
$stmt = $pdo->query("
    SELECT COUNT(*) FROM pelanggan 
    WHERE status = 'active' 
    AND (tipe_pelanggan = 'reguler' OR tipe_pelanggan IS NULL) 
    AND DATE(jatuh_tempo) < CURDATE()
");
$remaining = $stmt->fetchColumn();
echo date('[Y-m-d H:i:s]') . " - Sisa pelanggan expired: " . $remaining . "\n";
?>
