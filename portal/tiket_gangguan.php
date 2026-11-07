<?php
require_once __DIR__ . "/../includes/auth_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

include '../includes/header_portal.php';
require_once '../config/database.php';
require_once __DIR__ . '/../includes/upload_validator.php';

// =============================================================
// PAKAI SISTEM NOTIFIKASI YANG SUDAH ADA
// =============================================================
require_once '../includes/telegram_internal_notification.php';

$pdo = Database::getInstance()->getConnection();

// Ambil data pelanggan
$stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ?");
$stmt->execute([$_SESSION['pelanggan_kode']]);
$pelanggan = $stmt->fetch();

if (!$pelanggan) {
    die("Data pelanggan tidak ditemukan.");
}

$error = '';
$success = '';

// Proses submit tiket gangguan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jenis_gangguan = $_POST['jenis_gangguan'] ?? '';
    $pesan = trim($_POST['pesan']);
    $bukti_file = '';
    
    // =============================================================
    // PROSES UPLOAD BUKTI
    // =============================================================
    if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0) {
        // Validasi: coba sebagai gambar dulu, fallback ke dokumen (PDF)
        $check = validate_image_upload('bukti', 5);
        if (!$check['ok']) {
            $check = validate_document_upload('bukti', 5);
        }

        if ($check['ok']) {
            $upload_dir = '../uploads/bukti_gangguan/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $new_filename = safe_save_upload('bukti', $upload_dir, 'bukti');
            if ($new_filename) {
                $bukti_file = 'uploads/bukti_gangguan/' . $new_filename;
            } else {
                $error = "Gagal mengupload file. Silakan coba lagi.";
            }
        } else {
            $error = $check['error'];
        }
    }
    
    if (empty($jenis_gangguan) || empty($pesan)) {
        $error = "Semua field wajib diisi!";
    } elseif (empty($error)) {
        try {
            $subject = "GANGGUAN - " . htmlspecialchars($jenis_gangguan);
            
            // =============================================================
            // CEK APAKAH SUDAH ADA TIKET GANGGUAN DENGAN SUBJEK YANG SAMA
            // =============================================================
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) as total 
                FROM tickets 
                WHERE pelanggan_id = ? 
                AND subject = ? 
                AND status IN ('open', 'in_progress')
            ");
            $stmt_check->execute([$pelanggan['id'], $subject]);
            $existing = $stmt_check->fetch();
            
            if ($existing['total'] > 0) {
                $error = "⚠️ Anda sudah memiliki tiket gangguan dengan jenis yang sama (<strong>$jenis_gangguan</strong>) yang masih dalam proses. Silakan tunggu konfirmasi dari admin.";
            } else {
                $no_ticket = 'TCK/' . date('Ymd') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $message = "Jenis Gangguan: $jenis_gangguan\n\n";
                $message .= "Detail Gangguan:\n$pesan\n";
                
                if (!empty($bukti_file)) {
                    $message .= "\n📎 Bukti Gangguan: https://" . $_SERVER['HTTP_HOST'] . "/$bukti_file\n";
                }
                
                $message .= "\n\nDikirim oleh: " . $_SESSION['pelanggan_nama'] . " (" . $_SESSION['pelanggan_kode'] . ")\n";
                $message .= "Tanggal: " . date('d-m-Y H:i:s');
                
                $stmt_insert = $pdo->prepare("
                    INSERT INTO tickets (no_ticket, pelanggan_id, subject, message, priority, status, attachment, created_at)
                    VALUES (?, ?, ?, ?, 'high', 'open', ?, NOW())
                ");
                $stmt_insert->execute([$no_ticket, $pelanggan['id'], $subject, $message, $bukti_file]);
                
                $ticket_id = $pdo->lastInsertId();
                
                // =============================================================
                // AMBIL DATA TIKET YANG BARU DISIMPAN
                // =============================================================
                $stmt_ticket = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
                $stmt_ticket->execute([$ticket_id]);
                $ticket = $stmt_ticket->fetch();
                
                // =============================================================
                // KIRIM NOTIFIKASI TELEGRAM
                // =============================================================
                if ($ticket) {
                    $result = sendTelegramInternalNotification($pdo, $ticket, 'create');
                    if ($result['success']) {
                        $success = "✅ Tiket gangguan berhasil dikirim! Notifikasi sudah dikirim ke " . $result['total_sent'] . " admin via Telegram.";
                    } else {
                        $success = "✅ Tiket gangguan berhasil dikirim! (Notifikasi Telegram: " . ($result['message'] ?? 'gagal') . ")";
                    }
                } else {
                    $success = "✅ Tiket gangguan berhasil dikirim! Admin akan segera memproses.";
                }
            }
            
        } catch(Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-wifi"></i> Laporkan Gangguan Internet
        <a href="dashboard.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <script>
                setTimeout(function() {
                    window.location.href = 'dashboard.php';
                }, 2000);
            </script>
        <?php endif; ?>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong>Subjek otomatis:</strong> "GANGGUAN - [Jenis Gangguan]" akan dibuat otomatis.
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Jenis Gangguan <span class="text-danger">*</span></label>
                <select name="jenis_gangguan" class="form-control" required>
                    <option value="">-- Pilih Jenis Gangguan --</option>
                    <option value="Internet Mati Total">🌐 Internet Mati Total</option>
                    <option value="Koneksi Lambat">🐢 Koneksi Lambat</option>
                    <option value="Modem Mati">📡 Modem Mati</option>
                    <option value="Indikator LOS Merah">🔴 Indikator LOS Merah</option>
                    <option value="PPPoE Error">🔌 PPPoE Error</option>
                    <option value="Gangguan Lainnya">⚠️ Gangguan Lainnya</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Deskripsi Gangguan <span class="text-danger">*</span></label>
                <textarea name="pesan" class="form-control" rows="5" placeholder="Jelaskan gangguan yang Anda alami secara detail..." required></textarea>
                <small class="text-muted">Semakin detail, semakin cepat kami membantu.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Upload Bukti/Foto Gangguan</label>
                <input type="file" name="bukti" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                <small class="text-muted">Maksimal 5MB. Format: JPG, PNG, GIF, WEBP, atau PDF</small>
            </div>

            <button type="submit" class="btn btn-danger"><i class="fas fa-paper-plane"></i> Kirim Tiket Gangguan</button>
            <a href="dashboard.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../includes/footer_portal.php'; ?>
