<?php
/**
 * Webhook untuk menerima pesan dari Telegram
 * URL: https://your-domain.com/telegram_webhook.php
 */

require_once 'config/database.php';

define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE');
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');

function sendTelegram($chatId, $message) {
    $url = TELEGRAM_API_URL . 'sendMessage';
    $postData = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown'];
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

file_put_contents('/tmp/telegram_webhook.log', date('Y-m-d H:i:s') . " - " . $input . "\n", FILE_APPEND);

// ============================================================
// HANDLE CALLBACK QUERY (TOMBOL) — "Selesai Perbaikan"
// ============================================================
if (isset($data['callback_query'])) {
    $cb = $data['callback_query'];
    $callbackData = $cb['data'] ?? '';
    $callbackId   = $cb['id'] ?? '';
    $chatId       = $cb['message']['chat']['id'] ?? '';
    $messageId    = $cb['message']['message_id'] ?? '';
    
    // Default: nama dari Telegram
    $fromName     = $cb['from']['first_name'] ?? 'Teknisi';
    $fromUsername = $cb['from']['username'] ?? '';
    
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Cari user berdasarkan telegram_chat_id → pakai fullname dari DB
        $stmtUser = $pdo->prepare("
            SELECT fullname, username FROM users 
            WHERE telegram_chat_id = ? LIMIT 1
        ");
        $stmtUser->execute([$chatId]);
        $userDb = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if ($userDb && !empty($userDb['fullname'])) {
            // Title case: "MBAH ATMO" → "Mbah Atmo"
            $fromName = ucwords(strtolower($userDb['fullname']));
            if (empty($fromUsername) && !empty($userDb['username'])) {
                $fromUsername = $userDb['username'];
            }
        }
        
        if (strpos($callbackData, 'ack_') === 0) {
            $alarmId = (int) str_replace('ack_', '', $callbackData);
            
            $stmt = $pdo->prepare("SELECT * FROM alarm_aktif WHERE id = ?");
            $stmt->execute([$alarmId]);
            $alarm = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$alarm) {
                $answer = ['callback_query_id' => $callbackId, 'text' => "⚠️ Alarm tidak ditemukan"];
                @file_get_contents(TELEGRAM_API_URL . 'answerCallbackQuery?' . http_build_query($answer));
                http_response_code(200); echo 'OK'; exit;
            }
            
            if ($alarm['status'] == 'selesai') {
                $answer = ['callback_query_id' => $callbackId, 'text' => "⚠️ Alarm ini sudah ditandai selesai"];
                @file_get_contents(TELEGRAM_API_URL . 'answerCallbackQuery?' . http_build_query($answer));
                http_response_code(200); echo 'OK'; exit;
            }
            
            // Update status alarm
            $pdo->prepare("UPDATE alarm_aktif SET status='selesai', selesai=NOW() WHERE id = ?")
                ->execute([$alarmId]);
            
            // Jawab callback
            $answer = ['callback_query_id' => $callbackId, 'text' => "✅ Alarm ditandai selesai oleh {$fromName}"];
            @file_get_contents(TELEGRAM_API_URL . 'answerCallbackQuery?' . http_build_query($answer));
            
            // Footer
            $footer  = "\n\n━━━━━━━━━━━━━━━━━\n";
            $footer .= "✅ *SELESAI* — oleh {$fromName}";
            if ($fromUsername) $footer .= " (@{$fromUsername})";
            $footer .= "\n🕐 " . date('d/m/Y H:i');
            
            // Pesan asli
            $oldText = $cb['message']['text'] ?? '';
            
            // Ambil daftar penerima
            $penerima = json_decode($alarm['penerima'] ?? '[]', true) ?: [];
            
            // Pastikan yang klik ada di daftar
            $sudahAda = false;
            foreach ($penerima as $pen) {
                if (($pen['chat_id'] ?? '') == $chatId && ($pen['message_id'] ?? '') == $messageId) {
                    $sudahAda = true;
                    break;
                }
            }
            if (!$sudahAda) {
                $penerima[] = ['chat_id' => $chatId, 'message_id' => $messageId];
            }
            
            // Edit tiap pesan
            foreach ($penerima as $pen) {
                if (empty($pen['chat_id']) || empty($pen['message_id'])) continue;
                
                $editData = [
                    'chat_id' => $pen['chat_id'],
                    'message_id' => $pen['message_id'],
                    'text' => $oldText . $footer,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode(['inline_keyboard' => []])
                ];
                @file_get_contents(TELEGRAM_API_URL . 'editMessageText?' . http_build_query($editData));
                usleep(300000);
            }
            
            // Notif konfirmasi ke teknisi lain
            $uniqueChats = [];
            foreach ($penerima as $pen) {
                if (($pen['chat_id'] ?? '') != $chatId) {
                    $uniqueChats[$pen['chat_id']] = true;
                }
            }
            foreach (array_keys($uniqueChats) as $targetChat) {
                if (empty($targetChat)) continue;
                
                $notif  = "✅ *Konfirmasi*\n\n";
                $notif .= "Alarm `{$alarm['id_pelanggan']}` sudah diselesaikan oleh *{$fromName}*";
                if ($fromUsername) $notif .= " (@{$fromUsername})";
                $notif .= "\n🕐 " . date('d/m/Y H:i');
                
                @file_get_contents(TELEGRAM_API_URL . 'sendMessage?' . http_build_query([
                    'chat_id' => $targetChat,
                    'text' => $notif,
                    'parse_mode' => 'Markdown'
                ]));
                usleep(300000);
            }
            
            file_put_contents('/tmp/telegram_webhook.log', 
                date('Y-m-d H:i:s') . " - ACK alarm_id={$alarmId} by {$fromName} (chat: {$chatId})\n", 
                FILE_APPEND);
        }
    } catch (Exception $e) {
        error_log("Telegram Callback Error: " . $e->getMessage());
        file_put_contents('/tmp/telegram_webhook.log', 
            date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", 
            FILE_APPEND);
    }
    
    http_response_code(200);
    echo 'OK';
    exit;
}

// ============================================================
// HANDLE PESAN BIASA (REGISTER CHAT ID)
// ============================================================
if (isset($data['message'])) {
    $message = $data['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $username = $message['from']['username'] ?? 'unknown';
    $firstName = $message['from']['first_name'] ?? '';
    $lastName = $message['from']['last_name'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName);

    try {
        $pdo = Database::getInstance()->getConnection();

        $stmt = $pdo->prepare("
            SELECT id, username, fullname, role, telegram_chat_id
            FROM users
            WHERE username = ? OR fullname = ?
            LIMIT 1
        ");
        $stmt->execute([$username, $fullName]);
        $user = $stmt->fetch();

        if ($user) {
            $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = ? WHERE id = ?");
            $stmt->execute([$chatId, $user['id']]);

            $reply  = "✅ *Akun terhubung!*\n\n";
            $reply .= "👤 *User:* {$user['fullname']}\n";
            $reply .= "📱 *Username:* @{$user['username']}\n";
            $reply .= "🔑 *Role:* " . strtoupper($user['role']) . "\n\n";
            $reply .= "Anda akan menerima notifikasi dari sistem billing.\n";
            $reply .= "Terima kasih 🙏";

            sendTelegram($chatId, $reply);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, username, fullname, role
                FROM users
                WHERE fullname LIKE ?
                LIMIT 1
            ");
            $stmt->execute(['%' . $firstName . '%']);
            $user = $stmt->fetch();

            if ($user) {
                $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = ? WHERE id = ?");
                $stmt->execute([$chatId, $user['id']]);

                $reply  = "✅ *Akun terhubung!*\n\n";
                $reply .= "👤 *User:* {$user['fullname']}\n";
                $reply .= "📱 *Username:* @{$user['username']}\n";
                $reply .= "🔑 *Role:* " . strtoupper($user['role']) . "\n\n";
                $reply .= "Anda akan menerima notifikasi dari sistem billing.\n";
                $reply .= "Terima kasih 🙏";

                sendTelegram($chatId, $reply);
            } else {
                $reply  = "❌ *Akun tidak dikenali*\n\n";
                $reply .= "Silakan hubungi admin untuk mendaftarkan akun Anda.\n";
                $reply .= "Username Telegram: @{$username}\n";
                $reply .= "Chat ID: `{$chatId}`\n\n";
                $reply .= "Admin akan menghubungi Anda.";

                sendTelegram($chatId, $reply);

                $stmt = $pdo->query("
                    SELECT telegram_chat_id, fullname
                    FROM users
                    WHERE role = 'super_admin' AND telegram_chat_id IS NOT NULL
                    LIMIT 1
                ");
                $admin = $stmt->fetch();

                if ($admin) {
                    $adminMsg  = "🔔 *User Baru Connect Telegram*\n\n";
                    $adminMsg .= "👤 *Nama:* {$fullName}\n";
                    $adminMsg .= "📱 *Username:* @{$username}\n";
                    $adminMsg .= "🆔 *Chat ID:* `{$chatId}`\n\n";
                    $adminMsg .= "User ini belum terdaftar di database.\n";
                    $adminMsg .= "Silakan daftarkan manual dengan:\n";
                    $adminMsg .= "UPDATE users SET telegram_chat_id = '{$chatId}' WHERE username = '...'";

                    sendTelegram($admin['telegram_chat_id'], $adminMsg);
                }
            }
        }
    } catch(Exception $e) {
        error_log("Telegram Webhook Error: " . $e->getMessage());
    }
}

http_response_code(200);
echo 'OK';
