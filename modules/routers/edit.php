<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

// Proses update data HARUS di atas sebelum include header
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        require_once __DIR__ . "/../../config/database.php";
        $pdo = Database::getInstance()->getConnection();

        $nama_router = $_POST['nama_router'];
        $ip_address = $_POST['ip_address'];
        $port_api = $_POST['port_api'] ?: 8728;
        $username = $_POST['username'];
        $password = $_POST['password'];
        $lokasi = $_POST['lokasi'];
        $status = $_POST['status'];

        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE routers SET nama_router = ?, ip_address = ?, port_api = ?, username = ?, password = ?, lokasi = ?, status = ? WHERE id = ?");
            $stmt->execute([$nama_router, $ip_address, $port_api, $username, $password, $lokasi, $status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE routers SET nama_router = ?, ip_address = ?, port_api = ?, username = ?, lokasi = ?, status = ? WHERE id = ?");
            $stmt->execute([$nama_router, $ip_address, $port_api, $username, $lokasi, $status, $id]);
        }

        header("Location: index.php?success=2");
        exit();

    } catch(PDOException $e) {
        $error = "Gagal mengupdate router: " . $e->getMessage();
    }
}

// Ambil data router untuk ditampilkan di form
try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->prepare("SELECT * FROM routers WHERE id = ?");
    $stmt->execute([$id]);
    $router = $stmt->fetch();

    if (!$router) {
        header("Location: index.php");
        exit();
    }

} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit"></i> Edit Router: <?= htmlspecialchars($router['nama_router']) ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="formRouter">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Router <span class="text-danger">*</span></label>
                    <input type="text" name="nama_router" id="nama_router" class="form-control" value="<?= htmlspecialchars($router['nama_router']) ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">IP Address <span class="text-danger">*</span></label>
                    <input type="text" name="ip_address" id="ip_address" class="form-control" value="<?= htmlspecialchars($router['ip_address']) ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Port API</label>
                    <input type="number" name="port_api" id="port_api" class="form-control" value="<?= $router['port_api'] ?? 8728 ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control" value="<?= htmlspecialchars($router['username']) ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Password (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Isi untuk mengubah password">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Lokasi</label>
                    <input type="text" name="lokasi" class="form-control" value="<?= htmlspecialchars($router['lokasi']) ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= $router['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $router['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <hr>

            <!-- Panel Test Koneksi -->
            <div class="card border-info mb-3">
                <div class="card-header bg-info text-white py-2">
                    <i class="fas fa-plug"></i> Test Koneksi
                </div>
                <div class="card-body py-2">
                    <p class="small text-muted mb-2">
                        Test koneksi ke router. Kalau mau pakai password baru, isi field password dulu.
                        Kalau kosong, pakai password lama.
                    </p>
                    <button type="button" class="btn btn-info btn-sm" id="btnTest" onclick="testKoneksi()">
                        <i class="fas fa-plug"></i> Test Koneksi Sekarang
                    </button>
                    <div id="testResult" class="mt-2"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<script>
function testKoneksi() {
    const btn = document.getElementById('btnTest');
    const result = document.getElementById('testResult');

    const ip = document.getElementById('ip_address').value.trim();
    const port = document.getElementById('port_api').value.trim() || '8728';
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;

    if (!ip) {
        result.innerHTML = '<div class="alert alert-warning py-2 mb-0 small">IP Address wajib diisi!</div>';
        return;
    }
    if (!username) {
        result.innerHTML = '<div class="alert alert-warning py-2 mb-0 small">Username wajib diisi!</div>';
        return;
    }

    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test...';
    result.innerHTML = '<div class="alert alert-info py-2 mb-0 small"><i class="fas fa-spinner fa-spin"></i> Menghubungi router...</div>';

    const formData = new FormData();
    formData.append('ip_address', ip);
    formData.append('port_api', port);
    formData.append('username', username);
    formData.append('password', password);
    formData.append('router_id', '<?= (int)$router['id'] ?>');

    fetch('test_connection_temp.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;

        if (data.success) {
            result.innerHTML = '<div class="alert alert-success py-2 mb-0 small">' +
                '<i class="fas fa-check-circle"></i> <strong>BERHASIL!</strong><br>' +
                data.message + '</div>';
        } else {
            result.innerHTML = '<div class="alert alert-danger py-2 mb-0 small">' +
                '<i class="fas fa-times-circle"></i> <strong>GAGAL!</strong><br>' +
                data.message + '</div>';
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        result.innerHTML = '<div class="alert alert-danger py-2 mb-0 small">' +
            '<i class="fas fa-times-circle"></i> Error: ' + e.message + '</div>';
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
