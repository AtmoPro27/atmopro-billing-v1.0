<?php
require_once __DIR__ . '/../../includes/auth_check.php';

// Hanya super_admin yang bisa mereset password
if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

$default_password = 'admin123!2025';
$hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    // Ambil data user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: index.php");
        exit();
    }
    
    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed_password, $id]);
    
    header("Location: index.php?reset=success&user=" . urlencode($user['username']));
    exit();
    
} catch(PDOException $e) {
    header("Location: index.php?error=1");
    exit();
}
?>
