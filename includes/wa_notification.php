<?php
/**
 * Kirim notifikasi WhatsApp via Multi-Provider
 *
 * Provider yang didukung:
 * - fonnte
 * - wablast (wablas)
 * - woowa
 * - rapiwa
 * - custom
 */

function sendWANotification($pdo, $phone, $message, $type = 'invoice') {
    try {
        // Ambil setting WA dari database
        $stmt = $pdo->query("SELECT * FROM wa_settings WHERE is_active = 1 LIMIT 1");
        $waSetting = $stmt->fetch();

        if (!$waSetting) {
            return ['success' => false, 'message' => 'WA Gateway tidak aktif'];
        }

        // Format nomor telepon
        $phone = formatPhoneNumber($phone);

        // Log sebelum kirim
        logWAActivity($pdo, $phone, $message, $type, 'pending');

        // Kirim sesuai provider
        $provider = $waSetting['provider'] ?? 'fonnte';
        $url = $waSetting['api_url'];
        $apiKey = $waSetting['api_key'];
        $sender = $waSetting['sender_number'] ?? '';

        switch ($provider) {
            case 'fonnte':
                $result = sendToFonnte($url, $apiKey, $phone, $message);
                break;
            case 'wablast':
                $result = sendToWablast($url, $apiKey, $phone, $message);
                break;
            case 'woowa':
                $result = sendToWoowa($url, $apiKey, $sender, $phone, $message);
                break;
            case 'rapiwa':
                $result = sendToRapiwa($url, $apiKey, $phone, $message);
                break;
            default:
                $result = sendToCustom($url, $apiKey, $phone, $message);
        }

        // Update log
        logWAActivity($pdo, $phone, $message, $type, $result['success'] ? 'sent' : 'failed', $result['message']);

        return $result;

    } catch(Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function formatPhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) == '0') {
        $phone = '62' . substr($phone, 1);
    }
    if (substr($phone, 0, 2) != '62') {
        $phone = '62' . $phone;
    }
    return $phone;
}

// ============================================
// FONNTE
// ============================================
function sendToFonnte($url, $apiKey, $phone, $message) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query([
            'target' => $phone,
            'message' => $message,
            'countryCode' => '62'
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if (isset($data['status']) && $data['status'] == true) {
            return ['success' => true, 'message' => 'Pesan terkirim via Fonnte', 'response' => $data];
        } else {
            return ['success' => false, 'message' => $data['reason'] ?? 'Gagal mengirim', 'response' => $data];
        }
    }
    return ['success' => false, 'message' => "HTTP Error: $httpCode - $error", 'response' => $response];
}

// ============================================
// WABLAST (WABLAS)
// ============================================
function sendToWablast($url, $apiKey, $phone, $message) {
    $curl = curl_init();
    $fullUrl = $url . '?token=' . urlencode($apiKey);
    curl_setopt_array($curl, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query([
            'phone' => $phone,
            'message' => $message,
        ]),
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if (isset($data['status']) && $data['status'] == true) {
            return ['success' => true, 'message' => 'Pesan terkirim via Wablas', 'response' => $data];
        }
        return ['success' => false, 'message' => $data['message'] ?? 'Gagal mengirim', 'response' => $data];
    }
    return ['success' => false, 'message' => "HTTP Error: $httpCode - $error", 'response' => $response];
}

// ============================================
// WOOWA
// ============================================
function sendToWoowa($url, $apiKey, $sender, $phone, $message) {
    $curl = curl_init();
    $payload = json_encode([
        'phone_no' => $phone,
        'key' => $apiKey,
        'message' => $message,
    ]);
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($httpCode == 200) {
        return ['success' => true, 'message' => 'Pesan terkirim via Woowa', 'response' => $response];
    }
    return ['success' => false, 'message' => "HTTP Error: $httpCode - $error", 'response' => $response];
}

// ============================================
// RAPIWA
// ============================================
function sendToRapiwa($url, $apiKey, $phone, $message) {
    $curl = curl_init();
    $payload = json_encode([
        'phone' => $phone,
        'message' => $message,
    ]);
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $apiKey,
            'Content-Type: application/json'
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($httpCode == 200) {
        return ['success' => true, 'message' => 'Pesan terkirim via Rapiwa', 'response' => $response];
    }
    return ['success' => false, 'message' => "HTTP Error: $httpCode - $error", 'response' => $response];
}

// ============================================
// CUSTOM
// ============================================
function sendToCustom($url, $apiKey, $phone, $message) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => http_build_query([
            'target' => $phone,
            'phone' => $phone,
            'message' => $message,
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($httpCode == 200) {
        return ['success' => true, 'message' => 'Pesan terkirim via Custom', 'response' => $response];
    }
    return ['success' => false, 'message' => "HTTP Error: $httpCode - $error", 'response' => $response];
}

function logWAActivity($pdo, $phone, $message, $type, $status, $response = null) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wa_logs (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(20) NOT NULL,
                message TEXT,
                type VARCHAR(50),
                status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
                response TEXT,
                created_at DATETIME,
                INDEX idx_phone (phone),
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            )
        ");

        $stmt = $pdo->prepare("
            INSERT INTO wa_logs (phone, message, type, status, response, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$phone, $message, $type, $status, $response]);
    } catch(PDOException $e) {
        error_log("WA Log error: " . $e->getMessage());
    }
}
