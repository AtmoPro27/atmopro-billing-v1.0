<?php
ob_start();
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

$message = '';
if (isset($_GET['cancel']) && $_GET['cancel'] == 'success') {
    $message = '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-ban"></i> Pembayaran berhasil dibatalkan! Status tagihan kembali ke PENDING.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT i.*, p.nama_lengkap, p.alamat, p.no_telepon, p.id_pelanggan 
                           FROM invoice i 
                           LEFT JOIN pelanggan p ON i.pelanggan_id = p.id 
                           WHERE i.id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        header("Location: index.php");
        exit();
    }
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-file-invoice"></i> Detail Tagihan
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?= $message ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><th width="150">No Invoice</th><td>: <?= htmlspecialchars($invoice['no_invoice'] ?? '-') ?> </small></tr>
                    <tr><th>ID Pelanggan</th><td>: <?= htmlspecialchars($invoice['id_pelanggan'] ?? '-') ?> </small></tr>
                    <tr><th>Nama Pelanggan</th><td>: <?= htmlspecialchars($invoice['nama_lengkap'] ?? '-') ?> </small></tr>
                    <tr><th>Alamat</th><td>: <?= nl2br(htmlspecialchars($invoice['alamat'] ?? '-')) ?> </small></tr>
                    <tr><th>No Telepon</th><td>: <?= htmlspecialchars($invoice['no_telepon'] ?? '-') ?> </small></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <?php if (!empty($invoice['tgl_terbit']) && $invoice['tgl_terbit'] != '0000-00-00 00:00:00'): ?>
                    <tr><th width="150">Tanggal Terbit</th><td>: <?= date('d-m-Y H:i:s', strtotime($invoice['tgl_terbit'])) ?> </small></tr>
                    <?php else: ?>
                    <tr><th width="150">Tanggal Terbit</th><td>: - </small></tr>
                    <?php endif; ?>
                    
                    <?php if (!empty($invoice['tgl_jatuh_tempo']) && $invoice['tgl_jatuh_tempo'] != '0000-00-00 00:00:00'): ?>
                    <tr><th>Jatuh Tempo</th><td>: <?= date('d-m-Y', strtotime($invoice['tgl_jatuh_tempo'])) ?> </small></tr>
                    <?php else: ?>
                    <tr><th>Jatuh Tempo</th><td>: - </small></tr>
                    <?php endif; ?>
                    
                    <tr><th>Jumlah</th><td>: <strong>Rp <?= number_format($invoice['jumlah'] ?? 0, 0, ',', '.') ?></strong> </small></tr>
                    
                    <tr>
                        <th>Status</th>
                        <td>: 
                            <?php
                            if ($invoice['status'] == 'paid') {
                                echo '<span class="badge bg-success">Lunas' . (!empty($invoice['paid_by']) ? ' / ' . htmlspecialchars($invoice['paid_by']) : '') . '</span>';
                            } elseif ($invoice['status'] == 'pending') {
                                echo '<span class="badge bg-warning">Pending</span>';
                            } elseif ($invoice['status'] == 'cancelled') {
                                echo '<span class="badge bg-danger">Dibatalkan</span>';
                            } else {
                                echo '<span class="badge bg-secondary">' . ($invoice['status'] ?? '-') . '</span>';
                            }
                            ?>
                        </small>
                    </tr>
                    
                    <?php if ($invoice['status'] == 'paid' && !empty($invoice['tgl_bayar']) && $invoice['tgl_bayar'] != '0000-00-00 00:00:00'): ?>
                    <tr>
                        <th>Tgl Bayar</th>
                        <td>: <?= date('d-m-Y H:i:s', strtotime($invoice['tgl_bayar'])) ?> </small>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <?php if (!empty($invoice['keterangan'])): ?>
        <div class="alert alert-secondary mt-3">
            <strong>Keterangan Tagihan:</strong><br><?= nl2br(htmlspecialchars($invoice['keterangan'])) ?>
        </div>
        <?php endif; ?>
        
        <div class="mt-3">
            <a href="print_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-info">
                <i class="fas fa-print"></i> Print Invoice
            </a>
            <?php if ($invoice['status'] != 'paid'): ?>
                <a href="pay.php?id=<?= $invoice['id'] ?>" class="btn btn-success"><i class="fas fa-money-bill"></i> Catat Pembayaran</a>
            <?php else: ?>
                <?php if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'super_admin'): ?>
                    <a href="cancel_payment.php?id=<?= $invoice['id'] ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin membatalkan pembayaran ini?')">
                        <i class="fas fa-ban"></i> Batalkan Pembayaran
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary">Kembali</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
