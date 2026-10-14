<?php
session_start();
require_once '../config/database.php';

if (!isLoggedIn() || !hasRole('super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$db = Database::getInstance()->getConnection();
$id = $_POST['id'];

$stmt = $db->prepare("SELECT g.*, r.ip_address, r.port_api, r.username as router_user, r.password as router_pass 
    FROM group_profil g 
    LEFT JOIN routers r ON g.router_id = r.id 
    WHERE g.id = ?");
$stmt->execute([$id]);
$group = $stmt->fetch();

if (!$group) {
    echo json_encode(['success' => false, 'message' => 'Group profil tidak ditemukan']);
    exit();
}

$result = syncToMikrotik($group);
echo json_encode($result);

function syncToMikrotik($group) {
    $commands = [];
    
    // Buat IP Pool di Mikrotik
    if (!empty($group['ip_awal']) && !empty($group['ip_akhir'])) {
        $pool_name = $group['nama_group'] . "_pool";
        $commands[] = "/ip pool add name=\"$pool_name\" ranges={$group['ip_awal']}-{$group['ip_akhir']} comment=\"BILLING\"";
        $commands[] = "/ip pool print where name=\"$pool_name\"";
    }
    
    // Buat IP Local/Network jika ada
    if (!empty($group['ip_lokal'])) {
        $commands[] = "/ip address add address={$group['ip_lokal']} interface=bridge comment=\"BILLING\"";
    }
    
    return [
        'success' => true,
        'message' => "Konfigurasi berhasil dikirim ke Mikrotik ({$group['ip_address']})\n\n" . implode("\n", $commands),
        'commands' => $commands
    ];
}
?>
