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
$company_tagline = $settings['company_tagline'] ?? 'HIGH SPEED INTERNET CONNECTION';
$company_logo = $settings['company_logo'] ?? '';
$company_favicon = $settings['company_favicon'] ?? '';
$company_phone = $settings['company_phone'] ?? '628000000000';

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
        
        if (!$is_phone) {
            $error = "Format No HP harus dimulai dengan 62 (contoh: 628123456789)!";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ? AND no_telepon = ? AND status = 'active'");
            $stmt->execute([$id_pelanggan, $kontak]);
            $pelanggan = $stmt->fetch();

            if ($pelanggan) {
                $new_password = bin2hex(random_bytes(4));
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE pelanggan SET password = ? WHERE id = ?");
                $update->execute([$hashed_password, $pelanggan['id']]);
                $success = "Password Anda telah direset menjadi <strong>" . htmlspecialchars($new_password) . "</strong>. Silakan login dan segera ganti password.";
            } else {
                $error = "ID Pelanggan dan No HP tidak cocok!";
            }
        }
    }
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ?");
        $stmt->execute([$username]);
        $pelanggan = $stmt->fetch();

        if ($pelanggan) {
            if ($pelanggan['status'] == 'blocked') {
                $error = "AKUN ANDA DIBLOKIR. Silakan hubungi admin.";
            } elseif ($pelanggan['status'] == 'active' || $pelanggan['status'] == 'inactive') {
                if (password_verify($password, $pelanggan['password'])) {
                    $_SESSION['portal_login'] = true;
                    $_SESSION['pelanggan_id'] = $pelanggan['id'];
                    $_SESSION['pelanggan_kode'] = $pelanggan['id_pelanggan'];
                    $_SESSION['pelanggan_nama'] = $pelanggan['nama_lengkap'];
                    $_SESSION['pelanggan_alamat'] = $pelanggan['alamat'];
                    $_SESSION['pelanggan_telp'] = $pelanggan['no_telepon'];
                    $_SESSION['pelanggan_status'] = $pelanggan['status'];
                    
                    session_regenerate_id(true);
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Username atau Password salah!";
                }
            } else {
                $error = "Username atau Password salah!";
            }
        } else {
            $error = "Username atau Password salah!";
        }
    } else {
        $error = "Username dan Password harus diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Pelanggan - <?= htmlspecialchars($company_name) ?></title>
    <?php if (!empty($company_favicon)): ?>
    <link rel="icon" type="image/x-icon" href="/<?= htmlspecialchars($company_favicon) ?>">
    <link rel="shortcut icon" type="image/x-icon" href="/<?= htmlspecialchars($company_favicon) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #0d5c1e, #0a4a16);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
            text-align: center;
        }
        .btn-primary {
            background: #0d5c1e;
            border: none;
        }
        .btn-primary:hover {
            background: #1a8a2d;
        }
        .btn-warning {
            background: #ff9800;
            border: none;
        }
        .forgot-link {
            cursor: pointer;
            color: #0d5c1e;
            text-decoration: none;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <?php if ($company_logo): ?>
                            <img src="/<?= htmlspecialchars($company_logo) ?>" alt="Logo" height="60">
                        <?php else: ?>
                            <i class="fas fa-wifi fa-3x mb-2"></i>
                        <?php endif; ?>
                        <h4 class="mb-0"><?= htmlspecialchars($company_name) ?></h4>
                        <small><?= htmlspecialchars($company_tagline) ?></small>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>

                        <form method="POST" id="loginForm">
                            <input type="hidden" name="login" value="1">
                            <div class="mb-3">
                                <label class="form-label">ID Pelanggan</label>
                                <input type="text" name="username" class="form-control" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>

                        <hr>

                        <div class="text-center">
                            <a href="#" class="forgot-link" onclick="showResetForm()">Lupa Password? klik sini</a></br></br>
                            <a href="https://wa.me/<?= htmlspecialchars($company_phone) ?>" target="_blank">admin : <?= htmlspecialchars($company_phone) ?></a>
                        </div>

                        <div id="resetForm" style="display:none; margin-top:20px;">
                            <hr>
                            <h6>Reset Password</h6>
                            <form method="POST">
                                <input type="hidden" name="reset_password" value="1">
                                <div class="mb-3">
                                    <label class="form-label">ID Pelanggan</label>
                                    <input type="text" name="id_pelanggan" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">No HP (WA) terdaftar</label>
                                    <input type="text" name="kontak" class="form-control" placeholder="628000000000" required>
                                    <small class="text-muted">Gunakan format 62xxxxxxxxxx (tanpa 0 di awal)</small>
                                </div>
                                <button type="submit" class="btn btn-warning w-100">Reset Password</button>
                                <button type="button" class="btn btn-link w-100 mt-2" onclick="hideResetForm()">Kembali ke Login</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="text-center text-white mt-3">
                    <small>&copy; <?= date('Y') ?> <?= htmlspecialchars($company_name) ?> by AtmoPro - All Rights Reserved</small>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showResetForm() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('resetForm').style.display = 'block';
        }
        function hideResetForm() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('resetForm').style.display = 'none';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
