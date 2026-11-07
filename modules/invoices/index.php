<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';
require_once '../../config/database.php';
$pdo = Database::getInstance()->getConnection();

$is_superadmin = false;
try {
    $stmt_check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    $user_data = $stmt_check->fetch();
    $is_superadmin = ($user_data && $user_data['role'] == 'super_admin');
} catch(Exception $e) {
    $is_superadmin = false;
}

$success_msg = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) $success_msg = 'Tagihan berhasil dibuat!';
    elseif ($_GET['success'] == 2) $success_msg = 'Pembayaran berhasil dicatat!';
} elseif (isset($_GET['delete'])) {
    $success_msg = 'Tagihan berhasil dihapus!';
}

// Filter status dari GET
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
?>

<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">

<div class="card">
    <div class="card-header">
        <i class="fas fa-file-invoice"></i> Manajemen Tagihan
        <div class="float-end d-flex gap-2 align-items-center">
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Buat Tagihan Baru
            </a>
            <form method="GET" action="export_tagihan_pdf.php" target="_blank" class="d-inline-flex gap-1 align-items-center" style="margin:0;">
                <select name="bulan" class="form-select form-select-sm" style="width:auto;">
                    <?php
                    $nama_bulan_opt = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
                    for ($i = 1; $i <= 12; $i++):
                    ?>
                        <option value="<?= $i ?>" <?= $i == (int)date('n') ? 'selected' : '' ?>>
                            <?= $nama_bulan_opt[$i-1] ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <input type="number" name="tahun" class="form-control form-control-sm" value="<?= date('Y') ?>" min="2000" max="2100" style="width:80px;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        
        <!-- ========================================== -->
        <!-- TOMBOL FILTER STATUS -->
        <!-- ========================================== -->
        <div class="mb-3">
            <label class="fw-bold me-2">Filter Status:</label>
            <div class="btn-group" role="group">
                <a href="?status=all" class="btn btn-sm <?= $filter_status == 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">Semua</a>
                <a href="?status=paid" class="btn btn-sm <?= $filter_status == 'paid' ? 'btn-success' : 'btn-outline-success' ?>">✅ Lunas</a>
                <a href="?status=pending" class="btn btn-sm <?= $filter_status == 'pending' ? 'btn-warning' : 'btn-outline-warning' ?>">⏳ Pending</a>
            </div>
        </div>
        
        <div class="table-responsive">
            <table id="invoicesTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>No Invoice</th>
                        <th>ID Pelanggan</th>
                        <th>Nama Pelanggan</th>
                        <th>Tanggal Terbit</th>
                        <th>Jatuh Tempo</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Tgl Bayar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Query dengan filter
                    $sql = "
                        SELECT i.*, p.nama_lengkap, p.id_pelanggan 
                        FROM invoice i 
                        LEFT JOIN pelanggan p ON i.pelanggan_id = p.id 
                    ";
                    if ($filter_status != 'all') {
                        $sql .= " WHERE i.status = '" . $filter_status . "' ";
                    }
                    $sql .= " ORDER BY i.id DESC";
                    
                    $stmt = $pdo->query($sql);
                    while ($inv = $stmt->fetch()):
                        $status_badge = '';
                        if ($inv['status'] == 'paid') {
                            $status_badge = '<span class="badge bg-success">Lunas</span>';
                        } elseif ($inv['status'] == 'pending') {
                            $status_badge = '<span class="badge bg-warning">Pending</span>';
                        } elseif ($inv['status'] == 'cancelled') {
                            $status_badge = '<span class="badge bg-danger">Dibatalkan</span>';
                        } else {
                            $status_badge = '<span class="badge bg-secondary">' . $inv['status'] . '</span>';
                        }
                        $tgl_terbit = (!empty($inv['tgl_terbit']) && $inv['tgl_terbit'] != '0000-00-00 00:00:00') ? date('d-m-Y', strtotime($inv['tgl_terbit'])) : '-';
                        $tgl_jatuh_tempo = (!empty($inv['tgl_jatuh_tempo']) && $inv['tgl_jatuh_tempo'] != '0000-00-00 00:00:00') ? date('d-m-Y', strtotime($inv['tgl_jatuh_tempo'])) : '-';
                        $tgl_bayar = (!empty($inv['tgl_bayar']) && $inv['tgl_bayar'] != '0000-00-00 00:00:00') ? date('d-m-Y', strtotime($inv['tgl_bayar'])) : '-';
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['no_invoice']) ?></td>
                            <td><?= htmlspecialchars($inv['id_pelanggan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($inv['nama_lengkap'] ?? $inv['pelanggan_id']) ?></td>
                            <td><?= $tgl_terbit ?></td>
                            <td><?= $tgl_jatuh_tempo ?></td>
                            <td>Rp <?= number_format($inv['jumlah'], 0, ',', '.') ?></td>
                            <td><?= $status_badge ?></td>
                            <td><?= $tgl_bayar ?></td>
                            <td>
                                <a href="view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-info">Detail</a>
                                <?php if ($inv['status'] != 'paid'): ?>
                                    <a href="pay.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-success">Bayar</a>
                                <?php endif; ?>
                                <?php if ($is_superadmin): ?>
                                    <a href="delete.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus tagihan ini?')">Hapus</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#invoicesTable').DataTable({
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
        language: {
            url: "//cdn.datatables.net/plug-ins/1.11.5/i18n/id.json"
        },
        order: [[0, 'desc']]
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
