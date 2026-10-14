<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Akses ditolak";
    exit();
}

try {
    require_once __DIR__ . "/config/database.php";
$pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->query("
        SELECT id_pelanggan, nama_lengkap, no_telepon, alamat, paket_id, status 
        FROM pelanggan 
        WHERE status = 'blocked'
        ORDER BY id_pelanggan
    ");
    $customers = $stmt->fetchAll();

    $total = count($customers);

    echo "<div class='alert alert-danger mb-3'>Total Pelanggan Isolir: <strong>$total</strong></div>";

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
                    </tr>
                </thead>
                <tbody>';
        foreach ($customers as $cust) {
            echo "<tr>
                <td>" . htmlspecialchars($cust['id_pelanggan']) . "</td>
                <td>" . htmlspecialchars($cust['nama_lengkap']) . "</td>
                <td>" . htmlspecialchars($cust['no_telepon']) . "</td>
                <td>" . htmlspecialchars(substr($cust['alamat'], 0, 50)) . "</td>
                <td>" . $cust['paket_id'] . "</td>
                <td><span class='badge bg-danger'>Isolir</span></td>
            </tr>";
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-info">Tidak ada pelanggan isolir.</div>';
    }
} catch(PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>
