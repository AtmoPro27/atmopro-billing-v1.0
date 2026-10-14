<?php
/**
 * Format nomor telepon untuk WhatsApp API
 * Nomor harus dimulai dengan kode negara (contoh: 6281234567890)
 */
function formatPhoneForWA($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 2) == '62') {
        return $phone;
    }
    if (substr($phone, 0, 1) == '0') {
        return '62' . substr($phone, 1);
    }
    return '62' . $phone;
}

function formatPhoneDisplay($phone) {
    if (empty($phone)) {
        return '-';
    }
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) >= 12) {
        return substr($phone, 0, 5) . '-' . substr($phone, 5, 4) . '-' . substr($phone, 9);
    }
    return $phone;
}

function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 2) != '62') {
        return false;
    }
    $length = strlen($phone);
    return ($length >= 10 && $length <= 15);
}

function sendWhatsAppNotification($phone, $message) {
    $formatted_phone = formatPhoneForWA($phone);
    $api_key = getSetting('whatsapp_api_key');
    
    if (empty($api_key)) {
        return ['success' => false, 'message' => 'WhatsApp API Key tidak dikonfigurasi'];
    }
    
    // Contoh menggunakan Fonnte API
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => array(
            'target' => $formatted_phone,
            'message' => $message,
        ),
        CURLOPT_HTTPHEADER => array(
            "Authorization: $api_key"
        ),
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($http_code == 200) {
        return ['success' => true, 'response' => json_decode($response, true)];
    } else {
        return ['success' => false, 'message' => 'Gagal mengirim notifikasi'];
    }
}

function notifyPaymentSuccess($pelanggan_id, $amount, $invoice_no) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT nama_lengkap, no_telepon FROM pelanggan WHERE id = ?");
    $stmt->execute([$pelanggan_id]);
    $pelanggan = $stmt->fetch();
    
    if (!$pelanggan || empty($pelanggan['no_telepon'])) {
        return false;
    }
    
    $message = "✅ PEMBAYARAN BERHASIL ✅\n\n";
    $message .= "Yth. {$pelanggan['nama_lengkap']}\n\n";
    $message .= "Pembayaran Anda telah kami terima.\n";
    $message .= "📄 Invoice: {$invoice_no}\n";
    $message .= "💰 Jumlah: Rp " . number_format($amount, 0, ',', '.') . "\n\n";
    $message .= "Terima kasih telah menggunakan layanan Demo Company.\n\n";
    $message .= "_Pesan ini dikirim otomatis oleh sistem._";
    
    return sendWhatsAppNotification($pelanggan['no_telepon'], $message);
}

function notifyBillDue($pelanggan_id, $amount, $due_date) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT nama_lengkap, no_telepon FROM pelanggan WHERE id = ?");
    $stmt->execute([$pelanggan_id]);
    $pelanggan = $stmt->fetch();
    
    if (!$pelanggan || empty($pelanggan['no_telepon'])) {
        return false;
    }
    
    $message = "⚠️ PERINGATAN TAGIHAN ⚠️\n\n";
    $message .= "Yth. {$pelanggan['nama_lengkap']}\n\n";
    $message .= "Tagihan internet Anda sebesar Rp " . number_format($amount, 0, ',', '.') . "\n";
    $message .= "akan jatuh tempo pada tanggal {$due_date}.\n\n";
    $message .= "Harap segera melakukan pembayaran.\n\n";
    $message .= "_Pesan ini dikirim otomatis oleh sistem._";
    
    return sendWhatsAppNotification($pelanggan['no_telepon'], $message);
}
?>
