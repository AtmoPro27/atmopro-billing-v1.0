<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SESSION['user_role'] != 'super_admin' && $_SESSION['user_role'] != 'admin') {
    header("Location: /modules/customers/pelanggan_khusus.php");
    exit();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/routeros_api_new.php";

$pdo = Database::getInstance()->getConnection();

// ============================================
// FUNGSI GENERATE ID OTOMATIS
// ============================================
function generateIdPelanggan($pdo, $tipe, $pppoe = null) {
    // Untuk pelanggan khusus: ID Pelanggan = PPPoE username
    // Ini agar match dengan data Mikrotik & GenieACS
    if (!empty($pppoe)) {
        return $pppoe;
    }
    
    // Fallback: generate ID otomatis
    $prefix = strtoupper($tipe);
    $stmt = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan LIKE ? ORDER BY id_pelanggan DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();
    
    if ($last) {
        $last_number = (int) preg_replace('/[^0-9]/', '', $last['id_pelanggan']);
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    return $prefix . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

// ============================================
// FUNGSI DETEKSI TIPE DARI PROFILE
// ============================================
function detectTipe($name, $profile) {
    $profile = strtoupper($profile);
    $name_lower = strtolower($name);
    
    // Prioritas 1: Cek dari PROFILE
    if (stripos($profile, 'FASUM') !== false) return 'fasum';
    if (stripos($profile, 'VIP') !== false || stripos($profile, 'VVIP') !== false) return 'vip';
    if (stripos($profile, 'KHUSUS') !== false) return 'vip';
    
    // Prioritas 2: Cek dari NAMA
    if (stripos($name_lower, 'fasum') !== false) return 'fasum';
    
    // Default: VIP
    return 'vip';
}

// Handle AJAX scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan') {
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->query("SELECT * FROM routers WHERE status = 'active'");
        $routers = $stmt->fetchAll();
        
        $found = [];
        foreach ($routers as $router) {
            $API = new RouterosAPI();
            $API->debug = false;
            $API->timeout = 10;
            $API->port = (int)($router['port_api'] ?? 8730);
            
            if ($API->connect($router['ip_address'], $router['username'], $router['password'])) {
                $API->write('/ppp/secret/print');
                $secrets = $API->read();
                
                foreach ($secrets as $secret) {
                    $name = $secret['name'] ?? '';
                    if (preg_match('/[a-zA-Z]/', $name)) {
                        $cek = $pdo->prepare("SELECT id, tipe_pelanggan FROM pelanggan WHERE id_pelanggan = ?");
                        $cek->execute([$name]);
                        $exists = $cek->fetch();
                        
                        $tipe = detectTipe($name, $secret['profile'] ?? '');
                        
                        $found[] = [
                            'pppoe' => $name,
                            'password' => $secret['password'] ?? '',
                            'profile' => $secret['profile'] ?? '',
                            'disabled' => $secret['disabled'] ?? 'false',
                            'comment' => $secret['comment'] ?? '',
                            'router_id' => $router['id'],
                            'router_name' => $router['nama_router'],
                            'sudah_di_billing' => $exists ? true : false,
                            'tipe_estimasi' => $tipe
                        ];
                    }
                }
                $API->disconnect();
            }
        }
        
        echo json_encode(['success' => true, 'data' => $found]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX import single
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    header('Content-Type: application/json');
    
    try {
        $pppoe = trim($_POST['pppoe'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $tipe = $_POST['tipe'] ?? 'vip';
        $password = $_POST['password'] ?? '';
        
        if (empty($pppoe) || empty($nama)) {
            echo json_encode(['success' => false, 'message' => 'PPPoE dan Nama wajib diisi']);
            exit;
        }
        
        $cek = $pdo->prepare("SELECT id FROM pelanggan WHERE id_pelanggan = ?");
        $cek->execute([$pppoe]);
        if ($cek->fetch()) {
            echo json_encode(['success' => false, 'message' => 'PPPoE sudah terdaftar di billing']);
            exit;
        }
        
        $id_pelanggan = generateIdPelanggan($pdo, $tipe, $pppoe);
        
        $stmt = $pdo->prepare("
            INSERT INTO pelanggan 
            (id_pelanggan, nama_lengkap, tipe_pelanggan, status, status_aktif, password, created_at, is_synced) 
            VALUES (?, ?, ?, 'active', 'aktif', ?, NOW(), 1)
        ");
        $stmt->execute([$id_pelanggan, $nama, $tipe, $password]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Pelanggan ' . $nama . ' berhasil diimport dengan ID: ' . $id_pelanggan,
            'id_pelanggan' => $id_pelanggan
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle AJAX import all (massal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_all') {
    header('Content-Type: application/json');
    
    try {
        $data = json_decode($_POST['data'] ?? '[]', true);
        $imported = 0;
        $failed = 0;
        $results = [];
        
        foreach ($data as $item) {
            $pppoe = trim($item['pppoe'] ?? '');
            $nama = trim($item['nama'] ?? $pppoe);
            $tipe = $item['tipe'] ?? 'vip';
            $password = $item['password'] ?? '';
            
            if (empty($pppoe)) { $failed++; continue; }
            
            $cek = $pdo->prepare("SELECT id FROM pelanggan WHERE id_pelanggan = ?");
            $cek->execute([$pppoe]);
            if ($cek->fetch()) { $failed++; continue; }
            
            $id_pelanggan = generateIdPelanggan($pdo, $tipe, $pppoe);
            
            $stmt = $pdo->prepare("
                INSERT INTO pelanggan 
                (id_pelanggan, nama_lengkap, tipe_pelanggan, status, status_aktif, password, created_at, is_synced) 
                VALUES (?, ?, ?, 'active', 'aktif', ?, NOW(), 1)
            ");
            $stmt->execute([$id_pelanggan, $nama, $tipe, $password]);
            
            $imported++;
            $results[] = $id_pelanggan . ' (' . $pppoe . ')';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Import selesai! Berhasil: ' . $imported . ', Gagal: ' . $failed,
            'imported' => $imported,
            'failed' => $failed,
            'results' => $results
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import VIP/FASUM dari Mikrotik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid p-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4><i class="fas fa-download"></i> Import Pelanggan Khusus dari Mikrotik</h4>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                Fitur ini untuk memindai pelanggan khusus (VIP, FASUM) yang sudah jalan di Mikrotik 
                (dengan PPPoE berupa huruf) tapi belum terdaftar di billing.
            </div>
            
            <button class="btn btn-primary btn-lg" onclick="scanMikrotik()">
                <i class="fas fa-search"></i> Scan Mikrotik
            </button>
            <button class="btn btn-success btn-lg" onclick="importSemua()" id="btnImportSemua" style="display:none;">
                <i class="fas fa-download"></i> Import Semua yang Belum di Billing
            </button>
            <a href="pelanggan_khusus.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            
            <div id="hasilScan" class="mt-3"></div>
        </div>
    </div>
</div>

<!-- Modal Import Single -->
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-download"></i> Import Pelanggan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="import_pppoe">
                <input type="hidden" id="import_password">
                <div class="mb-3">
                    <label class="form-label">PPPoE Username (dari Mikrotik)</label>
                    <input type="text" id="import_pppoe_display" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nama Pelanggan <span class="text-danger">*</span></label>
                    <input type="text" id="import_nama" class="form-control" placeholder="Nama pelanggan">
                </div>
                <div class="mb-3">
                    <label class="form-label">Tipe Pelanggan</label>
                    <select id="import_tipe" class="form-select">
                        <option value="vip">⭐ VIP</option>
                        <option value="fasum">🏛️ FASUM</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" onclick="importPelanggan()">
                    <i class="fas fa-save"></i> Import ke Billing
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let scanData = [];

function tipeBadge(tipe) {
    if (tipe === 'vip') return '<span class="badge bg-warning text-dark">⭐ VIP</span>';
    if (tipe === 'fasum') return '<span class="badge bg-info">🏛️ FASUM</span>';
}

function scanMikrotik() {
    document.getElementById('hasilScan').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x"></i><p>Memindai Mikrotik...</p></div>';
    document.getElementById('btnImportSemua').style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'scan');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            scanData = data.data;
            renderHasil(data.data);
        } else {
            document.getElementById('hasilScan').innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
        }
    })
    .catch(err => {
        document.getElementById('hasilScan').innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
    });
}

function renderHasil(data) {
    if (data.length === 0) {
        document.getElementById('hasilScan').innerHTML = '<div class="alert alert-warning">Tidak ada pelanggan khusus ditemukan di Mikrotik.</div>';
        return;
    }
    
    const belumDiBilling = data.filter(d => !d.sudah_di_billing);
    
    // Statistik
    const vipCount = data.filter(d => d.tipe_estimasi === 'vip').length;
    const fasumCount = data.filter(d => d.tipe_estimasi === 'fasum').length;
        document.getElementById('btnImportSemua').style.display = 'inline-block';
        document.getElementById('btnImportSemua').innerHTML = '<i class="fas fa-download"></i> Import Semua (' + belumDiBilling.length + ' pelanggan)';
    }
    
    let html = '<div class="row mb-3">';
    html += '<div class="col-md-3"><div class="alert alert-warning text-center"><strong>' + vipCount + '</strong><br>⭐ VIP</div></div>';
    html += '<div class="col-md-3"><div class="alert alert-info text-center"><strong>' + fasumCount + '</strong><br>🏛️ FASUM</div></div>';
    html += '<div class="col-md-3"><div class="alert alert-secondary text-center"><strong>' + belumDiBilling.length + '</strong><br>Belum di Billing</div></div>';
    html += '<h5>Ditemukan ' + data.length + ' pelanggan:</h5>';
    html += '<div class="table-responsive"><table class="table table-bordered table-striped">';
    html += '<thead><tr><th>PPPoE</th><th>Profile</th><th>Router</th><th>Status</th><th>Tipe</th><th>Aksi</th></tr></thead><tbody>';
    
    data.forEach(item => {
        const statusBadge = item.disabled === 'false' ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Disable</span>';
        const billingBadge = item.sudah_di_billing ? '<span class="badge bg-info">Sudah di Billing</span>' : '<span class="badge bg-warning text-dark">Belum</span>';
        
        html += '<tr>';
        html += '<td><code>' + item.pppoe + '</code></td>';
        html += '<td>' + item.profile + '</td>';
        html += '<td>' + item.router_name + '</td>';
        html += '<td>' + statusBadge + '</td>';
        html += '<td>' + tipeBadge(item.tipe_estimasi) + '</td>';
        html += '<td>' + billingBadge + ' ';
        if (!item.sudah_di_billing) {
            html += '<button class="btn btn-sm btn-success" onclick="bukaImport(\'' + item.pppoe + '\', \'' + item.password + '\', \'' + item.tipe_estimasi + '\')"><i class="fas fa-download"></i> Import</button>';
        }
        html += '</td></tr>';
    });
    
    html += '</tbody></table></div>';
    document.getElementById('hasilScan').innerHTML = html;
}

function bukaImport(pppoe, password, tipe) {
    document.getElementById('import_pppoe').value = pppoe;
    document.getElementById('import_pppoe_display').value = pppoe;
    document.getElementById('import_password').value = password;
    document.getElementById('import_tipe').value = tipe;
    document.getElementById('import_nama').value = pppoe.toUpperCase();
    new bootstrap.Modal(document.getElementById('modalImport')).show();
}

function importPelanggan() {
    const nama = document.getElementById('import_nama').value.trim();
    if (!nama) {
        alert('Nama wajib diisi!');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'import');
    formData.append('pppoe', document.getElementById('import_pppoe').value);
    formData.append('password', document.getElementById('import_password').value);
    formData.append('tipe', document.getElementById('import_tipe').value);
    formData.append('nama', nama);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Gagal: ' + data.message);
        }
    })
    .catch(err => alert('Error: ' + err.message));
}

function importSemua() {
    const belumDiBilling = scanData.filter(d => !d.sudah_di_billing);
    if (belumDiBilling.length === 0) {
        alert('Tidak ada pelanggan yang perlu diimport.');
        return;
    }
    
    if (!confirm('Yakin ingin mengimport ' + belumDiBilling.length + ' pelanggan sekaligus?')) {
        return;
    }
    
    const dataToImport = belumDiBilling.map(d => ({
        pppoe: d.pppoe,
        password: d.password,
        tipe: d.tipe_estimasi,
        nama: d.pppoe.toUpperCase()
    }));
    
    const formData = new FormData();
    formData.append('action', 'import_all');
    formData.append('data', JSON.stringify(dataToImport));
    
    document.getElementById('hasilScan').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-3x"></i><p>Mengimport ' + belumDiBilling.length + ' pelanggan...</p></div>';
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Gagal: ' + data.message);
        }
    })
    .catch(err => alert('Error: ' + err.message));
}
</script>
</body>
</html>
