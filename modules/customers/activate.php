<?php
ob_start();
require_once __DIR__ . '/../../includes/auth_check.php';

$id_pelanggan = $_GET['id'] ?? '';
if (empty($id_pelanggan)) {
    header("Location: index.php");
    exit();
}

include '../../includes/header.php';
require_once '../../config/database.php';
$pdo = Database::getInstance()->getConnection();

$error = '';

// Ambil data pelanggan
$stmt = $pdo->prepare("SELECT p.*, pk.harga, pk.profil_ppp, pk.router_id, pk.nama_paket
                       FROM pelanggan p
                       LEFT JOIN paket_internet pk ON p.paket_id = pk.id
                       WHERE p.id_pelanggan = ?");
$stmt->execute([$id_pelanggan]);
$pelanggan = $stmt->fetch();

if (!$pelanggan) {
    echo "<div class='alert alert-danger'>Pelanggan tidak ditemukan!</div>";
    include '../../includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tgl_aktif = $_POST['tgl_aktif'] ?? date('Y-m-d');
    $buat_mikrotik = isset($_POST['buat_mikrotik']) ? true : false;
    
    try {
        $pdo->beginTransaction();
        
        // Update status pelanggan
        $stmt = $pdo->prepare("UPDATE pelanggan SET status_aktif = 'aktif', status = 'active' WHERE id_pelanggan = ?");
        $stmt->execute([$id_pelanggan]);
        
        $message = "Status pelanggan berhasil diaktifkan!";
        
        // Buat invoice prorata
        $tgl_aktif_date = new DateTime($tgl_aktif);
        $tahun = $tgl_aktif_date->format('Y');
        $bulan = $tgl_aktif_date->format('m');
        $jatuh_tempo = $pelanggan['jatuh_tempo'];
        $hari_jatuh_tempo = (int)date('d', strtotime($jatuh_tempo));
        $hari_aktif = (int)$tgl_aktif_date->format('d');
        
        if ($hari_aktif <= $hari_jatuh_tempo) {
            $hari_terpakai = $hari_jatuh_tempo - $hari_aktif + 1;
            $tgl_jatuh_tempo_inv = date('Y-m-d', strtotime("$tahun-$bulan-$hari_jatuh_tempo"));
        } else {
            $hari_dalam_bulan = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
            $hari_terpakai = ($hari_dalam_bulan - $hari_aktif + 1) + $hari_jatuh_tempo;
            $tgl_jatuh_tempo_inv = date('Y-m-d', strtotime("+1 month", strtotime("$tahun-$bulan-$hari_jatuh_tempo")));
        }
        
        $hari_dalam_bulan = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
        $harga_per_hari = $pelanggan['harga'] / $hari_dalam_bulan;
        $jumlah_tagihan = round($harga_per_hari * $hari_terpakai);
        
        if ($jumlah_tagihan > 0) {
            $no_invoice = 'INV/' . date('Ym') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $tgl_terbit = date('Y-m-d H:i:s');
            
            $stmt_inv = $pdo->prepare("INSERT INTO invoice (pelanggan_id, no_invoice, jumlah, status, tgl_terbit, tgl_jatuh_tempo) VALUES (?, ?, ?, 'pending', ?, ?)");
            $stmt_inv->execute([$pelanggan['id'], $no_invoice, $jumlah_tagihan, $tgl_terbit, $tgl_jatuh_tempo_inv]);
            $message .= "\nInvoice prorata: Rp " . number_format($jumlah_tagihan, 0, ',', '.') . " (Jatuh tempo: " . date('d-m-Y', strtotime($tgl_jatuh_tempo_inv)) . ")";
        }
        
        // Buat user PPPoE
        if ($buat_mikrotik && !empty($pelanggan['router_id']) && !empty($pelanggan['profil_ppp'])) {
            include_once '../../includes/mikrotik_api.php';
            $result = add_pppoe_user($pelanggan['router_id'], $id_pelanggan, '123456', $pelanggan['profil_ppp']);
            if ($result['success']) {
                $message .= "\nUser PPPoE: $id_pelanggan (Password: 123456)";
            } else {
                $message .= "\nGagal buat user PPPoE: " . ($result['message'] ?? 'Unknown');
            }
        }
        
        $pdo->commit();
        
        // Redirect dengan pesan via session
        $_SESSION['activation_message'] = $message;
        header("Location: index.php");
        exit();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-check-circle"></i> Aktivasi Pelanggan: <?= htmlspecialchars($pelanggan['nama_lengkap']) ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            ID: <?= htmlspecialchars($id_pelanggan) ?><br>
            Nama: <?= htmlspecialchars($pelanggan['nama_lengkap']) ?><br>
            Paket: <?= htmlspecialchars($pelanggan['nama_paket'] ?? '-') ?>
        </div>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Tanggal Aktif</label>
                <input type="date" name="tgl_aktif" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" name="buat_mikrotik" class="form-check-input" id="buat_mikrotik" value="1" checked>
                <label class="form-check-label" for="buat_mikrotik">
                    Buat user PPPoE di Mikrotik (Password: 123456)
                </label>
            </div>
            
            <button type="submit" class="btn btn-success">Aktifkan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
