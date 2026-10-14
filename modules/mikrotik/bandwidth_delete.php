<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$id = $_GET['id'] ?? '';
$router_id = $_GET['router_id'] ?? '';
if (empty($id) || empty($router_id)) {
    header("Location: index.php");
    exit();
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("DELETE FROM mikrotik_bandwidth WHERE id = ? AND router_id = ?");
    $stmt->execute([$id, $router_id]);
    
    // Redirect dengan JavaScript
    echo "<script>window.location.href = 'bandwidth.php?router_id=" . $router_id . "&delete=1';</script>";
    exit();
} catch(PDOException $e) {
    echo "<script>window.location.href = 'bandwidth.php?router_id=" . $router_id . "&error=1';</script>";
    exit();
}
?>
