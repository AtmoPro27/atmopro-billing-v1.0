<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    $investor_list = $pdo->query("SELECT id, nama, prosentase FROM investor ORDER BY nama ASC")->fetchAll();
} catch(PDOException $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $investor_id = $_POST['investor_id'];
        $jumlah = str_replace(['.', ','], '', $_POST['jumlah']);
        $keterangan = $_POST['keterangan'] ?? '';
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        
        // Insert ke pengeluaran dengan investor_id
        $stmt = $pdo->prepare("INSERT INTO pengeluaran (jenis, investor_id, keterangan, jumlah, tanggal) VALUES ('bagi_hasil', ?, ?, ?, ?)");
        $stmt->execute([$investor_id, $keterangan, $jumlah, $tanggal . ' 00:00:00']);
        
        // UPDATE bagi_hasil di tabel investor
        $stmt = $pdo->prepare("UPDATE investor SET bagi_hasil = COALESCE(bagi_hasil, 0) + ? WHERE id = ?");
        $stmt->execute([$jumlah, $investor_id]);
        
        echo '<div class="alert alert-success">Bagi hasil berhasil ditambahkan! <a href="bagi_hasil.php">Kembali</a></div>';
    } catch(PDOException $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="card">
    <div class="card-header bg-warning text-dark">
        <i class="fas fa-hand-holding-usd"></i> Tambah Bagi Hasil
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Pilih Investor</label>
                <select name="investor_id" class="form-select" required>
                    <option value="">-- Pilih --</option>
                    <?php if (!empty($investor_list)): ?>
                        <?php foreach ($investor_list as $inv): ?>
                            <option value="<?= $inv['id'] ?>">
                                <?= htmlspecialchars($inv['nama']) ?> (<?= number_format($inv['prosentase'], 2) ?>%)
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">Belum ada investor</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Jumlah Bagi Hasil (Rp)</label>
                <input type="text" name="jumlah" class="form-control" placeholder="150000" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Keterangan</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Bagi hasil periode ...">
            </div>
            <div class="mb-3">
                <label class="form-label">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Simpan</button>
            <a href="bagi_hasil.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
