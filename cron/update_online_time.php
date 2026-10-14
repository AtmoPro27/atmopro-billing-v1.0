<?php
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/routeros_api_new.php";

$pdo = Database::getInstance()->getConnection();

// Ambil semua router aktif
$stmt_router = $pdo->query("SELECT id, nama_router, ip_address, username, password, port_api FROM routers WHERE status = 'active'");
$routers = $stmt_router->fetchAll();

foreach ($routers as $router) {
    $ip = $router['ip_address'];
    $user = $router['username'];
    $pass = $router['password'];
    $port = (int)($router['port_api'] ?? 8730);

    // Koneksi API
    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 10;
    $API->port = $port;

    if (!$API->connect($ip, $user, $pass)) {
        echo "❌ Gagal koneksi ke {$router['nama_router']}\n";
        continue;
    }

    // Ambil semua PPP active sekaligus
    $API->write('/ppp/active/print');
    $actives = $API->read();
    $API->disconnect();

    // Buat map: username → uptime (detik)
    $uptime_map = [];
    foreach ($actives as $active) {
        $username = $active['name'] ?? '';
        $uptime_raw = $active['uptime'] ?? '';
        
        $current_uptime = 0;
        if (preg_match('/(\d+)w/i', $uptime_raw, $w)) $current_uptime += $w[1] * 604800;
        if (preg_match('/(\d+)d/i', $uptime_raw, $d)) $current_uptime += $d[1] * 86400;
        if (preg_match('/(\d+)h/i', $uptime_raw, $h)) $current_uptime += $h[1] * 3600;
        if (preg_match('/(\d+)m/i', $uptime_raw, $m)) $current_uptime += $m[1] * 60;
        if (preg_match('/(\d+)s/i', $uptime_raw, $s)) $current_uptime += $s[1];
        
        $uptime_map[$username] = $current_uptime;
    }

    // Ambil pelanggan yang pakai router ini
    $stmt = $pdo->prepare("
        SELECT p.id, p.id_pelanggan, p.last_uptime
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        WHERE p.status = 'active' AND pk.router_id = ?
    ");
    $stmt->execute([$router['id']]);
    $customers = $stmt->fetchAll();

    foreach ($customers as $cust) {
        $username = $cust['id_pelanggan'];
        $last_uptime = (int)$cust['last_uptime'];

        if (!isset($uptime_map[$username])) continue;

        $current_uptime = $uptime_map[$username];

        if ($last_uptime == 0) {
            $pdo->prepare("UPDATE pelanggan SET last_uptime = ? WHERE id = ?")
                ->execute([$current_uptime, $cust['id']]);
            echo date('[Y-m-d H:i:s]') . " Init {$username}: uptime {$current_uptime}s\n";
        } else {
            $delta = $current_uptime - $last_uptime;
            if ($delta > 0 && $delta < 86400) {
                $pdo->prepare("UPDATE pelanggan SET total_online_seconds = total_online_seconds + ?, last_uptime = ? WHERE id = ?")
                    ->execute([$delta, $current_uptime, $cust['id']]);
                echo date('[Y-m-d H:i:s]') . " Updated {$username}: +{$delta}s\n";
            } elseif ($delta < 0) {
                $pdo->prepare("UPDATE pelanggan SET last_uptime = ? WHERE id = ?")
                    ->execute([$current_uptime, $cust['id']]);
                echo date('[Y-m-d H:i:s]') . " Reset {$username}: uptime turun\n";
            }
        }
    }
}
?>
