<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';

$bulan = $_GET['bulan'] ?? date('Y-m');

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    // Ambil data invoice yang sudah paid untuk bulan yang dipilih
    $stmt = $pdo->prepare("
        SELECT i.*, p.nama_lengkap, DATE(i.tgl_bayar) as tgl_bayar_only
        FROM invoice i 
        LEFT JOIN pelanggan p ON i.pelanggan_id = p.id 
        WHERE i.status = 'paid' AND DATE_FORMAT(i.tgl_bayar, '%Y-%m') = ?
        ORDER BY i.tgl_bayar ASC
    ");
    $stmt->execute([$bulan]);
    $invoices = $stmt->fetchAll();
    
    $total = array_sum(array_column($invoices, 'jumlah'));
    
    // Rekap per hari
    $rekap_harian = [];
    foreach ($invoices as $inv) {
        $tgl = $inv['tgl_bayar_only'];
        if (!isset($rekap_harian[$tgl])) {
            $rekap_harian[$tgl] = 0;
        }
        $rekap_harian[$tgl] += $inv['jumlah'];
    }
    
} catch(PDOException $e) {
    $error = $e->getMessage();
    $invoices = [];
    $rekap_harian = [];
    $total = 0;
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-calendar-alt"></i> Laporan Pendapatan Bulanan
        <a href="income.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <form method="GET" class="row mb-3">
            <div class="col-md-4">
                <input type="month" name="bulan" class="form-control"  value="<?= htmlspecialchars($bulan) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <strong>Bulan:</strong> <?= date('F Y', strtotime($bulan . '-01')) ?><br>
            <strong>Jumlah Transaksi:</strong> <?= count($invoices) ?> pembayaran<br>
            <strong>Total Pendapatan:</strong> Rp <?= number_format($total, 0, ',', '.') ?>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">Rekap Harian</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr><th>Tanggal</th><th>Jumlah</th><th>Transaksi</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (count($rekap_harian) > 0): ?>
                                        <?php foreach ($rekap_harian as $tgl => $total_hari): ?>
                                            <?php
                                            $jml_transaksi = 0;
                                            foreach ($invoices as $inv) {
                                                if ($inv['tgl_bayar_only'] == $tgl) $jml_transaksi++;
                                            }
                                            ?>
                                            <tr>
                                                <td><?= date('d-m-Y', strtotime($tgl)) ?> </small></small></small>
                                                <td>Rp <?= number_format($total_hari, 0, ',', '.') ?> </small></small>
                                                <td><?= $jml_transaksi ?> transaksi</small>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center">Belum ada data pembayaran</small></small></small></tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-info">
                                    <tr>
                                        <th>Total</th>
                                        <th>Rp <?= number_format($total, 0, ',', '.') ?></th>
                                        <th><?= count($invoices) ?> transaksi</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">Detail Transaksi</div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr><th>No Invoice</th><th>Pelanggan</th><th>Jumlah</th><th>Tgl Bayar</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (count($invoices) > 0): ?>
                                        <?php foreach ($invoices as $inv): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($inv['no_invoice']) ?> </small></small>
                                                <td><?= htmlspecialchars($inv['nama_lengkap'] ?? '-') ?> </small></small>
                                                <td>Rp <?= number_format($inv['jumlah'], 0, ',', '.') ?> </small></small>
                                                <td><?= date('d-m-Y H:i:s', strtotime($inv['tgl_bayar'])) ?> </small></small>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center">Belum ada data pembayaran</small></small> </small></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
