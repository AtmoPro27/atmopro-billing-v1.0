<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$id = $_GET['id'] ?? 0;

if ($id <= 0) {
    header("Location: pengeluaran.php?error=ID tidak valid");
    exit();
}

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    // Cek apakah data ada
    $stmt = $pdo->prepare("SELECT id, jenis, keterangan, jumlah FROM pengeluaran WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    
    if (!$data) {
        header("Location: pengeluaran.php?error=Data tidak ditemukan");
        exit();
    }
    
    // Hapus data
    $stmt = $pdo->prepare("DELETE FROM pengeluaran WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: pengeluaran.php?success=Data berhasil dihapus");
    exit();
    
} catch(PDOException $e) {
    header("Location: pengeluaran.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
