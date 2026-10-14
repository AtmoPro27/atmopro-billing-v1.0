<?php
require_once __DIR__ . "/../../includes/session_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

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
    
    // Ambil data bandwidth dari database lokal
    $stmt = $pdo->prepare("SELECT * FROM mikrotik_bandwidth WHERE router_id = ? ORDER BY id DESC");
    $stmt->execute([$router_id]);
    $bandwidths = $stmt->fetchAll();
    
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
        <i class="fas fa-tachometer-alt"></i> Manajemen Bandwidth - <?= htmlspecialchars($router['nama_router'] ?? '') ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
        <button class="btn btn-primary btn-sm float-end me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Tambah Bandwidth
        </button>
    </div>
    <div class="card-body">
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?= $success_msg ?></div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Target</th>
                        <th>Max Limit Down</th>
                        <th>Max Limit Up</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bandwidths as $bw): ?>
                        <tr>
                            <td><?= htmlspecialchars($bw['name']) ?></td>
                            <td><?= htmlspecialchars($bw['target']) ?></td>
                            <td><?= htmlspecialchars($bw['max_limit_down']) ?></small></td>
                            <td><?= htmlspecialchars($bw['max_limit_up']) ?></small></td>
                            <td>
                                <a href="bandwidth_edit.php?id=<?= $bw['id'] ?>&router_id=<?= $router_id ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="bandwidth_delete.php?id=<?= $bw['id'] ?>&router_id=<?= $router_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($bandwidths)): ?>
                        <tr><td colspan="5" class="text-center">Belum ada data bandwidth</td></tr>
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
            <form method="POST" action="bandwidth_save.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Tambah Bandwidth</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="router_id" value="<?= $router_id ?>">
                    <div class="mb-3">
                        <label>Nama</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Target</label>
                        <input type="text" name="target" class="form-control" required placeholder="Contoh: 192.168.1.0/24">
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label>Max Limit Down</label>
                            <input type="text" name="max_limit_down" class="form-control" placeholder="10M">
                        </div>
                        <div class="col-md-6">
                            <label>Max Limit Up</label>
                            <input type="text" name="max_limit_up" class="form-control" placeholder="5M">
                        </div>
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
