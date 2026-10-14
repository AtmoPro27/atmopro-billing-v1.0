<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Anda harus login";
    exit;
}

try {
    require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();

    // Ambil SEMUA data pelanggan baru bulan ini (tanpa limit)
    $stmt = $pdo->query("SELECT id_pelanggan, nama_lengkap, status, created_at
                         FROM pelanggan
                         WHERE MONTH(created_at) = MONTH(CURDATE())
                         AND YEAR(created_at) = YEAR(CURDATE())
                         ORDER BY created_at DESC");
    $data = $stmt->fetchAll();

    $total = count($data);
    echo '<div class="alert alert-info">Total Pelanggan Baru: <strong>' . $total . '</strong> pelanggan';
    if ($total > 50) {
        echo '<br><small class="text-muted">⚠️ Data di bawah ini bisa di-scroll (tarik ke bawah/kanan)</small>';
    }
    echo '</div>';

    if ($total > 0) {
        echo '<div class="table-responsive" style="max-height: 500px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px;">';
        echo '<table class="table table-bordered table-striped mb-0">
            <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                <tr>
                    <th>ID Pelanggan</th>
                    <th>Nama Lengkap</th>
                    <th>Status</th>
                    <th>Tanggal Daftar</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($data as $row) {
            $status_badge = ($row['status'] == 'active') ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Tidak Aktif</span>';
            $created_at = date('d-m-Y H:i:s', strtotime($row['created_at']));
            echo '<tr>
                <td>' . htmlspecialchars($row['id_pelanggan']) . '</td>
                <td>' . htmlspecialchars($row['nama_lengkap']) . '</td>
                <td>' . $status_badge . '</td>
                <td>' . $created_at . '</td>
            </tr>';
        }

        echo '</tbody>
        </table>
        </div>';
        echo '<div class="mt-2 text-muted small">Menampilkan semua ' . $total . ' data. Untuk mencari, gunakan fitur pencarian di browser (Ctrl+F).</div>';
    } else {
        echo '<div class="alert alert-info text-center">Belum ada pelanggan baru bulan ini</div>';
    }

} catch(PDOException $e) {
    echo '<div class="alert alert-danger">Database Error: ' . $e->getMessage() . '</div>';
}
?>
