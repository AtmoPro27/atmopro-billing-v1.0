<?php
if (!function_exists('getServerStats')) {
    function getCpuUsage() {
    $stat1 = @file_get_contents('/proc/stat');
    usleep(100000);
    $stat2 = @file_get_contents('/proc/stat');
    
    if (!$stat1 || !$stat2) return 0;
    
    preg_match('/cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat1, $s1);
    preg_match('/cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat2, $s2);
    
    if (!isset($s1[1]) || !isset($s2[1])) return 0;
    
    $total1 = array_sum(array_slice($s1, 1, 4));
    $total2 = array_sum(array_slice($s2, 1, 4));
    $idle1 = $s1[4] ?? 0;
    $idle2 = $s2[4] ?? 0;
    
    $diff_total = $total2 - $total1;
    $diff_idle = $idle2 - $idle1;
    
    if ($diff_total == 0) return 0;
    
    return round((1 - $diff_idle / $diff_total) * 100, 1);
}

function getServerStats() {
        // CPU
        $load = sys_getloadavg();
        $cpu_cores = 1;
        if (@is_readable('/proc/cpuinfo')) {
            $cpuinfo = @file_get_contents('/proc/cpuinfo');
            if ($cpuinfo) {
                $cpu_cores = substr_count($cpuinfo, 'processor');
                if ($cpu_cores < 1) $cpu_cores = 1;
            }
        }
        // CPU usage real-time dari /proc/stat
        $cpu_percent = getCpuUsage();

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
            require_once __DIR__ . '/../config/database.php';
            $pdo = Database::getInstance()->getConnection();
            $db_size = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE()")->fetch()['size_mb'];
        } catch (Exception $e) {}

        return [
            'cpu' => $cpu_percent,
            'ram' => $ram_percent,
            'ram_used' => $ram_used,
            'ram_total' => $ram_total,
            'disk' => $disk_percent,
            'disk_used' => $disk_used,
            'disk_total' => $disk_total,
            'uptime_days' => $uptime_days,
            'uptime_hours' => $uptime_hours,
            'uptime_mins' => $uptime_mins,
            'db_size' => $db_size,
        ];
    }
}

function getServerColor($percent) {
    if ($percent < 60) return 'success';
    if ($percent < 80) return 'warning';
    return 'danger';
}

$server_stats = getServerStats();
?>

<div class="card shadow mb-3">
    <div class="card-header bg-dark text-white">
        <i class="fas fa-server"></i> Monitor Server
        <small class="float-end">
            <a href="/modules/monitoring/server_monitor.php" class="text-white text-decoration-none">
                Detail <i class="fas fa-arrow-right"></i>
            </a>
        </small>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-2 col-4 mb-2">
                <div class="border rounded p-2">
                    <i class="fas fa-microchip fa-2x text-<?= getServerColor($server_stats['cpu']) ?>"></i>
                    <h4 class="mb-0 mt-1"><?= $server_stats['cpu'] ?>%</h4>
                    <small class="text-muted">CPU</small>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="border rounded p-2">
                    <i class="fas fa-memory fa-2x text-<?= getServerColor($server_stats['ram']) ?>"></i>
                    <h4 class="mb-0 mt-1"><?= $server_stats['ram'] ?>%</h4>
                    <small class="text-muted">RAM</small>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="border rounded p-2">
                    <i class="fas fa-hdd fa-2x text-<?= getServerColor($server_stats['disk']) ?>"></i>
                    <h4 class="mb-0 mt-1"><?= $server_stats['disk'] ?>%</h4>
                    <small class="text-muted">Disk</small>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="border rounded p-2">
                    <i class="fas fa-clock fa-2x text-info"></i>
                    <h4 class="mb-0 mt-1"><?= $server_stats['uptime_days'] ?>h <?= $server_stats['uptime_hours'] ?>j</h4>
                    <small class="text-muted">Uptime</small>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="border rounded p-2">
                    <i class="fas fa-database fa-2x text-primary"></i>
                    <h4 class="mb-0 mt-1"><?= $server_stats['db_size'] ?>MB</h4>
                    <small class="text-muted">Database</small>
                </div>
            </div>
            <div class="col-md-2 col-4 mb-2">
                <div class="border rounded p-2">
                    <i class="fas fa-chart-line fa-2x text-success"></i>
                    <h4 class="mb-0 mt-1"><?= round($server_stats['ram_used'], 1) ?>GB</h4>
                    <small class="text-muted">RAM Used</small>
                </div>
            </div>
        </div>
    </div>
</div>
