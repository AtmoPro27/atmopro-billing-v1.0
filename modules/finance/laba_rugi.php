<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();

// ============================================================
// PROSES POST SALDO AWAL TAHUN
// ============================================================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saldo_awal'])) {
    $saldo_awal_input = str_replace('.', '', $_POST['saldo_awal']);
    $saldo_awal_input = (int)$saldo_awal_input;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES ('saldo_awal_tahun', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([$saldo_awal_input, $saldo_awal_input]);
        
        $message = '✅ Saldo awal tahun berhasil disimpan!';
        $message_type = 'success';
    } catch(PDOException $e) {
        $message = '❌ Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// =============================================================
// DATA INCOME (Pendapatan) - Gabungan Invoice + Income Manual
// =============================================================

// Income Bulan Ini (Invoice + Manual)
$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice 
    WHERE status = 'paid' AND MONTH(tgl_bayar) = MONTH(CURDATE()) AND YEAR(tgl_bayar) = YEAR(CURDATE())
");
$income_invoice_month = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual 
    WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())
");
$income_manual_month = $stmt->fetch()['total'];

$income_month = $income_invoice_month + $income_manual_month;

// Income Tahun Ini (Invoice + Manual)
$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice 
    WHERE status = 'paid' AND YEAR(tgl_bayar) = YEAR(CURDATE())
");
$income_invoice_year = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual 
    WHERE YEAR(tanggal) = YEAR(CURDATE())
");
$income_manual_year = $stmt->fetch()['total'];

$income_year = $income_invoice_year + $income_manual_year;

// =============================================================
// DATA PENGELUARAN
// =============================================================

// Total Pengeluaran Bulan Ini
$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran 
    WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())
");
$expense_month = $stmt->fetch()['total'];

// Total Pengeluaran Tahun Ini
$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran 
    WHERE YEAR(tanggal) = YEAR(CURDATE())
");
$expense_year = $stmt->fetch()['total'];

// =============================================================
// LABA RUGI
// =============================================================

$profit_month = $income_month - $expense_month;

// Laba bulan lalu
$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice
    WHERE status = 'paid' 
    AND MONTH(tgl_bayar) = MONTH(CURDATE() - INTERVAL 1 MONTH) 
    AND YEAR(tgl_bayar) = YEAR(CURDATE() - INTERVAL 1 MONTH)
");
$income_invoice_last_month = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual
    WHERE MONTH(tanggal) = MONTH(CURDATE() - INTERVAL 1 MONTH) 
    AND YEAR(tanggal) = YEAR(CURDATE() - INTERVAL 1 MONTH)
");
$income_manual_last_month = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran
    WHERE MONTH(tanggal) = MONTH(CURDATE() - INTERVAL 1 MONTH) 
    AND YEAR(tanggal) = YEAR(CURDATE() - INTERVAL 1 MONTH)
");
$expense_last_month = $stmt->fetch()['total'];

$income_last_month = $income_invoice_last_month + $income_manual_last_month;
$profit_last_month = $income_last_month - $expense_last_month;

// Hitung persentase
if ($profit_last_month > 0) {
    $persen_profit = round((($profit_month - $profit_last_month) / $profit_last_month) * 100, 1);
} else {
    $persen_profit = null;
}
$profit_year = $income_year - $expense_year;

// ============================================================
// SALDO PERUSAHAAN (SEMUA WAKTU)
// ============================================================
$stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid'");
$total_income_invoice = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual");
$total_income_manual = $stmt->fetch()['total'];

$total_income_all = $total_income_invoice + $total_income_manual;

$stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran");
$total_expense_all = $stmt->fetch()['total'];

$saldo_perusahaan = $total_income_all - $total_expense_all;

// ============================================================
// SALDO BERJALAN (SALDO AWAL + LABA KUMULATIF)
// ============================================================
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'saldo_awal_tahun'");
$saldo_awal = (int)($stmt->fetchColumn() ?: 0);

// Laba kumulatif tahun ini (Jan - bulan ini)
$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice
    WHERE status = 'paid'
    AND YEAR(tgl_bayar) = YEAR(CURDATE())
    AND MONTH(tgl_bayar) <= MONTH(CURDATE())
");
$income_kumulatif = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM income_manual
    WHERE YEAR(tanggal) = YEAR(CURDATE())
    AND MONTH(tanggal) <= MONTH(CURDATE())
");
$income_manual_kumulatif = $stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran
    WHERE YEAR(tanggal) = YEAR(CURDATE())
    AND MONTH(tanggal) <= MONTH(CURDATE())
");
$expense_kumulatif = $stmt->fetch()['total'];

$laba_kumulatif = ($income_kumulatif + $income_manual_kumulatif) - $expense_kumulatif;

$saldo_berjalan = $saldo_awal + $laba_kumulatif;

// ============================================================
// SALDO PER BULAN (RUNNING BALANCE)
// ============================================================
$saldo_per_bulan = [];
$saldo_kumulatif = $saldo_awal;

for ($i = 0; $i < 12; $i++) {
    $laba_bulan = $chart_profit[$i];
    $saldo_kumulatif += $laba_bulan;
    $saldo_per_bulan[$i] = $saldo_kumulatif;
}

$status_month = $profit_month >= 0 ? 'Laba' : 'Rugi';
$status_year = $profit_year >= 0 ? 'Laba' : 'Rugi';

// Label persentase
if ($persen_profit !== null) {
    if ($persen_profit > 0) {
        $label_persen = '<span class="text-success">↑ ' . $persen_profit . '%</span>';
    } elseif ($persen_profit < 0) {
        $label_persen = '<span class="text-danger">↓ ' . abs($persen_profit) . '%</span>';
    } else {
        $label_persen = '<span class="text-muted">→ 0%</span>';
    }
} else {
    $label_persen = '<span class="text-muted">-</span>';
}

// DEBUG
echo '<!-- DEBUG: persen_profit=' . ($persen_profit ?? 'NULL') . ' | profit_month=' . ($profit_month ?? 'NULL') . ' | profit_last_month=' . ($profit_last_month ?? 'NULL') . ' -->';

// =============================================================
// DATA UNTUK GRAFIK JANUARI - DESEMBER (TAHUN INI)
// =============================================================

$tahun_ini = date('Y');
$bulan_ini_num = (int)date('n');
$chart_labels = [];
$chart_income = [];
$chart_expense = [];
$chart_profit = [];

// Income per bulan (Jan - Des tahun ini) - dari invoice
$stmt = $pdo->query("
    SELECT 
        MONTH(tgl_bayar) as bulan,
        COALESCE(SUM(jumlah), 0) as total
    FROM invoice 
    WHERE status = 'paid' 
    AND YEAR(tgl_bayar) = $tahun_ini
    AND tgl_bayar IS NOT NULL
    GROUP BY MONTH(tgl_bayar)
");
$income_chart_invoice = [];
while ($row = $stmt->fetch()) {
    $income_chart_invoice[$row['bulan']] = $row['total'];
}

// Income per bulan (Jan - Des tahun ini) - dari manual
$stmt = $pdo->query("
    SELECT 
        MONTH(tanggal) as bulan,
        COALESCE(SUM(jumlah), 0) as total
    FROM income_manual 
    WHERE YEAR(tanggal) = $tahun_ini
    AND tanggal IS NOT NULL
    GROUP BY MONTH(tanggal)
");
$income_chart_manual = [];
while ($row = $stmt->fetch()) {
    $income_chart_manual[$row['bulan']] = $row['total'];
}

// Expense per bulan (Jan - Des tahun ini)
$stmt = $pdo->query("
    SELECT 
        MONTH(tanggal) as bulan,
        COALESCE(SUM(jumlah), 0) as total
    FROM pengeluaran 
    WHERE YEAR(tanggal) = $tahun_ini
    AND tanggal IS NOT NULL
    GROUP BY MONTH(tanggal)
");
$expense_chart = [];
while ($row = $stmt->fetch()) {
    $expense_chart[$row['bulan']] = $row['total'];
}

// Generate Jan - Des
$nama_bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
for ($i = 1; $i <= 12; $i++) {
    $chart_labels[] = $nama_bulan[$i];
    $income_total = ($income_chart_invoice[$i] ?? 0) + ($income_chart_manual[$i] ?? 0);
    $chart_income[] = $income_total;
    $chart_expense[] = $expense_chart[$i] ?? 0;
    $chart_profit[] = $income_total - ($expense_chart[$i] ?? 0);
}

// Total untuk tabel
$total_income = array_sum($chart_income);
$total_expense = array_sum($chart_expense);
$total_profit = $total_income - $total_expense;

// ============================================================
// SALDO PER BULAN (RUNNING BALANCE)
// ============================================================
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'saldo_awal_tahun'");
$saldo_awal = (int)($stmt->fetchColumn() ?: 0);

$saldo_per_bulan = [];
$saldo_kumulatif = $saldo_awal;

for ($i = 0; $i < 12; $i++) {
    $laba_bulan = $chart_profit[$i] ?? 0;
    $saldo_kumulatif += $laba_bulan;
    $saldo_per_bulan[$i] = $saldo_kumulatif;
}

// Saldo berjalan (sampai bulan ini)
$bulan_ini = (int)date('n') - 1; // 0-index
$saldo_berjalan = $saldo_per_bulan[$bulan_ini] ?? $saldo_awal;
?>

<style>
    .stat-box {
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        color: white;
        height: 130px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .stat-box h2 {
        margin: 0;
        font-weight: bold;
        font-size: 28px;
    }
    .stat-box small {
        opacity: 0.9;
        font-size: 12px;
    }
    .stat-box .sub-info {
        font-size: 10px;
        opacity: 0.8;
        margin-top: 3px;
    }
    .badge-laba { background-color: #28a745; color: white; }
    .badge-rugi { background-color: #dc3545; color: white; }
</style>

<!-- ============================================================= -->
<!-- BARIS 1: LABA RUGI BULAN INI & TAHUN INI -->
<!-- ============================================================= -->
<div class="row">
    <div class="col-md-6 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #0d5c1e, #1a8a2d);">
            <h2 style="color: white;">
                <?= $status_month ?>: Rp <?= number_format(abs($profit_month), 0, ',', '.') ?>
                <?= $label_persen ?? '' ?>
            </h2>
            <small>LABA RUGI BULAN INI</small>
            <div class="sub-info">
                Income: Rp <?= number_format($income_month, 0, ',', '.') ?> 
                (Invoice: Rp <?= number_format($income_invoice_month, 0, ',', '.') ?> + Manual: Rp <?= number_format($income_manual_month, 0, ',', '.') ?>)
                <br>
                Expense: Rp <?= number_format($expense_month, 0, ',', '.') ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #8e44ad, #9b59b6);">
            <h2 style="color: white;">
                <?= $status_year ?>: Rp <?= number_format(abs($profit_year), 0, ',', '.') ?>
                <?= $label_persen ?? '' ?>
            </h2>
            <small>LABA RUGI TAHUN INI</small>
            <div class="sub-info">
                Income: Rp <?= number_format($income_year, 0, ',', '.') ?>
                (Invoice: Rp <?= number_format($income_invoice_year, 0, ',', '.') ?> + Manual: Rp <?= number_format($income_manual_year, 0, ',', '.') ?>)
                <br>
                Expense: Rp <?= number_format($expense_year, 0, ',', '.') ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- BARIS 2: GRAFIK LABA RUGI -->
<!-- ============================================================= -->
<div class="row mt-3">
    <div class="col-md-12 mb-3">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar"></i> Grafik Laba Rugi Tahun <?= $tahun_ini ?> (Jan - Des)
            </div>
            <div class="card-body">
                <canvas id="profitChart" style="height: 350px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- BARIS 3: RINGKASAN TAHUNAN -->
<!-- ============================================================= -->
<div class="card mt-3">
    <div class="card-header">
        <i class="fas fa-table"></i> Ringkasan Laba Rugi Tahun <?= $tahun_ini ?>
        <div class="float-end">
            <form method="GET" action="export_laba_rugi.php" style="display:inline-block; margin:0;">
                <input type="hidden" name="dari" value="<?= $tahun_ini ?>-01-01">
                <input type="hidden" name="sampai" value="<?= $tahun_ini ?>-12-31">
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Export CSV</button>
                <a href="export_laba_rugi_pdf.php?dari=<?= $tahun_ini ?>-01-01&sampai=<?= $tahun_ini ?>-12-31" 
                   class="btn btn-danger btn-sm" target="_blank">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            </form>

                <?php if (in_array($_SESSION['user_role'] ?? '', ['super_admin','kasir'], true)): ?>
                <form method="GET" action="export_investor_pdf.php" target="_blank"
                      style="display:inline-flex; gap:4px; margin-left:5px; vertical-align:middle;">
                    <select name="bulan" class="form-select form-select-sm" style="width:auto;">
                        <?php
                        $nama_bulan_opt = ['Januari','Februari','Maret','April','Mei','Juni',
                                           'Juli','Agustus','September','Oktober','November','Desember'];
                        for ($i = 1; $i <= 12; $i++):
                        ?>
                            <option value="<?= $i ?>" <?= $i == (int)date('n') ? 'selected' : '' ?>>
                                <?= $nama_bulan_opt[$i-1] ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <input type="number" name="tahun" class="form-control form-control-sm"
                           value="<?= date('Y') ?>" min="2000" max="2100" style="width:90px;">
                    <button type="submit" class="btn btn-warning btn-sm">
                        <i class="fas fa-file-invoice-dollar"></i> Eksport Laporan Bulanan
                    </button>
                </form>
                <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <!-- Form Input Saldo Awal -->
        <div class="row mb-3">
            <div class="col-md-6">
                <form method="POST" class="d-flex gap-2 align-items-end">
                    <div class="flex-grow-1">
                        <label class="form-label mb-1"><strong>Saldo Awal Tahun</strong></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="saldo_awal" class="form-control"
                                   value="<?= number_format($saldo_awal, 0, ',', '.') ?>"
                                   placeholder="0">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th>Income</th>
                        <th>Pengeluaran</th>
                        <th>Laba / Rugi</th>
                        <th>Status</th>
                        <th>Saldo Kumulatif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $prev_profit = null;
                    ?>
                    <!-- BARIS: SALDO TAHUN LALU -->
                    <tr class="table-info">
                        <td><strong>Saldo Tahun Lalu</strong></td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-end fw-bold">Rp <?= number_format($saldo_awal, 0, ',', '.') ?></td>
                    </tr>
                    <?php
                    for ($i = 0; $i < 12; $i++): ?>
                        <?php 
                        $income = $chart_income[$i];
                        $expense = $chart_expense[$i];
                        $profit = $chart_profit[$i];
                        $status_class = $profit >= 0 ? 'text-success' : 'text-danger';
                        $status_label = $profit >= 0 ? 'Laba' : 'Rugi';
                        $badge_class = $profit >= 0 ? 'badge-laba' : 'badge-rugi';
                        
                        // Hitung persentase
                        $persen = null;
                        if ($prev_profit !== null && $prev_profit > 0) {
                            $persen = round((($profit - $prev_profit) / $prev_profit) * 100, 1);
                        }
                        
                        $label_persen = '';
                        if ($persen !== null) {
                            if ($persen > 0) {
                                $label_persen = ' <span class="text-success small">↑ ' . $persen . '%</span>';
                            } elseif ($persen < 0) {
                                $label_persen = ' <span class="text-danger small">↓ ' . abs($persen) . '%</span>';
                            } else {
                                $label_persen = ' <span class="text-muted small">→ 0%</span>';
                            }
                        }
                        ?>
                        <tr>
                            <td><strong><?= $chart_labels[$i] ?></strong></td>
                            <td>Rp <?= number_format($income, 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($expense, 0, ',', '.') ?></td>
                            <td class="<?= $status_class ?> fw-bold">Rp <?= number_format(abs($profit), 0, ',', '.') ?></td>
                            <td>
                                <span class="badge <?= $badge_class ?>"><?= $status_label ?></span>
                                <?= $label_persen ?>
                            </td>
                            <td class="text-end fw-bold <?= $saldo_per_bulan[$i] >= 0 ? 'text-success' : 'text-danger' ?>">
                                Rp <?= number_format(abs($saldo_per_bulan[$i]), 0, ',', '.') ?>
                                <?= $saldo_per_bulan[$i] < 0 ? '(Minus)' : '' ?>
                            </td>
                        </tr>
                    <?php 
                        $prev_profit = $profit;
                    endfor; ?>
                </tbody>
                <tfoot>
                    <tr class="table-dark fw-bold">
                        <td>TOTAL</td>
                        <td>Rp <?= number_format($total_income, 0, ',', '.') ?></td>
                        <td>Rp <?= number_format($total_expense, 0, ',', '.') ?></td>
                        <td class="<?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>">Rp <?= number_format(abs($total_profit), 0, ',', '.') ?></td>
                        <td><span class="badge <?= $total_profit >= 0 ? 'badge-laba' : 'badge-rugi' ?>"><?= $total_profit >= 0 ? 'Laba' : 'Rugi' ?></span></td>
                        <td class="text-end fw-bold <?= $saldo_berjalan >= 0 ? 'text-success' : 'text-danger' ?>">
                            Rp <?= number_format(abs($saldo_berjalan), 0, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- SCRIPTS -->
<!-- ============================================================= -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('profitChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                label: 'Income (Invoice + Manual)',
                data: <?= json_encode($chart_income) ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: '#28a745',
                borderWidth: 1
            },
            {
                label: 'Pengeluaran',
                data: <?= json_encode($chart_expense) ?>,
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderColor: '#dc3545',
                borderWidth: 1
            },
            {
                label: 'Laba / Rugi',
                data: <?= json_encode($chart_profit) ?>,
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: '#007bff',
                borderWidth: 2,
                type: 'line',
                tension: 0.3,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
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

<!-- ============================================================= -->
<?php include '../../includes/footer.php'; ?>
