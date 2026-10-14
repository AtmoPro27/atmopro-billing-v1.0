<?php
require_once __DIR__ . '/../../includes/auth_check.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/routeros_api_new.php';

try {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID router tidak valid']);
        exit;
    }

    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("SELECT * FROM routers WHERE id = ?");
    $stmt->execute([$id]);
    $router = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$router) {
        echo json_encode(['success' => false, 'message' => 'Router tidak ditemukan']);
        exit;
    }

    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 5;

    $port = (int)($router['port_api'] ?? 8728);

    if ($API->connect($router['ip_address'], $router['username'], $router['password'], $port)) {
        $identity = $API->comm('/system/identity/print');
        $API->disconnect();

        echo json_encode([
            'success' => true,
            'message' => 'Koneksi sukses ke ' . $router['nama_router'],
            'identity' => $identity[0]['name'] ?? '-',
            'ip' => $router['ip_address'],
            'port' => $port
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal koneksi ke ' . $router['ip_address'] . ':' . $port
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
