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
// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$total_tiket = (int)$pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$total_pages = max(1, (int)ceil($total_tiket / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT
        t.*,
        p.nama_lengkap as customer_name,
        p.id_pelanggan as customer_code
    FROM tickets t
    LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
    ORDER BY t.created_at DESC, t.id DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $per_page, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
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
                Halaman <?= $page ?> dari <?= $total_pages ?> (<?= $total_tiket ?> total tiket)
            </p>
        </nav>
        <?php endif; ?>
    </div>

<script>
$(document).ready(function() {
    // =============================================================
    // SOLUSI: Nonaktifkan sorting default, urutan dari query
    // =============================================================
    $('#ticketTable').DataTable({
        "paging": false,
        "info": false,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
        },
        "order": [],
        "columnDefs": [
            { "orderable": false, "targets": [0, 1, 2, 3, 4, 5, 6] }
        ]
    });
});
</script>

<?php include '../../includes/footer.php'; ?>