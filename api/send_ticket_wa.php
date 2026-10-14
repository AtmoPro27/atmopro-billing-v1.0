<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/wa_ticket_notification.php";

try {
    $pdo = Database::getInstance()->getConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    $ticketId = $input['ticket_id'] ?? $_POST['ticket_id'] ?? null;
    
    if (!$ticketId) {
        echo json_encode(['success' => false, 'message' => 'Ticket ID tidak ditemukan']);
        exit();
    }
    
    // Ambil data tiket dan customer
    $stmt = $pdo->prepare("
        SELECT t.*, c.nama_lengkap, c.no_telepon, c.id_pelanggan, c.alamat
        FROM tickets t
        JOIN pelanggan c ON t.pelanggan_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Tiket tidak ditemukan']);
        exit();
    }
    
    // Kirim notifikasi
    $result = sendTicketNotification($pdo, $ticket, $ticket, 'create');
    
    echo json_encode($result);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
