<?php
require_once __DIR__ . '/../../includes/auth_check.php';

// Hanya admin yang bisa hapus tiket
if ($_SESSION['user_role'] != 'super_admin' && $_SESSION['user_role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: index.php?delete=1");
    exit();
} catch(PDOException $e) {
    header("Location: index.php?error=1");
    exit();
}
?>
