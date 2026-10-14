<?php
/**
 * Cron: Cek pelanggan yang jatuh tempo dan isolir otomatis
 * Berjalan setiap jam
 */

require_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../includes/mikrotik_api.php';

$pdo = Database::getInstance()->getConnection();
date_default_timezone_set('Asia/Jakarta');

$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

echo "[$now] START - Cek jatuh tempo pelanggan\n";

try {
    // =============================================================
    // 1. ISOLIR PELANGGAN YANG JATUH TEMPO
    // =============================================================
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.id_pelanggan, 
            p.nama_lengkap, 
            p.jatuh_tempo, 
            pk.router_id
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        WHERE p.status = 'active' AND (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL) 
        AND p.status_aktif = 'aktif'
        AND DATE(p.jatuh_tempo) < ?
    ");
    $stmt->execute([$today]);
    $expired = $stmt->fetchAll();

    foreach ($expired as $customer) {
        echo "  ⛔ ISOLIR: {$customer['id_pelanggan']} - {$customer['nama_lengkap']}\n";
        
        // Ubah status menjadi 'inactive'
        $stmt_update = $pdo->prepare("UPDATE pelanggan SET status = 'inactive' WHERE id = ?");
        $stmt_update->execute([$customer['id']]);
        
        // DISABLE user PPPoE (bukan ENABLE!)
        if (!empty($customer['router_id']) && !empty($customer['id_pelanggan'])) {
            $result = disable_pppoe_user($customer['router_id'], $customer['id_pelanggan']);
            if ($result['success']) {
                echo "    ✅ User PPPoE dinonaktifkan\n";
            } else {
                echo "    ❌ Gagal nonaktifkan user: " . ($result['message'] ?? 'Unknown error') . "\n";
            }
        }
    }

    // =============================================================
    // 2. AKTIFKAN KEMBALI PELANGGAN YANG SUDAH BAYAR
    // =============================================================
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.id_pelanggan, 
            p.nama_lengkap,
            pk.router_id
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        WHERE p.status = 'inactive'
        AND p.status_aktif = 'aktif'
        AND DATE(p.jatuh_tempo) >= ?
    ");
    $stmt->execute([$today]);
    $reactive = $stmt->fetchAll();

    foreach ($reactive as $customer) {
        echo "  ✅ AKTIFKAN: {$customer['id_pelanggan']} - {$customer['nama_lengkap']}\n";
        
        // Ubah status menjadi 'active'
        $stmt_update = $pdo->prepare("UPDATE pelanggan SET status = 'active' WHERE id = ?");
        $stmt_update->execute([$customer['id']]);
        
        // ENABLE user PPPoE
        if (!empty($customer['router_id']) && !empty($customer['id_pelanggan'])) {
            $result = enable_pppoe_user($customer['router_id'], $customer['id_pelanggan']);
            if ($result['success']) {
                echo "    ✅ User PPPoE diaktifkan kembali\n";
            } else {
                echo "    ❌ Gagal aktifkan user: " . ($result['message'] ?? 'Unknown error') . "\n";
            }
        }
    }

    echo "[$now] SELESAI\n";

} catch(PDOException $e) {
    echo "[$now] ERROR: " . $e->getMessage() . "\n";
}
?>
