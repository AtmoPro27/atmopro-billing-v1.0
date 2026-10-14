<?php
/**
 * Session Timeout Handler — PORTAL PELANGGAN
 * Auto logout setelah idle & absolute timeout
 */

define('SESSION_IDLE_TIMEOUT', 1800);
define('SESSION_ABSOLUTE_TIMEOUT', 28800);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_IDLE_TIMEOUT);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

// Cek login portal
if (!isset($_SESSION['portal_login'])) {
    header("Location: /portal/index.php");
    exit();
}

$now = time();

// Absolute timeout (8 jam)
if (isset($_SESSION['portal_login_time'])) {
    if ($now - $_SESSION['portal_login_time'] > SESSION_ABSOLUTE_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: /portal/index.php?timeout=absolute");
        exit();
    }
} else {
    $_SESSION['portal_login_time'] = $now;
}

// Idle timeout (30 menit)
if (isset($_SESSION['portal_last_activity'])) {
    if ($now - $_SESSION['portal_last_activity'] > SESSION_IDLE_TIMEOUT) {
        session_unset();
        session_destroy();
        header("Location: /portal/index.php?timeout=idle");
        exit();
    }
}

$_SESSION['portal_last_activity'] = $now;
?>
