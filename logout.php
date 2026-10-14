<?php
session_start();

// Kosongkan session
$_SESSION = [];

// Hapus cookie session di browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hancurkan session di server
session_destroy();

// Redirect ke login
header("Location: /index.php?logout=1");
exit();
