<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

// Koneksi database
try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

$message = '';
$message_type = '';

// Proses simpan data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $isi = trim($_POST['isi']);
    $status = $_POST['status'];
    
    if (!empty($isi)) {
        try {
            // Cek apakah sudah ada data
            $cek = $pdo->query("SELECT COUNT(*) FROM pengumuman")->fetchColumn();
            
            if ($cek > 0) {
                // Update
                $sql = "UPDATE pengumuman SET isi = :isi, status = :status WHERE id = (SELECT id FROM pengumuman LIMIT 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':isi' => $isi, ':status' => $status]);
            } else {
                // Insert
                $sql = "INSERT INTO pengumuman (isi, status) VALUES (:isi, :status)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':isi' => $isi, ':status' => $status]);
            }
            $message = "Pengumuman berhasil disimpan!";
            $message_type = "success";
        } catch(Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "Isi pengumuman tidak boleh kosong!";
        $message_type = "danger";
    }
}

// Ambil data saat ini
$current_isi = "SEMUA BERJALAN NORMAL";
$current_status = "aktif";
try {
    $stmt = $pdo->query("SELECT isi, status FROM pengumuman LIMIT 1");
    if ($stmt && $row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_isi = $row['isi'];
        $current_status = $row['status'];
    }
} catch(Exception $e) {
    // Tabel mungkin belum ada
}
?>

<div class="container-fluid px-4">
    <div class="card mt-4">
        <div class="card-header">
            <i class="fas fa-bullhorn me-1"></i>
            Kelola Pengumuman Portal
            <a href="index.php" class="btn btn-secondary btn-sm float-end">
                <i class="fas fa-arrow-left"></i> Kembali ke Tiket
            </a>
        </div>
        <div class="card-body">
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="isi" class="form-label fw-bold">Isi Pengumuman</label>
                    <textarea class="form-control" id="isi" name="isi" rows="5" placeholder="Tulis pengumuman di sini..."><?= htmlspecialchars($current_isi) ?></textarea>
                    <div class="form-text">Jika kosong, akan menampilkan "SEMUA BERJALAN NORMAL"</div>
                </div>
                
                <div class="mb-3">
                    <label for="status" class="form-label fw-bold">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="aktif" <?= $current_status == 'aktif' ? 'selected' : '' ?>>Aktif (Tampil)</option>
                        <option value="nonaktif" <?= $current_status == 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Tidak Tampil)</option>
                    </select>
                </div>
                
                <button type="submit" name="simpan" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Pengumuman
                </button>
                
                <a href="../../portal/dashboard.php" class="btn btn-info" target="_blank">
                    <i class="fas fa-eye"></i> Lihat Portal Pelanggan
                </a>
            </form>
            
            <hr>
            
            <div class="alert alert-secondary">
                <strong><i class="fas fa-eye"></i> Preview (yang tampil di portal):</strong><br>
                <?= nl2br(htmlspecialchars($current_isi)) ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
