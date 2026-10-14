<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        require_once __DIR__ . "/../../config/database.php";
        $pdo = Database::getInstance()->getConnection();
        
        $nama = $_POST['nama'];
        $modal = str_replace(['.', ','], '', $_POST['modal']);
        $bagi_hasil_per_bulan = str_replace(['.', ','], '', $_POST['bagi_hasil_per_bulan'] ?? 0);
        
        // Buat tabel jika belum ada
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS investor (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                modal DECIMAL(15,2) NOT NULL,
                prosentase DECIMAL(5,2) NOT NULL DEFAULT 0,
                bagi_hasil_per_bulan DECIMAL(15,2) DEFAULT 0,
                bagi_hasil DECIMAL(15,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert investor baru
        $stmt = $pdo->prepare("INSERT INTO investor (nama, modal, bagi_hasil_per_bulan, prosentase) VALUES (?, ?, ?, 0)");
        $stmt->execute([$nama, $modal, $bagi_hasil_per_bulan]);
        
        // Update prosentase semua investor
        $total_modal = $pdo->query("SELECT COALESCE(SUM(modal), 0) as total FROM investor")->fetch()['total'];
        
        if ($total_modal > 0) {
            $update = $pdo->prepare("UPDATE investor SET prosentase = (modal / ?) * 100");
            $update->execute([$total_modal]);
        }
        
        echo '<div class="alert alert-success">Investor berhasil ditambahkan! <a href="bagi_hasil.php">Kembali</a></div>';
    } catch(PDOException $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="card">
    <div class="card-header bg-success text-white">
        <i class="fas fa-user-plus"></i> Tambah Investor
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong>Bagi Hasil Per Bulan</strong> = Jumlah yang diberikan ke investor setiap bulan (otomatis masuk ke pengeluaran tanggal 25).
        </div>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nama Investor</label>
                <input type="text" name="nama" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Jumlah Modal (Rp)</label>
                <input type="text" name="modal" class="form-control" placeholder="10000000" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Bagi Hasil Per Bulan (Rp)</label>
                <input type="text" name="bagi_hasil_per_bulan" class="form-control" placeholder="1000000">
                <small class="text-muted">Isi 0 jika investor tidak mendapat bagi hasil bulanan</small>
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
            <a href="bagi_hasil.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
