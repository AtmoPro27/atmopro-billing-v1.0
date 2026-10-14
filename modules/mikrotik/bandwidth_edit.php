<?php
ob_start();
require_once __DIR__ . '/../../includes/auth_check.php';

$id = $_GET['id'] ?? '';
$router_id = $_GET['router_id'] ?? '';
if (empty($id) || empty($router_id)) {
    header("Location: index.php");
    exit();
}

include '../../includes/header.php';

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM routers WHERE id = ?");
    $stmt->execute([$router_id]);
    $router = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM mikrotik_bandwidth WHERE id = ? AND router_id = ?");
    $stmt->execute([$id, $router_id]);
    $bandwidth = $stmt->fetch();
    
    if (!$bandwidth) {
        header("Location: bandwidth.php?router_id=" . $router_id);
        exit();
    }
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $target = $_POST['target'];
    $max_limit_down = $_POST['max_limit_down'];
    $max_limit_up = $_POST['max_limit_up'];
    
    try {
        $stmt = $pdo->prepare("UPDATE mikrotik_bandwidth SET name = ?, target = ?, max_limit_down = ?, max_limit_up = ? WHERE id = ? AND router_id = ?");
        $stmt->execute([$name, $target, $max_limit_down, $max_limit_up, $id, $router_id]);
        
        // Redirect dengan JavaScript
        echo "<script>window.location.href = 'bandwidth.php?router_id=" . $router_id . "&success=1';</script>";
        exit();
    } catch(PDOException $e) {
        $error = "Gagal mengupdate: " . $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-edit"></i> Edit Bandwidth - <?= htmlspecialchars($router['nama_router'] ?? '') ?>
        <a href="bandwidth.php?router_id=<?= $router_id ?>" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nama</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($bandwidth['name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Target</label>
                <input type="text" name="target" class="form-control" value="<?= htmlspecialchars($bandwidth['target']) ?>" required>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Max Limit Down</label>
                    <input type="text" name="max_limit_down" class="form-control" value="<?= htmlspecialchars($bandwidth['max_limit_down']) ?>" placeholder="10M">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Max Limit Up</label>
                    <input type="text" name="max_limit_up" class="form-control" value="<?= htmlspecialchars($bandwidth['max_limit_up']) ?>" placeholder="5M">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
            <a href="bandwidth.php?router_id=<?= $router_id ?>" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
