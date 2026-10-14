<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();

$labels = [];
$data = [];

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($date));
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) as total FROM invoice WHERE status = 'paid' AND DATE(tgl_bayar) = ?");
    $stmt->execute([$date]);
    $data[] = $stmt->fetch()['total'];
}

echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
?>
