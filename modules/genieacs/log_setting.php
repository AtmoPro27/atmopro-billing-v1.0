<?php
require_once __DIR__ . '/../../includes/auth_check.php';

require_once '../../config/database.php';
$pdo = Database::getInstance()->getConnection();

// ============================================
// HANDLE HAPUS — SEBELUM HEADER DI-LOAD
// ============================================
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_one' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM settings_log WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $_SESSION['success_message'] = '✅ Log berhasil dihapus';
        header("Location: log_setting.php");
        exit();
    }
    if ($_POST['action'] === 'delete_all') {
        $pdo->exec("DELETE FROM settings_log WHERE setting_key LIKE 'genieacs_%'");
        $_SESSION['success_message'] = '✅ Semua log GenieACS berhasil dihapus';
        header("Location: log_setting.php");
        exit();
    }
    if ($_POST['action'] === 'delete_old' && isset($_POST['days'])) {
        $days = (int)$_POST['days'];
        $stmt = $pdo->prepare("DELETE FROM settings_log WHERE setting_key LIKE 'genieacs_%' AND changed_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        $deleted = $stmt->rowCount();
        $_SESSION['success_message'] = "✅ $deleted log lama (>{$days} hari) dihapus";
        header("Location: log_setting.php");
        exit();
    }
}

// ============================================
// BARU LOAD HEADER SETELAH PROSES HAPUS
// ============================================
include '../../includes/header.php';

// Pagination
$limit = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$total = $pdo->query("SELECT COUNT(*) FROM settings_log WHERE setting_key LIKE 'genieacs_%'")->fetchColumn();
$total_pages = max(1, ceil($total / $limit));

$stmt = $pdo->prepare("SELECT * FROM settings_log WHERE setting_key LIKE 'genieacs_%' ORDER BY changed_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 text-gray-800">📊 Log Perubahan Setting GenieACS</h1>
        <a href="index.php" class="btn btn-secondary">← Kembali</a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success_message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Tombol Aksi -->
    <div class="card shadow mb-3">
        <div class="card-body">
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalHapusSemua">
                    🗑️ Hapus Semua Log
                </button>
                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalHapusLama">
                    ⏰ Hapus Log Lama
                </button>
                <span class="text-muted ms-auto align-self-center">
                    Total: <strong><?= $total ?></strong> log
                </span>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header">
            <i class="fas fa-history"></i> Riwayat Perubahan
        </div>
        <div class="card-body">
            <?php if (count($logs) == 0): ?>
                <div class="alert alert-info">Belum ada perubahan setting.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Setting</th>
                                <th>Nilai Lama</th>
                                <th>Nilai Baru</th>
                                <th>Diubah Oleh</th>
                                <th width="80">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($log['changed_at'])) ?></td>
                                    <td><code><?= htmlspecialchars($log['setting_key']) ?></code></td>
                                    <td><?= htmlspecialchars($log['old_value'] ?? '-') ?></td>
                                    <td><strong><?= htmlspecialchars($log['new_value'] ?? '-') ?></strong></td>
                                    <td><?= htmlspecialchars($log['changed_by'] ?? '-') ?></td>
                                    <td>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Hapus log ini?')">
                                            <input type="hidden" name="action" value="delete_one">
                                            <input type="hidden" name="id" value="<?= $log['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                🗑️
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Hapus Semua -->
<div class="modal fade" id="modalHapusSemua" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete_all">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">🗑️ Hapus Semua Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Yakin hapus <strong>SEMUA</strong> log GenieACS?</p>
                    <p class="text-danger"><strong>⚠️ Tindakan ini tidak bisa dibatalkan!</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus Semua</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Lama -->
<div class="modal fade" id="modalHapusLama" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete_old">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">⏰ Hapus Log Lama</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Hapus log lebih dari:</label>
                        <div class="input-group">
                            <input type="number" name="days" class="form-control" value="30" min="1" max="365">
                            <span class="input-group-text">hari</span>
                        </div>
                        <small class="text-muted">Contoh: 30 = hapus log lebih dari 30 hari yang lalu</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Hapus Log Lama</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
