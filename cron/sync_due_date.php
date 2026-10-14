#!/usr/bin/php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$log = date('[Y-m-d H:i:s]') . " - Sinkronisasi jatuh tempo\n";

// Ambil semua pelanggan aktif
$stmt = $pdo->query("
    SELECT p.id, p.id_pelanggan, p.nama_lengkap
    FROM pelanggan p
    WHERE p.status = 'active' AND (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)
");

$customers = $stmt->fetchAll();
$updated = 0;

foreach ($customers as $customer) {
    // Cari invoice pending tertua
    $stmt2 = $pdo->prepare("
        SELECT tgl_jatuh_tempo 
        FROM invoice 
        WHERE pelanggan_id = ? AND status = 'pending' 
        ORDER BY tgl_jatuh_tempo ASC 
        LIMIT 1
    ");
    $stmt2->execute([$customer['id']]);
    $pending = $stmt2->fetch();
    
    if ($pending) {
        // Update jatuh tempo ke invoice pending tertua
        $stmt3 = $pdo->prepare("UPDATE pelanggan SET jatuh_tempo = ? WHERE id = ?");
        $stmt3->execute([$pending['tgl_jatuh_tempo'], $customer['id']]);
        $updated++;
        $log .= "  ✓ {$customer['nama_lengkap']}: jatuh tempo = " . date('d-m-Y', strtotime($pending['tgl_jatuh_tempo'])) . " (dari pending)\n";
    } else {
        // Cari invoice terakhir yang lunas
        $stmt2 = $pdo->prepare("
            SELECT tgl_jatuh_tempo 
            FROM invoice 
            WHERE pelanggan_id = ? AND status = 'paid' 
            ORDER BY tgl_jatuh_tempo DESC 
            LIMIT 1
        ");
        $stmt2->execute([$customer['id']]);
        $last_paid = $stmt2->fetch();
        
        if ($last_paid) {
            // Jatuh tempo = 1 bulan setelah invoice terakhir lunas
            $new_due = date('Y-m-d H:i:s', strtotime($last_paid['tgl_jatuh_tempo'] . ' +1 month'));
            $stmt3 = $pdo->prepare("UPDATE pelanggan SET jatuh_tempo = ? WHERE id = ?");
            $stmt3->execute([$new_due, $customer['id']]);
            $updated++;
            $log .= "  ✓ {$customer['nama_lengkap']}: jatuh tempo = " . date('d-m-Y', strtotime($new_due)) . " (1 bulan setelah lunas)\n";
        }
    }
}

$log .= "Selesai! Diupdate: $updated pelanggan\n";
file_put_contents('/var/log/sync_due_date.log', $log, FILE_APPEND);
echo $log;
?>
