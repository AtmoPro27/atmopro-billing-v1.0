<?php
require_once __DIR__ . '/../../includes/auth_check.php';

require_once '../../includes/settings_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'unknown';
    
    $settings = [
        'genieacs_url' => trim($_POST['genieacs_url'] ?? ''),
        'genieacs_username' => trim($_POST['genieacs_username'] ?? ''),
        'genieacs_password' => $_POST['genieacs_password'] ?? '',
        'genieacs_timeout' => (int)($_POST['genieacs_timeout'] ?? 30),
    ];

    $changed = 0;
    foreach ($settings as $key => $value) {
        if (saveSetting($key, $value, $userId)) {
            $changed++;
        }
    }

    $_SESSION['success_message'] = "✅ Setting GenieACS disimpan! ($changed perubahan)";
} else {
    $_SESSION['error_message'] = '❌ Metode tidak valid';
}

header("Location: index.php");
exit();
