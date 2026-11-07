<?php
require_once __DIR__ . "/../includes/auth_check_portal.php";
$company_name = 'AtmoBilling';
try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT value FROM pengaturan WHERE key_setting = 'company_name'");
    $row = $stmt->fetch();
    if ($row) {
        $company_name = $row['value'];
    }
} catch(PDOException $e) {
    // Gunakan default
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - <?= htmlspecialchars($company_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/portal/manifest.php">
    <link rel="icon" type="image/png" sizes="192x192" href="/portal/icons/icon-192.png">
    <style>
        body {
            background: linear-gradient(135deg, #0d5c1e, #1a8a2d);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .offline-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .offline-card .icon {
            font-size: 72px;
            color: #0d5c1e;
            margin-bottom: 15px;
        }
        .offline-card .icon i {
            background: #f0f8f0;
            padding: 20px;
            border-radius: 50%;
        }
        .offline-card h2 {
            color: #0d5c1e;
            font-weight: 700;
            margin-top: 10px;
        }
        .offline-card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .offline-card .btn-reload {
            background: #0d5c1e;
            color: white;
            border: none;
            padding: 12px 35px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 16px;
        }
        .offline-card .btn-reload:hover {
            background: #1a8a2d;
            transform: translateY(-2px);
        }
        .offline-card .logo {
            max-width: 80px;
            margin-bottom: 10px;
        }
        .offline-card .footer-text {
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="offline-card">
        <?php
        $logo_path = '/uploads/logo/logo_1779236751.png';
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)) {
            echo '<img src="' . $logo_path . '" alt="Logo" class="logo">';
        } else {
            echo '<div class="icon"><i class="fas fa-wifi"></i></div>';
        }
        ?>
        <h2>🙈 Anda Offline</h2>
        <p>
            Sepertinya Anda kehilangan koneksi internet. 
            Beberapa fitur mungkin tidak tersedia.
        </p>
        <?php if (isset($_SESSION['pelanggan_nama'])): ?>
            <p>
                <small class="text-muted">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['pelanggan_nama']) ?>
                </small>
            </p>
        <?php endif; ?>
        <button class="btn-reload" onclick="location.reload()">
            <i class="fas fa-sync"></i> Coba Lagi
        </button>
        <hr>
        <p class="footer-text">
            <i class="fas fa-check-circle text-success"></i> 
            <?= date('d F Y H:i:s') ?>
            <br>
            <span class="text-muted"><?= htmlspecialchars($company_name) ?> Billing System</span>
        </p>
    </div>
    <script>
    // Cek koneksi secara periodik
    setInterval(function() {
        if (navigator.onLine) {
            location.reload();
        }
    }, 30000);
    </script>
</body>
</html>
