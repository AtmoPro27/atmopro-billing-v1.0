<?php
require_once __DIR__ . "/../includes/session_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

include '../includes/header_portal.php';

// Generate CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    require_once "../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM pelanggan WHERE id_pelanggan = ?");
    $stmt->execute([$_SESSION['pelanggan_kode']]);
    $pelanggan = $stmt->fetch();

    $message = '';
    $error = '';

    // Proses update profil
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error = "Token keamanan tidak valid. Silakan refresh halaman.";
        } else {
            $nama_lengkap = trim($_POST['nama_lengkap']);
            $alamat = trim($_POST['alamat']);
            $no_telepon = trim($_POST['no_telepon']);

            if (!empty($no_telepon) && !preg_match('/^62[0-9]{9,14}$/', $no_telepon)) {
                $error = "No Telepon harus dimulai dengan 62 (contoh: 628000000000)";
            } else {
                $stmt = $pdo->prepare("UPDATE pelanggan SET nama_lengkap = ?, alamat = ?, no_telepon = ? WHERE id_pelanggan = ?");
                $stmt->execute([$nama_lengkap, $alamat, $no_telepon, $_SESSION['pelanggan_kode']]);

                $_SESSION['pelanggan_nama'] = $nama_lengkap;
                $_SESSION['pelanggan_alamat'] = $alamat;
                $_SESSION['pelanggan_telp'] = $no_telepon;

                $message = "Profil berhasil diupdate!";
                $pelanggan['nama_lengkap'] = $nama_lengkap;
                $pelanggan['alamat'] = $alamat;
                $pelanggan['no_telepon'] = $no_telepon;
                
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }

    // Proses ganti password
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $error = "Token keamanan tidak valid. Silakan refresh halaman.";
        } else {
            $password_lama = $_POST['password_lama'];
            $password_baru = $_POST['password_baru'];
            $konfirmasi = $_POST['konfirmasi_password'];

            if (empty($password_lama) || empty($password_baru) || empty($konfirmasi)) {
                $error = "Semua field password harus diisi!";
            } elseif ($password_baru !== $konfirmasi) {
                $error = "Password baru dan konfirmasi tidak sama!";
            } elseif (strlen($password_baru) < 4) {
                $error = "Password minimal 4 karakter!";
            } elseif (!password_verify($password_lama, $pelanggan['password'])) {
                $error = "Password lama salah!";
            } else {
                $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE pelanggan SET password = ? WHERE id_pelanggan = ?");
                $stmt->execute([$hashed_password, $_SESSION['pelanggan_kode']]);
                $message = "Password berhasil diubah!";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }

} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-user-edit"></i> Edit Profil
                    <a href="dashboard.php" class="btn btn-sm btn-light float-end">Kembali</a>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <!-- Form Update Profil -->
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ID Pelanggan</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($pelanggan['id_pelanggan']) ?>" readonly disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($pelanggan['nama_lengkap']) ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="3" required><?= htmlspecialchars($pelanggan['alamat']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No Telepon (WA)</label>
                            <input type="text" name="no_telepon" class="form-control" value="<?= htmlspecialchars($pelanggan['no_telepon']) ?>" placeholder="628000000000">
                            <small class="text-muted">Format: 62xxxxxxxxxx (tanpa 0 di awal)</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Profil</button>
                    </form>

                    <hr>

                    <!-- Form Ganti Password -->
                    <h5 class="mt-4">Ganti Password</h5>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Password Lama</label>
                            <input type="password" name="password_lama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password_baru" class="form-control" required minlength="4">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" name="konfirmasi_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-warning">Ganti Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer_portal.php'; ?>
