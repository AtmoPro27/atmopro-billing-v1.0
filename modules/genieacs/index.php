<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';
?>
<!-- DataTables JS — SETELAH jQuery dari header.php -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<?php
require_once '../../includes/genieacs_functions.php';

// Ambil setting GenieACS untuk form
$genieacs_config = getGenieACSSetting();

// Ambil semua devices
$all_devices = getGenieACSDevices(2000);

// Hitung statistik
$total_devices_count = count($all_devices);
$online_count = 0;
$offline_count = 0;
$rx_bagus = 0;
$rx_sedang = 0;
$rx_kritis = 0;
$rx_kosong = 0;

foreach ($all_devices as $d) {
    $status = $d['status'] ?? 'Offline';
    if ($status == 'Online') {
        $online_count++;
    } else {
        $offline_count++;
    }
    
    $rx = $d['rx'] ?? '-';
    $rx_val = floatval(preg_replace('/[^0-9.-]/', '', $rx));
    if ($rx == '-' || $rx == '' || $rx_val == 0) {
        $rx_kosong++;
    } elseif ($rx_val >= -18) {
        $rx_bagus++;
    } elseif ($rx_val >= -24) {
        $rx_sedang++;
    } else {
        $rx_kritis++;
    }
}

$persen_online = $total_devices_count > 0 ? round(($online_count / $total_devices_count) * 100, 1) : 0;
$persen_offline = $total_devices_count > 0 ? round(($offline_count / $total_devices_count) * 100, 1) : 0;
$persen_rx_bagus = $total_devices_count > 0 ? round(($rx_bagus / $total_devices_count) * 100, 1) : 0;
$persen_rx_sedang = $total_devices_count > 0 ? round(($rx_sedang / $total_devices_count) * 100, 1) : 0;
$persen_rx_kritis = $total_devices_count > 0 ? round(($rx_kritis / $total_devices_count) * 100, 1) : 0;
$persen_offline = $total_devices_count > 0 ? round(($offline_count / $total_devices_count) * 100, 1) : 0;
$persen_rx_bagus = $total_devices_count > 0 ? round(($rx_bagus / $total_devices_count) * 100, 1) : 0;
$persen_rx_sedang = $total_devices_count > 0 ? round(($rx_sedang / $total_devices_count) * 100, 1) : 0;
$persen_rx_kritis = $total_devices_count > 0 ? round(($rx_kritis / $total_devices_count) * 100, 1) : 0;

// Ambil nama pelanggan dari database
$customer_names = [];
try {
    require_once __DIR__ . '/../../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT id_pelanggan, nama_lengkap FROM pelanggan WHERE status = 'active' OR status_aktif = 'aktif'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customer_names[$row['id_pelanggan']] = $row['nama_lengkap'];
    }
} catch(PDOException $e) {
    $customer_names = [];
}

// Tambahkan nama pelanggan
foreach ($all_devices as &$device) {
    $customer_id = $device['id'];
    $device['customer_name'] = $customer_names[$customer_id] ?? '-';
}
unset($device);

// ============================================
// SORTING & SEARCH (SERVER-SIDE)
// ============================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) && strtolower($_GET['order']) == 'desc' ? 'desc' : 'asc';

// Filter pencarian
if ($search !== '') {
    $searchLower = strtolower($search);
    $all_devices = array_filter($all_devices, function($d) use ($searchLower) {
        return (
            strpos(strtolower($d['id'] ?? ''), $searchLower) !== false ||
            strpos(strtolower($d['customer_name'] ?? ''), $searchLower) !== false ||
            strpos(strtolower($d['ssid'] ?? ''), $searchLower) !== false ||
            strpos(strtolower($d['ip_pppoe'] ?? ''), $searchLower) !== false ||
            strpos(strtolower($d['status'] ?? ''), $searchLower) !== false
        );
    });
    $all_devices = array_values($all_devices);
}

// Sorting
usort($all_devices, function($a, $b) use ($sort, $order) {
    // Custom sort untuk 'id' (PPPoE Username) — numerik dulu, huruf belakangan
    if ($sort === 'id') {
        $aId = $a['id'] ?? '';
        $bId = $b['id'] ?? '';
        $aNum = is_numeric($aId) ? (int)$aId : PHP_INT_MAX;
        $bNum = is_numeric($bId) ? (int)$bId : PHP_INT_MAX;
        if ($aNum != $bNum) {
            return $order === 'asc' ? ($aNum < $bNum ? -1 : 1) : ($aNum > $bNum ? -1 : 1);
        }
        // Fallback string compare untuk yang bukan angka
        $aVal = strtolower($aId);
        $bVal = strtolower($bId);
        if ($aVal == $bVal) return 0;
        return $order === 'asc' ? ($aVal < $bVal ? -1 : 1) : ($aVal > $bVal ? -1 : 1);
    }
    
    if ($sort === 'status') {
        $aVal = ($a['status'] ?? '') === 'Online' ? 1 : 0;
        $bVal = ($b['status'] ?? '') === 'Online' ? 1 : 0;
    } elseif ($sort === 'rx') {
        $aVal = floatval(preg_replace('/[^0-9.-]/', '', $a['rx'] ?? ''));
        $bVal = floatval(preg_replace('/[^0-9.-]/', '', $b['rx'] ?? ''));
    } else {
        $aVal = strtolower($a[$sort] ?? '');
        $bVal = strtolower($b[$sort] ?? '');
    }
    if ($aVal == $bVal) return 0;
    if ($order === 'asc') {
        return $aVal < $bVal ? -1 : 1;
    } else {
        return $aVal > $bVal ? -1 : 1;
    }
});

// ============================================
// PAGINATION (SERVER-SIDE)
// ============================================
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$total_devices = count($all_devices);
$total_pages = max(1, (int)ceil($total_devices / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;
$devices = array_slice($all_devices, $offset, $per_page);
?>

<style>
    .table-responsive { overflow-x: auto; }
    .table thead th { background-color: #f8f9fc; }
    .badge-online { background-color: #28a745; }
    .badge-offline { background-color: #dc3545; }
    .rx-bagus { color: #0066cc; font-weight: bold; background-color: #cce5ff; border-radius: 4px; padding: 2px 8px; display: inline-block; }
    .rx-sedang { color: #28a745; font-weight: bold; background-color: #d4edda; border-radius: 4px; padding: 2px 8px; display: inline-block; }
    .rx-kritis { color: #dc3545; font-weight: bold; background-color: #f8d7da; border-radius: 4px; padding: 2px 8px; display: inline-block; }
    .pagination-nav { margin-top: 15px; display: flex; justify-content: center; gap: 5px; flex-wrap: wrap; }
    .pagination-nav a, .pagination-nav span { padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #0d5c1e; background: white; font-size: 13px; }
    .pagination-nav a:hover { background: #0d5c1e; color: white; }
    .pagination-nav .active { background: #0d5c1e; color: white; border-color: #0d5c1e; }
    .pagination-nav .disabled { color: #999; background: #f5f5f5; }
    /* Fix cursor DataTables pagination */
    .dataTables_wrapper .paginate_button {
        cursor: pointer !important;
    }
    .dataTables_wrapper .paginate_button.disabled {
        cursor: not-allowed !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        cursor: default !important;
    }
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        cursor: pointer;
    }

/* ============================================ */
/* Pagination DataTables — RAPI & MODERN        */
/* ============================================ */
.dataTables_wrapper .dataTables_paginate {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    margin-top: 15px;
    padding: 10px 0;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    margin: 0 2px;
    border: 1px solid #ddd !important;
    border-radius: 6px !important;
    background: white !important;
    color: #0d5c1e !important;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none !important;
    transition: all 0.2s ease;
    cursor: pointer !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #0d5c1e !important;
    color: white !important;
    border-color: #0d5c1e !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(13,92,30,0.2);
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: #0d5c1e !important;
    color: white !important;
    border-color: #0d5c1e !important;
    cursor: default !important;
    box-shadow: 0 2px 4px rgba(13,92,30,0.3);
}

.dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
.dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
    background: #f5f5f5 !important;
    color: #999 !important;
    border-color: #e5e5e5 !important;
    cursor: not-allowed !important;
    transform: none;
    box-shadow: none;
}

.dataTables_wrapper .dataTables_info {
    font-size: 13px;
    color: #666;
    padding-top: 10px;
}

.dataTables_wrapper .dataTables_length {
    font-size: 13px;
    margin-bottom: 10px;
}

.dataTables_wrapper .dataTables_length select {
    padding: 5px 25px 5px 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    background: white;
}

.dataTables_wrapper .dataTables_filter input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    outline: none;
    transition: border-color 0.2s;
}

.dataTables_wrapper .dataTables_filter input:focus {
    border-color: #0d5c1e;
    box-shadow: 0 0 0 3px rgba(13,92,30,0.1);
}

@media (max-width: 768px) {
    .dataTables_wrapper .dataTables_paginate {
        flex-wrap: wrap;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        min-width: 32px;
        height: 32px;
        font-size: 12px;
        padding: 0 8px;
    }
}

</style>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

<!-- DataTables -->

<!-- DataTables CSS -->
<!-- DataTables JS — HARUS SEBELUM init -->
<!-- DataTables JS — HARUS SEBELUM init -->

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $_SESSION['success_message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">📡 Monitoring Perangkat</h5>
    <div>
        <a href="log_setting.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-history"></i> Log Perubahan
        </a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSettingGenieACS">
            <i class="fas fa-cog"></i> Setting GenieACS
        </button>
    </div>
</div>

<script>
// ============================================
// DATATABLES SEARCH (SEMUA DATA)
// ============================================
let dataTable;

jQuery(document).ready(function() {
    if (!jQuery.fn.DataTable) {
        console.warn('DataTables not loaded');
        return;
    }
    
    dataTable = jQuery('#deviceTable').DataTable({
        "paging": false,
        "info": false,
        "ordering": true,
        "stateSave": false,
        "order": [],
        "language": {
            "search": "Cari:",
            "lengthMenu": "Tampilkan _MENU_ entri",
            "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ device",
            "infoEmpty": "Menampilkan 0 sampai 0 dari 0 device",
            "infoFiltered": "(disaring dari _MAX_ device keseluruhan)",
            "zeroRecords": "Tidak ada device yang cocok",
            "paginate": {
                "first": "Pertama",
                "last": "Terakhir",
                "next": "Selanjutnya",
                "previous": "Sebelumnya"
            }
        }
    });
    
});

function testKoneksi() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    btn.disabled = true;
    
    const form = document.querySelector('#modalSettingGenieACS form');
    const formData = new FormData(form);
    
    fetch('test_koneksi.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        const icon = data.success ? '✅' : '❌';
        let msg = icon + ' ' + data.message;
        if (data.time) msg += '\n⏱️ ' + data.time + 'ms';
        if (data.code) msg += '\n📡 HTTP ' + data.code;
        alert(msg);
    })
    .catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('❌ Error: ' + err.message);
    });
}
</script>

<!-- Modal Setting GenieACS -->
<div class="modal fade" id="modalSettingGenieACS" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="save_setting.php">
                <div class="modal-header">
                    <h5 class="modal-title">⚙️ Setting GenieACS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><strong>URL/API GenieACS</strong></label>
                        <input type="text" name="genieacs_url" class="form-control" 
                               value="<?= htmlspecialchars($genieacs_config['genieacs_url'] ?? 'http://localhost:7557') ?>"
                               placeholder="http://localhost:7557" required>
                        <small class="text-muted">Contoh: http://localhost:7557</small>
                    </div>
                    <div class="form-group">
                        <label><strong>Username</strong></label>
                        <input type="text" name="genieacs_username" class="form-control"
                               value="<?= htmlspecialchars($genieacs_config['genieacs_username'] ?? '') ?>"
                               placeholder="admin">
                    </div>
                    <div class="form-group">
                        <label><strong>Password</strong></label>
                        <input type="password" name="genieacs_password" class="form-control"
                               value="<?= htmlspecialchars($genieacs_config['genieacs_password'] ?? '') ?>"
                               placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label><strong>Timeout (detik)</strong></label>
                        <input type="number" name="genieacs_timeout" class="form-control"
                               value="<?= htmlspecialchars($genieacs_config['genieacs_timeout'] ?? '30') ?>"
                               min="5" max="120">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-info" onclick="testKoneksi()">
                        <i class="fas fa-plug"></i> Test Koneksi
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- STATISTIK GENIEACS -->
<div class="row mb-3">
    <div class="col-md-2 col-6 mb-2">
        <div class="card bg-primary text-white shadow">
            <div class="card-body text-center p-2">
                <i class="fas fa-server fa-2x"></i>
                <h3 class="mb-0 mt-1"><?= $total_devices_count ?></h3>
                <small>Total Device</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="card bg-success text-white shadow">
            <div class="card-body text-center p-2">
                <i class="fas fa-wifi fa-2x"></i>
                <h3 class="mb-0 mt-1"><?= $online_count ?></h3>
                <small>Online (<?= $persen_online ?>%)</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="card bg-danger text-white shadow">
            <div class="card-body text-center p-2">
                <i class="fas fa-plug fa-2x"></i>
                <h3 class="mb-0 mt-1"><?= $offline_count ?></h3>
                <small>Offline (<?= $persen_offline ?>%)</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="card bg-info text-white shadow">
            <div class="card-body text-center p-2">
                <i class="fas fa-signal fa-2x"></i>
                <h3 class="mb-0 mt-1"><?= $rx_bagus ?></h3>
                <small>RX Bagus (<?= $persen_rx_bagus ?>%)</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="card bg-warning text-dark shadow">
            <div class="card-body text-center p-2">
                <i class="fas fa-exclamation-triangle fa-2x"></i>
                <h3 class="mb-0 mt-1"><?= $rx_sedang ?></h3>
                <small>RX Sedang (<?= $persen_rx_sedang ?>%)</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6 mb-2">
        <div class="card bg-dark text-white shadow">
            <div class="card-body text-center p-2">
                <i class="fas fa-times-circle fa-2x"></i>
                <h3 class="mb-0 mt-1"><?= $rx_kritis ?></h3>
                <small>RX Kritis (<?= $persen_rx_kritis ?>%)</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-tower-broadcast"></i> GenieACS Manager - Monitoring Perangkat
        <span class="badge bg-info float-end">Total: <?= $total_devices ?> device</span>
    </div>
    <div class="card-body">
        <!-- Search & Filter -->
        <div class="row mb-3">
            <div class="col-md-6">

            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" id="deviceTable">
                <thead>
                    <tr>
                        <?php
                        function sortLink($col, $label, $currentSort, $currentOrder, $search) {
                            $newOrder = ($currentSort == $col && $currentOrder == 'asc') ? 'desc' : 'asc';
                            $icon = '';
                            if ($currentSort == $col) {
                                $icon = $currentOrder == 'asc' ? ' ↑' : ' ↓';
                            } else {
                                $icon = ' ⇅';
                            }
                            $url = '?sort=' . $col . '&order=' . $newOrder;
                            if ($search !== '') $url .= '&search=' . urlencode($search);
                            return '<a href="' . $url . '" class="text-decoration-none text-dark">' . $label . $icon . '</a>';
                        }
                        ?>
                        <th><?= sortLink('status', 'Status', $sort, $order, $search) ?></th>
                        <th><?= sortLink('id', 'ID', $sort, $order, $search) ?></th>
                        <th><?= sortLink('customer_name', 'Nama', $sort, $order, $search) ?></th>
                        <th><?= sortLink('ssid', 'SSID', $sort, $order, $search) ?></th>
                        <th><?= sortLink('wifi_password', 'Wifi Password', $sort, $order, $search) ?></th>
                        <th><?= sortLink('rx', 'RX Power', $sort, $order, $search) ?></th>
                        <th><?= sortLink('temp', 'Temp', $sort, $order, $search) ?></th>
                        <th><?= sortLink('ip_pppoe', 'IP PPPoE', $sort, $order, $search) ?></th>
                        <th><?= sortLink('last_comm', 'Last Comm', $sort, $order, $search) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($devices)): ?>
                        <?php foreach ($devices as $d): ?>
                            <?php
                            $rxClass = '';
                            $rxVal = floatval(preg_replace('/[^0-9.-]/', '', $d['rx'] ?? ''));
                            if (is_numeric($rxVal) && $rxVal != 0) {
                                if ($rxVal >= -18) $rxClass = 'rx-bagus';
                                elseif ($rxVal >= -24) $rxClass = 'rx-sedang';
                                else $rxClass = 'rx-kritis';
                            }
                            $pass = $d['wifi_password'] ?? '';
                            if ($pass == '********' || empty($pass)) $pass = '******';
                            ?>
                            <tr>
                                <td><span class="badge <?= $d['status'] == 'Online' ? 'badge-online' : 'badge-offline' ?>" style="color:white; padding:5px 10px; border-radius:20px;"><?= $d['status'] ?? 'Offline' ?></span></td>
                                <td><a href="/modules/customers/detail.php?id=<?= urlencode($d['id'] ?? '') ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($d['id'] ?? '-') ?></a></td>
                                <td><?= htmlspecialchars($d['customer_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['ssid'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($pass) ?></td>
                                <td><span class="<?= $rxClass ?>"><?= htmlspecialchars($d['rx'] ?? '-') ?></span></td>
                                <td><?= htmlspecialchars($d['temp'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['ip_pppoe'] ?? '-') ?></td>
                                <td><small><?= htmlspecialchars($d['last_comm'] ?? '-') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <?php
                $qs = $_GET;
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($total_pages, $page + $range);
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($qs, ['page' => 1])) ?>">&laquo;</a>
                </li>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($qs, ['page' => $page - 1])) ?>">&lsaquo;</a>
                </li>
                <?php if ($start > 1): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($qs, ['page' => $i])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($end < $total_pages): ?>
                <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($qs, ['page' => $page + 1])) ?>">&rsaquo;</a>
                </li>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($qs, ['page' => $total_pages])) ?>">&raquo;</a>
                </li>
            </ul>
            <p class="text-center text-muted small">
                Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_devices ?> total device)
            </p>
        </nav>
        <?php endif; ?>
    </div>
</div>



<!-- DataTables JS — SETELAH jQuery -->

<!-- DataTables JS — SETELAH jQuery -->

<?php include '../../includes/footer.php'; ?>
