<?php
function log_admin_activity($pdo, $action, $target_type = null, $target_id = null, $details = null) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        return false;
    }
    
$allowed_actions = ['LOGIN', 'INVOICE_CREATE', 'INVOICE_EDIT', 'INVOICE_DELETE', 'INVOICE_PAID', 'INVOICE_UNPAID', 'TICKET_CREATE', 'TICKET_CREATE_ALL', 'TICKET_REPLY', 'TICKET_STATUS'];
    if (!in_array($action, $allowed_actions)) {
        return false;
    }
    
    $admin_id = $_SESSION['user_id'];
    $admin_username = $_SESSION['username'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, admin_username, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$admin_id, $admin_username, $action, $target_type, $target_id, $details, $ip_address]);
    } catch(PDOException $e) {
        error_log("Gagal mencatat log: " . $e->getMessage());
        return false;
    }
}
?>
