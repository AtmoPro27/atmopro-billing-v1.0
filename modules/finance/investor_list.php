<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->query("SELECT * FROM investor ORDER BY nama ASC");
    $investor = $stmt->fetchAll();
    
    $total_modal = array_sum(array_column($investor, 'modal'));
    $total_bagi = array_sum(array_column($investor, 'bagi_hasil'));
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-list"></i> Daftar Investor
        <div class="float-end">
            <a href="investor_add.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Tambah Investor</a>
            <a href="bagi_hasil.php" class="btn btn-secondary btn-sm">Kembali</a>
        </div>
    </div>
    <div class="card-body">
        
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="alert alert-info">
                    <strong>Total Modal:</strong> Rp <?= number_format($total_modal, 0, ',', '.') ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-warning">
                    <strong>Total Bagi Hasil:</strong> Rp <?= number_format($total_bagi, 0, ',', '.') ?>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="dataTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Investor</th>
                        <th>Modal</th>
                        <th>Prosentase</th>
                        <th>Bagi Hasil</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($investor)): ?>
                        <tr><td colspan="6" class="text-center">Belum ada data investor</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($investor as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
                            <td>Rp <?= number_format($row['modal'], 0, ',', '.') ?></td>
                            <td><?= number_format($row['prosentase'], 2) ?>%</td>
                            <td>Rp <?= number_format($row['bagi_hasil'] ?? 0, 0, ',', '.') ?></td>
                            <td>
                                <a href="investor_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="investor_delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
