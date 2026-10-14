<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard.php");
    exit();
}

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();

    // Buat tabel payment_gateway jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_gateway (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        is_active TINYINT(1) DEFAULT 0,
        api_key TEXT,
        api_secret TEXT,
        merchant_id VARCHAR(100),
        environment ENUM('sandbox', 'production') DEFAULT 'sandbox',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Tambahkan kolom jika belum ada
    $columns = $pdo->query("SHOW COLUMNS FROM payment_gateway")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('api_key', $columns)) {
        $pdo->exec("ALTER TABLE payment_gateway ADD COLUMN api_key TEXT AFTER is_active");
    }
    if (!in_array('api_secret', $columns)) {
        $pdo->exec("ALTER TABLE payment_gateway ADD COLUMN api_secret TEXT AFTER api_key");
    }
    if (!in_array('merchant_id', $columns)) {
        $pdo->exec("ALTER TABLE payment_gateway ADD COLUMN merchant_id VARCHAR(100) AFTER api_secret");
    }
    if (!in_array('environment', $columns)) {
        $pdo->exec("ALTER TABLE payment_gateway ADD COLUMN environment ENUM('sandbox', 'production') DEFAULT 'sandbox' AFTER merchant_id");
    }

    // Insert default gateways jika belum ada
    $gateways = ['Duitku', 'Midtrans', 'Xendit', 'Tripay', 'Paytren'];
    foreach ($gateways as $gw) {
        $check = $pdo->prepare("SELECT id FROM payment_gateway WHERE name = ?");
        $check->execute([$gw]);
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO payment_gateway (name, is_active) VALUES (?, 0)");
            $stmt->execute([$gw]);
        }
    }

    // Proses update gateway
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_gateway'])) {
        $id = $_POST['id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $api_key = $_POST['api_key'] ?? '';
        $api_secret = $_POST['api_secret'] ?? '';
        $merchant_id = $_POST['merchant_id'] ?? '';
        $environment = $_POST['environment'] ?? 'sandbox';

        $stmt = $pdo->prepare("UPDATE payment_gateway SET is_active = ?, api_key = ?, api_secret = ?, merchant_id = ?, environment = ? WHERE id = ?");
        $stmt->execute([$is_active, $api_key, $api_secret, $merchant_id, $environment, $id]);
        $success = "Pengaturan payment gateway berhasil diupdate!";
    }

    // Proses tambah bank
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_bank'])) {
        $bank_name = $_POST['bank_name'];
        $account_number = $_POST['account_number'];
        $account_name = $_POST['account_name'];

        $stmt = $pdo->prepare("INSERT INTO payment_settings (bank_name, account_number, account_name) VALUES (?, ?, ?)");
        $stmt->execute([$bank_name, $account_number, $account_name]);
        $success = "Bank berhasil ditambahkan!";
    }

    // Proses hapus bank
    if (isset($_GET['delete'])) {
        $stmt = $pdo->prepare("DELETE FROM payment_settings WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = "Bank berhasil dihapus!";
    }

    // Ambil data gateways
    $stmt = $pdo->query("SELECT * FROM payment_gateway ORDER BY name");
    $gateways = $stmt->fetchAll();

    // Ambil data bank
    $stmt = $pdo->query("SELECT * FROM payment_settings ORDER BY id");
    $banks = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<style>
    .gateway-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        background: #f9f9f9;
    }
    .gateway-card .badge-active {
        background: #28a745;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
    }
    .gateway-card .badge-inactive {
        background: #dc3545;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
    }
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-credit-card"></i> Pengaturan Pembayaran
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- PAYMENT GATEWAY - DROPDOWN -->
        <!-- ========================================== -->
        <h5 class="mb-3"><i class="fas fa-plug"></i> Payment Gateway</h5>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Pilih payment gateway yang aktif dan isi konfigurasi API-nya.
        </div>

        <!-- Dropdown Pilih Gateway -->
        <div class="mb-3">
            <label class="form-label fw-bold">Pilih Gateway</label>
            <select class="form-select" id="gatewaySelect" onchange="showGateway(this.value)">
                <option value="">-- Pilih Payment Gateway --</option>
                <?php foreach ($gateways as $gw): ?>
                    <option value="<?= $gw['id'] ?>" <?= $gw['is_active'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($gw['name']) ?> <?= $gw['is_active'] ? '✅' : '⏸️' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Form Gateway (akan tampil sesuai pilihan) -->
        <?php foreach ($gateways as $gw): ?>
            <div class="gateway-card" id="gateway_<?= $gw['id'] ?>" style="display: none;">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $gw['id'] ?>">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><?= htmlspecialchars($gw['name']) ?></h5>
                        <div>
                            <?php if ($gw['is_active']): ?>
                                <span class="badge-active">✅ Aktif</span>
                            <?php else: ?>
                                <span class="badge-inactive">❌ Nonaktif</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="active_<?= $gw['id'] ?>" <?= $gw['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="active_<?= $gw['id'] ?>">Aktifkan Gateway ini</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Environment</label>
                                <select name="environment" class="form-select form-select-sm">
                                    <option value="sandbox" <?= $gw['environment'] == 'sandbox' ? 'selected' : '' ?>>Sandbox (Testing)</option>
                                    <option value="production" <?= $gw['environment'] == 'production' ? 'selected' : '' ?>>Production (Live)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">API Key / Client ID</label>
                                <input type="text" name="api_key" class="form-control form-control-sm" value="<?= htmlspecialchars($gw['api_key'] ?? '') ?>" placeholder="Masukkan API Key">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">API Secret / Client Secret</label>
                                <input type="password" name="api_secret" class="form-control form-control-sm" value="<?= htmlspecialchars($gw['api_secret'] ?? '') ?>" placeholder="Masukkan API Secret">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Merchant ID</label>
                                <input type="text" name="merchant_id" class="form-control form-control-sm" value="<?= htmlspecialchars($gw['merchant_id'] ?? '') ?>" placeholder="Masukkan Merchant ID">
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" name="update_gateway" class="btn btn-primary btn-sm">
                            <i class="fas fa-save"></i> Simpan <?= htmlspecialchars($gw['name']) ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>

        <script>
        function showGateway(id) {
            // Sembunyikan semua
            document.querySelectorAll('.gateway-card').forEach(el => {
                el.style.display = 'none';
            });
            // Tampilkan yang dipilih
            if (id) {
                var el = document.getElementById('gateway_' + id);
                if (el) el.style.display = 'block';
            }
        }

        // Tampilkan gateway yang aktif saat load
        document.addEventListener('DOMContentLoaded', function() {
            var select = document.getElementById('gatewaySelect');
            var selected = select.value;
            if (selected) {
                showGateway(selected);
            }
        });
        </script>

        <hr>

        <!-- ========================================== -->
        <!-- REKENING BANK -->
        <!-- ========================================== -->
        <h5 class="mb-3"><i class="fas fa-university"></i> Rekening Bank</h5>
        <button class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#addBankModal">
            <i class="fas fa-plus"></i> Tambah Rekening Bank
        </button>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Bank</th>
                        <th>No Rekening</th>
                        <th>Atas Nama</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banks as $bank): ?>
                        <tr>
                            <td><?= htmlspecialchars($bank['bank_name']) ?></td>
                            <td><?= htmlspecialchars($bank['account_number']) ?></td>
                            <td><?= htmlspecialchars($bank['account_name']) ?></td>
                            <td>
                                <a href="?delete=<?= $bank['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus?')">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($banks)): ?>
                        <tr><td colspan="4" class="text-center">Belum ada rekening bank</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Bank -->
<div class="modal fade" id="addBankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Tambah Rekening Bank</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Bank</label>
                        <input type="text" name="bank_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No Rekening</label>
                        <input type="text" name="account_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Atas Nama</label>
                        <input type="text" name="account_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add_bank" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include '../../includes/footer.php'; ?>
