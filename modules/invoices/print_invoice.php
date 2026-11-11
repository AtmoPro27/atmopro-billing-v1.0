<?php
require_once __DIR__ . '/../../includes/auth_check.php';

require_once __DIR__ . "/../../config/database.php";

$id = $_GET['id'] ?? 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

include '../../includes/header.php';

$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
    echo "<div class='alert alert-danger'>Invoice tidak ditemukan!</div>";
    include '../../includes/footer.php';
    exit();
}

$stmt = $pdo->query("SELECT key_setting, value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['key_setting']] = $row['value'];
}
$company_name = $settings['company_name'] ?? 'AtmoBilling';
$company_tagline = $settings['company_tagline'] ?? 'HIGH SPEED INTERNET CONNECTION';
$company_address = $settings['company_address'] ?? '';
$company_phone = $settings['company_phone'] ?? '';
$company_logo = $settings['company_logo'] ?? '';
$logo_url = !empty($company_logo) ? '/' . ltrim($company_logo, '/') : '';
// Ambil SEMUA kasir aktif
$stmt_wa = $pdo->query("SELECT phone FROM users WHERE role = 'kasir' AND is_active = 1 AND phone IS NOT NULL AND phone != ''");
$wa_kasir_list = $stmt_wa->fetchAll(PDO::FETCH_COLUMN);
$no_wa_kasir = !empty($wa_kasir_list) ? implode(' / ', $wa_kasir_list) : ($settings['wa_number'] ?? '6282298411981');

$tgl_terbit = date('d-m-Y', strtotime($invoice['tgl_terbit']));
$tgl_jatuh_tempo = date('d-m-Y', strtotime($invoice['tgl_jatuh_tempo']));
$tgl_bayar = $invoice['tgl_bayar'] ? date('d-m-Y', strtotime($invoice['tgl_bayar'])) : '-';

$stmt = $pdo->query("SELECT template_message FROM wa_templates WHERE template_name = 'invoice_paid'");
$wa_template = $stmt->fetchColumn();

if (!$wa_template) {
    $wa_template = "*TERIMA KASIH ATAS PEMBAYARAN ANDA*\n\n*{company_name}*\n\nKepada Yth. {customer_name}\n\nID Pelanggan: {customer_id}\n\nNo Invoice: {invoice_no}\nTanggal: {invoice_date}\nJatuh Tempo: {due_date}\nJumlah: {amount}\n\nStatus: LUNAS\nTgl Bayar: {payment_date}\n\n[KLIK SINI] {portal_url}\n\nKonfirmasi pembayaran via WA: {wa_number}\n\nTerima kasih telah menggunakan layanan kami.\n---\n{company_name} - {tagline}";
}

$wa_message = $wa_template;
$wa_message = str_replace("{company_name}", strtoupper($company_name), $wa_message);
$wa_message = str_replace("{customer_name}", $invoice['nama_lengkap'], $wa_message);
$wa_message = str_replace("{customer_id}", $invoice['id_pelanggan'], $wa_message);
$wa_message = str_replace("{invoice_no}", $invoice['no_invoice'], $wa_message);
$wa_message = str_replace("{invoice_date}", $tgl_terbit, $wa_message);
$wa_message = str_replace("{due_date}", $tgl_jatuh_tempo, $wa_message);
$wa_message = str_replace("{amount}", "Rp " . number_format($invoice['jumlah'], 0, ',', '.'), $wa_message);
$wa_message = str_replace("{payment_date}", $tgl_bayar, $wa_message);
$wa_message = str_replace("{paid_by}", $invoice['paid_by'] ?? '-', $wa_message);
$wa_message = str_replace("{metode_bayar}", $invoice['metode_bayar'] ?? '-', $wa_message);
$wa_message = str_replace("{portal_url}", "http://klasnet.autobill.my.id/portal", $wa_message);
$wa_message = str_replace("{wa_number}", $no_wa_kasir, $wa_message);
$wa_message = str_replace("{tagline}", $company_tagline, $wa_message);
$wa_message_json = json_encode($wa_message);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($company_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Default @page untuk A5 */
        @page {
            size: A5 portrait;
            margin: 3mm;
        }

        /* Sembunyikan sidebar dan header saat print */
        @media print {
            .sidebar, .navbar-top, .no-print, .footer, .user-dropdown, .menu-toggle {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            body {
                margin: 0;
                padding: 0;
            }
        }
        
        /* Style untuk PRINT NORMAL (A4) */
        @page {
            size: A4 portrait;
            margin: 5mm;
        }
        @media print {
            body.normal-print {
                margin: 0 !important;
                padding: 0 !important;
            }
            body.normal-print .invoice-box {
                margin: 0 auto !important;
                padding: 0 !important;
                width: 190mm !important;
                max-width: 190mm !important;
                font-size: 12px;
                border: none !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            body.normal-print .invoice-box table {
                width: 100% !important;
                max-width: 100% !important;
                table-layout: fixed;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            body.normal-print .invoice-box .table-bordered td,
            body.normal-print .invoice-box .table-bordered th {
                padding: 3px 4px;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            body.normal-print .invoice-box h2 {
                font-size: 16px;
                margin: 2px 0;
            }
            body.normal-print .invoice-box h3 {
                font-size: 14px;
                margin: 2px 0;
            }
            body.normal-print hr {
                margin: 4px 0;
            }
            body.normal-print * {
                box-sizing: border-box !important;
            }
            body.normal-print .text-center h3 {
                font-size: 16px !important;
            }
            body.normal-print .text-center h3 span {
                font-size: 20px !important;
            }
        }
        
        /* Style untuk PRINT TERMAL (58mm/80mm) */
        @media print {
            body.thermal-print .invoice-box {
                margin: 0 !important;
                padding: 5px !important;
                width: 100% !important;
                font-size: 10px;
                border: none;
            }
            body.thermal-print .invoice-box h2, 
            body.thermal-print .invoice-box h3 {
                font-size: 12px;
                margin: 3px 0;
            }
            body.thermal-print .invoice-box table {
                font-size: 9px;
                width: 100%;
            }
            body.thermal-print .invoice-box .table-bordered td,
            body.thermal-print .invoice-box .table-bordered th {
                padding: 2px;
            }
            body.thermal-print hr {
                margin: 5px 0;
            }
        }
        
        /* Style untuk PRINT A5 (1/2 A4) */
        @media print {
            body.a5-print .invoice-box {
                margin: 0 auto !important;
                padding: 1.5mm !important;
                width: 120mm !important;
                max-width: 120mm !important;
                font-size: 10.5px;
                line-height: 1.35;
                border: none !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }
            body.a5-print .invoice-box h2 {
                font-size: 14px;
                margin: 2px 0;
            }
            body.a5-print .invoice-box h3 {
                font-size: 12px;
                margin: 2px 0;
            }
            body.a5-print .invoice-box small {
                font-size: 9px;
            }
            body.a5-print .invoice-box table {
                font-size: 9.5px;
                width: 100% !important;
                max-width: 100% !important;
                table-layout: fixed;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            body.a5-print .invoice-box .table-bordered td,
            body.a5-print .invoice-box .table-bordered th {
                padding: 2px 3px;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            body.a5-print hr {
                margin: 4px 0;
            }
            body.a5-print * {
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            body.a5-print .text-center h3 {
                font-size: 15px !important;
            }
            body.a5-print .text-center h3 span {
                font-size: 18px !important;
            }
            body.a5-print td[align="right"] {
                text-align: right !important;
                white-space: nowrap;
            }
            body.a5-print th[align="right"] {
                text-align: right !important;
            }
        }

        /* Tampilan normal di layar */
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            font-size: 16px;
            line-height: 24px;
            background: #fff;
        }
        .btn-wa {
            background: #25D366;
            color: white;
        }
        .btn-wa:hover {
            background: #128C7E;
            color: white;
        }
        .btn-primary, .btn-info, .btn-wa, .btn-secondary {
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="container mt-3 no-print">
    <div class="text-center mb-3">
        <button type="button" class="btn btn-primary" onclick="printNormal();"><i class="fas fa-print"></i> Print Biasa (A4)</button>
        <button type="button" class="btn btn-info" onclick="printThermal(<?= $invoice['id'] ?>)"><i class="fas fa-receipt"></i> Print Termal (58mm)</button>
        <button type="button" class="btn btn-success" onclick="printA5();"><i class="fas fa-file"></i> Print A5 (1/2 A4)</button>
        <button type="button" class="btn btn-wa" onclick="sendWA();"><i class="fab fa-whatsapp"></i> Kirim WhatsApp</button>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
</div>
<div class="invoice-box">
    <table width="100%">
        <tr>
            <td width="60%">
                <table style="border:none; margin:0;">
                    <tr>
                        <?php if ($logo_url): ?>
                        <td style="width:80px; vertical-align:middle; border:none; padding:0;">
                            <img src="<?= htmlspecialchars($logo_url) ?>" style="max-height:70px; max-width:70px;">
                        </td>
                        <?php endif; ?>
                        <td style="vertical-align:middle; border:none; padding-left:10px;">
                            <h2><?= htmlspecialchars($company_name) ?></h2>
                            <small><?= nl2br(htmlspecialchars($company_address)) ?></small><br>
                            <small>Telp: <?= htmlspecialchars($company_phone) ?></small>
                        </td>
                    </tr>
                </table>
            </td>
            <td width="40%" align="right">
                <h3>INVOICE</h3>
                <strong>No: <?= htmlspecialchars($invoice['no_invoice']) ?></strong>
            </td>
        </tr>
    </table>
    <hr>
    <table width="100%">
        <tr>
            <td width="50%">
                <strong>Kepada Yth:</strong><br>
                <?= htmlspecialchars($invoice['nama_lengkap']) ?> / <?= htmlspecialchars($invoice['id_pelanggan']) ?><br>
                <?= nl2br(htmlspecialchars($invoice['alamat'])) ?><br>
                Telp: <?= htmlspecialchars($invoice['no_telepon']) ?>
            </td>
            <td width="50%" align="right">
                <table cellpadding="3">
                    <tr>
                        <td align="right" style="white-space: nowrap;">Tanggal Terbit : <?= $tgl_terbit ?></td>
                    </tr>
                    <tr>
                        <td align="right" style="white-space: nowrap;">Jatuh Tempo : <?= $tgl_jatuh_tempo ?></td>
                    </tr>
                    <?php if ($invoice['status'] == 'paid'): ?>
                    <tr>
                        <td align="right" style="white-space: nowrap;">Tanggal Bayar : <?= $tgl_bayar ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </td>
        </tr>
    </table>
    <br>
    <table class="table table-bordered" width="100%">
        <thead>
            <tr><th>Deskripsi</th><th width="20%">Jumlah</th></tr>
        </thead>
        <tbody>
            <tr><td>Paket <?= htmlspecialchars($invoice['nama_paket']) ?></td>
                <td align="right">Rp <?= number_format($invoice['jumlah'], 0, ',', '.') ?></td>
            </tr>
            <tr><td><strong>TOTAL</strong></td>
                <td align="right"><strong>Rp <?= number_format($invoice['jumlah'], 0, ',', '.') ?></strong></td>
            </tr>
            <?php if (!empty($invoice['paid_by'])): ?>
            <tr>
                <td colspan="2" align="right">
                    <small>Diterima oleh: <strong><?= htmlspecialchars($invoice['paid_by']) ?></strong></small>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <hr>
    <div class="text-center" style="margin: -5px 0;">
        <h3>Status: 
            <?php if ($invoice['status'] == 'paid'): ?>
                <span class="color: #00aa00; font-weight: bold; font-size: 24px;">LUNAS<?= !empty($invoice['metode_bayar']) ? ' / ' . htmlspecialchars($invoice['metode_bayar']) : '' ?></span>
            <?php else: ?>
                <span class="color: #ff0000; font-weight: bold; font-size: 24px; font-weight: bold;">BELUM LUNAS</span>
            <?php endif; ?>
        </h3>
        <?php if ($invoice['status'] == 'paid'): ?>
        <p class="mt-2" style="font-size: 14px; color: #555;">
            <i>Terima Kasih atas pembayaran Anda</i>
        </p>
        <p class="mt-1" style="font-size: 12px; color: #888;">
            Simpan resi ini sebagai bukti pembayaran
        </p>
        <?php endif; ?>
    </div>
    <div class="text-center mt-3" style="font-size: 12px;">
        <p class="mb-1">
            Pembayaran / gangguan klik: 
            <a href="https://klasnet.autobill.my.id/portal">https://klasnet.autobill.my.id/portal</a>
        </p>
        <p class="mb-2">
            Konfirmasi pembayaran: <strong><?= $no_wa_kasir ?></strong>
        </p>
        <small><?= htmlspecialchars($company_name) ?> - <?= htmlspecialchars($company_tagline) ?></small>
    </div>
</div>

<script>
function printNormal() {
    document.body.classList.remove('thermal-print');
    document.body.classList.remove('a5-print');
    document.body.classList.add('normal-print');

    let style = document.getElementById('page-style');
    if (!style) {
        style = document.createElement('style');
        style.id = 'page-style';
        document.head.appendChild(style);
    }
    style.textContent = '@page { size: A4 portrait; margin: 5mm; }';

    setTimeout(function() {
        window.print();
    }, 200);
}

function printThermal() {
    document.body.classList.remove('normal-print');
    document.body.classList.remove('a5-print');
    document.body.classList.add('thermal-print');

    let style = document.getElementById('page-style');
    if (!style) {
        style = document.createElement('style');
        style.id = 'page-style';
        document.head.appendChild(style);
    }
    style.textContent = '@page { size: 58mm auto; margin: 0; }';

    setTimeout(function() {
        window.print();
    }, 200);
}

function printA5() {
    document.body.classList.remove('normal-print');
    document.body.classList.remove('thermal-print');
    document.body.classList.add('a5-print');
    
    let style = document.getElementById('page-style');
    if (!style) {
        style = document.createElement('style');
        style.id = 'page-style';
        document.head.appendChild(style);
    }
    style.textContent = '@page { size: A5 portrait; margin: 2mm; }';
    
    setTimeout(function() {
        window.print();
    }, 200);
}

function sendWA() {
    var phone = "<?= preg_replace('/[^0-9]/', '', $invoice['no_telepon'] ?? '') ?>";
    if (!phone) {
        alert('Nomor telepon pelanggan tidak tersedia.');
        return;
    }
    if (phone.startsWith('0')) {
        phone = '62' + phone.substring(1);
    }
    var message = <?= $wa_message_json ?>;
    var url = "https://wa.me/" + phone + "?text=" + encodeURIComponent(message);
    window.open(url, '_blank');
}

function printThermal(invoiceId) {
    // Hapus iframe lama kalau ada
    var oldIframe = document.getElementById('thermal-iframe');
    if (oldIframe) oldIframe.remove();

    var iframe = document.createElement('iframe');
    iframe.id = 'thermal-iframe';
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.src = 'print_invoice_thermal_pdf.php?id=' + invoiceId;

    iframe.onload = function() {
        setTimeout(function() {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch (e) {
                alert('Gagal membuka dialog print: ' + e.message);
            }
        }, 300);
    };

    document.body.appendChild(iframe);
}
</script>

<?php include '../../includes/footer.php'; ?>
