#!/usr/bin/php
<?php
require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();

// Reset pelanggan yang sudah melewati jatuh tempo
$stmt = $pdo->prepare("UPDATE pelanggan 
                       SET total_online_seconds = 0, last_uptime = 0 
                       WHERE CONCAT(DATE(jatuh_tempo), ' ', TIME(jatuh_tempo)) < NOW() 
                       AND status = 'active'");
$stmt->execute();

// Reset traffic
$stmt2 = $pdo->prepare("UPDATE pelanggan_traffic pt 
                        JOIN pelanggan p ON pt.pelanggan_id = p.id 
                        SET pt.total_bytes = 0 
                        WHERE CONCAT(DATE(p.jatuh_tempo), ' ', TIME(p.jatuh_tempo)) < NOW()");
$stmt2->execute();

echo date('[Y-m-d H:i:s]') . " - Reset jatuh tempo selesai\n";
?>
