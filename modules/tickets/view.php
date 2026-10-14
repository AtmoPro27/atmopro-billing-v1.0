<?php
require_once '/var/www/demo.atmopro.my.id/includes/upload_validator.php';
ob_start();
require_once __DIR__ . "/../../includes/session_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

include '../../includes/header.php';
include '../../includes/activity_log.php';

$upload_dir = '../../uploads/tiket/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Proses balasan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply'])) {
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
        
        $message = $_POST['message'];
        $status = $_POST['status'] ?? 'in_progress';
        $attachment = '';
        
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            // Validasi: coba sebagai gambar dulu, fallback ke dokumen
            $check = validate_image_upload('attachment', 10);
            if (!$check['ok']) {
                $check = validate_document_upload('attachment', 10);
            }
            if ($check['ok']) {
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $new_filename = safe_save_upload('attachment', $upload_dir, 'tiket');
                if ($new_filename) {
                    $attachment = 'uploads/tiket/' . $new_filename;
                }
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, attachment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$id, $_SESSION['user_id'], $message, $attachment]);
        
        $stmt = $pdo->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        echo "<script>window.location.href='view.php?id=$id&success=reply';</script>";
        exit();
        
    } catch(PDOException $e) {
        $error_reply = "Gagal mengirim balasan: " . $e->getMessage();
    }
}

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT t.*, p.nama_lengkap, p.id_pelanggan 
                           FROM tickets t 
                           LEFT JOIN pelanggan p ON t.pelanggan_id = p.id 
                           WHERE t.id = ?");
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        header("Location: index.php");
        exit();
    }
    
    // Ambil no_invoice dari pesan tiket
    preg_match('/INV\/[0-9]{6}\/[0-9A-Z]+/', $ticket['message'], $matches);
    $no_invoice_from_message = $matches[0] ?? '';
    
    $stmt = $pdo->prepare("SELECT r.*, u.username, u.fullname 
                           FROM ticket_replies r 
                           LEFT JOIN users u ON r.user_id = u.id 
                           WHERE r.ticket_id = ? 
                           ORDER BY r.created_at ASC");
    $stmt->execute([$id]);
    $replies = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}

$priority_badge = '';
if ($ticket['priority'] == 'urgent') $priority_badge = '<span class="badge bg-danger">Urgent</span>';
elseif ($ticket['priority'] == 'high') $priority_badge = '<span class="badge bg-warning">High</span>';
elseif ($ticket['priority'] == 'medium') $priority_badge = '<span class="badge bg-info">Medium</span>';
else $priority_badge = '<span class="badge bg-secondary">Low</span>';

$status_badge = '';
if ($ticket['status'] == 'open') $status_badge = '<span class="badge bg-danger">Open</span>';
elseif ($ticket['status'] == 'in_progress') $status_badge = '<span class="badge bg-warning">In Progress</span>';
elseif ($ticket['status'] == 'resolved') $status_badge = '<span class="badge bg-success">Resolved</span>';
else $status_badge = '<span class="badge bg-secondary">Closed</span>';

$is_payment_ticket = (strpos($ticket['subject'], 'Pembayaran') !== false || 
                      strpos($ticket['subject'], 'pembayaran') !== false ||
                      !empty($no_invoice_from_message));
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-ticket-alt"></i> Detail Tiket: <?= htmlspecialchars($ticket['no_ticket']) ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error_reply)): ?>
            <div class="alert alert-danger"><?= $error_reply ?></div>
        <?php endif; ?>
        
        <!-- Tombol Konfirmasi Pembayaran (khusus tiket pembayaran) -->
        <?php if ($is_payment_ticket && $no_invoice_from_message && $ticket['status'] != 'closed' && $ticket['status'] != 'resolved'): ?>
        <div class="alert alert-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-money-bill-wave fa-2x"></i>
                    <strong>Invoice: <?= $no_invoice_from_message ?></strong>
                </div>
                <form method="POST" action="confirm_payment.php" style="display:inline;">
                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                    <input type="hidden" name="no_invoice" value="<?= $no_invoice_from_message ?>">
                    <button type="submit" name="confirm_payment" class="btn btn-success btn-lg" onclick="return confirm('Konfirmasi pembayaran ini? Tiket akan ditutup.')">
                        <i class="fas fa-check-circle"></i> KONFIRMASI PEMBAYARAN & TUTUP TIKET
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><th width="120">No Tiket</th><td>: <?= htmlspecialchars($ticket['no_ticket']) ?> </small></tr>
                    <tr><th>Pelanggan</th><td>: <?= htmlspecialchars($ticket['nama_lengkap'] ?? '-') ?> (<?= $ticket['id_pelanggan'] ?? '-' ?>)</small></tr>
                    <tr><th>Subjek</th><td>: <?= htmlspecialchars($ticket['subject']) ?> </small></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><th>Prioritas</th><td>: <?= $priority_badge ?> </small></td>
                    <tr><th>Status</th><td>: <?= $status_badge ?> </small></td>
                    <tr><th>Tgl Buat</th><td>: <?= date('d-m-Y H:i:s', strtotime($ticket['created_at'])) ?> </small></td>
                    <tr><th>Lampiran</th>
                        <td>: 
                            <?php if (!empty($ticket['attachment'])): ?>
                                <a href="#" onclick="openImage('<?= $ticket['attachment'] ?>')" class="btn btn-sm btn-info">
                                    <i class="fas fa-image"></i> Lihat Bukti
                                </a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                         </small>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="alert alert-secondary">
            <strong>Pesan:</strong><br>
            <?= nl2br(htmlspecialchars($ticket['message'])) ?>
        </div>
        
        <hr>
        <h5><i class="fas fa-comments"></i> Balasan (<?= count($replies) ?>)</h5>
        
        <?php foreach ($replies as $reply): ?>
            <div class="card mb-2">
                <div class="card-header py-2">
                    <strong><?= htmlspecialchars($reply['fullname'] ?? $reply['username'] ?? 'Admin') ?></strong>
                    <small class="text-muted float-end"><?= date('d-m-Y H:i:s', strtotime($reply['created_at'])) ?></small>
                </div>
                <div class="card-body py-2">
                    <?= nl2br(htmlspecialchars($reply['message'])) ?>
                    <?php if (!empty($reply['attachment'])): ?>
                        <div class="mt-2">
                            <a href="#" onclick="openImage('<?= $reply['attachment'] ?>')" class="btn btn-sm btn-info">
                                <i class="fas fa-paperclip"></i> Lihat Lampiran
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if ($ticket['status'] != 'closed' && $ticket['status'] != 'resolved'): ?>
            <hr>
            <h5><i class="fas fa-reply"></i> Kirim Balasan</h5>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Balasan <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="4" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Lampiran Bukti Progress</label>
                    <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.zip">
                </div>
                <div class="mb-3">
                    <label class="form-label">Update Status</label>
                    <select name="status" class="form-control">
                        <option value="in_progress" <?= $ticket['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <button type="submit" name="reply" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Balasan</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Modal untuk melihat gambar -->
<div id="imageModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; cursor:pointer;" onclick="closeImage()">
    <img id="modalImage" src="" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); max-width:90%; max-height:90%;">
    <button onclick="closeImage()" style="position:absolute; top:20px; right:35px; color:white; font-size:40px; background:none; border:none;">&times;</button>
</div>

<script>
function openImage(filePath) {
    var modal = document.getElementById('imageModal');
    var modalImg = document.getElementById('modalImage');
    modal.style.display = 'block';
    modalImg.src = '/modules/tickets/../../' + filePath;
}

function closeImage() {
    document.getElementById('imageModal').style.display = 'none';
}
</script>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
