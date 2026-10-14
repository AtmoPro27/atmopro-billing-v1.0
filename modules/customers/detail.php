<?php
ob_start();
require_once __DIR__ . "/../../includes/session_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

// ============================================
// HANDLER AJAX - REBOOT ONT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reboot_ont') {
    error_reporting(0);
    ini_set('display_errors', 0);
    require_once __DIR__ . '/../../includes/genieacs_functions.php';
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    
    $deviceId = $_POST['device_id'] ?? '';
    
    if (empty($deviceId)) {
        echo json_encode(['success' => false, 'message' => 'Device ID kosong']);
        exit;
    }
    
    $token = getGenieACSToken();
    if (!$token) {
        echo json_encode(['success' => false, 'message' => 'Gagal ambil token']);
        exit;
    }
    
    $ch = curl_init(GENIEACS_URL . '/devices/' . urlencode($deviceId) . '/tasks?connection_request');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => 'reboot']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode(['success' => true, 'message' => 'Perintah reboot dikirim']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal (HTTP ' . $httpCode . ')']);
    }
    exit;
}

// ============================================
// HANDLER AJAX - GET HOSTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_hosts') {
    error_reporting(0);
    ini_set('display_errors', 0);
    require_once __DIR__ . '/../../includes/genieacs_functions.php';
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    
    $deviceId = $_POST['device_id'] ?? '';
    $hosts = [];
    
    if (!empty($deviceId)) {
        $token = getGenieACSToken();
        if ($token) {
            $ch = curl_init(GENIEACS_URL . '/devices/' . urlencode($deviceId));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if (is_array($data) && isset($data['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'])) {
                $hostList = $data['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'];
                foreach ($hostList as $key => $host) {
                    if (in_array($key, ['_object', '_timestamp', '_writable'])) continue;
                    if (is_array($host) && isset($host['HostName'])) {
                        $hosts[] = [
                            'hostname' => $host['HostName']['_value'] ?? '-',
                            'ip' => $host['IPAddress']['_value'] ?? '-',
                            'mac' => $host['MACAddress']['_value'] ?? '-',
                            'interface' => $host['InterfaceType']['_value'] ?? '-'
                        ];
                    }
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'hosts' => $hosts]);
    exit;
}

include '../../includes/header.php';
require_once '../../includes/genieacs_functions.php';
require_once '../../config/database.php';

// Fungsi RX Power
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

// Handle AJAX untuk update WiFi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Bersihkan semua output buffer
    while (ob_get_level()) ob_end_clean();
    ob_clean();
    header('Content-Type: application/json');

    $deviceId = $_POST['device_id'] ?? '';
    $parameter = $_POST['parameter'] ?? '';
    $value = $_POST['value'] ?? '';
    $customerId = $_POST['customer_id'] ?? '';
    $type = $_POST['type'] ?? '';

    if (empty($deviceId) || empty($parameter)) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
        exit;
    }

    $result = updateDeviceParameter($deviceId, $parameter, $value);

    if ($result) {
        if (!empty($customerId) && $type == 'password') {
            try {
                $pdo = Database::getInstance()->getConnection();
                $stmt = $pdo->prepare("UPDATE pelanggan SET wifi_password = ? WHERE id_pelanggan = ?");
                $stmt->execute([$value, $customerId]);
            } catch(PDOException $e) {}
        }
        echo json_encode(['success' => true, 'message' => 'Berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update. Pastikan device online.']);
    }
    exit;
}

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare("
    SELECT p.*, pk.nama_paket, pk.harga, pk.profil_ppp, pk.router_id,
           r.nama_router, r.ip_address as router_ip, r.username as router_user, r.password as router_pass, r.port_api,
           COALESCE(pt.total_bytes, 0) as total_traffic
    FROM pelanggan p
    LEFT JOIN paket_internet pk ON p.paket_id = pk.id
    LEFT JOIN routers r ON pk.router_id = r.id
    LEFT JOIN pelanggan_traffic pt ON p.id = pt.pelanggan_id AND pt.bulan = CASE WHEN DAY(NOW()) >= 21 THEN DATE_FORMAT(NOW(), '%Y-%m') ELSE DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m') END
    WHERE p.id_pelanggan = ?
");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    header("Location: index.php");
    exit();
}

// Ambil data dari GenieACS
$genieacs_data = getDeviceDetailByCustomerId($customer['id_pelanggan']);

// RX Power
$rx_power_display = '-';
$rx_status_class = 'secondary';
$rx_status_text = '';
$wifi_ssid = '-';
$wifi_password = '******';
$device_status = 'Tidak terdeteksi';
$device_status_class = 'secondary';
$device_id = '';

if ($genieacs_data && $genieacs_data['found']) {
    if (isset($genieacs_data['rx_power']) && $genieacs_data['rx_power'] != '-') {
        $rx_power_display = $genieacs_data['rx_power'];
        $rx_status_class = getRxPowerColor($rx_power_display);
        $rx_status_text = getRxPowerStatus($rx_power_display);
    }
    $wifi_ssid = $genieacs_data['ssid'] ?? '-';
    if (isset($genieacs_data['wifi_password']) && !empty($genieacs_data['wifi_password']) && $genieacs_data['wifi_password'] != '******') {
        $wifi_password = $genieacs_data['wifi_password'];
    }
    $device_status = $genieacs_data['status'] ?? 'Offline';
    $device_status_class = ($device_status == 'Online') ? 'success' : 'danger';
    $device_id = $genieacs_data['device_id'] ?? '';
}

// Traffic
$total_bytes = $customer['total_traffic'];
if ($total_bytes >= 1024 * 1024 * 1024) {
    $traffic_display = number_format($total_bytes / (1024 * 1024 * 1024), 2) . ' GB';
} elseif ($total_bytes >= 1024 * 1024) {
    $traffic_display = number_format($total_bytes / (1024 * 1024), 2) . ' MB';
} elseif ($total_bytes >= 1024) {
    $traffic_display = number_format($total_bytes / 1024, 2) . ' KB';
} else {
    $traffic_display = $total_bytes . ' Bytes';
}

// Waktu akumulasi
$total_online_seconds = $customer['total_online_seconds'] ?? 0;
$days = floor($total_online_seconds / 86400);
$hours = floor(($total_online_seconds % 86400) / 3600);
$minutes = floor(($total_online_seconds % 3600) / 60);
$seconds = $total_online_seconds % 60;
$waktu_akumulasi = '';
if ($days > 0) $waktu_akumulasi .= $days . ' Hr : ';
if ($hours > 0) $waktu_akumulasi .= $hours . ' J : ';
if ($minutes > 0) $waktu_akumulasi .= $minutes . ' M : ';
if ($seconds > 0) $waktu_akumulasi .= $seconds . ' Dt ';
if (empty($waktu_akumulasi)) $waktu_akumulasi = ' 0S ';

// Mikrotik data
$mikrotik_data = ['mac' => '-', 'ip_address' => '-', 'uptime' => '-', 'status' => 'Offline'];
if ($customer['router_id'] && $customer['router_ip']) {
    require_once __DIR__ . '/../../includes/routeros_api_new.php';
    $API = new RouterosAPI();
    $API->debug = false;
    $API->timeout = 5;
    $API->port = (int)($customer['port_api'] ?? 8730);
    if ($API->connect($customer['router_ip'], $customer['router_user'], $customer['router_pass'])) {
        $API->write('/ppp/active/print', false);
        $API->write('?name=' . $customer['id_pelanggan']);
        $actives = $API->read();
        $API->disconnect();
        if (!empty($actives)) {
            $active = $actives[0];
            $mikrotik_data['mac'] = $active['caller-id'] ?? '-';
            $mikrotik_data['ip_address'] = $active['address'] ?? '-';
            $mikrotik_data['uptime'] = $active['uptime'] ?? '-';
            $mikrotik_data['status'] = 'Online';
        }
    }
}

// SIMPAN STATUS ONLINE KE DATABASE
$status_online = ($mikrotik_data['status'] == 'Online') ? 1 : 0;
$stmt_update = $pdo->prepare("UPDATE pelanggan SET is_online = ? WHERE id = ?");
$stmt_update->execute([$status_online, $customer['id']]);
?>

<style>
    .rx-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: bold;
    }
    .bg-primary { background-color: #0066cc !important; color: white; }
    .bg-success { background-color: #28a745 !important; color: white; }
    .bg-danger { background-color: #dc3545 !important; color: white; }
    .bg-secondary { background-color: #6c757d !important; color: white; }
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
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-user"></i> Detail Pelanggan: <?= htmlspecialchars($customer['nama_lengkap']) ?>
        <?php
    $tipe = $customer['tipe_pelanggan'] ?? 'reguler';
    $is_khusus = in_array($tipe, ['vip', 'fasum']);
    $back_url = $is_khusus ? 'pelanggan_khusus.php' : 'index.php';
    ?>
    <a href="<?= $back_url ?>" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">Data Diri & Paket</div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr><td width="150">ID Pelanggan</td><td><strong><?= htmlspecialchars($customer['id_pelanggan']) ?></strong></td></tr>
                            <tr><td>Nama Lengkap</td><td><?= htmlspecialchars($customer['nama_lengkap']) ?></td></tr>
                            <tr><td>Alamat</td><td><?= nl2br(htmlspecialchars($customer['alamat'])) ?></td></tr>
                            <tr><td>No Telepon</td><td><?= htmlspecialchars($customer['no_telepon']) ?></td></tr>
                            <tr><td>Paket</td><td><strong><?= htmlspecialchars($customer['nama_paket']) ?></strong></td></tr>
                            <tr><td>Harga</td><td>Rp <?= number_format($customer['harga'], 0, ',', '.') ?></td></tr>
                            <tr><td>Jatuh Tempo</td><td><?= date('d-m-Y', strtotime($customer['jatuh_tempo'])) ?></td></tr>
                            <tr><td>Status</td><td><?= $customer['status'] == 'active' ? '<span class="badge bg-success">AKTIF</span>' : '<span class="badge bg-danger">ISOLIR</span>' ?></td></tr>
                            <tr><td>Router</td><td><?= htmlspecialchars($customer['nama_router'] ?? '-') ?></td></tr>
                            <tr><td>Profil PPPoE</td><td><?= htmlspecialchars($customer['profil_ppp'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">Informasi Trafik & Koneksi</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 text-center bg-light">
                                    <i class="fas fa-ethernet fa-2x text-primary"></i>
                                    <div class="small text-muted">MAC Address</div>
                                    <strong><?= $mikrotik_data['mac'] ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 text-center bg-light">
                                    <i class="fas fa-server fa-2x text-primary"></i>
                                    <div class="small text-muted">Status ONT</div>
                                    <span class="badge bg-<?= $device_status_class ?>"><?= $device_status ?></span>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 text-center bg-light">
                                    <i class="fas fa-network-wired fa-2x text-primary"></i>
                                    <div class="small text-muted">IP Address</div>
                                    <strong><?= $mikrotik_data['ip_address'] ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 text-center bg-light">
                                    <i class="fas fa-plug fa-2x text-success"></i>
                                    <div class="small text-muted">Status PPPoE</div>
                                    <?php if ($mikrotik_data['status'] == 'Online'): ?>
                                        <span class="badge bg-success">Online</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Offline</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 text-center bg-light">
                                    <i class="fas fa-clock fa-2x text-warning"></i>
                                    <div class="small text-muted">Waktu Online (Akumulasi)</div>
                                    <strong><?= $waktu_akumulasi ?></strong>
                                    <div class="small">(sejak pertama aktif)</div>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 text-center bg-light">
                                    <i class="fas fa-signal fa-2x text-info"></i>
                                    <div class="small text-muted">Rx Power (ONT)</div>
                                    <span class="rx-badge bg-<?= $rx_status_class ?>">
                                        <?= htmlspecialchars($rx_power_display) ?>
                                        <?php if ($rx_status_text): ?>
                                            (<?= $rx_status_text ?>)
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <div class="border rounded p-2 text-center bg-light">
                                    <i class="fas fa-database fa-2x text-info"></i>
                                    <div class="small text-muted">Quota Terpakai</div>
                                    <strong><?= $traffic_display ?></strong>
                                    <div class="small">(reset setiap jatuh tempo)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->

        <!-- PENGATURAN WIFI -->
        <div class="row mt-3">
            <div class="col-md-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <i class="fas fa-wifi"></i> PENGATURAN WIFI
                        <span class="badge bg-<?= $device_status_class ?> float-end"><?= $device_status ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="alert alert-secondary">
                                    <strong>SSID:</strong>
                                    <span id="ssid_display"><?= htmlspecialchars($wifi_ssid) ?></span>
                                    <?php if ($device_status == 'Online' && !empty($device_id)): ?>
                                        <button class="btn btn-sm btn-warning float-end" onclick="editWifi('ssid')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    <div id="ssid_edit" style="display:none; margin-top:5px;">
                                        <input type="text" id="ssid_input" class="form-control form-control-sm" value="<?= htmlspecialchars($wifi_ssid) ?>">
                                        <button class="btn btn-sm btn-success mt-1" onclick="saveWifi('ssid')">Simpan</button>
                                        <button class="btn btn-sm btn-secondary mt-1" onclick="cancelWifi('ssid')">Batal</button>
                                        <span id="ssid_status" class="ms-2"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-secondary">
                                    <strong>Password:</strong>
                                    <span id="password_display"><?= htmlspecialchars($wifi_password) ?></span>
                                    <?php if ($device_status == 'Online' && !empty($device_id)): ?>
                                        <button class="btn btn-sm btn-warning float-end" onclick="editWifi('password')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    <div id="password_edit" style="display:none; margin-top:5px;">
                                        <input type="text" id="password_input" class="form-control form-control-sm" value="<?= htmlspecialchars($wifi_password != '******' ? $wifi_password : '') ?>">
                                        <button class="btn btn-sm btn-success mt-1" onclick="saveWifi('password')">Simpan</button>
                                        <button class="btn btn-sm btn-secondary mt-1" onclick="cancelWifi('password')">Batal</button>
                                        <span id="password_status" class="ms-2"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PERANGKAT TERHUBUNG -->
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <i class="fas fa-users"></i> Perangkat Terhubung
                <button class="btn btn-sm btn-light float-end" onclick="loadHosts()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            <div class="card-body" id="hosts_container">
                <div class="text-center text-muted">Memuat perangkat...</div>
            </div>
        </div>
    </div>
</div>

<!-- REBOOT ONT -->
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-power-off"></i> Reboot ONT
            </div>
            <div class="card-body">
                <?php if ($device_status == 'Online' && !empty($device_id)): ?>
                    <p class="text-muted">ONT akan reboot setelah klik tombol. Koneksi terputus 1-2 menit.</p>
                    <button class="btn btn-danger btn-lg" onclick="rebootOnt()">
                        <i class="fas fa-power-off"></i> REBOOT SEKARANG
                    </button>
                    <span id="reboot_status" class="ms-3"></span>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Perangkat sedang <strong>Offline</strong>. Reboot hanya bisa saat perangkat online.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const deviceId = '<?= $device_id ?>';
const customerId = '<?= $customer['id_pelanggan'] ?>';
const ssidPath = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID';
const passwordPath = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase';

function editWifi(type) {
    if (type === 'ssid') {
        document.getElementById('ssid_edit').style.display = 'block';
    } else {
        document.getElementById('password_edit').style.display = 'block';
    }
}

function cancelWifi(type) {
    if (type === 'ssid') {
        document.getElementById('ssid_edit').style.display = 'none';
        document.getElementById('ssid_status').innerHTML = '';
    } else {
        document.getElementById('password_edit').style.display = 'none';
        document.getElementById('password_status').innerHTML = '';
    }
}

function saveWifi(type) {
    let value, parameter, statusId, displayId;

    if (type === 'ssid') {
        value = document.getElementById('ssid_input').value.trim();
        parameter = ssidPath;
        statusId = 'ssid_status';
        displayId = 'ssid_display';
    } else {
        value = document.getElementById('password_input').value.trim();
        parameter = passwordPath;
        statusId = 'password_status';
        displayId = 'password_display';
        if (value.length > 0 && value.length < 8) {
            document.getElementById(statusId).innerHTML = '<span class="text-danger">Minimal 8 karakter!</span>';
            return;
        }
    }

    if (!value) {
        document.getElementById(statusId).innerHTML = '<span class="text-danger">Tidak boleh kosong!</span>';
        return;
    }

    document.getElementById(statusId).innerHTML = '<span class="saving-spinner"></span> Menyimpan...';

    const formData = new FormData();
    formData.append('device_id', deviceId);
    formData.append('parameter', parameter);
    formData.append('value', value);
    formData.append('customer_id', customerId);
    formData.append('type', type);

    fetch("/modules/genieacs/ajax_update.php", {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch(e) {
            console.error('Response bukan JSON:', text.substring(0, 500));
            document.getElementById(statusId).innerHTML = '<span class="text-danger">Error: Response server tidak valid</span>';
            return;
        }
        if (data.success) {
            document.getElementById(statusId).innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Berhasil!</span>';
            document.getElementById(displayId).textContent = value;
            setTimeout(() => {
                cancelWifi(type);
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

// ============================================
// REBOOT ONT
// ============================================
function rebootOnt() {
    if (!deviceId) {
        alert('ONT belum terdaftar di GenieACS.');
        return;
    }

    if (!confirm('ONT akan reboot setelah klik tombol REBOOT SEKARANG.\n\nKoneksi internet akan terputus sekitar 1-2 menit.\n\nLanjutkan?')) {
        return;
    }

    const el = document.getElementById('reboot_status');
    el.innerHTML = '<div class="alert alert-info mb-0 small"><div class="spinner-border spinner-border-sm"></div> Mengirim perintah reboot...</div>';

    fetch('/api/reboot_ont.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'device_id=' + encodeURIComponent(deviceId)
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

// ============================================
// LOAD HOSTS
// ============================================
function loadHosts() {
    const el = document.getElementById('hosts_container');
    if (!el) return;

    if (!deviceId) {
        el.innerHTML = '<div class="alert alert-warning mb-0 small">ONT belum terdaftar di GenieACS.</div>';
        return;
    }

    el.innerHTML = '<div class="text-center text-muted py-2 small"><div class="spinner-border spinner-border-sm"></div> Memuat...</div>';

    fetch('/api/get_connected_devices.php?device_id=' + encodeURIComponent(deviceId))
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

        let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
        html += '<thead class="table-light"><tr><th>Nama</th><th>IP</th><th>MAC</th></tr></thead><tbody>';
        data.devices.forEach(d => {
            html += '<tr>';
            html += '<td><i class="fas fa-mobile-alt text-primary"></i> ' + (d.hostname || '-') + '</td>';
            html += '<td><code>' + (d.ip || '-') + '</code></td>';
            html += '<td><small class="text-muted">' + (d.mac || '-') + '</small></td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        html += '<small class="text-muted">Total: ' + data.total + ' perangkat</small>';
        el.innerHTML = html;
    })
    .catch(err => {
        el.innerHTML = '<div class="alert alert-danger mb-0 small">Error: ' + err.message + '</div>';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (deviceId && deviceId !== '') {
        loadHosts();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
