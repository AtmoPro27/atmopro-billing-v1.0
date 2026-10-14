<?php
session_start();
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

include '../includes/header_portal.php';
require_once '../includes/genieacs_functions.php';
require_once '../config/database.php';
$pdo = Database::getInstance()->getConnection();

date_default_timezone_set('Asia/Jakarta');

include_once '../includes/get_jadwal_sholat.php';
include_once '../includes/get_pengumuman.php';

// Handle AJAX update WiFi dari portal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $deviceId = $_POST['device_id'] ?? '';
    $parameter = $_POST['parameter'] ?? '';
    $value = $_POST['value'] ?? '';
    $pelangganId = $_POST['pelanggan_id'] ?? '';
    
    if (empty($deviceId) || empty($parameter)) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
        exit;
    }
    
    try {
        $mongo = new MongoDB\Driver\Manager("mongodb://localhost:27017");
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->update(
            ['_id' => $deviceId],
            ['$set' => ["parameters.$parameter" => $value]],
            ['multi' => false, 'upsert' => true]
        );
        $result = $mongo->executeBulkWrite('genieacs.devices', $bulk);
        
        if ($result->getModifiedCount() > 0 || $result->getUpsertedCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Berhasil diupdate']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update. Device tidak ditemukan.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

$hari_indo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$nama_hari = $hari_indo[date('l')];
$tanggal = date('d-m-Y');

try {
    $stmt = $pdo->prepare("
        SELECT p.*, pk.nama_paket, pk.harga, pk.profil_ppp, pk.router_id,
               r.nama_router, r.ip_address, r.username, r.password,
               COALESCE(pt.total_bytes, 0) as total_traffic, p.total_online_seconds
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        LEFT JOIN routers r ON pk.router_id = r.id
        LEFT JOIN pelanggan_traffic pt ON p.id = pt.pelanggan_id AND pt.bulan = CASE WHEN DAY(NOW()) >= 21 THEN DATE_FORMAT(NOW(), '%Y-%m') ELSE DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m') END
        WHERE p.id_pelanggan = ?
    ");
    $stmt->execute([$_SESSION['pelanggan_kode']]);
    $pelanggan = $stmt->fetch();

    if (!$pelanggan) {
        die("Data pelanggan tidak ditemukan.");
    }

    $genieacs_data = getDeviceDetailByCustomerId($pelanggan['id_pelanggan']);
    
    $genieacs_ssid = '-';
    $genieacs_password = '******';
    $device_status = 'Tidak terdeteksi';
    $device_status_class = 'secondary';
    $genieacs_device_id = '';
    $rx_power_display = '-';
    $rx_status_class = 'secondary';
    $rx_status_text = '';

    if ($genieacs_data && $genieacs_data['found']) {
        $genieacs_ssid = $genieacs_data['ssid'] ?? '-';
        if (isset($genieacs_data['wifi_password']) && !empty($genieacs_data['wifi_password']) && $genieacs_data['wifi_password'] != '******') {
            $genieacs_password = $genieacs_data['wifi_password'];
        }
        $device_status = $genieacs_data['status'] ?? 'Offline';
        $device_status_class = ($device_status == 'Online') ? 'success' : 'danger';
        $genieacs_device_id = $genieacs_data['device_id'] ?? '';
        
        if (isset($genieacs_data['rx_power']) && $genieacs_data['rx_power'] != '-') {
            $rx_power_display = $genieacs_data['rx_power'];
            $rx_status_class = getRxPowerColor($rx_power_display);
            $rx_status_text = getRxPowerStatus($rx_power_display);
        }
    }

    $status_badge = ($pelanggan['status'] == 'active') ? '<span class="badge bg-success">AKTIF</span>' : '<span class="badge bg-danger">ISOLIR</span>';

    $total_bytes = $pelanggan['total_traffic'];
    if ($total_bytes >= 1024*1024*1024) {
        $traffic_display = number_format($total_bytes / (1024*1024*1024), 2) . ' GB';
    } elseif ($total_bytes >= 1024*1024) {
        $traffic_display = number_format($total_bytes / (1024*1024), 2) . ' MB';
    } elseif ($total_bytes >= 1024) {
        $traffic_display = number_format($total_bytes / 1024, 2) . ' KB';
    } else {
        $traffic_display = $total_bytes . ' Bytes';
    }

    $total_online_seconds = $pelanggan['total_online_seconds'] ?? 0;
    $days = floor($total_online_seconds / 86400);
    $hours = floor(($total_online_seconds % 86400) / 3600);
    $minutes = floor(($total_online_seconds % 3600) / 60);
    $seconds = $total_online_seconds % 60;
    $waktu_akumulasi = '';
    if ($days > 0) $waktu_akumulasi .= $days . ' Hr : ';
    if ($hours > 0) $waktu_akumulasi .= $hours . ' J : ';
    if ($minutes > 0) $waktu_akumulasi .= $minutes . ' M : ';
    if ($seconds > 0) $waktu_akumulasi .= $seconds . ' Dt ';
    if (empty($waktu_akumulasi)) $waktu_akumulasi = ' 0Dt ';

    $mikrotik_data = ['mac' => '-', 'ip_address' => '-', 'uptime' => '-', 'status' => 'Offline'];
    // ============================================
    // AMBIL DATA MIKROTIK VIA API (bukan SSH)
    // ============================================
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
            
            if (!empty($actives) && isset($actives[0])) {
                $active = $actives[0];
                $mikrotik_data['mac'] = $active['caller-id'] ?? '-';
                $mikrotik_data['ip_address'] = $active['address'] ?? '-';
                $mikrotik_data['uptime'] = $active['uptime'] ?? '-';
                $mikrotik_data['status'] = 'Online';
            }
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM invoice WHERE pelanggan_id = ? ORDER BY id DESC LIMIT 3");
    $stmt->execute([$pelanggan['id']]);
    $invoices = $stmt->fetchAll();
    // Cek PG aktif
    $stmt_pg = $pdo->query("SELECT name FROM payment_gateway WHERE is_active = 1 LIMIT 1");
    $active_pg = $stmt_pg->fetch();

    $tagihan_total = array_sum(array_column($invoices, 'jumlah'));
    $tagihan_pending = 0;
    $tagihan_lunas = 0;
    foreach ($invoices as $inv) {
        if ($inv['status'] == 'pending') $tagihan_pending += $inv['jumlah'];
        else $tagihan_lunas += $inv['jumlah'];
    }

    $invoice_pending = null;
    foreach ($invoices as $inv) {
        if ($inv['status'] == 'pending') {
            $invoice_pending = $inv;
            break;
        }
    }

    $stmt_kasir = $pdo->prepare("SELECT phone FROM users WHERE role = 'kasir' AND is_active = 1 LIMIT 1");
    $stmt_kasir->execute();
    $kasir = $stmt_kasir->fetch();
    $wa_kasir = $kasir ? $kasir['phone'] : '628000000000';

    $stmt_tiket_bayar = $pdo->prepare("SELECT * FROM tickets WHERE pelanggan_id = ? AND subject LIKE '%Pembayaran%' ORDER BY created_at DESC LIMIT 5");
    $stmt_tiket_bayar->execute([$pelanggan['id']]);
    $tickets_pembayaran = $stmt_tiket_bayar->fetchAll();

    $stmt_tiket_pengaduan = $pdo->prepare("SELECT * FROM tickets WHERE pelanggan_id = ? AND subject NOT LIKE '%Pembayaran%' ORDER BY created_at DESC LIMIT 5");
    $stmt_tiket_pengaduan->execute([$pelanggan['id']]);
    $tickets_pengaduan = $stmt_tiket_pengaduan->fetchAll();

} catch(PDOException $e) {
    $error = $e->getMessage();
}

function getRxPowerColor($rxPower) {
    $value = floatval(preg_replace("/[^0-9.-]/", "", $rxPower));
    if (!is_numeric($value) || $value == 0) return "secondary";
    if ($value >= -18) return "primary";
    if ($value >= -24) return "success";
    return "danger";
}

function getRxPowerStatus($rxPower) {
    $value = floatval(preg_replace("/[^0-9.-]/", "", $rxPower));
    if (!is_numeric($value) || $value == 0) return "Tidak tersedia";
    if ($value >= -18) return "Bagus";
    if ($value >= -24) return "Sedang";
    return "Kritis";
}

function getStatusBadge($status) {
    if ($status == 'open') return '<span class="badge bg-danger">Open</span>';
    if ($status == 'in_progress') return '<span class="badge bg-warning">In Progress</span>';
    if ($status == 'resolved') return '<span class="badge bg-success">Resolved</span>';
    return '<span class="badge bg-secondary">Closed</span>';
}
?>

<style>
    .wifi-info-card {
        background-color: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 10px 15px;
        margin-bottom: 10px;
    }
    .wifi-info-card strong { color: #856404; }
    .password-visible {
        font-family: monospace;
        font-weight: bold;
        color: #0d5c1e;
        font-size: 16px;
    }
    .edit-wifi-btn { margin-left: 8px; cursor: pointer; }
    .wifi-input {
        width: 100%;
        padding: 6px 10px;
        border: 2px solid #ffc107;
        border-radius: 5px;
        font-size: 14px;
    }
    .wifi-input:focus {
        outline: none;
        border-color: #ff9800;
        box-shadow: 0 0 5px rgba(255,152,0,0.3);
    }
    .btn-save-wifi {
        background: #28a745;
        color: white;
        border: none;
        padding: 5px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
    }
    .btn-save-wifi:hover { background: #218838; }
    .btn-cancel-wifi {
        background: #6c757d;
        color: white;
        border: none;
        padding: 5px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
    }
    .btn-cancel-wifi:hover { background: #5a6268; }
    .saving-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #ccc;
        border-top-color: #28a745;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
        margin-left: 5px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .rx-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 13px;
    }
    .bg-primary { background-color: #0066cc !important; color: white; }
    .bg-success { background-color: #28a745 !important; color: white; }
    .bg-danger { background-color: #dc3545 !important; color: white; }
    .bg-secondary { background-color: #6c757d !important; color: white; }
    .info-box {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px 10px;
        background: #f8f9fa;
        height: 100%;
        text-align: center;
        min-height: 100px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .info-box i { font-size: 32px; margin-bottom: 8px; }
    .info-box .label { font-size: 12px; color: #6c757d; margin-bottom: 4px; }
    .info-box .value { font-weight: bold; font-size: 15px; }
    .device-table { font-size: 12px; }
    .device-table th { font-size: 11px; padding: 4px 6px; }
    .device-table td { padding: 4px 6px; }
</style>

<!-- BARIS 1: PENGUMUMAN -->
<div class="row mt-2">
    <div class="col-12 mb-3">
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
                        <?php if (!empty($pengumuman['isi'])): ?>
                            <?= nl2br(htmlspecialchars($pengumuman['isi'])) ?>
                        <?php elseif ($tagihan_pending > 0): ?>
                            <div style="background-color: #ff9800; padding: 10px; border-radius: 5px; color: #000;">
                                <strong>📋 TAGIHAN BARU</strong><br>
                                Anda memiliki tagihan pending sebesar <strong>Rp <?= number_format($tagihan_pending, 0, ',', '.') ?></strong><br>
                                <a href="tagihan.php" style="color: #fff; font-weight: bold;">→ Klik di sini untuk lihat dan bayar</a>
                            </div>
                        <?php else: ?>
                            <div style="color: #28a745; font-weight: bold;">✅ SEMUA BERJALAN NORMAL</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- BARIS 1.5: SPEED TEST (OpenSpeedTest Publik)   -->
<!-- ============================================== -->
<div class="row mt-2">
    <div class="col-12 mb-2">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white py-2 d-flex justify-content-between align-items-center">
                <span style="font-size:14px;">
                    <i class="fas fa-tachometer-alt"></i> SPEED TEST INTERNET
                </span>
                <button class="btn btn-sm btn-light py-0 px-2"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#speedtestCollapse"
                        aria-expanded="false">
                    <i class="fas fa-chevron-down"></i> Buka
                </button>
            </div>
            <div class="collapse" id="speedtestCollapse">
                <div class="card-body p-2">
                    <iframe src="https://openspeedtest.com/speedtest"
                            style="width:100%; height:600px; border:none; border-radius:6px;"
                            loading="lazy"
                            allow="fullscreen">
                    </iframe>

                    <div class="mt-2 p-2" style="background:#fff3cd; border-radius:6px; font-size:11px;">
                        <i class="fas fa-lightbulb" style="color:#856404;"></i>
                        <strong>Tips:</strong>
                        <ul style="margin:4px 0 0 16px; padding:0;">
                            <li>Untuk hasil akurat, gunakan <strong>kabel LAN</strong></li>
                            <li>WiFi wajar lebih lambat <strong>30-50%</strong></li>
                            <li>Tutup aplikasi lain saat tes</li>
                            <li>Ping &lt;50 ms = bagus untuk video call</li>
                            <li>Jitter &lt;5 ms = koneksi stabil</li>
                        </ul>
                    </div>

                    <div class="text-center mt-2">
                        <small class="text-muted" style="font-size:10px;">
                            <i class="fas fa-info-circle"></i>
                            Hasil dari server OpenSpeedTest (independen), bukan server Demo Company.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BARIS 2: JADWAL SHOLAT -->
<div class="row mt-2">
    <div class="col-12 mb-3">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-calendar-alt"></i> JANGAN LUPA SHOLAT
            </div>
            <div class="card-body py-2">
                <div class="row text-center">
                    <div class="col-md-2 mb-2">
                        <div class="rounded p-2 text-white" style="background-color: #28a745;">
                            <i class="fas fa-calendar-day"></i><br><strong><?= $nama_hari ?></strong><br><small><?= $tanggal ?></small>
                        </div>
                    </div>
                    <div class="col-md-2 mb-2">
                        <div class="rounded p-2 text-white" style="background-color: #003366;">
                            <i class="fas fa-clock"></i><br><strong id="jam_sekarang"><?= date('H:i:s') ?></strong><br><small>WIB</small>
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

<div class="container mt-4">

    <!-- BARIS 3: DATA DIRI | TAGIHAN -->
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
                    <?php if ($tagihan_pending > 0 && $invoice_pending): ?>
                        <div class="text-center mt-3">
                            <?php if ($active_pg): ?>
                                <a href="pilih_metode.php?id=<?= $invoice_pending['id'] ?>" class="btn btn-success btn-sm mb-1">
                                    <i class="fas fa-credit-card"></i> Bayar Online (<?= htmlspecialchars($active_pg['name']) ?>)
                                </a>
                                <br>
                                <a href="tiket_pembayaran.php?no_invoice=<?= urlencode($invoice_pending['no_invoice']) ?>&jumlah=<?= urlencode($invoice_pending['jumlah']) ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-paper-plane"></i> Kirim Bukti Bayar
                                </a>
                            <?php else: ?>
                                <a href="tiket_pembayaran.php?no_invoice=<?= urlencode($invoice_pending['no_invoice']) ?>&jumlah=<?= urlencode($invoice_pending['jumlah']) ?>" class="btn btn-warning btn-sm">
                                    <i class="fas fa-paper-plane"></i> Kirim Bukti Bayar
                                </a>
                            <?php endif; ?>
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

    <!-- BARIS 4: TRAFIK | WIFI (BERDAMPINGAN) -->
    <div class="row">
        <!-- INFORMASI TRAFIK -->
        <div class="col-md-6 mb-3">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-chart-line"></i> Informasi Trafik & Koneksi
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-2 text-center">
                            <div class="info-box">
                                <i class="fas fa-server text-primary"></i>
                                <div class="label">Status ONT</div>
                                <div class="value"><span class="badge bg-<?= $device_status_class ?>"><?= $device_status ?></span></div>
                            </div>
                        </div>
                        <div class="col-6 mb-2 text-center">
                            <div class="info-box">
                                <i class="fas fa-plug text-success"></i>
                                <div class="label">Status PPPoE</div>
                                <div class="value">
                                    <?php if ($mikrotik_data['status'] == 'Online'): ?>
                                        <span class="badge bg-success">ONLINE</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">OFFLINE</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 mb-2 text-center">
                            <div class="info-box">
                                <i class="fas fa-chart-simple text-info"></i>
                                <div class="label">Quota Terpakai</div>
                                <div class="value"><?= $traffic_display ?></div>
                            </div>
                        </div>
                        <div class="col-6 mb-2 text-center">
                            <div class="info-box">
                                <i class="fas fa-clock text-warning"></i>
                                <div class="label">Waktu Online</div>
                                <div class="value" style="font-size:12px;"><?= $waktu_akumulasi ?></div>
                            </div>
                        </div>
                        <div class="col-12 mb-2 text-center">
                            <div class="info-box">
                                <i class="fas fa-signal text-info"></i>
                                <div class="label">RX Power (ONT)</div>
                                <div class="value">
                                    <span class="rx-badge bg-<?= $rx_status_class ?>">
                                        <?= htmlspecialchars($rx_power_display) ?>
                                        <?php if ($rx_status_text): ?>
                                            (<?= $rx_status_text ?>)
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PENGATURAN WIFI -->
        <div class="col-md-6 mb-3">
            <div class="card h-100 border-warning">
                <div class="card-header bg-warning text-dark">
                    <i class="fas fa-wifi"></i> PENGATURAN WIFI
                    <span class="badge bg-<?= $device_status_class ?> float-end">
                        <?= $device_status ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($genieacs_data && $genieacs_data['found'] && !empty($genieacs_device_id)): ?>
                        <div class="wifi-info-card">
                            <strong><i class="fas fa-wifi"></i> SSID (Nama WiFi)</strong>
                            <div class="mt-1" id="portal_ssid_container">
                                <span class="fs-5" id="portal_ssid_display"><?= htmlspecialchars($genieacs_ssid) ?></span>
                                <?php if ($device_status == 'Online'): ?>
                                <button class="btn btn-sm btn-warning edit-wifi-btn" onclick="portalEditWifi('ssid')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div id="portal_ssid_edit" style="display:none;" class="mt-2">
                                <input type="text" class="wifi-input" id="portal_ssid_input" value="<?= htmlspecialchars($genieacs_ssid) ?>" placeholder="Masukkan SSID baru">
                                <div class="mt-2">
                                    <button class="btn-save-wifi" onclick="portalSaveWifi('ssid')"><i class="fas fa-save"></i> Simpan</button>
                                    <button class="btn-cancel-wifi" onclick="portalCancelWifi('ssid')">Batal</button>
                                    <span id="portal_ssid_status" style="margin-left:10px;"></span>
                                </div>
                            </div>
                        </div>

                        <div class="wifi-info-card">
                            <strong><i class="fas fa-lock"></i> Password WiFi</strong>
                            <div class="mt-1" id="portal_password_container">
                                <span class="password-visible" id="portal_password_display"><?= htmlspecialchars($genieacs_password) ?></span>
                                <?php if ($device_status == 'Online'): ?>
                                <button class="btn btn-sm btn-warning edit-wifi-btn" onclick="portalEditWifi('password')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div id="portal_password_edit" style="display:none;" class="mt-2">
                                <input type="text" class="wifi-input" id="portal_password_input" value="<?= htmlspecialchars($genieacs_password != '******' ? $genieacs_password : '') ?>" placeholder="Masukkan password baru (min 8 karakter)">
                                <div class="mt-2">
                                    <button class="btn-save-wifi" onclick="portalSaveWifi('password')"><i class="fas fa-save"></i> Simpan</button>
                                    <button class="btn-cancel-wifi" onclick="portalCancelWifi('password')">Batal</button>
                                    <span id="portal_password_status" style="margin-left:10px;"></span>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- PERANGKAT TERHUBUNG -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong><i class="fas fa-mobile-alt"></i> Perangkat Terhubung</strong>
                                <button class="btn btn-sm btn-outline-secondary" onclick="portalLoadDevices()">
                                    <i class="fas fa-sync"></i> Refresh
                                </button>
                            </div>
                            <div id="portalDeviceListContainer">
                                <div class="text-center text-muted py-2 small">
                                    <div class="spinner-border spinner-border-sm"></div> Memuat...
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- REBOOT ONT -->
                        <div class="mb-0">
                            <strong><i class="fas fa-power-off text-danger"></i> Reboot ONT</strong>
                            <p class="text-muted small mb-2 mt-1">
                                ONT akan reboot setelah klik tombol REBOOT SEKARANG. Koneksi terputus sekitar 1-2 menit.
                            </p>
                            <button class="btn btn-sm btn-warning" onclick="portalRebootONT()">
                                <i class="fas fa-sync"></i> Reboot Sekarang
                            </button>
                            <div id="portalRebootStatus" class="mt-2"></div>
                        </div>

                        <?php if ($device_status != 'Online'): ?>
                        <div class="alert alert-warning mt-3 mb-0 small">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Perangkat sedang <strong>Offline</strong>. Perubahan WiFi hanya bisa dilakukan saat perangkat online, setelah dirubah reboot / restart modem anda.
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> 
                            Perangkat belum terdaftar di GenieACS. Hubungi admin untuk pendaftaran perangkat.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- BARIS 5: TIKET GANGGUAN | TIKET PEMBAYARAN -->
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card h-100 border-danger">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-wifi"></i> TIKET GANGGUAN
                    <a href="tiket_gangguan.php" class="btn btn-sm btn-light float-end">Buat Tiket +</a>
                </div>
                <div class="card-body">
                    <?php if (count($tickets_pengaduan) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($tickets_pengaduan as $ticket): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div><strong><?= htmlspecialchars($ticket['subject']) ?></strong><br><small><?= date('d-m-Y', strtotime($ticket['created_at'])) ?></small></div>
                                        <div class="text-end">
                                            <?= getStatusBadge($ticket['status']) ?><br>
                                            <a href="tiket_detail.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-info mt-1">Detail</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Belum ada tiket gangguan</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center bg-white">
                    <a href="tiket_gangguan.php" class="text-decoration-none">Lihat Semua Tiket Gangguan</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card h-100 border-success">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-money-bill-wave"></i> TIKET PEMBAYARAN
                    <a href="tiket_pembayaran.php" class="btn btn-sm btn-light float-end">Buat Tiket +</a>
                </div>
                <div class="card-body">
                    <?php if ($tagihan_pending > 0 && $invoice_pending): ?>
                        <div class="alert alert-warning mb-2">
                            <strong>⚠️ Ada tagihan pending!</strong>
                            <?php if ($active_pg): ?>
                                <a href="pilih_metode.php?id=<?= $invoice_pending['id'] ?>" class="btn btn-sm btn-success mt-1 w-100 mb-1">
                                    <i class="fas fa-credit-card"></i> Bayar Online (<?= htmlspecialchars($active_pg['name']) ?>)
                                </a>
                                <a href="tiket_pembayaran.php?no_invoice=<?= urlencode($invoice_pending['no_invoice']) ?>&jumlah=<?= urlencode($invoice_pending['jumlah']) ?>" class="btn btn-sm btn-warning mt-1 w-100">
                                    <i class="fas fa-paper-plane"></i> Kirim Bukti Bayar
                                </a>
                            <?php else: ?>
                                <a href="tiket_pembayaran.php?no_invoice=<?= urlencode($invoice_pending['no_invoice']) ?>&jumlah=<?= urlencode($invoice_pending['jumlah']) ?>" class="btn btn-sm btn-warning mt-1 w-100">
                                    <i class="fas fa-paper-plane"></i> Kirim Bukti Bayar
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (count($tickets_pembayaran) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($tickets_pembayaran as $ticket): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div><strong><?= htmlspecialchars($ticket['subject']) ?></strong><br><small><?= date('d-m-Y', strtotime($ticket['created_at'])) ?></small></div>
                                        <div class="text-end">
                                            <?= getStatusBadge($ticket['status']) ?><br>
                                            <a href="tiket_detail.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-info mt-1">Detail</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Belum ada tiket pembayaran</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center bg-white">
                    <a href="tiket_pembayaran.php" class="text-decoration-none">Lihat Semua Tiket Pembayaran</a>
                </div>
            </div>
        </div>
    </div>

    <!-- BARIS 6: INFORMASI PEMBAYARAN -->
    <div class="row mt-3">
        <div class="col-md-12 mb-3">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-credit-card"></i> Informasi Pembayaran
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-7">
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
                        <div class="col-md-5">
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-info-circle"></i> <strong>Setelah transfer:</strong><br>
                                <a href="tiket_pembayaran.php" class="alert-link">Kirim bukti melalui Tiket Pembayaran</a> atau
                                <a href="https://wa.me/<?= $wa_kasir ?>" class="alert-link" target="_blank">WhatsApp Admin</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const portalCustomerId = '<?= $pelanggan['id_pelanggan'] ?>';
const portalDeviceId = '<?= $genieacs_device_id ?>';
const portalSsidPath = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID';
const portalPasswordPath = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase';

function portalEditWifi(type) {
    if (type === 'ssid') {
        document.getElementById('portal_ssid_container').style.display = 'none';
        document.getElementById('portal_ssid_edit').style.display = 'block';
        document.getElementById('portal_ssid_input').focus();
    } else {
        document.getElementById('portal_password_container').style.display = 'none';
        document.getElementById('portal_password_edit').style.display = 'block';
        document.getElementById('portal_password_input').focus();
    }
}

function portalCancelWifi(type) {
    if (type === 'ssid') {
        document.getElementById('portal_ssid_container').style.display = 'block';
        document.getElementById('portal_ssid_edit').style.display = 'none';
        document.getElementById('portal_ssid_status').innerHTML = '';
    } else {
        document.getElementById('portal_password_container').style.display = 'block';
        document.getElementById('portal_password_edit').style.display = 'none';
        document.getElementById('portal_password_status').innerHTML = '';
    }
}

function portalSaveWifi(type) {
    let value, parameter, statusId, displayId;
    
    if (type === 'ssid') {
        value = document.getElementById('portal_ssid_input').value.trim();
        parameter = portalSsidPath;
        statusId = 'portal_ssid_status';
        displayId = 'portal_ssid_display';
    } else {
        value = document.getElementById('portal_password_input').value.trim();
        parameter = portalPasswordPath;
        statusId = 'portal_password_status';
        displayId = 'portal_password_display';
        if (value.length > 0 && value.length < 8) {
            document.getElementById(statusId).innerHTML = '<span class="text-danger">Password minimal 8 karakter!</span>';
            return;
        }
    }
    
    if (!value) {
        document.getElementById(statusId).innerHTML = '<span class="text-danger">Nilai tidak boleh kosong!</span>';
        return;
    }
    
    document.getElementById(statusId).innerHTML = '<span class="saving-spinner"></span> Menyimpan...';
    
    const formData = new FormData();
    formData.append('device_id', portalDeviceId);
    formData.append('parameter', parameter);
    formData.append('value', value);
    formData.append('pelanggan_id', '<?= $pelanggan['id'] ?>');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById(statusId).innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Berhasil disimpan!</span>';
            document.getElementById(displayId).textContent = value;
            setTimeout(() => {
                portalCancelWifi(type);
                document.getElementById(statusId).innerHTML = '';
                location.reload();
            }, 1500);
        } else {
            document.getElementById(statusId).innerHTML = '<span class="text-danger">Gagal: ' + data.message + '</span>';
        }
    })
    .catch(error => {
        document.getElementById(statusId).innerHTML = '<span class="text-danger">Error: ' + error.message + '</span>';
    });
}

// ============================================================
// PERANGKAT TERHUBUNG
// ============================================================
function portalLoadDevices() {
    const el = document.getElementById('portalDeviceListContainer');
    if (!el) return;
    
    if (!portalDeviceId) {
        el.innerHTML = '<div class="alert alert-warning mb-0 small">ONT belum terdaftar di GenieACS.</div>';
        return;
    }
    
    el.innerHTML = '<div class="text-center text-muted py-2 small"><div class="spinner-border spinner-border-sm"></div> Memuat...</div>';
    
    fetch('/api/get_connected_devices_portal.php?device_id=' + encodeURIComponent(portalDeviceId))
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                el.innerHTML = '<div class="alert alert-danger mb-0 small">' + data.message + '</div>';
                return;
            }
            if (data.total === 0) {
                el.innerHTML = '<div class="alert alert-info mb-0 small">Tidak ada perangkat terhubung.</div>';
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0 device-table">';
            html += '<thead class="table-light"><tr><th>Nama</th><th>IP</th><th>MAC</th></tr></thead><tbody>';
            data.devices.forEach(d => {
                html += '<tr>';
                html += '<td><i class="fas fa-mobile-alt text-primary"></i> ' + (d.hostname || '-') + '</td>';
                html += '<td><code>' + (d.ip || '-') + '</code></td>';
                html += '<td><small class="text-muted">' + (d.mac || '-') + '</small></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            html += '<small class="text-muted d-block mt-1"><i class="fas fa-check-circle text-success"></i> Total: ' + data.total + ' perangkat</small>';
            el.innerHTML = html;
        })
        .catch(e => {
            el.innerHTML = '<div class="alert alert-danger mb-0 small">Gagal: ' + e.message + '</div>';
        });
}

// ============================================================
// REBOOT ONT
// ============================================================
function portalRebootONT() {
    if (!portalDeviceId) {
        alert('ONT belum terdaftar di GenieACS.');
        return;
    }
    
   if (!confirm('ONT akan reboot setelah klik tombol REBOOT SEKARANG.\n\nKoneksi internet akan terputus sekitar 1-2 menit.\n\nLanjutkan?')) {
        return;
    }
    
    const el = document.getElementById('portalRebootStatus');
    el.innerHTML = '<div class="alert alert-info mb-0 small"><div class="spinner-border spinner-border-sm"></div> Mengirim perintah reboot...</div>';
    
    fetch('/api/reboot_ont_portal.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'device_id=' + encodeURIComponent(portalDeviceId)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            el.innerHTML = '<div class="alert alert-success mb-0 small"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
            setTimeout(() => { el.innerHTML = ''; }, 15000);
        } else {
            el.innerHTML = '<div class="alert alert-danger mb-0 small">❌ ' + data.message + '</div>';
        }
    })
    .catch(e => {
        el.innerHTML = '<div class="alert alert-danger mb-0 small">Error: ' + e.message + '</div>';
    });
}

// Auto-load devices saat halaman siap
document.addEventListener('DOMContentLoaded', function() {
    if (portalDeviceId) {
        portalLoadDevices();
    }
});
</script>

<?php include '../includes/footer_portal.php'; ?>
