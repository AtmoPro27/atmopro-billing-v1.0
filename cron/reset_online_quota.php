#!/usr/bin/php
<?php
/**
 * Cron: Reset waktu online & kuota pelanggan yang jatuh tempo
 * Dijalankan setiap jam
 * 
 * Tujuan:
 * 1. Reset total_online_seconds = 0
 * 2. Reset total_bytes = 0
 * 3. Reset last_uptime = 0
 * 4. Reset last_traffic = 0
 */

require_once __DIR__ . '/../config/database.php';

$pdo = Database::getInstance()->getConnection();
date_default_timezone_set('Asia/Jakarta');

$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

echo "[$now] START - Reset waktu online & kuota\n";

try {
    // =============================================================
    // CARI PELANGGAN YANG JATUH TEMPO HARI INI
    // =============================================================
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            id_pelanggan, 
            nama_lengkap,
            total_online_seconds,
            last_traffic
        FROM pelanggan
        WHERE status_aktif = 'aktif'
        AND DATE(jatuh_tempo) = ?
    ");
    $stmt->execute([$today]);
    $customers = $stmt->fetchAll();

    if (empty($customers)) {
        echo "[$now] Tidak ada pelanggan yang jatuh tempo hari ini\n";
    } else {
        echo "[$now] Ditemukan " . count($customers) . " pelanggan jatuh tempo\n";
        
        foreach ($customers as $customer) {
            echo "  - RESET: {$customer['id_pelanggan']} - {$customer['nama_lengkap']}\n";
            echo "    Sebelum: Online = {$customer['total_online_seconds']} detik, Traffic = " . number_format($customer['last_traffic'] / 1024 / 1024 / 1024, 2) . " GB\n";
            
            // =============================================================
            // RESET WAKTU ONLINE & KUOTA
            // =============================================================
            $stmt_update = $pdo->prepare("
                UPDATE pelanggan 
                SET 
                    total_online_seconds = 0,
                    last_uptime = 0,
                    last_traffic = 0,
                    total_online_seconds_reset = NOW()
                WHERE id = ?
            ");
            $stmt_update->execute([$customer['id']]);
            
            // Reset traffic di tabel pelanggan_traffic
            $stmt_traffic = $pdo->prepare("
                UPDATE pelanggan_traffic 
                SET total_bytes = 0 
                WHERE pelanggan_id = ?
                AND bulan = DATE_FORMAT(NOW(), '%Y-%m')
            ");
            $stmt_traffic->execute([$customer['id']]);
            
            echo "    ✅ Reset selesai\n";
        }
    }

    // =============================================================
    // CEK PELANGGAN YANG SUDAH DIBAYAR & PERLU RESET
    // =============================================================
    // (Opsional: reset pelanggan yang statusnya berubah dari inactive ke active)
    // $stmt = $pdo->prepare("
    //     SELECT id, id_pelanggan, nama_lengkap
    //     FROM pelanggan
    //     WHERE status = 'active' AND (tipe_pelanggan = 'reguler' OR tipe_pelanggan IS NULL)
    //     AND status_aktif = 'aktif'
    //     AND total_online_seconds > 0
    //     AND DATE(jatuh_tempo) = ?
    // ");

    echo "[$now] SELESAI\n";

} catch(PDOException $e) {
    echo "[$now] ERROR: " . $e->getMessage() . "\n";
}
?>
