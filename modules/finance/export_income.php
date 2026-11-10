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

// Profil perusahaan
$stmt_company = $pdo->query("SELECT key_setting, value FROM pengaturan");
$company = [];
foreach ($stmt_company->fetchAll() as $row) {
    $company[$row['key_setting']] = $row['value'];
}
$company_name    = $company['company_name'] ?? 'Klasnet';
$company_tagline = $company['company_tagline'] ?? '';
$company_address = $company['company_address'] ?? '';
$company_phone   = $company['company_phone'] ?? '';
$company_logo    = $company['company_logo'] ?? '';
$logo_data = '';
if (!empty($company_logo)) {
    $logo_file = __DIR__ . '/../../' . $company_logo;
    if (file_exists($logo_file)) {
        $mime = mime_content_type($logo_file);
        $logo_data = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logo_file));
    }
}

$html = '<style>
    body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
    h2 { margin: 0; color: #0d5c1e; }
    .header { text-align: center; border-bottom: 2px solid #0d5c1e; padding-bottom: 8px; margin-bottom: 10px; }
    .header small { color: #666; }
    .info { margin-bottom: 10px; }
    .info table { font-size: 11px; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.data th { background: #0d5c1e; color: white; padding: 6px; text-align: left; font-size: 10px; }
    table.data td { padding: 5px 6px; border-bottom: 1px solid #ddd; font-size: 10px; }
    table.data tr:nth-child(even) td { background: #f9f9f9; }
    .text-right { text-align: right; }
    .total-row td { font-weight: bold; background: #e8f5e9 !important; }
    .footer { margin-top: 15px; font-size: 9px; color: #999; text-align: right; }
</style>';

$html .= '<div class="header" style="position:relative; width:100%; border-bottom:2px solid #0d5c1e; padding-bottom:8px; margin-bottom:10px; min-height:0;">';
if ($logo_data) {
    $html .= '<img src="' . $logo_data . '" style="position:absolute; left:0; top:0; max-height:70px; max-width:80px;">';
}
$html .= '<div style="width:100%; text-align:center;">';
$html .= '<h2>' . htmlspecialchars($company_name) . '</h2>';
if ($company_tagline) $html .= '<small style="color:#666;">' . htmlspecialchars($company_tagline) . '</small><br>';
if ($company_address) $html .= '<small style="color:#666;">' . htmlspecialchars($company_address) . '</small><br>';
if ($company_phone) $html .= '<small style="color:#666;">Telp/WA: ' . htmlspecialchars($company_phone) . '</small>';
$html .= '</div>';
$html .= '</div>';

$html .= '<h3 style="margin:5px 0;">LAPORAN PENDAPATAN ' . strtoupper($mode) . '</h3>';
$html .= '<div class="info"><table>';
$html .= '<tr><td width="80">Periode</td><td>: ' . htmlspecialchars($label) . '</td></tr>';
$html .= '<tr><td>Dari</td><td>: ' . htmlspecialchars($dari) . '</td></tr>';
$html .= '<tr><td>Sampai</td><td>: ' . htmlspecialchars($sampai) . '</td></tr>';
$html .= '</table></div>';

// =============================================================
// MODE TAHUNAN — Rekap per bulan
// =============================================================
if ($mode === 'tahunan') {
    $nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    $html .= '<table class="data"><thead><tr>';
    $html .= '<th>Bulan</th><th class="text-right">Pendapatan Invoice</th><th class="text-right">Pendapatan Manual</th><th class="text-right">Total Pendapatan</th><th class="text-right">Jumlah Transaksi</th>';
    $html .= '</tr></thead><tbody>';

    $grand_inv = 0; $grand_man = 0; $grand_jml = 0;

    for ($m = 1; $m <= 12; $m++) {
        // Invoice per bulan
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total, COUNT(*) AS jml FROM invoice WHERE status = 'paid' AND MONTH(tgl_bayar) = ? AND YEAR(tgl_bayar) = ?");
        $stmt->execute([$m, $tahun]);
        $inv = $stmt->fetch();
        $total_inv = (float)$inv['total'];
        $jml_inv = (int)$inv['jml'];

        // Manual per bulan
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) AS total, COUNT(*) AS jml FROM income_manual WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
        $stmt->execute([$m, $tahun]);
        $man = $stmt->fetch();
        $total_man = (float)$man['total'];
        $jml_man = (int)$man['jml'];

        $total_bulan = $total_inv + $total_man;
        $jml_bulan = $jml_inv + $jml_man;

        $grand_inv += $total_inv;
        $grand_man += $total_man;
        $grand_jml += $jml_bulan;

        $html .= '<tr>';
        $html .= '<td>' . $nama_bulan[$m-1] . '</td>';
        $html .= '<td class="text-right">Rp ' . number_format($total_inv, 0, ',', '.') . '</td>';
        $html .= '<td class="text-right">Rp ' . number_format($total_man, 0, ',', '.') . '</td>';
        $html .= '<td class="text-right"><strong>Rp ' . number_format($total_bulan, 0, ',', '.') . '</strong></td>';
        $html .= '<td class="text-right">' . $jml_bulan . '</td>';
        $html .= '</tr>';
    }

    $grand_total = $grand_inv + $grand_man;
    $html .= '<tr class="total-row">';
    $html .= '<td><strong>TOTAL</strong></td>';
    $html .= '<td class="text-right">Rp ' . number_format($grand_inv, 0, ',', '.') . '</td>';
    $html .= '<td class="text-right">Rp ' . number_format($grand_man, 0, ',', '.') . '</td>';
    $html .= '<td class="text-right">Rp ' . number_format($grand_total, 0, ',', '.') . '</td>';
    $html .= '<td class="text-right">' . $grand_jml . '</td>';
    $html .= '</tr>';

    $html .= '</tbody></table>';
} else {
    // =============================================================
    // MODE HARIAN / BULANAN — Daftar transaksi + Diterima Oleh
    // =============================================================
    $sql = "
        SELECT
            'Invoice' AS sumber,
            i.tgl_bayar AS tanggal,
            CONVERT(COALESCE(p.id_pelanggan, '-') USING utf8mb4) COLLATE utf8mb4_general_ci AS id_pelanggan,
            CONVERT(COALESCE(p.nama_lengkap, '-') USING utf8mb4) COLLATE utf8mb4_general_ci AS nama_lengkap,
            CONVERT(i.no_invoice USING utf8mb4) COLLATE utf8mb4_general_ci AS referensi,
            i.jumlah,
            COALESCE(i.metode_bayar, '-') AS metode_bayar,
            COALESCE(i.paid_by, '-') AS diterima_oleh
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
            im.jumlah,
            'MANUAL' AS metode_bayar,
            '-' AS diterima_oleh
        FROM income_manual im
        WHERE DATE(im.tanggal) BETWEEN ? AND ?
        ORDER BY tanggal DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$dari, $sampai, $dari, $sampai]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html .= '<table class="data"><thead><tr>';
    $html .= '<th width="25">No</th><th>Sumber</th><th>Tanggal</th><th>ID Pelanggan</th><th>Nama / Keterangan</th><th>Referensi</th><th class="text-right">Jumlah</th><th>Metode</th><th>Diterima Oleh</th>';
    $html .= '</tr></thead><tbody>';

    $no = 1;
    $total = 0;
    foreach ($data as $row) {
        $html .= '<tr>';
        $html .= '<td>' . $no++ . '</td>';
        $html .= '<td>' . htmlspecialchars($row['sumber']) . '</td>';
        $html .= '<td>' . date('d-m-Y H:i', strtotime($row['tanggal'])) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['id_pelanggan']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['nama_lengkap']) . '</td>';
        $html .= '<td>' . htmlspecialchars($row['referensi']) . '</td>';
        $html .= '<td class="text-right">Rp ' . number_format($row['jumlah'], 0, ',', '.') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['metode_bayar'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($row['diterima_oleh']) . '</td>';
        $html .= '</tr>';
        $total += $row['jumlah'];
    }

    $html .= '<tr class="total-row">';
    $html .= '<td colspan="6" class="text-right"><strong>TOTAL</strong></td>';
    $html .= '<td class="text-right">Rp ' . number_format($total, 0, ',', '.') . '</td>';
    $html .= '<td></td>';
    $html .= '<td></td>';
    $html .= '</tr>';

    $html .= '</tbody></table>';
}

$html .= '<div class="footer">Dicetak: ' . date('d-m-Y H:i:s') . '</div>';

// Render PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = "income_" . $mode . "_" . $dari . "_sd_" . $sampai . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;
