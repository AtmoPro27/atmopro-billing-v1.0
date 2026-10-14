<?php
/**
 * Settings Helper — dengan enkripsi & log
 */

// Kunci enkripsi (ganti dengan random string, simpan di tempat aman)
if (!defined('SETTINGS_ENCRYPT_KEY')) {
    define('SETTINGS_ENCRYPT_KEY', 'DemoBilling2026_SecureKey_ChangeMe!');
}

/**
 * Enkripsi value
 */
function encryptSetting($value) {
    if (empty($value)) return $value;
    $key = hash('sha256', SETTINGS_ENCRYPT_KEY);
    $iv = substr(hash('sha256', SETTINGS_ENCRYPT_KEY . 'iv'), 0, 16);
    return base64_encode(openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv));
}

/**
 * Dekripsi value
 */
function decryptSetting($value) {
    if (empty($value)) return $value;
    $key = hash('sha256', SETTINGS_ENCRYPT_KEY);
    $iv = substr(hash('sha256', SETTINGS_ENCRYPT_KEY . 'iv'), 0, 16);
    $decoded = base64_decode($value, true);
    if ($decoded === false) return $value; // bukan hasil enkripsi
    $result = openssl_decrypt($decoded, 'AES-256-CBC', $key, 0, $iv);
    return $result !== false ? $result : $value;
}

/**
 * Ambil setting (dengan dekripsi otomatis untuk key sensitif)
 */
function getSetting($key, $default = null) {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    
    try {
        require_once __DIR__ . '/../config/database.php';
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) return $default;
        
        $value = $row['setting_value'];
        // Dekripsi kalau key sensitif
        if (in_array($key, ['genieacs_password'])) {
            $value = decryptSetting($value);
        }
        $cache[$key] = $value;
        return $value;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Simpan setting (dengan enkripsi & log otomatis)
 */
function saveSetting($key, $value, $userId = null) {
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    
    // Ambil value lama
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $old = $stmt->fetch();
    $oldValue = $old ? $old['setting_value'] : null;
    
    // Enkripsi kalau sensitif
    $valueToSave = $value;
    if (in_array($key, ['genieacs_password'])) {
        $valueToSave = encryptSetting($value);
    }
    
    // Skip kalau tidak ada perubahan
    if ($oldValue === $valueToSave) return false;
    
    // Simpan
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    $stmt->execute([$key, $valueToSave]);
    
    // Log
    try {
        $stmt = $pdo->prepare("INSERT INTO settings_log (setting_key, old_value, new_value, changed_by) VALUES (?, ?, ?, ?)");
        // Mask password di log
        $oldLog = in_array($key, ['genieacs_password']) ? '***' : $oldValue;
        $newLog = in_array($key, ['genieacs_password']) ? '***' : $valueToSave;
        $stmt->execute([$key, $oldLog, $newLog, $userId]);
    } catch (Exception $e) {
        // Abaikan error log
    }
    
    return true;
}

/**
 * Test koneksi GenieACS
 */
function testGenieACSConnection($url = null, $username = null, $password = null, $timeout = 10) {
    if ($url === null) $url = getSetting('genieacs_url', 'http://localhost:7557');
    if ($username === null) $username = getSetting('genieacs_username');
    if ($password === null) $password = getSetting('genieacs_password');
    
    // Auto-prefix
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    
    $testUrl = rtrim($url, '/') . '/devices?limit=1';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    if ($username && $password) {
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    }
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $elapsed = round((microtime(true) - $start) * 1000);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'message' => 'Koneksi gagal: ' . $error, 'time' => $elapsed];
    }
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        $count = is_array($data) ? count($data) : 0;
        return ['success' => true, 'message' => "Koneksi OK! $count device ditemukan", 'time' => $elapsed, 'code' => $httpCode];
    }
    if ($httpCode == 401 || $httpCode == 403) {
        return ['success' => false, 'message' => 'Username/password salah', 'time' => $elapsed, 'code' => $httpCode];
    }
    return ['success' => false, 'message' => "HTTP $httpCode", 'time' => $elapsed, 'code' => $httpCode];
}
