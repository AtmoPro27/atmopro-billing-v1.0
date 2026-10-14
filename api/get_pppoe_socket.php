<?php
session_start();
require_once '../config/database.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT * FROM routers WHERE status = 'active' AND connection_status = 'online' LIMIT 1");
$router = $stmt->fetch();

if (!$router) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada router online', 'users' => []]);
    exit();
}

// Simulasi data untuk demo
// Di implementasi nyata, ini akan mengambil dari Mikrotik via API
$demo_users = [
    ['username' => 'PEL001', 'ip_address' => '10.10.10.2', 'uptime' => '3h 25m', 'rx_bytes' => '125.3 MB', 'tx_bytes' => '45.2 MB'],
    ['username' => 'PEL002', 'ip_address' => '10.10.10.3', 'uptime' => '1d 2h 10m', 'rx_bytes' => '520.8 MB', 'tx_bytes' => '180.5 MB'],
    ['username' => 'PEL003', 'ip_address' => '10.10.10.4', 'uptime' => '5h 30m', 'rx_bytes' => '89.2 MB', 'tx_bytes' => '32.1 MB'],
    ['username' => 'PEL004', 'ip_address' => '10.10.10.5', 'uptime' => '12h 15m', 'rx_bytes' => '234.5 MB', 'tx_bytes' => '78.3 MB'],
    ['username' => 'PEL005', 'ip_address' => '10.10.10.6', 'uptime' => '2d 8h 45m', 'rx_bytes' => '1.2 GB', 'tx_bytes' => '450.2 MB'],
];

echo json_encode(['success' => true, 'users' => $demo_users, 'total' => count($demo_users)]);
?>
