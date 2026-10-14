<?php
/**
 * Cron: Aktifkan user PPPoE di Mikrotik
 * HANYA untuk pelanggan dengan status = 'active' DAN jatuh_tempo >= hari ini
 */

require_once __DIR__ . '/../config/database.php';
include_once __DIR__ . '/../includes/mikrotik_api.php';

$pdo = Database::getInstance()->getConnection();
date_default_timezone_set('Asia/Jakarta');

$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

echo "[$now] START - Enable active router (filter jatuh_tempo)\n";

try {
    // HANYA status = 'active' DAN jatuh_tempo >= hari ini
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.id_pelanggan,
            p.nama_lengkap,
            p.jatuh_tempo,
            pk.router_id
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        WHERE p.status = 'active'
        AND p.status_aktif = 'aktif'
        AND DATE(p.jatuh_tempo) >= ?
    ");
    $stmt->execute([$today]);
    $customers = $stmt->fetchAll();

    $total = 0;
    foreach ($customers as $customer) {
        if (!empty($customer['router_id']) && !empty($customer['id_pelanggan'])) {
            $result = enable_pppoe_user($customer['router_id'], $customer['id_pelanggan']);
            if ($result['success']) {
                echo "  ✅ Aktif: {$customer['id_pelanggan']} - {$customer['nama_lengkap']} (Jatuh tempo: {$customer['jatuh_tempo']})\n";
                $total++;
            } else {
                echo "  ❌ Gagal: {$customer['id_pelanggan']} - " . ($result['message'] ?? 'Unknown error') . "\n";
            }
        }
    }

    echo "[$now] SELESAI - Total diaktifkan: $total\n";

} catch(PDOException $e) {
    echo "[$now] ERROR: " . $e->getMessage() . "\n";
}
?>
