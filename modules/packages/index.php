<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

$success_msg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $success_msg = 'Paket berhasil ditambahkan!';
    } elseif ($_GET['success'] == 2) {
        $success_msg = 'Paket berhasil diupdate!';
    }
} elseif (isset($_GET['delete'])) {
    $success_msg = 'Paket berhasil dihapus!';
} elseif (isset($_GET['error'])) {
    $success_msg = 'Gagal menghapus paket!';
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-box"></i> Paket Internet
        <a href="add.php" class="btn btn-primary btn-sm float-end">
            <i class="fas fa-plus"></i> Tambah Paket
        </a>
    </div>
    <div class="card-body">
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="get" class="mb-3 d-flex" id="searchForm" style="max-width:400px;">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" id="searchInput" class="form-control"
                       placeholder="Cari nama paket / profil PPP..."
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <?php if ($search !== ''): ?>
                <a href="index.php" class="btn btn-outline-secondary" title="Clear">
                    <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table id="packagesTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Paket</th>
                        <th>Profil PPPoE</th>
                        <th>Harga</th>
                        <th>Router</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
                        // Pagination
                        $per_page = 10;
                        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

                        $where = "";
                        $params_where = [];
                        if ($search !== '') {
                            $where = " WHERE (p.nama_paket LIKE ? OR p.profil_ppp LIKE ?)";
                            $kw = '%' . $search . '%';
                            $params_where = [$kw, $kw];
                        }

                        $sql_count = "SELECT COUNT(*) FROM paket_internet p" . $where;
                        $stmt_count = $pdo->prepare($sql_count);
                        $stmt_count->execute($params_where);
                        $total_paket = (int)$stmt_count->fetchColumn();
                        $total_pages = max(1, (int)ceil($total_paket / $per_page));
                        if ($page > $total_pages) $page = $total_pages;
                        $offset = ($page - 1) * $per_page;

                        $sql = "SELECT p.*, r.nama_router, r.ip_address FROM paket_internet p LEFT JOIN routers r ON p.router_id = r.id" . $where . " ORDER BY p.id DESC LIMIT ? OFFSET ?";
                        $stmt = $pdo->prepare($sql);
                        $bind_idx = 1;
                        foreach ($params_where as $pw) {
                            $stmt->bindValue($bind_idx++, $pw);
                        }
                        $stmt->bindValue($bind_idx++, $per_page, PDO::PARAM_INT);
                        $stmt->bindValue($bind_idx, $offset, PDO::PARAM_INT);
                        $stmt->execute();
                        while ($row = $stmt->fetch()) {
                            $status_badge = ($row['is_active'] == 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                            $router_info = $row['nama_router'] ? $row['nama_router'] . ' (' . $row['ip_address'] . ')' : '<span class="text-muted">-</span>';
                            $profil_info = $row['profil_ppp'] ? '<span class="badge bg-info">' . htmlspecialchars($row['profil_ppp']) . '</span>' : '<span class="text-muted">-</span>';
                            echo '<tr>
                                <td>' . $row['id'] . '</small></td>
                                <td><strong>' . htmlspecialchars($row['nama_paket']) . '</strong></small></td>
                                <td>' . $profil_info . '</small></td>
                                <td>Rp ' . number_format($row['harga'], 0, ',', '.') . '</small></td>
                                <td>' . $router_info . '</small></td>
                                <td>' . $status_badge . '</small></td>
                                <td>
                                    <a href="edit.php?id=' . $row['id'] . '" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="delete.php?id=' . $row['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Yakin ingin menghapus paket ini?\')"><i class="fas fa-trash"></i> Hapus</a>
                                 </small>
                            </tr>';
                        }
                    } catch(PDOException $e) {
                        echo '<tr><td colspan="7" class="text-center">Error: ' . $e->getMessage() . '</small></tr>';
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
                Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_paket ?> total paket)
            </p>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#packagesTable').DataTable({
        paging: false,
        info: false,
        searching: false,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
        }
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
