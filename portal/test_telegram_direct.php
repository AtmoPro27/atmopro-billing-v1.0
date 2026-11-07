<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== TEST TELEGRAM ===\n";

require_once '../config/database.php';
require_once '../includes/telegram_notification.php';

$pdo = Database::getInstance()->getConnection();

// Ambil token dan chat_id dari database
$stmt = $pdo->query("
    SELECT key_setting, value FROM pengaturan 
    WHERE key_setting IN ('telegram_bot_token', 'telegram_chat_id', 'telegram_notification_enabled')
");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['key_setting']] = $row['value'];
}

echo "Token: " . ($settings['telegram_bot_token'] ? substr($settings['telegram_bot_token'], 0, 10) . '...' : 'KOSONG') . "\n";
echo "Chat ID: " . ($settings['telegram_chat_id'] ?? 'KOSONG') . "\n";
echo "Enabled: " . ($settings['telegram_notification_enabled'] ?? '0') . "\n";

if (empty($settings['telegram_bot_token'])) {
    echo "❌ Token kosong! Isi di database\n";
    exit;
}

// Kirim test
$result = sendTelegramNotification($pdo, '5260779112', '🧪 Test notifikasi langsung', 'test');
echo "Hasil: ";
print_r($result);
?>
