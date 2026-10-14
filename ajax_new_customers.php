<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Akses ditolak";
    exit();
}

try {
    require_once __DIR__ . "/config/database.php";
$pdo = Database::getInstance()->getConnection();

    // Ambil pelanggan yang dibuat bulan ini
    $stmt = $pdo->query("
        SELECT id_pelanggan, nama_lengkap, no_telepon, alamat, paket_id, status, created_at 
        FROM pelanggan 
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
        ORDER BY created_at DESC
    ");
    $customers = $stmt->fetchAll();

    $total = count($customers);

    echo "<div class='alert alert-info mb-3'>Total Pelanggan Baru Bulan Ini: <strong>$total</strong></div>";

    if ($total > 0) {
        echo '<div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>ID Pelanggan</th>
                        <th>Nama Lengkap</th>
                        <th>No Telepon</th>
                        <th>Alamat</th>
                        <th>Paket ID</th>
                        <th>Status</th>
                        <th>Tanggal Daftar</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($customers as $cust) {
            $status_badge = ($cust['status'] == 'active') ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Tidak Aktif</span>';
            echo "<tr>
                <td>" . htmlspecialchars($cust['id_pelanggan']) . "</td>
                <td>" . htmlspecialchars($cust['nama_lengkap']) . "</td>
                <td>" . htmlspecialchars($cust['no_telepon']) . "</td>
                <td>" . htmlspecialchars(substr($cust['alamat'], 0, 50)) . "</td>
                <td>" . $cust['paket_id'] . "</td>
                <td>$status_badge</span></td>
                <td>" . date('d-m-Y H:i:s', strtotime($cust['created_at'])) . "</td>
            </tr>";
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-warning">Belum ada pelanggan baru bulan ini.</div>';
    }
} catch(PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>
