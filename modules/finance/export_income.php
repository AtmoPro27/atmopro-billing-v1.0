<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak");
}
require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getInstance()->getConnection();

$mode = $_GET['mode'] ?? 'bulanan';
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$dari = null;
$sampai = null;

switch ($mode) {
    case 'harian':
        $dari = $tanggal;
        $sampai = $tanggal;
        $label = "Harian - " . date('d-m-Y', strtotime($tanggal));
        break;
    case 'tahunan':
        $dari = "$tahun-01-01";
        $sampai = "$tahun-12-31";
        $label = "Tahunan - $tahun";
        break;
    default:
        $dari = date('Y-m-01', strtotime("$tahun-$bulan-01"));
        $sampai = date('Y-m-t', strtotime("$tahun-$bulan-01"));
        $label = "Bulanan - " . date('F Y', strtotime("$tahun-$bulan-01"));
        break;
}

$filename = "income_" . $mode . "_" . $dari . "_sd_" . $sampai . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// =============================================================
// MODE TAHUNAN — Rekap per bulan (Jan-Des)
// =============================================================
if ($mode === 'tahunan') {
    fputcsv($output, ['LAPORAN PENDAPATAN TAHUNAN']);
    fputcsv($output, ['Tahun', $tahun]);
    fputcsv($output, []);
    fputcsv($output, ['Bulan', 'Pendapatan Invoice', 'Pendapatan Manual', 'Total Pendapatan', 'Jumlah Transaksi']);

    $nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    $grand_inv = 0;
    $grand_man = 0;
    $grand_jml = 0;

    for ($m = 1; $m <= 12; $m++) {
        // Invoice per bulan
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(jumlah), 0) AS total, COUNT(*) AS jml
            FROM invoice 
            WHERE status = 'paid' 
            AND MONTH(tgl_bayar) = ? AND YEAR(tgl_bayar) = ?
        ");
        $stmt->execute([$m, $tahun]);
        $row_inv = $stmt->fetch(PDO::FETCH_ASSOC);
        $inv = $row_inv['total'];
        $jml_inv = $row_inv['jml'];

        // Manual per bulan
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(jumlah), 0) AS total, COUNT(*) AS jml
            FROM income_manual 
            WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?
        ");
        $stmt->execute([$m, $tahun]);
        $row_man = $stmt->fetch(PDO::FETCH_ASSOC);
        $man = $row_man['total'];
        $jml_man = $row_man['jml'];

        $total = $inv + $man;
        $jml = $jml_inv + $jml_man;

        fputcsv($output, [
            $nama_bulan[$m-1],
            $inv,
            $man,
            $total,
            $jml
        ]);

        $grand_inv += $inv;
        $grand_man += $man;
        $grand_jml += $jml;
    }

    fputcsv($output, []);
    fputcsv($output, ['TOTAL', $grand_inv, $grand_man, $grand_inv + $grand_man, $grand_jml]);
    fputcsv($output, []);
    fputcsv($output, ['Dicetak', date('d-m-Y H:i:s')]);
    fclose($output);
    exit;
}

// =============================================================
// MODE HARIAN / BULANAN — Daftar transaksi
// =============================================================
$sql = "
    SELECT 
        'Invoice' AS sumber, 
        i.tgl_bayar AS tanggal, 
        CONVERT(COALESCE(p.id_pelanggan, '-') USING utf8mb4) COLLATE utf8mb4_general_ci AS id_pelanggan, 
        CONVERT(COALESCE(p.nama_lengkap, '-') USING utf8mb4) COLLATE utf8mb4_general_ci AS nama_lengkap, 
        CONVERT(i.no_invoice USING utf8mb4) COLLATE utf8mb4_general_ci AS referensi, 
        i.jumlah
    FROM invoice i
    LEFT JOIN pelanggan p ON p.id = i.pelanggan_id
    WHERE i.status = 'paid' AND DATE(i.tgl_bayar) BETWEEN ? AND ?
    UNION ALL
    SELECT 
        'Manual' AS sumber, 
        im.tanggal, 
        CONVERT('-' USING utf8mb4) COLLATE utf8mb4_general_ci AS id_pelanggan, 
        CONVERT(im.keterangan USING utf8mb4) COLLATE utf8mb4_general_ci AS nama_lengkap, 
        CONVERT(im.kategori USING utf8mb4) COLLATE utf8mb4_general_ci AS referensi, 
        im.jumlah
    FROM income_manual im
    WHERE DATE(im.tanggal) BETWEEN ? AND ?
    ORDER BY tanggal DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dari, $sampai, $dari, $sampai]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

fputcsv($output, ['LAPORAN PENDAPATAN']);
fputcsv($output, ['Periode', $label]);
fputcsv($output, ['Dari', $dari]);
fputcsv($output, ['Sampai', $sampai]);
fputcsv($output, []);
fputcsv($output, ['No', 'Sumber', 'Tanggal', 'ID Pelanggan', 'Nama / Keterangan', 'Referensi', 'Jumlah']);

$no = 1;
$total = 0;
foreach ($data as $row) {
    fputcsv($output, [
        $no++,
        $row['sumber'],
        $row['tanggal'],
        $row['id_pelanggan'],
        $row['nama_lengkap'],
        $row['referensi'],
        $row['jumlah']
    ]);
    $total += $row['jumlah'];
}

fputcsv($output, []);
fputcsv($output, ['', '', '', '', '', 'TOTAL', $total]);
fputcsv($output, []);
fputcsv($output, ['Dicetak', date('d-m-Y H:i:s')]);

fclose($output);
exit;
