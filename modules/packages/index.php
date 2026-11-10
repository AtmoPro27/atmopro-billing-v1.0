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
                        $sql = "SELECT p.*, r.nama_router, r.ip_address FROM paket_internet p LEFT JOIN routers r ON p.router_id = r.id ORDER BY p.id DESC";
                        $stmt = $pdo->query($sql);
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

        
    </div>
</div>

<style>
/* Pagination DataTables — tampilan seragam */
.dataTables_wrapper .dataTables_paginate { margin-top: 10px; }
.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 5px 10px !important;
    margin: 0 2px !important;
    border-radius: 4px !important;
    border: 1px solid #ddd !important;
    background: white !important;
    color: #0d5c1e !important;
    font-size: 13px !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #0d5c1e !important;
    color: white !important;
    border-color: #0d5c1e !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #0d5c1e !important;
    color: white !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
    background: #f5f5f5 !important;
    color: #999 !important;
}
.dataTables_wrapper .dataTables_info { font-size: 13px; color: #666; }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#packagesTable').DataTable({
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        info: true,
        searching: true,
        ordering: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json'
        },
        order: []
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
