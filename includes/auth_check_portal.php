<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['portal_login']) || empty($_SESSION['pelanggan_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'unauthorized', 'message' => 'Anda harus login']);
    exit;
}

$_SESSION['portal_last_activity'] = time();
