<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        require_once __DIR__ . "/../../config/database.php";
        $pdo = Database::getInstance()->getConnection();
        
        $jenis = $_POST['jenis'];
        $keterangan = $_POST['keterangan'];
        $jumlah_barang = str_replace(['.', ','], '', $_POST['jumlah_barang']);
        $harga_satuan = str_replace(['.', ','], '', $_POST['harga_satuan']);
        $total = $jumlah_barang * $harga_satuan;
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        
        $stmt = $pdo->prepare("INSERT INTO pengeluaran (jenis, keterangan, jumlah, tanggal) VALUES (?, ?, ?, ?)");
        $stmt->execute([$jenis, $keterangan . ' (Jml: ' . $jumlah_barang . ' x Rp ' . number_format($harga_satuan, 0, ',', '.') . ')', $total, $tanggal . ' 00:00:00']);
        
        echo '<div class="alert alert-success">Pengeluaran berhasil ditambahkan! <a href="pengeluaran.php">Kembali</a></div>';
    } catch(PDOException $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<style>
    .form-group {
        margin-bottom: 15px;
    }
    .total-box {
        background: #0d5c1e;
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        font-size: 24px;
        font-weight: bold;
        margin-top: 10px;
    }
    .total-box small {
        font-size: 14px;
        font-weight: normal;
        display: block;
        opacity: 0.8;
    }
</style>

<div class="card">
    <div class="card-header bg-success text-white">
        <i class="fas fa-plus"></i> Tambah Pengeluaran
    </div>
    <div class="card-body">
        <form method="POST" onsubmit="return validateForm()">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Jenis Pengeluaran</label>
                        <select name="jenis" class="form-select" required>
                            <option value="rutin">RUTIN</option>
                            <option value="psb">PSB</option>
                            <option value="maintenance">MAINTENANCE</option>
                            <option value="bagi_hasil">BAGI HASIL</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Keterangan</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Beli kabel fiber, Beli router, dll" required>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="form-label">Jumlah Barang (Qty)</label>
                        <input type="text" name="jumlah_barang" id="jumlah_barang" class="form-control" placeholder="Contoh: 5" value="1" required oninput="hitungTotal()">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="form-label">Harga Satuan (Rp)</label>
                        <input type="text" name="harga_satuan" id="harga_satuan" class="form-control" placeholder="Contoh: 100000" required oninput="hitungTotal()">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="form-label">Total (Rp)</label>
                        <input type="text" name="total" id="total" class="form-control" readonly style="background: #e9ecef; font-weight: bold; color: #0d5c1e;">
                    </div>
                </div>
            </div>
            
            <div class="total-box" id="totalBox">
                Rp 0
                <small>Total Pengeluaran</small>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
                <a href="pengeluaran.php" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
function hitungTotal() {
    var jumlah = document.getElementById('jumlah_barang').value.replace(/[^0-9]/g, '');
    var harga = document.getElementById('harga_satuan').value.replace(/[^0-9]/g, '');
    
    var jml = parseInt(jumlah) || 0;
    var hrg = parseInt(harga) || 0;
    var total = jml * hrg;
    
    document.getElementById('total').value = total > 0 ? total.toLocaleString('id-ID') : '';
    document.getElementById('totalBox').innerHTML = 'Rp ' + (total > 0 ? total.toLocaleString('id-ID') : '0') + '<small>Total Pengeluaran</small>';
}

function validateForm() {
    var total = document.getElementById('total').value.replace(/[^0-9]/g, '');
    if (total === '' || parseInt(total) === 0) {
        alert('Total tidak boleh 0!');
        return false;
    }
    return true;
}

// Format angka otomatis
document.getElementById('jumlah_barang').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});

document.getElementById('harga_satuan').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});
</script>

<?php include '../../includes/footer.php'; ?>
