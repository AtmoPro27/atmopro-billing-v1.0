<?php
require_once __DIR__ . "/../../includes/session_check.php";
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'super_admin') {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';
include '../../includes/activity_log.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Ambil daftar user
$stmt = $pdo->query("
    SELECT 
        id, 
        username, 
        fullname, 
        email, 
        phone, 
        role, 
        is_active,
        telegram_chat_id,
        receive_telegram_notification,
        DATE_FORMAT(created_at, '%d-%m-%Y') as created_date 
    FROM users 
    ORDER BY id
");
$users = $stmt->fetchAll();

// Role labels
$roleLabels = [
    'super_admin' => 'Super Admin',
    'kasir' => 'Kasir',
    'teknisi' => 'Teknisi',
    'pelanggan' => 'Pelanggan'
];
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-users"></i> Manajemen User
        <a href="add.php" class="btn btn-primary btn-sm float-end">
            <i class="fas fa-plus"></i> Tambah User
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="userTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Telegram</th>
                        <th>Status</th>
                        <th>Tgl Daftar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['fullname']) ?></td>
                        <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                        <td>
                            <span class="badge bg-<?= $user['role'] == 'super_admin' ? 'danger' : ($user['role'] == 'kasir' ? 'success' : 'warning') ?>">
                                <?= $roleLabels[$user['role']] ?? $user['role'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($user['telegram_chat_id'])): ?>
                                <span class="badge bg-success" title="Chat ID: <?= htmlspecialchars($user['telegram_chat_id']) ?>">
                                    <i class="fab fa-telegram"></i> <?= htmlspecialchars($user['telegram_chat_id']) ?>
                                </span>
                                <?php if ($user['receive_telegram_notification']): ?>
                                    <span class="badge bg-info">✅ Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">🔇 Nonaktif</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['is_active'] == 1): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $user['created_date'] ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="update_telegram.php?id=<?= $user['id'] ?>" class="btn btn-info" title="Set Telegram Chat ID">
                                    <i class="fab fa-telegram"></i> Set
                                </a>
                                <a href="edit.php?id=<?= $user['id'] ?>" class="btn btn-warning" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete.php?id=<?= $user['id'] ?>" class="btn btn-danger" title="Hapus User" onclick="return confirm('Yakin ingin menghapus user ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#userTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
        },
        "order": [[0, "asc"]]
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
