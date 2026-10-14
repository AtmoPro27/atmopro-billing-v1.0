<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$id = $_GET['id'] ?? 0;

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    // Hapus investor
    $stmt = $pdo->prepare("DELETE FROM investor WHERE id = ?");
    $stmt->execute([$id]);
    
    // Recalculate prosentase semua investor
    $total_modal = $pdo->query("SELECT COALESCE(SUM(modal), 0) as total FROM investor")->fetch()['total'];
    
    if ($total_modal > 0) {
        $update = $pdo->prepare("UPDATE investor SET prosentase = (modal / ?) * 100");
        $update->execute([$total_modal]);
    }
    
    header("Location: bagi_hasil.php?success=deleted");
    exit();
    
} catch(PDOException $e) {
    header("Location: bagi_hasil.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
