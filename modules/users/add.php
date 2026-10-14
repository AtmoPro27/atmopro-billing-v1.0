<?php
require_once __DIR__ . "/../../includes/session_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Hanya super_admin yang bisa mengakses
if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard.php");
    exit();
}

// Proses tambah user
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
        
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $fullname = $_POST['fullname'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        $is_active = $_POST['is_active'] ?? 1;
        
        // Cek username sudah ada
        $cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->fetchColumn() > 0) {
            $error = "Username sudah terdaftar!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, email, phone, role, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $fullname, $email, $phone, $role, $is_active]);
            
            echo "<script>window.location.href = 'index.php?success=1';</script>";
            exit();
        }
    } catch(PDOException $e) {
        $error = "Gagal menambah user: " . $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-plus"></i> Tambah User
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="fullname" class="form-control" required>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">No Telepon</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="super_admin">Super Admin</option>
                        <option value="kasir">Kasir</option>
                        <option value="teknisi">Teknisi</option>
                        <option value="pelanggan">Pelanggan</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1">Aktif</option>
                        <option value="0">Nonaktif</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
