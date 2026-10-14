<?php
require_once __DIR__ . "/../includes/session_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

include '../includes/header_portal.php';

date_default_timezone_set('Asia/Jakarta');

// Ambil jadwal sholat
include_once '../includes/get_jadwal_sholat.php';

$hari_indo = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$nama_hari = $hari_indo[date('l')];
$tanggal = date('d-m-Y');
?>

<!-- BOX PENGUMUMAN -->
<div class="row mt-2">
    <div class="col-md-12 mb-3">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-bullhorn"></i> 📢 PENGUMUMAN
            </div>
            <div class="card-body py-2">
                <div class="row align-items-center">
                    <div class="col-md-1 text-center">
                        <i class="fas fa-volume-up" style="font-size: 28px; color: #ff9800;"></i>
                    </div>
                    <div class="col-md-11">
                        <strong>⚠️ INFO PENTING!</strong><br>
                        Jadwal pembayaran tagihan bulan Juni 2026 diperpanjang hingga <strong>15 Juni 2026</strong>.<br>
                        Hubungi CS di <strong>0812-3456-7890</strong> jika ada kendala.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Box Informasi Waktu & Jadwal Sholat -->
<div class="row mt-2">
    <div class="col-md-12 mb-3">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-calendar-alt"></i> JANGAN LUPA SHOLAT
            </div>
            <div class="card-body py-2">
                <div class="row text-center">
                    <div class="col-md-2 mb-2">
                        <div class="rounded p-2 text-white" style="background-color: #28a745;">
                            <i class="fas fa-calendar-day"></i><br>
                            <strong><?= $nama_hari ?></strong><br>
                            <small><?= $tanggal ?></small>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="rounded p-2 text-white" style="background-color: #003366;">
                            <i class="fas fa-clock"></i><br>
                            <strong id="jam_sekarang"><?= date('H:i:s') ?></strong><br>
                            <small>WIB</small>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-3 col-md-2"><small>Imsak</small><br><strong><?= $sholat_today['imsak'] ?></strong></div>
                            <div class="col-3 col-md-2"><small>Subuh</small><br><strong><?= $sholat_today['subuh'] ?></strong></div>
                            <div class="col-3 col-md-2"><small>Dzuhur</small><br><strong><?= $sholat_today['dzuhur'] ?></strong></div>
                            <div class="col-3 col-md-2"><small>Ashar</small><br><strong><?= $sholat_today['ashar'] ?></strong></div>
                            <div class="col-3 col-md-2"><small>Maghrib</small><br><strong><?= $sholat_today['maghrib'] ?></strong></div>
                            <div class="col-3 col-md-2"><small>Isya</small><br><strong><?= $sholat_today['isya'] ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateJam() {
    var now = new Date();
    document.getElementById('jam_sekarang').innerHTML = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0') + ':' + now.getSeconds().toString().padStart(2,'0');
}
setInterval(updateJam, 1000);
</script>

<?php
try {
    require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->prepare("
        SELECT p.*, pk.nama_paket, pk.harga, pk.profil_ppp, pk.router_id,
               r.nama_router, r.ip_address, r.username, r.password, r.port_api,
               COALESCE(pt.total_bytes, 0) as total_traffic
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        LEFT JOIN routers r ON pk.router_id = r.id
        LEFT JOIN pelanggan_traffic pt ON p.id = pt.pelanggan_id AND pt.bulan = CASE WHEN DAY(NOW()) >= 21 THEN DATE_FORMAT(NOW(), '%Y-%m') ELSE DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m') END
        WHERE p.id_pelanggan = ?
    ");
    $stmt->execute([$_SESSION['pelanggan_kode']]);
    $pelanggan = $stmt->fetch();

    $status_badge = ($pelanggan['status'] == 'active') ? '<span class="badge bg-success">AKTIF</span>' : '<span class="badge bg-danger">ISOLIR</span>';

    $total_bytes = $pelanggan['total_traffic'];
    if ($total_bytes >= 1024 * 1024 * 1024) {
        $traffic_display = number_format($total_bytes / (1024 * 1024 * 1024), 2) . ' GB';
    } elseif ($total_bytes >= 1024 * 1024) {
        $traffic_display = number_format($total_bytes / (1024 * 1024), 2) . ' MB';
    } elseif ($total_bytes >= 1024) {
        $traffic_display = number_format($total_bytes / 1024, 2) . ' KB';
    } else {
        $traffic_display = $total_bytes . ' Bytes';
    }

    $mikrotik_data = ['mac' => '-', 'ip_address' => '-', 'uptime' => '-', 'status' => 'Offline'];
    if ($pelanggan['router_id'] && $pelanggan['ip_address']) {
        require_once __DIR__ . '/../includes/routeros_api_new.php';
        $API = new RouterosAPI();
        $API->debug = false;
        $API->timeout = 1;
        $API->port = (int)($pelanggan['port_api'] ?? 8730);
        if ($API->connect($pelanggan['ip_address'], $pelanggan['username'], $pelanggan['password'])) {
            $API->write('/ppp/active/print', false);
            $API->write('?name=' . $pelanggan['id_pelanggan']);
            $actives = $API->read();
            $API->disconnect();
            if (!empty($actives)) {
                $active = $actives[0];
                $mikrotik_data['mac'] = $active['caller-id'] ?? '-';
                $mikrotik_data['ip_address'] = $active['address'] ?? '-';
                $uptime_raw = $active['uptime'] ?? '-';
                $uptime_raw = preg_replace('/(\d+)([dhms])/', '$1$2 ', $uptime_raw);
                $uptime_raw = str_replace(['d','h','m','s'], [' Hr ',' J ',' M ',' Dt '], $uptime_raw);
                $uptime_raw = preg_replace('/\s+/', ' ', $uptime_raw);
                $mikrotik_data['uptime'] = trim($uptime_raw);
                $mikrotik_data['status'] = 'Online';
            }
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE pelanggan_id = ? ORDER BY id DESC LIMIT 3");
    $stmt->execute([$pelanggan['id']]);
    $invoices = $stmt->fetchAll();

    $tagihan_total = array_sum(array_column($invoices, 'jumlah'));
    $tagihan_pending = 0;
    $tagihan_lunas = 0;
    foreach ($invoices as $inv) {
        if ($inv['status'] == 'pending') $tagihan_pending += $inv['jumlah'];
        else $tagihan_lunas += $inv['jumlah'];
    }

    // Ambil nomor WA kasir
    $stmt_kasir = $pdo->prepare("SELECT phone FROM users WHERE role = 'kasir' AND is_active = 1 LIMIT 1");
    $stmt_kasir->execute();
    $kasir = $stmt_kasir->fetch();
    $wa_kasir = $kasir ? $kasir['phone'] : '628000000000';

    // Tiket
    $stmt_tiket = $pdo->prepare("SELECT * FROM tickets WHERE pelanggan_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt_tiket->execute([$pelanggan['id']]);
    $tickets = $stmt_tiket->fetchAll();

} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-user-circle"></i> Data Diri & Paket
                    <a href="profil.php" class="btn btn-sm btn-light float-end">Edit Profil</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> <?= $_SESSION['pelanggan_kode'] ?></p>
                            <p><strong>Nama:</strong> <?= htmlspecialchars($_SESSION['pelanggan_nama']) ?></p>
                            <p><strong>Alamat:</strong> <?= nl2br(htmlspecialchars(substr($_SESSION['pelanggan_alamat'], 0, 50))) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Paket:</strong> <?= htmlspecialchars($pelanggan['nama_paket'] ?? '-') ?></p>
                            <p><strong>Harga:</strong> Rp <?= number_format($pelanggan['harga'] ?? 0, 0, ',', '.') ?></p>
                            <p><strong>Jatuh Tempo:</strong> <?= date('d-m-Y', strtotime($pelanggan['jatuh_tempo'])) ?></p>
                            <p><strong>Status:</strong> <?= $status_badge ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-chart-line"></i> Informasi Trafik & Koneksi
                </div>
                <div class="card-body text-center">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <i class="fas fa-ethernet"></i> MAC Address<br>
                            RAHASIA
                        </div>
                        <div class="col-6 mb-2">
                            <i class="fas fa-network-wired"></i> IP Address<br>
                            RAHASIA
                        </div>
                        <div class="col-6 mb-2">
                            <i class="fas fa-plug"></i> Status<br>
                            <?php if ($mikrotik_data['status'] == 'Online'): ?>
                                <span class="badge bg-success">ONLINE</span>
                            <?php else: ?>
                                <span class="badge bg-danger">OFFLINE</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 mb-2">
                            <i class="fas fa-clock"></i> Waktu Online<br>
                            <strong><?= $mikrotik_data['uptime'] ?></strong>
                        </div>
                        <div class="col-12 mt-2">
                            <i class="fas fa-database"></i> Quota Terpakai<br>
                            <h5><?= $traffic_display ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card h-100 border-warning">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-ticket-alt"></i> TIKET
                    <a href="tiket.php" class="btn btn-sm btn-dark float-end">Buat Tiket +</a>
                </div>
                <div class="card-body">
                    <?php if (count($tickets) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($tickets as $ticket): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?= htmlspecialchars($ticket['subject']) ?></strong><br>
                                            <small><?= date('d-m-Y', strtotime($ticket['created_at'])) ?></small>
                                        </div>
                                        <div>
                                            <?php if ($ticket['status'] == 'open'): ?>
                                                <span class="badge bg-danger">Open</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Closed</span>
                                            <?php endif; ?>
                                            <a href="tiket.php?detail=<?= $ticket['id'] ?>" class="btn btn-sm btn-info ms-2">Detail</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Belum ada tiket</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center bg-white">
                    <a href="tiket.php" class="text-decoration-none">Lihat Semua Tiket</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-file-invoice"></i> TAGIHAN
                    <a href="tagihan.php" class="btn btn-sm btn-light float-end">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <small>Total</small>
                            <h5 class="text-primary">Rp <?= number_format($tagihan_total, 0, ',', '.') ?></h5>
                        </div>
                        <div class="col-4">
                            <small>Pending</small>
                            <h5 class="text-warning">Rp <?= number_format($tagihan_pending, 0, ',', '.') ?></h5>
                        </div>
                        <div class="col-4">
                            <small>Lunas</small>
                            <h5 class="text-success">Rp <?= number_format($tagihan_lunas, 0, ',', '.') ?></h5>
                        </div>
                    </div>

                    <?php if ($tagihan_pending > 0): ?>
                        <div class="text-center mt-3">
                            <a href="tiket.php" class="btn btn-primary">
                                <i class="fas fa-credit-card"></i> BAYAR SEKARANG
                            </a>
                            <small class="text-muted d-block">Klik untuk ke halaman Tiket</small>
                        </div>
                    <?php endif; ?>

                    <?php if (count($invoices) > 0): ?>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr><th>No Invoice</th><th>Jumlah</th><th>Jatuh Tempo</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $inv): ?>
                                        <tr class="<?= $inv['status'] == 'pending' ? 'table-warning' : ($inv['status'] == 'paid' ? 'table-success' : '') ?>">
                                            <td><?= $inv['no_invoice'] ?></td>
                                            <td>Rp <?= number_format($inv['jumlah'], 0, ',', '.') ?></td>
                                            <td><?= date('d-m-Y', strtotime($inv['tgl_jatuh_tempo'])) ?></td>
                                            <td><?= $inv['status'] == 'paid' ? '<span class="badge bg-success">Lunas</span>' : '<span class="badge bg-warning">Pending</span>' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Pembayaran -->
    <div class="row mt-3">
        <div class="col-md-12 mb-3">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-credit-card"></i> Informasi Pembayaran
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Transfer ke rekening berikut:</strong></p>
                            <?php
                            $stmt_rek = $pdo->prepare("SELECT bank_name, account_number, account_name FROM payment_settings WHERE is_active = 1");
                            $stmt_rek->execute();
                            $rekening = $stmt_rek->fetchAll();

                            if (count($rekening) > 0) {
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-sm table-borderless">';
                                foreach ($rekening as $rek) {
                                    echo '<tr>';
                                    echo '<td width="100"><i class="fas fa-university"></i> ' . htmlspecialchars($rek['bank_name']) . '</td>';
                                    echo '<td><strong>' . htmlspecialchars($rek['account_number']) . '</strong></td>';
                                    echo '<td>a.n. ' . htmlspecialchars($rek['account_name']) . '</td>';
                                    echo '</tr>';
                                }
                                echo '</table></div>';
                            } else {
                                echo '<div class="alert alert-warning">Data rekening belum tersedia. Silakan hubungi admin.</div>';
                            }
                            ?>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-info-circle"></i> <strong>Setelah transfer:</strong><br>
                                Silakan kirim bukti transfer melalui
                                <a href="tiket.php" class="alert-link">Tiket Pembayaran</a> atau
                                <a href="https://wa.me/<?= $wa_kasir ?>" class="alert-link" target="_blank">WhatsApp Admin</a>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer_portal.php'; ?>
