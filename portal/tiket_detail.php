<?php
require_once '/var/www/demo.atmopro.my.id/includes/upload_validator.php';
require_once __DIR__ . "/../includes/auth_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: dashboard.php");
    exit();
}

include '../includes/header_portal.php';

$upload_dir = '../uploads/tiket/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

try {
    require_once "../config/database.php";
$pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("
        SELECT t.*, p.nama_lengkap 
        FROM tickets t 
        LEFT JOIN pelanggan p ON t.pelanggan_id = p.id 
        WHERE t.id = ? AND t.pelanggan_id = (SELECT id FROM pelanggan WHERE id_pelanggan = ?)
    ");
    $stmt->execute([$id, $_SESSION['pelanggan_kode']]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        header("Location: dashboard.php");
        exit();
    }
    
    $stmt = $pdo->prepare("
        SELECT r.*, u.username, u.fullname 
        FROM ticket_replies r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.ticket_id = ? 
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$id]);
    $replies = $stmt->fetchAll();
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply'])) {
        $message = $_POST['message'];
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
        
        if (!empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, attachment, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$id, $_SESSION['pelanggan_id'], $message, $attachment]);
            
            header("Location: tiket_detail.php?id=" . $id);
            exit();
        }
    }
    
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
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-ticket-alt"></i> Detail Tiket: <?= htmlspecialchars($ticket['no_ticket']) ?>
                    <a href="dashboard.php" class="btn btn-sm btn-light float-end">Kembali ke Dashboard</a>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr><th width="120">No Tiket</th><td>: <?= htmlspecialchars($ticket['no_ticket']) ?> </small></td>
                                <tr><th>Subjek</th><td>: <?= htmlspecialchars($ticket['subject']) ?> </small></td>
                                <tr><th>Prioritas</th><td>: <?= $priority_badge ?> </small></td>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
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
                                <label class="form-label">Balasan</label>
                                <textarea name="message" class="form-control" rows="4" required placeholder="Tulis balasan Anda..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Lampiran (opsional)</label>
                                <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.zip">
                                <small class="text-muted">Upload bukti tambahan jika ada</small>
                            </div>
                            <button type="submit" name="reply" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Balasan</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-secondary">
                            <i class="fas fa-info-circle"></i> Tiket ini sudah ditutup.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
    modalImg.src = '/' + filePath;
}

function closeImage() {
    document.getElementById('imageModal').style.display = 'none';
}
</script>

<?php include '../includes/footer_portal.php'; ?>
