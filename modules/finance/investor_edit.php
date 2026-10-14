<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

$id = $_GET['id'] ?? 0;

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM investor WHERE id = ?");
    $stmt->execute([$id]);
    $investor = $stmt->fetch();
    
    if (!$investor) {
        echo '<div class="alert alert-danger">Investor tidak ditemukan</div>';
        include '../../includes/footer.php';
        exit();
    }
} catch(PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    include '../../includes/footer.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $nama = $_POST['nama'];
        $modal = str_replace(['.', ','], '', $_POST['modal']);
        $bagi_hasil_per_bulan = str_replace(['.', ','], '', $_POST['bagi_hasil_per_bulan']);
        
        // Update investor
        $stmt = $pdo->prepare("UPDATE investor SET nama = ?, modal = ?, bagi_hasil_per_bulan = ? WHERE id = ?");
        $stmt->execute([$nama, $modal, $bagi_hasil_per_bulan, $id]);
        
        // Recalculate prosentase
        $total_modal = $pdo->query("SELECT COALESCE(SUM(modal), 0) as total FROM investor")->fetch()['total'];
        
        if ($total_modal > 0) {
            $update = $pdo->prepare("UPDATE investor SET prosentase = (modal / ?) * 100");
            $update->execute([$total_modal]);
        }
        
        echo '<div class="alert alert-success">Data investor berhasil diupdate! <a href="bagi_hasil.php">Kembali</a></div>';
    } catch(PDOException $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="card">
    <div class="card-header bg-warning text-dark">
        <i class="fas fa-user-edit"></i> Edit Investor
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong>Bagi Hasil Per Bulan</strong> = Jumlah yang diberikan ke investor setiap bulan (otomatis masuk ke pengeluaran tanggal 25).
        </div>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nama Investor</label>
                <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($investor['nama']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Jumlah Modal (Rp)</label>
                <input type="text" name="modal" class="form-control" value="<?= number_format($investor['modal'], 0, ',', '.') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Bagi Hasil Per Bulan (Rp)</label>
                <input type="text" name="bagi_hasil_per_bulan" class="form-control" value="<?= number_format($investor['bagi_hasil_per_bulan'] ?? 0, 0, ',', '.') ?>" required>
                <small class="text-muted">Isi 0 jika investor tidak mendapat bagi hasil bulanan</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Prosentase (Otomatis)</label>
                <input type="text" class="form-control" value="<?= number_format($investor['prosentase'], 2) ?>%" disabled>
                <small class="text-muted">Prosentase dihitung otomatis berdasarkan total modal</small>
            </div>
            <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update</button>
            <a href="bagi_hasil.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
