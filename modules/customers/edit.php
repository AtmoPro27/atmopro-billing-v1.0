<?php
ob_start();
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

$id_pelanggan = $_GET['id'] ?? '';
if (empty($id_pelanggan)) {
    header("Location: index.php");
    exit();
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ?");
    $stmt->execute([$id_pelanggan]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        header("Location: index.php");
        exit();
    }
    
    $stmt = $pdo->query("SELECT id, nama_paket, harga FROM paket_internet WHERE is_active = 1 ORDER BY harga ASC");
    $packages = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_lengkap = $_POST['nama_lengkap'];
    $alamat = $_POST['alamat'];
    $no_telepon = $_POST['no_telepon'];
    $paket_id = $_POST['paket_id'] ?: null;
    $jatuh_tempo = $_POST['jatuh_tempo'] ?: null;
    if ($jatuh_tempo && strlen($jatuh_tempo) <= 10) {
        $jatuh_tempo .= ' 23:59:59';
    } else if ($jatuh_tempo) {
        // Kalau datetime-local, paksa detik jadi 23:59:59
        $jatuh_tempo = date('Y-m-d 23:59:59', strtotime($jatuh_tempo));
    }
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE pelanggan SET nama_lengkap = ?, alamat = ?, no_telepon = ?, paket_id = ?, jatuh_tempo = ?, status = ? WHERE id_pelanggan = ?");
        $stmt->execute([$nama_lengkap, $alamat, $no_telepon, $paket_id, $jatuh_tempo, $status, $id_pelanggan]);
        
        // Update jatuh tempo di invoice yang pending
        $stmt_inv = $pdo->prepare("UPDATE invoice SET tgl_jatuh_tempo = ? WHERE pelanggan_id = ? AND status = 'pending'");
        $stmt_inv->execute([$jatuh_tempo, $customer['id']]);
        
        echo "<script>window.location.href = 'index.php?success=2';</script>";
        exit();
    } catch(PDOException $e) {
        $error = "Gagal mengupdate data: " . $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit"></i> Edit Pelanggan: <?= htmlspecialchars($customer['id_pelanggan']) ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">ID Pelanggan</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($customer['id_pelanggan']) ?>" readonly disabled>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($customer['nama_lengkap']) ?>" required>
                </div>
                
                <div class="col-md-12 mb-3">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($customer['alamat']) ?></textarea>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">No Telepon</label>
                    <input type="text" name="no_telepon" class="form-control" value="<?= htmlspecialchars($customer['no_telepon']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Paket Internet</label>
                    <select name="paket_id" class="form-control">
                        <option value="">-- Pilih Paket --</option>
                        <?php foreach ($packages as $pkg): ?>
                            <option value="<?= $pkg['id'] ?>" <?= ($customer['paket_id'] == $pkg['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pkg['nama_paket']) ?> - Rp <?= number_format($pkg['harga'], 0, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Jatuh Tempo</label>
                    <input type="datetime-local" name="jatuh_tempo" class="form-control" value="<?= $customer['jatuh_tempo'] ? date('Y-m-d\TH:i', strtotime($customer['jatuh_tempo'])) : '' ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= $customer['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $customer['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="blocked" <?= $customer['status'] == 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
