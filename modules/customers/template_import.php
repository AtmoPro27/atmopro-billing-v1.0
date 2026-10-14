<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$filename = 'template_import_pelanggan.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

fputcsv($output, [
    'ID Pelanggan',
    'Nama Lengkap',
    'Alamat',
    'No Telepon',
    'Tipe Pelanggan',
    'Paket',
    'Jatuh Tempo',
    'Status'
]);

fputcsv($output, ['PEL001', 'Contoh Pelanggan', 'Jl. Contoh No. 123', '08123456789', 'reguler', 'SMART_150_BARAT', '20-11-2026', 'Aktif']);
fputcsv($output, ['PEL002', 'Contoh VIP', 'Jl. Contoh No. 456', '08123456780', 'vip', 'SMART_200_TIMUR', '20-11-2026', 'Aktif']);

fputcsv($output, []);
fputcsv($output, ['Catatan:']);
fputcsv($output, ['- ID Pelanggan dan Nama Lengkap wajib diisi']);
fputcsv($output, ['- Tipe Pelanggan: reguler, vip, fasum, nardi']);
fputcsv($output, ['- Paket: harus sesuai nama paket di sistem']);
fputcsv($output, ['- Status: Aktif, Tidak Aktif, Diblokir']);
fputcsv($output, ['- Format tanggal Jatuh Tempo: DD-MM-YYYY atau DD/MM/YYYY']);
fputcsv($output, ['- Kolom ke-9 dst akan diabaikan saat import']);

fclose($output);
exit();
?>
