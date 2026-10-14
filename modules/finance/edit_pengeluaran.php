<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

$id = $_GET['id'] ?? 0;

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM pengeluaran WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        echo '<div class="alert alert-danger">Data tidak ditemukan</div>';
        include '../../includes/footer.php';
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $jenis = $_POST['jenis'];
        $keterangan = $_POST['keterangan'];
        $jumlah = str_replace(['.', ','], '', $_POST['jumlah']);
        $tanggal = $_POST['tanggal'];
        
        $stmt = $pdo->prepare("UPDATE pengeluaran SET jenis = ?, keterangan = ?, jumlah = ?, tanggal = ? WHERE id = ?");
        $stmt->execute([$jenis, $keterangan, $jumlah, $tanggal, $id]);
        
        echo '<div class="alert alert-success">Data berhasil diupdate! <a href="pengeluaran.php">Kembali</a></div>';
    }
} catch(PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>

<div class="card">
    <div class="card-header bg-warning text-dark">
        <i class="fas fa-edit"></i> Edit Pengeluaran
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Jenis</label>
                <select name="jenis" class="form-select" required>
                    <option value="rutin" <?= $data['jenis'] == 'rutin' ? 'selected' : '' ?>>Rutin</option>
                    <option value="maintenance" <?= $data['jenis'] == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    <option value="bagi_hasil" <?= $data['jenis'] == 'bagi_hasil' ? 'selected' : '' ?>>Bagi Hasil</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Keterangan</label>
                <input type="text" name="keterangan" class="form-control" value="<?= htmlspecialchars($data['keterangan']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Jumlah (Rp)</label>
                <input type="text" name="jumlah" class="form-control" value="<?= number_format($data['jumlah'], 0, ',', '.') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d', strtotime($data['tanggal'])) ?>" required>
            </div>
            <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update</button>
            <a href="pengeluaran.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
