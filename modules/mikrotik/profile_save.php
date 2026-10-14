<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $router_id = $_POST['router_id'];
    $name = $_POST['name'];
    $local_address = $_POST['local_address'] ?? null;
    $remote_address = $_POST['remote_address'] ?? null;
    $dns_server = $_POST['dns_server'] ?? null;
    $rate_limit = $_POST['rate_limit'] ?? null;
    $session_timeout = $_POST['session_timeout'] ?? null;
    $idle_timeout = $_POST['idle_timeout'] ?? null;
    $use_encryption = $_POST['use_encryption'] ?? 'yes';
    
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
        
        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE mikrotik_ppp_profile SET name = ?, local_address = ?, remote_address = ?, dns_server = ?, rate_limit = ?, session_timeout = ?, idle_timeout = ?, use_encryption = ? WHERE id = ?");
            $stmt->execute([$name, $local_address, $remote_address, $dns_server, $rate_limit, $session_timeout, $idle_timeout, $use_encryption, $id]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO mikrotik_ppp_profile (router_id, name, local_address, remote_address, dns_server, rate_limit, session_timeout, idle_timeout, use_encryption) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$router_id, $name, $local_address, $remote_address, $dns_server, $rate_limit, $session_timeout, $idle_timeout, $use_encryption]);
        }
        
        header("Location: profile.php?router_id=" . $router_id . "&success=1");
    } catch(PDOException $e) {
        header("Location: profile.php?router_id=" . $router_id . "&error=1");
    }
}
?>
