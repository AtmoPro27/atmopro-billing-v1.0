<?php
/**
 * Auth Check untuk API / AJAX Endpoint
 * Return JSON 401 kalau unauthorized / idle timeout.
 * 
 * Cara pakai:
 *   require_once __DIR__ . '/../includes/auth_check_api.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kalau belum login → 401 JSON
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'unauthorized', 'message' => 'Anda harus login']);
    exit;
}

$now = time();

// Cek idle timeout (30 menit)
if (!empty($_SESSION['last_activity']) 
    && ($now - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'idle_timeout', 'message' => 'Sesi berakhir, silakan login ulang']);
    exit;
}

// Update last_activity
$_SESSION['last_activity'] = $now;
