<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/routeros_api_new.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query("SELECT id, ip_address, username, password, port_api FROM routers WHERE status = 'active'");
$routers = $stmt->fetchAll();
$total_online = 0;
foreach ($routers as $router) {
    $port = (int)($router['port_api'] ?? 8730);
    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 10;
    $API->port = $port;
    if (!$API->connect($router['ip_address'], $router['username'], $router['password'])) continue;
    $API->write('/ppp/active/print');
    $actives = $API->read();
    $API->disconnect();
    $total_online += count($actives);
}
header('Content-Type: application/json');
echo json_encode(['success' => true, 'count' => $total_online]);
