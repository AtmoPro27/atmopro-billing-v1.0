#!/usr/bin/php
<?php
require_once __DIR__ . "/../config/database.php";

$pdo = Database::getInstance()->getConnection();

// 1. Pelanggan dengan invoice paid -> ACTIVE
$pdo->exec("
    UPDATE pelanggan c
    SET c.status = 'active'
    WHERE c.status = 'isolir'
    AND EXISTS (
        SELECT 1 FROM invoice i
        WHERE i.pelanggan_id = c.id
        AND i.status = 'paid'
    )
");

// 2. Pelanggan dengan invoice pending & lewat jatuh tempo -> ISOLIR
$pdo->exec("
    UPDATE pelanggan c
    SET c.status = 'blocked'
    WHERE c.status = 'active'
    AND (c.tipe_pelanggan = 'reguler' OR c.tipe_pelanggan IS NULL)
    AND EXISTS (
        SELECT 1 FROM invoice i
        WHERE i.pelanggan_id = c.id
        AND i.status = 'pending'
        AND i.tgl_jatuh_tempo < CURDATE()
    )
");

echo date('[Y-m-d H:i:s]') . " - Status semua pelanggan diperbaiki\n";
?>
