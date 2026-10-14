<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/routeros_api_new.php";

$pdo = Database::getInstance()->getConnection();

$stmt = $pdo->query("SELECT id_pelanggan, tipe_pelanggan FROM pelanggan WHERE status = 'active'");
$billing_users = [];
$tipe_users = [];
while ($row = $stmt->fetch()) {
    $billing_users[$row['id_pelanggan']] = true;
    $tipe_users[$row['id_pelanggan']] = $row['tipe_pelanggan'] ?? 'reguler';
}

$stmt_router = $pdo->query("SELECT id, nama_router, ip_address, username, password, port_api FROM routers WHERE status = 'active'");
$routers = $stmt_router->fetchAll();

$online_users_list = [];
$online_reguler = [];
$online_khusus = [];
$online_data = [];

foreach ($routers as $router) {
    $ip = $router['ip_address'];
    $user = $router['username'];
    $pass = $router['password'];
    $port = (int)($router['port_api'] ?? 8730);

    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 10;
    $API->port = $port;

    if (!$API->connect($ip, $user, $pass)) {
        echo date('[Y-m-d H:i:s]') . " ❌ Gagal koneksi ke {$router['nama_router']}\n";
        continue;
    }

    $API->write('/ppp/active/print');
    $actives = $API->read();
    $API->disconnect();

    foreach ($actives as $active) {
        $username = $active['name'] ?? '';
        if ($username && isset($billing_users[$username])) {
            $online_users_list[] = $username;
            
            // Pisahkan reguler & khusus
            $tipe = $tipe_users[$username] ?? 'reguler';
            if (in_array($tipe, ['vip', 'fasum'])) {
                $online_khusus[] = $username;
            } else {
                $online_reguler[] = $username;
            }
            
            $online_data[$username] = [
                'mac' => $active['caller-id'] ?? '-',
                'ip' => $active['address'] ?? '-',
                'uptime' => $active['uptime'] ?? '-',
                'router' => $router['nama_router']
            ];
        }
    }
}

$online_users_list = array_unique($online_users_list);
file_put_contents('/tmp/pppoe_online_users_demo.txt', implode("\n", $online_users_list));
file_put_contents('/tmp/pppoe_user_data_demo.json', json_encode($online_data));

$total_online = count($online_users_list);
$total_reguler = count(array_unique($online_reguler));
$total_khusus = count(array_unique($online_khusus));

// Simpan cache
file_put_contents('/tmp/pppoe_online_count_demo.txt', $total_online);
file_put_contents('/tmp/pppoe_reguler_demo.txt', $total_reguler);
file_put_contents('/tmp/pppoe_khusus_demo.txt', $total_khusus);

echo date('[Y-m-d H:i:s]') . " Total PPPoE Online (Billing): $total_online (Reguler: $total_reguler, Khusus: $total_khusus)\n";
?>
