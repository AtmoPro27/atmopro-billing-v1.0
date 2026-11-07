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
    
    // Income hari ini
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND DATE(tgl_bayar) = CURDATE()");
    $income_today = $stmt->fetch()['total'];
    
    // Income bulan ini
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND MONTH(tgl_bayar) = MONTH(CURDATE()) AND YEAR(tgl_bayar) = YEAR(CURDATE())");
    $income_month = $stmt->fetch()['total'];
    
    // Income tahun ini
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND YEAR(tgl_bayar) = YEAR(CURDATE())");
    $income_year = $stmt->fetch()['total'];
    
    // Total tagihan pending
    $stmt = $pdo->query("SELECT COUNT(*) as total, COALESCE(SUM(jumlah), 0) as total_nilai FROM invoice WHERE status = 'pending'");
    $pending = $stmt->fetch();
    
    // Tagihan overdue
    $stmt = $pdo->query("SELECT COUNT(*) as total, COALESCE(SUM(jumlah), 0) as total_nilai FROM invoice WHERE status = 'pending' AND tgl_jatuh_tempo < NOW()");
    $overdue = $stmt->fetch();
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="row">
    <div class="col-md-3 mb-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #0d5c1e, #1a8a2d);">
            <div class="card-body">
                <i class="fas fa-calendar-day fa-2x mb-2"></i>
                <h3 class="mb-0">Rp <?= number_format($income_today, 0, ',', '.') ?></h3>
                <small>Income Hari Ini</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #1a2980, #26d0ce);">
            <div class="card-body">
                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                <h3 class="mb-0">Rp <?= number_format($income_month, 0, ',', '.') ?></h3>
                <small>Income Bulan Ini</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #e65c00, #f9a826);">
            <div class="card-body">
                <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                <h3 class="mb-0">Rp <?= number_format($income_year, 0, ',', '.') ?></h3>
                <small>Income Tahun Ini</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-white" style="background: linear-gradient(135deg, #7c0088, #b12bc9);">
            <div class="card-body">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h3 class="mb-0"><?= $pending['total'] ?></h3>
                <small>Tagihan Pending</small>
            </div>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i> Laporan Pendapatan Bulanan
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" style="height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle"></i> Status Tagihan
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Pending:</strong> <?= $pending['total'] ?> tagihan (Rp <?= number_format($pending['total_nilai'], 0, ',', '.') ?>)
                </div>
                <div class="alert alert-danger">
                    <strong>Overdue:</strong> <?= $overdue['total'] ?> tagihan (Rp <?= number_format($overdue['total_nilai'], 0, ',', '.') ?>)
                </div>
                <div class="alert alert-success">
                    <strong>Lunas Bulan Ini:</strong> Rp <?= number_format($income_month, 0, ',', '.') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <i class="fas fa-table"></i> Laporan Pendapatan Per Bulan
        <div class="float-end">
            <a href="report_daily.php" class="btn btn-sm btn-primary">Harian</a>
            <a href="report_monthly.php" class="btn btn-sm btn-success">Bulanan</a>
            <a href="report_yearly.php" class="btn btn-sm btn-info">Tahunan</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th>Jumlah Tagihan</th>
                        <th>Total Pendapatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT 
                                DATE_FORMAT(tgl_bayar, '%Y-%m') as bulan,
                                COUNT(*) as jumlah,
                                COALESCE(SUM(jumlah), 0) as total
                            FROM invoice 
                            WHERE status = 'paid' AND tgl_bayar IS NOT NULL
                            GROUP BY DATE_FORMAT(tgl_bayar, '%Y-%m')
                            ORDER BY bulan DESC
                            LIMIT 12
                        ");
                        while ($row = $stmt->fetch()) {
                            $bulan_nama = date('F Y', strtotime($row['bulan'] . '-01'));
                            echo '<tr>';
                            echo '<td>' . $bulan_nama . '</td>';
                            echo '<td>' . $row['jumlah'] . ' tagihan</td>';
                            echo '<td>Rp ' . number_format($row['total'], 0, ',', '.') . '</td>';
                            echo '<td><a href="report_detail.php?bulan=' . $row['bulan'] . '" class="btn btn-sm btn-info">Detail</a></td>';
                            echo '</tr>';
                        }
                    } catch(PDOException $e) {
                        echo '<tr><td colspan="4">Error: ' . $e->getMessage() . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php
// Ambil data 12 bulan terakhir untuk chart
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(tgl_bayar, '%Y-%m') as bulan,
        COALESCE(SUM(jumlah), 0) as total
    FROM invoice 
    WHERE status = 'paid' AND tgl_bayar IS NOT NULL
    GROUP BY DATE_FORMAT(tgl_bayar, '%Y-%m')
    ORDER BY bulan ASC
    LIMIT 12
");
$chart_labels = [];
$chart_data = [];
while ($row = $stmt->fetch()) {
    $chart_labels[] = date('M Y', strtotime($row['bulan'] . '-01'));
    $chart_data[] = $row['total'];
}
?>
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: '#0d5c1e',
            borderColor: '#0a4a16',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
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
