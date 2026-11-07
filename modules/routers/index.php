<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

require_once __DIR__ . "/../../config/database.php";

$pdo = Database::getInstance()->getConnection();
$error = '';

try {
    $stmt = $pdo->query("SELECT * FROM routers ORDER BY id DESC");
    $routers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = $e->getMessage();
    $routers = [];
}

include __DIR__ . "/../../includes/header.php";
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><i class="fas fa-server"></i> Daftar Router / MikroTik</h3>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Router
        </a>
    </div>

    <?php if (isset($_GET['delete'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> Router berhasil dihapus!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <?php if ($_GET['error'] == 'still_used'): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Router tidak bisa dihapus!</strong>
                Masih ada <strong><?= (int)($_GET['count'] ?? 0) ?></strong> pelanggan aktif yang pakai router ini.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['error'] == 'db_error'): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-times-circle"></i> Gagal hapus router.
                <?php if (!empty($_GET['msg'])): ?>
                    <br><small><strong>Error:</strong> <?= htmlspecialchars($_GET['msg']) ?></small>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php else: ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-times-circle"></i> Terjadi kesalahan.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-list"></i> Total: <?= count($routers) ?> router
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="50">ID</th>
                            <th>Nama Router</th>
                            <th>IP Address</th>
                            <th>Port API</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th width="240">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($routers)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    Belum ada router terdaftar.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($routers as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['id']) ?></td>
                                    <td><strong><?= htmlspecialchars($r['nama_router']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($r['ip_address']) ?></code></td>
                                    <td><?= htmlspecialchars($r['port_api'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($r['username'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($r['status'] == 'active'): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-info" 
                                                onclick="testRouter(<?= (int)$r['id'] ?>, this)"
                                                title="Test koneksi ke router">
                                            <i class="fas fa-plug"></i> Test
                                        </button>
                                        <a href="edit.php?id=<?= urlencode($r['id']) ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="delete.php?id=<?= urlencode($r['id']) ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Yakin hapus router <?= htmlspecialchars($r['nama_router']) ?>?\n\nSemua data terkait akan dihapus (bandwidth, ippool, profile).');">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function testRouter(id, btn) {
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test...';
    
    fetch('test_connection.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'router_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        
        if (data.success) {
            alert('✅ KONEKSI BERHASIL\n\n' + (data.message || 'Router dapat diakses'));
        } else {
            alert('❌ KONEKSI GAGAL\n\n' + (data.message || 'Router tidak dapat diakses'));
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        alert('❌ ERROR\n\n' + e.message);
    });
}
</script>

<?php include __DIR__ . "/../../includes/footer.php"; ?>
