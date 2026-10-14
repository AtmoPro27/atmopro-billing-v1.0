#!/usr/bin/php
<?php
/**
 * Cron: Hapus log setting GenieACS yang lebih dari X hari
 * Default: 30 hari
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Ambil setting berapa hari log disimpan (default 30)
    $retentionDays = 30;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute(['log_retention_days']);
        $row = $stmt->fetch();
        if ($row && (int)$row['setting_value'] > 0) {
            $retentionDays = (int)$row['setting_value'];
        }
    } catch (Exception $e) {
        // abaikan
    }
    
    // Hapus log lama
    $stmt = $pdo->prepare("DELETE FROM settings_log WHERE setting_key LIKE 'genieacs_%' AND changed_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$retentionDays]);
    $deleted = $stmt->rowCount();
    
    $log = date('[Y-m-d H:i:s]') . " ✅ Cleanup settings_log: $deleted log dihapus (retention: {$retentionDays} hari)";
} catch (Exception $e) {
    $log = date('[Y-m-d H:i:s]') . " ❌ Error: " . $e->getMessage();
}

file_put_contents('/var/log/cleanup_settings_log.log', $log . PHP_EOL, FILE_APPEND);
echo $log . PHP_EOL;
