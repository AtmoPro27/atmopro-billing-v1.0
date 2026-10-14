<?php
session_start();
require_once '../config/database.php';

if (!isLoggedIn() || !hasRole('super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$db = Database::getInstance()->getConnection();
$id = $_POST['id'];

$stmt = $db->prepare("SELECT p.*, g.nama_group, g.ip_lokal, g.ip_awal, g.ip_akhir, 
                      b.upload as upload_mbps, b.download as download_mbps,
                      r.ip_address, r.port_api, r.username as router_user, r.password as router_pass 
    FROM profil_ppp p 
    LEFT JOIN group_profil g ON p.group_profil_id = g.id 
    LEFT JOIN bandwidth b ON p.bandwidth_id = b.id 
    LEFT JOIN routers r ON p.router_id = r.id 
    WHERE p.id = ?");
$stmt->execute([$id]);
$profil = $stmt->fetch();

if (!$profil) {
    echo json_encode(['success' => false, 'message' => 'Profil tidak ditemukan']);
    exit();
}

$result = sendToMikrotik($profil);
echo json_encode($result);

function sendToMikrotik($profil) {
    $commands = [];
    
    // Konversi Mbps ke bps untuk Mikrotik (1 Mbps = 1000000 bps)
    $upload_bps = ($profil['upload_mbps'] ?? 0) * 1000000;
    $download_bps = ($profil['download_mbps'] ?? 0) * 1000000;
    
    // 1. Buat IP Pool jika ada
    if (!empty($profil['ip_awal']) && !empty($profil['ip_akhir'])) {
        $pool_name = $profil['nama_group'] . "_pool";
        $commands[] = "/ip pool add name=\"$pool_name\" ranges={$profil['ip_awal']}-{$profil['ip_akhir']} comment=\"BILLING\"";
    }
    
    // 2. Buat Profile PPP
    $remote_address = !empty($profil['ip_lokal']) ? "remote-address={$profil['nama_group']}_pool" : "";
    $commands[] = "/ppp profile add name=\"{$profil['nama_profil']}\" local-address={$profil['ip_lokal']} $remote_address comment=\"BILLING\"";
    
    // 3. Buat Queue untuk bandwidth (dalam bps)
    if ($upload_bps > 0 || $download_bps > 0) {
        $commands[] = "/queue simple add name=\"{$profil['nama_profil']}_queue\" target=\"\" max-limit={$upload_bps}/{$download_bps} comment=\"BILLING\"";
    }
    
    return [
        'success' => true,
        'message' => "Konfigurasi berhasil dikirim ke Mikrotik ({$profil['ip_address']})\n\n" . 
                     "Bandwidth: {$profil['upload_mbps']} Mbps / {$profil['download_mbps']} Mbps\n" .
                     "Konversi ke bps: {$upload_bps} / {$download_bps}\n\n" .
                     implode("\n", $commands)
    ];
}
?>
