<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Portal Pelanggan - <?= $company_name ?? 'DEMO COMPANY' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/portal/manifest.json">
    <meta name="theme-color" content="#0d5c1e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DEMO COMPANY">
    <link rel="apple-touch-icon" href="/portal/icons/icon-192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/portal/icons/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/portal/icons/icon-512.png">
    <?php if (!empty($company_favicon)): ?><link rel="icon" type="image/x-icon" href="/<?= htmlspecialchars($company_favicon) ?>"><?php endif; ?>
    
    <!-- PWA - Service Worker Registration -->
    <script>
    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/portal/sw.js')
                .then(function(registration) {
                    console.log('✅ Service Worker registered successfully:', registration);
                })
                .catch(function(error) {
                    console.log('❌ Service Worker registration failed:', error);
                });
        });
    }

    // Cek update service worker
    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', function() {
        if (refreshing) return;
        refreshing = true;
        window.location.reload();
    });
    </script>
    
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', sans-serif;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #0d5c1e, #0a4a16);
            color: white;
        }
        .navbar-custom .navbar-brand {
            color: white;
            font-weight: bold;
        }
        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.9);
        }
        .navbar-custom .nav-link:hover {
            color: white;
        }
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        .user-dropdown-btn {
            background: transparent;
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
        }
        .user-dropdown-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-radius: 8px;
            z-index: 1000;
            margin-top: 5px;
        }
        .user-dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 14px;
        }
        .user-dropdown-content a:hover {
            background: #f5f5f5;
        }
        .user-dropdown-content .text-danger {
            color: #dc3545;
        }
        .show {
            display: block;
        }
        .footer {
            text-align: center;
            padding: 15px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #e0e0e0;
            margin-top: 20px;
            background: white;
        }
    </style>
</head>
<body>
<?php
// Ambil pengaturan perusahaan dari database
require_once __DIR__ . '/../config/database.php';
$pdo_settings = Database::getInstance()->getConnection();

$settings = [];
try {
    $stmt = $pdo_settings->query("SELECT key_setting, value FROM pengaturan");
    while ($row = $stmt->fetch()) {
        $settings[$row['key_setting']] = $row['value'];
    }
} catch(PDOException $e) {
    $settings = [];
}
$company_name = $settings['company_name'] ?? 'DEMO COMPANY';
$company_tagline = $settings['company_tagline'] ?? 'HIGH SPEED INTERNET CONNECTION';
$company_favicon = $settings['company_favicon'] ?? '';

?>
<nav class="navbar navbar-expand-lg navbar-custom">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-wifi"></i> <?= htmlspecialchars($company_name) ?> Portal
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a class="nav-link" href="tagihan.php"><i class="fas fa-file-invoice"></i> Tagihan</a>
            <a class="nav-link" href="tiket.php"><i class="fas fa-ticket-alt"></i> Tiket</a>
            <div class="user-dropdown">
                <button class="user-dropdown-btn" onclick="toggleUserDropdown()">
                    <i class="fas fa-user-circle"></i> <?= $_SESSION['pelanggan_nama'] ?? 'User' ?>
                    <i class="fas fa-chevron-down" style="font-size: 12px; margin-left: 5px;"></i>
                </button>
                <div class="user-dropdown-content" id="userDropdownContent">
                    <a href="profil.php"><i class="fas fa-user-edit"></i> Profil</a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>
<div class="container">
<script>
function toggleUserDropdown() {
    var dropdown = document.getElementById('userDropdownContent');
    dropdown.classList.toggle('show');
}
window.onclick = function(event) {
    if (!event.target.matches('.user-dropdown-btn') && !event.target.closest('.user-dropdown-btn')) {
        var dropdowns = document.getElementsByClassName('user-dropdown-content');
        for (var i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) {
                dropdowns[i].classList.remove('show');
            }
        }
    }
}
</script>
