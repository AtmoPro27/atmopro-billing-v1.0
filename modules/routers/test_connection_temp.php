<?php
require_once __DIR__ . '/../../includes/auth_check.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/routeros_api_new.php';

try {
    $ip = trim($_POST['ip_address'] ?? '');
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $port = (int)($_POST['port_api'] ?? 8728);

    if (!$ip || !$user) {
        echo json_encode(['success' => false, 'message' => 'IP dan username wajib diisi']);
        exit;
    }

    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 5;

    if ($API->connect($ip, $user, $pass, $port)) {
        $identity = $API->comm('/system/identity/print');
        $API->disconnect();

        echo json_encode([
            'success' => true,
            'message' => 'Koneksi sukses!',
            'identity' => $identity[0]['name'] ?? '-',
            'ip' => $ip,
            'port' => $port
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal koneksi ke ' . $ip . ':' . $port
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
