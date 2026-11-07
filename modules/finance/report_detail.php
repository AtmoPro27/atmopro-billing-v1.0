<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$bulan = $_GET['bulan'] ?? '';
if (empty($bulan)) {
    header("Location: index.php");
    exit();
}

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    // Ambil data invoice untuk bulan tersebut
    $stmt = $pdo->prepare("
        SELECT i.*, p.nama_lengkap 
        FROM invoice i 
        LEFT JOIN pelanggan p ON i.pelanggan_id = p.id 
        WHERE i.status = 'paid' AND DATE_FORMAT(i.tgl_bayar, '%Y-%m') = ?
        ORDER BY i.tgl_bayar DESC
    ");
    $stmt->execute([$bulan]);
    $invoices = $stmt->fetchAll();
    
    $total = array_sum(array_column($invoices, 'jumlah'));
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}

$nama_bulan = date('F Y', strtotime($bulan . '-01'));
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-calendar-alt"></i> Detail Laporan Bulanan: <?= $nama_bulan ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <strong>Bulan:</strong> <?= $nama_bulan ?><br>
            <strong>Total Pendapatan:</strong> Rp <?= number_format($total, 0, ',', '.') ?>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No Invoice</th>
                        <th>Pelanggan</th>
                        <th>Jumlah</th>
                        <th>Tanggal Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($invoices) > 0): ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><?= htmlspecialchars($inv['no_invoice']) ?></td>
                                <td><?= htmlspecialchars($inv['nama_lengkap'] ?? '-') ?></td>
                                <td>Rp <?= number_format($inv['jumlah'], 0, ',', '.') ?></td>
                                <td><?= date('d-m-Y H:i:s', strtotime($inv['tgl_bayar'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">Tidak ada data pembayaran untuk bulan <?= $nama_bulan ?></td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-info">
                    <tr>
                        <th colspan="2">Total</th>
                        <th>Rp <?= number_format($total, 0, ',', '.') ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
