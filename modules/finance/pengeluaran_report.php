<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    // Filter
    $filter_jenis = $_GET['jenis'] ?? 'all';
    $filter_bulan = $_GET['bulan'] ?? date('Y-m');
    $filter_tahun = $_GET['tahun'] ?? date('Y');
    $periode = $_GET['periode'] ?? 'bulanan';
    
    // Query
    $sql = "SELECT id, tanggal, jenis, investor_id, keterangan, jumlah FROM pengeluaran WHERE 1=1";
    $params = [];
    
    if ($filter_jenis != 'all') {
        $sql .= " AND jenis = ?";
        $params[] = $filter_jenis;
    }
    
    if ($periode == 'bulanan' && isset($_GET['bulan']) && $_GET['bulan'] != '') {
        $sql .= " AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
        $params[] = date('m', strtotime($filter_bulan . '-01'));
        $params[] = date('Y', strtotime($filter_bulan . '-01'));
    } elseif ($periode == 'tahunan' && isset($_GET['tahun']) && $_GET['tahun'] != '') {
        $sql .= " AND YEAR(tanggal) = ?";
        $params[] = $filter_tahun;
    }
    
    $sql .= " ORDER BY tanggal DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pengeluaran = $stmt->fetchAll();
    
    // Total
    $total = array_sum(array_column($pengeluaran, 'jumlah'));
    
    // Jenis label
    $jenis_label = [
        'rutin' => 'Rutin',
        'psb' => 'PSB',
        'maintenance' => 'Maintenance',
        'bagi_hasil' => 'Bagi Hasil'
    ];
    
    $jenis_badge = [
        'rutin' => 'bg-secondary',
        'psb' => 'bg-info',
        'maintenance' => 'bg-danger',
        'bagi_hasil' => 'bg-warning text-dark'
    ];
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-file-alt"></i> Laporan Pengeluaran
        <div class="float-end">
            <a href="add_pengeluaran.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Tambah</a>
            <a href="pengeluaran.php" class="btn btn-secondary btn-sm">Kembali</a>
        </div>
    </div>
    <div class="card-body">
        
        <!-- Filter -->
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Jenis</label>
                <select name="jenis" class="form-select">
                    <option value="all">Semua</option>
                    <option value="rutin" <?= $filter_jenis == 'rutin' ? 'selected' : '' ?>>Rutin</option>
                    <option value="maintenance" <?= $filter_jenis == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    <option value="bagi_hasil" <?= $filter_jenis == 'bagi_hasil' ? 'selected' : '' ?>>Bagi Hasil</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Periode</label>
                <select name="periode" class="form-select" id="periodeSelect">
                    <option value="bulanan" <?= $periode == 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
                    <option value="tahunan" <?= $periode == 'tahunan' ? 'selected' : '' ?>>Tahunan</option>
                </select>
            </div>
            <div class="col-md-3" id="bulanDiv" <?= $periode == 'tahunan' ? 'style="display:none;"' : '' ?>>
                <label class="form-label">Bulan</label>
                <input type="month" name="bulan" class="form-control" value="<?= $filter_bulan ?>">
            </div>
            <div class="col-md-3" id="tahunDiv" <?= $periode == 'bulanan' ? 'style="display:none;"' : '' ?>>
                <label class="form-label">Tahun</label>
                <select name="tahun" class="form-select">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= $filter_tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="pengeluaran_report.php" class="btn btn-secondary ms-2">Reset</a>
            </div>
        </form>
        
        <script>
        document.getElementById('periodeSelect').addEventListener('change', function() {
            if (this.value === 'bulanan') {
                document.getElementById('bulanDiv').style.display = 'block';
                document.getElementById('tahunDiv').style.display = 'none';
            } else {
                document.getElementById('bulanDiv').style.display = 'none';
                document.getElementById('tahunDiv').style.display = 'block';
            }
        });
        </script>
        
        <!-- Total -->
        <div class="alert alert-info">
            <strong>Total Pengeluaran:</strong> Rp <?= number_format($total, 0, ',', '.') ?>
            <span class="ms-3"><strong>Jumlah Data:</strong> <?= count($pengeluaran) ?></span>
        </div>
        
        <!-- Tabel -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="dataTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th>Jumlah</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pengeluaran)): ?>
                        <tr><td colspan="6" class="text-center text-muted">Tidak ada data</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($pengeluaran as $row): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                            <td><span class="badge <?= $jenis_badge[$row['jenis']] ?? 'bg-secondary' ?>"><?= $jenis_label[$row['jenis']] ?? $row['jenis'] ?></span></td>
                            <td><?= htmlspecialchars($row['keterangan']) ?></td>
                            <td>Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                            <td>
                                <a href="edit_pengeluaran.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="delete_pengeluaran.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
