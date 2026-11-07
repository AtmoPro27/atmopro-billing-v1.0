#!/usr/bin/php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../config/database.php";

$log_file = '/var/log/auto_zakat.log';

function writeLog($msg) {
    global $log_file;
    file_put_contents($log_file, date('[Y-m-d H:i:s]') . " - " . $msg . "\n", FILE_APPEND);
}

try {
    $pdo = Database::getInstance()->getConnection();

    // Cek apakah zakat bulan ini sudah dicatat
    $cek = $pdo->query("
        SELECT COUNT(*) as total FROM pengeluaran
        WHERE jenis = 'zakat'
        AND MONTH(tanggal) = MONTH(CURDATE())
        AND YEAR(tanggal) = YEAR(CURDATE())
    ")->fetch()['total'];

    if ($cek > 0) {
        writeLog("Zakat bulan ini sudah dicatat, skip");
        exit;
    }

    // 1. Income bulan ini
    $income_invoice = $pdo->query("
        SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice
        WHERE status = 'paid'
        AND MONTH(tgl_bayar) = MONTH(CURDATE())
        AND YEAR(tgl_bayar) = YEAR(CURDATE())
    ")->fetch()['total'];

    $income_manual = $pdo->query("
        SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual
        WHERE MONTH(tanggal) = MONTH(CURDATE())
        AND YEAR(tanggal) = YEAR(CURDATE())
    ")->fetch()['total'];

    $income_total = $income_invoice + $income_manual;

    // 2. Pengeluaran rutin bulan ini
    $pengeluaran_rutin = $pdo->query("
        SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran
        WHERE jenis = 'rutin'
        AND MONTH(tanggal) = MONTH(CURDATE())
        AND YEAR(tanggal) = YEAR(CURDATE())
    ")->fetch()['total'];

    // 3. Laba sebelum zakat
    $laba_sebelum_zakat = $income_total - $pengeluaran_rutin;

    // 4. Zakat = 2,5% (jika laba > 0)
    if ($laba_sebelum_zakat > 0) {
        $zakat = round($laba_sebelum_zakat * 0.025, 2);

        $stmt = $pdo->prepare("
            INSERT INTO pengeluaran (jenis, keterangan, jumlah, tanggal)
            VALUES ('zakat', ?, ?, NOW())
        ");
        $stmt->execute([
            '[AUTO ZAKAT] 2,5% dari laba (Income: Rp ' . number_format($income_total, 0, ',', '.') . ' - Rutin: Rp ' . number_format($pengeluaran_rutin, 0, ',', '.') . ' = Rp ' . number_format($laba_sebelum_zakat, 0, ',', '.') . ')',
            $zakat
        ]);

        writeLog("Zakat dicatat: Rp " . number_format($zakat, 0, ',', '.'));
    } else {
        writeLog("Laba sebelum zakat = Rp " . number_format($laba_sebelum_zakat, 0, ',', '.') . " (tidak ada zakat)");
    }

} catch(PDOException $e) {
    writeLog("ERROR: " . $e->getMessage());
}
