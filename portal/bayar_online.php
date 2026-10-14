<?php
require_once __DIR__ . "/../includes/session_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

require_once '../config/database.php';
$pdo = Database::getInstance()->getConnection();

$id_invoice = (int)($_GET['id'] ?? 0);
$metode = $_GET['metode'] ?? '';
if (!$id_invoice) {
    header("Location: tagihan.php?error=invalid_invoice");
    exit();
}

// Ambil invoice (pastikan milik pelanggan yang login)
$stmt = $pdo->prepare("SELECT * FROM invoice WHERE id = ? AND pelanggan_id = ?");
$stmt->execute([$id_invoice, $_SESSION['pelanggan_id']]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header("Location: tagihan.php?error=invoice_not_found");
    exit();
}

if ($invoice['status'] === 'paid') {
    header("Location: tagihan.php?error=already_paid");
    exit();
}

// Ambil PG aktif
$stmt_pg = $pdo->query("SELECT * FROM payment_gateway WHERE is_active = 1 LIMIT 1");
$pg = $stmt_pg->fetch();

// Simpan metode ke session
$_SESSION['payment_method'] = $metode;

if (!$pg) {
    header("Location: tagihan.php?error=pg_inactive");
    exit();
}

// Route ke handler sesuai nama PG
$pg_name = strtolower($pg['name']);
$handler = __DIR__ . '/pg/' . $pg_name . '.php';

if (!file_exists($handler)) {
    die("Payment gateway belum didukung: " . htmlspecialchars($pg['name']));
}

// Include handler
include $handler;
