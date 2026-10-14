<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak");
}
require_once __DIR__ . '/../../config/database.php';

$pdo = Database::getInstance()->getConnection();

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');
$jenis = $_GET['jenis'] ?? '';

$sql = "SELECT p.id, p.tanggal, p.jenis, p.jumlah, p.keterangan FROM pengeluaran p WHERE DATE(p.tanggal) BETWEEN ? AND ?";
$params = [$dari, $sampai];

if (!empty($jenis)) {
    $sql .= " AND p.jenis = ?";
    $params[] = $jenis;
}
$sql .= " ORDER BY p.tanggal DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "pengeluaran_" . $dari . "_sd_" . $sampai . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['LAPORAN PENGELUARAN']);
fputcsv($output, ['Periode', $dari . ' s/d ' . $sampai]);
fputcsv($output, ['Jenis', $jenis ?: 'Semua']);
fputcsv($output, []);
fputcsv($output, ['No', 'Tanggal', 'Jenis', 'Jumlah', 'Keterangan']);

$no = 1;
$total = 0;
foreach ($data as $row) {
    fputcsv($output, [$no++, $row['tanggal'], $row['jenis'], $row['jumlah'], $row['keterangan']]);
    $total += $row['jumlah'];
}

fputcsv($output, []);
fputcsv($output, ['', '', 'TOTAL', $total, '']);
fputcsv($output, []);
fputcsv($output, ['Dicetak', date('d-m-Y H:i:s')]);

fclose($output);
exit;
