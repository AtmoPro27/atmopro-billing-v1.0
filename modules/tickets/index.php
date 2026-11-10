<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';
include '../../includes/activity_log.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// =============================================================
// AMBIL DATA TIKET DENGAN URUTAN TERBARU DI ATAS (created_at DESC)
// =============================================================
$sql = "
    SELECT
        t.*,
        p.nama_lengkap as customer_name,
        p.id_pelanggan as customer_code
    FROM tickets t
    LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
    ORDER BY t.created_at DESC, t.id DESC";
$stmt = $pdo->query($sql);
$tickets = $stmt->fetchAll();

// Status labels
$statusLabels = [
    'open' => 'Open',
    'in_progress' => 'In Progress',
    'resolved' => 'Resolved',
    'closed' => 'Closed'
];

$statusBadges = [
    'open' => 'success',
    'in_progress' => 'warning',
    'resolved' => 'info',
    'closed' => 'secondary'
];

$priorityLabels = [
    'low' => 'Rendah',
    'medium' => 'Sedang',
    'high' => 'Tinggi',
    'urgent' => 'Urgent'
];

$priorityBadges = [
    'low' => 'secondary',
    'medium' => 'primary',
    'high' => 'warning',
    'urgent' => 'danger'
];
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-ticket-alt"></i> Daftar Tiket
        <div class="float-end">
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Buat Tiket
            </a>
            <a href="pengumuman.php" class="btn btn-info btn-sm">
                <i class="fas fa-bullhorn"></i> Pengumuman
            </a>
        </div>
    </div>
    <div class="card-body">
<div class="table-responsive">
            <table class="table table-bordered table-hover" id="ticketTable">
                <thead>
                    <tr>
                        <th>No Tiket</th>
                        <th>Pelanggan</th>
                        <th>Subject</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($tickets) > 0): ?>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ticket['no_ticket']) ?></strong>
                            </td>
                            <td>
                                <?= htmlspecialchars($ticket['customer_name'] ?? '-') ?>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($ticket['customer_code'] ?? '') ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($ticket['subject']) ?>
                                <br>
                                <small class="text-muted">
                                    <?= strlen($ticket['message']) > 50 ? substr($ticket['message'], 0, 50) . '...' : htmlspecialchars($ticket['message']) ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $priorityBadges[$ticket['priority']] ?? 'secondary' ?>">
                                    <?= $priorityLabels[$ticket['priority']] ?? $ticket['priority'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusBadges[$ticket['status']] ?? 'secondary' ?>">
                                    <?= $statusLabels[$ticket['status']] ?? $ticket['status'] ?>
                                </span>
                            </td>
                            <td>
                                <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="view.php?id=<?= $ticket['id'] ?>" class="btn btn-info" title="Lihat">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="reply.php?id=<?= $ticket['id'] ?>" class="btn btn-success" title="Balas">
                                        <i class="fas fa-reply"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $ticket['id'] ?>" class="btn btn-danger" title="Hapus" onclick="return confirm('Yakin ingin menghapus tiket ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Belum ada tiket</td>
                        </tr>
                    <?php endif; ?>
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

<script>
$(document).ready(function() {
    // =============================================================
    // SOLUSI: Nonaktifkan sorting default, urutan dari query
    // =============================================================
    $('#ticketTable').DataTable({
        "paging": true,
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        "info": true,
        "searching": true,
        "ordering": true,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
        },
        "order": []
    });
});
</script>

<?php include '../../includes/footer.php'; ?>