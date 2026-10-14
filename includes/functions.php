<?php
function getSettings() {
    static $settings = null;
    if ($settings === null) {
        try {
            require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->query("SELECT key_setting, value FROM pengaturan");
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['key_setting']] = $row['value'];
            }
        } catch(PDOException $e) {
            $settings = [];
        }
    }
    return $settings;
}

function getSetting($key, $default = '') {
    $settings = getSettings();
    return $settings[$key] ?? $default;
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
