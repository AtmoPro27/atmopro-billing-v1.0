<?php
require_once __DIR__ . '/../../includes/auth_check.php';
include '../../includes/activity_log.php';

$id = $_GET['id'] ?? '';
$status = $_GET['status'] ?? '';

if (empty($id) || empty($status)) {
    header("Location: index.php");
    exit();
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    header("Location: view.php?id=" . $id);
    exit();
} catch(PDOException $e) {
    header("Location: index.php?error=1");
    exit();
}
?>
