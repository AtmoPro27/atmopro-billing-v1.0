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
require_once __DIR__ . "/../../includes/upload_validator.php";
    $pdo = Database::getInstance()->getConnection();
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Ambil daftar pelanggan
$stmt = $pdo->query("SELECT id, id_pelanggan, nama_lengkap FROM pelanggan WHERE status = 'active' ORDER BY nama_lengkap");
$customers = $stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $pelanggan_id = $_POST['pelanggan_id'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';

    if (empty($subject) || empty($message)) {
        $error = "Subject dan pesan harus diisi!";
    } elseif (empty($pelanggan_id)) {
        $error = "Pilih pelanggan!";
    } else {
        try {
            if ($pelanggan_id == 'all') {
                $stmt_all = $pdo->query("SELECT id FROM pelanggan WHERE status = 'active'");
                $count = 0;
                while ($row = $stmt_all->fetch()) {
                    $no = 'TCK/' . date('Ymd') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $stmt2 = $pdo->prepare("INSERT INTO tickets (no_ticket, pelanggan_id, subject, message, priority, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt2->execute([$no, $row['id'], $subject, $message, $priority, $_SESSION['user_id']]);
                    $count++;
                }
                log_admin_activity($pdo, 'TICKET_CREATE_ALL', 'ticket', 'all', "Membuat tiket untuk $count pelanggan: $subject");
                $success = "Tiket berhasil dikirim ke $count pelanggan";
            } else {
                $no_ticket = 'TCK/' . date('Ymd') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Proses upload file
                $upload_dir = '../../uploads/tiket/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $attachment = '';
                
                // Cek file dari galeri (PATCHED)
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
                    $check = validate_document_upload('attachment', 10);
                    if ($check['ok']) {
                        $new_filename = safe_save_upload('attachment', $upload_dir, 'ticket');
                        if ($new_filename) {
                            $attachment = 'uploads/tiket/' . $new_filename;
                        }
                    }
                }
                
                // Cek file dari kamera (PATCHED)
                if (empty($attachment) && isset($_FILES['camera_attachment']) && $_FILES['camera_attachment']['error'] == 0) {
                    $check = validate_image_upload('camera_attachment', 10);
                    if ($check['ok']) {
                        $new_filename = safe_save_upload('camera_attachment', $upload_dir, 'camera');
                        if ($new_filename) {
                            $attachment = 'uploads/tiket/' . $new_filename;
                        }
                    }
                }
                
                $stmt2 = $pdo->prepare("INSERT INTO tickets (no_ticket, pelanggan_id, subject, message, priority, attachment, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt2->execute([$no_ticket, $pelanggan_id, $subject, $message, $priority, $attachment, $_SESSION['user_id']]);
                log_admin_activity($pdo, 'TICKET_CREATE', 'ticket', $no_ticket, "Membuat tiket untuk pelanggan ID: $pelanggan_id - $subject");
                $success = "Tiket berhasil dibuat";
            }

            echo "<script>alert('$success'); window.location.href='index.php';</script>";
            exit();
        } catch(Exception $e) {
            $error = "Gagal membuat tiket: " . $e->getMessage();
        }
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-plus"></i> Buat Tiket Baru
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Pelanggan <span class="text-danger">*</span></label>
                <select name="pelanggan_id" class="form-control" required>
                    <option value="">-- Pilih Pelanggan --</option>
                    <option value="all" style="font-weight:bold; background:#e8f5e9;">📢 KIRIM KE SEMUA PELANGGAN</option>
                    <?php foreach ($customers as $cust): ?>
                        <option value="<?= $cust['id'] ?>"><?= htmlspecialchars($cust['nama_lengkap']) ?> (<?= $cust['id_pelanggan'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Subjek <span class="text-danger">*</span></label>
                <input type="text" name="subject" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Prioritas</label>
                <select name="priority" class="form-control">
                    <option value="low">Rendah</option>
                    <option value="medium" selected>Sedang</option>
                    <option value="high">Tinggi</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Pesan <span class="text-danger">*</span></label>
                <textarea name="message" class="form-control" rows="5" required></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Lampiran</label>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-images fa-2x mb-2 text-primary"></i>
                                <h6>Dari Galeri</h6>
                                <input type="file" name="attachment" class="form-control" accept="image/*,.pdf,.zip">
                                <small class="text-muted">Pilih file dari perangkat</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-camera fa-2x mb-2 text-success"></i>
                                <h6>Dari Kamera</h6>
                                <input type="file" name="camera_attachment" class="form-control" accept="image/*" capture="environment">
                                <small class="text-muted">Ambil foto langsung</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="fileInfo" class="mt-2 text-muted"></div>
            </div>

            <button type="submit" name="submit" class="btn btn-primary"><i class="fas fa-save"></i> Buat Tiket</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<script>
document.querySelector('input[name="attachment"]').addEventListener('change', function(e) {
    if (this.files.length > 0) {
        document.getElementById('fileInfo').innerHTML = '<i class="fas fa-check-circle text-success"></i> File dari galeri: ' + this.files[0].name;
    }
});
document.querySelector('input[name="camera_attachment"]').addEventListener('change', function(e) {
    if (this.files.length > 0) {
        document.getElementById('fileInfo').innerHTML = '<i class="fas fa-check-circle text-success"></i> Foto dari kamera: ' + this.files[0].name;
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
