#!/usr/bin/php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../config/database.php";

$log_file = '/var/log/auto_pengeluaran.log';

function writeLog($msg) {
    global $log_file;
    file_put_contents($log_file, date('[Y-m-d H:i:s]') . " - " . $msg . "\n", FILE_APPEND);
}

try {
    $pdo = Database::getInstance()->getConnection();
    
    // ============================================
    // 1. PENGELUARAN RUTIN (dari tabel pengeluaran_otomatis)
    // ============================================
    
    // Cek apakah sudah dicatat bulan ini
    $cek_rutin = $pdo->query("
        SELECT COUNT(*) as total FROM pengeluaran 
        WHERE jenis = 'rutin' 
        AND MONTH(tanggal) = MONTH(CURDATE()) 
        AND YEAR(tanggal) = YEAR(CURDATE())
        AND keterangan LIKE '%AUTO RUTIN%'
    ")->fetch()['total'];
    
    if ($cek_rutin == 0) {
        $rutin_items = $pdo->query("
            SELECT * FROM pengeluaran_otomatis 
            WHERE jenis = 'rutin' AND aktif = 1
        ")->fetchAll();
        
        foreach ($rutin_items as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO pengeluaran (jenis, keterangan, jumlah, tanggal) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                'rutin',
                '[AUTO RUTIN] ' . $item['nama'] . ' - ' . $item['keterangan'],
                $item['jumlah']
            ]);
        }
        writeLog(count($rutin_items) . " pengeluaran RUTIN otomatis dicatat");
    } else {
        writeLog("RUTIN sudah dicatat bulan ini, skip");
    }
    // Bagi hasil dipindah ke cron terpisah (auto_bagi_hasil.php)
    
echo date('[Y-m-d H:i:s]') . " - Selesai\n";
    
} catch(PDOException $e) {
    writeLog("ERROR: " . $e->getMessage());
    echo date('[Y-m-d H:i:s]') . " - Error: " . $e->getMessage() . "\n";
}
?>
