<?php
/**
 * AtmoPro Installer — Step 3: Selesai
 */
$env_file = __DIR__ . '/../.env';
$installed = file_exists($env_file) && filesize($env_file) > 50;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi Selesai — AtmoPro Billing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0d5c1e, #0a4a16); min-height: 100vh; padding: 40px 0; font-family: 'Segoe UI', sans-serif; }
        .finish-card { max-width: 720px; margin: 0 auto; background: white; border-radius: 15px; padding: 40px; box-shadow: 0 15px 35px rgba(0,0,0,0.3); }
        h2 { color: #0d5c1e; }
        .step-box { background: #f8f9fa; border-left: 4px solid #0d5c1e; padding: 15px 20px; margin-bottom: 15px; border-radius: 5px; }
        code { background: #fff3cd; padding: 2px 6px; border-radius: 3px; color: #856404; }
    </style>
</head>
<body>
<div class="container">
    <div class="finish-card">
        <?php if (!$installed): ?>
            <div class="alert alert-danger">
                <h4>⚠️ Instalasi Belum Selesai</h4>
                <p>File <code>.env</code> belum terbentuk. Ulangi proses install.</p>
                <a href="index.php" class="btn btn-primary">Kembali ke Installer</a>
            </div>
        <?php else: ?>
            <div class="text-center mb-4">
                <div style="font-size:60px;">🎉</div>
                <h2>Instalasi Berhasil!</h2>
                <p class="text-muted">AtmoPro Billing System siap digunakan.</p>
            </div>

            <hr>

            <h5>🔐 Login Default</h5>
            <table class="table table-sm">
                <tr><td width="130"><strong>Super Admin</strong></td><td><code>admin</code> / <code>admin123</code></td></tr>
                <tr><td><strong>Kasir</strong></td><td><code>kasir</code> / <code>admin123</code></td></tr>
                <tr><td><strong>Teknisi</strong></td><td><code>teknisi</code> / <code>admin123</code></td></tr>
            </table>
            <div class="alert alert-warning">
                <strong>⚠️ WAJIB:</strong> Segera ganti password setelah login pertama!
            </div>

            <hr>

            <h5>📋 Langkah Selanjutnya (WAJIB)</h5>
            <div class="step-box">
                <strong>1. Hapus folder <code>/install/</code></strong><br>
                <small>Untuk alasan keamanan, folder installer harus dihapus setelah instalasi selesai.</small>
                <pre class="mt-2 mb-0 bg-dark text-light p-2 rounded" style="font-size:12px;">rm -rf /path/ke/atmoPro/install/</pre>
            </div>

            <div class="step-box">
                <strong>2. Setting permission folder</strong>
                <pre class="mt-2 mb-0 bg-dark text-light p-2 rounded" style="font-size:12px;">chmod 640 .env
chmod -R 755 uploads/ cache/ logs/</pre>
            </div>

            <div class="step-box">
                <strong>3. Setup cron jobs</strong><br>
                <small>Copy isi <code>install/cron.txt</code> ke crontab. Ganti path & user sesuai server Anda.</small>
            </div>

            <div class="step-box">
                <strong>4. Konfigurasi lanjutan</strong><br>
                <small>Setelah login: MikroTik, GenieACS, WhatsApp, Telegram, Payment Gateway (lihat <code>docs/CONFIGURATION.md</code>).</small>
            </div>

            <hr>

            <div class="text-center">
                <a href="../" class="btn btn-success btn-lg px-5">🚀 Buka Aplikasi</a>
            </div>
        <?php endif; ?>

        <p class="text-center text-muted mt-4 mb-0" style="font-size:12px;">AtmoPro Billing v1.2</p>
    </div>
</div>
</body>
</html>
