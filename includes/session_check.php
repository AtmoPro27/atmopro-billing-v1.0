<?php
/**
 * Session Timeout Handler
 * Auto logout setelah idle & absolute timeout
 */

// Konfigurasi timeout
define('SESSION_IDLE_TIMEOUT', 1800);      // 30 menit idle
define('SESSION_ABSOLUTE_TIMEOUT', 28800); // 8 jam absolute

// Set session cookie params (sebelum session_start)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_IDLE_TIMEOUT);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    // Cookie lifetime = 0 (sampai browser ditutup)
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

// Cek login
if (!isset($_SESSION['user_id'])) {
    // Tidak login, redirect ke login
    header("Location: /index.php");
    exit();
}

$now = time();

// Cek absolute timeout (8 jam dari login pertama)
if (isset($_SESSION['login_time'])) {
    if ($now - $_SESSION['login_time'] > SESSION_ABSOLUTE_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: /index.php?timeout=absolute");
        exit();
    }
} else {
    // Set login_time kalau belum ada
    $_SESSION['login_time'] = $now;
}

// Cek idle timeout (30 menit dari aktivitas terakhir)
if (isset($_SESSION['last_activity'])) {
    if ($now - $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: /index.php?timeout=idle");
        exit();
    }
}

// Update last activity
$_SESSION['last_activity'] = $now;
?>
