<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

$user_role = $_SESSION['user_role'] ?? 'guest';
$is_admin = ($user_role == 'super_admin' || $user_role == 'admin');

// Filter status dari URL
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page_title = "Data Pelanggan";
if ($status_filter == 'inactive') {
    $page_title = "Data Pelanggan Isolir";
} elseif ($status_filter == 'active') {
    $page_title = "Data Pelanggan Aktif";
} elseif ($status_filter == 'blocked') {
    $page_title = "Data Pelanggan Blokir";
}

// Notifikasi dari berbagai sumber
$success_msg = '';
$error_msg = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) $success_msg = 'Data berhasil ditambahkan!';
    elseif ($_GET['success'] == 2) $success_msg = 'Data berhasil diupdate!';
    elseif ($_GET['success'] == 'delete') $success_msg = 'Data berhasil dihapus!';
}

// Notifikasi dari aktivasi
if (isset($_GET['activation_success'])) {
    if (isset($_GET['msg']) && !empty($_GET['msg'])) {
        $success_msg = urldecode($_GET['msg']);
    } else {
        $success_msg = 'Pelanggan berhasil diaktifkan!';
    }
}
if (isset($_GET['activation_error'])) {
    $error_msg = 'Gagal aktivasi: ' . htmlspecialchars($_GET['activation_error']);
}

// Notifikasi dari session
if (isset($_SESSION['activation_message'])) {
    $success_msg = $_SESSION['activation_message'];
    unset($_SESSION['activation_message']);
}
?>

<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">

<div class="card">
    <div class="card-header">
        <a href="sync_to_mikrotik.php" class="btn btn-info btn-sm float-end ms-2" target="_blank"><i class="fas fa-sync-alt"></i> Sinkronisasi ke MikroTik</a>
        <i class="fas fa-users"></i> <?= $page_title ?>
        <div class="float-end">
            <?php if ($is_admin): ?>
                <a href="export_csv.php" class="btn btn-success btn-sm me-1"><i class="fas fa-file-excel"></i> Export CSV</a>
                <a href="import_excel.php" class="btn btn-info btn-sm me-1"><i class="fas fa-file-import"></i> Import CSV</a>
                <a href="add.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah</a>
            <?php else: ?>
                <a href="export_csv.php" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Export CSV</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= nl2br(htmlspecialchars($success_msg)) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?= $error_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Tombol filter -->
        <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
            <?php
            $qs_filter = $search !== '' ? '&search=' . urlencode($search) : '';
            ?>
            <a href="index.php?<?= $qs_filter ? ltrim($qs_filter, '&') : '' ?>" class="btn btn-sm btn-secondary">Semua</a>
            <a href="index.php?status=active<?= $qs_filter ?>" class="btn btn-sm btn-success">Aktif</a>
            <a href="index.php?status=inactive<?= $qs_filter ?>" class="btn btn-sm btn-danger">Isolir</a>
            <a href="index.php?status=blocked<?= $qs_filter ?>" class="btn btn-sm btn-dark">Blokir</a>

            <form method="get" class="d-flex ms-auto" style="max-width:300px;">
                <?php if ($status_filter != 'all'): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <?php endif; ?>
                <input type="text" name="search" id="searchInput" class="form-control me-1" placeholder="Cari ID / nama / alamat / telp..." value="<?= htmlspecialchars($search) ?>" autocomplete="off" style="min-height:38px;">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($search !== ''): ?>
                    <a href="index.php<?= $status_filter != 'all' ? '?status=' . urlencode($status_filter) : '' ?>" class="btn btn-danger btn-sm ms-1" style="min-width:40px;min-height:32px;" title="Hapus pencarian"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="table-responsive">
            <style>
    .rx-power-badge {
        font-size: 14px;
        padding: 6px 12px;
        font-weight: bold;
        display: inline-block;
    }
    </style>
    <table id="customersTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID Pelanggan</th>
                        <th>Nama</th>
                        <th>Alamat</th>
                        <th>Paket</th>
                        <th>Jatuh Tempo</th>
                        <th>RX Power</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        require_once __DIR__ . "/../../config/database.php";
                        require_once __DIR__ . "/../../includes/genieacs_functions.php";
                        $pdo = Database::getInstance()->getConnection();
                        
                        // Pagination
                        $per_page = 10;
                        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

                        // Bangun kondisi WHERE
                        $where = " WHERE (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)";
                        if ($status_filter == 'inactive') {
                            $where .= " AND p.status = 'inactive'";
                        } elseif ($status_filter == 'active') {
                            $where .= " AND p.status = 'active'";
                        } elseif ($status_filter == 'blocked') {
                            $where .= " AND p.status = 'blocked'";
                        }

                        // Pencarian server-side
                        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                        $params_where = [];
                        if ($search !== '') {
                            $where .= " AND (p.id_pelanggan LIKE ? OR p.nama_lengkap LIKE ? OR p.alamat LIKE ? OR p.no_telepon LIKE ?)";
                            $kw = '%' . $search . '%';
                            $params_where = [$kw, $kw, $kw, $kw];
                        }

                        // Hitung total
                        $sql_count = "SELECT COUNT(*) FROM pelanggan p" . $where;
                        $stmt_count = $pdo->prepare($sql_count);
                        $stmt_count->execute($params_where);
                        $total_pelanggan = (int)$stmt_count->fetchColumn();
                        $total_pages = max(1, (int)ceil($total_pelanggan / $per_page));
                        if ($page > $total_pages) $page = $total_pages;
                        $offset = ($page - 1) * $per_page;

                        // Ambil data hanya halaman aktif
                        $sql = "SELECT p.id_pelanggan, p.nama_lengkap, p.alamat, p.no_telepon, p.jatuh_tempo, p.status, p.status_aktif, p.is_online, pk.nama_paket, pk.harga
                                FROM pelanggan p
                                LEFT JOIN paket_internet pk ON p.paket_id = pk.id" . $where . "
                                ORDER BY p.id_pelanggan ASC
                                LIMIT ? OFFSET ?";
                        $stmt = $pdo->prepare($sql);
                        $bind_idx = 1;
                        foreach ($params_where as $pw) {
                            $stmt->bindValue($bind_idx++, $pw);
                        }
                        $stmt->bindValue($bind_idx++, $per_page, PDO::PARAM_INT);
                        $stmt->bindValue($bind_idx, $offset, PDO::PARAM_INT);
                        $stmt->execute();
                        
                        $today = new DateTime();
                        $today->setTime(0, 0, 0);
                        
                        // Ambil RX Power dari GenieACS (batch)
                        $rx_map = [];
                        if (function_exists('getGenieACSDevices')) {
                            $genieacs_devices = getGenieACSDevices(2000);
                            foreach ($genieacs_devices as $gd) {
                                $rx_map[$gd['id']] = $gd['rx'] ?? '-';
                            }
                        }
                        
                        while ($row = $stmt->fetch()) {
                            $is_online = ($row['is_online'] == 1);
                            $online_badge = $is_online ? '<span class="badge bg-success ms-1">ONLINE</span>' : '<span class="badge bg-secondary ms-1">OFFLINE</span>';
                            
                            if ($row['status'] == 'active') {
                                $status_text = 'AKTIF';
                                $status_class = 'bg-success';
                            } elseif ($row['status'] == 'inactive') {
                                $status_text = 'ISOLIR';
                                $status_class = 'bg-danger';
                            } else {
                                $status_text = 'BLOKIR';
                                $status_class = 'bg-secondary';
                            }
                            $status_badge = '<span class="badge ' . $status_class . '">' . $status_text . '</span> ' . $online_badge;
                            
                            $paket_info = $row['nama_paket'] ? $row['nama_paket'] . '<br><small>Rp ' . number_format($row['harga'], 0, ',', '.') . '</small>' : '<span class="text-muted">-</span>';
                            
                            $jatuh_tempo_text = '-';
                            $jatuh_tempo_class = '';
                            if (!empty($row['jatuh_tempo']) && $row['jatuh_tempo'] != '0000-00-00 00:00:00') {
                                $dueDate = new DateTime($row['jatuh_tempo']);
                                $dueDate->setTime(0, 0, 0);
                                $jatuh_tempo_text = $dueDate->format('d-m-Y');
                                if ($dueDate < $today) $jatuh_tempo_class = 'class="text-danger fw-bold"';
                                elseif ($dueDate->format('Y-m-d') == $today->format('Y-m-d')) $jatuh_tempo_class = 'class="text-warning fw-bold"';
                            }
                            
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['id_pelanggan']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['nama_lengkap']) . '</td>';
                            echo '<td>' . nl2br(htmlspecialchars($row['alamat'])) . '</td>';
                            echo '<td>' . $paket_info . '</td>';
                            echo '<td ' . $jatuh_tempo_class . '>' . $jatuh_tempo_text . '</td>';
                            
                            // RX Power
                            $rx_power = $rx_map[$row['id_pelanggan']] ?? '-';
                            $rx_class = 'secondary';
                            $rx_val = floatval(preg_replace('/[^0-9.-]/', '', $rx_power));
                            if (is_numeric($rx_val)) {
                                if ($rx_val >= -18) $rx_class = 'primary';
                                elseif ($rx_val >= -24) $rx_class = 'success';
                                else $rx_class = 'danger';
                            }
                            echo '<td><span class="badge bg-' . $rx_class . ' rx-power-badge">' . htmlspecialchars($rx_power) . '</span></td>';
                            
                            echo '<td>' . $status_badge . '</td>';
                            
                            echo '<td class="text-center">';
                            echo '<a href="detail.php?id=' . $row['id_pelanggan'] . '" class="btn btn-sm btn-info">Detail</a> ';
                            
                            // Tombol Aktivasi untuk pelanggan yang masih on_progress
                            if ($row['status_aktif'] == 'on_progress') {
                                echo '<a href="activate_fix.php?id=' . $row['id_pelanggan'] . '" class="btn btn-sm btn-warning">Aktivasi</a> ';
                            }
                            
                            if ($is_admin) {
                                echo '<a href="edit.php?id=' . $row['id_pelanggan'] . '" class="btn btn-sm btn-primary">Edit</a> ';
                                echo '<a href="delete.php?id=' . $row['id_pelanggan'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Yakin?\')">Hapus</a>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                    } catch(PDOException $e) {
                        echo '<tr><td colspan="8">Error: ' . $e->getMessage() . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

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
                Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_pelanggan ?> total pelanggan)
            </p>
        </nav>
        <?php endif; ?>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#customersTable').DataTable({
        "paging": false,
        "info": false,
        "searching": false,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json"
        }
    });

    // Auto-search dengan debounce
    let searchTimer = null;
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const value = this.value;
            searchTimer = setTimeout(function() {
                const url = new URL(window.location.href);
                if (value.trim() === '') {
                    url.searchParams.delete('search');
                } else {
                    url.searchParams.set('search', value);
                }
                url.searchParams.delete('page'); // reset ke halaman 1
                window.location.href = url.toString();
            }, 400);
        });
        // Jangan fokus otomatis (biar keyboard HP tidak muncul)
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
