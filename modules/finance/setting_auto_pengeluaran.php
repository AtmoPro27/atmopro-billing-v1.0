<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    // Proses tambah/edit/hapus
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'add') {
            $jenis = $_POST['jenis'];
            $nama = $_POST['nama'];
            $jumlah = str_replace(['.', ','], '', $_POST['jumlah']);
            $keterangan = $_POST['keterangan'];
            $aktif = 1; // LANGSUNG AKTIF!
            
            $stmt = $pdo->prepare("INSERT INTO pengeluaran_otomatis (jenis, nama, jumlah, keterangan, aktif) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$jenis, $nama, $jumlah, $keterangan, $aktif]);
            
            echo '<div class="alert alert-success">Pengeluaran otomatis berhasil ditambahkan (LANGSUNG AKTIF)!</div>';
        } elseif ($action == 'edit') {
            $id = $_POST['id'];
            $jenis = $_POST['jenis'];
            $nama = $_POST['nama'];
            $jumlah = str_replace(['.', ','], '', $_POST['jumlah']);
            $keterangan = $_POST['keterangan'];
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE pengeluaran_otomatis SET jenis = ?, nama = ?, jumlah = ?, keterangan = ?, aktif = ? WHERE id = ?");
            $stmt->execute([$jenis, $nama, $jumlah, $keterangan, $aktif, $id]);
            
            echo '<div class="alert alert-success">Pengeluaran otomatis berhasil diupdate!</div>';
        } elseif ($action == 'delete') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM pengeluaran_otomatis WHERE id = ?");
            $stmt->execute([$id]);
            
            echo '<div class="alert alert-success">Pengeluaran otomatis berhasil dihapus!</div>';
        }
    }
    
    // Ambil data
    $items = $pdo->query("SELECT * FROM pengeluaran_otomatis ORDER BY jenis, nama")->fetchAll();
    
    // Hitung total otomatis
    $total_auto = $pdo->query("
        SELECT COALESCE(SUM(jumlah), 0) as total 
        FROM pengeluaran_otomatis 
        WHERE aktif = 1
    ")->fetch()['total'];
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-cog"></i> Setting Pengeluaran Otomatis
    </div>
    <div class="card-body">
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            Pengeluaran otomatis akan dicatat setiap bulan pada tanggal 25.
           Total otomatis bulan ini: <strong>Rp <?= number_format($total_auto, 0, ',', '.') ?></strong>
        </div>
        
        <!-- Form Tambah -->
        <div class="card mb-3">
            <div class="card-header bg-success text-white">Tambah Pengeluaran Otomatis</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-3">
                            <label>Jenis</label>
                            <select name="jenis" class="form-select" required>
                                <option value="rutin">Rutin</option>
                                <option value="bagi_hasil">Bagi Hasil</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Nama</label>
                            <input type="text" name="nama" class="form-control" placeholder="Contoh: Listrik" required>
                        </div>
                        <div class="col-md-2">
                            <label>Jumlah (Rp)</label>
                            <input type="text" name="jumlah" class="form-control" placeholder="150000" required>
                        </div>
                        <div class="col-md-3">
                            <label>Keterangan</label>
                            <input type="text" name="keterangan" class="form-control" placeholder="Bayar listrik">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="mt-2 text-success">
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Daftar -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Jenis</th>
                        <th>Nama</th>
                        <th>Jumlah</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="text-center">Belum ada data</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><span class="badge <?= $item['jenis'] == 'rutin' ? 'bg-secondary' : 'bg-warning text-dark' ?>"><?= strtoupper($item['jenis']) ?></span></td>
                            <td><strong><?= htmlspecialchars($item['nama']) ?></strong></td>
                            <td>Rp <?= number_format($item['jumlah'], 0, ',', '.') ?></td>
                            <td><?= htmlspecialchars($item['keterangan']) ?></td>
                            <td>
                                <?php if ($item['aktif']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editItem(<?= $item['id'] ?>, '<?= $item['jenis'] ?>', '<?= addslashes($item['nama']) ?>', <?= $item['jumlah'] ?>, '<?= addslashes($item['keterangan']) ?>', <?= $item['aktif'] ?>)"><i class="fas fa-edit"></i></button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin?')"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Form Edit (hidden) -->
        <div id="editForm" style="display:none;" class="card mt-3">
            <div class="card-header bg-warning text-dark">Edit Pengeluaran Otomatis</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row">
                        <div class="col-md-3">
                            <label>Jenis</label>
                            <select name="jenis" id="edit_jenis" class="form-select">
                                <option value="rutin">Rutin</option>
                                <option value="bagi_hasil">Bagi Hasil</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Nama</label>
                            <input type="text" name="nama" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label>Jumlah (Rp)</label>
                            <input type="text" name="jumlah" id="edit_jumlah" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label>Keterangan</label>
                            <input type="text" name="keterangan" id="edit_keterangan" class="form-control">
                        </div>
                        <div class="col-md-1">
                            <label>Aktif</label>
                            <input type="checkbox" name="aktif" id="edit_aktif" value="1" checked>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('editForm').style.display='none'">Batal</button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>

<script>
function editItem(id, jenis, nama, jumlah, keterangan, aktif) {
    document.getElementById('editForm').style.display = 'block';
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_jenis').value = jenis;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_jumlah').value = jumlah;
    document.getElementById('edit_keterangan').value = keterangan;
    document.getElementById('edit_aktif').checked = aktif == 1;
    document.getElementById('editForm').scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php include '../../includes/footer.php'; ?>
