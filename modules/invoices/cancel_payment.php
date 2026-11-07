<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'super_admin') {
    header("Location: index.php?error=unauthorized");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

// Proses pembatalan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm'])) {
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
        
        $alasan = $_POST['alasan'] ?? '';
        $username = $_SESSION['user_name'];
        
        // Ambil data invoice sebelum update
        $stmt = $pdo->prepare("SELECT no_invoice, pelanggan_id, jatuh_tempo_sebelum FROM invoice WHERE id = ?");
        $stmt->execute([$id]);
        $invoice_data = $stmt->fetch();
        $no_invoice = $invoice_data['no_invoice'];
        $pelanggan_id = $invoice_data['pelanggan_id'];
        $jatuh_tempo_sebelum = $invoice_data['jatuh_tempo_sebelum'];

        // Kembalikan jatuh tempo pelanggan jika ada data sebelumnya
        if (!empty($jatuh_tempo_sebelum) && !empty($pelanggan_id)) {
            $stmt_restore = $pdo->prepare("UPDATE pelanggan SET jatuh_tempo = ? WHERE id = ?");
            $stmt_restore->execute([$jatuh_tempo_sebelum, $pelanggan_id]);
        }

        // Update status invoice ke pending + reset jatuh_tempo_sebelum
        $stmt = $pdo->prepare("UPDATE invoice SET status = 'pending', tgl_bayar = NULL, paid_by = NULL, metode_bayar = NULL, jatuh_tempo_sebelum = NULL WHERE id = ?");
        $stmt->execute([$id]);
        
        // Catat log pembatalan
        include '../../includes/activity_log.php';
        log_admin_activity($pdo, 'INVOICE_UNPAID', 'invoice', $no_invoice, 'Membatalkan pembayaran invoice. Alasan: ' . $alasan);
        
        // Redirect ke view
        header("Location: view.php?id=" . $id . "&cancel=success");
        exit();
        
    } catch(PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Ambil data invoice untuk ditampilkan
try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT i.*, p.nama_lengkap, p.id_pelanggan 
                           FROM invoice i 
                           LEFT JOIN pelanggan p ON i.pelanggan_id = p.id 
                           WHERE i.id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        header("Location: index.php");
        exit();
    }
    
    if ($invoice['status'] != 'paid') {
        header("Location: index.php?error=not_paid");
        exit();
    }
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header bg-danger text-white">
        <i class="fas fa-ban"></i> Batalkan Pembayaran
        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Peringatan!</strong> Pembatalan pembayaran akan mengembalikan status tagihan menjadi PENDING.
        </div>
        
        <div class="alert alert-info">
            <strong>Detail Invoice:</strong><br>
            No Invoice: <?= htmlspecialchars($invoice['no_invoice']) ?><br>
            Pelanggan: <?= htmlspecialchars($invoice['nama_lengkap'] ?? '-') ?><br>
            Jumlah: Rp <?= number_format($invoice['jumlah'], 0, ',', '.') ?>
        </div>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Alasan Pembatalan</label>
                <textarea name="alasan" class="form-control" rows="3" required></textarea>
            </div>
            
            <button type="submit" name="confirm" class="btn btn-danger" onclick="return confirm('Yakin ingin membatalkan pembayaran ini?')">
                Ya, Batalkan Pembayaran
            </button>
            <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
