<?php
header('Content-Type: application/json');

// Ambil setting dari DB
try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT key_setting, value FROM pengaturan WHERE key_setting IN ('company_name', 'company_tagline', 'company_logo')");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key_setting']] = $row['value'];
    }
} catch (Exception $e) {
    $settings = [];
}

$company_name = $settings['company_name'] ?? 'AtmoBilling';
$company_tagline = $settings['company_tagline'] ?? 'High Speed Internet';
$company_logo = $settings['company_logo'] ?? '';

// Cari icon
$icon_192 = '/portal/icons/icon-192.png';
$icon_512 = '/portal/icons/icon-512.png';

if (!empty($company_logo) && file_exists(__DIR__ . '/../' . $company_logo)) {
    $icon_192 = '/' . $company_logo;
    $icon_512 = '/' . $company_logo;
}

echo json_encode([
    'name' => $company_name . ' Portal',
    'short_name' => $company_name,
    'description' => 'Portal Pelanggan ' . $company_name . ' - ' . $company_tagline,
    'start_url' => '/portal/dashboard.php',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#0d5c1e',
    'theme_color' => '#0d5c1e',
    'scope' => '/portal/',
    'icons' => [
        ['src' => $icon_192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => $icon_512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']
    ],
    'categories' => ['business', 'finance', 'utilities'],
    'lang' => 'id',
    'dir' => 'ltr'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
