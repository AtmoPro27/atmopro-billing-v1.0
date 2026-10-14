<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak");
}
require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getInstance()->getConnection();

$dari = $_GET['dari'] ?? date('Y-01-01');
$sampai = $_GET['sampai'] ?? date('Y-12-31');
$tahun = date('Y', strtotime($dari));

$filename = "laba_rugi_" . $dari . "_sd_" . $sampai . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['LAPORAN LABA RUGI']);
fputcsv($output, ['Tahun', $tahun]);
fputcsv($output, ['Periode', $dari . ' s/d ' . $sampai]);
fputcsv($output, []);

// =============================================================
// 1. RINCIAN PER BULAN (Jan - Des)
// =============================================================
fputcsv($output, ['RINCIAN PER BULAN']);
fputcsv($output, [
    'Bulan',
    'Pendapatan Invoice',
    'Pendapatan Manual',
    'Total Pendapatan',
    'Total Pengeluaran',
    'Laba / Rugi'
]);

$nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

$grand_inv = 0;
$grand_man = 0;
$grand_peng = 0;

for ($m = 1; $m <= 12; $m++) {
    // Invoice per bulan
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM invoice WHERE status = 'paid' AND MONTH(tgl_bayar) = ? AND YEAR(tgl_bayar) = ?");
    $stmt->execute([$m, $tahun]);
    $inv = (float) $stmt->fetchColumn();

    // Manual per bulan
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM income_manual WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt->execute([$m, $tahun]);
    $man = (float) $stmt->fetchColumn();

    // Pengeluaran per bulan
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM pengeluaran WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt->execute([$m, $tahun]);
    $peng = (float) $stmt->fetchColumn();

    $total_income = $inv + $man;
    $laba_rugi = $total_income - $peng;

    fputcsv($output, [
        $nama_bulan[$m-1],
        $inv,
        $man,
        $total_income,
        $peng,
        $laba_rugi
    ]);

    $grand_inv += $inv;
    $grand_man += $man;
    $grand_peng += $peng;
}

// Baris TOTAL
$grand_income = $grand_inv + $grand_man;
$grand_laba = $grand_income - $grand_peng;

fputcsv($output, []);
fputcsv($output, [
    'TOTAL',
    $grand_inv,
    $grand_man,
    $grand_income,
    $grand_peng,
    $grand_laba
]);

// =============================================================
// 2. RINGKASAN TAHUNAN
// =============================================================
fputcsv($output, []);
fputcsv($output, ['RINGKASAN TAHUNAN']);
fputcsv($output, ['Pendapatan Invoice', $grand_inv]);
fputcsv($output, ['Pendapatan Manual', $grand_man]);
fputcsv($output, ['Total Pendapatan', $grand_income]);
fputcsv($output, ['Total Pengeluaran', $grand_peng]);
fputcsv($output, ['LABA / RUGI', $grand_laba]);
fputcsv($output, []);

// =============================================================
// 3. MARGIN LABA (%)
// =============================================================
if ($grand_income > 0) {
    $margin = ($grand_laba / $grand_income) * 100;
    fputcsv($output, ['Margin Laba', number_format($margin, 2) . '%']);
}

fputcsv($output, []);
fputcsv($output, ['Dicetak', date('d-m-Y H:i:s')]);

fclose($output);
exit;
