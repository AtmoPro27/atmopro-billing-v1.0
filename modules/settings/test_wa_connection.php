<?php
require_once __DIR__ . '/../../includes/auth_check.php';
header('Content-Type: application/json');
if (($_SESSION['user_role'] ?? '') !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');
$useSaved = $input['use_saved'] ?? true;

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Nomor tujuan wajib diisi']);
    exit;
}

$phone = preg_replace('/[^0-9]/', '', $phone);
if (substr($phone, 0, 1) === '0') $phone = '62' . substr($phone, 1);
if (substr($phone, 0, 2) !== '62') $phone = '62' . $phone;

if (strlen($phone) < 10 || strlen($phone) > 15) {
    echo json_encode(['success' => false, 'message' => 'Format nomor tidak valid: ' . $phone]);
    exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    $pdo = Database::getInstance()->getConnection();

    if ($useSaved) {
        $wa = $pdo->query("SELECT * FROM wa_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } else {
        $wa = [
            'provider' => $input['provider'] ?? 'fonnte',
            'api_url' => $input['api_url'] ?? '',
            'api_key' => $input['api_key'] ?? '',
            'sender_number' => $input['sender_number'] ?? '',
        ];
    }
    if (empty($wa['provider'])) $wa['provider'] = 'fonnte';

    if (empty($wa['api_url']) || empty($wa['api_key'])) {
        echo json_encode(['success' => false, 'message' => 'API URL / API Key belum diisi']);
        exit;
    }

    $testMessage = "TES KONEKSI WA GATEWAY\n\n"
        . "Halo! Ini pesan tes dari sistem billing.\n\n"
        . "Waktu: " . date('d-m-Y H:i:s') . "\n"
        . "Nomor tujuan: " . $phone . "\n"
        . "Gateway: " . parse_url($wa['api_url'], PHP_URL_HOST) . "\n\n"
        . "Jika Anda menerima pesan ini, WA Gateway berfungsi dengan baik.";

    $start = microtime(true);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $wa['api_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query([
            'target' => $phone,
            'message' => $testMessage,
            'countryCode' => '62',
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $wa['api_key'],
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    $elapsed = round((microtime(true) - $start) * 1000);

    $data = json_decode($response, true);
    $success = false;
    $message = '';

    if ($httpCode === 200) {
        if (is_array($data) && isset($data['status'])) {
            $success = ($data['status'] === true || $data['status'] === 'true');
            $message = $success
                ? 'Pesan tes berhasil dikirim ke ' . $phone
                : 'Gagal: ' . ($data['reason'] ?? 'Alasan tidak diketahui');
        } else {
            $success = true;
            $message = 'Respons diterima (HTTP 200)';
        }
    } else {
        $message = 'HTTP ' . $httpCode . ($curlError ? ' - ' . $curlError : '');
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS wa_logs (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20),
            message TEXT,
            type VARCHAR(50),
            status ENUM('sent','failed','pending') DEFAULT 'pending',
            response TEXT,
            created_at DATETIME,
            INDEX idx_phone (phone),
            INDEX idx_status (status)
        )");
        $stmt = $pdo->prepare("INSERT INTO wa_logs (phone, message, type, status, response, created_at) VALUES (?, ?, 'test_connection', ?, ?, NOW())");
        $stmt->execute([$phone, $testMessage, $success ? 'sent' : 'failed', substr((string)$response, 0, 500)]);
    } catch (Exception $e) {}

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'elapsed_ms' => $elapsed,
        'http_code' => $httpCode,
        'phone' => $phone,
        'raw_response' => substr((string)$response, 0, 500),
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
