<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM income_manual WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'id' => $data['id'],
        'tanggal' => $data['tanggal'],
        'keterangan' => $data['keterangan'],
        'kategori' => $data['kategori'],
        'jumlah' => $data['jumlah'],
        'jumlah_formatted' => number_format($data['jumlah'], 0, ',', '.'),
        'catatan' => $data['catatan']
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
