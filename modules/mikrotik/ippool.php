<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$router_id = $_GET['router_id'] ?? '';
if (empty($router_id)) {
    header("Location: index.php");
    exit();
}

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    // Ambil data router
    $stmt = $pdo->prepare("SELECT * FROM routers WHERE id = ?");
    $stmt->execute([$router_id]);
    $router = $stmt->fetch();
    
    // Ambil data IP Pool dari database lokal
    $stmt = $pdo->prepare("SELECT * FROM mikrotik_ippool WHERE router_id = ? ORDER BY id DESC");
    $stmt->execute([$router_id]);
    $ippools = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}

$success_msg = '';
if (isset($_GET['success'])) {
    $success_msg = 'Data berhasil disimpan!';
} elseif (isset($_GET['delete'])) {
    $success_msg = 'Data berhasil dihapus!';
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-network-wired"></i> Manajemen IP Pool - <?= htmlspecialchars($router['nama_router'] ?? '') ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
        <button class="btn btn-primary btn-sm float-end me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Tambah IP Pool
        </button>
    </div>
    <div class="card-body">
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nama Pool</th>
                        <th>Ranges</th>
                        <th>Next Pool</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ippools as $pool): ?>
                        <tr>
                            <td><?= $pool['id'] ?></td>
                            <td><?= htmlspecialchars($pool['name']) ?></td>
                            <td><code><?= htmlspecialchars($pool['ranges']) ?></code></td>
                            <td><?= htmlspecialchars($pool['next_pool'] ?? '-') ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $pool['id'] ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="ippool_delete.php?id=<?= $pool['id'] ?>&router_id=<?= $router_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus IP Pool ini?')">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            </td>
                        </tr>
                        
                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?= $pool['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="ippool_save.php">
                                        <div class="modal-header bg-warning text-white">
                                            <h5 class="modal-title">Edit IP Pool</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="id" value="<?= $pool['id'] ?>">
                                            <input type="hidden" name="router_id" value="<?= $router_id ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Nama Pool</label>
                                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($pool['name']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Ranges</label>
                                                <input type="text" name="ranges" class="form-control" value="<?= htmlspecialchars($pool['ranges']) ?>" required>
                                                <small class="text-muted">Contoh: 10.10.10.1-10.10.10.100</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Next Pool</label>
                                                <input type="text" name="next_pool" class="form-control" value="<?= htmlspecialchars($pool['next_pool']) ?>">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-warning">Update</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($ippools)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Belum ada data IP Pool</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="ippool_save.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Tambah IP Pool</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="router_id" value="<?= $router_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Nama Pool</label>
                        <input type="text" name="name" class="form-control" required>
                        <small class="text-muted">Contoh: pool_pppoe</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ranges</label>
                        <input type="text" name="ranges" class="form-control" required>
                        <small class="text-muted">Contoh: 10.10.10.1-10.10.10.100</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Next Pool</label>
                        <input type="text" name="next_pool" class="form-control">
                        <small class="text-muted">Pool berikutnya jika habis (opsional)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../../includes/footer.php'; ?>
