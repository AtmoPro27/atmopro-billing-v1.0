<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';

// CPU
$load = sys_getloadavg();
$cpu_cores = 1;
$cpu_percent = 0;
if (@is_readable('/proc/cpuinfo')) {
    $cpuinfo = @file_get_contents('/proc/cpuinfo');
    if ($cpuinfo) {
        $cpu_cores = substr_count($cpuinfo, 'processor');
        if ($cpu_cores < 1) $cpu_cores = 1;
    }
}
if ($cpu_cores > 0) {
    $cpu_percent = round(($load[0] / $cpu_cores) * 100, 1);
}

// RAM
$ram_total = 0;
$ram_used = 0;
$ram_percent = 0;
if (@is_readable('/proc/meminfo')) {
    $meminfo = @file_get_contents('/proc/meminfo');
    if ($meminfo && preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total) && preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $avail)) {
        if (isset($total[1]) && isset($avail[1])) {
            $ram_total = round($total[1] / 1024 / 1024, 2);
            $ram_avail = round($avail[1] / 1024 / 1024, 2);
            $ram_used = round($ram_total - $ram_avail, 2);
            if ($ram_total > 0) {
                $ram_percent = round(($ram_used / $ram_total) * 100, 1);
            }
        }
    }
}

// Disk
$disk_total = 0;
$disk_used = 0;
$disk_percent = 0;
$disk_total_bytes = @disk_total_space('/var/www/demo.atmopro.my.id');
$disk_free_bytes = @disk_free_space('/var/www/demo.atmopro.my.id');
if ($disk_total_bytes && $disk_free_bytes) {
    $disk_total = round($disk_total_bytes / 1024 / 1024 / 1024, 2);
    $disk_free = round($disk_free_bytes / 1024 / 1024 / 1024, 2);
    $disk_used = round($disk_total - $disk_free, 2);
    if ($disk_total > 0) {
        $disk_percent = round(($disk_used / $disk_total) * 100, 1);
    }
}

// Uptime
$uptime_days = 0;
$uptime_hours = 0;
$uptime_mins = 0;
if (@is_readable('/proc/uptime')) {
    $uptime_raw = @file_get_contents('/proc/uptime');
    if ($uptime_raw) {
        $uptime_sec = (int)explode(' ', $uptime_raw)[0];
        $uptime_days = floor($uptime_sec / 86400);
        $uptime_hours = floor(($uptime_sec % 86400) / 3600);
        $uptime_mins = floor(($uptime_sec % 3600) / 60);
    }
}

// Database
$db_size = 0;
try {
    require_once '../../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    $db_size = $pdo->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.TABLES
        WHERE table_schema = DATABASE()
    ")->fetch()['size_mb'];
} catch (Exception $e) {
    $db_size = 0;
}

$php_version = PHP_VERSION;
$php_memory = round(memory_get_usage() / 1024 / 1024, 2);

function getColor($percent) {
    if ($percent < 60) return 'success';
    if ($percent < 80) return 'warning';
    return 'danger';
}
?>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">🖥️ Monitor Server</h1>
        <a href="/dashboard_fix.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="row mb-3">
        <div class="col-md-4 mb-3">
            <div class="card shadow h-100">
                <div class="card-body text-center">
                    <i class="fas fa-microchip fa-3x text-<?= getColor($cpu_percent) ?>"></i>
                    <h5 class="mt-3">CPU</h5>
                    <h2 class="text-<?= getColor($cpu_percent) ?>"><?= $cpu_percent ?>%</h2>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-<?= getColor($cpu_percent) ?>" 
                             style="width: <?= $cpu_percent ?>%"></div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Load: <?= number_format($load[0], 2) ?>, 
                        <?= number_format($load[1], 2) ?>, 
                        <?= number_format($load[2], 2) ?><br>
                        <?= $cpu_cores ?> CPU Cores
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow h-100">
                <div class="card-body text-center">
                    <i class="fas fa-memory fa-3x text-<?= getColor($ram_percent) ?>"></i>
                    <h5 class="mt-3">RAM</h5>
                    <h2 class="text-<?= getColor($ram_percent) ?>"><?= $ram_percent ?>%</h2>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-<?= getColor($ram_percent) ?>" 
                             style="width: <?= $ram_percent ?>%"></div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <?= $ram_used ?> GB / <?= $ram_total ?> GB
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow h-100">
                <div class="card-body text-center">
                    <i class="fas fa-hdd fa-3x text-<?= getColor($disk_percent) ?>"></i>
                    <h5 class="mt-3">Disk</h5>
                    <h2 class="text-<?= getColor($disk_percent) ?>"><?= $disk_percent ?>%</h2>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-<?= getColor($disk_percent) ?>" 
                             style="width: <?= $disk_percent ?>%"></div>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <?= $disk_used ?> GB / <?= $disk_total ?> GB
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card shadow">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-3x text-info"></i>
                    <h5 class="mt-3">Uptime</h5>
                    <h3><?= $uptime_days ?> hari</h3>
                    <small class="text-muted"><?= $uptime_hours ?> jam, <?= $uptime_mins ?> menit</small>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow">
                <div class="card-body text-center">
                    <i class="fas fa-database fa-3x text-primary"></i>
                    <h5 class="mt-3">Database</h5>
                    <h3><?= $db_size ?> MB</h3>
                    <small class="text-muted">demo_billing</small>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow">
                <div class="card-body text-center">
                    <i class="fas fa-code fa-3x text-warning"></i>
                    <h5 class="mt-3">PHP</h5>
                    <h3>v<?= $php_version ?></h3>
                    <small class="text-muted">Memory: <?= $php_memory ?> MB</small>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-3">
        <small class="text-muted">
            <i class="fas fa-sync"></i> Auto refresh: <span id="countdown">30</span>s
        </small>
    </div>
</div>

<script>
let countdown = 30;
setInterval(function() {
    countdown--;
    document.getElementById('countdown').textContent = countdown;
    if (countdown <= 0) {
        location.reload();
    }
}, 1000);
</script>

<?php include '../../includes/footer.php'; ?>
