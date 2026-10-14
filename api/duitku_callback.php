<?php
/**
 * Callback handler dari Duitku
 * 
 * Duitku akan POST data ke URL ini setelah pembayaran selesai.
 */

require_once __DIR__ . '/../config/database.php';
$pdo = Database::getInstance()->getConnection();

// Log semua callback
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

// Terima data callback
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    // Coba dari POST biasa
    $data = $_POST;
}

@file_put_contents($log_dir . '/duitku_callback.log',
    date('[Y-m-d H:i:s]') . ' CALLBACK: ' . $raw . PHP_EOL . PHP_EOL,
    FILE_APPEND);

if (!$data) {
    http_response_code(400);
    exit('Invalid data');
}

// Ambil field penting
$merchant_code = $data['merchantCode'] ?? '';
$amount = $data['amount'] ?? 0;
$merchant_order_id = $data['merchantOrderId'] ?? '';
$signature = $data['signature'] ?? '';
$result_code = $data['resultCode'] ?? '';
$reference = $data['reference'] ?? '';
$payment_code = $data['paymentCode'] ?? '';

if (empty($merchant_order_id)) {
    http_response_code(400);
    exit('Missing merchantOrderId');
}

// Ambil credential dari DB
$stmt_pg = $pdo->prepare("SELECT api_key, merchant_id FROM payment_gateway WHERE name = 'Duitku' LIMIT 1");
$stmt_pg->execute();
$pg = $stmt_pg->fetch();

if (!$pg) {
    http_response_code(500);
    exit('Payment gateway not found');
}

// Verifikasi signature: md5(merchantCode + amount + merchantOrderId + apiKey)
$calc_signature = md5($merchant_code . $amount . $merchant_order_id . $pg['api_key']);

if ($signature !== $calc_signature) {
    @file_put_contents($log_dir . '/duitku_callback.log',
        date('[Y-m-d H:i:s]') . ' SIGNATURE MISMATCH: expected=' . $calc_signature . ' got=' . $signature . PHP_EOL . PHP_EOL,
        FILE_APPEND);
    http_response_code(403);
    exit('Invalid signature');
}

// Cari invoice
$stmt = $pdo->prepare("SELECT * FROM invoice WHERE no_invoice = ?");
$stmt->execute([$merchant_order_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    exit('Invoice not found');
}

// resultCode: 00 = sukses, 01 = pending, 02 = gagal
if ($result_code === '00') {
    // Update invoice jadi paid
    $stmt_upd = $pdo->prepare("UPDATE invoice SET status = 'paid', tgl_bayar = NOW() WHERE id = ?");
    $stmt_upd->execute([$invoice['id']]);

    // Update jatuh tempo pelanggan +1 bulan
    $stmt_pel = $pdo->prepare("UPDATE pelanggan SET jatuh_tempo = DATE_ADD(jatuh_tempo, INTERVAL 1 MONTH) WHERE id = ?");
    $stmt_pel->execute([$invoice['pelanggan_id']]);

    @file_put_contents($log_dir . '/duitku_callback.log',
        date('[Y-m-d H:i:s]') . ' SUCCESS: invoice=' . $merchant_order_id . ' ref=' . $reference . PHP_EOL . PHP_EOL,
        FILE_APPEND);

    http_response_code(200);
    echo 'OK';
} else {
    @file_put_contents($log_dir . '/duitku_callback.log',
        date('[Y-m-d H:i:s]') . ' NOT PAID: invoice=' . $merchant_order_id . ' resultCode=' . $result_code . PHP_EOL . PHP_EOL,
        FILE_APPEND);

    http_response_code(200);
    echo 'OK - not paid';
}
