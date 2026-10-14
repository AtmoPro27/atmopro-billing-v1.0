<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/routeros_api_new.php';

$pdo = Database::getInstance()->getConnection();
date_default_timezone_set('Asia/Jakarta');

echo date('[Y-m-d H:i:s]') . " START - Update Online Status\n";

$stmt = $pdo->query("
    SELECT id, nama_router, ip_address, port_api, username, password 
    FROM routers 
    WHERE status = 'active'
");
$routers = $stmt->fetchAll();

$all_online = [];
$router_success = [];   // router yang berhasil diquery
$router_down = [];      // router yang benar-benar down (ping gagal)

foreach ($routers as $router) {
    $ip = $router['ip_address'];
    $port = (int)($router['port_api'] ?? 8730);
    $user = $router['username'];
    $pass = $router['password'];
    $router_online = 0;

    echo "\n[{$router['nama_router']}] $ip:$port\n";

    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 15; // naikkan timeout biar tidak sering gagal
    $API->port = $port;

    // ============================================================
    // COBA KONEK API
    // ============================================================
    if (!$API->connect($ip, $user, $pass)) {
        
        echo "  ⚠️  API gagal konek. Cek ping...\n";
        
        // Cek ping ke router
        $pingResult = cekPingRouter($ip, 5); // 5 detik timeout
        
        if ($pingResult) {
            // Router hidup, tapi API gagal → skip, JANGAN sentuh pelanggan
            echo "  ⚠️  Ping OK tapi API gagal — SKIP (tidak update pelanggan di router ini)\n";
            continue;
        } else {
            // Router benar-benar down → set pelanggan di router ini offline
            echo "  ❌ Ping GAGAL — router dianggap DOWN\n";
            $router_down[] = $router['id'];
            continue;
        }
    }

    // ============================================================
    // API BERHASIL → ambil data PPPoE active
    // ============================================================
    $API->write('/ppp/active/print');
    $actives = $API->read();
    $API->disconnect();

    $router_success[] = $router['id'];
    
    foreach ($actives as $active) {
        $username = $active['name'] ?? '';
        if ($username && !in_array(strtolower($username), ["", "-", "--", "---", "default", "admin", "test"])) {
            $all_online[] = $username;
            $router_online++;
        }
    }
    
    echo "  ✅ Berhasil — $router_online pelanggan online\n";
}

$all_online = array_unique($all_online);

echo "\n" . date('[Y-m-d H:i:s]') . " Ringkasan:\n";
echo "  Router berhasil: " . count($router_success) . "\n";
echo "  Router down: " . count($router_down) . "\n";
echo "  Total pelanggan online: " . count($all_online) . "\n";

// ============================================================
// UPDATE PELANGGAN
// ============================================================

$total_updated_online = 0;
$total_updated_offline = 0;

// --- 1. Update pelanggan yang ONLINE ---
if (!empty($all_online)) {
    $placeholders = rtrim(str_repeat('?,', count($all_online)), ',');
    $stmt = $pdo->prepare("
        UPDATE pelanggan 
        SET is_online = 1 
        WHERE id_pelanggan IN ($placeholders)
          AND status = 'active'
          AND status_aktif = 'aktif'
    ");
    $stmt->execute($all_online);
    $total_updated_online = $stmt->rowCount();
}

// --- 2. Update pelanggan yang OFFLINE (hanya di router yang berhasil) ---
if (!empty($router_success)) {
    $router_ids = implode(',', array_map('intval', $router_success));
    
    // Ambil pelanggan yang router-nya berhasil diquery
    $stmt = $pdo->query("
        SELECT p.id_pelanggan 
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON pk.id = p.paket_id
        WHERE pk.router_id IN ($router_ids)
          AND p.status = 'active'
          AND p.status_aktif = 'aktif'
    ");
    $pelanggan_target = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($pelanggan_target)) {
        // Cari yang is_online = 1 tapi tidak ada di daftar PPPoE active → set 0
        if (!empty($all_online)) {
            $target_placeholders = rtrim(str_repeat('?,', count($pelanggan_target)), ',');
            $online_placeholders = rtrim(str_repeat('?,', count($all_online)), ',');
            
            $params = array_merge($pelanggan_target, $all_online);
            
            $stmt = $pdo->prepare("
                UPDATE pelanggan 
                SET is_online = 0 
                WHERE id_pelanggan IN ($target_placeholders)
                  AND id_pelanggan NOT IN ($online_placeholders)
                  AND is_online = 1
            ");
            $stmt->execute($params);
            $total_updated_offline = $stmt->rowCount();
        } else {
            // Tidak ada yang online → semua pelanggan di router berhasil di-set 0
            $target_placeholders = rtrim(str_repeat('?,', count($pelanggan_target)), ',');
            $stmt = $pdo->prepare("
                UPDATE pelanggan 
                SET is_online = 0 
                WHERE id_pelanggan IN ($target_placeholders)
                  AND is_online = 1
            ");
            $stmt->execute($pelanggan_target);
            $total_updated_offline = $stmt->rowCount();
        }
    }
}

// --- 3. Update pelanggan di router yang DOWN (set offline) ---
if (!empty($router_down)) {
    $router_ids_down = implode(',', array_map('intval', $router_down));
    
    $stmt = $pdo->prepare("
        UPDATE pelanggan p
        LEFT JOIN paket_internet pk ON pk.id = p.paket_id
        SET p.is_online = 0
        WHERE pk.router_id IN ($router_ids_down)
          AND p.status = 'active'
          AND p.status_aktif = 'aktif'
          AND p.is_online = 1
    ");
    $stmt->execute();
    $total_down_update = $stmt->rowCount();
    echo "  Router down → {$total_down_update} pelanggan di-set offline\n";
}

echo "  Update online: {$total_updated_online} | Update offline: {$total_updated_offline}\n";
echo date('[Y-m-d H:i:s]') . " SELESAI\n";


// ============================================================
// HELPER: Cek ping ke router
// ============================================================
function cekPingRouter($ip, $timeout = 5) {
    // Coba fsockopen ke port 80/22/23 — atau kirim ICMP
    // ICMP biasanya butuh root, jadi pakai exec ping
    
    $cmd = "ping -c 1 -W $timeout " . escapeshellarg($ip) . " 2>&1";
    $output = shell_exec($cmd);
    
    // Cek apakah ada "1 received" atau "1 packets received"
    if (strpos($output, '1 received') !== false 
        || strpos($output, '1 packets received') !== false
        || strpos($output, '1 received,') !== false) {
        return true;
    }
    return false;
}
