<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$error = '';
$success_redirect = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once '../../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    
    $pelanggan_id = $_POST['pelanggan_id'];
    $jumlah = str_replace('.', '', $_POST['jumlah']);
    $tgl_jatuh_tempo = $_POST['tgl_jatuh_tempo'];
    $tgl_terbit = date('Y-m-d H:i:s');
    
    // Ambil nama pelanggan untuk log
    $stmt_cust = $pdo->prepare("SELECT id_pelanggan, nama_lengkap FROM pelanggan WHERE id = ?");
    $stmt_cust->execute([$pelanggan_id]);
    $customer = $stmt_cust->fetch();
    $customer_name = $customer['nama_lengkap'] ?? $customer['id_pelanggan'] ?? 'Unknown';
    
    // Buat nomor invoice unik
    do {
        $no_invoice = 'INV/' . date('Ym') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $check = $pdo->prepare("SELECT id FROM invoice WHERE no_invoice = ?");
        $check->execute([$no_invoice]);
        $exists = $check->fetch();
    } while ($exists);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO invoice (no_invoice, pelanggan_id, jumlah, tgl_terbit, tgl_jatuh_tempo, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$no_invoice, $pelanggan_id, $jumlah, $tgl_terbit, $tgl_jatuh_tempo]);
        
        // Catat log aktivitas
        include '../../includes/activity_log.php';
        log_admin_activity($pdo, 'INVOICE_CREATE', 'invoice', $no_invoice, "Membuat invoice $no_invoice untuk pelanggan: $customer_name (ID: $pelanggan_id) dengan jumlah Rp " . number_format($jumlah, 0, ',', '.'));
        
        header("Location: index.php?success=1");
        exit();
    } catch(PDOException $e) {
        $error = "Gagal membuat tagihan: " . $e->getMessage();
    }
}

include '../../includes/header.php';

require_once '../../config/database.php';
$pdo = Database::getInstance()->getConnection();

// Ambil SEMUA pelanggan (active + inactive) untuk dropdown
$stmt = $pdo->query("
    SELECT p.id, p.id_pelanggan, p.nama_lengkap, p.jatuh_tempo, p.status, pk.harga 
    FROM pelanggan p
    LEFT JOIN paket_internet pk ON p.paket_id = pk.id
    WHERE p.status IN ('active', 'inactive')
    ORDER BY p.status DESC, p.nama_lengkap
");
$customers = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-plus"></i> Buat Tagihan Baru
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
                    <select name="pelanggan_id" id="pelanggan_id" class="form-control" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        <?php foreach ($customers as $cust): ?>
                            <option  value="<?= htmlspecialchars($cust['id']) ?>" 
                                    data-harga="<?= $cust['harga'] ?>"
                                    data-jatuh_tempo="<?= date('Y-m-d', strtotime($cust['jatuh_tempo'])) ?>">
                                <?= htmlspecialchars($cust['nama_lengkap']) ?> (<?= $cust['id_pelanggan'] ?>)
                                <?php if ($cust['status'] == 'inactive'): ?>
                                    - <span class="text-danger">ISOLIR</span>
                                <?php endif; ?>
                                <?php if ($cust['harga']): ?> - Rp <?= number_format($cust['harga'], 0, ',', '.') ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Pelanggan dengan status ISOLIR tetap bisa dibuatkan invoice</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Jumlah Tagihan <span class="text-danger">*</span></label>
                    <input type="text" name="jumlah" id="jumlah" class="form-control" required onkeyup="formatRupiah(this)">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
                    <input type="date" name="tgl_jatuh_tempo" id="tgl_jatuh_tempo" class="form-control" required>
                    <small class="text-muted">Akan otomatis mengikuti jatuh tempo pelanggan yang dipilih</small>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Buat Tagihan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#pelanggan_id').change(function() {
        var harga = $(this).find(':selected').data('harga');
        var jatuhTempo = $(this).find(':selected').data('jatuh_tempo');
        
        if (harga && harga > 0) {
            $('#jumlah').val(formatRupiahNumber(harga));
        } else {
            $('#jumlah').val('');
        }
        
        if (jatuhTempo) {
            $('#tgl_jatuh_tempo').val(jatuhTempo);
        } else {
            $('#tgl_jatuh_tempo').val('');
        }
    });
    
    function formatRupiahNumber(angka) {
        return new Intl.NumberFormat('id-ID').format(angka);
    }
});

function formatRupiah(element) {
    let value = element.value.replace(/[^,\d]/g, '');
    value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    element.value = value;
}
</script>

<?php include '../../includes/footer.php'; ?>
