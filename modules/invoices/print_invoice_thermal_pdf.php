<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak");
}
if (!in_array($_SESSION['user_role'] ?? '', ['super_admin', 'kasir'], true)) {
    http_response_code(403);
    die("Akses ditolak: halaman ini hanya untuk Super Admin dan Kasir.");
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = $_GET['id'] ?? 0;
if (!$id) {
    die("ID invoice tidak valid");
}

$pdo = Database::getInstance()->getConnection();

// Ambil invoice
$stmt = $pdo->prepare("
    SELECT i.*, p.nama_lengkap, p.alamat, p.no_telepon, p.id_pelanggan,
           pk.nama_paket
    FROM invoice i
    JOIN pelanggan p ON i.pelanggan_id = p.id
    LEFT JOIN paket_internet pk ON p.paket_id = pk.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice tidak ditemukan");
}

// Ambil pengaturan perusahaan
$stmt = $pdo->query("SELECT key_setting, value FROM pengaturan");
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['key_setting']] = $row['value'];
}
$company_name    = $settings['company_name'] ?? 'AtmoBilling';
$company_tagline = $settings['company_tagline'] ?? '';
$company_address = $settings['company_address'] ?? '';
$company_phone   = $settings['company_phone'] ?? '';
$company_logo    = $settings['company_logo'] ?? '';

$logo_path = (!empty($company_logo) && file_exists(__DIR__ . '/../../' . $company_logo))
    ? realpath(__DIR__ . '/../../' . $company_logo)
    : '';

// Nomor WA kasir
$stmt_wa = $pdo->query("SELECT phone FROM users WHERE role = 'kasir' AND is_active = 1 AND phone IS NOT NULL AND phone != ''");
$wa_kasir_list = $stmt_wa->fetchAll(PDO::FETCH_COLUMN);
$no_wa_kasir = !empty($wa_kasir_list) ? implode(' / ', $wa_kasir_list) : ($settings['wa_number'] ?? '6282298419811');

// Format tanggal
$tgl_terbit  = !empty($invoice['tgl_terbit'])  ? date('d-m-Y', strtotime($invoice['tgl_terbit']))  : '-';
$tgl_tempo   = !empty($invoice['tgl_jatuh_tempo']) ? date('d-m-Y', strtotime($invoice['tgl_jatuh_tempo'])) : '-';
$tgl_bayar   = !empty($invoice['tgl_bayar'])    ? date('d-m-Y', strtotime($invoice['tgl_bayar']))    : '-';
$status_text = ($invoice['status'] == 'paid')
    ? 'LUNAS' . (!empty($invoice['metode_bayar']) ? ' / ' . $invoice['metode_bayar'] : '')
    : 'BELUM LUNAS';
$diterima    = !empty($invoice['paid_by']) ? $invoice['paid_by'] : '-';

$html = '
<!DOCTYPE html>
<html>
<head>
<style>
    @page { margin: 2mm; }
    body { font-family: Arial, sans-serif; font-size: 8px; margin: 0; padding: 14px 0 0 0; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .line { border-top: 1px dashed #000; margin: 3px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 1px 0; vertical-align: top; font-size: 8px; }
    .right { text-align: right; }
    .logo { max-height: 30px; max-width: 40px; }
    .small { font-size: 7px; color: #333; }
</style>
</head>
<body>
    <div class="center">
        ' . ($logo_path ? '<img src="' . $logo_path . '" class="logo"><br>' : '') . '
        <span class="bold" style="font-size:10px;">' . htmlspecialchars($company_name) . '</span><br>
        <span class="small">' . htmlspecialchars($company_address) . '</span><br>
        <span class="small">Telp: ' . htmlspecialchars($company_phone) . '</span>
    </div>
    <div class="line"></div>

    <table>
        <tr><td width="35%">No Invoice</td><td>: ' . htmlspecialchars($invoice['no_invoice']) . '</td></tr>
        <tr><td>Tgl Terbit</td><td>: ' . $tgl_terbit . '</td></tr>
        <tr><td>Jatuh Tempo</td><td>: ' . $tgl_tempo . '</td></tr>
        <tr><td>Nama</td><td>: ' . htmlspecialchars($invoice['nama_lengkap']) . '</td></tr>
        <tr><td>ID Pelanggan</td><td>: ' . htmlspecialchars($invoice['id_pelanggan']) . '</td></tr>
    </table>
    <div class="line"></div>

    <table>
        <tr><td width="60%">' . htmlspecialchars($invoice['nama_paket']) . '</td><td class="right">Rp ' . number_format($invoice['jumlah'], 0, ',', '.') . '</td></tr>
        <tr><td class="bold">TOTAL</td><td class="right bold">Rp ' . number_format($invoice['jumlah'], 0, ',', '.') . '</td></tr>
    </table>
    <div class="line"></div>

    <div class="center">
        Status: <span class="bold">' . $status_text . '</span>
        ' . ($invoice['status'] == 'paid' ? '<br><span class="small">Tgl Bayar: ' . $tgl_bayar . '</span>' : '') . '
        <br><span class="small">Diterima oleh: ' . htmlspecialchars($diterima) . '</span>
    </div>
    <div class="line"></div>

    <div class="center small">
    <div class="line"></div>
    <div class="center small" style="word-break: break-all;">
        Pembayaran / gangguan klik:<br>
        https://demo.atmopro.my.id/portal<br>
        Konfirmasi pembayaran:<br>
        ' . htmlspecialchars($no_wa_kasir) . '
    </div>
        ' . ($invoice['status'] == 'paid' ? 'Terima Kasih atas pembayaran Anda<br>' : '') . '
        Simpan resi ini sebagai bukti pembayaran<br>
        ' . htmlspecialchars($company_name) . ' - ' . htmlspecialchars($company_tagline) . '
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/../../'));
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
// 58mm = 164.4pt, tinggi 600pt (~21cm) dengan auto
$dompdf->setPaper([0, 0, 164.4, 230], 'portrait');
$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream('struk_' . $invoice['no_invoice'] . '.pdf', ['Attachment' => false]);
