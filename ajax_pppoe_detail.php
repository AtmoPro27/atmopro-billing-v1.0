<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Anda harus login";
    exit;
}

set_time_limit(15);
$start_time = microtime(true);

try {
    require_once __DIR__ . "/config/database.php";
    require_once __DIR__ . "/includes/routeros_api_new.php";
    $pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->query("SELECT id_pelanggan, nama_lengkap, id FROM pelanggan WHERE status = 'active'");
    $billing_users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $billing_users[$row['id_pelanggan']] = [
            'nama_lengkap' => $row['nama_lengkap'],
            'id' => $row['id']
        ];
    }

    $pppoe_users = [];
    $router_info = [];

    $stmt = $pdo->query("SELECT id, nama_router, ip_address, username, password, port_api FROM routers WHERE status = 'active'");
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($routers)) {
        echo '<div class="alert alert-warning">Tidak ada router aktif</div>';
    } else {
        foreach ($routers as $router) {
            $ip = $router['ip_address'];
            $user = $router['username'];
            $pass = $router['password'];
            $port = (int)($router['port_api'] ?? 8730);
            $router_name = $router['nama_router'];

            $API = new RouterosAPI();
            $API->debug = false;
            $API->timeout = 5;
            $API->port = $port;

            $router_users = 0;

            if ($API->connect($ip, $user, $pass)) {
                $API->write('/ppp/active/print');
                $actives = $API->read();
                $API->disconnect();

                foreach ($actives as $active) {
                    $username = $active['name'] ?? '';
                    if (isset($billing_users[$username])) {
                        $pppoe_users[] = [
                            'username' => $username,
                            'nama_lengkap' => $billing_users[$username]['nama_lengkap'],
                            'id_pelanggan' => $username,
                            'router_name' => $router_name,
                            'router_ip' => $ip
                        ];
                        $router_users++;
                    }
                }
            }

            $router_info[] = [
                'name' => $router_name,
                'ip' => $ip,
                'total' => $router_users
            ];
        }
    }

    echo '<div class="alert alert-success">Total User Online: <strong>' . count($pppoe_users) . '</strong></div>';

    if (!empty($pppoe_users)) {
        echo '<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-light" style="position: sticky; top: 0;">
                <tr><th>ID</th><th>Nama</th><th>Router</th><th>Status</th></tr>
            </thead>
            <tbody>';
        foreach ($pppoe_users as $user) {
            echo '<tr>
                <td>' . htmlspecialchars($user['id_pelanggan']) . '</td>
                <td>' . htmlspecialchars($user['nama_lengkap']) . '</td>
                <td>' . htmlspecialchars($user['router_name']) . '</td>
                <td><span class="badge bg-success">Online</span></td>
            </tr>';
        }
        echo '</tbody></table></div>';
    }

} catch(PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>
