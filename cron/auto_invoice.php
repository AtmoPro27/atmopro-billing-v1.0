<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
   require_once __DIR__ . '/../config/database.php';
$pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$log = date('[Y-m-d H:i:s]') . " - Memulai proses auto invoice\n";

// Hanya untuk pelanggan REGULER dengan status ACTIVE
// EXCLUDE pelanggan VIP & FASUM (tidak ada jatuh tempo & tidak diisolir)
$stmt = $pdo->query("
    SELECT p.id, p.id_pelanggan, p.nama_lengkap, p.paket_id, p.jatuh_tempo, p.status, p.tipe_pelanggan, pk.harga, pk.nama_paket
    FROM pelanggan p
    LEFT JOIN paket_internet pk ON p.paket_id = pk.id
    WHERE p.status = 'active'
    AND pk.id IS NOT NULL
    AND pk.harga > 0
    AND (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)
");
$customers = $stmt->fetchAll();

$log .= "Total pelanggan REGULER ACTIVE dengan paket: " . count($customers) . "\n";

// Log pelanggan yang di-skip karena VIP/FASUM
$stmt_skip = $pdo->query("
    SELECT p.id_pelanggan, p.nama_lengkap, p.tipe_pelanggan 
    FROM pelanggan p 
    WHERE p.tipe_pelanggan IN ('vip', 'fasum') 
    AND p.status = 'active'
");
$skip_vip_fasum = $stmt_skip->fetchAll();

if (count($skip_vip_fasum) > 0) {
    $log .= "\n[SKIP] Pelanggan VIP & FASUM (tidak dibuatkan invoice):\n";
    foreach ($skip_vip_fasum as $s) {
        $log .= "  - {$s['id_pelanggan']} - {$s['nama_lengkap']} [{$s['tipe_pelanggan']}]\n";
    }
    $log .= "\n";
}

$created = 0;
$failed = 0;
$skipped = 0;

foreach ($customers as $customer) {
    $harga = $customer['harga'] ?? 0;
    if ($harga <= 0) {
        $skipped++;
        continue;
    }

    // Cek apakah sudah ada invoice bulan ini
    $cek = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE pelanggan_id = ? AND MONTH(tgl_terbit) = MONTH(CURDATE()) AND YEAR(tgl_terbit) = YEAR(CURDATE())");
    $cek->execute([$customer['id']]);
    $exists = $cek->fetchColumn();

    if ($exists > 0) {
        $skipped++;
        $log .= "  - Skip: {$customer['nama_lengkap']} [{$customer['status']}] (sudah ada invoice bulan ini)\n";
        continue;
    }

    $no_invoice = 'INV/' . date('Ym') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $tgl_terbit = date('Y-m-d H:i:s');

    // Jatuh tempo = mengikuti jatuh_tempo pelanggan
    if (!empty($customer['jatuh_tempo']) && $customer['jatuh_tempo'] != '0000-00-00 00:00:00') {
        $tgl_jatuh_tempo = date('Y-m-d 23:59:59', strtotime($customer['jatuh_tempo']));
    } else {
        // Fallback: jika jatuh_tempo kosong, gunakan tanggal 25 bulan ini
        $tgl_jatuh_tempo = date('Y-m-25 23:59:59');
        $log .= "  ! Warning: {$customer['nama_lengkap']} tidak memiliki jatuh_tempo, menggunakan default 25\n";
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO invoice (pelanggan_id, no_invoice, jumlah, status, tgl_terbit, tgl_jatuh_tempo) VALUES (?, ?, ?, 'pending', ?, ?)");
        $stmt->execute([$customer['id'], $no_invoice, $harga, $tgl_terbit, $tgl_jatuh_tempo]);
        $created++;
        $log .= "  ✓ Created: {$customer['nama_lengkap']} [{$customer['status']}] - Rp " . number_format($harga) . " - $no_invoice (Jatuh Tempo: $tgl_jatuh_tempo)\n";
    } catch(PDOException $e) {
        $failed++;
        $log .= "  ✗ Failed: {$customer['nama_lengkap']} [{$customer['status']}] - " . $e->getMessage() . "\n";
    }
}

$log .= "\nSelesai! Created: $created, Failed: $failed, Skipped: $skipped\n";
$log .= "Catatan: Pelanggan VIP & FASUM tidak dibuatkan auto invoice (hanya monitoring)\n";
$log .= "Catatan: Pelanggan INACTIVE (isolir) tidak dibuatkan auto invoice\n";
$log .= "----------------------------------------\n";

$log_file = '/var/log/billing_auto_invoice.log';
file_put_contents($log_file, $log, FILE_APPEND);

if (php_sapi_name() === 'cli') {
    echo $log;
}
?>
