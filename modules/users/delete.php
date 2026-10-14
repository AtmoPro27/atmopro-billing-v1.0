<?php
require_once __DIR__ . '/../../includes/auth_check.php';

// Hanya admin/super_admin yang bisa mengakses
if ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

// Cegah menghapus diri sendiri
if ($id == $_SESSION['user_id']) {
    header("Location: index.php?error=self");
    exit();
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: index.php?delete=1");
    exit();
} catch(PDOException $e) {
    header("Location: index.php?error=1");
    exit();
}
?>
