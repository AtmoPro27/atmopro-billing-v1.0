<?php
require_once __DIR__ . "/../../includes/session_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

include '../../includes/header.php';

// Ambil daftar router aktif
try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT id, nama_router, ip_address, lokasi FROM routers WHERE status = 'active'");
    $routers = $stmt->fetchAll();
} catch(PDOException $e) {
    $routers = [];
}
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-server"></i> Manajemen Mikrotik
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Pilih Router:</label>
                        <select id="routerSelect" class="form-control">
                            <option value="">-- Pilih Router --</option>
                            <?php foreach ($routers as $router): ?>
                                <option value="<?= $router['id'] ?>">
                                    <?= htmlspecialchars($router['nama_router']) ?> (<?= $router['ip_address'] ?>) - <?= htmlspecialchars($router['lokasi']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button id="syncToRouter" class="btn btn-warning" onclick="syncToRouter()" disabled>
                                <i class="fas fa-sync-alt"></i> Sinkronisasi ke Mikrotik
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row" id="menuContainer" style="display:none;">
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-tachometer-alt"></i> Bandwidth
            </div>
            <div class="card-body">
                <p>Manajemen bandwidth limit untuk paket internet.</p>
                <a href="bandwidth.php?router_id=" id="bandwidthLink" class="btn btn-primary btn-sm">
                    <i class="fas fa-chart-line"></i> Kelola Bandwidth
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <i class="fas fa-network-wired"></i> IP Pool
            </div>
            <div class="card-body">
                <p>Manajemen IP pool untuk koneksi PPPoE.</p>
                <a href="ippool.php?router_id=" id="ippoolLink" class="btn btn-success btn-sm">
                    <i class="fas fa-address-book"></i> Kelola IP Pool
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-user-lock"></i> Profil PPPoE
            </div>
            <div class="card-body">
                <p>Manajemen profil PPPoE untuk koneksi PPPoE.</p>
                <a href="profile.php?router_id=" id="profileLink" class="btn btn-warning btn-sm">
                    <i class="fas fa-users"></i> Kelola Profil PPPoE
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let selectedRouter = null;

$('#routerSelect').change(function() {
    selectedRouter = $(this).val();
    if (selectedRouter) {
        $('#menuContainer').show();
        $('#bandwidthLink').attr('href', 'bandwidth.php?router_id=' + selectedRouter);
        $('#ippoolLink').attr('href', 'ippool.php?router_id=' + selectedRouter);
        $('#profileLink').attr('href', 'profile.php?router_id=' + selectedRouter);
        $('#syncToRouter').prop('disabled', false);
    } else {
        $('#menuContainer').hide();
        $('#syncToRouter').prop('disabled', true);
    }
});

function syncToRouter() {
    if (!selectedRouter) {
        alert('Pilih router terlebih dahulu');
        return;
    }
    
    if (confirm('Sinkronisasi akan mengirim semua konfigurasi ke Mikrotik. Lanjutkan?')) {
        $('#syncToRouter').html('<i class="fas fa-spinner fa-spin"></i> Sinkronisasi...').prop('disabled', true);
        
        $.ajax({
            url: 'sync_to_mikrotik.php',
            type: 'POST',
            data: { router_id: selectedRouter },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    alert('Sinkronisasi berhasil!\n' + res.message);
                } else {
                    alert('Sinkronisasi gagal: ' + res.message);
                }
            },
            error: function() {
                alert('Gagal sinkronisasi');
            },
            complete: function() {
                $('#syncToRouter').html('<i class="fas fa-sync-alt"></i> Sinkronisasi ke Mikrotik').prop('disabled', false);
            }
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
