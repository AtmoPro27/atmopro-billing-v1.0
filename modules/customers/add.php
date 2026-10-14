<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . "/../../includes/session_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';
require_once '../../config/database.php';
include '../../includes/mikrotik_api.php';

$pdo = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Ambil data paket
try {
    $stmt = $pdo->query("SELECT id, nama_paket, harga, profil_ppp, router_id FROM paket_internet WHERE is_active = 1 ORDER BY harga ASC");
    $packages = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, nama_router, ip_address FROM routers WHERE status = 'active'");
    $routers = $stmt->fetchAll();
} catch(PDOException $e) {
    $packages = [];
    $routers = [];
    $error_db = $e->getMessage();
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_pelanggan = trim($_POST['id_pelanggan'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $paket_id = $_POST['paket_id'] ?: null;
    $status_aktif = $_POST['status_aktif'] ?? 'on_progress';
    $status = ($status_aktif == 'on_progress') ? 'inactive' : 'active';
    $jatuh_tempo = $_POST['jatuh_tempo'] ?: date('Y-m-d', strtotime('+30 days'));
    $tgl_aktif = $_POST['tgl_aktif'] ?? date('Y-m-d');
    $prorata = isset($_POST['prorata']) ? 1 : 0;
    
    // Ambil data paket
    $paket_data = null;
    $harga_paket = 0;
    $profil_ppp = '';
    $router_id = null;
    
    foreach ($packages as $pkg) {
        if ($pkg['id'] == $paket_id) {
            $harga_paket = $pkg['harga'];
            $profil_ppp = $pkg['profil_ppp'];
            $router_id = $pkg['router_id'];
            $paket_data = $pkg;
            break;
        }
    }
    
    try {
        // Cek duplikasi ID
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pelanggan WHERE id_pelanggan = ?");
        $stmt->execute([$id_pelanggan]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("ID Pelanggan '$id_pelanggan' sudah terdaftar!");
        }
        
        $pdo->beginTransaction();
        
        // Insert pelanggan
        $default_password = "123456";
        $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO pelanggan (id_pelanggan, nama_lengkap, alamat, no_telepon, paket_id, jatuh_tempo, status, status_aktif, password, tgl_aktif, is_prorata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id_pelanggan, 
            $nama_lengkap, 
            $alamat, 
            $no_telepon, 
            $paket_id, 
            $jatuh_tempo, 
            $status, 
            $status_aktif, 
            $hashed_password,
            $tgl_aktif,
            $prorata
        ]);
        
        $pelanggan_id = $pdo->lastInsertId();
        
        // Inisialisasi traffic
        $stmt_traffic = $pdo->prepare("INSERT INTO pelanggan_traffic (pelanggan_id, total_bytes, bulan) VALUES (?, 0, DATE_FORMAT(NOW(), '%Y-%m')) ON DUPLICATE KEY UPDATE total_bytes = total_bytes");
        $stmt_traffic->execute([$pelanggan_id]);
        
        // Buat user PPPoE di Mikrotik (hanya jika status aktif)
        $mikrotik_result = ['success' => true, 'message' => ''];
        if ($status_aktif == 'aktif' && $router_id && $profil_ppp) {
            $password = '123456';
            $mikrotik_result = add_pppoe_user($router_id, $id_pelanggan, $password, $profil_ppp);
        }
        
        // =========================================================
        // PERHITUNGAN PRORATA (hanya jika status aktif)
        // =========================================================
        $jumlah_tagihan = 0;
        $tgl_jatuh_tempo_inv = date('Y-m-d 23:59:59', strtotime($jatuh_tempo));
        $tgl_terbit = date('Y-m-d H:i:s');
        
        if ($status_aktif == 'aktif' && $harga_paket > 0) {
            $tgl_aktif_date = new DateTime($tgl_aktif);
            $tahun = $tgl_aktif_date->format('Y');
            $bulan = $tgl_aktif_date->format('m');
            $hari_aktif = (int)$tgl_aktif_date->format('d');
            
            $jatuh_tempo_date = new DateTime($jatuh_tempo);
            $hari_jatuh_tempo = (int)$jatuh_tempo_date->format('d');
            
            if ($prorata) {
                $hari_dalam_bulan = 30;
                if ($hari_aktif <= $hari_jatuh_tempo) {
                    $hari_terpakai = $hari_jatuh_tempo - $hari_aktif + 1;
                    $tgl_jatuh_tempo_inv = date('Y-m-d 23:59:59', strtotime("$tahun-$bulan-$hari_jatuh_tempo"));
                } else {
                    $hari_terpakai = ($hari_dalam_bulan - $hari_aktif + 1) + $hari_jatuh_tempo;
                    $tgl_jatuh_tempo_inv = date('Y-m-d 23:59:59', strtotime("+1 month", strtotime("$tahun-$bulan-$hari_jatuh_tempo")));
                }
                $harga_per_hari = $harga_paket / $hari_dalam_bulan;
                $jumlah_tagihan = round($harga_per_hari * $hari_terpakai);
            } else {
                $jumlah_tagihan = $harga_paket;
                $hari_terpakai = 30;
            }
        }
        
        // Buat invoice jika status aktif dan jumlah > 0
        if ($status_aktif == 'aktif' && $jumlah_tagihan > 0) {
            do {
                $no_invoice = 'INV/' . date('Ym') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $check = $pdo->prepare("SELECT id FROM invoice WHERE no_invoice = ?");
                $check->execute([$no_invoice]);
                $exists = $check->fetch();
            } while ($exists);
            
            $stmt_inv = $pdo->prepare("INSERT INTO invoice (pelanggan_id, no_invoice, jumlah, status, tgl_terbit, tgl_jatuh_tempo) VALUES (?, ?, ?, 'pending', ?, ?)");
            $stmt_inv->execute([$pelanggan_id, $no_invoice, $jumlah_tagihan, $tgl_terbit, $tgl_jatuh_tempo_inv]);
        }
        
        $pdo->commit();
        
        // =========================================================
        // REDIRECT DENGAN PESAN SUKSES
        // =========================================================
        $_SESSION['success_message'] = "✅ Pelanggan $id_pelanggan - $nama_lengkap berhasil ditambahkan!";
        header("Location: index.php?success=1");
        exit();
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "❌ Gagal menambah data: " . $e->getMessage();
    }
}
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
#prorataInfo {
    background: #f8f9fa;
    border-left: 4px solid #0d5c1e;
    padding: 15px;
    border-radius: 5px;
    margin-top: 10px;
}
</style>

<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus"></i> Tambah Pelanggan Baru
                <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if (isset($error_db)): ?>
                    <div class="alert alert-danger">Database Error: <?= htmlspecialchars($error_db) ?></div>
                <?php endif; ?>

                <form method="POST" id="formPelanggan">
                    <div class="row">
                        <!-- ID Pelanggan -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ID Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" name="id_pelanggan" class="form-control" required placeholder="Contoh: 112233">
                            <small class="text-muted">Password default: 123456</small>
                        </div>

                        <!-- Nama Lengkap -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama_lengkap" class="form-control" required>
                        </div>

                        <!-- Alamat -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="2"></textarea>
                        </div>

                        <!-- No Telepon -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">No Telepon</label>
                            <input type="text" name="no_telepon" class="form-control">
                        </div>

                        <!-- Paket -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Paket Internet <span class="text-danger">*</span></label>
                            <select name="paket_id" id="paket_id" class="form-control" required>
                                <option value="">-- Pilih Paket --</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>" 
                                            data-harga="<?= $pkg['harga'] ?>"
                                            data-profil="<?= $pkg['profil_ppp'] ?>"
                                            data-router="<?= $pkg['router_id'] ?>">
                                        <?= htmlspecialchars($pkg['nama_paket']) ?> - Rp <?= number_format($pkg['harga'], 0, ',', '.') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status Layanan -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Layanan <span class="text-danger">*</span></label>
                            <select name="status_aktif" id="status_aktif" class="form-control" required>
                                <option value="on_progress">⏳ On Progress (Belum Aktif)</option>
                                <option value="aktif">✅ Aktif Sekarang</option>
                            </select>
                            <small class="text-muted">Pilih "On Progress" jika pemasangan belum selesai</small>
                        </div>

                        <!-- Jatuh Tempo -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jatuh Tempo <span class="text-danger">*</span></label>
                            <input type="date" name="jatuh_tempo" id="jatuh_tempo" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            <small class="text-muted">Tanggal jatuh tempo tagihan setiap bulan</small>
                        </div>

                        <!-- Prorata (hanya muncul jika status aktif) -->
                        <div class="col-md-12 mb-3" id="prorataContainer" style="display:none;">
                            <div class="form-check">
                                <input type="checkbox" name="prorata" id="prorata" class="form-check-input" value="1">
                                <label class="form-check-label" for="prorata">
                                    <strong>💰 Prorata</strong> - Hitung tagihan proporsional dari tanggal aktif
                                </label>
                            </div>
                            <small class="text-muted">Jika tidak dicentang, tagihan full 1 bulan penuh</small>
                        </div>

                        <!-- Tanggal Aktif (hanya muncul jika prorata aktif) -->
                        <div class="col-md-6 mb-3" id="prorataFields" style="display:none;">
                            <label class="form-label">Tanggal Aktif <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_aktif" id="tgl_aktif" class="form-control" value="<?= date('Y-m-d') ?>">
                            <small class="text-muted">Tanggal mulai aktif layanan</small>
                        </div>

                        <!-- Info Prorata -->
                        <div class="col-md-6 mb-3" id="prorataInfo" style="display:none;">
                            <div id="prorataDetail">
                                <strong>💡 Informasi Prorata:</strong><br>
                                <span id="prorataText">-</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('#status_aktif').change(function() {
        if ($(this).val() === 'aktif') {
            $('#prorataContainer').show();
            $('#prorata').prop('checked', false);
            $('#prorataFields').hide();
            $('#prorataInfo').hide();
        } else {
            $('#prorataContainer').hide();
            $('#prorataFields').hide();
            $('#prorataInfo').hide();
            $('#prorata').prop('checked', false);
        }
    });
    
    $('#prorata').change(function() {
        if ($(this).is(':checked')) {
            $('#prorataFields').show();
            $('#prorataInfo').show();
            hitungProrata();
        } else {
            $('#prorataFields').hide();
            $('#prorataInfo').hide();
        }
    });
    
    $('#tgl_aktif, #paket_id, #jatuh_tempo').change(function() {
        if ($('#prorata').is(':checked')) {
            hitungProrata();
        }
    });
    
    function hitungProrata() {
        var tglAktif = $('#tgl_aktif').val();
        var paketId = $('#paket_id').val();
        var hargaPaket = $('#paket_id option:selected').data('harga');
        var jatuhTempo = $('#jatuh_tempo').val();
        
        if (tglAktif && paketId && hargaPaket && jatuhTempo) {
            var tanggal = new Date(tglAktif);
            var tahun = tanggal.getFullYear();
            var bulan = tanggal.getMonth() + 1;
            var hariMulai = tanggal.getDate();
            
            var jatuhTempoDate = new Date(jatuhTempo);
            var hariJatuhTempo = jatuhTempoDate.getDate();
            
            var hariTerpakai = 0;
            var tglJatuhTempoInv = '';
            
            if (hariMulai <= hariJatuhTempo) {
                hariTerpakai = hariJatuhTempo - hariMulai + 1;
                tglJatuhTempoInv = tahun + '-' + String(bulan).padStart(2, '0') + '-' + String(hariJatuhTempo).padStart(2, '0');
            } else {
                var hariBulanIni = new Date(tahun, bulan, 0).getDate();
                hariTerpakai = (hariBulanIni - hariMulai + 1) + hariJatuhTempo;
                var nextMonth = new Date(tahun, bulan, 1);
                tglJatuhTempoInv = nextMonth.getFullYear() + '-' + String(nextMonth.getMonth() + 1).padStart(2, '0') + '-' + String(hariJatuhTempo).padStart(2, '0');
            }
            
            var hariDalamBulan = 30;
            var hargaPerHari = hargaPaket / hariDalamBulan;
            var hargaProrata = Math.round(hargaPerHari * hariTerpakai);
            var hargaFull = hargaPaket;
            
            var tglAktifFormatted = tglAktif.split('-').reverse().join('-');
            var tglJatuhFormatted = tglJatuhTempoInv.split('-').reverse().join('-');
            
            $('#prorataText').html(`
                <div style="margin-top:5px;">
                    <table style="width:100%; font-size:14px;">
                        <tr><td>📦 Paket</td><td>: Rp ${formatRupiah(hargaPaket)} / bulan</td></tr>
                        <tr><td>📅 Tanggal Aktif</td><td>: ${tglAktifFormatted}</td></tr>
                        <tr><td>📅 Jatuh Tempo</td><td>: Tanggal ${hariJatuhTempo} setiap bulan</td></tr>
                        <tr><td>📊 Hari Terpakai</td><td>: ${hariTerpakai} hari</td></tr>
                        <tr style="border-top:1px solid #dee2e6; padding-top:5px;">
                            <td><strong>💰 Tanpa Prorata</strong></td>
                            <td><strong>: Rp ${formatRupiah(hargaFull)}</strong> (full 1 bulan)</td>
                        </tr>
                        <tr>
                            <td><strong>💰 Dengan Prorata</strong></td>
                            <td><strong style="color:#0d5c1e;">: Rp ${formatRupiah(hargaProrata)}</strong></td>
                        </tr>
                        <tr>
                            <td>📅 Jatuh Tempo Invoice</td>
                            <td>: ${tglJatuhFormatted}</td>
                        </tr>
                    </table>
                </div>
            `);
        } else {
            $('#prorataText').html('Pilih paket, tanggal aktif, dan jatuh tempo terlebih dahulu');
        }
    }
    
    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID').format(angka);
    }
    
    if ($('#status_aktif').val() === 'aktif') {
        $('#prorataContainer').show();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
