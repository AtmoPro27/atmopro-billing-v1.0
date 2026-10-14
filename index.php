<?php
session_start();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard_fix.php");
    exit();
}

$error = '';
$success = '';

// Ambil pengaturan perusahaan dari database
try {
    require_once __DIR__ . '/config/database.php';
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->query("SELECT key_setting, value FROM pengaturan");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key_setting']] = $row['value'];
    }
    $company_phone = $settings['company_phone'] ?? '628000000000';
    $company_name_setting = $settings['company_name'] ?? 'DEMO COMPANY';
    $company_tagline_setting = $settings['company_tagline'] ?? 'HIGH SPEED INTERNET CONNECTION';
    $company_logo = $settings['company_logo'] ?? '';
    $company_favicon = $settings['company_favicon'] ?? '';
} catch(PDOException $e) {
    $company_phone = '628000000000';
    $company_name_setting = 'DEMO COMPANY';
    $company_tagline_setting = 'HIGH SPEED INTERNET CONNECTION';
    $company_logo = '';
}

// Proses logo
$logo_src = '';
if (!empty($company_logo)) {
    $logo_path = BASE_PATH . '/' . $company_logo;
    if (file_exists($logo_path)) {
        $logo_src = '/' . $company_logo;
    } else {
        $logo_files = glob(BASE_PATH . '/uploads/logo/*.{png,jpg,jpeg}', GLOB_BRACE);
        if (!empty($logo_files)) {
            usort($logo_files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $logo_file = basename($logo_files[0]);
            $logo_src = '/uploads/logo/' . $logo_file;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['reset_password'])) {
        // Proses reset password
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';

        if (!empty($username) && !empty($email)) {
            try {
                require_once __DIR__ . '/config/database.php';
                $pdo = Database::getInstance()->getConnection();

                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND email = ? AND is_active = 1");
                $stmt->execute([$username, $email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Generate password random 12 karakter
                        $new_password_plain = bin2hex(random_bytes(6));
                        $new_password_hash = password_hash($new_password_plain, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$new_password_hash, $user['id']]);
                    $success = "Password baru Anda: <strong>" . $new_password_plain . "</strong><br>Segera login dan ganti password!";
                } else {
                    $error = "Username dan Email tidak cocok!";
                }
            } catch(PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = "Username dan Email harus diisi!";
        }
    } else {
        // Proses login biasa
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!empty($username) && !empty($password)) {
            try {
                require_once __DIR__ . '/config/database.php';
                $pdo = Database::getInstance()->getConnection();

                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user) {
                      $is_valid = password_verify($password, $user['password']);

                    if ($is_valid) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['fullname'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();
                        
                        // Catat log login
                        try {
                            require_once __DIR__ . '/config/database.php';
                            $pdo_log = Database::getInstance()->getConnection();
                            $stmt_log = $pdo_log->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, target_type, target_id, details, ip_address) VALUES (?, ?, 'LOGIN', 'user', ?, 'Login ke sistem', ?)");
                            $stmt_log->execute([$user['id'], $user['username'], $user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                        } catch(Exception $e) {
                            error_log("Login log gagal: " . $e->getMessage());
                        }
                        
                        header("Location: dashboard_fix.php");
                        exit();
                    } else {
                        $error = "Username atau Password salah!";
                    }
                } else {
                    $error = "Username atau Password salah!";
                }
            } catch(PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = "Username dan Password harus diisi!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($company_favicon)): ?>
    <link rel="icon" type="image/x-icon" href="/<?= htmlspecialchars($company_favicon) ?>">
    <link rel="shortcut icon" type="image/x-icon" href="/<?= htmlspecialchars($company_favicon) ?>">
    <?php endif; ?>
    <title>Login - <?= htmlspecialchars($company_name_setting) ?> Billing System</title>
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
        .btn-outline-success {
            border-color: #0d5c1e;
            color: #0d5c1e;
        }
        .btn-outline-success:hover {
            background: #0d5c1e;
            color: white;
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
                        <?php if ($logo_src && file_exists($_SERVER['DOCUMENT_ROOT'] . $logo_src)): ?>
                            <img src="<?= $logo_src ?>" alt="Logo <?= htmlspecialchars($company_name_setting) ?>" class="company-logo">
                        <?php else: ?>
                            <i class="fas fa-globe fa-4x" style="color: #0d5c1e;"></i>
                        <?php endif; ?>
                        <div class="company-name"><?= htmlspecialchars($company_name_setting) ?></div>
                        <div class="company-tagline"><?= htmlspecialchars($company_tagline_setting) ?></div>
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
                        
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                                </div>
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
                        
                        <div class="text-center">
                            <a href="/portal/" class="btn btn-outline-success btn-sm mb-2">
                                <i class="fas fa-user"></i> Pelanggan? Klik di sini
                            </a>
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-headset"></i> Butuh bantuan? Hubungi CS kami<br>
                                <strong>Telp/WA: <?= htmlspecialchars($company_phone) ?></strong>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: #0d5c1e; color: white;">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <p>Masukkan Username dan Email yang terdaftar.</p>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="email@domain.com" required>
                        </div>
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle"></i> Password baru akan digenerate secara random dan ditampilkan setelah reset.
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
</body>
</html>
