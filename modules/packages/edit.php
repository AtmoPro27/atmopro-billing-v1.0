<?php
ob_start();
require_once __DIR__ . "/../../includes/session_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    // Ambil data paket
    $stmt = $pdo->prepare("SELECT * FROM paket_internet WHERE id = ?");
    $stmt->execute([$id]);
    $package = $stmt->fetch();
    
    if (!$package) {
        header("Location: index.php");
        exit();
    }
    
    // Ambil daftar router
    $stmt = $pdo->query("SELECT id, nama_router, ip_address, lokasi FROM routers WHERE status = 'active'");
    $routers = $stmt->fetchAll();
    
    // Ambil daftar Profil PPPoE dari Manajemen Mikrotik
    $stmt = $pdo->query("SELECT DISTINCT name, router_id FROM mikrotik_ppp_profile ORDER BY name");
    $ppp_profiles = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

// Proses update data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_paket = $_POST['nama_paket'];
    $profil_ppp = $_POST['profil_ppp'] ?: null;
    $harga = str_replace('.', '', $_POST['harga']);
    $router_id = $_POST['router_id'] ?: null;
    $is_active = $_POST['is_active'];
    $deskripsi = $_POST['deskripsi'];
    
    try {
        $stmt = $pdo->prepare("UPDATE paket_internet SET nama_paket = ?, profil_ppp = ?, harga = ?, router_id = ?, is_active = ?, deskripsi = ? WHERE id = ?");
        $stmt->execute([$nama_paket, $profil_ppp, $harga, $router_id, $is_active, $deskripsi, $id]);
        
        echo "<script>window.location.href = 'index.php?success=2';</script>";
        exit();
    } catch(PDOException $e) {
        $error = "Gagal mengupdate paket: " . $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit"></i> Edit Paket Internet: <?= htmlspecialchars($package['nama_paket']) ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Paket <span class="text-danger">*</span></label>
                    <input type="text" name="nama_paket" class="form-control" value="<?= htmlspecialchars($package['nama_paket']) ?>" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Router / Mikrotik</label>
                    <select name="router_id" class="form-control">
                        <option value="">-- Pilih Router --</option>
                        <?php foreach ($routers as $router): ?>
                            <option value="<?= $router['id'] ?>" <?= ($package['router_id'] == $router['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($router['nama_router']) ?> (<?= $router['ip_address'] ?>) - <?= htmlspecialchars($router['lokasi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Profil PPPoE</label>
                    <select name="profil_ppp" class="form-control">
                        <option value="">-- Pilih Profil PPPoE --</option>
                        <?php foreach ($ppp_profiles as $profile): ?>
                            <option value="<?= htmlspecialchars($profile['name']) ?>" <?= ($package['profil_ppp'] == $profile['name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($profile['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Pilih profil PPPoE yang sudah dibuat di Manajemen Mikrotik (Rate Limit diambil dari sini)</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Harga <span class="text-danger">*</span></label>
                    <input type="text" name="harga" class="form-control" value="<?= number_format($package['harga'], 0, ',', '.') ?>" required id="harga" onkeyup="formatRupiah(this)">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= $package['is_active'] == 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $package['is_active'] == 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-12 mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($package['deskripsi']) ?></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<script>
function formatRupiah(element) {
    let value = element.value.replace(/[^,\d]/g, '');
    value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    element.value = value;
}
</script>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
