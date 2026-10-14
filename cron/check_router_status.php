#!/usr/bin/php
<?php
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getInstance()->getConnection();
require_once __DIR__ . '/../includes/routeros_api_new.php';

// Batasi eksekusi
set_time_limit(60);

// Ambil semua router
$stmt = $pdo->query("SELECT id, nama_router, ip_address, username, password, port_api FROM routers WHERE status = 'active'");
$routers = $stmt->fetchAll();

foreach ($routers as $router) {
    $ip = $router['ip_address'];
    $user = $router['username'];
    $pass = $router['password'];
    $port = (int)($router['port_api'] ?? 8730);
    $nama_router = $router['nama_router'];
    $status = 'offline';
    $message = '';

    // === METODE 1: Cek port API via fsockopen ===
    $connection = @fsockopen($ip, $port, $errno, $errstr, 3);
    if (is_resource($connection)) {
        fclose($connection);
        
        // === METODE 2: Test koneksi API ===
        try {
            $API = new RouterosAPI();
            $API->debug = false;
            $API->timeout = 5;
            $API->port = $port;
            
            if ($API->connect($ip, $user, $pass)) {
                // Ambil identity router
                $API->write('/system/identity/print');
                $identity = $API->read();
                $API->disconnect();
                
                $router_name = $identity[0]['name'] ?? 'unknown';
                $status = 'online';
                $message = 'API OK - Identity: ' . $router_name;
            } else {
                $status = 'offline';
                $message = 'API gagal login';
            }
        } catch (Exception $e) {
            $status = 'offline';
            $message = 'API error: ' . substr($e->getMessage(), 0, 100);
        }
    } else {
        $status = 'offline';
        $message = 'Port ' . $port . ' tertutup';
    }

    // Update status
    $update = $pdo->prepare("UPDATE routers SET connection_status = ?, last_check = NOW(), last_check_message = ? WHERE id = ?");
    $update->execute([$status, $message, $router['id']]);

    echo date('[Y-m-d H:i:s]') . " - Router {$nama_router} ({$ip}:{$port}): {$status} - {$message}\n";
}

echo date('[Y-m-d H:i:s]') . " - Router status checked\n";
?>
