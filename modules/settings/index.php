<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Hanya super_admin yang bisa mengakses pengaturan
if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard.php");
    exit();
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-building"></i> Pengaturan Umum
            </div>
            <div class="card-body">
                <p>Atur data perusahaan, nama, logo, alamat, dll.</p>
                <a href="general.php" class="btn btn-primary">Kelola</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <i class="fas fa-credit-card"></i> Pengaturan Pembayaran
            </div>
            <div class="card-body">
                <p>Atur rekening bank, payment gateway, dll.</p>
                <a href="payment.php" class="btn btn-success">Kelola</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <i class="fab fa-whatsapp"></i> Pengaturan WA Gateway
            </div>
            <div class="card-body">
                <p>Atur API WhatsApp untuk notifikasi dan kirim pesan.</p>
                <a href="wa_gateway.php" class="btn btn-warning">Kelola</a>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <i class="fab fa-telegram"></i> Pengaturan Telegram
            </div>
            <div class="card-body">
                <p>Atur bot Telegram untuk notifikasi.</p>
                <a href="telegram.php" class="btn btn-info">Kelola</a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
