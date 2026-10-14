<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SESSION['user_role'] != 'super_admin' && $_SESSION['user_role'] != 'admin') {
    header("Location: index.php?error=unauthorized");
    exit();
}

$id_pelanggan = $_GET['id'] ?? '';
if (empty($id_pelanggan)) {
    header("Location: index.php");
    exit();
}

include '../../includes/mikrotik_api.php';

try {
    require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    // Ambil data pelanggan
    $stmt = $pdo->prepare("SELECT p.*, pk.router_id 
                           FROM pelanggan p 
                           LEFT JOIN paket_internet pk ON p.paket_id = pk.id 
                           WHERE p.id_pelanggan = ?");
    $stmt->execute([$id_pelanggan]);
    $pelanggan = $stmt->fetch();
    
    if (!$pelanggan) {
        header("Location: index.php");
        exit();
    }
    
    $username_pppoe = $pelanggan['id_pelanggan'];
    $router_id = $pelanggan['router_id'];
    
    $mikrotik_message = '';
    
    // Hapus user dari Mikrotik
    if ($router_id) {
        $result = delete_pppoe_user($router_id, $username_pppoe);
        if ($result['success']) {
            $mikrotik_message = "User PPPoE '$username_pppoe' berhasil dihapus dari Mikrotik.";
        } else {
            $mikrotik_message = "Gagal hapus user PPPoE: " . ($result['message'] ?? 'Unknown error');
        }
    } else {
        $mikrotik_message = "Tidak ada router terhubung untuk paket ini.";
    }
    
    // Hapus data dari database
    $stmt = $pdo->prepare("DELETE FROM invoice WHERE pelanggan_id = ?");
    $stmt->execute([$pelanggan['id']]);
    
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE pelanggan_id = ?");
    $stmt->execute([$pelanggan['id']]);
    
    $stmt = $pdo->prepare("DELETE FROM pelanggan WHERE id_pelanggan = ?");
    $stmt->execute([$id_pelanggan]);
    
    $msg = urlencode("Pelanggan berhasil dihapus. $mikrotik_message");
    header("Location: index.php?success=delete&msg=$msg");
    exit();
    
} catch(PDOException $e) {
    $msg = urlencode("Error: " . $e->getMessage());
    header("Location: index.php?error=1&msg=$msg");
    exit();
}
?>
