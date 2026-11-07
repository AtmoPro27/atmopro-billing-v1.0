<?php
require_once __DIR__ . "/includes/auth_check.php";

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

try {
    require_once __DIR__ . "/config/database.php";
$pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->query("SELECT key_setting, value FROM pengaturan");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key_setting']] = $row['value'];
    }

    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND DATE(tgl_bayar) = CURDATE()");
    $income_today = $stmt->fetch()['total'];
    if ($income_today == 0) $income_today = 0;
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND MONTH(tgl_bayar) = MONTH(CURDATE())");
    $income_month = $stmt->fetch()['total'];
    if ($income_month == 0) $income_month = 0;

   // Ambil dari cache file (update setiap 2 menit oleh cron)
   $pppoe_online = (int) @file_get_contents('/tmp/pppoe_online_count_demo.txt');
   
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $pelanggan_baru = $stmt->fetch()['total'];
    if ($pelanggan_baru == 0) $pelanggan_baru = 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM invoice WHERE status = 'pending'");
    $total_tagihan = $stmt->fetch()['total'];
    if ($total_tagihan == 0) $total_tagihan = 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE status = 'blocked'");
    $isolir_count = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelanggan WHERE status = 'active'");
    $total_customers = $stmt->fetch()['total'];

} catch(PDOException $e) {
    $settings = [];
    // $income_today = 0; # hardcode removed
    // $income_month = 0; # hardcode removed
    $pppoe_online = 0;
    $pelanggan_baru = 0;
    $total_tagihan = 0;
    $isolir_count = 0;
    $total_customers = 0;
}

$currency_symbol = $settings['currency_symbol'] ?? 'Rp';
$current_month = date('F Y');

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-md-3 col-6 mb-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #0d5c1e, #1a8a2d); cursor: pointer;" onclick="showDetail('income')">
                <div class="card-body">
                    <i class="fas fa-money-bill-wave fa-2x mb-2"></i>
                    <h3 class="mb-0"><?= $currency_symbol ?> <?= number_format($income_today, 0, ',', '.') ?></h3>
                    <small>Income Hari Ini</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6 mb-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #1a2980, #26d0ce); cursor: pointer;" onclick="showDetail('income_month')">
                <div class="card-body">
                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                    <h3 class="mb-0"><?= $currency_symbol ?> <?= number_format($income_month, 0, ',', '.') ?></h3>
                    <small>Income Bulan Ini</small>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6 mb-3">
            <div class="card text-white" style="background: linear-gradient(135deg, #e65c00, #f9a826); cursor: pointer;" onclick="showDetail('pppoe')">
                <div class="card-body">
                    <i class="fas fa-wifi fa-2x mb-2"></i>
                    <h3 class="mb-0" id="pppoeCount"><?= number_format($pppoe_online) ?></h3>
                    <small>Users PPPoE Online</small>
                    <div class="mt-2"><i class="fas fa-sync-alt" style="cursor:pointer;" onclick="event.stopPropagation(); refreshPPPoE()"></i></div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6 mb-3">
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
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Grafik Pendapatan 30 Hari</div>
                <div class="card-body"><canvas id="incomeChart" style="height:250px;"></canvas></div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Status Tagihan</div>
                <div class="card-body"><canvas id="statusChart" style="height:250px;"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Pelanggan Isolir</div>
                <div class="card-body">
                    <h3 class="text-danger"><?= number_format($isolir_count) ?></h3>
                    <p>Pelanggan yang sedang diisolir</p>
                    <a href="/modules/customers/index.php" class="btn btn-sm btn-primary">Lihat</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">Ringkasan Bulan Ini</div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><td>Total Pelanggan</td><td class="text-end"><strong><?= $total_customers ?></strong></td></tr>
                        <tr><td>Tagihan Pending</td><td class="text-end"><strong><?= $total_tagihan ?></strong></td></tr>
                        <tr><td>PPPoE Online</td><td class="text-end"><strong><?= $pppoe_online ?></strong></td></tr>
                        <tr><td>Pelanggan Baru</td><td class="text-end"><strong><?= $pelanggan_baru ?></strong></td></tr>
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
new Chart(document.getElementById('incomeChart'), { type: 'line', data: { labels: ['H-30','H-25','H-20','H-15','H-10','H-5','Hari Ini'], datasets: [{ label: 'Pendapatan', data: [0,0,0,0,0,0,<?= $income_today ?>], borderColor: '#0d5c1e', fill: true }] } });
new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: { labels: ['Lunas','Pending'], datasets: [{ data: [65,<?= $total_tagihan ?>], backgroundColor: ['#0d5c1e','#f9a826'] }] } });
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

<?php include 'includes/footer.php'; ?>
