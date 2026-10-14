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
$company_name = $settings['company_name'] ?? 'DEMO COMPANY';
$company_tagline = $settings['company_tagline'] ?? 'HIGH SPEED INTERNET CONNECTION';
$company_address = $settings['company_address'] ?? '';
$company_phone = $settings['company_phone'] ?? '';
$stmt_wa = $pdo->query("SELECT phone FROM users WHERE role = 'kasir' AND is_active = 1 LIMIT 1");
$wa_kasir = $stmt_wa->fetchColumn();
$no_wa_kasir = $wa_kasir ?: $settings['wa_number'] ?? '628000000000';

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
$wa_message = str_replace("{portal_url}", BASE_URL . "/portal", $wa_message);
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
        @media print {
            body.normal-print .invoice-box {
                margin: 0 auto !important;
                padding: 20px !important;
                width: 100% !important;
                max-width: 800px !important;
                font-size: 14px;
            }
            body.normal-print .invoice-box table {
                width: 100%;
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
        <button type="button" class="btn btn-info" onclick="printThermal();"><i class="fas fa-receipt"></i> Print Termal (80mm)</button>
        <button type="button" class="btn btn-wa" onclick="sendWA();"><i class="fab fa-whatsapp"></i> Kirim WhatsApp</button>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
</div>
<div class="invoice-box">
    <table width="100%">
        <tr>
            <td width="60%">
                <h2><?= htmlspecialchars($company_name) ?></h2>
                <small><?= nl2br(htmlspecialchars($company_address)) ?></h3><br>
                <small>Telp: <?= htmlspecialchars($company_phone) ?></h3>
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
                <?= htmlspecialchars($invoice['nama_lengkap']) ?><br>
                <?= nl2br(htmlspecialchars($invoice['alamat'])) ?><br>
                Telp: <?= htmlspecialchars($invoice['no_telepon']) ?>
            </td>
            <td width="50%" align="right">
                <table cellpadding="3">
                    <tr><th align="left">Tanggal Terbit</th><td>: <?= $tgl_terbit ?></td></tr>
                    <tr><th align="left">Jatuh Tempo</th><td>: <?= $tgl_jatuh_tempo ?></td></tr>
                    <?php if ($invoice['status'] == 'paid'): ?>
                    <tr><th align="left">Tanggal Bayar</th><td>: <?= $tgl_bayar ?></td></tr>
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
        </tbody>
    </table>
    <hr>
    <div class="text-center" style="margin: 20px 0;">
        <h3>Status: 
            <?php if ($invoice['status'] == 'paid'): ?>
                <span class="color: #00aa00; font-weight: bold; font-size: 24px; font-weight: bold;">LUNAS</span>
            <?php else: ?>
                <span class="color: #ff0000; font-weight: bold; font-size: 24px; font-weight: bold;">BELUM LUNAS</span>
            <?php endif; ?>
        </h3>
    </div>
    <div class="text-center mt-3">
        <small><?= htmlspecialchars($company_name) ?> - <?= htmlspecialchars($company_tagline) ?></h3>
    </div>
</div>

<script>
function printNormal() {
    document.body.classList.remove('thermal-print');
    document.body.classList.add('normal-print');
    setTimeout(function() {
        window.print();
    }, 100);
}

function printThermal() {
    document.body.classList.remove('normal-print');
    document.body.classList.add('thermal-print');
    setTimeout(function() {
        window.print();
    }, 100);
}

function sendWA() {
    let noTelepon = "<?= $invoice['no_telepon'] ?>";
    if (!noTelepon || noTelepon === '-') { 
        alert('Nomor telepon pelanggan tidak tersedia!'); 
        return; 
    }
    let waNumber = noTelepon.replace(/^0+/, '');
    if (!waNumber.startsWith('62')) { 
        waNumber = '62' + waNumber; 
    }
    let message = <?= $wa_message_json ?>;
    window.open("https://wa.me/" + waNumber + "?text=" + encodeURIComponent(message), '_blank');
}
</script>

<?php include '../../includes/footer.php'; ?>
