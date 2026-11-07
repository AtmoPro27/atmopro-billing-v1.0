<?php
require_once __DIR__ . "/../includes/auth_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

include '../includes/header_portal.php';

date_default_timezone_set('Asia/Jakarta');

try {
    require_once "../config/database.php";
$pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ?");
    $stmt->execute([$_SESSION['pelanggan_kode']]);
    $pelanggan = $stmt->fetch();

    // Tandai invoice pending sebagai sudah dilihat
    try {
        $stmt_update = $pdo->prepare("
            UPDATE invoice 
            SET viewed_at = NOW() 
            WHERE pelanggan_id = ? 
            AND status = 'pending' 
            AND (viewed_at IS NULL OR viewed_at = '0000-00-00 00:00:00')
        ");
        $stmt_update->execute([$pelanggan['id']]);
    } catch (PDOException $e) {
        // Kolom viewed_at belum ada, abaikan
    }

    $stmt_inv = $pdo->prepare("SELECT * FROM invoice WHERE pelanggan_id = ? ORDER BY id DESC");
    $stmt_inv->execute([$pelanggan['id']]);
    $invoices = $stmt_inv->fetchAll();

    // Cek PG aktif
    $stmt_pg = $pdo->query("SELECT name FROM payment_gateway WHERE is_active = 1 LIMIT 1");
    $active_pg = $stmt_pg->fetch();

} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-success text-white">
            <i class="fas fa-file-invoice"></i> DAFTAR TAGIHAN
            <a href="dashboard.php" class="btn btn-sm btn-light float-end">Kembali</a>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if (count($invoices) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>No Invoice</th>
                                <th>Jumlah</th>
                                <th>Tgl Jatuh Tempo</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                                <tr class="<?= $inv['status'] == 'pending' ? 'table-warning' : 'table-success' ?>">
                                    <td><?= $inv['no_invoice'] ?></td>
                                    <td>Rp <?= number_format($inv['jumlah'], 0, ',', '.') ?></td>
                                    <td><?= date('d-m-Y', strtotime($inv['tgl_jatuh_tempo'])) ?></td>
                                    <td>
                                        <?php if ($inv['status'] == 'paid'): ?>
                                            <span class="badge bg-success">Lunas</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                     </small>
                                    <td>
                                        <?php if ($inv['status'] == 'pending'): ?>
        <?php if ($active_pg): ?>
            <a href="pilih_metode.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-success mb-1">
                <i class="fas fa-credit-card"></i> Bayar Online (<?= htmlspecialchars($active_pg['name']) ?>)
            </a>
            <br>
            <a href="tiket_pembayaran.php?no_invoice=<?= urlencode($inv['no_invoice']) ?>&jumlah=<?= $inv['jumlah'] ?>" class="btn btn-sm btn-primary mb-1">
                <i class="fas fa-paper-plane"></i> Kirim Bukti Bayar
            </a>
        <?php else: ?>
            <a href="tiket_pembayaran.php?no_invoice=<?= urlencode($inv['no_invoice']) ?>&jumlah=<?= $inv['jumlah'] ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-paper-plane"></i> Kirim Bukti Bayar
            </a>
        <?php endif; ?>
    <?php endif; ?>
                                     </small>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Belum ada tagihan.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer_portal.php'; ?>
