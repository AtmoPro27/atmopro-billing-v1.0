<?php
require_once __DIR__ . "/../../includes/auth_check.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Hanya super_admin yang bisa mengakses
if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard.php");
    exit();
}

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

// Proses update user
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
        
        $fullname = $_POST['fullname'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        $is_active = $_POST['is_active'];
        $password = $_POST['password'];
        
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, role = ?, is_active = ?, password = ? WHERE id = ?");
            $stmt->execute([$fullname, $email, $phone, $role, $is_active, $hashed, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, role = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$fullname, $email, $phone, $role, $is_active, $id]);
        }
        
        echo "<script>window.location.href = 'index.php?success=2';</script>";
        exit();
    } catch(PDOException $e) {
        $error = "Gagal mengupdate user: " . $e->getMessage();
    }
}

// Ambil data user
try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: index.php");
        exit();
    }
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit"></i> Edit User: <?= htmlspecialchars($user['username']) ?>
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Password (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">No Telepon</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="super_admin" <?= $user['role'] == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                        <option value="kasir" <?= $user['role'] == 'kasir' ? 'selected' : '' ?>>Kasir</option>
                        <option value="teknisi" <?= $user['role'] == 'teknisi' ? 'selected' : '' ?>>Teknisi</option>
                        <option value="pelanggan" <?= $user['role'] == 'pelanggan' ? 'selected' : '' ?>>Pelanggan</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1" <?= $user['is_active'] == 1 ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $user['is_active'] == 0 ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            <a href="index.php" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
