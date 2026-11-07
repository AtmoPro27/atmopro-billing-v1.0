<?php
require_once __DIR__ . "/../includes/auth_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

require_once '../config/database.php';
$pdo = Database::getInstance()->getConnection();

$id_invoice = (int)($_GET['id'] ?? 0);
if (!$id_invoice) {
    header("Location: tagihan.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM invoice WHERE id = ? AND pelanggan_id = ?");
$stmt->execute([$id_invoice, $_SESSION['pelanggan_id']]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header("Location: tagihan.php");
    exit();
}

include '../includes/header_portal.php';
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-success text-white">
            <i class="fas fa-credit-card"></i> Pilih Metode Pembayaran
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Invoice: <?= htmlspecialchars($invoice['no_invoice']) ?></strong><br>
                Jumlah: Rp <?= number_format($invoice['jumlah'], 0, ',', '.') ?>
            </div>

            <form action="bayar_online.php" method="GET">
                <input type="hidden" name="id" value="<?= $invoice['id'] ?>">

                <div class="mb-3">
                    <label class="form-label">Pilih Metode:</label>
                    <select name="metode" class="form-control" required>
                        <option value="">-- Pilih Metode --</option>

                        <optgroup label="Bank Transfer">
                            <option value="VC">BCA Virtual Account</option>
                            <option value="A1">Mandiri Virtual Account</option>
                            <option value="I1">BNI Virtual Account</option>
                            <option value="S1">BRI Virtual Account</option>
                            <option value="BT">Permata Virtual Account</option>
                            <option value="VA">Maybank Virtual Account</option>
                            <option value="B1">CIMB Virtual Account</option>
                        </optgroup>

                        <optgroup label="e-Wallet">
                            <option value="DA">DANA</option>
                            <option value="OV">OVO</option>
                            <option value="SP">ShopeePay</option>
                            <option value="LF">LinkAja</option>
                        </optgroup>

                        <optgroup label="Retail">
                            <option value="IR">Indomaret</option>
                        </optgroup>

                        <optgroup label="QRIS">
                            <option value="NQ">QRIS</option>
                        </optgroup>

                        <optgroup label="Kartu Kredit">
                            <option value="CC">Kartu Kredit</option>
                        </optgroup>
                    </select>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-arrow-right"></i> Lanjut Bayar
                </button>
                <a href="tagihan.php" class="btn btn-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer_portal.php'; ?>
