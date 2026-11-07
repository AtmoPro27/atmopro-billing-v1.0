<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';

$tahun = $_GET['tahun'] ?? date('Y');

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            bulan,
            SUM(jumlah_transaksi) AS jumlah_transaksi,
            SUM(total) AS total
        FROM (
            SELECT
                MONTH(tgl_bayar) AS bulan,
                COUNT(*) AS jumlah_transaksi,
                COALESCE(SUM(jumlah), 0) AS total
            FROM invoice
            WHERE status = 'paid' AND YEAR(tgl_bayar) = ?
            GROUP BY MONTH(tgl_bayar)
            UNION ALL
            SELECT
                MONTH(tanggal) AS bulan,
                COUNT(*) AS jumlah_transaksi,
                COALESCE(SUM(jumlah), 0) AS total
            FROM income_manual
            WHERE YEAR(tanggal) = ?
            GROUP BY MONTH(tanggal)
        ) AS gabungan
        GROUP BY bulan
        ORDER BY bulan ASC
    ");
    $stmt->execute([$tahun, $tahun]);
    $data_bulanan = $stmt->fetchAll();
    
    $total_tahunan = array_sum(array_column($data_bulanan, 'total'));
    
    $chart_data = array_fill(1, 12, 0);
    foreach ($data_bulanan as $row) {
        $chart_data[$row['bulan']] = $row['total'];
    }
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-calendar-year"></i> Laporan Pendapatan Tahunan
        <a href="income.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <form method="GET" class="row mb-3">
            <div class="col-md-4">
                <input type="number" name="tahun" class="form-control"  value="<?= htmlspecialchars($tahun) ?>" min="2020" max="2030">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
        
        <div class="alert alert-info">
            <strong>Tahun:</strong> <?= $tahun ?><br>
            <strong>Total Pendapatan:</strong> Rp <?= number_format($total_tahunan, 0, ',', '.') ?>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <canvas id="yearlyChart" style="height: 300px;"></canvas>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">Rekap Bulanan</div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Bulan</th><th>Total</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $nama_bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
                                for ($i = 1; $i <= 12; $i++) {
                                    $total = $chart_data[$i];
                                    echo "<tr>
                                        <td>{$nama_bulan[$i-1]}</td>
                                        <td>Rp " . number_format($total, 0, ',', '.') . "</td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('yearlyChart'), {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: <?= json_encode(array_values($chart_data)) ?>,
            borderColor: '#0d5c1e',
            backgroundColor: 'rgba(13,92,30,0.1)',
            fill: true,
            tension: 0.3
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
