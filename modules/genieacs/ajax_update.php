<?php
require_once __DIR__ . '/../../includes/auth_check.php';

error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_clean();

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/genieacs_functions.php';

$deviceId = $_POST['device_id'] ?? '';
$parameter = $_POST['parameter'] ?? '';
$value = $_POST['value'] ?? '';

if (empty($deviceId) || empty($parameter)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Ambil hasil lengkap untuk tahu HTTP code
$result = updateGenieACSParameter($deviceId, $parameter, $value);

if ($result['success']) {
    $httpCode = $result['code'] ?? 0;

    if ($httpCode == 202) {
        echo json_encode([
            'success' => true,
            'message' => '✅ Perubahan berhasil dikirim. Akan aktif dalam 5 menit (saat ONT connect).'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => '✅ Berhasil diupdate.'
        ]);
    }
} else {
    $httpCode = $result['code'] ?? 0;
    echo json_encode([
        'success' => false,
        'message' => "❌ Gagal update. HTTP: $httpCode. Pastikan device online."
    ]);
}
?>
