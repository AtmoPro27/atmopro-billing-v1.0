<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT i.*, p.nama_lengkap 
        FROM invoice i 
        LEFT JOIN pelanggan p ON i.pelanggan_id = p.id 
        WHERE i.status = 'paid' AND DATE(i.tgl_bayar) = ?
        ORDER BY i.tgl_bayar DESC
    ");
    $stmt->execute([$tanggal]);
    $invoices = $stmt->fetchAll();
    
    $total = array_sum(array_column($invoices, 'jumlah'));
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-calendar-day"></i> Laporan Pendapatan Harian
        <a href="income.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <form method="GET" class="row mb-3">
            <div class="col-md-4">
                <input type="date" name="tanggal" class="form-control"  value="<?= htmlspecialchars($tanggal) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <strong>Tanggal:</strong> <?= date('d-m-Y', strtotime($tanggal)) ?><br>
            <strong>Total Pendapatan:</strong> Rp <?= number_format($total, 0, ',', '.') ?>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No Invoice</th>
                        <th>Pelanggan</th>
                        <th>Jumlah</th>
                        <th>Tgl Bayar</th>
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
                        <tr><td colspan="4" class="text-center">Tidak ada data pembayaran untuk tanggal <?= date('d-m-Y', strtotime($tanggal)) ?></td></tr>
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
