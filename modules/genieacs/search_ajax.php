<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../includes/genieacs_functions.php';

header('Content-Type: application/json');

$keyword = $_GET['q'] ?? '';
$keyword = trim($keyword);

if (empty($keyword)) {
    echo json_encode(['success' => true, 'data' => [], 'total' => 0]);
    exit();
}

// Ambil SEMUA device dari GenieACS
$all_devices = getGenieACSDevices(2000);

// Ambil nama pelanggan dari DB
try {
    require_once '../../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT id_pelanggan, nama_lengkap FROM pelanggan WHERE status = 'active' OR status_aktif = 'aktif'");
    $customer_names = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $customer_names[$row['id_pelanggan']] = $row['nama_lengkap'];
    }
} catch (Exception $e) {
    $customer_names = [];
}

// Filter
$keywordLower = strtolower($keyword);
$filtered = [];
foreach ($all_devices as $d) {
    $customer_name = $customer_names[$d['id']] ?? '-';
    $d['customer_name'] = $customer_name;
    
    $searchable = strtolower(
        ($d['id'] ?? '') . ' ' .
        $customer_name . ' ' .
        ($d['ssid'] ?? '') . ' ' .
        ($d['ip_pppoe'] ?? '') . ' ' .
        ($d['status'] ?? '')
    );
    
    if (strpos($searchable, $keywordLower) !== false) {
        $filtered[] = $d;
    }
}

echo json_encode([
    'success' => true,
    'data' => $filtered,
    'total' => count($filtered),
    'keyword' => $keyword
]);
