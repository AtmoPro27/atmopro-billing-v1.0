<?php
require_once __DIR__ . "/../includes/auth_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

include '../includes/header_portal.php';
require_once '../config/database.php';

$pdo = Database::getInstance()->getConnection();

// Ambil data pelanggan
$stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ?");
$stmt->execute([$_SESSION['pelanggan_kode']]);
$pelanggan = $stmt->fetch();

if (!$pelanggan) {
    die("Data pelanggan tidak ditemukan.");
}

// Ambil daftar tiket pelanggan
$stmt = $pdo->prepare("
    SELECT * FROM tickets 
    WHERE pelanggan_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$pelanggan['id']]);
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
?>

<style>
    .ticket-card {
        border-left: 4px solid #0d5c1e;
        margin-bottom: 15px;
        transition: all 0.3s;
    }
    .ticket-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .ticket-card .card-body {
        padding: 15px 20px;
    }
    .ticket-card .ticket-subject {
        font-weight: 600;
        font-size: 16px;
    }
    .ticket-card .ticket-message {
        color: #666;
        font-size: 14px;
        margin-top: 5px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .ticket-card .ticket-meta {
        font-size: 12px;
        color: #999;
        margin-top: 8px;
    }
    .badge-invoice {
        background-color: #0d5c1e;
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 12px;
    }
</style>

<div class="card">
    <div class="card-header">
        <i class="fas fa-ticket-alt"></i> Daftar Tiket Saya
        <div class="float-end">
            <a href="tiket_gangguan.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Tiket Gangguan
            </a>
            <a href="tiket_pembayaran.php" class="btn btn-success btn-sm">
                <i class="fas fa-money-bill-wave"></i> Tiket Pembayaran
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($tickets) == 0): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> Belum ada tiket. Buat tiket baru jika ada kendala.
            </div>
        <?php else: ?>
            <?php foreach ($tickets as $ticket): ?>
                <div class="card ticket-card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="ticket-subject">
                                    <?= htmlspecialchars($ticket['subject']) ?>
                                    
                                    <?php
                                    // =============================================================
                                    // CEK APAKAH TIKET PEMBAYARAN - TAMPILKAN NO INVOICE
                                    // =============================================================
                                    if (strpos($ticket['subject'], 'Konfirmasi Pembayaran - Invoice') !== false) {
                                        preg_match('/Invoice\s+([^\s]+)/', $ticket['subject'], $matches);
                                        $no_invoice = $matches[1] ?? '-';
                                        echo ' <span class="badge-invoice"><i class="fas fa-file-invoice"></i> ' . htmlspecialchars($no_invoice) . '</span>';
                                    }
                                    ?>
                                </div>
                                <div class="ticket-message">
                                    <?= nl2br(htmlspecialchars(substr($ticket['message'], 0, 200))) ?>
                                    <?php if (strlen($ticket['message']) > 200): ?>
                                        ...
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-meta">
                                    <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                                    <?php if ($ticket['updated_at'] != $ticket['created_at']): ?>
                                        <span class="text-muted">(Terakhir update: <?= date('d/m/Y H:i', strtotime($ticket['updated_at'])) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="badge bg-<?= $statusBadges[$ticket['status']] ?? 'secondary' ?>">
                                    <?= $statusLabels[$ticket['status']] ?? $ticket['status'] ?>
                                </span>
                                <br>
                                <a href="tiket_detail.php?id=<?= $ticket['id'] ?>" class="btn btn-info btn-sm mt-2">
                                    <i class="fas fa-eye"></i> Detail
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer_portal.php'; ?>
