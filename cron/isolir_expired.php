#!/usr/bin/php
<?php
require_once __DIR__ . "/../config/database.php";

$pdo = Database::getInstance()->getConnection();

// Isolir pelanggan REGULER yang sudah lewat jatuh tempo
// EXCLUDE VIP & FASUM
$stmt = $pdo->prepare("
    UPDATE pelanggan
    SET status = 'blocked'
    WHERE status = 'active'
    AND (tipe_pelanggan = 'reguler' OR tipe_pelanggan IS NULL)
    AND jatuh_tempo < NOW()
");
$stmt->execute();
$count = $stmt->rowCount();

echo date('[Y-m-d H:i:s]') . " - " . $count . " pelanggan diisolir\n";

// Cek sisa
$stmt = $pdo->query("
    SELECT COUNT(*) FROM pelanggan
    WHERE status = 'active'
    AND (tipe_pelanggan = 'reguler' OR tipe_pelanggan IS NULL)
    AND jatuh_tempo < NOW()
");
$remaining = $stmt->fetchColumn();
echo date('[Y-m-d H:i:s]') . " - Sisa pelanggan expired: " . $remaining . "\n";
?>
