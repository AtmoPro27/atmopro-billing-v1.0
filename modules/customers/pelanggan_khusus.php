<?php
require_once __DIR__ . '/../../includes/auth_check.php';

// ============================================
// CEK ROLE USER
// ============================================
$user_role = $_SESSION['user_role'] ?? 'guest';
$is_superadmin = ($user_role == 'super_admin');

// Hanya superadmin yang bisa tambah/edit/hapus
$can_edit = $is_superadmin;

// ============================================
// INCLUDE DATABASE & API (SEBELUM HANDLER)
// ============================================
require_once '../../config/database.php';
require_once '../../includes/mikrotik_api.php';
require_once '../../includes/genieacs_functions.php';

$pdo = Database::getInstance()->getConnection();

// ============================================
// HANDLER AJAX
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    error_reporting(0);
    ini_set('display_errors', 0);
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    // Proteksi: hanya superadmin yang bisa edit/hapus/tambah
    $protected_actions = ['update_tipe', 'hapus_pelanggan', 'edit_data', 'tambah_pelanggan'];
    if (in_array($action, $protected_actions) && !$is_superadmin) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya Superadmin yang bisa melakukan aksi ini.']);
        exit;
    }

    try {
        // Update tipe
        if ($action === 'update_tipe') {
            $pelanggan_id = $_POST['pelanggan_id'] ?? '';
            $tipe = $_POST['tipe'] ?? '';
            $stmt = $pdo->prepare("UPDATE pelanggan SET tipe_pelanggan = ? WHERE id = ?");
            $stmt->execute([$tipe, $pelanggan_id]);
            echo json_encode(['success' => true, 'message' => 'Tipe berhasil diupdate']);
        }
        // Hapus pelanggan
        elseif ($action === 'hapus_pelanggan') {
            $pelanggan_id = $_POST['pelanggan_id'] ?? '';

            // Ambil data pelanggan dulu
            $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id = ? AND tipe_pelanggan IN ('vip', 'fasum', 'nardi')");
            $stmt->execute([$pelanggan_id]);
            $pelanggan = $stmt->fetch();

            if (!$pelanggan) {
                echo json_encode(['success' => false, 'message' => 'Pelanggan tidak ditemukan']);
                exit;
            }

            $id_pelanggan = $pelanggan['id_pelanggan'];
            $paket_id = $pelanggan['paket_id'];
            $msg_mikrotik = '';

            // ============================================
            // HAPUS DI MIKROTIK (SECRET + ACTIVE)
            // ============================================
            if (!empty($paket_id)) {
                $stmt_paket = $pdo->prepare("SELECT router_id FROM paket_internet WHERE id = ?");
                $stmt_paket->execute([$paket_id]);
                $paket_data = $stmt_paket->fetch();

                if ($paket_data && !empty($paket_data['router_id'])) {
                    $router_id = $paket_data['router_id'];

                    // 1. Hapus active connection dulu
                    $conn = connectMikrotik($router_id);
                    if ($conn['success']) {
                        $api = $conn['api'];
                        $api->write('/ppp/active/print', false);
                        $api->write('?name=' . $id_pelanggan);
                        $actives = $api->read();

                        if (!empty($actives) && isset($actives[0]['.id'])) {
                            $api->write('/ppp/active/remove', false);
                            $api->write('=.id=' . $actives[0]['.id']);
                            $api->read();
                        }
                        $api->disconnect();

                        // 2. Hapus secret
                        $result = delete_pppoe_user($router_id, $id_pelanggan);
                        if ($result['success']) {
                            $msg_mikrotik = ' Secret & koneksi Mikrotik dihapus.';
                        } else {
                            $msg_mikrotik = ' Secret gagal dihapus: ' . $result['message'];
                        }
                    }
                }
            }

            // ============================================
            // HAPUS DARI DATABASE BILLING
            // ============================================
            $stmt = $pdo->prepare("DELETE FROM pelanggan WHERE id = ?");
            $stmt->execute([$pelanggan_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Pelanggan berhasil dihapus.' . $msg_mikrotik
            ]);
        }
        // Ambil data untuk edit
        elseif ($action === 'get_pelanggan') {
            $pelanggan_id = $_POST['pelanggan_id'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id = ?");
            $stmt->execute([$pelanggan_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
        }
        // Edit data lengkap
        elseif ($action === 'edit_data') {
            $pelanggan_id = $_POST['pelanggan_id'] ?? '';
            $id_pelanggan = trim($_POST['id_pelanggan'] ?? '');
            $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $no_telepon = trim($_POST['no_telepon'] ?? '');
            $tipe_pelanggan = $_POST['tipe_pelanggan'] ?? 'vip';
            $paket_id = $_POST['paket_id'] ?? null;
            $jatuh_tempo = $_POST['jatuh_tempo'] ?? null;
            $status = $_POST['status'] ?? 'active';

            if (empty($nama_lengkap)) {
                echo json_encode(['success' => false, 'message' => 'Nama wajib diisi']);
                exit;
            }

            $sql = "UPDATE pelanggan SET 
                        id_pelanggan = ?,
                        nama_lengkap = ?, 
                        alamat = ?, 
                        no_telepon = ?, 
                        tipe_pelanggan = ?,
                        paket_id = ?,
                        jatuh_tempo = ?,
                        status = ?
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $id_pelanggan,
                $nama_lengkap,
                $alamat,
                $no_telepon,
                $tipe_pelanggan,
                $paket_id ?: null,
                $jatuh_tempo ?: null,
                $status,
                $pelanggan_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Data berhasil diupdate']);
        }
        // Tambah pelanggan baru + auto-create Mikrotik
        elseif ($action === 'tambah_pelanggan') {
            $id_pelanggan = trim($_POST['id_pelanggan'] ?? '');
            $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $no_telepon = trim($_POST['no_telepon'] ?? '');
            $tipe_pelanggan = $_POST['tipe_pelanggan'] ?? 'vip';
            $paket_id = $_POST['paket_id'] ?? null;
            $jatuh_tempo = $_POST['jatuh_tempo'] ?? null;

            if (empty($id_pelanggan) || empty($nama_lengkap)) {
                echo json_encode(['success' => false, 'message' => 'ID dan Nama wajib diisi']);
                exit;
            }

            // Cek duplikat
            $cek = $pdo->prepare("SELECT id FROM pelanggan WHERE id_pelanggan = ?");
            $cek->execute([$id_pelanggan]);
            if ($cek->fetch()) {
                echo json_encode(['success' => false, 'message' => 'ID Pelanggan sudah terdaftar']);
                exit;
            }

            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO pelanggan
                (id_pelanggan, nama_lengkap, alamat, no_telepon, tipe_pelanggan, paket_id, jatuh_tempo, status, status_aktif, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 'aktif', NOW())
            ");
            $stmt->execute([
                $id_pelanggan,
                $nama_lengkap,
                $alamat,
                $no_telepon,
                $tipe_pelanggan,
                $paket_id ?: null,
                $jatuh_tempo ?: null
            ]);

            // Auto-create secret Mikrotik
            $mikrotik_result = null;
            $msg_extra = '';

            if (!empty($paket_id)) {
                $stmt_paket = $pdo->prepare("SELECT profil_ppp, router_id FROM paket_internet WHERE id = ?");
                $stmt_paket->execute([$paket_id]);
                $paket_data = $stmt_paket->fetch();

                if ($paket_data && !empty($paket_data['router_id']) && !empty($paket_data['profil_ppp'])) {
                    $password_ppp = '123456';
                    $mikrotik_result = add_pppoe_user($paket_data['router_id'], $id_pelanggan, $password_ppp, $paket_data['profil_ppp']);

                    if ($mikrotik_result['success']) {
                        $hashed_password = password_hash($password_ppp, PASSWORD_DEFAULT);
                        $stmt_upd = $pdo->prepare("UPDATE pelanggan SET password = ? WHERE id_pelanggan = ?");
                        $stmt_upd->execute([$hashed_password, $id_pelanggan]);
                        $msg_extra = ' Secret Mikrotik berhasil dibuat.';
                    } else {
                        $msg_extra = ' TAPI gagal buat secret: ' . $mikrotik_result['message'];
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Pelanggan khusus berhasil ditambahkan.' . $msg_extra,
                'mikrotik' => $mikrotik_result
            ]);
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenal']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================================
// INCLUDE HEADER (SETELAH HANDLER)
// ============================================
include '../../includes/header.php';

// ============================================
// AMBIL DATA PELANGGAN KHUSUS
// ============================================
$stmt = $pdo->query("
    SELECT p.*, pk.nama_paket, pk.harga, pk.router_id, r.nama_router
    FROM pelanggan p
    LEFT JOIN paket_internet pk ON p.paket_id = pk.id
    LEFT JOIN routers r ON pk.router_id = r.id
    WHERE p.tipe_pelanggan IN ('vip', 'fasum', 'nardi')
    ORDER BY 
        CASE p.tipe_pelanggan 
            WHEN 'vip' THEN 1 
            WHEN 'fasum' THEN 2 
            WHEN 'nardi' THEN 3
            ELSE 4 
        END,
        p.id_pelanggan ASC
");
$pelanggan_khusus = $stmt->fetchAll();

// Ambil paket untuk dropdown
$stmt_paket = $pdo->query("
    SELECT pk.id, pk.nama_paket, pk.harga, r.nama_router
    FROM paket_internet pk
    LEFT JOIN routers r ON pk.router_id = r.id
    ORDER BY r.nama_router, pk.nama_paket
");
$paket_list = $stmt_paket->fetchAll();

// Ambil data Mikrotik via API
$mikrotik_map = [];
foreach ($pelanggan_khusus as $p) {
    $mikrotik_data = ['status' => 'Offline', 'ip_address' => '-', 'uptime' => '-', 'mac' => '-'];
    
    $router_ip = null;
    $router_user = null;
    $router_pass = null;
    $router_port = 8730;
    
    if (!empty($p['router_id'])) {
        $stmt_r = $pdo->prepare("SELECT * FROM routers WHERE id = ? AND status = 'active'");
        $stmt_r->execute([$p['router_id']]);
        $router = $stmt_r->fetch();
        if ($router) {
            $router_ip = $router['ip_address'];
            $router_user = $router['username'];
            $router_pass = $router['password'];
            $router_port = (int)($router['port_api'] ?? 8730);
        }
    }
    
    if (!$router_ip) {
        $stmt_r = $pdo->query("SELECT * FROM routers WHERE status = 'active' LIMIT 1");
        $router = $stmt_r->fetch();
        if ($router) {
            $router_ip = $router['ip_address'];
            $router_user = $router['username'];
            $router_pass = $router['password'];
            $router_port = (int)($router['port_api'] ?? 8730);
        }
    }
    
    if ($router_ip) {
        require_once __DIR__ . '/../../includes/routeros_api_new.php';
        $API = new RouterosAPI();
        $API->debug = false;
        $API->timeout = 3;
        $API->port = $router_port;
        
        if ($API->connect($router_ip, $router_user, $router_pass)) {
            $API->write('/ppp/active/print', false);
            $API->write('?name=' . $p['id_pelanggan']);
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
    
    $mikrotik_map[$p['id_pelanggan']] = $mikrotik_data;
}

// ============================================
// AMBIL RX POWER DARI GENIEACS (BATCH - SEKALI)
// ============================================
$genieacs_devices = getGenieACSDevices(2000);
$genieacs_map = [];
foreach ($genieacs_devices as $gd) {
    $genieacs_map[$gd['id']] = $gd['rx'] ?? '-';
}

// Gabungkan RX Power ke mikrotik_map
foreach ($mikrotik_map as $id => &$data) {
    $data['rx'] = $genieacs_map[$id] ?? '-';
}
unset($data);

$total_vip = count(array_filter($pelanggan_khusus, function($p) { return $p['tipe_pelanggan'] == 'vip'; }));
$total_fasum = count(array_filter($pelanggan_khusus, function($p) { return $p['tipe_pelanggan'] == 'fasum'; }));
$total_nardi = count(array_filter($pelanggan_khusus, function($p) { return $p['tipe_pelanggan'] == 'nardi'; }));

$total_online = 0;
foreach ($pelanggan_khusus as $p) {
    if (isset($mikrotik_map[$p['id_pelanggan']]) && $mikrotik_map[$p['id_pelanggan']]['status'] == 'Online') {
        $total_online++;
    }
}
?>

<style>
    .tipe-badge { font-size: 12px; padding: 5px 10px; border-radius: 20px; }
    .badge-vip { background: linear-gradient(135deg, #ffc107, #ff9800); color: #000; font-weight: bold; }
    .badge-fasum { background: linear-gradient(135deg, #17a2b8, #138496); color: white; font-weight: bold; }
    .badge-nardi { background: linear-gradient(135deg, #0066cc, #004499); color: white; font-weight: bold; }
    .stat-box { border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .stat-vip { background: linear-gradient(135deg, #ffc107, #ff9800); color: #000; }
    .stat-fasum { background: linear-gradient(135deg, #17a2b8, #138496); color: white; }
    .stat-nardi { background: linear-gradient(135deg, #0066cc, #004499); color: white; }
    .stat-total { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
    .table-khusus th { background-color: #f8f9fc; font-size: 13px; }
    .table-khusus td { font-size: 13px; vertical-align: middle; }
    .rx-power-badge {
        font-size: 14px;
        padding: 6px 12px;
        font-weight: bold;
        display: inline-block;
    }
    .table-khusus th.sortable {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 20px !important;
    }
    .table-khusus th.sortable:hover {
        background-color: #e9ecef;
    }
    .table-khusus th.sortable:after {
        content: "⇅";
        position: absolute;
        right: 5px;
        opacity: 0.3;
        font-size: 11px;
    }
    .table-khusus th.sortable.asc:after {
        content: "↑";
        opacity: 1;
        color: #0d5c1e;
    }
    .table-khusus th.sortable.desc:after {
        content: "↓";
        opacity: 1;
        color: #0d5c1e;
    }
</style>

<div class="container-fluid p-3">
    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2">
            <div class="stat-box stat-vip">
                <i class="fas fa-crown fa-2x"></i>
                <h4 class="mt-2 mb-0"><?= $total_vip ?></h4>
                <small>VIP</small>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="stat-box stat-fasum">
                <i class="fas fa-building fa-2x"></i>
                <h4 class="mt-2 mb-0"><?= $total_fasum ?></h4>
                <small>FASUM</small>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="stat-box stat-nardi">
                <i class="fas fa-satellite-dish fa-2x"></i>
                <h4 class="mt-2 mb-0"><?= $total_nardi ?></h4>
                <small>NARDI</small>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="stat-box stat-total">
                <i class="fas fa-wifi fa-2x"></i>
                <h4 class="mt-2 mb-0"><?= $total_online ?></h4>
                <small>Online</small>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <?php if ($can_edit): ?>
        <button class="btn btn-success btn-lg" onclick="tambahPelanggan()">
            <i class="fas fa-plus-circle"></i> Tambah Pelanggan Khusus
        </button>
        <?php endif; ?>
        <?php if ($can_edit): ?>
        <a href="import_vip_fasum.php" class="btn btn-primary btn-lg">
            <i class="fas fa-download"></i> Import dari Mikrotik
        </a>
        <?php endif; ?>
        <button class="btn btn-info btn-lg" onclick="location.reload()">
            <i class="fas fa-sync"></i> Refresh
        </button>
    </div>

    <!-- ============================================ -->
    <!-- FORM PENCARIAN & FILTER -->
    <!-- ============================================ -->
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" 
                               placeholder="Cari ID, Nama, Paket, Router, IP...">
                        <button class="btn btn-outline-secondary" onclick="clearSearch()" title="Clear">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <select id="filterTipe" class="form-select">
                        <option value="">Semua Tipe</option>
                        <option value="vip">⭐ VIP</option>
                        <option value="fasum">🏛️ FASUM</option>
                        <option value="nardi">📡 NARDI</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="filterStatus" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="online">🟢 Online</option>
                        <option value="offline">🔴 Offline</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="text-muted align-self-center pt-2">
                        Menampilkan <strong id="visibleCount"><?= count($pelanggan_khusus) ?></strong> 
                        dari <strong><?= count($pelanggan_khusus) ?></strong> pelanggan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-warning text-dark">
            <i class="fas fa-star"></i> Daftar Pelanggan Khusus
            <span class="badge bg-dark float-end" id="badgeCount"><?= count($pelanggan_khusus) ?> pelanggan</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-khusus">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="0" width="90">Tipe</th>
                            <th class="sortable" data-sort="1">ID / PPPoE</th>
                            <th class="sortable" data-sort="2">Nama</th>
                            <th class="sortable" data-sort="3">Paket</th>
                            <th class="sortable" data-sort="4">Router</th>
                            <th class="sortable" data-sort="5">Status</th>
                            <th class="sortable" data-sort="6">IP Address</th>
                            <th class="sortable" data-sort="7">RX Power</th>
                            <th width="130">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pelanggan_khusus) > 0): ?>
                            <?php foreach ($pelanggan_khusus as $p): ?>
                                <?php
                                $mk = $mikrotik_map[$p['id_pelanggan']] ?? ['status' => 'Offline', 'ip_address' => '-', 'uptime' => '-'];
                                $is_online = ($mk['status'] == 'Online');
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($p['tipe_pelanggan'] == 'vip'): ?>
                                            <span class="badge badge-vip tipe-badge">⭐ VIP</span>
                                        <?php elseif ($p['tipe_pelanggan'] == 'fasum'): ?>
                                            <span class="badge badge-fasum tipe-badge">🏛️ FASUM</span>
                                        <?php else: ?>
                                            <span class="badge badge-nardi tipe-badge">📡 NARDI</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($p['id_pelanggan']) ?></strong></td>
                                    <td><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($p['nama_paket'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['nama_router'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($is_online): ?>
                                            <span class="badge bg-success"><i class="fas fa-circle"></i> Online</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-circle"></i> Offline</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($mk['ip_address']) ?></td>
                                    <td>
                                    <?php
                                    $rx_power = $mikrotik_map[$p['id_pelanggan']]['rx'] ?? '-';
                                    $rx_class = 'secondary';
                                    $rx_val = floatval(preg_replace('/[^0-9.-]/', '', $rx_power));
                                    if (is_numeric($rx_val)) {
                                        if ($rx_val >= -18) $rx_class = 'primary';
                                        elseif ($rx_val >= -24) $rx_class = 'success';
                                        else $rx_class = 'danger';
                                    }
                                    ?>
                                    <span class="badge bg-<?= $rx_class ?> rx-power-badge"><?= htmlspecialchars($rx_power) ?></span>
                                    </td>
                                    <td>
                                        <a href="detail.php?id=<?= urlencode($p['id_pelanggan']) ?>" class="btn btn-sm btn-info" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($can_edit): ?>
                                        <button class="btn btn-sm btn-warning" onclick="editPelanggan(<?= $p['id'] ?>)" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="hapusPelanggan(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_lengkap']) ?>')" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-center text-muted">Belum ada pelanggan khusus</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit Pelanggan -->
<div class="modal fade" id="modalPelanggan" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus-circle"></i> Tambah Pelanggan Khusus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="form_id">
                <input type="hidden" id="form_action" value="tambah_pelanggan">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ID Pelanggan / PPPoE <span class="text-danger">*</span></label>
                        <input type="text" id="form_id_pelanggan" class="form-control" placeholder="Contoh: vip001">
                        <small class="text-muted">Harus sama dengan PPPoE username di Mikrotik</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" id="form_nama_lengkap" class="form-control" placeholder="Nama pelanggan">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipe Pelanggan <span class="text-danger">*</span></label>
                        <select id="form_tipe_pelanggan" class="form-select">
                            <option value="vip">⭐ VIP</option>
                            <option value="fasum">🏛️ FASUM</option>
                            <option value="nardi">📡 NARDI</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Paket Internet</label>
                        <select id="form_paket_id" class="form-select">
                            <option value="">-- Pilih Paket --</option>
                            <?php foreach ($paket_list as $pk): ?>
                                <option value="<?= $pk['id'] ?>">
                                    <?= htmlspecialchars($pk['nama_paket']) ?> 
                                    (<?= htmlspecialchars($pk['nama_router'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Jatuh Tempo</label>
                        <input type="date" id="form_jatuh_tempo" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select id="form_status" class="form-select">
                            <option value="active">Aktif</option>
                            <option value="inactive">Tidak Aktif</option>
                            <option value="blocked">Diblokir</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">No Telepon</label>
                        <input type="text" id="form_no_telepon" class="form-control" placeholder="628xxxxxxxxxx">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea id="form_alamat" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning" onclick="simpanPelanggan()">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <span id="form_status_msg" class="ms-3"></span>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// PENCARIAN & FILTER
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const filterTipe = document.getElementById('filterTipe');
    const filterStatus = document.getElementById('filterStatus');
    
    if (searchInput) {
        searchInput.addEventListener('keyup', applyFilter);
        filterTipe.addEventListener('change', applyFilter);
        filterStatus.addEventListener('change', applyFilter);
    }
});

function applyFilter() {
    const search = document.getElementById('searchInput').value.toLowerCase().trim();
    const tipe = document.getElementById('filterTipe').value.toLowerCase();
    const status = document.getElementById('filterStatus').value.toLowerCase();
    
    const rows = document.querySelectorAll('.table-khusus tbody tr');
    let visible = 0;
    
    rows.forEach(row => {
        // Skip baris "belum ada data"
        if (row.querySelector('td[colspan]')) return;
        
        const text = row.textContent.toLowerCase();
        const tipeCell = row.children[0]?.textContent.toLowerCase() || '';
        const statusCell = row.children[5]?.textContent.toLowerCase() || '';
        
        const matchSearch = !search || text.includes(search);
        const matchTipe = !tipe || tipeCell.includes(tipe);
        const matchStatus = !status || statusCell.includes(status);
        
        if (matchSearch && matchTipe && matchStatus) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('visibleCount').textContent = visible;
    document.getElementById('badgeCount').textContent = visible + ' pelanggan';
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterTipe').value = '';
    document.getElementById('filterStatus').value = '';
    applyFilter();
}

function tambahPelanggan() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Tambah Pelanggan Khusus';
    document.getElementById('form_action').value = 'tambah_pelanggan';
    document.getElementById('form_id').value = '';
    document.getElementById('form_id_pelanggan').value = '';
    document.getElementById('form_id_pelanggan').readOnly = false;
    document.getElementById('form_nama_lengkap').value = '';
    document.getElementById('form_tipe_pelanggan').value = 'vip';
    document.getElementById('form_paket_id').value = '';
    document.getElementById('form_jatuh_tempo').value = '';
    document.getElementById('form_status').value = 'active';
    document.getElementById('form_no_telepon').value = '';
    document.getElementById('form_alamat').value = '';
    document.getElementById('form_status_msg').innerHTML = '';
    new bootstrap.Modal(document.getElementById('modalPelanggan')).show();
}

function editPelanggan(id) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Pelanggan Khusus';
    document.getElementById('form_action').value = 'edit_data';
    document.getElementById('form_status_msg').innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Memuat data...</span>';
    
    const formData = new FormData();
    formData.append('action', 'get_pelanggan');
    formData.append('pelanggan_id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.data) {
            const p = data.data;
            document.getElementById('form_id').value = p.id;
            document.getElementById('form_id_pelanggan').value = p.id_pelanggan || '';
            document.getElementById('form_nama_lengkap').value = p.nama_lengkap || '';
            document.getElementById('form_tipe_pelanggan').value = p.tipe_pelanggan || 'vip';
            document.getElementById('form_paket_id').value = p.paket_id || '';
            document.getElementById('form_jatuh_tempo').value = p.jatuh_tempo ? p.jatuh_tempo.split(' ')[0] : '';
            document.getElementById('form_status').value = p.status || 'active';
            document.getElementById('form_no_telepon').value = p.no_telepon || '';
            document.getElementById('form_alamat').value = p.alamat || '';
            document.getElementById('form_status_msg').innerHTML = '';
            new bootstrap.Modal(document.getElementById('modalPelanggan')).show();
        } else {
            alert('Gagal memuat data');
        }
    })
    .catch(err => {
        document.getElementById('form_status_msg').innerHTML = '<span class="text-danger">Error: ' + err.message + '</span>';
    });
}

function simpanPelanggan() {
    const action = document.getElementById('form_action').value;
    const id = document.getElementById('form_id').value;
    const id_pelanggan = document.getElementById('form_id_pelanggan').value.trim();
    const nama_lengkap = document.getElementById('form_nama_lengkap').value.trim();
    const alamat = document.getElementById('form_alamat').value.trim();
    const no_telepon = document.getElementById('form_no_telepon').value.trim();
    const tipe_pelanggan = document.getElementById('form_tipe_pelanggan').value;
    const paket_id = document.getElementById('form_paket_id').value;
    const jatuh_tempo = document.getElementById('form_jatuh_tempo').value;
    const status = document.getElementById('form_status').value;
    
    if (!id_pelanggan) { alert('ID Pelanggan wajib diisi!'); return; }
    if (!nama_lengkap) { alert('Nama wajib diisi!'); return; }
    
    document.getElementById('form_status_msg').innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Menyimpan...</span>';
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('pelanggan_id', id);
    formData.append('id_pelanggan', id_pelanggan);
    formData.append('nama_lengkap', nama_lengkap);
    formData.append('alamat', alamat);
    formData.append('no_telepon', no_telepon);
    formData.append('tipe_pelanggan', tipe_pelanggan);
    formData.append('paket_id', paket_id);
    formData.append('jatuh_tempo', jatuh_tempo);
    formData.append('status', status);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('form_status_msg').innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> ' + data.message + '</span>';
            setTimeout(() => location.reload(), 1500);
        } else {
            document.getElementById('form_status_msg').innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> ' + data.message + '</span>';
        }
    })
    .catch(err => {
        document.getElementById('form_status_msg').innerHTML = '<span class="text-danger">Error: ' + err.message + '</span>';
    });
}

// ============================================
// SORTING TABEL
// ============================================
let currentSort = { column: -1, order: 'asc' };

document.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('th.sortable');
    
    headers.forEach(header => {
        header.addEventListener('click', function() {
            const column = parseInt(this.getAttribute('data-sort'));
            const order = (currentSort.column === column && currentSort.order === 'asc') ? 'desc' : 'asc';
            
            // Reset class
            headers.forEach(h => h.classList.remove('asc', 'desc'));
            this.classList.add(order);
            
            // Update current sort
            currentSort = { column: column, order: order };
            
            // Sort tabel
            sortTable(column, order);
        });
    });
});

function sortTable(column, order) {
    const tbody = document.querySelector('.table-khusus tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Skip jika tidak ada data
    if (rows.length === 0 || rows[0].querySelector('td[colspan]')) return;
    
    rows.sort((a, b) => {
        let aVal = a.children[column]?.textContent.trim() || '';
        let bVal = b.children[column]?.textContent.trim() || '';
        
        // Khusus kolom Status (kolom 5) — urutkan berdasarkan Online/Offline
        if (column === 5) {
            aVal = aVal.includes('Online') ? '1' : '0';
            bVal = bVal.includes('Online') ? '1' : '0';
        }
        
        // Khusus kolom Tipe (kolom 0) — urutkan berdasarkan VIP/FASUM/NARDI
        if (column === 0) {
            const tipeOrder = { 'VIP': '1', 'FASUM': '2', 'NARDI': '3' };
            for (let key in tipeOrder) {
                if (aVal.includes(key)) aVal = tipeOrder[key];
                if (bVal.includes(key)) bVal = tipeOrder[key];
            }
        }
        
        // Coba parse sebagai angka
        const aNum = parseFloat(aVal);
        const bNum = parseFloat(bVal);
        if (!isNaN(aNum) && !isNaN(bNum)) {
            aVal = aNum;
            bVal = bNum;
        } else {
            aVal = aVal.toLowerCase();
            bVal = bVal.toLowerCase();
        }
        
        if (order === 'asc') {
            return aVal > bVal ? 1 : (aVal < bVal ? -1 : 0);
        } else {
            return aVal < bVal ? 1 : (aVal > bVal ? -1 : 0);
        }
    });
    
    // Re-append rows
    rows.forEach(row => tbody.appendChild(row));
}

function hapusPelanggan(id, nama) {
    if (!confirm('Yakin ingin menghapus pelanggan khusus "' + nama + '"?')) return;
    
    const formData = new FormData();
    formData.append('action', 'hapus_pelanggan');
    formData.append('pelanggan_id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
        else alert('Gagal: ' + data.message);
    })
    .catch(err => alert('Error: ' + err.message));
}
</script>

<?php include '../../includes/footer.php'; ?>
