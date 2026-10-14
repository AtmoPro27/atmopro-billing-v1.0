<?php
require_once __DIR__ . "/telegram_notification.php";

/**
 * Kirim notifikasi tiket ke internal (kasir, teknisi, admin, super_admin)
 * Dipanggil dari portal pelanggan saat pelanggan buat tiket
 */
function sendTelegramInternalNotification($pdo, $ticket, $action = 'create') {
    error_log("=== sendTelegramInternalNotification START ===");
    error_log("Ticket ID: " . ($ticket['id'] ?? 'unknown'));
    error_log("Ticket Subject: " . ($ticket['subject'] ?? 'unknown'));
    
    try {
        // Tentukan tipe tiket
        $ticketType = determineTicketType($ticket);
        error_log("Ticket Type: " . $ticketType);

        // Cek konfigurasi notifikasi
        $stmt = $pdo->prepare("
            SELECT * FROM telegram_ticket_notifications
            WHERE ticket_type = ? AND is_active = 1
        ");
        $stmt->execute([$ticketType]);
        $configs = $stmt->fetchAll();
        error_log("Configs found: " . count($configs));

        if (empty($configs)) {
            error_log("Telegram: No config found for type: " . $ticketType);
            return ['success' => false, 'message' => 'Konfigurasi notifikasi tidak ditemukan'];
        }

        // Generate pesan internal
        $message = generateInternalTicketMessage($pdo, $ticket, $ticketType);
        error_log("Message generated, length: " . strlen($message));

        $sentTo = [];
        $failedTo = [];

        foreach ($configs as $config) {
            error_log("Processing config: " . print_r($config, true));
            $chatIds = [];
            
            if (strpos($config['chat_ids'], 'auto_') === 0) {
                $role = str_replace('auto_', '', $config['chat_ids']);
                error_log("Role: " . $role);
                
                $stmt = $pdo->prepare("
                    SELECT telegram_chat_id FROM users
                    WHERE role = ? AND is_active = 1
                    AND telegram_chat_id IS NOT NULL AND telegram_chat_id != ''
                ");
                $stmt->execute([$role]);
                $users = $stmt->fetchAll();
                error_log("Users with chat_id for role {$role}: " . count($users));

                foreach ($users as $user) {
                    if (!empty($user['telegram_chat_id'])) {
                        $chatIds[] = $user['telegram_chat_id'];
                        error_log("Chat ID found: " . $user['telegram_chat_id']);
                    }
                }
            } else {
                $chatIds = array_map('trim', explode(',', $config['chat_ids']));
                error_log("Manual chat IDs: " . implode(',', $chatIds));
            }

            if (empty($chatIds)) {
                error_log("No chat IDs to send");
                continue;
            }

            foreach ($chatIds as $chatId) {
                if (empty($chatId)) continue;
                
                error_log("Sending to chat_id: " . $chatId);
                $result = sendTelegramNotification($pdo, $chatId, $message, 'ticket_' . $ticketType);

                if ($result['success']) {
                    $sentTo[] = $chatId;
                    error_log("Success to " . $chatId);
                } else {
                    $failedTo[] = $chatId;
                    error_log("Failed to " . $chatId . ": " . ($result['message'] ?? 'Unknown error'));
                }

                sleep(1);
            }
        }

        error_log("Sent to " . count($sentTo) . " users, failed " . count($failedTo));
        
        return [
            'success' => !empty($sentTo),
            'sent_to' => $sentTo,
            'failed_to' => $failedTo,
            'total_sent' => count($sentTo),
            'total_failed' => count($failedTo),
            'message' => "Terkirim ke " . count($sentTo) . " penerima"
        ];

    } catch(Exception $e) {
        error_log("Telegram Error: " . $e->getMessage());
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

function generateInternalTicketMessage($pdo, $ticket, $ticketType) {
    $customerName = 'Unknown';
    $customerPhone = '-';

    try {
        if (isset($ticket['pelanggan_id']) && $ticket['pelanggan_id']) {
            $stmt = $pdo->prepare("SELECT nama_lengkap, no_telepon FROM pelanggan WHERE id = ?");
            $stmt->execute([$ticket['pelanggan_id']]);
            $customer = $stmt->fetch();
            if ($customer) {
                $customerName = $customer['nama_lengkap'] ?? 'Unknown';
                $customerPhone = $customer['no_telepon'] ?? '-';
            }
        }
    } catch(Exception $e) {
        error_log("Error getting customer data: " . $e->getMessage());
    }

    if ($ticketType == 'payment') {
        $emoji = "💰";
        $title = "TIKET PEMBAYARAN DARI PELANGGAN";
    } else {
        $emoji = "🔧";
        $title = "TIKET GANGGUAN/TEKNIS DARI PELANGGAN";
    }

    $message = "$emoji *NOTIFIKASI $title*\n\n";
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

    if ($ticketType == 'payment') {
        $message .= "Harap segera diproses oleh kasir.\n";
    } else {
        $message .= "Harap segera ditangani oleh teknisi.\n";
    }
    $message .= "Terima kasih 🙏";

    return $message;
}
?>
