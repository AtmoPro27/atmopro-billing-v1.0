<?php
require_once __DIR__ . "/wa_notification.php";

/**
 * Kirim notifikasi tiket masuk via WhatsApp
 */
function sendTicketNotification($pdo, $ticket, $customer, $action = 'create') {
    try {
        // Cek apakah WA Gateway aktif
        $stmt = $pdo->query("SELECT * FROM wa_settings WHERE is_active = 1 LIMIT 1");
        if (!$stmt->fetch()) {
            return ['success' => false, 'message' => 'WA Gateway tidak aktif'];
        }
        
        // Tentukan tipe tiket
        $ticketType = determineTicketType($ticket);
        $ticket['ticket_type'] = $ticketType;
        
        // Cek konfigurasi notifikasi untuk tipe tiket
        $stmt = $pdo->prepare("
            SELECT * FROM wa_ticket_notifications 
            WHERE ticket_type = ? AND is_active = 1
        ");
        $stmt->execute([$ticketType]);
        $configs = $stmt->fetchAll();
        
        if (empty($configs)) {
            return ['success' => false, 'message' => 'Konfigurasi notifikasi tidak ditemukan untuk tipe: ' . $ticketType];
        }
        
        // Generate pesan berdasarkan tipe tiket
        if ($ticketType == 'payment') {
            $message = generatePaymentTicketMessage($ticket, $customer);
        } else {
            $message = generateTechnicalTicketMessage($ticket, $customer);
        }
        
        $sentTo = [];
        $failedTo = [];
        $allPhones = [];
        
        // Kirim ke setiap konfigurasi
        foreach ($configs as $config) {
            // Cek apakah phone_numbers berisi 'auto_' (ambil dari database users)
            if (strpos($config['phone_numbers'], 'auto_') === 0) {
                $role = str_replace('auto_', '', $config['phone_numbers']);
                // Ambil nomor dari tabel users berdasarkan role
                $stmt = $pdo->prepare("
                    SELECT phone FROM users 
                    WHERE role = ? AND is_active = 1 AND phone IS NOT NULL AND phone != ''
                ");
                $stmt->execute([$role]);
                $users = $stmt->fetchAll();
                
                $phones = [];
                foreach ($users as $user) {
                    $phones[] = $user['phone'];
                }
            } else {
                // Jika manual, split dengan koma
                $phones = array_map('trim', explode(',', $config['phone_numbers']));
            }
            
            // Filter duplikat
            foreach ($phones as $phone) {
                if (empty($phone)) continue;
                if (in_array($phone, $allPhones)) continue;
                $allPhones[] = $phone;
            }
        }
        
        // Kirim ke semua penerima (tanpa duplikat)
        foreach ($allPhones as $phone) {
            if (empty($phone)) continue;
            
            // Kirim via Fonnte
            $result = sendWANotification($pdo, $phone, $message, 'ticket_' . $ticketType);
            
            if ($result['success']) {
                $sentTo[] = $phone;
            } else {
                $failedTo[] = $phone;
            }
            
            // Delay 1 detik untuk menghindari rate limit
            sleep(1);
        }
        
        // Update status notifikasi di tiket
        if (!empty($sentTo)) {
            try {
                // Cek apakah kolom wa_notification_sent ada
                $stmt = $pdo->query("SHOW COLUMNS FROM tickets LIKE 'wa_notification_sent'");
                if ($stmt->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE tickets ADD COLUMN wa_notification_sent TINYINT(1) DEFAULT 0");
                    $pdo->exec("ALTER TABLE tickets ADD COLUMN wa_notification_time DATETIME NULL");
                }
                
                $stmt = $pdo->prepare("
                    UPDATE tickets 
                    SET wa_notification_sent = 1, wa_notification_time = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$ticket['id']]);
            } catch(PDOException $e) {
                error_log("Error updating ticket notification status: " . $e->getMessage());
            }
        }
        
        return [
            'success' => !empty($sentTo),
            'sent_to' => $sentTo,
            'failed_to' => $failedTo,
            'total_sent' => count($sentTo),
            'total_failed' => count($failedTo),
            'all_recipients' => $allPhones,
            'message' => "Terkirim ke " . count($sentTo) . " penerima" . 
                        (count($failedTo) > 0 ? ", gagal ke " . count($failedTo) . " penerima" : "")
        ];
        
    } catch(Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function determineTicketType($ticket) {
    // Cek apakah ada field ticket_type
    if (isset($ticket['ticket_type']) && !empty($ticket['ticket_type'])) {
        return $ticket['ticket_type'];
    }
    
    // Cek dari subject
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
    
    // Default: payment
    return 'payment';
}

function generatePaymentTicketMessage($ticket, $customer) {
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
    $message .= "Terima kasih 🙏\n";
    
    return $message;
}

function generateTechnicalTicketMessage($ticket, $customer) {
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
    $message .= "Terima kasih 🙏\n";
    
    return $message;
}
