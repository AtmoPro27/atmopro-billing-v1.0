<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$phone = $_POST["phone"] ?? "";
$invoice_no = $_POST["invoice_no"] ?? "";

if (empty($phone)) {
    echo json_encode(["success" => false, "message" => "Nomor telepon tidak ada"]);
    exit;
}

try {
    require_once __DIR__ . "/../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    
    // Ambil template dari database
    $stmt = $pdo->query("SELECT template_message FROM wa_templates WHERE template_name = \"invoice_paid\"");
    $template = $stmt->fetchColumn();
    
    $message = "";
    
    if ($invoice_no && $template) {
        // Ambil data invoice
        $stmt = $pdo->prepare("
            SELECT i.*, p.nama_lengkap, p.id_pelanggan as customer_id 
            FROM invoice i 
            JOIN pelanggan p ON i.pelanggan_id = p.id 
            WHERE i.no_invoice = ?
        ");
        $stmt->execute([$invoice_no]);
        $invoice = $stmt->fetch();
        
        if ($invoice) {
            $stmt = $pdo->prepare("SELECT value FROM pengaturan WHERE key_setting = ?");
            
            $stmt->execute(["company_name"]);
            $company_name = $stmt->fetchColumn();
            
            $stmt->execute(["company_tagline"]);
            $tagline = $stmt->fetchColumn();
            
            $stmt->execute(["wa_number"]);
            $wa_number = $stmt->fetchColumn();
            
            $data = [
                "company_name" => $company_name ?: "AtmoBilling",
                "customer_name" => $invoice["nama_lengkap"],
                "customer_id" => $invoice["customer_id"],
                "invoice_no" => $invoice["no_invoice"],
                "invoice_date" => date("d-m-Y", strtotime($invoice["tanggal"])),
                "due_date" => date("d-m-Y", strtotime($invoice["jatuh_tempo"])),
                "amount" => "Rp " . number_format($invoice["jumlah"], 0, ",", "."),
                "payment_date" => date("d-m-Y", strtotime($invoice["tgl_bayar"])),
                "portal_url" => BASE_URL . "/portal",
                "wa_number" => $wa_number ?: "628000000000",
                "tagline" => $tagline ?: "HIGH SPEED INTERNET CONNECTION"
            ];
            
            $message = $template;
            foreach ($data as $key => $value) {
                $message = str_replace("{" . $key . "}", $value, $message);
            }
        }
    }
    
    if (empty($message)) {
        $message = $_POST["message"] ?? "Terima kasih telah melakukan pembayaran";
    }
    
    // Format nomor
    $phone = preg_replace("/[^0-9]/", "", $phone);
    if (substr($phone, 0, 1) == "0") {
        $phone = "62" . substr($phone, 1);
    }
    if (substr($phone, 0, 2) != "62") {
        $phone = "62" . $phone;
    }
    
    // Ambil pengaturan WA Gateway
    $stmt = $pdo->query("SELECT * FROM wa_settings LIMIT 1");
    $wa = $stmt->fetch();
    
    if (!$wa || $wa["is_active"] != 1) {
        echo json_encode(["success" => false, "message" => "WA Gateway tidak aktif"]);
        exit;
    }
    
    // Kirim ke API Gateway
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $wa["api_url"]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        "target" => $phone,
        "message" => $message
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $wa["api_key"]]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        echo json_encode(["success" => true, "message" => "WA terkirim"]);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal: HTTP " . $http_code]);
    }
    
} catch(PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
