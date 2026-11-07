<?php date_default_timezone_set("Asia/Jakarta"); ?>
<?php
// Ambil pengaturan dari database
if (!isset($settings)) {
    try {
        require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->query("SELECT key_setting, value FROM pengaturan");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key_setting']] = $row['value'];
        }
    } catch(PDOException $e) {
        $settings = [];
    }
}

$company_name = isset($settings['company_name']) && is_string($settings['company_name']) ? $settings['company_name'] : 'AtmoBilling';
$company_tagline = isset($settings['company_tagline']) && is_string($settings['company_tagline']) ? $settings['company_tagline'] : 'HIGH SPEED INTERNET CONNECTION';
$user_role = $_SESSION['user_role'] ?? 'guest';
$user_name = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($company_name) ?> - <?= $title ?? 'Billing System' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; overflow: hidden; height: 100vh; }
        
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 12px;
            left: 15px;
            z-index: 1001;
            background: #0d5c1e;
            border: none;
            color: white;
            font-size: 22px;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 998;
        }
        
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #0d5c1e 0%, #0a4a16 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        
        @media (min-width: 769px) {
            .main-content { margin-left: 260px; }
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .navbar-top { padding-left: 60px !important; }
        }
        
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { margin: 0; font-weight: bold; }
        .sidebar-header small { font-size: 10px; opacity: 0.8; }
        
        .sidebar .nav { padding: 0 15px 20px; }
        .sidebar .nav-item { margin-bottom: 5px; list-style: none; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 10px 15px; border-radius: 10px; text-decoration: none; display: block; font-size: 14px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: #ffd700; }
        .sidebar .nav-link i { width: 24px; margin-right: 10px; }
        .sidebar .menu-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 15px 0; }
        .sidebar .submenu { padding-left: 30px; margin: 0; list-style: none; }
        .sidebar .submenu .nav-link { padding: 8px 15px; font-size: 13px; }
        .sidebar .submenu .nav-link i { width: 20px; margin-right: 8px; }
        .sidebar .submenu-icon { transition: transform 0.3s; }
        
        .main-content { padding: 0; height: 100vh; overflow-y: auto; }
        
        /* Navbar Top - SAMA SEPERTI DASHBOARD */
        .navbar-top {
            background: linear-gradient(135deg, #0d5c1e, #1a8a2d);
            color: white;
            padding: 12px 25px;
            position: sticky;
            top: 0;
            z-index: 99;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar-top .menu-title {
            font-size: 16px;
            font-weight: 500;
        }
        .navbar-top .menu-title i {
            margin-right: 8px;
        }
        
        /* User Dropdown */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        .user-dropdown-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .user-dropdown-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 45px;
            background: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-radius: 12px;
            z-index: 100;
            overflow: hidden;
        }
        .user-dropdown-content a {
            color: #333;
            padding: 12px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-dropdown-content a:hover {
            background: #f0f2f5;
        }
        .user-dropdown-content .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 5px 0;
        }
        .user-dropdown.show .user-dropdown-content {
            display: block;
        }
        
        .content-wrapper { padding: 20px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: none; margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 1px solid #e0e0e0; padding: 15px 20px; }
        .card-body { padding: 20px; }
        .btn-primary { background: #0d5c1e; border: none; }
        .btn-primary:hover { background: #1a8a2d; }
        .footer { background: white; padding: 15px; text-align: center; border-top: 1px solid #e2e8f0; margin-top: 20px; }
        @media (max-width: 768px) { .content-wrapper { padding: 15px; } .navbar-top { padding-left: 60px; } }
        .dataTables_wrapper { overflow-x: auto; }
        table.dataTable td, table.dataTable th { white-space: nowrap; }
    </style>
</head>
<body>

<button class="mobile-menu-btn" id="menuBtn"><i class="fas fa-bars"></i></button>
<div class="menu-overlay" id="menuOverlay"></div>

<div class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-wifi fa-2x mb-2"></i>
        <h4><?= htmlspecialchars($company_name) ?></h4>
        <small><?= htmlspecialchars($company_tagline) ?></small>
    </div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        
        <?php if($user_role == 'super_admin'): ?>
        <li class="nav-item">
            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : '' ?>" href="/modules/users/index.php">
                <i class="fas fa-users-cog"></i> User Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'permissions') !== false ? 'active' : '' ?>" href="/modules/permissions/index.php">
                <i class="fas fa-lock"></i> Hak Akses
            </a>
        </li>
        <?php endif; ?>
        
        <!-- Paket Internet dengan Sub Menu -->
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="togglePaketSubmenu(); return false;">
                <i class="fas fa-globe"></i> Paket Internet 
                <i class="fas fa-chevron-down submenu-icon" id="paketIcon" style="float:right; margin-top:5px;"></i>
            </a>
            <ul id="paketSubmenu" class="submenu" style="display:none;">
                <li><a class="nav-link" href="/modules/paket/index.php"><i class="fas fa-box"></i> Daftar Paket</a></li>
                <li><a class="nav-link" href="/modules/paket/bandwidth/index.php"><i class="fas fa-chart-line"></i> Bandwidth</a></li>
                <li><a class="nav-link" href="/modules/paket/group_profil/index.php"><i class="fas fa-layer-group"></i> Group Profil</a></li>
                <li><a class="nav-link" href="/modules/paket/profil_ppp/index.php"><i class="fas fa-user-lock"></i> Profil PPP</a></li>
            </ul>
        </li>
        
        <?php if($user_role == 'super_admin' || $user_role == 'teknisi'): ?>
        <li class="nav-item">
            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'routers') !== false ? 'active' : '' ?>" href="/modules/routers/index.php">
                <i class="fas fa-server"></i> Routers
            </a>
        </li>
        <?php endif; ?>
        
        <?php if($user_role == 'super_admin' || $user_role == 'kasir'): ?>
        <li class="nav-item">
            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pelanggan') !== false ? 'active' : '' ?>" href="/modules/pelanggan/index.php">
                <i class="fas fa-users"></i> Data Pelanggan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/modules/tagihan/index.php">
                <i class="fas fa-file-invoice-dollar"></i> Data Tagihan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="/modules/keuangan/index.php">
                <i class="fas fa-chart-line"></i> Data Keuangan
            </a>
        </li>
        <?php endif; ?>
        
        <li class="nav-item">
            <a class="nav-link" href="/modules/tiket/index.php">
                <i class="fas fa-ticket-alt"></i> Tiket
            </a>
        </li>
        
        <li class="menu-divider"></li>
        
        <?php if($user_role == 'super_admin'): ?>
        <li class="nav-item">
            <a class="nav-link" href="/modules/log/index.php">
                <i class="fas fa-history"></i> Log Aktivitas
            </a>
        </li>
        <?php endif; ?>
        
        <li class="nav-item">
            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pengaturan') !== false ? 'active' : '' ?>" href="/modules/pengaturan/index.php">
                <i class="fas fa-cog"></i> Pengaturan
            </a>
        </li>
        
        <li class="menu-divider"></li>
        
        <li class="nav-item">
            <a class="nav-link" href="/logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>

<script>
// Mobile menu
var menuBtn = document.getElementById('menuBtn');
var sidebar = document.querySelector('.sidebar');
var overlay = document.getElementById('menuOverlay');

if (menuBtn) {
    menuBtn.addEventListener('click', function() {
        sidebar.classList.toggle('open');
        if (sidebar.classList.contains('open')) {
            overlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } else {
            overlay.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
}

if (overlay) {
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.style.display = 'none';
        document.body.style.overflow = 'auto';
    });
}

function togglePaketSubmenu() {
    var submenu = document.getElementById('paketSubmenu');
    var icon = document.getElementById('paketIcon');
    if (submenu.style.display === 'none') {
        submenu.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        submenu.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
}

// Buka submenu otomatis jika sedang di halaman paket
document.addEventListener('DOMContentLoaded', function() {
    var currentUrl = window.location.href;
    if (currentUrl.includes('/modules/paket/') || currentUrl.includes('bandwidth') || 
        currentUrl.includes('group_profil') || currentUrl.includes('profil_ppp')) {
        var submenu = document.getElementById('paketSubmenu');
        var icon = document.getElementById('paketIcon');
        if (submenu) {
            submenu.style.display = 'block';
            if (icon) icon.style.transform = 'rotate(180deg)';
        }
    }
});

// User dropdown
function toggleUserDropdown() {
    var dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('show');
}

window.onclick = function(event) {
    if (!event.target.matches('.user-dropdown-btn') && !event.target.closest('.user-dropdown-btn')) {
        var dropdowns = document.getElementsByClassName('user-dropdown');
        for (var i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].classList.contains('show')) {
                dropdowns[i].classList.remove('show');
            }
        }
    }
}
</script>
