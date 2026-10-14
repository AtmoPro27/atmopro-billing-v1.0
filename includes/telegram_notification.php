<?php
/**
 * Kirim notifikasi ke Telegram
 * @param PDO $pdo Koneksi database
 * @param string $chatId Chat ID tujuan
 * @param string $message Pesan yang akan dikirim
 * @param string $type Tipe notifikasi
 * @return array
 */
function sendTelegramNotification($pdo, $chatId, $message, $type = 'general') {
    try {
        // Ambil token dari database
        $stmt = $pdo->query("
            SELECT key_setting, value FROM pengaturan 
            WHERE key_setting IN ('telegram_bot_token', 'telegram_notification_enabled')
        ");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key_setting']] = $row['value'];
        }
        
        $token = $settings['telegram_bot_token'] ?? '';
        $enabled = $settings['telegram_notification_enabled'] ?? '0';
        
        if ($enabled != '1' || empty($token) || empty($chatId)) {
            return [
                'success' => false,
                'message' => 'Telegram not enabled or missing token/chat_id'
            ];
        }
        
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $success = $httpCode == 200;
        $status = $success ? 'sent' : 'failed';
        
        // =============================================================
        // SIMPAN LOG (DENGAN ERROR HANDLING)
        // =============================================================
        try {
            // Cek apakah tabel telegram_logs ada
            $stmt_check = $pdo->query("SHOW TABLES LIKE 'telegram_logs'");
            if ($stmt_check->rowCount() > 0) {
                // Sanitasi message untuk menghindari error karakter
                $clean_message = mb_substr($message, 0, 500, "UTF-8");
                $clean_response = is_string($response) ? mb_substr($response, 0, 500, "UTF-8") : '';
                
                $stmt_log = $pdo->prepare("
                    INSERT INTO telegram_logs (chat_id, message, status, response, type, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt_log->execute([
                    $chatId,
                    $clean_message,
                    $status,
                    $clean_response,
                    $type
                ]);
            }
        } catch(Exception $e) {
            // Log error tapi jangan stop proses
            error_log("Telegram Log error: " . $e->getMessage());
        }
        
        return [
            'success' => $success,
            'message' => $success ? 'Pesan terkirim' : 'Gagal kirim pesan',
            'http_code' => $httpCode,
            'response' => $response,
            'curl_error' => $curlError
        ];
        
    } catch(Exception $e) {
        error_log("Telegram notification error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
