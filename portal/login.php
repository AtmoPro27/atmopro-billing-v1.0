<?php
session_start();

try {
    require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();
} catch(PDOException $e) {
    die("Database error");
}

// Ambil pengaturan perusahaan
$stmt = $pdo->query("SELECT key_setting, value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['key_setting']] = $row['value'];
}
$company_name = $settings['company_name'] ?? 'AtmoBilling';
$company_favicon = $settings['company_favicon'] ?? '';
$company_tagline = $settings['company_tagline'] ?? 'HIGH SPEED INTERNET CONNECTION';

// Ambil logo perusahaan dari folder uploads
$logo_path = '';
$logo_files = glob(BASE_PATH . '/uploads/logo/*.{png,jpg,jpeg}', GLOB_BRACE);
if (!empty($logo_files)) {
    // Ambil file logo terbaru
    usort($logo_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $logo_file = basename($logo_files[0]);
    $logo_path = '/uploads/logo/' . $logo_file;
}

$error = '';
$success = '';

// Proses reset password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $id_pelanggan = $_POST['id_pelanggan'] ?? '';
    $kontak = $_POST['kontak'] ?? '';

    if (empty($id_pelanggan) || empty($kontak)) {
        $error = "ID Pelanggan dan No HP harus diisi!";
    } else {
        $is_phone = preg_match('/^62[0-9]{9,14}$/', $kontak);

        $query = "SELECT * FROM pelanggan WHERE id_pelanggan = ? AND status = 'active' AND (";
        $params = [$id_pelanggan];

        if ($is_email) {
            $params[] = $kontak;
        } elseif ($is_phone) {
            $query .= " no_telepon = ?";
            $params[] = $kontak;
        } else {
            $error = "Format No HP harus dimulai dengan 62 (contoh: 628000000000)";
        }
        $query .= ")";

        if (empty($error)) {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $pelanggan = $stmt->fetch();

            if ($pelanggan) {
                $success = "Password Anda telah direset menjadi <strong>123456</strong>. Silakan login.";
            } else {
                $error = "ID Pelanggan, No HP, tidak cocok!";
            }
        }
    }
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ? AND status = 'active'");
        $stmt->execute([$username]);
        $pelanggan = $stmt->fetch();

        if ($pelanggan && $password === '123456') {
            $_SESSION['pelanggan_id'] = $pelanggan['id'];
            $_SESSION['pelanggan_kode'] = $pelanggan['id_pelanggan'];
            $_SESSION['pelanggan_nama'] = $pelanggan['nama_lengkap'];
            $_SESSION['pelanggan_alamat'] = $pelanggan['alamat'];
            $_SESSION['pelanggan_telp'] = $pelanggan['no_telepon'];
            $_SESSION['portal_login'] = true;
            $_SESSION['portal_login_time'] = time();
            $_SESSION['portal_last_activity'] = time();

            header("Location: index.php");
            exit();
        } else {
            $error = "ID Pelanggan atau password salah!";
        }
    } else {
        $error = "ID Pelanggan dan password harus diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d5c1e">
    <link rel="manifest" href="manifest.php">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <?php if (!empty($company_favicon)): ?>
    <link rel="icon" type="image/x-icon" href="/<?= htmlspecialchars($company_favicon) ?>">
    <link rel="shortcut icon" type="image/x-icon" href="/<?= htmlspecialchars($company_favicon) ?>">
    <?php endif; ?>
    <title>Portal Pelanggan - <?= htmlspecialchars($company_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0d5c1e, #0a4a16);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: white;
            text-align: center;
            padding: 30px 20px 20px;
        }
        .login-body {
            padding: 30px;
            background: white;
        }
        .company-logo {
            max-width: 100px;
            max-height: 100px;
            margin-bottom: 15px;
            border-radius: 15px;
        }
        .btn-login {
            background: linear-gradient(135deg, #0d5c1e, #0a4a16);
            border: none;
            width: 100%;
            padding: 12px;
            font-weight: bold;
            font-size: 16px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #0e6e23, #0c551a);
        }
        .forgot-link {
            text-align: center;
            margin-top: 15px;
        }
        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #0d5c1e;
            margin-bottom: 5px;
        }
        .company-tagline {
            font-size: 11px;
            color: #666;
        }
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        .divider span {
            padding: 0 10px;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card login-card">
                    <div class="login-header">
                        <?php if ($logo_path && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_path)): ?>
                            <img src="<?= $logo_path ?>" alt="Logo <?= htmlspecialchars($company_name) ?>" class="company-logo">
                        <?php else: ?>
                            <i class="fas fa-globe fa-4x" style="color: #0d5c1e;"></i>
                        <?php endif; ?>
                        <div class="company-name"><?= htmlspecialchars($company_name) ?></div>
                        <div class="company-tagline"><?= htmlspecialchars($company_tagline) ?></div>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?= $success ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Form Login -->
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">ID Pelanggan</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" class="form-control" placeholder="Contoh: 1122001" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" placeholder="Password default: 123456" required>
                                </div>
                                <div class="form-text text-muted">Password default: 123456</div>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary btn-login">
                                <i class="fas fa-sign-in-alt"></i> LOGIN
                            </button>
                        </form>
                        
                        <div class="divider"><span>atau</span></div>
                        
                        <div class="forgot-link">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#resetModal" style="color: #0d5c1e;">
                                <i class="fas fa-key"></i> Lupa Password?
                            </a>
                        </div>
                        
                        <hr>
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-headset"></i> Butuh bantuan? Hubungi CS kami<br>
                                <strong>Telp/WA: 628000000000</strong>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Reset Password -->
    <div class="modal fade" id="resetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: #0d5c1e; color: white;">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Masukkan ID Pelanggan dan No HP yang terdaftar.</p>
                        <div class="mb-3">
                            <label class="form-label">ID Pelanggan</label>
                            <input type="text" name="id_pelanggan" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No HP</label>
                            <input type="text" name="kontak" class="form-control" placeholder="628000000000 atau email@domain.com" required>
                        </div>
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle"></i> No HP: mulai dengan 62 (contoh: 628000000000)<br>
                                Jika lupa NO HP silahkan hubungi Admin
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="reset_password" class="btn btn-primary" style="background: #0d5c1e; border: none;">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Service Worker untuk PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/portal/sw.js')
                .then(reg => console.log('SW registered:', reg))
                .catch(err => console.log('SW failed:', err));
        }
    </script>
</body>
</html>
