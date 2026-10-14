<?php
require_once __DIR__ . "/../../includes/session_check.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Koneksi database
try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $user = [
            'username' => $user_name,
            'email' => '',
            'full_name' => $user_name,
            'role' => $user_role
        ];
    }
} catch(PDOException $e) {
    $user = [
        'username' => $user_name,
        'email' => '',
        'full_name' => $user_name,
        'role' => $user_role
    ];
}

$page_title = 'Profil Saya';
include '../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-user-circle"></i> Profil Saya
    </div>
    <div class="card-body">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Profil berhasil diupdate!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Gagal mengupdate profil!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="update.php">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username'] ?? $user_name) ?>" readonly disabled>
                <small class="text-muted">Username tidak dapat diubah</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? $user_name) ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Role</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['role'] ?? $user_role) ?>" readonly disabled>
            </div>
            
            <hr>
            <h6><i class="fas fa-key"></i> Ganti Password</h6>
            <div class="mb-3">
                <label class="form-label">Password Baru</label>
                <input type="password" name="new_password" class="form-control" placeholder="Kosongkan jika tidak ingin mengganti password">
            </div>
            <div class="mb-3">
                <label class="form-label">Konfirmasi Password Baru</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Ketik ulang password baru">
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
            <a href="/dashboard.php" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
