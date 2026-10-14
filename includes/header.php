<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
   require_once __DIR__ . "/../config/database.php";
   try {
        $pdo_settings = Database::getInstance()->getConnection();
        $pdo_settings->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo_settings->query("SELECT key_setting, value FROM pengaturan");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key_setting']] = $row['value'];
        }
    } catch(PDOException $e) {
        $settings = [];
    }

    $company_name = $settings['company_name'] ?? 'Your Company';
    $company_tagline = $settings['company_tagline'] ?? 'HIGH SPEED INTERNET CONNECTION';
    $company_logo = $settings['company_logo'] ?? '';
    $company_favicon = $settings['company_favicon'] ?? '';
    ?>

    <title><?= htmlspecialchars($company_name) ?> - Billing System</title>
    <?php if (!empty($company_favicon)): ?><link rel="icon" type="image/x-icon" href="/<?= htmlspecialchars($company_favicon) ?>"><?php else: ?><link rel="icon" type="image/x-icon" href="/favicon.ico"><?php endif; ?>
    <?php if (!empty($company_favicon)): ?><link rel="shortcut icon" type="image/x-icon" href="/<?= htmlspecialchars($company_favicon) ?>"><?php else: ?><link rel="shortcut icon" type="image/x-icon" href="/favicon.ico"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }

        .sidebar { width: 260px; background: linear-gradient(180deg, #0d5c1e, #0a4a16); color: white; position: fixed; left: 0; top: 0; bottom: 0; overflow-y: auto; z-index: 100; }
        .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h4 { margin: 0; font-weight: bold; font-size: 16px; }
        .sidebar-header small { font-size: 10px; opacity: 0.8; }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); padding: 12px 20px; display: block; text-decoration: none; transition: all 0.3s; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,0.1); color: #ffd700; padding-left: 25px; }
        .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: #ffd700; border-left: 3px solid #ffd700; }
        .sidebar .nav-link i { width: 24px; margin-right: 10px; }

        .badge-notif { background-color: #ff4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; margin-left: 8px; }
        .sidebar .collapse .nav-link { padding-left: 45px; }
        .sidebar .dropdown-toggle::after { float: right; margin-top: 8px; }

        .dropdown-menu-custom {
            display: none;
            margin-left: 20px;
        }
        .dropdown-menu-custom.show {
            display: block;
        }
        .dropdown-item {
            color: rgba(255,255,255,0.8);
            padding: 8px 20px 8px 45px;
            display: block;
            text-decoration: none;
        }
        .dropdown-item:hover {
            background: rgba(255,255,255,0.1);
            color: #ffd700;
        }
        .dropdown-divider-custom {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin: 5px 0;
        }

        .main-content { margin-left: 260px; padding: 20px; min-height: 100vh; }

        .navbar-top { background: linear-gradient(135deg, #0d5c1e, #0a4a16); color: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .navbar-top .page-title { font-size: 20px; font-weight: 600; color: white; }

        .user-dropdown { position: relative; display: inline-block; }
        .user-dropdown-btn { background: transparent; border: none; color: white; cursor: pointer; padding: 8px 12px; border-radius: 8px; }
        .user-dropdown-btn:hover { background: rgba(255,255,255,0.2); }
        .user-dropdown-content { display: none; position: absolute; right: 0; top: 100%; background: white; min-width: 180px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); border-radius: 8px; z-index: 1000; margin-top: 5px; }
        .user-dropdown-content a { color: #333; padding: 12px 16px; text-decoration: none; display: block; font-size: 14px; }
        .user-dropdown-content a:hover { background: #f5f5f5; }
        .user-dropdown-content .text-danger { color: #dc3545; }
        .show { display: block; }

        .card { background: white; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .card-header { background: white; border-bottom: 1px solid #e0e0e0; padding: 15px 20px; font-weight: 600; }
        .card-body { padding: 20px; }
        .footer { text-align: center; padding: 15px; color: #666; font-size: 12px; border-top: 1px solid #e0e0e0; margin-top: 20px; background: white; border-radius: 10px; }
        .btn-primary { background: #0d5c1e; border: none; }
        .btn-primary:hover { background: #1a8a2d; }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                width: 260px;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle { cursor: pointer; }
        }
        @media (min-width: 769px) {
            .menu-toggle { display: none; }
        }
        .table-responsive { overflow-x: auto; }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <button class="btn-close btn-close-white" onclick="document.getElementById('sidebar').classList.remove('show');" style="position:absolute; right:10px; top:10px;"></button>
        <?php if (!empty($company_logo) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $company_logo)): ?>
            <img src="/<?= $company_logo ?>" alt="Logo" style="max-width: 80%; margin-bottom: 10px;">
        <?php else: ?>
            <i class="fas fa-wifi fa-2x mb-2"></i>
        <?php endif; ?>
        <h4><?= htmlspecialchars($company_name) ?></h4>
        <small><?= htmlspecialchars($company_tagline) ?></small>
    </div>
    <div class="nav flex-column">
        <?php
        $user_role = $_SESSION['user_role'] ?? 'guest';
        $is_admin = ($user_role == 'super_admin' || $user_role == 'admin');

        try {
            $pdo_notif = Database::getInstance()->getConnection();
            $pdo_notif->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $pdo_notif->query("SELECT COUNT(*) as total FROM tickets WHERE status = 'open' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $new_tickets = $stmt->fetch()['total'];
        } catch(PDOException $e) {
            $new_tickets = 0;
        }
        ?>

        <a href="/dashboard_fix.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="/modules/customers/index.php" class="nav-link"><i class="fas fa-users"></i> Data Pelanggan</a>
        <a href="/modules/customers/pelanggan_khusus.php" class="nav-link"><i class="fas fa-star"></i> Pelanggan Khusus</a>

        <?php if ($is_admin): ?>
            <a href="/modules/packages/index.php" class="nav-link"><i class="fas fa-box"></i> Paket Internet</a>
        <?php endif; ?>

        <a href="/modules/invoices/index.php" class="nav-link"><i class="fas fa-file-invoice"></i> Tagihan</a>

        <a href="/modules/tickets/index.php" class="nav-link">
            <i class="fas fa-ticket-alt"></i> Tiket
            <?php if ($new_tickets > 0): ?>
                <span class="badge-notif"><?= $new_tickets ?></span>
            <?php endif; ?>
        </a>

        <?php if ($user_role == 'teknisi'): ?>
            <a href="/modules/genieacs/index.php" class="nav-link"><i class="fas fa-tower-broadcast"></i> GenieACS</a>
        <?php endif; ?>

        <?php if ($is_admin): ?>
            <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#mikrotikMenu">
                <i class="fas fa-cogs"></i> Manajemen Mikrotik
            </a>
            <div class="collapse" id="mikrotikMenu">
                <a href="/modules/mikrotik/index.php" class="nav-link"><i class="fas fa-server"></i> Dashboard Mikrotik</a>
                <a href="/modules/routers/index.php" class="nav-link"><i class="fas fa-router"></i> Daftar Router</a>
            </div>
        <?php endif; ?>

        <?php if ($is_admin): ?>
            <a href="/modules/genieacs/index.php" class="nav-link"><i class="fas fa-tower-broadcast"></i> GenieACS</a>
        <?php endif; ?>

        <?php if ($_SESSION['user_role'] == 'super_admin'): ?>
        <div class="nav-item">
            <a href="#" class="nav-link" onclick="toggleSettings(event)">
                <i class="fas fa-cog"></i> Pengaturan <span style="float:right">▼</span>
            </a>
            <div id="settingsMenu" style="display:none;">
                <a href="/modules/settings/general.php" class="nav-link" style="padding-left:45px"><i class="fas fa-sliders-h"></i> Pengaturan Umum</a>
                <a href="/modules/settings/payment.php" class="nav-link" style="padding-left:45px"><i class="fas fa-credit-card"></i> Pengaturan Pembayaran</a>
                <a href="/modules/settings/wa_gateway.php" class="nav-link" style="padding-left:45px"><i class="fab fa-whatsapp"></i> WA Gateway</a>
                <a href="/modules/settings/telegram.php" class="nav-link" style="padding-left:45px"><i class="fab fa-telegram"></i> Telegram</a>
                <a href="/modules/users/index.php" class="nav-link" style="padding-left:45px"><i class="fas fa-users"></i> Manajemen User</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- DROPDOWN KEUANGAN (INCOME, PENGELUARAN, LABA RUGI) -->
        <!-- ========================================== -->
        <div class="nav-item">
            <a href="#" class="nav-link" onclick="toggleFinance(event)">
                <i class="fas fa-chart-line"></i> Keuangan <span style="float:right">▼</span>
            </a>
            <div id="financeMenu" style="display:none;">
                <a href="/modules/finance/income.php" class="nav-link" style="padding-left:45px"><i class="fas fa-arrow-up text-success"></i> INCOME</a>
                <a href="/modules/finance/pengeluaran.php" class="nav-link" style="padding-left:45px"><i class="fas fa-arrow-down text-danger"></i> PENGELUARAN</a>
                <a href="/modules/finance/bagi_hasil.php" class="nav-link" style="padding-left:45px"><i class="fas fa-hand-holding-usd text-warning"></i> BAGI HASIL</a>
                <a href="/modules/finance/laba_rugi.php" class="nav-link" style="padding-left:45px"><i class="fas fa-chart-pie text-primary"></i> LABA RUGI</a>
                <a href="/modules/finance/setting_auto_pengeluaran.php" class="nav-link" style="padding-left:45px"><i class="fas fa-cog text-secondary"></i> SETTING AUTO</a> 
 </div>
        </div>

        <?php if ($is_admin): ?>
            <a href="/modules/logs/index.php" class="nav-link"><i class="fas fa-history"></i> Log Aktivitas</a>
        <?php endif; ?>
        <hr>
        <a href="/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="navbar-top">
        <div class="page-title">
            <i class="fas fa-bars menu-toggle" id="menuToggle" onclick="document.getElementById('sidebar').classList.toggle('show');" style="margin-right: 15px;"></i>
            <?= strtoupper(htmlspecialchars($company_name)) ?> BILLING SYSTEM
        </div>
        <div class="user-dropdown">
            <button class="user-dropdown-btn" onclick="toggleUserDropdown()">
                <i class="fas fa-user-circle"></i> <?= $_SESSION['user_name'] ?? 'User' ?> (<?= $_SESSION['user_role'] ?? 'Guest' ?>)
                <i class="fas fa-chevron-down" style="font-size: 12px; margin-left: 8px;"></i>
            </button>
            <div class="user-dropdown-content" id="userDropdownContent">
                <a href="/modules/profile/index.php"><i class="fas fa-user-edit"></i> Profil</a>
                <div class="dropdown-divider"></div>
                <a href="/logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <script>
        function toggleSettings(e) {
            e.preventDefault();
            var menu = document.getElementById('settingsMenu');
            if (menu.style.display === 'none') {
                menu.style.display = 'block';
            } else {
                menu.style.display = 'none';
            }
        }

        function toggleFinance(e) {
            e.preventDefault();
            var menu = document.getElementById('financeMenu');
            if (menu.style.display === 'none') {
                menu.style.display = 'block';
            } else {
                menu.style.display = 'none';
            }
        }

        function toggleUserDropdown() {
            var dropdown = document.getElementById('userDropdownContent');
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            } else {
                dropdown.classList.add('show');
            }
        }

        window.onclick = function(event) {
            if (!event.target.matches('.user-dropdown-btn')) {
                var dropdowns = document.getElementsByClassName("user-dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    if (dropdowns[i].classList.contains('show')) {
                        dropdowns[i].classList.remove('show');
                    }
                }
            }
        }

        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar')?.classList.toggle('show');
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- ===== IDLE TIMEOUT DETECTOR ===== -->
    <script>
    (function() {
        const IDLE_LIMIT = 30 * 60 * 1000;   // 30 menit
        const WARNING_AT = 2 * 60 * 1000;    // warning 2 menit sebelum
        let idleTimer, warnTimer;

        function resetTimers() {
            clearTimeout(idleTimer);
            clearTimeout(warnTimer);
            warnTimer = setTimeout(showWarning, IDLE_LIMIT - WARNING_AT);
            idleTimer = setTimeout(doLogout, IDLE_LIMIT);
        }

        function showWarning() {
            if (document.getElementById('idle-warning')) return;
            const modal = document.createElement('div');
            modal.id = 'idle-warning';
            modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;font-family:sans-serif;';
            modal.innerHTML = '<div style="background:#fff;padding:30px;border-radius:10px;text-align:center;max-width:400px;">'
                + '<h3 style="margin:0 0 15px;color:#0d5c1e;">Sesi Akan Berakhir</h3>'
                + '<p style="margin:0 0 20px;">Anda akan otomatis logout dalam <strong>2 menit</strong>.</p>'
                + '<button id="stay-logged-in" style="background:#0d5c1e;color:#fff;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-size:14px;">Tetap Login</button>'
                + '</div>';
            document.body.appendChild(modal);

            document.getElementById('stay-logged-in').addEventListener('click', function() {
                modal.remove();
                fetch(window.location.href, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                resetTimers();
            });
        }

        function doLogout() {
            window.location.href = '/logout.php?reason=idle';
        }

        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function(evt) {
            document.addEventListener(evt, function() {
                if (!document.getElementById('idle-warning')) resetTimers();
            }, { passive: true });
        });

        resetTimers();
    })();
    </script>
    <!-- ===== END IDLE TIMEOUT DETECTOR ===== -->
    <!-- ===== IDLE TIMEOUT DETECTOR ===== -->
    <script>
    (function() {
        const IDLE_LIMIT = 30 * 60 * 1000;   // 30 menit
        const WARNING_AT = 2 * 60 * 1000;    // warning 2 menit sebelum
        let idleTimer, warnTimer;

        function resetTimers() {
            clearTimeout(idleTimer);
            clearTimeout(warnTimer);
            warnTimer = setTimeout(showWarning, IDLE_LIMIT - WARNING_AT);
            idleTimer = setTimeout(doLogout, IDLE_LIMIT);
        }

        function showWarning() {
            if (document.getElementById('idle-warning')) return;
            const modal = document.createElement('div');
            modal.id = 'idle-warning';
            modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);display:flex;align-items:center;justify-content:center;z-index:99999;font-family:sans-serif;';
            modal.innerHTML = '<div style="background:#fff;padding:30px;border-radius:10px;text-align:center;max-width:400px;">'
                + '<h3 style="margin:0 0 15px;color:#0d5c1e;">Sesi Akan Berakhir</h3>'
                + '<p style="margin:0 0 20px;">Anda akan otomatis logout dalam <strong>2 menit</strong>.</p>'
                + '<button id="stay-logged-in" style="background:#0d5c1e;color:#fff;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-size:14px;">Tetap Login</button>'
                + '</div>';
            document.body.appendChild(modal);

            document.getElementById('stay-logged-in').addEventListener('click', function() {
                modal.remove();
                fetch(window.location.href, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                resetTimers();
            });
        }

        function doLogout() {
            window.location.href = '/logout.php?reason=idle';
        }

        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function(evt) {
            document.addEventListener(evt, function() {
                if (!document.getElementById('idle-warning')) resetTimers();
            }, { passive: true });
        });

        resetTimers();
    })();
    </script>
    <!-- ===== END IDLE TIMEOUT DETECTOR ===== -->
</body>
</html>
<script>
function toggleFinance(e) {
    e.preventDefault();
    var menu = document.getElementById('financeMenu');
    if (menu.style.display === 'none') {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}
</script>
