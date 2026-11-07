<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /index.php");
    exit();
}

require_once '../../config/database.php';
$pdo = Database::getInstance()->getConnection();

// Proses update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
    $receive_notification = isset($_POST['receive_notification']) ? 1 : 0;
    
    if ($user_id) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = ?, receive_telegram_notification = ? WHERE id = ?");
            $stmt->execute([$telegram_chat_id, $receive_notification, $user_id]);
            $success = "Data Telegram berhasil diupdate!";
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Ambil data user
$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT id, username, fullname, role, phone, email, telegram_chat_id, receive_telegram_notification FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: index.php");
    exit();
}

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <i class="fab fa-telegram"></i> Konfigurasi Telegram - <?= htmlspecialchars($user['fullname']) ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-md-6">
                <table class="table table-bordered">
                    <tr>
                        <th style="width:30%">Username</th>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                    </tr>
                    <tr>
                        <th>Nama Lengkap</th>
                        <td><?= htmlspecialchars($user['fullname']) ?></td>
                    </tr>
                    <tr>
                        <th>Role</th>
                        <td><span class="badge bg-primary"><?= strtoupper($user['role']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>No HP</th>
                        <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Cara Mendapatkan Chat ID:</h6>
                    <ol>
                        <li>Buka Telegram di HP/Desktop</li>
                        <li>Cari bot: <strong>@your_bot_username</strong></li>
                        <li>Kirim pesan: <strong>Halo</strong> atau <strong>Test</strong></li>
                        <li>Chat ID akan otomatis terdeteksi</li>
                        <li>Atau masukkan manual di form bawah</li>
                    </ol>
                    <hr>
                    <small>
                        <i class="fas fa-link"></i> Link bot: 
                        <a href="https://t.me/your_bot_username" target="_blank">https://t.me/your_bot_username</a>
                    </small>
                </div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            
            <div class="mb-3">
                <label class="form-label">Telegram Chat ID</label>
                <input type="text" name="telegram_chat_id" class="form-control" 
                       value="<?= htmlspecialchars($user['telegram_chat_id'] ?? '') ?>"
                       placeholder="Contoh: 241082535">
                <small class="text-muted">
                    Masukkan Chat ID dari Telegram. Biarkan kosong jika belum punya.
                </small>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" name="receive_notification" class="form-check-input" 
                       id="receive_notification" <?= ($user['receive_telegram_notification'] ?? 1) == 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="receive_notification">
                    <i class="fab fa-telegram"></i> Aktifkan Notifikasi Telegram
                </label>
                <small class="d-block text-muted">
                    Jika diaktifkan, user akan menerima notifikasi tiket via Telegram
                </small>
            </div>
            
            <button type="submit" name="update" class="btn btn-primary">
                <i class="fas fa-save"></i> Simpan
            </button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
        
        <?php if (!empty($user['telegram_chat_id'])): ?>
        <hr>
        <div class="mt-3">
            <h6><i class="fas fa-paper-plane"></i> Test Kirim Pesan</h6>
            <button class="btn btn-success btn-sm" onclick="testTelegram(<?= $user['id'] ?>)">
                <i class="fab fa-telegram"></i> Kirim Test
            </button>
            <div id="test_result" class="mt-2"></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function testTelegram(userId) {
    if (!confirm('Kirim pesan test ke Telegram?')) return;
    
    $('#test_result').html('<div class="alert alert-info">Mengirim...</div>');
    
    $.ajax({
        url: 'test_telegram_user.php',
        type: 'POST',
        data: { user_id: userId },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                $('#test_result').html('<div class="alert alert-success">✅ ' + res.message + '</div>');
            } else {
                $('#test_result').html('<div class="alert alert-danger">❌ ' + res.message + '</div>');
            }
        },
        error: function() {
            $('#test_result').html('<div class="alert alert-danger">Gagal mengirim request</div>');
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
