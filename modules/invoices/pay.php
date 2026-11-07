<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();

        $tgl_bayar = $_POST['tgl_bayar'];

        $metode_bayar = $_POST['metode_bayar'] ?? 'TUNAI';
        if (!in_array($metode_bayar, ['TUNAI', 'TRANSFER'], true)) {
            $metode_bayar = 'TUNAI';
        }

        // Ambil no_invoice sebelum update
        $stmt = $pdo->prepare("SELECT no_invoice, pelanggan_id FROM invoice WHERE id = ?");
        $stmt->execute([$id]);
        $invoice_data = $stmt->fetch();
        $no_invoice = $invoice_data['no_invoice'];
        $pelanggan_id = $invoice_data['pelanggan_id'];

        $stmt = $pdo->prepare("UPDATE invoice SET status = 'paid', tgl_bayar = ?, paid_by = ?, metode_bayar = ? WHERE id = ?");
        $stmt->execute([$tgl_bayar, $_SESSION['username'] ?? 'system', $metode_bayar, $id]);

        // Catat log pembayaran
        include '../../includes/activity_log.php';
        log_admin_activity($pdo, 'INVOICE_PAID', 'invoice', $no_invoice, 'Menandai invoice sebagai LUNAS');

        $stmt = $pdo->prepare("SELECT pelanggan_id FROM invoice WHERE id = ?");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();

        if ($invoice) {
            // Simpan jatuh tempo lama sebelum diperpanjang (untuk restore saat batal)
            $stmt_old = $pdo->prepare("SELECT jatuh_tempo FROM pelanggan WHERE id = ?");
            $stmt_old->execute([$invoice['pelanggan_id']]);
            $old_due = $stmt_old->fetchColumn();
            if ($old_due) {
                $stmt_save = $pdo->prepare("UPDATE invoice SET jatuh_tempo_sebelum = ? WHERE id = ?");
                $stmt_save->execute([$old_due, $id]);
            }

            // Ambil tgl_terbit invoice untuk cek invoice baru vs nunggakan
            $stmt_terbit = $pdo->prepare("SELECT tgl_terbit FROM invoice WHERE id = ?");
            $stmt_terbit->execute([$id]);
            $tgl_terbit = $stmt_terbit->fetchColumn();

            $bulan_terbit = date('Y-m', strtotime($tgl_terbit));
            $bulan_ini    = date('Y-m');
            $hari_ini     = (int)date('j');

            if ($bulan_terbit === $bulan_ini) {
                // Invoice baru (terbit bulan ini) => jatuh tempo bulan depan tgl 20
                $jatuh_tempo_baru = date('Y-m-20 23:59:59', strtotime('+1 month'));
            } else {
                // Nunggakan: bayar tgl 1-14 => bulan ini, tgl 15+ => bulan depan
                if ($hari_ini <= 14) {
                    $jatuh_tempo_baru = date('Y-m-20 23:59:59');
                } else {
                    $jatuh_tempo_baru = date('Y-m-20 23:59:59', strtotime('+1 month'));
                }
            }

            // Perpanjang jatuh tempo dan aktifkan status
            $stmt = $pdo->prepare("
                UPDATE pelanggan
                SET jatuh_tempo = ?,
                    status = 'active'
                WHERE id = ?
            ");
            $stmt->execute([$jatuh_tempo_baru, $invoice['pelanggan_id']]);

            // Aktifkan user di Mikrotik via API
            require_once __DIR__ . '/../../includes/mikrotik_api.php';
            
            $stmt = $pdo->prepare("
                SELECT p.id_pelanggan, pk.router_id
                FROM pelanggan p
                LEFT JOIN paket_internet pk ON p.paket_id = pk.id
                WHERE p.id = ?
            ");
            $stmt->execute([$invoice['pelanggan_id']]);
            $customer = $stmt->fetch();

            if ($customer && $customer['router_id']) {
                $username_pppoe = $customer['id_pelanggan'];
                $router_id = $customer['router_id'];
                
                // Enable user via API
                $result = enable_pppoe_user($router_id, $username_pppoe);
                error_log("[PAY] Enable user {$username_pppoe}: " . json_encode($result));
            }
        }

        header("Location: print_invoice.php?id=" . $id . "&success=1");
        exit();

    } catch(PDOException $e) {
        $error = "Gagal: " . $e->getMessage();
    }
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->prepare("SELECT i.*, p.nama_lengkap FROM invoice i LEFT JOIN pelanggan p ON i.pelanggan_id = p.id WHERE i.id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        header("Location: index.php");
        exit();
    }

    if ($invoice['status'] == 'paid') {
        header("Location: print_invoice.php?id=" . $id);
        exit();
    }

} catch(PDOException $e) {
    $error_db = $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-money-bill"></i> Catat Pembayaran
        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="alert alert-info">
            <strong>No Invoice:</strong> <?= htmlspecialchars($invoice['no_invoice']) ?><br>
            <strong>Pelanggan:</strong> <?= htmlspecialchars($invoice['nama_lengkap'] ?? '-') ?><br>
            <strong>Jumlah:</strong> Rp <?= number_format($invoice['jumlah'], 0, ',', '.') ?>
        </div>

        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tanggal Bayar <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="tgl_bayar" class="form-control" required value="<?= htmlspecialchars(date('Y-m-d\TH:i')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Metode Pembayaran <span class="text-danger">*</span></label>
                    <select name="metode_bayar" class="form-select" required>
                        <option value="TUNAI" selected>TUNAI / CASH</option>
                        <option value="TRANSFER">TRANSFER</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-success"><i class="fas fa-check-circle"></i> Konfirmasi Pembayaran</button>
            <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
