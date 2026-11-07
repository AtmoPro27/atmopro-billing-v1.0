<?php
require_once __DIR__ . "/../includes/auth_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

include '../includes/header_portal.php';

try {
    require_once "../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $id = $_GET['id'] ?? 0;
    if (!$id) {
        header("Location: dashboard.php");
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM pelanggan WHERE id_pelanggan = ?");
    $stmt->execute([$_SESSION['pelanggan_kode']]);
    $pelanggan = $stmt->fetch();
    $pelanggan_id = $pelanggan['id'];

    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            p.nama_lengkap as customer_name,
            p.id_pelanggan as customer_code,
            p.no_telepon as customer_phone,
            u.fullname as created_by_name
        FROM tickets t
        LEFT JOIN pelanggan p ON t.pelanggan_id = p.id
        LEFT JOIN users u ON t.created_by = u.id
        WHERE t.id = ? AND t.pelanggan_id = ?
    ");
    $stmt->execute([$id, $pelanggan_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        header("Location: dashboard.php");
        exit();
    }

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

} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">📋 Detail Tiket</h5>
            <a href="dashboard.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($ticket): ?>
            <div class="row">
                <div class="col-md-12">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width:30%">No Tiket</th>
                            <td><strong><?= htmlspecialchars($ticket['no_ticket']) ?></strong></td>
                        </tr>
                        <tr>
                            <th>Pelanggan</th>
                            <td><?= htmlspecialchars($ticket['customer_name'] ?? '-') ?> (<?= htmlspecialchars($ticket['customer_code'] ?? '-') ?>)</td>
                        </tr>
                        <tr>
                            <th>Subjek</th>
                            <td><?= htmlspecialchars($ticket['subject']) ?></td>
                        </tr>
                        <tr>
                            <th>Pesan</th>
                            <td><?= nl2br(htmlspecialchars($ticket['message'])) ?></td>
                        </tr>
                        <tr>
                            <th>Prioritas</th>
                            <td>
                                <?php
                                $priorityLabels = [
                                    'low' => '🟢 Rendah',
                                    'medium' => '🟡 Sedang',
                                    'high' => '🔴 Tinggi',
                                    'urgent' => '⛔ URGENT'
                                ];
                                echo $priorityLabels[$ticket['priority']] ?? $ticket['priority'];
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="badge bg-<?= $statusBadges[$ticket['status']] ?? 'secondary' ?>">
                                    <?= $statusLabels[$ticket['status']] ?? $ticket['status'] ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Tanggal Dibuat</th>
                            <td><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <th>Dibuat Oleh</th>
                            <td><?= htmlspecialchars($ticket['created_by_name'] ?? 'Pelanggan') ?></td>
                        </tr>
                        <?php if (!empty($ticket['attachment'])): ?>
                        <tr>
                            <th>Lampiran</th>
                            <td>
                                <a href="../<?= $ticket['attachment'] ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-download"></i> Lihat Lampiran
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer_portal.php'; ?>
