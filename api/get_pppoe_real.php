<?php
require_once __DIR__ . '/../includes/auth_check_api.php';
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../lib/RouterOS.php';

use RouterOS\Client;
use RouterOS\Query;

$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT * FROM routers WHERE status = 'active'");
$routers = $stmt->fetchAll();

$all_users = [];

foreach ($routers as $router) {
    try {
        $client = new Client([
            'host' => $router['ip_address'],
            'port' => $router['port_api'],
            'user' => $router['username'],
            'pass' => $router['password']
        ]);
        
        $query = (new Query('/interface/pppoe-server/active/print'));
        $response = $client->query($query)->read();
        
        foreach ($response as $item) {
            $all_users[] = [
                'username' => $item['name'],
                'ip_address' => $item['caller-id'],
                'uptime' => $item['uptime'],
                'router' => $router['nama_router'],
                'router_ip' => $router['ip_address']
            ];
        }
    } catch (Exception $e) {
        // Router offline atau error
    }
}

echo json_encode(['success' => true, 'users' => $all_users, 'total' => count($all_users)]);
?>
