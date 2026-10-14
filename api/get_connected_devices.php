<?php
require_once __DIR__ . '/../includes/auth_check_api.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/genieacs_functions.php';
header('Content-Type: application/json');

$deviceId = $_GET['device_id'] ?? '';
if (empty($deviceId)) {
    echo json_encode(['success' => false, 'message' => 'Device ID tidak valid.']);
    exit;
}

// Ambil setting GenieACS dari DB
$baseUrl = getGenieACSSetting('genieacs_url') ?: 'http://localhost:7557';
if (!preg_match('#^https?://#i', $baseUrl)) $baseUrl = 'http://' . $baseUrl;
$baseUrl = rtrim($baseUrl, '/');

$auth_user = getGenieACSSetting('genieacs_username');
$auth_pass = getGenieACSSetting('genieacs_password');

// Query ke NBI — ambil LANDevice.1.Hosts (bukan VirtualParameters.ConnectedDevices)
$query = json_encode(['_id' => $deviceId]);
$url = $baseUrl . '/devices/?query=' . urlencode($query) . '&projection=InternetGatewayDevice.LANDevice.1.Hosts';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

if ($auth_user && $auth_pass) {
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$auth_user:$auth_pass");
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data dari GenieACS (HTTP ' . $httpCode . ').']);
    exit;
}

$data = json_decode($response, true);

// NBI return array — ambil index 0
if (is_array($data) && isset($data[0])) {
    $data = $data[0];
}

// Ambil Hosts
$hostList = $data['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'] ?? [];

$devices = [];
foreach ($hostList as $key => $host) {
    // Skip metadata keys
    if (in_array($key, ['_object', '_timestamp', '_writable'])) continue;
    if (!is_array($host)) continue;

    // Ambil nilai-nilai
    $hostname  = $host['HostName']['_value']      ?? '';
    $ip        = $host['IPAddress']['_value']     ?? '';
    $mac       = $host['MACAddress']['_value']    ?? '';
    $interface = $host['InterfaceType']['_value'] ?? '';

    // Skip host yang benar-benar kosong
    if ($hostname === '' && $ip === '' && $mac === '') continue;

    $devices[] = [
        'hostname'  => $hostname ?: '-',
        'ip'        => $ip ?: '-',
        'mac'       => $mac ?: '-',
        'interface' => $interface ?: '-'
    ];
}

echo json_encode([
    'success' => true,
    'total'   => count($devices),
    'devices' => $devices
]);
