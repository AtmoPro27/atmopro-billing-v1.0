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

$pdo = Database::getInstance()->getConnection();

// Filter bulan & tahun (default: bulan berjalan)
$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');

// Filter status (opsional)
$filter_status = $_GET['status'] ?? 'all';
if (!in_array($filter_status, ['all', 'paid', 'pending', 'cancelled'], true)) {
    $filter_status = 'all';
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

// Query: filter berdasarkan tgl_bayar (bulan & tahun)
if ($filter_status == 'paid') {
    $sql = "SELECT i.*, p.nama_lengkap, p.id_pelanggan, p.alamat
            FROM invoice i
            JOIN pelanggan p ON i.pelanggan_id = p.id
            WHERE i.status = 'paid' AND MONTH(i.tgl_bayar) = ? AND YEAR(i.tgl_bayar) = ?
            ORDER BY p.id_pelanggan ASC, i.id ASC";
    $params = [$bulan, $tahun];
} elseif ($filter_status == 'pending') {
    $sql = "SELECT i.*, p.nama_lengkap, p.id_pelanggan, p.alamat
            FROM invoice i
            JOIN pelanggan p ON i.pelanggan_id = p.id
            WHERE i.status = 'pending'
            ORDER BY p.id_pelanggan ASC, i.id ASC";
    $params = [];
} elseif ($filter_status == 'cancelled') {
    $sql = "SELECT i.*, p.nama_lengkap, p.id_pelanggan, p.alamat
            FROM invoice i
            JOIN pelanggan p ON i.pelanggan_id = p.id
            WHERE i.status = 'cancelled'
            ORDER BY p.id_pelanggan ASC, i.id ASC";
    $params = [];
} else {
    $sql = "SELECT i.*, p.nama_lengkap, p.id_pelanggan, p.alamat
            FROM invoice i
            JOIN pelanggan p ON i.pelanggan_id = p.id
            WHERE (i.status = 'paid' AND MONTH(i.tgl_bayar) = ? AND YEAR(i.tgl_bayar) = ?)
               OR (i.status = 'pending' AND i.tgl_jatuh_tempo <= LAST_DAY(CONCAT(?, '-', ?, '-01')))
            ORDER BY p.id_pelanggan ASC, i.id ASC";
    $params = [$bulan, $tahun, $tahun, $bulan];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$label_bulan = $nama_bulan[$bulan - 1] . ' ' . $tahun;
$label_status = [
    'all' => 'Semua Status',
    'paid' => 'Lunas',
    'pending' => 'Pending',
    'cancelled' => 'Dibatalkan',
][$filter_status];

$html = '
<!DOCTYPE html>
<html>
<head>
<style>
    @page { margin: 8mm; }
    body { font-family: Arial, sans-serif; font-size: 9px; }
    .kop { position: relative; text-align: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 10px; min-height: 55px; }
    .kop h2 { margin: 0; font-size: 14px; }
    .kop small { font-size: 8px; color: #333; }
    .logo { position: absolute; left: 0; top: 0; max-height: 50px; max-width: 60px; }
    .kop-text { text-align: center; }
    h3 { text-align: center; margin: 8px 0; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 3px 4px; }
    th { background: #4e4376; color: #fff; font-size: 8px; }
    td.num { text-align: right; }
    td.center { text-align: center; }
    .footer { margin-top: 10px; font-size: 8px; text-align: center; color: #666; border-top: 1px solid #ccc; padding-top: 5px; }
</style>
</head>
<body>
    <div class="kop">
        ' . ($logo_path ? '<img src="' . $logo_path . '" class="logo">' : '') . '
        <div class="kop-text">
            <h2>' . htmlspecialchars($company_name) . '</h2>
            <small>' . htmlspecialchars($company_address) . '</small><br>
            <small>Telp: ' . htmlspecialchars($company_phone) . '</small>
        </div>
    </div>

    <h3>DAFTAR TAGIHAN' . ($filter_status != 'all' ? ' ' . strtoupper($label_status) : '') . ' - ' . htmlspecialchars($label_bulan) . '</h3>

    <table>
        <thead>
            <tr>
                <th width="3%">No</th>
                <th width="10%">ID Pelanggan</th>
                <th width="14%">Nama Pelanggan</th>
                <th width="16%">Alamat</th>
                <th width="8%">Jatuh Tempo</th>
                <th width="8%">Tgl Bayar</th>
                <th width="9%">Metode</th>
                <th width="10%">Penerima</th>
                <th width="11%">Nominal Tagihan</th>
                <th width="11%">Nominal Lunas</th>
            </tr>
        </thead>
        <tbody>';
$no = 1;
$total_pending = 0;
$total_lunas = 0;
$jml_pending = 0;
$jml_lunas = 0;
$today = date('Y-m-d');
foreach ($invoices as $inv) {
    $tgl_bayar = (!empty($inv['tgl_bayar']) && $inv['tgl_bayar'] != '0000-00-00 00:00:00')
        ? date('d-m-Y', strtotime($inv['tgl_bayar']))
        : '-';
    $tgl_tempo = (!empty($inv['tgl_jatuh_tempo']) && $inv['tgl_jatuh_tempo'] != '0000-00-00 00:00:00')
        ? date('d-m-Y', strtotime($inv['tgl_jatuh_tempo']))
        : '-';
    $metode = !empty($inv['metode_bayar']) ? $inv['metode_bayar'] : '-';
    $penerima = !empty($inv['paid_by']) ? $inv['paid_by'] : '-';

    if ($inv['status'] == 'pending') {
        $is_terlambat = (!empty($inv['tgl_jatuh_tempo']) && $inv['tgl_jatuh_tempo'] < $today);
        $warna = $is_terlambat ? ' style="color:red; font-weight:bold;"' : '';
        $nom_tagihan = '<span' . $warna . '>Rp ' . number_format($inv['jumlah'], 0, ',', '.') . '</span>';
        $nom_lunas = '-';
        $total_pending += (float)$inv['jumlah'];
        $jml_pending++;
    } else {
        $nom_tagihan = '-';
        $nom_lunas = '<span style="color:green; font-weight:bold;">Rp ' . number_format($inv['jumlah'], 0, ',', '.') . '</span>';
        $total_lunas += (float)$inv['jumlah'];
        $jml_lunas++;
    }

    $html .= '
            <tr>
                <td class="center">' . $no++ . '</td>
                <td>' . htmlspecialchars($inv['id_pelanggan']) . '</td>
                <td>' . htmlspecialchars($inv['nama_lengkap']) . '</td>
                <td>' . htmlspecialchars($inv['alamat']) . '</td>
                <td class="center">' . $tgl_tempo . '</td>
                <td class="center">' . $tgl_bayar . '</td>
                <td class="center">' . htmlspecialchars($metode) . '</td>
                <td>' . htmlspecialchars($penerima) . '</td>
                <td class="num">' . $nom_tagihan . '</td>
                <td class="num">' . $nom_lunas . '</td>
            </tr>';
}

$html .= '
            <tr>
                <td colspan="8" class="num" style="font-weight:bold; background:#f0f0f0;">PENDING: ' . $jml_pending . ' pelanggan</td>
                <td class="num" style="font-weight:bold; background:#f0f0f0; color:red;">Rp ' . number_format($total_pending, 0, ',', '.') . '</td>
                <td class="num" style="background:#f0f0f0;">-</td>
            </tr>
            <tr>
                <td colspan="8" class="num" style="font-weight:bold; background:#f0f0f0;">LUNAS: ' . $jml_lunas . ' pelanggan</td>
                <td class="num" style="background:#f0f0f0;">-</td>
                <td class="num" style="font-weight:bold; background:#f0f0f0; color:green;">Rp ' . number_format($total_lunas, 0, ',', '.') . '</td>
            </tr>
        </tbody>
    </table>
    <div class="footer">
        Dicetak: ' . date('d-m-Y H:i:s') . ' | Dicetak oleh: ' . htmlspecialchars($_SESSION['user_name'] ?? 'User') . '<br>
        ' . htmlspecialchars($company_name) . ' &copy; ' . date('Y') . '
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/../../'));
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'landscape');
$dompdf->loadHtml($html);
$dompdf->render();
$dompdf->stream('daftar_tagihan_' . $tahun . '_' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '.pdf', ['Attachment' => false]);
