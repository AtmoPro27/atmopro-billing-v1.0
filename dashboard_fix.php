<?php
require_once __DIR__ . '/includes/auth_check.php';

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

try {
    $pdo = require_once __DIR__ . "/config/database.php";
$pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->query("SELECT key_setting, value FROM pengaturan");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key_setting']] = $row['value'];
    }

    // Income hari ini
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND DATE(tgl_bayar) = CURDATE()");
    $income_today = $stmt->fetch()['total'];
    
    // Income bulan ini
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND MONTH(tgl_bayar) = MONTH(CURDATE()) AND YEAR(tgl_bayar) = YEAR(CURDATE())");
    $income_month = $stmt->fetch()['total'];
    
    // PPPoE online dari cache
    $pppoe_online = (int) @file_get_contents('/tmp/pppoe_online_count_demo.txt');
    
    // Pelanggan baru bulan ini (berdasarkan created_at)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $pelanggan_baru = $stmt->fetch()['total'];
    
    // Tagihan Pending
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM invoice WHERE status = 'pending'");
    $total_tagihan = $stmt->fetch()['total'];
    
    // Tagihan Lunas (untuk grafik)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM invoice WHERE status = 'paid' AND MONTH(tgl_bayar) = MONTH(CURDATE()) AND YEAR(tgl_bayar) = YEAR(CURDATE())");
    $total_lunas = $stmt->fetch()['total'];
    
    // Pelanggan ISOLIR (status = 'inactive')
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE status = 'inactive'");
    $isolir_count = $stmt->fetch()['total'];

    // ============================================
    // CARD BULANAN (RESET SETIAP BULAN)
    // ============================================
    
    // Jumlah pelanggan reguler
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE (tipe_pelanggan = 'reguler' OR tipe_pelanggan IS NULL)");
    $total_reguler = $stmt->fetch()['total'];

    // ============================================
    // INVOICE BULAN INI (berdasarkan tgl_terbit)
    // ============================================

    // Total invoice bulan ini
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT i.pelanggan_id) as total
        FROM invoice i
        INNER JOIN pelanggan p ON i.pelanggan_id = p.id
        WHERE (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)
        AND MONTH(i.tgl_terbit) = MONTH(CURDATE())
        AND YEAR(i.tgl_terbit) = YEAR(CURDATE())
    ");
    $total_invoice_bulan_ini = $stmt->fetch()['total'];

    // Invoice pending bulan ini
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT i.pelanggan_id) as total
        FROM invoice i
        INNER JOIN pelanggan p ON i.pelanggan_id = p.id
        WHERE i.status = 'pending'
        AND (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)
        AND MONTH(i.tgl_terbit) = MONTH(CURDATE())
        AND YEAR(i.tgl_terbit) = YEAR(CURDATE())
    ");
    $pending_bulan_ini = $stmt->fetch()['total'];

    // Invoice lunas bulan ini
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT i.pelanggan_id) as total
        FROM invoice i
        INNER JOIN pelanggan p ON i.pelanggan_id = p.id
        WHERE i.status = 'paid'
        AND (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)
        AND MONTH(i.tgl_terbit) = MONTH(CURDATE())
        AND YEAR(i.tgl_terbit) = YEAR(CURDATE())
    ");
    $lunas_bulan_ini = $stmt->fetch()['total'];

    // Persentase
    $persen_pending = $total_invoice_bulan_ini > 0 ? round(($pending_bulan_ini / $total_invoice_bulan_ini) * 100, 1) : 0;
    $persen_lunas = $total_invoice_bulan_ini > 0 ? round(($lunas_bulan_ini / $total_invoice_bulan_ini) * 100, 1) : 0;

    // ============================================
    // TIDAK PUNYA INVOICE BULAN INI
    // ============================================

    // Pelanggan Isolir reguler (inactive + aktif = nunggak)
    $stmt = $pdo->query("
        SELECT COUNT(*) as total FROM pelanggan
        WHERE (tipe_pelanggan = 'reguler' OR tipe_pelanggan IS NULL)
        AND status = 'inactive' AND status_aktif = 'aktif'
    ");
    $isolir_reguler = $stmt->fetch()['total'];

    // Persentase isolir dari total pelanggan
    $persen_isolir = $total_reguler > 0 ? round(($isolir_reguler / $total_reguler) * 100, 1) : 0;
    // Pelanggan bayar tunggakan bulan lalu di bulan ini
    // (invoice terbit bukan bulan ini, tapi dibayar bulan ini)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT i.pelanggan_id) as total
        FROM invoice i
        INNER JOIN pelanggan p ON i.pelanggan_id = p.id
        WHERE i.status = 'paid'
        AND (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)
        AND MONTH(i.tgl_bayar) = MONTH(CURDATE())
        AND YEAR(i.tgl_bayar) = YEAR(CURDATE())
        AND (MONTH(i.tgl_terbit) != MONTH(CURDATE()) OR YEAR(i.tgl_terbit) != YEAR(CURDATE()))
    ");
    $bayar_tunggakan = $stmt->fetch()['total'];
    
    // TOTAL SEMUA PELANGGAN (active + inactive + blocked)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelanggan");
    $total_customers = $stmt->fetch()['total'];

} catch(PDOException $e) {
    $settings = [];
    $income_today = 0;
    $income_month = 0;
    $pppoe_online = 0;
    $pelanggan_baru = 0;
    $total_tagihan = 0;
    $total_lunas = 0;
    $isolir_count = 0;
    $total_customers = 0;
}

$currency_symbol = $settings['currency_symbol'] ?? 'Rp';
$current_month = date('F Y');

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col mb-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #0d5c1e, #1a8a2d); cursor: pointer;" onclick="showDetail('income')">
                <div class="card-body">
                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                    <h3 class="mb-0"><?= $currency_symbol ?> <?= number_format($income_today, 0, ',', '.') ?></h3>
                    <small>Income Hari Ini</small>
                </div>
            </div>
        </div>

        <div class="col mb-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #1a2980, #26d0ce); cursor: pointer;" onclick="showDetail('income_month')">
                <div class="card-body">
                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                    <h3 class="mb-0"><?= $currency_symbol ?> <?= number_format($income_month, 0, ',', '.') ?></h3>
                    <small>Income Bulan Ini</small>
                </div>
            </div>
        </div>

        <div class="col mb-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #e65c00, #f9a826); cursor: pointer; min-height: 130px;" onclick="showDetail('pppoe')">
                <div class="card-body style="padding: 15px !important;">
                    <i class="fas fa-wifi fa-2x mb-1"></i>
                    <h4 class="mb-0">
                        <?= number_format((int) @file_get_contents('/tmp/pppoe_reguler_demo.txt')) ?>
                        <small class="text-white-50">/</small>
                        <?= number_format((int) @file_get_contents('/tmp/pppoe_khusus_demo.txt')) ?>
                    </h4>
                    <small>PPPoE Online (Reg/Khusus)</small>
                </div>
            </div>
        </div>

        <div class="col mb-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #7c0088, #b12bc9); cursor: pointer;" onclick="showDetail('new_customer')">
                <div class="card-body">
                    <i class="fas fa-user-plus fa-2x mb-2"></i>
                    <h3 class="mb-0"><?= number_format($pelanggan_baru) ?></h3>
                    <small>Pelanggan Baru - <?= $current_month ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
<div class="row mt-3">
    <!-- KIRI: Grafik + Ringkasan -->
    <div class="col-md-6 mb-3">
        <div class="card">
            <div class="card-header">Grafik Pendapatan 30 Hari</div>
            <div class="card-body"><canvas id="incomeChart" style="height:250px;"></canvas></div>
        </div>
        
        <!-- Ringkasan Bulan Ini -->
        <div class="card mt-3">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-chart-bar"></i> Ringkasan Bulan <?= $current_month ?>
            </div>
            <div class="card-body">
                <!-- BARIS 1: Total & Invoice Bulan Ini -->
                <div class="row text-center mb-2">
                    <div class="col-md-4 col-12 mb-2">
                        <div class="border rounded p-3 bg-light">
                            <i class="fas fa-users fa-2x text-success mb-2"></i>
                            <h4 class="mb-0"><?= number_format($total_reguler) ?></h4>
                            <small class="text-muted">Total Pelanggan Reguler</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-6 mb-2">
                        <div class="border rounded p-3 bg-light">
                            <i class="fas fa-file-invoice fa-2x text-info mb-2"></i>
                            <h4 class="mb-0"><?= number_format($total_invoice_bulan_ini) ?></h4>
                            <small class="text-muted">Invoice terbit </br>Bulan Ini</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-6 mb-2">
                        <div class="border rounded p-3 bg-light">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0"><?= number_format($pending_bulan_ini) ?></h4>
                            <small class="text-muted">Pending Bulan Ini</small>
                            <div class="small text-warning fw-bold"><?= $persen_pending ?>%</div>
                        </div>
                    </div>
                </div>

                <!-- BARIS 2: Lunas, Bayar Tunggakan, Isolir -->
                <div class="row text-center">
                    <div class="col-md-4 col-6 mb-2">
                        <div class="border rounded p-3 bg-light">
                            <i class="fas fa-check-circle fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0"><?= number_format($lunas_bulan_ini) ?></h4>
                            <small class="text-muted">Lunas Bulan Ini</small>
                            <div class="small text-primary fw-bold"><?= $persen_lunas ?>%</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6 mb-2">
                        <div class="border rounded p-3 bg-light">
                            <i class="fas fa-hand-holding-usd fa-2x text-info mb-2"></i>
                            <h4 class="mb-0"><?= number_format($bayar_tunggakan) ?></h4>
                            <small class="text-muted">Bayar Tunggakan</small>
                            <div class="small text-info fw-bold">bulan sebelumnya</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-6 mb-2">
                        <div class="border rounded p-3 bg-light">
                            <i class="fas fa-user-slash fa-2x text-danger mb-2"></i>
                            <h4 class="mb-0"><?= number_format($isolir_reguler) ?></h4>
                            <small class="text-muted">Isolir (Nunggak)</small>
                            <div class="small text-danger fw-bold"><?= $persen_isolir ?>%</div>
                        </div>
                    </div>
                </div></div>
        </div>
    </div>
    
    <!-- KANAN: Pie -->
    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-header">Status Tagihan</div>
            <div class="card-body"><canvas id="statusChart" style="height:250px;"></canvas></div>
        </div>
    </div>
</div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Pelanggan Isolir</div>
                <div class="card-body">
                    <h3 class="text-danger"><?= number_format($isolir_count) ?></h3>
                    <p>Pelanggan yang sedang diisolir</p>
                    <a href="/modules/customers/index.php?status=inactive" class="btn btn-sm btn-primary">Lihat</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Ringkasan Bulan Ini</div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><td width="50%">Total Pelanggan</td><td class="text-end"><strong><?= number_format($total_customers) ?></strong></td></tr>
                        <tr><td>Tagihan Pending</td><td class="text-end"><strong><?= number_format($total_tagihan) ?></strong></td></tr>
                        <tr><td>Pelanggan Baru (<?= $current_month ?>)</td><td class="text-end"><strong><?= number_format($pelanggan_baru) ?></strong></td></tr>
                        <tr><td class="text-danger">Pelanggan Isolir</td><td class="text-end text-danger"><strong><?= number_format($isolir_count) ?></strong></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="detailModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1050;">
    <div style="background:white; width:90%; max-width:800px; margin:50px auto; border-radius:10px;">
        <div style="padding:15px; background:#0d5c1e; color:white; border-radius:10px 10px 0 0; display:flex; justify-content:space-between;">
            <h5 id="modalTitle">Detail</h5>
            <button onclick="closeModal()" style="background:none; border:none; color:white; font-size:24px;">&times;</button>
        </div>
        <div id="modalBody" style="padding:20px; max-height:60vh; overflow-y:auto;">Loading...</div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function showDetail(type) {
    $('#detailModal').show();
    $('#modalBody').html('<div class="text-center">Loading...</div>');
    if (type == 'income') { $('#modalTitle').html('Income Hari Ini'); $('#modalBody').load('ajax/income_detail.php'); }
    else if (type == 'income_month') { $('#modalTitle').html('Income Bulan Ini'); $('#modalBody').load('ajax/income_month_detail.php'); }
    else if (type == 'pppoe') { $('#modalTitle').html('Users PPPoE Online'); $('#modalBody').load('ajax/pppoe_detail.php'); }
    else if (type == 'new_customer') { $('#modalTitle').html('Pelanggan Baru'); $('#modalBody').load('ajax/new_customer_detail.php'); }
}
function closeModal() { $('#detailModal').hide(); }
function refreshPPPoE() {
    $.ajax({ url: 'ajax/get_pppoe_count.php', type: 'GET', dataType: 'json',
        success: function(res) { if(res.success) { $('#pppoeCount').text(res.total); } }
    });
}

// Grafik Pendapatan 30 Hari
$.ajax({
    url: 'ajax/get_income_30days.php',
    type: 'GET',
    dataType: 'json',
    success: function(res) {
        if (res.success) {
            new Chart(document.getElementById('incomeChart'), { 
                type: 'line', 
                data: { 
                    labels: res.labels, 
                    datasets: [{ 
                        label: 'Pendapatan', 
                        data: res.data, 
                        borderColor: '#0d5c1e', 
                        fill: true 
                    }] 
                } 
            });
        } else {
            fallbackIncomeChart();
        }
    },
    error: function() { fallbackIncomeChart(); }
});

function fallbackIncomeChart() {
    new Chart(document.getElementById('incomeChart'), { 
        type: 'line', 
        data: { 
            labels: ['H-30','H-25','H-20','H-15','H-10','H-5','Hari Ini'], 
            datasets: [{ 
                label: 'Pendapatan', 
                data: [0,0,0,0,0,0,<?= $income_today ?>], 
                borderColor: '#0d5c1e', 
                fill: true 
            }] 
        } 
    });
}

// Grafik Status Tagihan
new Chart(document.getElementById('statusChart'), { 
    type: 'doughnut', 
    data: { 
        labels: ['Lunas', 'Pending'], 
        datasets: [{ 
            data: [<?= $total_lunas ?>, <?= $total_tagihan ?>], 
            backgroundColor: ['#0d5c1e', '#f9a826'] 
        }] 
    } 
});

refreshPPPoE();

// Notifikasi tiket di sidebar
function updateTicketNotif() {
    $.ajax({ url: 'modules/dashboard/notifikasi_tiket.php', type: 'GET', dataType: 'json',
        success: function(res) {
            if(res.success && res.total > 0) {
                var badge = $('#ticketNotif');
                if(badge.length) { badge.text(res.total); }
                else { $('a[href="/modules/tickets/index.php"]').append('<span class="badge-notif" id="ticketNotif">'+res.total+'</span>'); }
            } else if(res.success && res.total == 0) { $('#ticketNotif').remove(); }
        }
    });
}
setInterval(updateTicketNotif, 60000);
updateTicketNotif();
</script>

<!-- SERVER MONITOR WIDGET -->
<?php include __DIR__ . '/includes/server_widget.php'; ?>

<!-- SERVER MONITOR WIDGET -->

<!-- SERVER MONITOR WIDGET -->

<?php include 'includes/footer.php'; ?>
