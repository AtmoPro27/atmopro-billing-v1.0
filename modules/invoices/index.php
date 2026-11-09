<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';
require_once '../../config/database.php';
$pdo = Database::getInstance()->getConnection();

$is_superadmin = false;
try {
    $stmt_check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    $user_data = $stmt_check->fetch();
    $is_superadmin = ($user_data && $user_data['role'] == 'super_admin');
} catch(Exception $e) {
    $is_superadmin = false;
}

$success_msg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) $success_msg = 'Tagihan berhasil dibuat!';
    elseif ($_GET['success'] == 2) $success_msg = 'Pembayaran berhasil dicatat!';
} elseif (isset($_GET['delete'])) {
    $success_msg = 'Tagihan berhasil dihapus!';
}

// Filter status dari GET
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
?>

<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">

<div class="card">
    <div class="card-header">
        <i class="fas fa-file-invoice"></i> Manajemen Tagihan
        <div class="float-end d-flex gap-2 align-items-center">
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Buat Tagihan Baru
            </a>
            <form method="GET" action="export_tagihan_pdf.php" target="_blank" class="d-inline-flex gap-1 align-items-center" style="margin:0;">
                <select name="bulan" class="form-select form-select-sm" style="width:auto;">
                    <?php
                    $nama_bulan_opt = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
                    for ($i = 1; $i <= 12; $i++):
                    ?>
                        <option value="<?= $i ?>" <?= $i == (int)date('n') ? 'selected' : '' ?>>
                            <?= $nama_bulan_opt[$i-1] ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <input type="number" name="tahun" class="form-control form-control-sm" value="<?= date('Y') ?>" min="2000" max="2100" style="width:80px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        
        <!-- ========================================== -->
        <!-- TOMBOL FILTER STATUS -->
        <!-- ========================================== -->
        <div class="mb-3">
            <label class="fw-bold me-2">Filter Status:</label>
            <div class="btn-group" role="group">
                <a href="?status=all" class="btn btn-sm <?= $filter_status == 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">Semua</a>
                <a href="?status=paid" class="btn btn-sm <?= $filter_status == 'paid' ? 'btn-success' : 'btn-outline-success' ?>">✅ Lunas</a>
                <a href="?status=pending" class="btn btn-sm <?= $filter_status == 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">⏳ Pending</a>
            </div>
        </div>
        
        <form method="get" class="mb-3 d-flex" id="searchForm" style="max-width:400px;">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" id="searchInput" class="form-control"
                       placeholder="Cari no invoice / nama / ID pelanggan..."
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <?php if ($filter_status != 'all'): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <?php endif; ?>
                <?php if ($search !== '' || $filter_status != 'all'): ?>
                <a href="index.php" class="btn btn-outline-secondary" title="Clear">
                    <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table id="invoicesTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No Invoice</th>
                        <th>ID Pelanggan</th>
                        <th>Nama Pelanggan</th>
                        <th>Tanggal Terbit</th>
                        <th>Jatuh Tempo</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Tgl Bayar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Pagination
                    $per_page = 10;
                    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

                    $where = "";
                    $params = [];
                    $cond = [];

                    if ($filter_status != 'all') {
                        $cond[] = "i.status = ?";
                        $params[] = $filter_status;
                    }

                    if ($search !== '') {
                        $cond[] = "(i.no_invoice LIKE ? OR p.nama_lengkap LIKE ? OR p.id_pelanggan LIKE ?)";
                        $kw = '%' . $search . '%';
                        $params[] = $kw;
                        $params[] = $kw;
                        $params[] = $kw;
                    }

                    if (!empty($cond)) {
                        $where = " WHERE " . implode(" AND ", $cond);
                    }

                    $sql_count = "SELECT COUNT(*) FROM invoice i LEFT JOIN pelanggan p ON i.pelanggan_id = p.id" . $where;
                    $stmt_count = $pdo->prepare($sql_count);
                    $stmt_count->execute($params);
                    $total_tagihan = (int)$stmt_count->fetchColumn();
                    $total_pages = max(1, (int)ceil($total_tagihan / $per_page));
                    if ($page > $total_pages) $page = $total_pages;
                    $offset = ($page - 1) * $per_page;

                    $sql = "
                        SELECT i.*, p.nama_lengkap, p.id_pelanggan
                        FROM invoice i
                        LEFT JOIN pelanggan p ON i.pelanggan_id = p.id
                    " . $where . " ORDER BY i.id DESC LIMIT ? OFFSET ?";
                    $stmt = $pdo->prepare($sql);
                    $bind_idx = 1;
                    foreach ($params as $p) {
                        $stmt->bindValue($bind_idx++, $p);
                    }
                    $stmt->bindValue($bind_idx++, $per_page, PDO::PARAM_INT);
                    $stmt->bindValue($bind_idx, $offset, PDO::PARAM_INT);
                    $stmt->execute();
                    while ($inv = $stmt->fetch()):
                        $status_badge = '';
                        if ($inv['status'] == 'paid') {
                            $status_badge = '<span class="badge bg-success">Lunas</span>';
                        } elseif ($inv['status'] == 'pending') {
                            $status_badge = '<span class="badge bg-warning">Pending</span>';
                        } elseif ($inv['status'] == 'cancelled') {
                            $status_badge = '<span class="badge bg-danger">Dibatalkan</span>';
                        } else {
                            $status_badge = '<span class="badge bg-secondary">' . $inv['status'] . '</span>';
                        }
                        $tgl_terbit = (!empty($inv['tgl_terbit']) && $inv['tgl_terbit'] != '0000-00-00 00:00:00') ? date('d-m-Y', strtotime($inv['tgl_terbit'])) : '-';
                        $tgl_jatuh_tempo = (!empty($inv['tgl_jatuh_tempo']) && $inv['tgl_jatuh_tempo'] != '0000-00-00 00:00:00') ? date('d-m-Y', strtotime($inv['tgl_jatuh_tempo'])) : '-';
                        $tgl_bayar = (!empty($inv['tgl_bayar']) && $inv['tgl_bayar'] != '0000-00-00 00:00:00') ? date('d-m-Y', strtotime($inv['tgl_bayar'])) : '-';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['no_invoice']) ?></td>
                            <td><?= htmlspecialchars($inv['id_pelanggan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($inv['nama_lengkap'] ?? $inv['pelanggan_id']) ?></td>
                            <td><?= $tgl_terbit ?></td>
                            <td><?= $tgl_jatuh_tempo ?></td>
                            <td>Rp <?= number_format($inv['jumlah'], 0, ',', '.') ?></td>
                            <td><?= $status_badge ?></td>
                            <td><?= $tgl_bayar ?></td>
                            <td>
                                <a href="view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-info">Detail</a>
                                <?php if ($inv['status'] != 'paid'): ?>
                                    <a href="pay.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-success">Bayar</a>
                                <?php endif; ?>
                                <?php if ($is_superadmin): ?>
                                    <a href="delete.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus tagihan ini?')">Hapus</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
                Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_tagihan ?> total tagihan)
            </p>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#invoicesTable').DataTable({
        paging: false,
        info: false,
        searching: false,
        language: {
            url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json"
        },
        order: []
    });

    // Auto-submit search (debounce 500ms)
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');
    if (searchInput && searchForm) {
        let timer = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(function() {
                searchForm.submit();
            }, 500);
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
