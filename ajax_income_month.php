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
        SELECT i.*, p.nama_lengkap 
        FROM invoice i 
        JOIN pelanggan p ON i.pelanggan_id = p.id 
        WHERE i.status = 'paid' AND MONTH(i.tgl_bayar) = MONTH(CURDATE()) AND YEAR(i.tgl_bayar) = YEAR(CURDATE())
        ORDER BY i.tgl_bayar DESC
    ");
    $invoices = $stmt->fetchAll();

    $total = array_sum(array_column($invoices, 'jumlah'));

    echo "<div class='alert alert-success mb-3'>Total Income Bulan Ini: <strong>Rp " . number_format($total, 0, ',', '.') . "</strong></div>";

    if (count($invoices) > 0) {
        echo '<div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>No Invoice</th>
                        <th>Pelanggan</th>
                        <th>Jumlah</th>
                        <th>Tanggal Bayar</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($invoices as $inv) {
            echo "<tr>
                <td>" . htmlspecialchars($inv['no_invoice']) . "</td>
                <td>" . htmlspecialchars($inv['nama_lengkap']) . "</td>
                <td>Rp " . number_format($inv['jumlah'], 0, ',', '.') . "</td>
                <td>" . date('d-m-Y H:i:s', strtotime($inv['tgl_bayar'])) . "</td>
            </tr>";
        }
        echo '</tbody></table></div>';
    } else {
        echo '<div class="alert alert-warning">Belum ada income bulan ini.</div>';
    }
} catch(PDOException $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}
?>
