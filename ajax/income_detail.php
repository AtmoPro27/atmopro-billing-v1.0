<?php
session_start();
if (!isset($_SESSION['user_id'])) { echo "Login dulu"; exit; }
try {
    require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT i.*, p.nama_lengkap FROM invoice i JOIN pelanggan p ON i.pelanggan_id = p.id WHERE i.status = 'paid' AND DATE(i.tgl_bayar) = CURDATE()");
    $data = $stmt->fetchAll();
    echo '<table class="table table-bordered"><tr><th>No Invoice</th><th>Pelanggan</th><th>Jumlah</th></tr>';
    foreach($data as $row) {
        echo "<tr><td>{$row['no_invoice']}</td><td>{$row['nama_lengkap']}</td><td>Rp " . number_format($row['jumlah']) . "</td></tr>";
    }
    if(count($data)==0) echo "<tr><td colspan='3'>Tidak ada data</td></tr>";
    echo '</table>';
} catch(Exception $e) { echo "Error"; }
?>
