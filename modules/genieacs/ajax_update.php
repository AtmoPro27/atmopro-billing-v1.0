<?php
// Matikan semua error reporting
error_reporting(0);
ini_set('display_errors', 0);

// Bersihkan output buffer
while (ob_get_level()) ob_end_clean();
ob_clean();

// Set header JSON
header('Content-Type: application/json');

// Load fungsi - gunakan path absolut
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/genieacs_functions.php';

// Ambil data POST
$deviceId = $_POST['device_id'] ?? '';
$parameter = $_POST['parameter'] ?? '';
$value = $_POST['value'] ?? '';

// Validasi
if (empty($deviceId) || empty($parameter)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Proses update
$result = updateDeviceParameter($deviceId, $parameter, $value);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Berhasil diupdate']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal update. Pastikan device online.']);
}
?>
