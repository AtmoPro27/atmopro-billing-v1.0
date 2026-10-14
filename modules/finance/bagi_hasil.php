<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    // Total Bagi Hasil Bulan Ini (dari pengeluaran)
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran WHERE jenis = 'bagi_hasil' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
    $bagi_hasil_bulan = $stmt->fetch()['total'];
    
    // Total Bagi Hasil Tahun Ini (dari pengeluaran)
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran WHERE jenis = 'bagi_hasil' AND YEAR(tanggal) = YEAR(CURDATE())");
    $bagi_hasil_tahun = $stmt->fetch()['total'];
    
    // Jumlah Investor
    $stmt = $pdo->query("SELECT COUNT(DISTINCT id) as total FROM investor");
    $total_investor = $stmt->fetch()['total'];
    
    // Total Modal
    $stmt = $pdo->query("SELECT COALESCE(SUM(modal), 0) as total FROM investor");
    $total_modal = $stmt->fetch()['total'];
    
    // Data Investor - ambil langsung dari tabel investor
    $investor_data = $pdo->query("
        SELECT 
            id,
            nama,
            modal,
            prosentase,
            bagi_hasil_per_bulan as total_bagi_hasil
        FROM investor
        ORDER BY nama ASC
    ")->fetchAll();
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<style>
    .stat-box {
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        color: white;
        height: 130px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .stat-box h2 {
        margin: 0;
        font-weight: bold;
        font-size: 28px;
    }
    .stat-box small {
        opacity: 0.9;
        font-size: 12px;
    }
</style>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
            <h2 style="color: white;">Rp <?= number_format($bagi_hasil_bulan, 0, ',', '.') ?></h2>
            <small>BAGI HASIL BULAN INI</small>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #e67e22, #d35400);">
            <h2 style="color: white;">Rp <?= number_format($bagi_hasil_tahun, 0, ',', '.') ?></h2>
            <small>BAGI HASIL TAHUN INI</small>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #2b5876, #4e4376);">
            <h2 style="color: white;"><?= $total_investor ?></h2>
            <small>JUMLAH INVESTOR</small>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #1a2980, #26d0ce);">
            <h2 style="color: white;">Rp <?= number_format($total_modal, 0, ',', '.') ?></h2>
            <small>TOTAL MODAL</small>
        </div>
    </div>
</div>

<!-- Tombol Aksi -->
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body text-center">
                <a href="investor_add.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Tambah Investor</a>
                <a href="bagi_hasil_add.php" class="btn btn-warning"><i class="fas fa-hand-holding-usd"></i> Tambah Bagi Hasil</a>
                <a href="investor_list.php" class="btn btn-primary"><i class="fas fa-list"></i> Daftar Investor</a>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Investor -->
<div class="card mt-3">
    <div class="card-header">
        <i class="fas fa-users"></i> Daftar Investor & Bagi Hasil
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Nama Investor</th>
                        <th>Modal</th>
                        <th>Prosentase</th>
                        <th>Bagi Hasil / Bulan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($investor_data)): ?>
                        <?php foreach ($investor_data as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
                                <td>Rp <?= number_format($row['modal'], 0, ',', '.') ?></td>
                                <td><?= number_format($row['prosentase'], 2) ?>%</td>
                                <td class="fw-bold">Rp <?= number_format($row['total_bagi_hasil'], 0, ',', '.') ?></td>
                                <td>
                                    <a href="investor_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <a href="investor_delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted">Belum ada data</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
