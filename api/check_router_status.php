<?php
require_once __DIR__ . '/../includes/auth_check_api.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT * FROM routers WHERE status = 'active'");
$routers = $stmt->fetchAll();

foreach ($routers as $router) {
    $ip = $router['ip_address'];
    $id = $router['id'];
    $name = $router['nama_router'];
    
    // Test ping (1 paket, timeout 2 detik)
    exec("ping -c 1 -W 2 $ip 2>/dev/null", $output, $return_var);
    
    if ($return_var == 0) {
        $status = 'online';
        $message = "ONLINE - Ping ke $ip berhasil";
    } else {
        $status = 'offline';
        $message = "OFFLINE - Ping ke $ip gagal";
    }
    
    $stmt2 = $db->prepare("UPDATE routers SET connection_status = ?, last_check = NOW(), last_check_message = ? WHERE id = ?");
    $stmt2->execute([$status, $message, $id]);
    
    echo "$name: " . ($return_var == 0 ? "ONLINE" : "OFFLINE") . "\n";
}

echo "Router check completed at " . date('Y-m-d H:i:s') . "\n";
?>
