<?php
ob_start();
require_once __DIR__ . '/../../includes/auth_check.php';

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
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

// Ambil data tiket
$stmt = $pdo->prepare("SELECT t.*, p.nama_lengkap FROM tickets t LEFT JOIN pelanggan p ON t.pelanggan_id = p.id WHERE t.id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        $error = "Pesan balasan tidak boleh kosong!";
    } else {
        try {
            // Insert balasan
            $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$id, $_SESSION['user_id'], $message]);
            
            // Update status tiket
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'in_progress' WHERE id = ?");
            $stmt->execute([$id]);
            
            // Catat log
            log_admin_activity($pdo, 'TICKET_REPLY', 'ticket', $ticket['no_ticket'], 'Membalas tiket');
            
            // Redirect dengan JavaScript
            echo "<script>
                alert('Balasan berhasil dikirim!');
                window.location.href = 'view.php?id=" . $id . "&success=2';
            </script>";
            exit();
        } catch(PDOException $e) {
            $error = "Gagal mengirim balasan: " . $e->getMessage();
        }
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-reply"></i> Balas Tiket: <?= htmlspecialchars($ticket['no_ticket']) ?>
        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <strong>Subjek:</strong> <?= htmlspecialchars($ticket['subject']) ?><br>
            <strong>Pelanggan:</strong> <?= htmlspecialchars($ticket['nama_lengkap'] ?? '-') ?>
        </div>
        
        <form method="POST" id="replyForm">
            <div class="mb-3">
                <label class="form-label">Balasan <span class="text-danger">*</span></label>
                <textarea name="message" class="form-control" rows="5" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Balasan</button>
            <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<script>
// Cegah submit duplikat
document.getElementById('replyForm').addEventListener('submit', function() {
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
});
</script>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
