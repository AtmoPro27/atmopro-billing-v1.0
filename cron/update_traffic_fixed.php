<?php
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/routeros_api_new.php";

$pdo = Database::getInstance()->getConnection();

echo date('[Y-m-d H:i:s]') . " - Memulai update traffic (via API)\n";

// Periode quota: 21 bulan ini - 20 bulan depan
if ((int)date("d") >= 21) {
    $bulan = date("Y-m");
} else {
    $bulan = date("Y-m", strtotime("-1 month"));
}

// Ambil semua router aktif
$stmt = $pdo->query("
    SELECT id, nama_router, ip_address, username, password, port_api
    FROM routers WHERE status = 'active'
");
$routers = $stmt->fetchAll();

$updated = 0;
$failed = 0;
$skipped = 0;

foreach ($routers as $router) {
    $ip = $router['ip_address'];
    $user = $router['username'];
    $pass = $router['password'];
    $port = (int)($router['port_api'] ?? 8730);
    $router_name = $router['nama_router'];

    echo "\n[{$router_name}] Koneksi ke $ip:$port\n";

    // Koneksi API
    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 10;
    $API->port = $port;

    if (!$API->connect($ip, $user, $pass)) {
        echo "  ❌ Gagal koneksi API\n";
        $failed++;
        continue;
    }

    // Ambil semua interface PPPoE sekaligus (batch!)
    $API->write('/interface/print', false);
    $API->write('?type=pppoe-in');
    $interfaces = $API->read();

    // Buat map: name → {tx-byte, rx-byte}
    $traffic_map = [];
    foreach ($interfaces as $intf) {
        $name = $intf['name'] ?? '';
        // Name format: <pppoe-username>
        if (preg_match('/<pppoe-(.+)>/', $name, $matches)) {
            $username = $matches[1];
            $traffic_map[$username] = [
                'tx' => (int)($intf['tx-byte'] ?? 0),
                'rx' => (int)($intf['rx-byte'] ?? 0),
            ];
        }
    }

    $API->disconnect();

    echo "  Ditemukan " . count($traffic_map) . " interface PPPoE\n";

    // Ambil pelanggan yang pakai router ini
    $stmt2 = $pdo->prepare("
        SELECT p.id, p.id_pelanggan, p.last_traffic
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        WHERE p.status = 'active' AND pk.router_id = ?
    ");
    $stmt2->execute([$router['id']]);
    $customers = $stmt2->fetchAll();

    foreach ($customers as $cust) {
        $username = $cust['id_pelanggan'];
        $last_traffic = (int)$cust['last_traffic'];

        if (!isset($traffic_map[$username])) {
            $skipped++;
            continue;
        }

        $current_traffic = $traffic_map[$username]['tx'] + $traffic_map[$username]['rx'];

        // Offline check
        if ($current_traffic == 0 && $last_traffic > 0) {
            $skipped++;
            continue;
        }

        if ($last_traffic == 0) {
            // Inisialisasi pertama
            $pdo->prepare("UPDATE pelanggan SET last_traffic = ? WHERE id = ?")
                ->execute([$current_traffic, $cust['id']]);
            echo "  Init {$username}: {$current_traffic} bytes\n";
        } else {
            $delta = $current_traffic - $last_traffic;
            if ($delta > 0) {
                // Update traffic bulan ini
                $check = $pdo->prepare("SELECT id FROM pelanggan_traffic WHERE pelanggan_id = ? AND bulan = ?");
                $check->execute([$cust['id'], $bulan]);

                if ($check->fetch()) {
                    $pdo->prepare("UPDATE pelanggan_traffic SET total_bytes = total_bytes + ?, last_update = NOW() WHERE pelanggan_id = ? AND bulan = ?")
                        ->execute([$delta, $cust['id'], $bulan]);
                } else {
                    $pdo->prepare("INSERT INTO pelanggan_traffic (pelanggan_id, total_bytes, bulan, last_update) VALUES (?, ?, ?, NOW())")
                        ->execute([$cust['id'], $delta, $bulan]);
                }

                $pdo->prepare("UPDATE pelanggan SET last_traffic = ? WHERE id = ?")
                    ->execute([$current_traffic, $cust['id']]);
                $updated++;
            } elseif ($delta < 0) {
                // Traffic turun (Mikrotik restart)
                $pdo->prepare("UPDATE pelanggan SET last_traffic = ? WHERE id = ?")
                    ->execute([$current_traffic, $cust['id']]);
            }
        }
    }
}

echo "\n" . date('[Y-m-d H:i:s]') . " Selesai! Updated: $updated, Skipped: $skipped, Failed: $failed\n";
?>
