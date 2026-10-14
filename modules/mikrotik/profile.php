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
    
    // Ambil data IP Pool untuk pilihan remote address
    $stmt = $pdo->prepare("SELECT * FROM mikrotik_ippool WHERE router_id = ? ORDER BY name");
    $stmt->execute([$router_id]);
    $ippools = $stmt->fetchAll();
    
    // Ambil data profil PPPoE dari database lokal
    $stmt = $pdo->prepare("SELECT * FROM mikrotik_ppp_profile WHERE router_id = ? ORDER BY id DESC");
    $stmt->execute([$router_id]);
    $profiles = $stmt->fetchAll();
    
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
        <i class="fas fa-user-lock"></i> Manajemen Profil PPPoE - <?= htmlspecialchars($router['nama_router'] ?? '') ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
        <button class="btn btn-primary btn-sm float-end me-2" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus"></i> Tambah Profil
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
                        <th>Nama Profil</th>
                        <th>Local Address</th>
                        <th>Remote Address (IP Pool)</th>
                        <th>DNS Server</th>
                        <th>Rate Limit</th>
                        <th>Session Timeout</th>
                        <th>Encryption</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profiles as $prof): ?>
                        <tr>
                            <td><?= $prof['id'] ?></small></td>
                            <td><strong><?= htmlspecialchars($prof['name']) ?></strong></small></td>
                            <td><code><?= htmlspecialchars($prof['local_address'] ?? '-') ?></code></small></td>
                            <td><code><?= htmlspecialchars($prof['remote_address'] ?? '-') ?></code></small></td>
                            <td><?= htmlspecialchars($prof['dns_server'] ?? '-') ?></small></td>
                            <td><?= htmlspecialchars($prof['rate_limit'] ?? '-') ?></small></td>
                            <td><?= htmlspecialchars($prof['session_timeout'] ?? '-') ?></small></td>
                            <td><?= $prof['use_encryption'] == 'yes' ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></small></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $prof['id'] ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="profile_delete.php?id=<?= $prof['id'] ?>&router_id=<?= $router_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus profil ini?')">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            </small>
                        </tr>
                        
                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?= $prof['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST" action="profile_save.php">
                                        <div class="modal-header bg-warning text-white">
                                            <h5 class="modal-title">Edit Profil PPPoE</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="id" value="<?= $prof['id'] ?>">
                                            <input type="hidden" name="router_id" value="<?= $router_id ?>">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Nama Profil</label>
                                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($prof['name']) ?>" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Rate Limit</label>
                                                    <input type="text" name="rate_limit" class="form-control" value="<?= htmlspecialchars($prof['rate_limit']) ?>" placeholder="10M/5M">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Local Address</label>
                                                    <input type="text" name="local_address" class="form-control" value="<?= htmlspecialchars($prof['local_address']) ?>" placeholder="10.10.10.1">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Remote Address (IP Pool)</label>
                                                    <select name="remote_address" class="form-control">
                                                        <option value="">-- Pilih IP Pool --</option>
                                                        <?php foreach ($ippools as $pool): ?>
                                                            <option value="<?= htmlspecialchars($pool['name']) ?>" <?= ($prof['remote_address'] == $pool['name']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($pool['name']) ?> (<?= htmlspecialchars($pool['ranges']) ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <small class="text-muted">Pilih IP Pool yang sudah dibuat</small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">DNS Server</label>
                                                    <input type="text" name="dns_server" class="form-control" value="<?= htmlspecialchars($prof['dns_server']) ?>" placeholder="8.8.8.8,8.8.4.4">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Session Timeout</label>
                                                    <input type="text" name="session_timeout" class="form-control" value="<?= htmlspecialchars($prof['session_timeout']) ?>" placeholder="1d">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Idle Timeout</label>
                                                    <input type="text" name="idle_timeout" class="form-control" value="<?= htmlspecialchars($prof['idle_timeout']) ?>" placeholder="1h">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Use Encryption</label>
                                                    <select name="use_encryption" class="form-control">
                                                        <option value="yes" <?= $prof['use_encryption'] == 'yes' ? 'selected' : '' ?>>Yes</option>
                                                        <option value="no" <?= $prof['use_encryption'] == 'no' ? 'selected' : '' ?>>No</option>
                                                    </select>
                                                </div>
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
                    <?php if (empty($profiles)): ?>
                        <tr>
                            <td colspan="9" class="text-center">Belum ada data Profil PPPoE</small></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="profile_save.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Tambah Profil PPPoE</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="router_id" value="<?= $router_id ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Profil</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rate Limit</label>
                            <input type="text" name="rate_limit" class="form-control" placeholder="10M/5M">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Local Address</label>
                            <input type="text" name="local_address" class="form-control" placeholder="10.10.10.1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Remote Address (IP Pool)</label>
                            <select name="remote_address" class="form-control">
                                <option value="">-- Pilih IP Pool --</option>
                                <?php foreach ($ippools as $pool): ?>
                                    <option value="<?= htmlspecialchars($pool['name']) ?>">
                                        <?= htmlspecialchars($pool['name']) ?> (<?= htmlspecialchars($pool['ranges']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Pilih IP Pool yang sudah dibuat di menu IP Pool</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">DNS Server</label>
                            <input type="text" name="dns_server" class="form-control" placeholder="8.8.8.8,8.8.4.4">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Session Timeout</label>
                            <input type="text" name="session_timeout" class="form-control" placeholder="1d">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Idle Timeout</label>
                            <input type="text" name="idle_timeout" class="form-control" placeholder="1h">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Use Encryption</label>
                            <select name="use_encryption" class="form-control">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
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
