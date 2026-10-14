<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Anda harus login";
    exit;
}

// Update cache di background
// Update cache di background — exec disabled
// exec('/usr/bin/php ' . BASE_PATH . '/ajax/update_pppoe_cache_direct.php > /dev/null 2>&1 &');

echo '<style>
    .modal-body, .alert, .table, .list-group-item { font-size: 14px; }
    .table-sm td, .table-sm th { padding: 8px; }
</style>';

set_time_limit(15);
$start_time = microtime(true);

try {
    require_once __DIR__ . "/../config/database.php";
    require_once __DIR__ . "/../includes/routeros_api_new.php";
    $pdo = Database::getInstance()->getConnection();

    // Ambil pelanggan aktif (untuk filter)
    $stmt = $pdo->query("SELECT id_pelanggan, nama_lengkap, id FROM pelanggan WHERE status IN ('active', 'aktif')");
    $billing_users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $billing_users[$row['id_pelanggan']] = [
            'nama_lengkap' => $row['nama_lengkap'],
            'id' => $row['id']
        ];
    }

    $pppoe_users = [];
    $router_info = [];

    // Ambil semua router
    $stmt = $pdo->query("SELECT id, nama_router, ip_address, username, password, port_api FROM routers WHERE status = 'active'");
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($routers)) {
        echo '<div class="alert alert-warning">Tidak ada router aktif</div>';
    } else {
        foreach ($routers as $router) {
            $ip = $router['ip_address'];
            $user = $router['username'];
            $pass = $router['password'];
            $router_name = $router['nama_router'];
            $router_id = $router['id'];
            $port = (int)($router['port_api'] ?? 8730);

            $router_users = 0;
            $router_user_list = [];

            // Koneksi API
            $API = new RouterosAPI();
            $API->debug = false;
            $API->timeout = 10;
            $API->port = $port;

            if ($API->connect($ip, $user, $pass)) {
                $API->write('/ppp/active/print');
                $actives = $API->read();
                $API->disconnect();

                foreach ($actives as $active) {
                    $username = $active['name'] ?? '';

                    if (empty($username)) continue;

                    if (isset($billing_users[$username])) {
                        $key = $username . '|' . $router_name;
                        if (!isset($pppoe_users[$key])) {
                            $pppoe_users[$key] = [
                                'username' => $username,
                                'nama_lengkap' => $billing_users[$username]['nama_lengkap'],
                                'id_pelanggan' => $username,
                                'router_name' => $router_name,
                                'router_ip' => $ip
                            ];
                            $router_users++;
                            $router_user_list[] = $username;
                        }
                    }
                }
            }

            $router_info[] = [
                'name' => $router_name,
                'ip' => $ip,
                'total' => $router_users
            ];

            $status = $router_users > 0 ? 'online' : 'offline';
            $stmt_up = $pdo->prepare("UPDATE routers SET connection_status = ?, last_check = NOW() WHERE id = ?");
            $stmt_up->execute([$status, $router_id]);
        }
    }

    $execution_time = round((microtime(true) - $start_time) * 1000);
    $total_users = count($pppoe_users);

    echo '<div class="alert alert-info" style="font-size:13px;">';
    echo '<strong><i class="fas fa-server"></i> Sumber Data (Router):</strong><br>';
    foreach ($router_info as $router) {
        echo '• ' . htmlspecialchars($router['name']) . ' (' . htmlspecialchars($router['ip']) . ') - <strong>' . $router['total'] . ' user</strong><br>';
    }
    echo '</div>';

    echo '<div class="alert alert-success">Total User Online: <strong>' . $total_users . '</strong> koneksi PPPoE (dari billing)</div>';

    if ($total_users > 0) {
        echo '<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-light" style="position: sticky; top: 0;">
                <tr>
                    <th>ID Pelanggan</th>
                    <th>Nama Pelanggan</th>
                    <th>Username</th>
                    <th>Router</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($pppoe_users as $user) {
            echo '<tr>
                <td>' . htmlspecialchars($user['id_pelanggan']) . '</td>
                <td>' . htmlspecialchars($user['nama_lengkap']) . '</td>
                <td>' . htmlspecialchars($user['username']) . '</td>
                <td>' . htmlspecialchars($user['router_name']) . '</td>
                <td><span class="badge bg-success">Online</span></td>
            </tr>';
        }

        echo '</tbody>
        </table>
        </div>';
        echo '<small class="text-muted">⏱️ Waktu loading: ' . $execution_time . ' ms</small>';
    } else {
        echo '<div class="alert alert-info text-center">Tidak ada user PPPoE online yang terdaftar di billing</div>';
    }

} catch(PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>
