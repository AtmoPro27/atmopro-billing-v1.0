<?php
// Konfigurasi cookie SEBELUM session_start
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

require_once __DIR__ . '/config/database.php';
$pdo = Database::getInstance()->getConnection();

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header("Location: index.php?error=empty");
    exit();
}

$stmt = $pdo->prepare("SELECT id, username, fullname, password, role, is_active FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    if ($user['is_active'] != 1) {
        header("Location: index.php?error=inactive");
        exit();
    }

    // 🔒 Cegah session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_name']     = $user['fullname'] ?? $user['username'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['login_time']    = time();   // untuk absolute timeout
    $_SESSION['last_activity'] = time();   // untuk idle timeout

    header("Location: dashboard_fix.php");
    exit();
} else {
    header("Location: index.php?error=invalid");
    exit();
}
