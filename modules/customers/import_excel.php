<?php
require_once __DIR__ . '/../../includes/auth_check.php';

// Hanya super_admin yang bisa import
if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: index.php?error=unauthorized");
    exit();
}

include '../../includes/header.php';

$message = '';
$imported = 0;
$failed = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_csv'])) {
    $file = $_FILES['file_csv'];

    if ($file['error'] != 0) {
        $message = '<div class="alert alert-danger">Gagal upload file!</div>';
    } else {
        $allowed = ['csv'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $message = '<div class="alert alert-danger">Format file harus .csv</div>';
        } else {
            try {
                require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();

                // Ambil semua paket yang tersedia
                $stmt_paket_all = $pdo->query("SELECT id, nama_paket FROM paket_internet");
                $paket_list = [];
                while ($row = $stmt_paket_all->fetch()) {
                    $paket_list[strtolower(trim($row['nama_paket']))] = $row['id'];
                }

                // Default paket
                $default_package_id = !empty($paket_list) ? reset($paket_list) : 26;

                // Baca file CSV
                $row = 1;
                if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
                    // Skip BOM
                    $bom = fgets($handle);
                    if (strpos($bom, chr(0xEF).chr(0xBB).chr(0xBF)) === 0) {
                        // BOM detected, skip
                    } else {
                        rewind($handle);
                    }

                    // Baca header
                    $header = fgetcsv($handle, 1000, ',');

                    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                        $row++;

                        if (empty(array_filter($data))) continue;

                        $id_pelanggan = trim($data[0] ?? '');
                        $nama_lengkap = trim($data[1] ?? '');
                        $alamat = trim($data[2] ?? '');
                        $no_telepon = trim($data[3] ?? '');
                        $tipe_pelanggan = trim($data[4] ?? 'reguler');
                        $paket_nama = trim($data[5] ?? '');
                        $jatuh_tempo = trim($data[6] ?? '');
                        $status = trim($data[7] ?? '');

                        // Validasi tipe pelanggan
                        $tipe_valid = ['reguler', 'vip', 'fasum', 'nardi'];
                        if (empty($tipe_pelanggan) || !in_array(strtolower($tipe_pelanggan), $tipe_valid)) {
                            $tipe_pelanggan = 'reguler';
                        } else {
                            $tipe_pelanggan = strtolower($tipe_pelanggan);
                        }

                        if (empty($id_pelanggan) || empty($nama_lengkap)) {
                            $failed++;
                            $errors[] = "Baris $row: ID Pelanggan dan Nama wajib diisi";
                            continue;
                        }

                        // Cek apakah ID sudah ada
                        $cek = $pdo->prepare("SELECT COUNT(*) FROM pelanggan WHERE id_pelanggan = ?");
                        $cek->execute([$id_pelanggan]);
                        if ($cek->fetchColumn() > 0) {
                            $failed++;
                            $errors[] = "Baris $row: ID Pelanggan '$id_pelanggan' sudah ada";
                            continue;
                        }

                        // Cari package_id
                        $package_id = $default_package_id;
                        if (!empty($paket_nama)) {
                            $paket_key = strtolower(trim($paket_nama));
                            if (isset($paket_list[$paket_key])) {
                                $package_id = $paket_list[$paket_key];
                            } else {
                                $stmt_paket = $pdo->prepare("SELECT id FROM paket_internet WHERE nama_paket LIKE ? LIMIT 1");
                                $stmt_paket->execute(["%$paket_nama%"]);
                                $paket = $stmt_paket->fetch();
                                if ($paket) {
                                    $package_id = $paket['id'];
                                } else {
                                    $errors[] = "Baris $row: Paket '$paket_nama' tidak ditemukan, menggunakan paket default";
                                }
                            }
                        }

                        // Mapping status
                        $status_db = 'active';
                        if (strtolower($status) == 'tidak aktif') $status_db = 'inactive';
                        if (strtolower($status) == 'diblokir') $status_db = 'blocked';
                        if (strtolower($status) == 'isolir') $status_db = 'inactive';

                        // Parse tanggal jatuh tempo
                        $jatuh_tempo_db = null;
                        if (!empty($jatuh_tempo) && $jatuh_tempo != '-') {
                            $jatuh_tempo_db = date('Y-m-d 23:59:59', strtotime(str_replace('/', '-', $jatuh_tempo)));
                            if ($jatuh_tempo_db == '1970-01-01 23:59:59') {
                                $jatuh_tempo_db = null;
                            }
                        }

                        if ($jatuh_tempo_db == null) {
                            $jatuh_tempo_db = date('Y-m-d 23:59:59', strtotime('+30 days'));
                        }

                        try {
                            // START TRANSACTION
                            $pdo->beginTransaction();

                            // 1. Insert ke tabel pelanggan (password di-hash)
                            $hashed_password = password_hash('123456', PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("INSERT INTO pelanggan (id_pelanggan, nama_lengkap, alamat, no_telepon, tipe_pelanggan, jatuh_tempo, status, paket_id, password, status_aktif, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif', NOW())");
                            $stmt->execute([$id_pelanggan, $nama_lengkap, $alamat, $no_telepon, $tipe_pelanggan, $jatuh_tempo_db, $status_db, $package_id, $hashed_password]);

                            // Ambil ID pelanggan yang baru diinsert
                            $pelanggan_id = $pdo->lastInsertId();

                           // Reset total_online_seconds untuk pelanggan baru
 			    $pdo->prepare("UPDATE pelanggan SET total_online_seconds = 0 WHERE id = ?") ->execute([$pelanggan_id]);


                            // 3. Insert ke tabel pelanggan_traffic (untuk quota)
                            $stmt3 = $pdo->prepare("INSERT INTO pelanggan_traffic (pelanggan_id, total_bytes, bulan, last_update) VALUES (?, 0, DATE_FORMAT(NOW(), '%Y-%m'), NOW())");
                            $stmt3->execute([$pelanggan_id]);

                            $pdo->commit();
                            $imported++;
                            
                        } catch(PDOException $e) {
                            $pdo->rollBack();
                            $failed++;
                            $errors[] = "Baris $row: " . $e->getMessage();
                        }
                    }
                    fclose($handle);
                }

                $message = '<div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Import selesai!<br>
                    Berhasil: ' . $imported . ' data (tabel pelanggan + customers + traffic)<br>
                    Gagal: ' . $failed . ' data
                </div>';

                if (!empty($errors)) {
                    $message .= '<div class="alert alert-warning" style="max-height:300px; overflow-y:auto;"><strong>Detail Error:</strong><ul>';
                    foreach ($errors as $err) {
                        $message .= '<li>' . htmlspecialchars($err) . '</li>';
                    }
                    $message .= '</ul></div>';
                }

            } catch(Exception $e) {
                $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
        }
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-file-import"></i> Import Data Pelanggan dari CSV
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?= $message ?>

        <div class="alert alert-info">
            <strong>Panduan Import:</strong>
            <ul>
                <li>Format file: <strong>.CSV</strong> (Excel bisa save as CSV)</li>
                <li>Kolom wajib: <strong>ID Pelanggan</strong> dan <strong>Nama Lengkap</strong></li>
                <li>Kolom <strong>Paket</strong> harus sesuai dengan nama paket di database</li>
                <li>Data akan otomatis masuk ke tabel <strong>pelanggan</strong>, <strong>customers</strong>, dan <strong>pelanggan_traffic</strong></li>
                <li>Password default untuk login portal: <strong>123456</strong></li>
            </ul>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Pilih File CSV</label>
                <input type="file" name="file_csv" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Import Data</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
