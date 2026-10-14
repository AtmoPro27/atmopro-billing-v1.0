<?php
require_once __DIR__ . "/telegram_notification.php";

/**
 * Kirim notifikasi tiket via Telegram
 */
function sendTelegramTicketNotification($pdo, $ticket, $customer, $action = 'create') {
    try {
        // Tentukan tipe tiket
        $ticketType = determineTicketType($ticket);
        
        // Cek konfigurasi notifikasi
        $stmt = $pdo->prepare("
            SELECT * FROM telegram_ticket_notifications 
            WHERE ticket_type = ? AND is_active = 1
        ");
        $stmt->execute([$ticketType]);
        $configs = $stmt->fetchAll();
        
        if (empty($configs)) {
            return ['success' => false, 'message' => 'Konfigurasi notifikasi tidak ditemukan'];
        }
        
        // Generate pesan
        if ($ticketType == 'payment') {
            $message = generateTelegramPaymentMessage($ticket, $customer);
        } else {
            $message = generateTelegramTechnicalMessage($ticket, $customer);
        }
        
        $sentTo = [];
        $failedTo = [];
        
        // Kirim ke setiap konfigurasi
        foreach ($configs as $config) {
            if (strpos($config['chat_ids'], 'auto_') === 0) {
                $role = str_replace('auto_', '', $config['chat_ids']);
                $stmt = $pdo->prepare("
                    SELECT telegram_chat_id FROM users 
                    WHERE role = ? AND is_active = 1 
                    AND telegram_chat_id IS NOT NULL AND telegram_chat_id != ''
                ");
                $stmt->execute([$role]);
                $users = $stmt->fetchAll();
                
                $chatIds = [];
                foreach ($users as $user) {
                    if (!empty($user['telegram_chat_id'])) {
                        $chatIds[] = $user['telegram_chat_id'];
                    }
                }
            } else {
                $chatIds = array_map('trim', explode(',', $config['chat_ids']));
            }
            
            foreach ($chatIds as $chatId) {
                if (empty($chatId)) continue;
                
                $result = sendTelegramNotification($pdo, $chatId, $message, 'ticket_' . $ticketType);
                
                if ($result['success']) {
                    $sentTo[] = $chatId;
                } else {
                    $failedTo[] = $chatId;
                }
                
                sleep(1);
            }
        }
        
        return [
            'success' => !empty($sentTo),
            'sent_to' => $sentTo,
            'failed_to' => $failedTo,
            'total_sent' => count($sentTo),
            'total_failed' => count($failedTo),
            'message' => "Terkirim ke " . count($sentTo) . " penerima" . 
                        (count($failedTo) > 0 ? ", gagal ke " . count($failedTo) . " penerima" : "")
        ];
        
    } catch(Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function determineTicketType($ticket) {
    if (isset($ticket['ticket_type']) && !empty($ticket['ticket_type'])) {
        return $ticket['ticket_type'];
    }
    
    $subject = strtolower($ticket['subject'] ?? '');
    $message = strtolower($ticket['message'] ?? '');
    
    $paymentKeywords = ['pembayaran', 'bayar', 'invoice', 'tagihan', 'payment', 'bill', 'konfirmasi pembayaran'];
    $technicalKeywords = ['gangguan', 'error', 'rusak', 'mati', 'internet', 'technical', 'problem', 'issue', 'down', 'putus'];
    
    foreach ($paymentKeywords as $keyword) {
        if (strpos($subject, $keyword) !== false || strpos($message, $keyword) !== false) {
            return 'payment';
        }
    }
    
    foreach ($technicalKeywords as $keyword) {
        if (strpos($subject, $keyword) !== false || strpos($message, $keyword) !== false) {
            return 'technical';
        }
    }
    
    return 'payment';
}

function generateTelegramPaymentMessage($ticket, $customer) {
    $customerName = $customer['nama_lengkap'] ?? $customer['fullname'] ?? 'Customer';
    $customerPhone = $customer['no_telepon'] ?? $customer['phone'] ?? '-';
    
    $message = "💰 *NOTIFIKASI TIKET PEMBAYARAN*\n\n";
    $message .= "──────────────────\n";
    $message .= "🏷️ *No. Tiket: #{$ticket['no_ticket']}*\n";
    $message .= "👤 *Customer: {$customerName}*\n";
    $message .= "📱 *Telp: {$customerPhone}*\n";
    $message .= "──────────────────\n";
    $message .= "📌 *Subject:*\n{$ticket['subject']}\n\n";
    
    if (!empty($ticket['message'])) {
        $msg = strlen($ticket['message']) > 200 ? substr($ticket['message'], 0, 200) . '...' : $ticket['message'];
        $message .= "📝 *Deskripsi:*\n{$msg}\n\n";
    }
    
    if (!empty($ticket['priority'])) {
        $priorityLabels = [
            'low' => '🟢 Rendah',
            'medium' => '🟡 Sedang', 
            'high' => '🔴 Tinggi',
            'urgent' => '⛔ URGENT'
        ];
        $message .= "⚡ *Prioritas: " . ($priorityLabels[$ticket['priority']] ?? $ticket['priority']) . "*\n";
    }
    
    $message .= "──────────────────\n";
    $message .= "📅 *Tanggal: " . date('d/m/Y H:i', strtotime($ticket['created_at'])) . "*\n";
    $message .= "Harap segera diproses oleh kasir.\n";
    $message .= "Terima kasih 🙏";
    
    return $message;
}

function generateTelegramTechnicalMessage($ticket, $customer) {
    $customerName = $customer['nama_lengkap'] ?? $customer['fullname'] ?? 'Customer';
    $customerPhone = $customer['no_telepon'] ?? $customer['phone'] ?? '-';
    $customerAddress = $customer['alamat'] ?? '-';
    
    $message = "🔧 *NOTIFIKASI TIKET GANGGUAN/TEKNIS*\n\n";
    $message .= "──────────────────\n";
    $message .= "🏷️ *No. Tiket: #{$ticket['no_ticket']}*\n";
    $message .= "👤 *Customer: {$customerName}*\n";
    $message .= "📱 *Telp: {$customerPhone}*\n";
    
    if (!empty($customerAddress) && $customerAddress != '-') {
        $message .= "📍 *Alamat: {$customerAddress}*\n";
    }
    
    $message .= "──────────────────\n";
    $message .= "📌 *Subject:*\n{$ticket['subject']}\n\n";
    
    if (!empty($ticket['message'])) {
        $msg = strlen($ticket['message']) > 200 ? substr($ticket['message'], 0, 200) . '...' : $ticket['message'];
        $message .= "📝 *Deskripsi:*\n{$msg}\n\n";
    }
    
    if (!empty($ticket['priority'])) {
        $priorityLabels = [
            'low' => '🟢 Rendah',
            'medium' => '🟡 Sedang', 
            'high' => '🔴 Tinggi',
            'urgent' => '⛔ URGENT'
        ];
        $message .= "⚡ *Prioritas: " . ($priorityLabels[$ticket['priority']] ?? $ticket['priority']) . "*\n";
    }
    
    $message .= "──────────────────\n";
    $message .= "📅 *Tanggal: " . date('d/m/Y H:i', strtotime($ticket['created_at'])) . "*\n";
    $message .= "Harap segera ditangani oleh teknisi.\n";
    $message .= "Terima kasih 🙏";
    
    return $message;
}
