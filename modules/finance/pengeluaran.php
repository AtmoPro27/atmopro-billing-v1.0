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

    // RUTIN
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran WHERE jenis = 'rutin' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
    $rutin = $stmt->fetch()['total'];

    // MAINTENANCE
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran WHERE jenis = 'maintenance' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
    $maintenance = $stmt->fetch()['total'];

    // BAGI HASIL
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran WHERE jenis = 'bagi_hasil' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
    $bagi_hasil = $stmt->fetch()['total'];

    // PSB
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran WHERE jenis = 'psb' AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())");
    $psb = $stmt->fetch()['total'];

    // TOTAL PENGELUARAN BULAN INI
    $total_pengeluaran = $rutin + $maintenance + $bagi_hasil + $psb;

    // HARI INI
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran WHERE DATE(tanggal) = CURDATE()");
    $today = $stmt->fetch()['total'];

    // TAHUN INI
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM pengeluaran WHERE YEAR(tanggal) = YEAR(CURDATE())");
    $year = $stmt->fetch()['total'];

} catch(PDOException $e) {
    $error = $e->getMessage();
    $rutin = 0;
    $maintenance = 0;
    $bagi_hasil = 0;
    $psb = 0;
    $total_pengeluaran = 0;
    $today = 0;
    $year = 0;
}
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
</style>

<div class="row">
    <!-- RUTIN -->
    <div class="col-md-4 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #2b5876, #4e4376);">
            <i class="fas fa-repeat fa-2x mb-2"></i>
            <h2 style="color: white;">Rp <?= number_format($rutin, 0, ',', '.') ?></h2>
            <small>RUTIN</small>
        </div>
    </div>

    <!-- MAINTENANCE -->
    <div class="col-md-4 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #c0392b, #e74c3c);">
            <i class="fas fa-tools fa-2x mb-2"></i>
            <h2 style="color: white;">Rp <?= number_format($maintenance, 0, ',', '.') ?></h2>
            <small>MAINTENANCE</small>
        </div>
    </div>

    <!-- BAGI HASIL -->
    <div class="col-md-4 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
            <i class="fas fa-hand-holding-usd fa-2x mb-2"></i>
            <h2 style="color: white;">Rp <?= number_format($bagi_hasil, 0, ',', '.') ?></h2>
            <small>BAGI HASIL</small>
        </div>
    </div>
</div>

<div class="row mt-2">
    <!-- BULAN INI -->
    <div class="col-md-4 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #1a2980, #26d0ce);">
            <i class="fas fa-calendar-alt fa-2x mb-2"></i>
            <h2 style="color: white;">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></h2>
            <small>BULAN INI</small>
        </div>
    </div>

    <!-- TOTAL PENGELUARAN -->
    <div class="col-md-4 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #0d5c1e, #1a8a2d);">
            <i class="fas fa-coins fa-2x mb-2"></i>
            <h2 style="color: white;">Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?></h2>
            <small>TOTAL PENGELUARAN</small>
        </div>
    </div>

    <!-- TAHUN INI -->
    <div class="col-md-4 mb-3">
        <div class="stat-box" style="background: linear-gradient(135deg, #8e44ad, #9b59b6);">
            <i class="fas fa-calendar-alt fa-2x mb-2"></i>
            <h2 style="color: white;">Rp <?= number_format($year, 0, ',', '.') ?></h2>
            <small>TAHUN INI</small>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body text-center">
                <a href="add_pengeluaran.php" class="btn btn-success"><i class="fas fa-plus"></i> Tambah Pengeluaran</a>
                <a href="pengeluaran_report.php" class="btn btn-primary"><i class="fas fa-list"></i> Laporan Lengkap</a>
                <a href="setting_auto_pengeluaran.php" class="btn btn-info"><i class="fas fa-cog"></i> Setting Auto</a>
                <a href="export_pengeluaran.php?dari=<?= date("Y-m-01") ?>&sampai=<?= date("Y-m-t") ?>" class="btn btn-success"><i class="fas fa-file-excel"></i> Export CSV</a>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <i class="fas fa-table"></i> Laporan Pengeluaran Bulan Ini
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Keterangan</th>
                        <th>Jumlah</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT id, tanggal, jenis, keterangan, jumlah
                            FROM pengeluaran 
                            WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())
                            ORDER BY tanggal DESC
                            LIMIT 50
                        ");
                        while ($row = $stmt->fetch()) {
                            $jenis_label = [
                                'rutin' => 'Rutin',
                                'maintenance' => 'Maintenance',
                                'bagi_hasil' => 'Bagi Hasil',
                                'psb' => 'PSB'
                            ][$row['jenis']] ?? $row['jenis'];

                            $jenis_badge = [
                                'rutin' => 'bg-secondary',
                                'maintenance' => 'bg-danger',
                                'bagi_hasil' => 'bg-warning text-dark',
                                'psb' => 'bg-info'
                            ][$row['jenis']] ?? 'bg-secondary';

                            echo '<tr>';
                            echo '<td>' . date('d-m-Y', strtotime($row['tanggal'])) . '</td>';
                            echo '<td><span class="badge ' . $jenis_badge . '">' . $jenis_label . '</span></td>';
                            echo '<td>' . htmlspecialchars($row['keterangan']) . '</td>';
                            echo '<td>Rp ' . number_format($row['jumlah'], 0, ',', '.') . '</td>';
                            echo '<td>
                                    <a href="edit_pengeluaran.php?id=' . $row['id'] . '" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <a href="delete_pengeluaran.php?id=' . $row['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Yakin?\')"><i class="fas fa-trash"></i></a>
                                  </td>';
                            echo '</tr>';
                        }
                    } catch(PDOException $e) {
                        echo '<tr><td colspan="5">Error: ' . $e->getMessage() . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
