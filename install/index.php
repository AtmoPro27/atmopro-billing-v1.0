<?php
/**
 * AtmoPro Installer — Step 1: Cek Requirement & Form Database
 * Hanya bisa diakses jika .env belum ada / belum terisi.
 */

$env_file = __DIR__ . '/../.env';
$already_installed = file_exists($env_file) && filesize($env_file) > 50;

if ($already_installed) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;background:#fff3cd;border:1px solid #ffc107;max-width:600px;margin:40px auto;border-radius:8px;">
        <h2 style="color:#856404;">⚠️ AtmoPro Sudah Terinstall</h2>
        <p>Akses ke folder <code>/install/</code> diblokir karena konfigurasi sudah ada.</p>
        <p><strong>Hapus folder <code>/install/</code> sekarang</strong> untuk alasan keamanan.</p>
        </div>');
}

// ============================================================
// Cek requirement server
// ============================================================
$requirements = [
    'PHP >= 8.1'         => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL'          => extension_loaded('pdo_mysql'),
    'cURL'               => extension_loaded('curl'),
    'JSON'               => extension_loaded('json'),
    'MBString'           => extension_loaded('mbstring'),
    'OpenSSL'            => extension_loaded('openssl'),
    'Folder writable'    => is_writable(__DIR__ . '/../'),
];

$all_ok = !in_array(false, $requirements, true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer — AtmoPro Billing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0d5c1e, #0a4a16); min-height: 100vh; padding: 40px 0; font-family: 'Segoe UI', sans-serif; }
        .install-card { max-width: 720px; margin: 0 auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.3); }
        .req-ok { color: #198754; }
        .req-fail { color: #dc3545; }
        h2 { color: #0d5c1e; }
    </style>
</head>
<body>
<div class="container">
    <div class="install-card">
        <h2 class="text-center mb-1">🚀 AtmoPro Installer</h2>
        <p class="text-center text-muted">Billing System untuk ISP / RT-RW Net</p>
        <hr>

        <h5>1. Cek Requirement Server</h5>
        <ul class="list-unstyled">
            <?php foreach ($requirements as $name => $ok): ?>
                <li class="<?= $ok ? 'req-ok' : 'req-fail' ?>">
                    <?= $ok ? '✅' : '❌' ?> <?= $name ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$all_ok): ?>
            <div class="alert alert-danger">
                <strong>Requirement belum terpenuhi.</strong> Perbaiki dulu sebelum melanjutkan.
            </div>
        <?php endif; ?>

        <hr>

        <h5>2. Konfigurasi Database</h5>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <?php if (!$all_ok): ?>
            <div class="alert alert-warning">Form dinonaktifkan sampai requirement terpenuhi.</div>
        <?php else: ?>
        <form method="post" action="process.php">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">DB Host</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">DB Name</label>
                    <input type="text" name="db_name" class="form-control" placeholder="nama_database" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">DB User</label>
                    <input type="text" name="db_user" class="form-control" placeholder="user_database" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">DB Password</label>
                    <input type="password" name="db_pass" class="form-control" placeholder="password_kuat" required>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Base URL (domain aplikasi)</label>
                    <input type="text" name="base_url" class="form-control" value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Timezone</label>
                    <input type="text" name="timezone" class="form-control" value="Asia/Jakarta" required>
                </div>
            </div>

            <div class="alert alert-info small">
                <strong>Info:</strong> Pastikan database sudah dibuat dan user punya akses penuh.
                Jika DB belum ada, installer akan mencoba membuatnya.
            </div>

            <button type="submit" class="btn btn-success w-100 py-2">
                🚀 Install Sekarang
            </button>
        </form>
        <?php endif; ?>

        <p class="text-center text-muted mt-3 mb-0" style="font-size:12px;">AtmoPro Billing v1.2</p>
    </div>
</div>
</body>
</html>
