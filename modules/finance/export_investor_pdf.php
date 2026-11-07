<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak");

if (!in_array($_SESSION['user_role'] ?? '', ['super_admin', 'kasir'], true)) {
    http_response_code(403);
    die("Akses ditolak: halaman ini hanya untuk Super Admin dan Kasir.");
}
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = Database::getInstance()->getConnection();

$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

$nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$nama_bulan_ini = $nama_bulan[$bulan - 1];

// ============================================================
// A. INCOME
// ============================================================

// 1. Saldo Bulan Lalu (saldo awal tahun + laba kumulatif sebelum bulan ini)
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'saldo_awal_tahun'");
$saldo_awal_tahun = (int)($stmt->fetchColumn() ?: 0);

$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM invoice WHERE status = 'paid' AND YEAR(tgl_bayar) = ? AND MONTH(tgl_bayar) < ?");
$stmt->execute([$tahun, $bulan]);
$income_wifi_sebelum = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM income_manual WHERE YEAR(tanggal) = ? AND MONTH(tanggal) < ?");
$stmt->execute([$tahun, $bulan]);
$income_manual_sebelum = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM pengeluaran WHERE YEAR(tanggal) = ? AND MONTH(tanggal) < ?");
$stmt->execute([$tahun, $bulan]);
$pengeluaran_sebelum = (float) $stmt->fetchColumn();

$laba_kumulatif_sebelum = ($income_wifi_sebelum + $income_manual_sebelum) - $pengeluaran_sebelum;
$saldo_bulan_lalu = $saldo_awal_tahun + $laba_kumulatif_sebelum;

// 2. Pelanggan Reguler (Invoice WiFi bulan ini)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM invoice WHERE status = 'paid' AND MONTH(tgl_bayar) = ? AND YEAR(tgl_bayar) = ?");
$stmt->execute([$bulan, $tahun]);
$income_wifi = (float) $stmt->fetchColumn();

// 3. Income Manual
$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM income_manual WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmt->execute([$bulan, $tahun]);
$income_manual = (float) $stmt->fetchColumn();

// Total Income
$total_income = $saldo_bulan_lalu + $income_wifi + $income_manual;

// ============================================================
// B. PENGELUARAN
// ============================================================

// 1. Biaya Rutin (detail per item)
$stmt = $pdo->prepare("
    SELECT keterangan, jumlah FROM pengeluaran 
    WHERE jenis = 'rutin' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ORDER BY id ASC
");
$stmt->execute([$bulan, $tahun]);
$rutin_items = $stmt->fetchAll();
$total_rutin = 0;
foreach ($rutin_items as $item) {
    $total_rutin += (float) $item['jumlah'];
}

// 2. Zakat
$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM pengeluaran WHERE jenis = 'zakat' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmt->execute([$bulan, $tahun]);
$zakat = (float) $stmt->fetchColumn();

// 3. Bagi Hasil
$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM pengeluaran WHERE jenis = 'bagi_hasil' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmt->execute([$bulan, $tahun]);
$bagi_hasil = (float) $stmt->fetchColumn();

// 3b. Detail Bagi Hasil per item
$stmt = $pdo->prepare("SELECT keterangan, jumlah FROM pengeluaran
    WHERE jenis = 'bagi_hasil' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ORDER BY tanggal");
$stmt->execute([$bulan, $tahun]);
$bagi_hasil_items = $stmt->fetchAll();

// 4. PSB
$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM pengeluaran WHERE jenis = 'psb' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmt->execute([$bulan, $tahun]);
$psb = (float) $stmt->fetchColumn();

// 4b. Detail PSB per item
$stmt = $pdo->prepare("SELECT keterangan, jumlah FROM pengeluaran
    WHERE jenis = 'psb' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ORDER BY tanggal");
$stmt->execute([$bulan, $tahun]);
$psb_items = $stmt->fetchAll();

// 5. Maintenance
$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM pengeluaran WHERE jenis = 'maintenance' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmt->execute([$bulan, $tahun]);
$maintenance = (float) $stmt->fetchColumn();

// 5b. Detail Maintenance per item
$stmt = $pdo->prepare("SELECT keterangan, jumlah FROM pengeluaran
    WHERE jenis = 'maintenance' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ORDER BY tanggal");
$stmt->execute([$bulan, $tahun]);
$maintenance_items = $stmt->fetchAll();

// Total Pengeluaran
$total_pengeluaran = $total_rutin + $zakat + $bagi_hasil + $psb + $maintenance;

// ============================================================
// C. SALDO AKHIR
// ============================================================
$saldo_akhir = $total_income - $total_pengeluaran;

// ============================================================
// NAMA PERUSAHAAN
// ============================================================
$stmt = $pdo->query("SELECT key_setting, value FROM pengaturan");
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['key_setting']] = $row['value'];
}
$company_name = $settings['company_name'] ?? 'AtmoBilling';
$company_tagline = $settings['company_tagline'] ?? 'High Speed Internet Connection';
$company_address = $settings['company_address'] ?? '';
$company_phone = $settings['company_phone'] ?? '';
$company_email = $settings['company_email'] ?? '';
$company_logo = $settings['company_logo'] ?? '';
$logo_path = (!empty($company_logo) && file_exists(__DIR__ . '/../../' . $company_logo))
    ? __DIR__ . '/../../' . $company_logo
    : '';

// ============================================================
// HTML UNTUK PDF
// ============================================================
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        @page { margin: 60px 50px 20px 50px; }
        body { font-family: Arial, sans-serif; font-size: 10px; }
        .header { text-align: center; border-bottom: 3px double #0d5c1e; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #0d5c1e; }
        .header h3 { margin: 5px 0; color: #333; }
        .header small { color: #666; }
        .periode { text-align: center; font-size: 14px; font-weight: bold; margin: 15px 0; padding: 10px; background: #f0f0f0; border-radius: 5px; }
        .section-title { font-weight: bold; color: #fff; background: #0d5c1e; padding: 8px 12px; margin-top: 20px; margin-bottom: 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        td { padding: 4px 6px; border-bottom: 1px solid #eee; }
        td.label { width: 70%; }
        td.num { width: 30%; text-align: right; }
        td.subtotal { font-weight: bold; border-top: 2px solid #333; background: #f8f9fa; }
        td.total { font-weight: bold; font-size: 14px; background: #e8f5e9; border: 2px solid #0d5c1e; }
        td.saldo { font-weight: bold; font-size: 16px; background: #fff3cd; border: 2px solid #f39c12; }
        td.item { padding-left: 15px; font-size: 9px; color: #555; }
        .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
    </style>
</head>
<body>
    <div class="header" style="position:relative; width:100%;">
        ' . ($logo_path ? '<img src="' . $logo_path . '" style="position:absolute; left:0; top:0; max-height:100px; max-width:110px;">' : '') . '
        <div style="width:100%; text-align:center;">
            <h2>' . htmlspecialchars($company_name) . '</h2>
            <h3>' . htmlspecialchars($company_tagline) . '</h3>
            <small>' . htmlspecialchars($company_address) . '</small><br>
            <small>Telp/WA: ' . htmlspecialchars($company_phone) . '</small>
        </div>
    </div>

    <div class="periode">
        LAPORAN KEUANGAN<br>
        Periode: ' . $nama_bulan_ini . ' ' . $tahun . '
    </div>

    <!-- A. INCOME -->
    <div class="section-title">A. INCOME</div>
    <table>
        <tr>
            <td class="label">1. Saldo Bulan Lalu</td>
            <td class="num">Rp ' . number_format($saldo_bulan_lalu, 0, ',', '.') . '</td>
        </tr>
        <tr>
            <td class="label">2. Pelanggan Reguler (Invoice WiFi)</td>
            <td class="num">Rp ' . number_format($income_wifi, 0, ',', '.') . '</td>
        </tr>
        <tr>
            <td class="label">3. Income Manual</td>
            <td class="num">Rp ' . number_format($income_manual, 0, ',', '.') . '</td>
        </tr>
        <tr>
            <td class="label subtotal">TOTAL INCOME (A)</td>
            <td class="num subtotal">Rp ' . number_format($total_income, 0, ',', '.') . '</td>
        </tr>
    </table>

    <!-- B. PENGELUARAN -->
    <div class="section-title">B. PENGELUARAN</div>
    <table>
        <tr>
            <td class="label"><strong>1. Biaya Rutin</strong></td>
            <td class="num"><strong>Rp ' . number_format($total_rutin, 0, ',', '.') . '</strong></td>
        </tr>';

if (count($rutin_items) > 0) {
    foreach ($rutin_items as $item) {
        $html .= '
        <tr>
            <td class="label item">- ' . htmlspecialchars($item['keterangan']) . '</td>
            <td class="num item">Rp ' . number_format($item['jumlah'], 0, ',', '.') . '</td>
        </tr>';
    }
} else {
    $html .= '
        <tr>
            <td class="label item" colspan="2"><em>(tidak ada biaya rutin bulan ini)</em></td>
        </tr>';
}

$html .= '
        <tr>
            <td class="label">2. PSB (Pasang Baru @Rp. 150.000)</td>
            <td class="num">Rp ' . number_format($psb, 0, ',', '.') . '</td>
        </tr>';

if (count($psb_items) > 0) {
    foreach ($psb_items as $item) {
        $html .= '
        <tr>
            <td class="label item">- ' . htmlspecialchars($item['keterangan']) . '</td>
            <td class="num item">Rp ' . number_format($item['jumlah'], 0, ',', '.') . '</td>
        </tr>';
    }
} else {
    $html .= '
        <tr>
            <td class="label item" colspan="2"><em>(tidak ada PSB bulan ini)</em></td>
        </tr>';
}

$html .= '
        <tr>
            <td class="label">3. Zakat 2,5%</td>
            <td class="num">Rp ' . number_format($zakat, 0, ',', '.') . '</td>
        </tr>
        <tr>
            <td class="label">4. Maintenance / Belanja</td>
            <td class="num">Rp ' . number_format($maintenance, 0, ',', '.') . '</td>
        </tr>';

if (count($maintenance_items) > 0) {
    foreach ($maintenance_items as $item) {
        $html .= '
        <tr>
            <td class="label item">- ' . htmlspecialchars($item['keterangan']) . '</td>
            <td class="num item">Rp ' . number_format($item['jumlah'], 0, ',', '.') . '</td>
        </tr>';
    }
} else {
    $html .= '
        <tr>
            <td class="label item" colspan="2"><em>(tidak ada maintenance bulan ini)</em></td>
        </tr>';
}

$html .= '
        <tr>
            <td class="label">5. Bagi Hasil</td>
            <td class="num">Rp ' . number_format($bagi_hasil, 0, ',', '.') . '</td>
        </tr>';

if (count($bagi_hasil_items) > 0) {
    foreach ($bagi_hasil_items as $item) {
        $html .= '
        <tr>
            <td class="label item">- ' . htmlspecialchars($item['keterangan']) . '</td>
            <td class="num item">Rp ' . number_format($item['jumlah'], 0, ',', '.') . '</td>
        </tr>';
    }
} else {
    $html .= '
        <tr>
            <td class="label item" colspan="2"><em>(tidak ada bagi hasil bulan ini)</em></td>
        </tr>';
}

$html .= '
        <tr>
            <td class="label subtotal">TOTAL PENGELUARAN (B)</td>
            <td class="num subtotal">Rp ' . number_format($total_pengeluaran, 0, ',', '.') . '</td>
        </tr>
    </table>

    <!-- C. SALDO -->
    <div class="section-title">C. SALDO</div>
    <table>
        <tr>
            <td class="label">Income (A) - Pengeluaran (B)</td>
            <td class="num">Rp ' . number_format($total_income, 0, ',', '.') . ' - Rp ' . number_format($total_pengeluaran, 0, ',', '.') . '</td>
        </tr>
        <tr>
            <td class="label saldo">SALDO AKHIR</td>
            <td class="num saldo ' . ($saldo_akhir >= 0 ? 'text-success' : 'text-danger') . '">
                Rp ' . number_format(abs($saldo_akhir), 0, ',', '.') . ($saldo_akhir < 0 ? ' (Minus)' : '') . '
            </td>
        </tr>
    </table>

    <div class="footer">
        Dicetak oleh: ' . htmlspecialchars($_SESSION['user_name'] ?? 'User') . ', ' . date('d-m-Y H:i:s') . ' | Laporan ini bersifat rahasia<br>
        ' . htmlspecialchars($company_name) . ' &copy; ' . date('Y') . '
    </div>
</body>
</html>';

// Generate PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/../../'));
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('LAPORAN KEUANGAN KLASNET ' . $nama_bulan_ini . ' ' . $tahun . '.pdf', ['Attachment' => false]);
