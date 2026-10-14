<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard.php");
    exit();
}

include '../../includes/header.php';

try {
    $pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil setting Telegram (hanya bot_token & enabled)
    $stmt = $pdo->query("SELECT key_setting, value FROM pengaturan WHERE key_setting IN ('telegram_bot_token', 'telegram_notification_enabled')");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key_setting']] = $row['value'];
    }

    $bot_token = $settings['telegram_bot_token'] ?? '';
    $enabled = $settings['telegram_notification_enabled'] ?? '0';

    // Proses simpan
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_telegram'])) {
        $new_token = trim($_POST['bot_token'] ?? '');
        $new_enabled = isset($_POST['enabled']) ? '1' : '0';

        $updates = [
            'telegram_bot_token' => $new_token,
            'telegram_notification_enabled' => $new_enabled
        ];

        foreach ($updates as $key => $value) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengaturan WHERE key_setting = ?");
            $stmt->execute([$key]);
            if ($stmt->fetchColumn() > 0) {
                $stmt = $pdo->prepare("UPDATE pengaturan SET value = ? WHERE key_setting = ?");
                $stmt->execute([$value, $key]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO pengaturan (key_setting, value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
        }

        $success = "Pengaturan Telegram berhasil disimpan!";
        $bot_token = $new_token;
        $enabled = $new_enabled;
    }

    // Proses test kirim pesan
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_telegram'])) {
        $test_token = trim($_POST['bot_token'] ?? '');

        if (empty($test_token)) {
            $error = "Bot Token harus diisi untuk test!";
        } else {
            // Ambil semua super admin & admin yang aktif terima notifikasi
            $stmt = $pdo->query("
                SELECT telegram_chat_id FROM users 
                WHERE role IN ('super_admin', 'admin') 
                AND receive_telegram_notification = 1 
                AND telegram_chat_id IS NOT NULL 
                AND telegram_chat_id != ''
            ");
            $chat_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($chat_ids)) {
                $error = "Tidak ada admin yang terdaftar untuk menerima notifikasi. Set chat ID di Manajemen User terlebih dahulu.";
            } else {
                $message = "🔔 *Test Notifikasi*\n\nIni adalah pesan test dari Billing System.\n\nJika Anda menerima pesan ini, berarti konfigurasi Telegram sudah benar. ✅";
                $berhasil = 0;
                $gagal = 0;

                foreach ($chat_ids as $chat_id) {
                    $url = "https://api.telegram.org/bot{$test_token}/sendMessage";
                    $data = [
                        'chat_id' => $chat_id,
                        'text' => $message,
                        'parse_mode' => 'Markdown'
                    ];

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    $result = json_decode($response, true);

                    if ($http_code == 200 && !empty($result['ok'])) {
                        $berhasil++;
                    } else {
                        $gagal++;
                    }
                }

                if ($berhasil > 0) {
                    $success = "✅ Test berhasil dikirim ke {$berhasil} admin" . ($gagal > 0 ? " ({$gagal} gagal)" : "") . "!";
                } else {
                    $error = "❌ Test gagal dikirim ke semua admin. Cek bot token & chat ID.";
                }
            }
        }
    }

} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="card">
    <div class="card-header">
        <i class="fab fa-telegram"></i> Pengaturan Telegram Bot
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Cara Mendapatkan Bot Token:</strong>
            <ol class="mb-0 mt-2">
                <li>Buka Telegram, cari <strong>@BotFather</strong></li>
                <li>Kirim pesan <code>/newbot</code></li>
                <li>Ikuti instruksi, Anda akan mendapat <strong>token</strong></li>
                <li>Salin token ke form di bawah</li>
            </ol>
        </div>

        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Chat ID per user</strong> diatur di 
            <a href="/modules/users/index.php"><strong>Manajemen User</strong></a> → 
            pilih user → klik tombol <strong>Set</strong> (ikon Telegram).
        </div>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Bot Token</label>
                <input type="text" name="bot_token" class="form-control"
                       value="<?= htmlspecialchars($bot_token) ?>"
                       placeholder="Contoh: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                <small class="text-muted">Token dari @BotFather</small>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="enabled"
                       id="enabled" <?= $enabled == '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="enabled">
                    Aktifkan Notifikasi Telegram
                </label>
            </div>

            <button type="submit" name="save_telegram" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <button type="submit" name="test_telegram" class="btn btn-success">
                <i class="fas fa-paper-plane"></i> Test Kirim ke Admin
            </button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
