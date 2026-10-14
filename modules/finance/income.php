<?php
require_once __DIR__ . "/../../includes/session_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Proses tambah income manual
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_manual') {
        $keterangan = trim($_POST['keterangan']);
        $jumlah = str_replace('.', '', $_POST['jumlah']);
        $kategori = $_POST['kategori'];
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $catatan = trim($_POST['catatan'] ?? '');
        $user_id = $_SESSION['user_id'];
        
        if (empty($keterangan) || empty($jumlah) || $jumlah <= 0) {
            $message = 'Semua field wajib diisi dengan benar!';
            $message_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO income_manual (keterangan, jumlah, kategori, tanggal, catatan, user_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$keterangan, $jumlah, $kategori, $tanggal, $catatan, $user_id]);
                
                $message = 'Income manual berhasil ditambahkan!';
                $message_type = 'success';
            } catch(PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
    
    if ($_POST['action'] == 'edit_manual') {
        $id = $_POST['id'];
        $keterangan = trim($_POST['keterangan']);
        $jumlah = str_replace('.', '', $_POST['jumlah']);
        $kategori = $_POST['kategori'];
        $tanggal = $_POST['tanggal'];
        $catatan = trim($_POST['catatan'] ?? '');
        
        if (empty($keterangan) || empty($jumlah) || $jumlah <= 0) {
            $message = 'Semua field wajib diisi dengan benar!';
            $message_type = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE income_manual 
                    SET keterangan = ?, jumlah = ?, kategori = ?, tanggal = ?, catatan = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$keterangan, $jumlah, $kategori, $tanggal, $catatan, $id]);
                
                $message = 'Income manual berhasil diupdate!';
                $message_type = 'success';
            } catch(PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
    
    if ($_POST['action'] == 'delete_manual') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM income_manual WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Data berhasil dihapus!';
            $message_type = 'success';
        } catch(PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Income dari invoice (tagihan WiFi)
$stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND DATE(tgl_bayar) = CURDATE()");
$income_today = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND MONTH(tgl_bayar) = MONTH(CURDATE()) AND YEAR(tgl_bayar) = YEAR(CURDATE())");
$income_month = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND YEAR(tgl_bayar) = YEAR(CURDATE())");
$income_year = $stmt->fetch()['total'];

// Income manual
$stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual WHERE DATE(tanggal) = CURDATE()");
$manual_today = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
$manual_month = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual WHERE YEAR(tanggal) = YEAR(CURDATE())");
$manual_year = $stmt->fetch()['total'];

// Total income manual
$stmt_total = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual");
$total_manual = $stmt_total->fetch()['total'];

// Total invoice
$stmt_invoice = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid'");
$total_invoice = $stmt_invoice->fetch()['total'];

// Pending dan Overdue
$stmt = $pdo->query("SELECT COUNT(*) as total, COALESCE(SUM(jumlah), 0) as total_nilai FROM invoice WHERE status = 'pending'");
$pending = $stmt->fetch();

$stmt = $pdo->query("SELECT COUNT(*) as total, COALESCE(SUM(jumlah), 0) as total_nilai FROM invoice WHERE status = 'pending' AND tgl_jatuh_tempo < NOW()");
$overdue = $stmt->fetch();

// Ambil data income manual
$stmt = $pdo->query("
    SELECT im.*, u.username 
    FROM income_manual im
    LEFT JOIN users u ON im.user_id = u.id
    ORDER BY im.tanggal DESC, im.created_at DESC
");
$income_manual = $stmt->fetchAll();

// Kategori income manual
$kategori_list = [
    'pemasangan' => 'Pemasangan Baru',
    'penjualan' => 'Penjualan Perangkat',
    'jasa' => 'Jasa Service',
    'donasi' => 'Donasi',
    'lainnya' => 'Lainnya'
];

// =============================================================
// DATA UNTUK CHART - PERBAIKAN
// =============================================================
$chart_labels = [];
$chart_invoice_data = [];
$chart_manual_data = [];

try {
    // Data Invoice
    $stmt_invoice = $pdo->query("
        SELECT 
            DATE_FORMAT(tgl_bayar, '%Y-%m') as bulan,
            COALESCE(SUM(jumlah), 0) as total
        FROM invoice 
        WHERE status = 'paid' AND tgl_bayar IS NOT NULL
        GROUP BY DATE_FORMAT(tgl_bayar, '%Y-%m')
        ORDER BY bulan ASC
        LIMIT 12
    ");
    
    $invoice_data = [];
    while ($row = $stmt_invoice->fetch()) {
        $invoice_data[$row['bulan']] = $row['total'];
    }
    
    // Data Manual
    $stmt_manual = $pdo->query("
        SELECT 
            DATE_FORMAT(tanggal, '%Y-%m') as bulan,
            COALESCE(SUM(jumlah), 0) as total
        FROM income_manual
        GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
        ORDER BY bulan ASC
        LIMIT 12
    ");
    
    $manual_data = [];
    while ($row = $stmt_manual->fetch()) {
        $manual_data[$row['bulan']] = $row['total'];
    }
    
    // Gabungkan semua bulan
    $all_months = array_unique(array_merge(array_keys($invoice_data), array_keys($manual_data)));
    sort($all_months);
    
    // Ambil 12 bulan terakhir
    $all_months = array_slice($all_months, -12);
    
    foreach ($all_months as $bulan) {
        $chart_labels[] = date('M Y', strtotime($bulan . '-01'));
        $chart_invoice_data[] = $invoice_data[$bulan] ?? 0;
        $chart_manual_data[] = $manual_data[$bulan] ?? 0;
    }
    
    // Jika tidak ada data sama sekali, tambahkan bulan ini dengan nilai 0
    if (empty($chart_labels)) {
        $chart_labels[] = date('M Y');
        $chart_invoice_data[] = 0;
        $chart_manual_data[] = 0;
    }
} catch(PDOException $e) {
    // Fallback: tampilkan bulan ini dengan nilai 0
    $chart_labels[] = date('M Y');
    $chart_invoice_data[] = 0;
    $chart_manual_data[] = 0;
}
?>

<style>
    .income-manual-table td { vertical-align: middle; }
    .badge-kategori { font-size: 11px; padding: 4px 10px; }
    .btn-edit { margin-right: 3px; }
</style>

<!-- ============================================================= -->
<!-- BARIS 1: CARD INCOME (TOTAL) -->
<!-- ============================================================= -->
<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #0d5c1e, #1a8a2d);">
            <div class="card-body">
                <i class="fas fa-calendar-day fa-2x mb-2"></i>
                <h5 class="mb-0">Rp <?= number_format($income_today + $manual_today, 0, ',', '.') ?></h5>
                <small>Income Hari Ini</small>
                <hr class="my-1" style="border-color: rgba(255,255,255,0.2);">
                <small class="text-light">Invoice: Rp <?= number_format($income_today, 0, ',', '.') ?> | Manual: Rp <?= number_format($manual_today, 0, ',', '.') ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #1a2980, #26d0ce);">
            <div class="card-body">
                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                <h5 class="mb-0">Rp <?= number_format($income_month + $manual_month, 0, ',', '.') ?></h5>
                <small>Income Bulan Ini</small>
                <hr class="my-1" style="border-color: rgba(255,255,255,0.2);">
                <small class="text-light">Invoice: Rp <?= number_format($income_month, 0, ',', '.') ?> | Manual: Rp <?= number_format($manual_month, 0, ',', '.') ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #e65c00, #f9a826);">
            <div class="card-body">
                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                <h5 class="mb-0">Rp <?= number_format($income_year + $manual_year, 0, ',', '.') ?></h5>
                <small>Income Tahun Ini</small>
                <hr class="my-1" style="border-color: rgba(255,255,255,0.2);">
                <small class="text-light">Invoice: Rp <?= number_format($income_year, 0, ',', '.') ?> | Manual: Rp <?= number_format($manual_year, 0, ',', '.') ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #7c0088, #b12bc9);">
            <div class="card-body">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h5 class="mb-0"><?= $pending['total'] ?></h5>
                <small>Tagihan Pending</small>
                <hr class="my-1" style="border-color: rgba(255,255,255,0.2);">
                <small class="text-light">Rp <?= number_format($pending['total_nilai'], 0, ',', '.') ?></small>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- BARIS 2: MESSAGE -->
<!-- ============================================================= -->
<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ============================================================= -->
<!-- BARIS 3: GRAFIK -->
<!-- ============================================================= -->
<div class="row mt-2">
    <div class="col-md-12 mb-3">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i> Grafik Pendapatan Bulanan
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- BARIS 4: INCOME MANUAL + RINGKASAN -->
<!-- ============================================================= -->
<div class="row mt-2">
    <div class="col-md-7 mb-3">
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-hand-holding-usd"></i> Income Manual (Non-Tagihan)</span>
                <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus"></i> Tambah
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm mb-0 income-manual-table" id="manualTable">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Keterangan</th>
                                <th>Kategori</th>
                                <th class="text-end">Jumlah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($income_manual) > 0): ?>
                                <?php foreach ($income_manual as $data): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($data['tanggal'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($data['keterangan']) ?>
                                        <?php if (!empty($data['catatan'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($data['catatan']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info badge-kategori">
                                            <?= $kategori_list[$data['kategori']] ?? $data['kategori'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><strong>Rp <?= number_format($data['jumlah'], 0, ',', '.') ?></strong></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-warning btn-edit" onclick="editData(<?= $data['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger" onclick="hapusData(<?= $data['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Belum ada income manual</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <td colspan="3" class="text-end"><strong>TOTAL MANUAL</strong></td>
                                <td class="text-end"><strong>Rp <?= number_format($total_manual, 0, ',', '.') ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-5 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-chart-pie"></i> Ringkasan Pendapatan
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <strong>💰 Total Income Invoice WiFi</strong><br>
                    Rp <?= number_format($total_invoice, 0, ',', '.') ?>
                </div>
                <div class="alert alert-info">
                    <strong>💰 Total Income Manual</strong><br>
                    Rp <?= number_format($total_manual, 0, ',', '.') ?>
                </div>
                <div class="alert alert-primary">
                    <strong>💰 TOTAL KESELURUHAN</strong><br>
                    <h4 class="mb-0">Rp <?= number_format($total_invoice + $total_manual, 0, ',', '.') ?></h4>
                </div>
                <hr>
                <div class="alert alert-warning">
                    <strong>⏳ Tagihan Pending:</strong> <?= $pending['total'] ?> tagihan<br>
                    <small>Rp <?= number_format($pending['total_nilai'], 0, ',', '.') ?></small>
                </div>
                <div class="alert alert-danger">
                    <strong>⚠️ Overdue:</strong> <?= $overdue['total'] ?> tagihan<br>
                    <small>Rp <?= number_format($overdue['total_nilai'], 0, ',', '.') ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- BARIS 5: LAPORAN PER BULAN -->
<!-- ============================================================= -->
<div class="row mt-2">
    <div class="col-md-12 mb-3">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-table"></i> Laporan Pendapatan Per Bulan
                <div class="float-end">
                    <form method="GET" action="export_income.php" style="display:inline-block; margin:0;">
                        <input type="hidden" name="mode" value="harian">
                        <input type="hidden" name="tanggal" value="<?= date("Y-m-d") ?>">
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Export Harian</button>
                    </form>
                    <form method="GET" action="export_income.php" style="display:inline-block; margin:0;">
                        <input type="hidden" name="mode" value="bulanan">
                        <input type="hidden" name="bulan" value="<?= date("m") ?>">
                        <input type="hidden" name="tahun" value="<?= date("Y") ?>">
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Export Bulanan</button>
                    </form>
                    <form method="GET" action="export_income.php" style="display:inline-block; margin:0;">
                        <input type="hidden" name="mode" value="tahunan">
                        <input type="hidden" name="tahun" value="<?= date("Y") ?>">
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Export Tahunan</button>
                    </form>
                    <span class="mx-2">|</span>
                    <a href="report_daily.php" class="btn btn-outline-primary btn-sm">Laporan Harian</a>
                    <a href="report_monthly.php" class="btn btn-outline-success btn-sm">Laporan Bulanan</a>
                    <a href="report_yearly.php" class="btn btn-outline-info btn-sm">Laporan Tahunan</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Bulan</th>
                                <th>Invoice WiFi</th>
                                <th>Income Manual</th>
                                <th>Total Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $stmt = $pdo->query("
                                    SELECT 
                                        DATE_FORMAT(tgl_bayar, '%Y-%m') as bulan,
                                        'invoice' as sumber,
                                        COUNT(*) as jumlah,
                                        COALESCE(SUM(jumlah), 0) as total
                                    FROM invoice 
                                    WHERE status = 'paid' AND tgl_bayar IS NOT NULL
                                    GROUP BY DATE_FORMAT(tgl_bayar, '%Y-%m')
                                    UNION ALL
                                    SELECT 
                                        DATE_FORMAT(tanggal, '%Y-%m') as bulan,
                                        'manual' as sumber,
                                        0 as jumlah,
                                        COALESCE(SUM(jumlah), 0) as total
                                    FROM income_manual
                                    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
                                    ORDER BY bulan DESC
                                    LIMIT 12
                                ");
                                
                                $bulan_data = [];
                                while ($row = $stmt->fetch()) {
                                    $bulan = $row['bulan'];
                                    if (!isset($bulan_data[$bulan])) {
                                        $bulan_data[$bulan] = ['invoice' => 0, 'manual' => 0];
                                    }
                                    if ($row['sumber'] == 'invoice') {
                                        $bulan_data[$bulan]['invoice'] = $row['total'];
                                    } else {
                                        $bulan_data[$bulan]['manual'] = $row['total'];
                                    }
                                }
                                
                                if (count($bulan_data) > 0) {
                                    foreach ($bulan_data as $bulan => $data) {
                                        $bulan_nama = date('M Y', strtotime($bulan . '-01'));
                                        $total = $data['invoice'] + $data['manual'];
                                        echo '<tr>';
                                        echo '<td><strong>' . $bulan_nama . '</strong></td>';
                                        echo '<td>Rp ' . number_format($data['invoice'], 0, ',', '.') . '</td>';
                                        echo '<td>Rp ' . number_format($data['manual'], 0, ',', '.') . '</td>';
                                        echo '<td><strong>Rp ' . number_format($total, 0, ',', '.') . '</strong></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="4" class="text-center text-muted">Belum ada data pendapatan</td></tr>';
                                }
                            } catch(PDOException $e) {
                                echo '<tr><td colspan="4">Error: ' . $e->getMessage() . '</td></tr>';
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <td><strong>TOTAL</strong></td>
                                <td><strong>Rp <?= number_format($total_invoice, 0, ',', '.') ?></strong></td>
                                <td><strong>Rp <?= number_format($total_manual, 0, ',', '.') ?></strong></td>
                                <td><strong>Rp <?= number_format($total_invoice + $total_manual, 0, ',', '.') ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL TAMBAH INCOME MANUAL -->
<!-- ============================================================= -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_manual">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle text-success"></i> Tambah Income Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan <span class="text-danger">*</span></label>
                        <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Pemasangan WiFi Paket Gold" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select name="kategori" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_list as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                        <input type="text" name="jumlah" class="form-control" id="jumlahInput" onkeyup="formatRupiah(this)" placeholder="Contoh: 500000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Catatan tambahan (opsional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- MODAL EDIT INCOME MANUAL -->
<!-- ============================================================= -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_manual">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit text-warning"></i> Edit Income Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" id="edit_tanggal" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan <span class="text-danger">*</span></label>
                        <input type="text" name="keterangan" id="edit_keterangan" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select name="kategori" id="edit_kategori" class="form-control" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_list as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                        <input type="text" name="jumlah" id="edit_jumlah" class="form-control" onkeyup="formatRupiah(this)" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="catatan" id="edit_catatan" class="form-control" rows="2" placeholder="Catatan tambahan (opsional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- FORM DELETE (HIDDEN) -->
<!-- ============================================================= -->
<form id="formDelete" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete_manual">
    <input type="hidden" name="id" id="deleteId">
</form>

<!-- ============================================================= -->
<!-- SCRIPTS -->
<!-- ============================================================= -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    $('#manualTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
        },
        "order": [[0, "desc"]],
        "pageLength": 25
    });
});

function formatRupiah(element) {
    let value = element.value.replace(/[^,\d]/g, '');
    value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    element.value = value;
}

function hapusData(id) {
    if (confirm('Yakin ingin menghapus data income ini?')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('formDelete').submit();
    }
}

function editData(id) {
    $.ajax({
        url: 'get_income_manual.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_tanggal').value = data.tanggal;
                document.getElementById('edit_keterangan').value = data.keterangan;
                document.getElementById('edit_kategori').value = data.kategori;
                document.getElementById('edit_jumlah').value = data.jumlah_formatted;
                document.getElementById('edit_catatan').value = data.catatan || '';
                $('#modalEdit').modal('show');
            } else {
                alert('Gagal mengambil data: ' + data.message);
            }
        },
        error: function() {
            alert('Error mengambil data');
        }
    });
}

// Chart
const ctx = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                label: 'Invoice WiFi',
                data: <?= json_encode($chart_invoice_data) ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: '#28a745',
                borderWidth: 1
            },
            {
                label: 'Income Manual',
                data: <?= json_encode($chart_manual_data) ?>,
                backgroundColor: 'rgba(249, 168, 38, 0.7)',
                borderColor: '#e65c00',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
