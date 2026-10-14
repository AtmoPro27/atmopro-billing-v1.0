<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$id = $_GET['id'] ?? '';
$router_id = $_GET['router_id'] ?? '';

if ($id) {
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
        
        $stmt = $pdo->prepare("DELETE FROM mikrotik_ippool WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: ippool.php?router_id=" . $router_id . "&delete=1");
    } catch(PDOException $e) {
        header("Location: ippool.php?router_id=" . $router_id . "&error=1");
    }
}
?>
