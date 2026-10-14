<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$router_id = $_POST['router_id'] ?? '';
if (empty($router_id)) {
    echo json_encode(['success' => false, 'message' => 'Router ID tidak ditemukan']);
    exit;
}

try {
    require_once __DIR__ . "/../../config/database.php";
    require_once __DIR__ . "/../../includes/routeros_api_new.php";
    $pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->prepare("SELECT * FROM routers WHERE id = ? AND status = 'active'");
    $stmt->execute([$router_id]);
    $router = $stmt->fetch();

    if (!$router) {
        echo json_encode(['success' => false, 'message' => 'Router tidak ditemukan atau tidak aktif']);
        exit;
    }

    $ip = $router['ip_address'];
    $port = (int)($router['port_api'] ?? 8730);
    $user = $router['username'];
    $pass = $router['password'];

    // Koneksi API
    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 10;
    $API->port = $port;

    if (!$API->connect($ip, $user, $pass)) {
        echo json_encode(['success' => false, 'message' => 'Gagal konek ke Mikrotik']);
        exit;
    }

    $messages = [];
    $successCount = 0;
    $failCount = 0;

    // 1. Bandwidth
    $stmt = $pdo->prepare("SELECT * FROM mikrotik_bandwidth WHERE router_id = ?");
    $stmt->execute([$router_id]);
    $bandwidths = $stmt->fetchAll();

    foreach ($bandwidths as $bw) {
        $API->write('/queue/simple/add', false);
        $API->write('=name=' . $bw['name'], false);
        $API->write('=target=' . $bw['target'], false);
        $API->write('=max-limit=' . $bw['max_limit_down'] . '/' . $bw['max_limit_up'], false);
        $API->write('=comment=sync_from_billing');
        $result = $API->read();
        
        if (empty($result) || !isset($result[0]['!trap'])) {
            $successCount++;
            $messages[] = "✓ Bandwidth '{$bw['name']}' OK";
        } else {
            $failCount++;
            $messages[] = "✗ Bandwidth '{$bw['name']}' gagal";
        }
    }

    // 2. IP Pool
    $stmt = $pdo->prepare("SELECT * FROM mikrotik_ippool WHERE router_id = ?");
    $stmt->execute([$router_id]);
    $ippools = $stmt->fetchAll();

    foreach ($ippools as $pool) {
        $API->write('/ip/pool/add', false);
        $API->write('=name=' . $pool['name'], false);
        $API->write('=ranges=' . $pool['ranges'], false);
        $API->write('=comment=sync_from_billing');
        $result = $API->read();
        
        if (empty($result) || !isset($result[0]['!trap'])) {
            $successCount++;
            $messages[] = "✓ IP Pool '{$pool['name']}' OK";
        } else {
            $failCount++;
            $messages[] = "✗ IP Pool '{$pool['name']}' gagal";
        }
    }

    // 3. Profil PPPoE
    $stmt = $pdo->prepare("SELECT * FROM mikrotik_ppp_profile WHERE router_id = ?");
    $stmt->execute([$router_id]);
    $profiles = $stmt->fetchAll();

    foreach ($profiles as $prof) {
        $API->write('/ppp/profile/add', false);
        $API->write('=name=' . $prof['name'], false);
        if ($prof['local_address']) $API->write('=local-address=' . $prof['local_address'], false);
        if ($prof['remote_address']) $API->write('=remote-address=' . $prof['remote_address'], false);
        if ($prof['dns_server']) $API->write('=dns-server=' . $prof['dns_server'], false);
        if ($prof['rate_limit']) $API->write('=rate-limit=' . $prof['rate_limit'], false);
        if ($prof['session_timeout']) $API->write('=session-timeout=' . $prof['session_timeout'], false);
        if ($prof['idle_timeout']) $API->write('=idle-timeout=' . $prof['idle_timeout'], false);
        $API->write('=use-encryption=' . ($prof['use_encryption'] == 'yes' ? 'yes' : 'no'), false);
        $API->write('=comment=sync_from_billing');
        $result = $API->read();
        
        if (empty($result) || !isset($result[0]['!trap'])) {
            $successCount++;
            $messages[] = "✓ Profil PPPoE '{$prof['name']}' OK";
        } else {
            $failCount++;
            $messages[] = "✗ Profil PPPoE '{$prof['name']}' gagal";
        }
    }

    $API->disconnect();

    $messageText = "Sinkronisasi selesai!\nBerhasil: $successCount\nGagal: $failCount\n\n" . implode("\n", $messages);
    echo json_encode(['success' => true, 'message' => $messageText]);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
