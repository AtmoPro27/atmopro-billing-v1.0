<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: ../logs/index.php");
    exit();
}

include '../../includes/header.php';

$message = '';
$total_logs = 0;

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_logs");
    $total_logs = $stmt->fetch()['total'];
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['delete_all'])) {
            $pdo->query("TRUNCATE TABLE admin_logs");
            $message = '<div class="alert alert-success">✅ Semua log berhasil dihapus!</div>';
            $total_logs = 0;
        }
        
        if (isset($_POST['delete_by_days']) && isset($_POST['days'])) {
            $days = (int)$_POST['days'];
            $date_limit = date('Y-m-d H:i:s', strtotime("-$days days"));
            $stmt = $pdo->prepare("DELETE FROM admin_logs WHERE created_at < ?");
            $stmt->execute([$date_limit]);
            $deleted = $stmt->rowCount();
            $message = "<div class='alert alert-success'>✅ Berhasil menghapus $deleted log yang lebih dari $days hari!</div>";
            
            // Update total
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_logs");
            $total_logs = $stmt->fetch()['total'];
        }
        
        if (isset($_POST['keep_count']) && isset($_POST['count'])) {
            $keep = (int)$_POST['count'];
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_logs");
            $total = $stmt->fetch()['total'];
            
            if ($total > $keep) {
                $to_delete = $total - $keep;
                $stmt = $pdo->prepare("DELETE FROM admin_logs ORDER BY created_at ASC LIMIT ?");
                $stmt->execute([$to_delete]);
                $message = "<div class='alert alert-success'>✅ Berhasil menyimpan $keep log terakhir (menghapus $to_delete log)!</div>";
            } else {
                $message = "<div class='alert alert-info'>ℹ️ Total log ($total) sudah kurang dari batas ($keep). Tidak ada yang dihapus.</div>";
            }
            
            // Update total
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin_logs");
            $total_logs = $stmt->fetch()['total'];
        }
    }
} catch(PDOException $e) {
    $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>

<div class="card">
    <div class="card-header bg-danger text-white">
        <i class="fas fa-trash-alt"></i> Pembersihan Log Aktivitas
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali ke Log</a>
    </div>
    <div class="card-body">
        <?= $message ?>
        
        <div class="alert alert-info">
            <strong>📊 Statistik Log Saat Ini:</strong><br>
            Total log tersimpan: <strong><?= number_format($total_logs) ?></strong> record
        </div>
        
        <div class="row">
            <!-- Hapus Semua -->
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white">
                        <i class="fas fa-trash-alt"></i> Hapus Semua Log
                    </div>
                    <div class="card-body">
                        <p>Menghapus <strong>SEMUA</strong> log aktivitas.</p>
                        <p class="text-danger"><small>⚠️ Tindakan ini tidak dapat dibatalkan!</small></p>
                        <form method="POST">
                            <button type="submit" name="delete_all" class="btn btn-danger w-100" onclick="return confirm('Yakin ingin menghapus SEMUA log?')">
                                <i class="fas fa-trash"></i> Hapus Semua (<?= number_format($total_logs) ?> log)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Hapus berdasarkan hari -->
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-calendar-alt"></i> Hapus Berdasarkan Hari
                    </div>
                    <div class="card-body">
                        <p>Hapus log yang lebih dari:</p>
                        <form method="POST">
                            <div class="input-group mb-3">
                                <input type="number" name="days" class="form-control" value="30" min="1" required>
                                <span class="input-group-text">hari</span>
                            </div>
                            <button type="submit" name="delete_by_days" class="btn btn-warning w-100">
                                <i class="fas fa-clock"></i> Hapus
                            </button>
                        </form>
                        <small class="text-muted">Contoh: 30 hari = hapus log lebih dari 30 hari yang lalu</small>
                    </div>
                </div>
            </div>
            
            <!-- Simpan N log terakhir -->
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-sort-amount-down"></i> Simpan N Log Terakhir
                    </div>
                    <div class="card-body">
                        <p>Simpan sejumlah log terakhir:</p>
                        <form method="POST">
                            <div class="input-group mb-3">
                                <input type="number" name="count" class="form-control" value="1000" min="1" required>
                                <span class="input-group-text">log terakhir</span>
                            </div>
                            <button type="submit" name="keep_count" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                        </form>
                        <small class="text-muted">Contoh: 1000 = simpan 1000 log terbaru, hapus sisanya</small>
                    </div>
                </div>
            </div>
        </div>
        
        <hr>
        
        <div class="alert alert-secondary">
            <strong><i class="fas fa-info-circle"></i> Rekomendasi:</strong><br>
            - Untuk VPS kecil: simpan <strong>1000-2000</strong> log terakhir<br>
            - Untuk VPS besar: simpan <strong>5000-10000</strong> log terakhir<br>
            - Atau hapus otomatis log lebih dari <strong>30-90 hari</strong>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
