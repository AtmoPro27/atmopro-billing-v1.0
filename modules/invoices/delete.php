<?php
require_once __DIR__ . '/../../includes/auth_check.php';

// Cek apakah user adalah super_admin
$is_superadmin = false;
try {
    require_once '../../config/database.php';
    $pdo = Database::getInstance()->getConnection();

    $stmt_check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    $user_data = $stmt_check->fetch();
    $is_superadmin = ($user_data && $user_data['role'] == 'super_admin');
} catch(Exception $e) {
    $is_superadmin = false;
}

if (!$is_superadmin) {
    header("Location: index.php?error=unauthorized");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: index.php?error=missing_id");
    exit();
}

try {
    require_once '../../config/database.php';
    $pdo = Database::getInstance()->getConnection();

    // Ambil data invoice sebelum dihapus untuk log
    $stmt_inv = $pdo->prepare("SELECT i.*, p.nama_lengkap, p.id_pelanggan FROM invoice i LEFT JOIN pelanggan p ON i.pelanggan_id = p.id WHERE i.id = ?");
    $stmt_inv->execute([$id]);
    $invoice = $stmt_inv->fetch();

    if (!$invoice) {
        header("Location: index.php?error=not_found");
        exit();
    }

    $no_invoice = $invoice['no_invoice'];
    $customer_name = $invoice['nama_lengkap'] ?? 'Unknown';
    $jumlah = $invoice['jumlah'];

    // Hapus invoice
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE id = ?");
    $stmt->execute([$id]);

    // Catat log aktivitas
    include '../../includes/activity_log.php';
    log_admin_activity($pdo, 'INVOICE_DELETE', 'invoice', $no_invoice, "Menghapus invoice $no_invoice untuk pelanggan: $customer_name dengan jumlah Rp " . number_format($jumlah, 0, ',', '.'));

    header("Location: index.php?delete=1");
    exit();

} catch(PDOException $e) {
    header("Location: index.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
