<?php
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/routeros_api_new.php";

$pdo = Database::getInstance()->getConnection();

// Ambil semua ID pelanggan dari database
$stmt = $pdo->query("SELECT id_pelanggan FROM pelanggan");
$customer_db = [];
while ($row = $stmt->fetch()) {
    $customer_db[$row['id_pelanggan']] = true;
}

$users_online = 0;

// Ambil semua router aktif
$stmt_router = $pdo->query("SELECT ip_address, username, password, port_api FROM routers WHERE status = 'active'");
$routers = $stmt_router->fetchAll();

foreach ($routers as $router) {
    $ip = $router['ip_address'];
    $user = $router['username'];
    $pass = $router['password'];
    $port = (int)($router['port_api'] ?? 8730);

    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 5;
    $API->port = $port;

    if (!$API->connect($ip, $user, $pass)) continue;

    $API->write('/ppp/active/print');
    $actives = $API->read();
    $API->disconnect();

    foreach ($actives as $active) {
        $username = $active['name'] ?? '';
        if (isset($customer_db[$username])) {
            $users_online++;
        }
    }
}

echo $users_online;
?>
