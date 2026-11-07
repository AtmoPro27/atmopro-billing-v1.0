#!/usr/bin/php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../config/database.php";

$log_file = '/var/log/auto_bagi_hasil.log';

function writeLog($msg) {
    global $log_file;
    file_put_contents($log_file, date('[Y-m-d H:i:s]') . " - " . $msg . "\n", FILE_APPEND);
}

try {
    $pdo = Database::getInstance()->getConnection();

    // Cek apakah bagi hasil bulan ini sudah dicatat
    $cek = $pdo->query("
        SELECT COUNT(*) as total FROM pengeluaran
        WHERE jenis = 'bagi_hasil'
        AND MONTH(tanggal) = MONTH(CURDATE())
        AND YEAR(tanggal) = YEAR(CURDATE())
        AND keterangan LIKE '%AUTO BAGI HASIL%'
    ")->fetch()['total'];

    if ($cek > 0) {
        writeLog("Bagi hasil bulan ini sudah dicatat, skip");
        exit;
    }

    // Ambil investor dengan bagi hasil > 0
    $investors = $pdo->query("
        SELECT id, nama, bagi_hasil_per_bulan
        FROM investor
        WHERE bagi_hasil_per_bulan > 0
    ")->fetchAll();

    $count = 0;
    foreach ($investors as $inv) {
        $stmt = $pdo->prepare("
            INSERT INTO pengeluaran (jenis, investor_id, keterangan, jumlah, tanggal)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            'bagi_hasil',
            $inv['id'],
            '[AUTO BAGI HASIL] ' . $inv['nama'] . ' - Bagi hasil bulanan',
            $inv['bagi_hasil_per_bulan']
        ]);
        $count++;
    }

    writeLog("$count pengeluaran BAGI HASIL otomatis dicatat");

} catch(PDOException $e) {
    writeLog("ERROR: " . $e->getMessage());
}
