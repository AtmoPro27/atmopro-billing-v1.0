<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $router_id = $_POST['router_id'];
    $name = $_POST['name'];
    $target = $_POST['target'];
    $max_limit_down = $_POST['max_limit_down'];
    $max_limit_up = $_POST['max_limit_up'];
    
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
        
        $stmt = $pdo->prepare("INSERT INTO mikrotik_bandwidth (router_id, name, target, max_limit_down, max_limit_up) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$router_id, $name, $target, $max_limit_down, $max_limit_up]);
        
        header("Location: bandwidth.php?router_id=" . $router_id . "&success=1");
    } catch(PDOException $e) {
        header("Location: bandwidth.php?router_id=" . $router_id . "&error=1");
    }
}
?>
