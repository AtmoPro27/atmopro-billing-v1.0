<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/routeros_api_new.php';

$pdo = Database::getInstance()->getConnection();

// Ambil daftar pelanggan billing aktif
$stmt = $pdo->query("SELECT id_pelanggan, tipe_pelanggan FROM pelanggan WHERE status = 'active' AND status_aktif = 'aktif'");
$billing_users = [];
$tipe_users = [];
while ($row = $stmt->fetch()) {
    $billing_users[$row['id_pelanggan']] = true;
    $tipe_users[$row['id_pelanggan']] = $row['tipe_pelanggan'] ?? 'reguler';
}

// Query router
$stmt = $pdo->query("SELECT id, ip_address, username, password, port_api FROM routers WHERE status = 'active'");
$routers = $stmt->fetchAll();

$all_online = [];
$online_reguler = [];
$online_khusus = [];

foreach ($routers as $router) {
    $port = (int)($router['port_api'] ?? 8730);
    
    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 10;
    $API->port = $port;
    
    if (!$API->connect($router['ip_address'], $router['username'], $router['password'])) {
        continue;
    }
    
    $API->write('/ppp/active/print');
    $actives = $API->read();
    $API->disconnect();
    
    foreach ($actives as $a) {
        $username = $a['name'] ?? '';
        // FILTER: hanya pelanggan billing aktif
        if ($username && isset($billing_users[$username])) {
            $all_online[] = $username;
            
            $tipe = $tipe_users[$username] ?? 'reguler';
            if (in_array($tipe, ['vip', 'fasum'])) {
                $online_khusus[] = $username;
            } else {
                $online_reguler[] = $username;
            }
        }
    }
}

$all_online = array_unique($all_online);

$all_online = array_unique($all_online);
$online_reguler = array_unique($online_reguler);
$online_khusus = array_unique($online_khusus);

file_put_contents('/tmp/pppoe_online_users_demo.txt', implode("\n", $all_online));
file_put_contents('/tmp/pppoe_online_count_demo.txt', count($all_online));
file_put_contents('/tmp/pppoe_reguler_demo.txt', count($online_reguler));
file_put_contents('/tmp/pppoe_khusus_demo.txt', count($online_khusus));

header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'total' => count($all_online),
    'reguler' => count($online_reguler),
    'khusus' => count($online_khusus)
]);
