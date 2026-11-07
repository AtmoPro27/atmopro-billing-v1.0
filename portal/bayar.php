<?php
require_once __DIR__ . "/../includes/auth_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    header("Location: index.php");
    exit();
}

$id_invoice = $_GET['id'] ?? '';
if (empty($id_invoice)) {
    header("Location: dashboard.php");
    exit();
}

// Redirect ke halaman buat tiket dengan data invoice
header("Location: tiket.php?invoice_id=" . $id_invoice);
exit();
?>
