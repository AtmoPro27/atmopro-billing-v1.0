<?php
require_once __DIR__ . '/../includes/auth_check_portal.php';
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getInstance()->getConnection();
require_once __DIR__ . '/../includes/genieacs_functions.php';
header('Content-Type: application/json');

// 🔒 AMBIL DEVICE_ID DARI SESSION — BUKAN DARI USER INPUT!
// Ini mencegah pelanggan A reboot ONT pelanggan B.

$stmt = $pdo->prepare("SELECT genieacs_device_id FROM pelanggan WHERE id = ?");
$stmt->execute([$_SESSION['pelanggan_id']]);
$deviceId = $stmt->fetchColumn();

if (empty($deviceId)) {
    echo json_encode(['success' => false, 'message' => 'Device ID tidak ditemukan untuk akun Anda.']);
    exit;
}

// Ambil setting GenieACS dari DB
$baseUrl = getGenieACSSetting('genieacs_url') ?: 'http://localhost:7557';
if (!preg_match('#^https?://#i', $baseUrl)) $baseUrl = 'http://' . $baseUrl;
$baseUrl = rtrim($baseUrl, '/');

$auth_user = getGenieACSSetting('genieacs_username');
$auth_pass = getGenieACSSetting('genieacs_password');

$encodedId = str_replace('-', '%2D', $deviceId);
$url = $baseUrl . '/devices/' . $encodedId . '/tasks';
$payload = json_encode(['name' => 'reboot']);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if ($auth_user && $auth_pass) {
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$auth_user:$auth_pass");
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode == 200 || $httpCode == 201 || $httpCode == 202) {
    echo json_encode(['success' => true, 'message' => 'Perintah reboot dikirim. ONT akan reboot dalam 1-5 menit. Koneksi terputus sekitar 1-2 menit.']);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal. HTTP ' . $httpCode,
        'debug' => [
            'url' => $url,
            'response' => substr($response, 0, 300),
            'curl_error' => $curlError
        ]
    ]);
}
