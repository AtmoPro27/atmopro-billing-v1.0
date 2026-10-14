<?php
ob_start();
require_once __DIR__ . '/../../includes/auth_check.php';

$id_pelanggan = $_GET['id'] ?? '';
if (empty($id_pelanggan)) {
    header("Location: index.php");
    exit();
}

include '../../includes/header.php';
require_once '../../config/database.php';
$pdo = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $stmt = $pdo->prepare("UPDATE pelanggan SET status_aktif = 'aktif', status = 'active' WHERE id_pelanggan = ?");
        $stmt->execute([$id_pelanggan]);
        
        $_SESSION['activation_message'] = "Pelanggan berhasil diaktifkan!";
        header("Location: index.php");
        exit();
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// Ambil data pelanggan
$stmt = $pdo->prepare("SELECT id_pelanggan, nama_lengkap, status_aktif FROM pelanggan WHERE id_pelanggan = ?");
$stmt->execute([$id_pelanggan]);
$pelanggan = $stmt->fetch();
?>

<div class="card">
    <div class="card-header">Aktivasi Pelanggan: <?= htmlspecialchars($pelanggan['nama_lengkap']) ?></div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <button type="submit" class="btn btn-success">Aktifkan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
