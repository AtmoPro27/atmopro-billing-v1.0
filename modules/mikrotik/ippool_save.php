<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $router_id = $_POST['router_id'];
    $name = $_POST['name'];
    $ranges = $_POST['ranges'];
    $next_pool = $_POST['next_pool'] ?? null;
    
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
        
        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE mikrotik_ippool SET name = ?, ranges = ?, next_pool = ? WHERE id = ?");
            $stmt->execute([$name, $ranges, $next_pool, $id]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO mikrotik_ippool (router_id, name, ranges, next_pool) VALUES (?, ?, ?, ?)");
            $stmt->execute([$router_id, $name, $ranges, $next_pool]);
        }
        
        header("Location: ippool.php?router_id=" . $router_id . "&success=1");
    } catch(PDOException $e) {
        header("Location: ippool.php?router_id=" . $router_id . "&error=1");
    }
}
?>
