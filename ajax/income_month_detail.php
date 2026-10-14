<?php
session_start();
if (!isset($_SESSION['user_id'])) { echo "Login dulu"; exit; }
try {
    require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT i.*, p.nama_lengkap FROM invoice i JOIN pelanggan p ON i.pelanggan_id = p.id WHERE i.status = 'paid' AND MONTH(i.tgl_bayar) = MONTH(CURDATE()) AND YEAR(i.tgl_bayar) = YEAR(CURDATE()) ORDER BY i.tgl_bayar DESC");
    $data = $stmt->fetchAll();
    
    $total = array_sum(array_column($data, 'jumlah'));
    
    echo '<div class="alert alert-info">Total Income Bulan Ini: <strong>Rp ' . number_format($total, 0, ',', '.') . '</strong></div>';
    echo '<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr><th>No Invoice</th><th>Pelanggan</th><th>Jumlah</th><th>Tanggal Bayar</th></tr>
        </thead>
        <tbody>';
    foreach($data as $row) {
        echo "<tr>
            <td>{$row['no_invoice']}</td>
            <td>{$row['nama_lengkap']}</td>
            <td>Rp " . number_format($row['jumlah'], 0, ',', '.') . "</td>
            <td>" . date('d-m-Y', strtotime($row['tgl_bayar'])) . "</td>
        </tr>";
    }
    if(count($data)==0) echo "<tr><td colspan='4' class='text-center'>Belum ada pembayaran bulan ini</td></tr>";
    echo '</tbody></table></div>';
} catch(Exception $e) { echo "Error: " . $e->getMessage(); }
?>
