<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';
?>

<style>
.main-content {
    margin-left: 0 !important;
    padding: 20px !important;
    width: 100% !important;
}
.container-fluid {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
}
.card {
    width: 100% !important;
    margin: 0 !important;
}
.row {
    margin: 0 !important;
    width: 100% !important;
}
.col-md-6 {
    width: 48% !important;
    float: left !important;
    margin-right: 2% !important;
}
.col-md-6:nth-child(2n) {
    margin-right: 0 !important;
}
body {
    padding-left: 260px !important;
}
@media (max-width: 768px) {
    body {
        padding-left: 0 !important;
    }
    .col-md-6 {
        width: 100% !important;
        float: none !important;
        margin-right: 0 !important;
    }
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus"></i> Tambah Paket Internet
                <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
            </div>
            <div class="card-body">
                <?php
                // Proses tambah data
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    try {
                        require_once __DIR__ . "/../../config/database.php";
                        $pdo = Database::getInstance()->getConnection();

                        $nama_paket = $_POST['nama_paket'];
                        $profil_ppp = $_POST['profil_ppp'] ?: null;
                        $harga = str_replace('.', '', $_POST['harga']);
                        $router_id = $_POST['router_id'] ?: null;
                        $is_active = $_POST['is_active'] ?? 1;
                        $deskripsi = $_POST['deskripsi'] ?? '';

                        $stmt = $pdo->prepare("INSERT INTO paket_internet (nama_paket, profil_ppp, harga, router_id, is_active, deskripsi) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nama_paket, $profil_ppp, $harga, $router_id, $is_active, $deskripsi]);

                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> Paket <strong>' . htmlspecialchars($nama_paket) . '</strong> berhasil ditambahkan!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                        echo '<script>setTimeout(function(){ window.location.href = "index.php"; }, 2000);</script>';
                    } catch(PDOException $e) {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> Error: ' . $e->getMessage() . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                    }
                }
                ?>

                <form method="POST" id="formTambahPaket">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Paket <span class="text-danger">*</span></label>
                            <input type="text" name="nama_paket" class="form-control" required placeholder="Contoh: GOLD_100">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Router / Mikrotik</label>
                            <select name="router_id" class="form-control" id="router_id">
                                <option value="">-- Pilih Router --</option>
                                <?php
                                try {
                                    require_once __DIR__ . "/../../config/database.php";
                                    $pdo = Database::getInstance()->getConnection();
                                    
                                    // Query ambil semua router active
                                    $stmt_router = $pdo->query("SELECT id, nama_router, ip_address, lokasi, port_api FROM routers WHERE status = 'active' ORDER BY nama_router");
                                    
                                    while ($router = $stmt_router->fetch()) {
                                        $display = htmlspecialchars($router['nama_router']) . ' (' . $router['ip_address'] . ')';
                                        if (!empty($router['lokasi'])) {
                                            $display .= ' - ' . htmlspecialchars($router['lokasi']);
                                        }
                                        echo '<option value="' . $router['id'] . '">' . $display . '</option>';
                                    }
                                } catch(PDOException $e) {
                                    echo '<option value="">Error: ' . $e->getMessage() . '</option>';
                                }
                                ?>
                            </select>
                            <small class="text-muted">Router yang akan digunakan untuk paket ini</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Profil PPPoE</label>
                            <select name="profil_ppp" class="form-control" id="profil_ppp">
                                <option value="">-- Pilih Profil PPPoE --</option>
                                <?php
                                try {
                                    require_once __DIR__ . "/../../config/database.php";
                                    $pdo_profile = Database::getInstance()->getConnection();
                                    $stmt_profile = $pdo_profile->query("SELECT DISTINCT name FROM mikrotik_ppp_profile ORDER BY name");
                                    while ($profile = $stmt_profile->fetch()) {
                                        echo '<option value="' . htmlspecialchars($profile['name']) . '">' . htmlspecialchars($profile['name']) . '</option>';
                                    }
                                } catch(PDOException $e) {
                                    echo '<option value="">Error loading profiles</option>';
                                }
                                ?>
                            </select>
                            <small class="text-muted">Rate Limit diambil dari profil ini (buat profil di Manajemen Mikrotik terlebih dahulu)</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Harga <span class="text-danger">*</span></label>
                            <input type="text" name="harga" class="form-control" required id="harga" onkeyup="formatRupiah(this)" placeholder="Contoh: 200000">
                            <small class="text-muted">Format: angka tanpa titik</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-control">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="3" placeholder="Deskripsi paket (opsional)"></textarea>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function formatRupiah(element) {
    let value = element.value.replace(/[^,\d]/g, '');
    value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    element.value = value;
}

// Validasi form sebelum submit
document.getElementById('formTambahPaket').addEventListener('submit', function(e) {
    let router = document.getElementById('router_id').value;
    let profil = document.getElementById('profil_ppp').value;
    
    if (!router) {
        e.preventDefault();
        alert('Silakan pilih Router terlebih dahulu!');
        document.getElementById('router_id').focus();
        return false;
    }
    
    if (!profil) {
        e.preventDefault();
        alert('Silakan pilih Profil PPPoE terlebih dahulu!');
        document.getElementById('profil_ppp').focus();
        return false;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>