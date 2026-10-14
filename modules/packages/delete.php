<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("DELETE FROM paket_internet WHERE id = ?");
    $stmt->execute([$id]);
    
    header("Location: index.php?delete=1");
    exit();
} catch(PDOException $e) {
    header("Location: index.php?error=1");
    exit();
}
?>
