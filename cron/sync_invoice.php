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

// Ambil semua pelanggan aktif
$stmt = $pdo->query("
    SELECT p.id, p.id_pelanggan, p.nama_lengkap, p.jatuh_tempo, p.paket_id, pk.harga
    FROM pelanggan p
    LEFT JOIN paket_internet pk ON p.paket_id = pk.id
    WHERE p.status = 'active' 
    AND pk.id IS NOT NULL
    AND pk.harga > 0
    AND (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)
");

$customers = $stmt->fetchAll();
$created = 0;
$log = date('[Y-m-d H:i:s]') . " - Sinkronisasi invoice\n";

foreach ($customers as $customer) {
    $jatuh_tempo = $customer['jatuh_tempo'];
    if (empty($jatuh_tempo)) continue;
    
    $bulan_jatuh_tempo = date('m', strtotime($jatuh_tempo));
    $tahun_jatuh_tempo = date('Y', strtotime($jatuh_tempo));
    
    // Cek apakah ada invoice untuk bulan jatuh tempo
    $cek = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE pelanggan_id = ? AND MONTH(tgl_jatuh_tempo) = ? AND YEAR(tgl_jatuh_tempo) = ?");
    $cek->execute([$customer['id'], $bulan_jatuh_tempo, $tahun_jatuh_tempo]);
    
    if ($cek->fetchColumn() == 0) {
        // Buat invoice
        $no_invoice = 'INV/' . $tahun_jatuh_tempo . str_pad($bulan_jatuh_tempo, 2, '0', STR_PAD_LEFT) . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $tgl_terbit = date('Y-m-05 00:00:00', strtotime($jatuh_tempo));
        $tgl_jatuh_tempo = date('Y-m-d 23:59:59', strtotime($jatuh_tempo));
        
        $stmt = $pdo->prepare("INSERT INTO invoice (pelanggan_id, no_invoice, jumlah, status, tgl_terbit, tgl_jatuh_tempo) VALUES (?, ?, ?, 'pending', ?, ?)");
        $stmt->execute([$customer['id'], $no_invoice, $customer['harga'], $tgl_terbit, $tgl_jatuh_tempo]);
        $created++;
        $log .= "  ✓ Buat invoice untuk {$customer['nama_lengkap']} - jatuh tempo " . date('d-m-Y', strtotime($jatuh_tempo)) . "\n";
    }
}

$log .= "Selesai! Dibuat: $created invoice\n";
file_put_contents('/var/log/sync_invoice.log', $log, FILE_APPEND);
echo $log;
?>
