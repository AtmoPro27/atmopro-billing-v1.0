#!/usr/bin/php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();

$now = date('Y-m-d H:i:s');

// 1. Isolir pelanggan REGULER yang punya invoice pending & lewat jatuh tempo
// EXCLUDE pelanggan VIP & FASUM
$stmt = $pdo->prepare("
    UPDATE pelanggan c
    SET c.status = 'blocked'
    WHERE c.status = 'active'
    AND (c.tipe_pelanggan = 'reguler' OR c.tipe_pelanggan IS NULL)
    AND EXISTS (
        SELECT 1 FROM invoice i
        WHERE i.pelanggan_id = c.id
        AND i.status = 'pending'
        AND i.tgl_jatuh_tempo < ?
    )
");
$stmt->execute([$now]);
$isolir_count = $stmt->rowCount();

echo "[" . date('Y-m-d H:i:s') . "] Isolated $isolir_count pelanggan (reguler only)\n";

// 2. Jika ada yang diisolir, tampilkan detail
if ($isolir_count > 0) {
    $stmt = $pdo->query("
        SELECT c.id_pelanggan, c.nama_lengkap, c.status
        FROM pelanggan c
        WHERE c.status = 'blocked'
        AND (c.tipe_pelanggan = 'reguler' OR c.tipe_pelanggan IS NULL)
        LIMIT 10
    ");
    while ($row = $stmt->fetch()) {
        echo "  - " . $row['id_pelanggan'] . " | " . $row['nama_lengkap'] . "\n";
    }
}

// 3. Log pelanggan VIP & FASUM yang di-skip
$stmt_skip = $pdo->query("
    SELECT id_pelanggan, nama_lengkap, tipe_pelanggan
    FROM pelanggan
    WHERE tipe_pelanggan IN ('vip', 'fasum')
");
$skip = $stmt_skip->fetchAll();
if (count($skip) > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] SKIP VIP/FASUM (" . count($skip) . " pelanggan):\n";
    foreach ($skip as $s) {
        echo "  - {$s['id_pelanggan']} - {$s['nama_lengkap']} [{$s['tipe_pelanggan']}]\n";
    }
}
