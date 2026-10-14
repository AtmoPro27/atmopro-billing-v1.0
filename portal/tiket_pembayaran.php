<?php
error_log("=== FILE TIKET_PEMBAYARAN.PHP DIAKSES ===");

require_once __DIR__ . "/../includes/session_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

include '../includes/header_portal.php';
require_once '../config/database.php';
require_once __DIR__ . '/../includes/upload_validator.php';

error_log("=== TIKET PEMBAYARAN - AFTER INCLUDE ===");

// =============================================================
// INCLUDE NOTIFIKASI TELEGRAM
// =============================================================
if (file_exists('../includes/telegram_internal_notification.php')) {
    require_once '../includes/telegram_internal_notification.php';
    error_log("Telegram: File included successfully");
} else {
    error_log("Telegram: File NOT found!");
}

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

// Prefill no_invoice dari URL
$prefill_invoice = trim($_GET['no_invoice'] ?? '');

// Ambil invoice pending pelanggan
$stmt = $pdo->prepare("
    SELECT * FROM invoice 
    WHERE pelanggan_id = ? AND status = 'pending' 
    ORDER BY tgl_jatuh_tempo ASC
");
$stmt->execute([$pelanggan['id']]);
$invoices = $stmt->fetchAll();

// Proses submit tiket
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    error_log("=== TIKET PEMBAYARAN - POST DITERIMA ===");
    
    $no_invoice = trim($_POST['no_invoice']);
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
            $upload_dir = '../uploads/bukti_pembayaran/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $new_filename = safe_save_upload('bukti', $upload_dir, 'bukti');
            if ($new_filename) {
                $bukti_file = 'uploads/bukti_pembayaran/' . $new_filename;
                error_log("Bukti uploaded: " . $bukti_file);
            } else {
                $error = "Gagal mengupload file. Silakan coba lagi.";
            }
        } else {
            $error = $check['error'];
        }
    }

            // Handle base64 (dari kamera) — PATCHED
            if (empty($bukti_file) && !empty($_POST['base64_image'])) {
                $base64 = $_POST['base64_image'];
                if (strpos($base64, 'base64,') !== false) {
                    $base64 = explode('base64,', $base64)[1];
                }
                $image_data = base64_decode($base64);

                // Validasi: harus benar-benar gambar
                if ($image_data !== false && strlen($image_data) > 0) {
                    // Cek magic bytes gambar
                    $valid_image = false;
                    if (substr($image_data, 0, 3) === "\xFF\xD8\xFF") $valid_image = 'jpg';      // JPEG
                    elseif (substr($image_data, 0, 8) === "\x89PNG\r\n\x1a\n") $valid_image = 'png'; // PNG
                    elseif (substr($image_data, 0, 6) === "GIF87a" || substr($image_data, 0, 6) === "GIF89a") $valid_image = 'gif'; // GIF
                    elseif (substr($image_data, 0, 4) === "RIFF" && substr($image_data, 8, 4) === "WEBP") $valid_image = 'webp'; // WEBP

                    // Cek tidak mengandung kode PHP
                    if ($valid_image && preg_match('/<\?php|<\?=/i', $image_data)) {
                        $valid_image = false;
                        error_log("Base64 upload rejected: contains PHP tag");
                    }

                    if ($valid_image) {
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $new_filename = 'bukti_camera_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $valid_image;
                        $upload_path = $upload_dir . $new_filename;
                        if (file_put_contents($upload_path, $image_data)) {
                            $bukti_file = 'uploads/bukti_pembayaran/' . $new_filename;
                            error_log("Bukti (camera) uploaded: " . $bukti_file);
                        }
                    } else {
                        $error = "❌ Gambar dari kamera tidak valid.";
                    }
                }
            }
    
    if (empty($no_invoice) || empty($pesan)) {
        $error = "Semua field wajib diisi!";
    } elseif (empty($error)) {
        // =============================================================
        // CEK APAKAH SUDAH ADA TIKET OPEN UNTUK INVOICE INI
        // =============================================================
        $stmt_check = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM tickets 
            WHERE pelanggan_id = ? 
            AND subject LIKE ? 
            AND status IN ('open', 'in_progress')
        ");
        $stmt_check->execute([$pelanggan['id'], '%' . $no_invoice . '%']);
        $existing = $stmt_check->fetch();
        
        if ($existing['total'] > 0) {
            $error = "⚠️ Anda sudah memiliki tiket pembayaran untuk invoice <strong>$no_invoice</strong> yang masih dalam proses. Silakan tunggu konfirmasi dari admin.";
        } else {
            // =============================================================
            // CEK APAKAH INVOICE VALID
            // =============================================================
            $stmt_inv = $pdo->prepare("
                SELECT * FROM invoice 
                WHERE no_invoice = ? AND pelanggan_id = ? AND status = 'pending'
            ");
            $stmt_inv->execute([$no_invoice, $pelanggan['id']]);
            $invoice = $stmt_inv->fetch();
            
            if (!$invoice) {
                $error = "Invoice tidak ditemukan atau sudah lunas!";
            } else {
                // =============================================================
                // BUAT TIKET PEMBAYARAN
                // =============================================================
                $no_ticket = 'TCK/' . date('Ymd') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $subject = "Konfirmasi Pembayaran - Invoice $no_invoice";
                $message = "Saya telah melakukan pembayaran untuk invoice $no_invoice sebesar Rp " . number_format($invoice['jumlah'], 0, ',', '.') . "\n\n";
                $message .= "Detail Pembayaran:\n";
                $message .= "- No Invoice: $no_invoice\n";
                $message .= "- Jumlah: Rp " . number_format($invoice['jumlah'], 0, ',', '.') . "\n";
                $message .= "- Tanggal Transfer: " . date('d-m-Y') . "\n\n";
                $message .= "Catatan: $pesan\n";
                
                if (!empty($bukti_file)) {
                    $message .= "\n📎 Bukti Pembayaran: https://" . $_SERVER['HTTP_HOST'] . "/$bukti_file\n";
                }
                
                $message .= "\nMohon segera dikonfirmasi. Terima kasih.";
                
                $stmt_insert = $pdo->prepare("
                    INSERT INTO tickets (no_ticket, pelanggan_id, subject, message, priority, status, attachment, created_at)
                    VALUES (?, ?, ?, ?, 'medium', 'open', ?, NOW())
                ");
                $stmt_insert->execute([$no_ticket, $pelanggan['id'], $subject, $message, $bukti_file]);
                
                $ticket_id = $pdo->lastInsertId();
                
                // =============================================================
                // KIRIM NOTIFIKASI TELEGRAM
                // =============================================================
                $stmt_ticket = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
                $stmt_ticket->execute([$ticket_id]);
                $ticket = $stmt_ticket->fetch();
                
                if ($ticket && function_exists('sendTelegramInternalNotification')) {
                    error_log("=== TELEGRAM: Memanggil sendTelegramInternalNotification ===");
                    $result = sendTelegramInternalNotification($pdo, $ticket, 'create');
                    error_log("Telegram result: " . print_r($result, true));
                    
                    if ($result['success']) {
                        $success = "✅ Tiket pembayaran untuk invoice <strong>$no_invoice</strong> berhasil dikirim! Notifikasi sudah dikirim ke " . $result['total_sent'] . " admin via Telegram.";
                    } else {
                        $success = "✅ Tiket pembayaran untuk invoice <strong>$no_invoice</strong> berhasil dikirim! (Notifikasi Telegram: " . ($result['message'] ?? 'gagal') . ")";
                    }
                } else {
                    error_log("Telegram: Fungsi tidak ditemukan atau tiket kosong");
                    $success = "✅ Tiket pembayaran untuk invoice <strong>$no_invoice</strong> berhasil dikirim! Admin akan segera memproses.";
                }
            }
        }
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-paper-plane"></i> Kirim Tiket Pembayaran
        <a href="dashboard.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            <script>
                setTimeout(function() {
                    window.location.href = 'dashboard.php';
                }, 2000);
            </script>
        <?php endif; ?>

        <?php if (count($invoices) == 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i> Tidak ada tagihan pending. Semua tagihan Anda sudah lunas.
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Pilih Invoice</label>
                    <select name="no_invoice" class="form-control" required>
                        <option value="">-- Pilih Invoice --</option>
                        <?php foreach ($invoices as $inv): ?>
                            <option value="<?= $inv['no_invoice'] ?>" <?= ($prefill_invoice === $inv['no_invoice']) ? 'selected' : '' ?>>
                                <?= $inv['no_invoice'] ?> - Rp <?= number_format($inv['jumlah'], 0, ',', '.') ?>
                                (Jatuh Tempo: <?= date('d-m-Y', strtotime($inv['tgl_jatuh_tempo'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Catatan Pembayaran</label>
                    <textarea name="pesan" class="form-control" rows="3" placeholder="Contoh: Sudah transfer via BCA ke rekening 1234567890 a.n. PT Demo Company" required></textarea>
                    <small class="text-muted">Mohon sertakan bukti transfer jika ada</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Upload Bukti Pembayaran</label>
                    <input type="hidden" name="base64_image" id="base64_image" value="">
                    <input type="file" name="bukti" id="attachment" style="display:none;" accept="image/*,application/pdf">
                    
                    <div class="row">
                        <div class="col-6 mb-2">
                            <button type="button" class="btn btn-outline-primary w-100" onclick="pilihDariGaleri()">
                                <i class="fas fa-images"></i> Galeri
                            </button>
                        </div>
                        <div class="col-6 mb-2">
                            <button type="button" class="btn btn-outline-success w-100" onclick="ambilDariKamera()">
                                <i class="fas fa-camera"></i> Kamera
                            </button>
                        </div>
                    </div>
                    
                    <div id="preview" style="display:none; margin-top:10px;">
                        <img id="preview-img" src="#" style="max-width:150px; border-radius:5px;">
                        <button type="button" class="btn btn-sm btn-danger mt-1" onclick="hapusPreview()">Hapus</button>
                    </div>
                    
                    <small class="text-muted">Maksimal 5MB. Format: JPG, PNG, GIF, WEBP, atau PDF</small>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Tiket</button>
                <a href="dashboard.php" class="btn btn-secondary">Batal</a>
            </form>
        <?php endif; ?>
    </div>
</div>


<script>
let currentFile = null;

function pilihDariGaleri() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*,application/pdf';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            currentFile = file;
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('preview-img').src = event.target.result;
                    document.getElementById('preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                document.getElementById('preview').style.display = 'none';
                alert('File ' + file.name + ' berhasil dipilih');
            }
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            document.getElementById('attachment').files = dataTransfer.files;
        }
    };
    input.click();
}

function ambilDariKamera() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.capture = 'environment';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            currentFile = file;
            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('preview-img').src = event.target.result;
                document.getElementById('preview').style.display = 'block';
                document.getElementById('base64_image').value = event.target.result;
            };
            reader.readAsDataURL(file);
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            document.getElementById('attachment').files = dataTransfer.files;
        }
    };
    input.click();
}

function hapusPreview() {
    document.getElementById('attachment').value = '';
    document.getElementById('preview').style.display = 'none';
    document.getElementById('preview-img').src = '#';
    document.getElementById('base64_image').value = '';
    currentFile = null;
}
</script>
<?php include '../includes/footer_portal.php'; ?>
