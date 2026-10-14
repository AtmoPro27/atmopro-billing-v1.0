<?php
session_start();
if (!isset($_SESSION['user_id'])) { echo "Login dulu"; exit; }
try {
    require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT id_pelanggan, nama_lengkap, username, paket_id, status FROM pelanggan WHERE status = 'active' ORDER BY id DESC LIMIT 20");
    $data = $stmt->fetchAll();
    echo '<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr><th>ID</th><th>Nama</th><th>Username</th><th>Status</th></tr>
        </thead>
        <tbody>';
    foreach($data as $row) {
        echo "<tr>
            <td>{$row['id_pelanggan']}</td>
            <td>{$row['nama_lengkap']}</td>
            <td>{$row['username']}</td>
            <td><span class='badge bg-success'>Aktif</span></td>
        </tr>";
    }
    if(count($data)==0) echo "<tr><td colspan='4' class='text-center'>Belum ada data</td></tr>";
    echo '</tbody></table></div>';
} catch(Exception $e) { echo "Error: " . $e->getMessage(); }
?>
