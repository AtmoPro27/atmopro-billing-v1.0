<?php
/**
 * Handler Duitku Payment Gateway
 * 
 * Dokumentasi: https://docs.duitku.com/api/
 * 
 * Variable yang tersedia dari bayar_online.php:
 * - $pdo, $invoice, $pg
 * - $_SESSION['pelanggan_kode'], $_SESSION['pelanggan_nama'], $_SESSION['pelanggan_telp']
 */

// Ambil credential Duitku
$merchant_code = $pg['merchant_id'];
$api_key = $pg['api_key'];
$environment = $pg['environment'] ?? 'sandbox';

if (empty($merchant_code) || empty($api_key)) {
    header("Location: ../tagihan.php?error=pg_not_configured");
    exit();
}

// Tentukan URL API Duitku
$api_url = ($environment === 'production')
    ? 'https://passport.duitku.com/webapi/api/merchant/v2/inquiry'
    : 'https://sandbox.duitku.com/webapi/api/merchant/v2/inquiry';

// Data transaksi
$payment_amount = (int)$invoice['jumlah'];
$payment_method = $_SESSION['payment_method'] ?? 'VC';
$merchant_order_id = $invoice['no_invoice'];
$product_details = 'Tagihan Internet - ' . $invoice['no_invoice'];
$email = $_SESSION['pelanggan_kode'] . '@demo.local'; // email dummy
$phoneNumber = $_SESSION['pelanggan_telp'] ?? '628000000000';
$customerVaName = $_SESSION['pelanggan_nama'] ?? 'Pelanggan';
$callbackUrl = BASE_URL . '/api/duitku_callback.php';
$returnUrl = BASE_URL . '/portal/tagihan.php';
$expiryPeriod = 1440; // 24 jam

// Signature Duitku: md5(merchantCode + merchantOrderId + paymentAmount + apiKey)
$signature = md5($merchant_code . $merchant_order_id . $payment_amount . $api_key);

// Susun parameter
$params = [
    'merchantCode' => $merchant_code,
    'paymentAmount' => $payment_amount,
    'paymentMethod' => $payment_method,
    'merchantOrderId' => $merchant_order_id,
    'productDetails' => $product_details,
    'email' => $email,
    'phoneNumber' => $phoneNumber,
    'customerVaName' => $customerVaName,
    'callbackUrl' => $callbackUrl,
    'returnUrl' => $returnUrl,
    'signature' => $signature,
    'expiryPeriod' => $expiryPeriod,
];

// Request ke API Duitku
$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($params),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Log untuk debugging
$log_dir = __DIR__ . '/../../logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
@file_put_contents($log_dir . '/duitku_request.log',
    date('[Y-m-d H:i:s]') . ' REQUEST: ' . json_encode($params) . PHP_EOL .
    date('[Y-m-d H:i:s]') . ' HTTP: ' . $http_code . PHP_EOL .
    date('[Y-m-d H:i:s]') . ' ERROR: ' . $curl_error . PHP_EOL .
    date('[Y-m-d H:i:s]') . ' RESPONSE: ' . $response . PHP_EOL . PHP_EOL,
    FILE_APPEND);

if ($curl_error) {
    header("Location: ../tagihan.php?error=" . urlencode('Koneksi ke Duitku gagal: ' . $curl_error));
    exit();
}

$result = json_decode($response, true);

if ($http_code == 200 && !empty($result['paymentUrl'])) {
    // Redirect ke halaman pembayaran Duitku
    header("Location: " . $result['paymentUrl']);
    exit();
} else {
    $error_msg = $result['statusMessage'] ?? $result['Message'] ?? 'Gagal membuat transaksi (HTTP ' . $http_code . ')';
    header("Location: ../tagihan.php?error=" . urlencode($error_msg));
    exit();
}
