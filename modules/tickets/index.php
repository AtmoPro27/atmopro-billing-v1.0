<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

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
$stmt = $pdo->query("
    SELECT 
        t.*,
        p.nama_lengkap as customer_name,
        p.id_pelanggan as customer_code
    FROM tickets t
    LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
    ORDER BY t.created_at DESC, t.id DESC
");
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

<script>
$(document).ready(function() {
    // =============================================================
    // SOLUSI: Nonaktifkan sorting default, urutan dari query
    // =============================================================
    $('#ticketTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
        },
        "pageLength": 10,
        "order": [],  // Kosongkan agar tidak override
        "columnDefs": [
            { "orderable": false, "targets": [0, 1, 2, 3, 4, 5, 6] }  // Nonaktifkan semua sorting
        ]
    });
});
</script>

<?php include '../../includes/footer.php'; ?>