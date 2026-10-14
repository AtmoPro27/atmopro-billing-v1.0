<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $ticket_id = $_POST['ticket_id'];
    $no_invoice = $_POST['no_invoice'];

    error_log("=== CONFIRM PAYMENT ===");
    error_log("Ticket ID: " . $ticket_id);
    error_log("No Invoice: " . $no_invoice);

    try {
        require_once __DIR__ . "/../../config/database.php";
        $pdo = Database::getInstance()->getConnection();

        // Cari invoice berdasarkan no_invoice
        $stmt = $pdo->prepare("SELECT id, pelanggan_id, status FROM invoice WHERE no_invoice = ?");
        $stmt->execute([$no_invoice]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            error_log("Invoice not found for: " . $no_invoice);
            header("Location: view.php?id=" . $ticket_id . "&error=Invoice tidak ditemukan: " . urlencode($no_invoice));
            exit();
        }

        error_log("Invoice found: ID=" . $invoice['id'] . ", Status=" . $invoice['status']);

        if ($invoice['status'] == 'paid') {
            header("Location: view.php?id=" . $ticket_id . "&error=Invoice sudah lunas");
            exit();
        }

        // Update invoice menjadi paid
        $stmt = $pdo->prepare("UPDATE invoice SET status = 'paid', tgl_bayar = NOW() WHERE id = ?");
        $stmt->execute([$invoice['id']]);
        error_log("Invoice updated to paid");

        // Update jatuh tempo pelanggan +1 bulan
        $stmt = $pdo->prepare("UPDATE pelanggan SET jatuh_tempo = DATE_ADD(DATE(jatuh_tempo), INTERVAL 1 MONTH) + INTERVAL 23 HOUR + INTERVAL 59 MINUTE + INTERVAL 59 SECOND WHERE id = ?");
        $stmt->execute([$invoice['pelanggan_id']]);
        error_log("Jatuh tempo updated");

        // ============================================
        // TAMBAHAN: Update customers & enable router
        // ============================================

        // Ambil customer_id dari pelanggan
        $stmt = $pdo->prepare("SELECT id_pelanggan FROM pelanggan WHERE id = ?");
        $stmt->execute([$invoice['pelanggan_id']]);
        $customer = $stmt->fetch();
        $customer_id = $customer['id_pelanggan'] ?? null;

        if ($customer_id) {
            // Update status di customers
            $stmt = $pdo->prepare("UPDATE pelanggan SET status = 'active' WHERE id_pelanggan = ?");
            $stmt->execute([$customer_id]);
            error_log("Customer status updated: $customer_id -> active");

            // Aktifkan di router
            shell_exec('/usr/bin/php ' . BASE_PATH . '/cron/enable_active_router.php > /dev/null 2>&1 &');
            error_log("Router enable triggered for: $customer_id");
        }

        // Catat log aktivitas
        include '../../includes/activity_log.php';
        log_admin_activity($pdo, 'INVOICE_PAID', 'invoice', $no_invoice, 'Konfirmasi pembayaran via tiket oleh ' . $_SESSION['username']);

        // Update tiket menjadi closed
        $stmt = $pdo->prepare("UPDATE tickets SET status = 'closed' WHERE id = ?");
        $stmt->execute([$ticket_id]);
        error_log("Ticket closed");

        // Tambahkan balasan otomatis
        $reply_message = "✅ Pembayaran untuk invoice $no_invoice telah dikonfirmasi. Jatuh tempo telah diperpanjang ke bulan berikutnya. Terima kasih.";
        $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$ticket_id, $_SESSION['user_id'], $reply_message]);

        // Redirect ke print_invoice
        header("Location: /modules/invoices/print_invoice.php?id=" . $invoice['id'] . "&success=paid");
        exit();

    } catch(PDOException $e) {
        error_log("Error: " . $e->getMessage());
        header("Location: view.php?id=" . $ticket_id . "&error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>
