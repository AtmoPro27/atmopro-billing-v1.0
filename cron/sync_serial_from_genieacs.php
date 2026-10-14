<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/genieacs_functions.php';

$pdo = Database::getInstance()->getConnection();
date_default_timezone_set('Asia/Jakarta');

echo date('[Y-m-d H:i:s]') . " START - Sync Serial Number dari GenieACS\n";

// Ambil setting GenieACS dari DB
$baseUrl = getGenieACSSetting('genieacs_url') ?: 'http://localhost:7557';
if (!preg_match('#^https?://#i', $baseUrl)) $baseUrl = 'http://' . $baseUrl;
$baseUrl = rtrim($baseUrl, '/');

$auth_user = getGenieACSSetting('genieacs_username');
$auth_pass = getGenieACSSetting('genieacs_password');
$timeout = (int)(getGenieACSSetting('genieacs_timeout') ?: 60);

$url = $baseUrl . "/devices?projection=_id,VirtualParameters.pppoeUsername,VirtualParameters.getSerialNumber";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

if ($auth_user && $auth_pass) {
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$auth_user:$auth_pass");
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || empty($response)) {
    echo "❌ Gagal ambil data dari GenieACS (HTTP $httpCode)\n";
    exit;
}

$devices = json_decode($response, true);
if (!is_array($devices)) {
    echo "❌ Response bukan JSON array\n";
    exit;
}

echo "📦 Total device: " . count($devices) . "\n";

$updated = 0;
$not_found = 0;
$already_set = 0;

foreach ($devices as $dev) {
    $device_id = $dev['_id'] ?? '';
    $pppoe_user = $dev['VirtualParameters']['pppoeUsername']['_value'] ?? '';
    $serial = $dev['VirtualParameters']['getSerialNumber']['_value'] ?? '';

    if (empty($pppoe_user) || empty($serial)) continue;

    $stmt = $pdo->prepare("SELECT id, serial_number FROM pelanggan WHERE id_pelanggan = ?");
    $stmt->execute([$pppoe_user]);
    $cust = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cust) {
        $not_found++;
        continue;
    }

    if ($cust['serial_number'] == $serial) {
        $already_set++;
        continue;
    }

    $update = $pdo->prepare("UPDATE pelanggan SET serial_number = ? WHERE id = ?");
    $update->execute([$serial, $cust['id']]);
    $updated++;
    echo "  ✅ {$pppoe_user} → Serial: {$serial}\n";
}

echo "\n" . date('[Y-m-d H:i:s]') . " SELESAI\n";
echo "Updated: $updated | Sudah set: $already_set | Tidak ketemu: $not_found\n";
