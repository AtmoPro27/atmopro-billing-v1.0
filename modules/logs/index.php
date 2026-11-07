<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Hanya superadmin yang bisa melihat log
if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard_fix.php");
    exit();
}

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Ambil semua log
$stmt = $pdo->query("SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 100");
$logs = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-history"></i> Log Aktivitas Admin
       <a href="cleanup.php" class="btn btn-sm btn-danger float-end me-2">Cleanup</a>
<a href="?refresh=1" class="btn btn-sm btn-primary float-end">Refresh</a>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="alert alert-info">Belum ada aktivitas yang tercatat.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Waktu</th>
                            <th>Admin</th>
                            <th>Aksi</th>
                            <th>Target</th>
                            <th>Detail</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= date('d-m-Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td><strong><?= htmlspecialchars($log['admin_username']) ?></strong></td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td>
                                    <?php if ($log['target_type'] && $log['target_id']): ?>
                                        <?= htmlspecialchars($log['target_type']) ?>: <strong><?= htmlspecialchars($log['target_id']) ?></strong>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
