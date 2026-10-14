<?php
/**
 * Cleanup Admin Logs
 * Hapus log berdasarkan konfigurasi:
 * - Berdasarkan umur (hari)
 * - Berdasarkan jumlah maksimal
 */

require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();

// ================= KONFIGURASI =================
// Pilih salah satu metode atau kedua-duanya:

// METODE 1: Hapus berdasarkan UMUR (hari)
$delete_older_than_days = 90;  // Hapus log lebih dari 90 hari (0 = nonaktif)

// METODE 2: Hapus berdasarkan JUMLAH maksimal
$max_logs_to_keep = 5000;       // Simpan 5000 log terakhir (0 = nonaktif)
// ===============================================

$total_deleted = 0;

// Metode 1: Hapus berdasarkan umur
if ($delete_older_than_days > 0) {
    $date_limit = date('Y-m-d H:i:s', strtotime("-$delete_older_than_days days"));
    $stmt = $pdo->prepare("DELETE FROM admin_logs WHERE created_at < ?");
    $stmt->execute([$date_limit]);
    $deleted_age = $stmt->rowCount();
    $total_deleted += $deleted_age;
    echo "[Age] Deleted $deleted_age logs older than $delete_older_than_days days ($date_limit)\n";
}

// Metode 2: Hapus berdasarkan jumlah (simpan N terbaru)
if ($max_logs_to_keep > 0) {
    // Hitung total log
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_logs");
    $total_logs = $stmt->fetch()['total'];
    
    if ($total_logs > $max_logs_to_keep) {
        $to_delete = $total_logs - $max_logs_to_keep;
        
        // Cari ID tertua yang boleh dihapus
        $stmt = $pdo->prepare("SELECT id FROM admin_logs ORDER BY created_at ASC LIMIT ?");
        $stmt->execute([$to_delete]);
        $old_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($old_ids)) {
            $ids_string = implode(',', $old_ids);
            $stmt = $pdo->query("DELETE FROM admin_logs WHERE id IN ($ids_string)");
            $deleted_count = $stmt->rowCount();
            $total_deleted += $deleted_count;
            echo "[Count] Deleted $deleted_count logs, keeping last $max_logs_to_keep (total was $total_logs)\n";
        }
    } else {
        echo "[Count] No deletion needed. Total logs: $total_logs (limit: $max_logs_to_keep)\n";
    }
}

echo "Total deleted: $total_deleted\n";
?>
