<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak");
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = Database::getInstance()->getConnection();

$dari = $_GET['dari'] ?? date('Y-01-01');
$sampai = $_GET['sampai'] ?? date('Y-12-31');
$tahun = date('Y', strtotime($dari));

// Saldo awal tahun
$stmt_saldo = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'saldo_awal_tahun'");
$saldo_awal = (int)($stmt_saldo->fetchColumn() ?: 0);

// Profil perusahaan (logo, nama, tagline, alamat, telp)
$stmt_company = $pdo->query("SELECT key_setting, value FROM pengaturan");
$company = [];
foreach ($stmt_company->fetchAll() as $row) {
    $company[$row['key_setting']] = $row['value'];
}
$company_name    = $company['company_name'] ?? 'AtmoBilling';
$company_tagline = $company['company_tagline'] ?? '';
$company_address = $company['company_address'] ?? '';
$company_phone   = $company['company_phone'] ?? '';
$company_logo    = $company['company_logo'] ?? '';
$logo_path = (!empty($company_logo) && file_exists(__DIR__ . '/../../' . $company_logo))
    ? realpath(__DIR__ . '/../../' . $company_logo)
    : '';


// Ambil data per bulan
$nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$data = [];
$grand_inv = 0;
$grand_man = 0;
$grand_peng = 0;
$saldo_kumulatif = $saldo_awal;

for ($m = 1; $m <= 12; $m++) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM invoice WHERE status = 'paid' AND MONTH(tgl_bayar) = ? AND YEAR(tgl_bayar) = ?");
    $stmt->execute([$m, $tahun]);
    $inv = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM income_manual WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt->execute([$m, $tahun]);
    $man = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total FROM pengeluaran WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt->execute([$m, $tahun]);
    $peng = (float) $stmt->fetchColumn();

    $total_income = $inv + $man;
    $laba_rugi = $total_income - $peng;
    $saldo_kumulatif += $laba_rugi;

    $data[] = [
        'bulan' => $nama_bulan[$m-1],
        'inv' => $inv,
        'man' => $man,
        'income' => $total_income,
        'peng' => $peng,
        'laba' => $laba_rugi,
        'saldo' => $saldo_kumulatif,
    ];

    $grand_inv += $inv;
    $grand_man += $man;
    $grand_peng += $peng;
}

$grand_income = $grand_inv + $grand_man;
$grand_laba = $grand_income - $grand_peng;

// HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        h2 { text-align: center; margin-bottom: 5px; }
        h4 { text-align: center; margin-top: 0; color: #666; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 5px; }
        th { background-color: #4e4376; color: white; }
        td.num { text-align: right; }
        tr.total { background-color: #d4edda; font-weight: bold; }
        tr.saldo-awal { background-color: #d1ecf1; font-weight: bold; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .footer { text-align: center; margin-top: 20px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="kop" style="position:relative; width:100%; border-bottom:3px double #4e4376; padding-bottom:8px; margin-bottom:10px; min-height:0;">
        ' . ($logo_path ? '<img src="' . $logo_path . '" style="position:absolute; left:0; top:0; max-height:70px; max-width:80px;">' : '') . '
        <div style="width:100%; text-align:center;">
            <h2 style="margin:0;">' . htmlspecialchars($company_name) . '</h2>
            <small style="color:#666;">' . htmlspecialchars($company_tagline) . '</small><br>
            <small style="color:#666;">' . htmlspecialchars($company_address) . '</small><br>
            <small style="color:#666;">Telp/WA: ' . htmlspecialchars($company_phone) . '</small>
        </div>
    </div>
    <h2>LAPORAN LABA RUGI</h2>
    <h4>Tahun ' . $tahun . ' | Periode ' . date('d-m-Y', strtotime($dari)) . ' s/d ' . date('d-m-Y', strtotime($sampai)) . '</h4>

    <table>
        <thead>
            <tr>
                <th>Bulan</th>
                <th>Pendapatan Invoice</th>
                <th>Pendapatan Manual</th>
                <th>Total Pendapatan</th>
                <th>Total Pengeluaran</th>
                <th>Laba / Rugi</th>
                <th>Saldo Kumulatif</th>
            </tr>
        </thead>
        <tbody>
            <tr class="saldo-awal">
                <td>Saldo Tahun Lalu</td>
                <td class="num">-</td>
                <td class="num">-</td>
                <td class="num">-</td>
                <td class="num">-</td>
                <td class="num">-</td>
                <td class="num">' . number_format($saldo_awal, 0, ',', '.') . '</td>
            </tr>';

foreach ($data as $row) {
    $laba_class = $row['laba'] >= 0 ? 'text-success' : 'text-danger';
    $html .= '
            <tr>
                <td>' . $row['bulan'] . '</td>
                <td class="num">' . number_format($row['inv'], 0, ',', '.') . '</td>
                <td class="num">' . number_format($row['man'], 0, ',', '.') . '</td>
                <td class="num">' . number_format($row['income'], 0, ',', '.') . '</td>
                <td class="num">' . number_format($row['peng'], 0, ',', '.') . '</td>
                <td class="num ' . $laba_class . '">' . number_format($row['laba'], 0, ',', '.') . '</td>
                <td class="num">' . number_format($row['saldo'], 0, ',', '.') . '</td>
            </tr>';
}

$html .= '
            <tr class="total">
                <td>TOTAL</td>
                <td class="num">' . number_format($grand_inv, 0, ',', '.') . '</td>
                <td class="num">' . number_format($grand_man, 0, ',', '.') . '</td>
                <td class="num">' . number_format($grand_income, 0, ',', '.') . '</td>
                <td class="num">' . number_format($grand_peng, 0, ',', '.') . '</td>
                <td class="num">' . number_format($grand_laba, 0, ',', '.') . '</td>
                <td class="num">' . number_format($saldo_kumulatif, 0, ',', '.') . '</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Dicetak: ' . date('d-m-Y H:i:s') . '
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
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('laba_rugi_' . $dari . '_sd_' . $sampai . '.pdf', ['Attachment' => false]);
