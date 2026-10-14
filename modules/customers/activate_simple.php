<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$id_pelanggan = $_GET['id'] ?? '';
if (empty($id_pelanggan)) {
    header("Location: index.php");
    exit();
}

include '../../includes/header.php';
require_once '../../config/database.php';
$pdo = Database::getInstance()->getConnection();

echo "<h1>Test Aktivasi</h1>";
echo "<p>ID Pelanggan: " . htmlspecialchars($id_pelanggan) . "</p>";

// Ambil data pelanggan
$stmt = $pdo->prepare("SELECT id_pelanggan, nama_lengkap, status_aktif FROM pelanggan WHERE id_pelanggan = ?");
$stmt->execute([$id_pelanggan]);
$pelanggan = $stmt->fetch();

if (!$pelanggan) {
    echo "<p class='text-danger'>Pelanggan tidak ditemukan!</p>";
} else {
    echo "<p>Nama: " . htmlspecialchars($pelanggan['nama_lengkap']) . "</p>";
    echo "<p>Status saat ini: " . htmlspecialchars($pelanggan['status_aktif']) . "</p>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<p>Form submitted!</p>";
    
    try {
        $stmt = $pdo->prepare("UPDATE pelanggan SET status_aktif = 'aktif', status = 'active' WHERE id_pelanggan = ?");
        $stmt->execute([$id_pelanggan]);
        echo "<p class='text-success'>Update berhasil!</p>";
        echo "<script>alert('Pelanggan berhasil diaktifkan!'); window.location.href='index.php';</script>";
    } catch(Exception $e) {
        echo "<p class='text-danger'>Error: " . $e->getMessage() . "</p>";
    }
}
?>

<form method="POST">
    <button type="submit" class="btn btn-success">Aktifkan</button>
    <a href="index.php" class="btn btn-secondary">Batal</a>
</form>

<?php include '../../includes/footer.php'; ?>
