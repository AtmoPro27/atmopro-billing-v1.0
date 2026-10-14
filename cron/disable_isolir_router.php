#!/usr/bin/php
<?php
/**
 * Cron: Nonaktifkan secret & hapus active connection pelanggan isolir
 * VERSI API (bukan SSH)
 * 
 * UPDATE: Exclude pelanggan VIP & FASUM dari proses isolir
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/routeros_api_new.php';

$pdo = Database::getInstance()->getConnection();
date_default_timezone_set('Asia/Jakarta');

$now = date('Y-m-d H:i:s');
echo "[$now] START - Disable isolir router (API)\n";

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.id_pelanggan, p.nama_lengkap, p.tipe_pelanggan, pk.router_id
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        WHERE p.status IN ('inactive', 'blocked')
        AND p.status_aktif = 'aktif'
        AND pk.router_id IS NOT NULL
        AND (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)
    ");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($customers)) {
        echo "[$now] Tidak ada pelanggan isolir (reguler)\n";
        exit;
    }

    // Log pelanggan VIP & FASUM yang di-skip
    $stmt_skip = $pdo->query("
        SELECT p.id_pelanggan, p.nama_lengkap, p.tipe_pelanggan 
        FROM pelanggan p 
        WHERE p.tipe_pelanggan IN ('vip', 'fasum') 
        AND p.status IN ('inactive', 'blocked')
    ");
    $skip_vip_fasum = $stmt_skip->fetchAll();

    if (count($skip_vip_fasum) > 0) {
        echo "\n[SKIP] Pelanggan VIP & FASUM (tidak diisolir):\n";
        foreach ($skip_vip_fasum as $s) {
            echo "  - {$s['id_pelanggan']} - {$s['nama_lengkap']} [{$s['tipe_pelanggan']}]\n";
        }
        echo "\n";
    }

    $routers = [];
    foreach ($customers as $c) {
        $key = $c['router_id'];
        if (!isset($routers[$key])) $routers[$key] = ['customers' => []];
        $routers[$key]['customers'][] = $c;
    }

    $total_success = 0;
    $total_failed = 0;

    $stmt_r = $pdo->prepare("SELECT id, nama_router, ip_address, username, password, port_api FROM routers WHERE id = ? AND status = 'active'");

    foreach ($routers as $router_id => $data) {
        $stmt_r->execute([$router_id]);
        $router = $stmt_r->fetch(PDO::FETCH_ASSOC);

        if (!$router) {
            $total_failed += count($data['customers']);
            continue;
        }

        $ip = $router['ip_address'];
        $user = $router['username'];
        $pass = $router['password'];
        $port = (int)($router['port_api'] ?? 8730);
        $router_name = $router['nama_router'];

        echo "\n[$now] - Router: $router_name ($ip:$port)\n";

        $API = new RouterosAPI();
        $API->debug = false;
        $API->timeout = 10;
        $API->port = $port;

        if (!$API->connect($ip, $user, $pass)) {
            echo "  ❌ Gagal koneksi API\n";
            $total_failed += count($data['customers']);
            continue;
        }

        $success = 0;
        $failed = 0;

        foreach ($data['customers'] as $customer) {
            $username = $customer['id_pelanggan'];

            // Cari secret
            $API->write('/ppp/secret/print', false);
            $API->write('?name=' . $username);
            $secret_result = $API->read();

            if (empty($secret_result)) {
                $failed++;
                echo "  ⚠️ $username - tidak ditemukan\n";
                continue;
            }

            $secret_id = $secret_result[0]['.id'] ?? null;

            if ($secret_id) {
                $API->write('/ppp/secret/disable', false);
                $API->write('=.id=' . $secret_id);
                $API->read();
            }

            // Remove active connection
            $API->write('/ppp/active/print', false);
            $API->write('?name=' . $username);
            $active_result = $API->read();

            if (!empty($active_result)) {
                $active_id = $active_result[0]['.id'] ?? null;
                if ($active_id) {
                    $API->write('/ppp/active/remove', false);
                    $API->write('=.id=' . $active_id);
                    $API->read();
                }
            }

            echo "  ✅ $username - {$customer['nama_lengkap']}\n";
            $success++;
        }

        $API->disconnect();
        echo "  - Router $router_name: $success berhasil, $failed gagal\n";
        $total_success += $success;
        $total_failed += $failed;
    }

    echo "\n[$now] TOTAL: $total_success berhasil, $total_failed gagal\n";
    echo "[$now] Catatan: Pelanggan VIP & FASUM tidak diisolir (hanya monitoring)\n";

} catch(PDOException $e) {
    echo "[$now] ERROR: " . $e->getMessage() . "\n";
}
?>
