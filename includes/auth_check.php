<?php
/**
 * Auth Check + Idle Timeout
 * Include file ini di halaman yang butuh login.
 * 
 * Cara pakai:
 *   require_once __DIR__ . '/includes/auth_check.php';   (dari root)
 *   require_once __DIR__ . '/../includes/auth_check.php'; (dari modules/xxx/)
 */

// Set cookie params (security)
if (session_status() === PHP_SESSION_NONE) {
    ini_set("session.cookie_httponly", 1);
    ini_set("session.cookie_samesite", "Lax");
    ini_set("session.use_strict_mode", 1);
    session_start();
}

// Konfigurasi timeout
define('IDLE_TIMEOUT', 1800);       // 30 menit idle → logout
define('ABSOLUTE_TIMEOUT', 28800);  // 8 jam total → logout paksa

// Kalau belum login → tendang ke halaman login
if (empty($_SESSION['user_id'])) {
    header('Location: /index.php?error=session_expired');
    exit;
}

$now = time();

// 1. Cek absolute timeout (8 jam)
if (!empty($_SESSION['login_time']) 
    && ($now - $_SESSION['login_time']) > ABSOLUTE_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: /index.php?error=absolute_timeout');
    exit;
}

// 2. Cek idle timeout (30 menit)
// Hanya cek untuk request halaman biasa, BUKAN AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax 
    && !empty($_SESSION['last_activity']) 
    && ($now - $_SESSION['last_activity']) > IDLE_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: /index.php?error=idle_timeout');
    exit;
}

// 3. Update waktu aktivitas terakhir (hanya request halaman)
if (!$is_ajax) {
    $_SESSION['last_activity'] = $now;
}
