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
        $stmt = $pdo->prepare("UPDATE invoice SET status = 'paid', tgl_bayar = NOW(), paid_by = ?, metode_bayar = 'TRANSFER' WHERE id = ?");
        $stmt->execute([$_SESSION['username'] ?? 'system', $invoice['id']]);
        error_log("Invoice updated to paid");

        // Update jatuh tempo pelanggan (logika sama dengan pay.php)
        // - Invoice terbit bulan ini (invoice baru) => jatuh tempo bulan depan tgl 20
        // - Invoice terbit bulan lalu (nunggakan):
        //   * Bayar tgl 1-14 => jatuh tempo bulan ini tgl 20
        //   * Bayar tgl 15+  => jatuh tempo bulan depan tgl 20
        $stmt_terbit = $pdo->prepare("SELECT tgl_terbit FROM invoice WHERE id = ?");
        $stmt_terbit->execute([$invoice['id']]);
        $tgl_terbit = $stmt_terbit->fetchColumn();

        $bulan_terbit = date('Y-m', strtotime($tgl_terbit));
        $bulan_ini    = date('Y-m');
        $hari_ini     = (int)date('j');

        if ($bulan_terbit === $bulan_ini) {
            $jatuh_tempo_baru = date('Y-m-20 23:59:59', strtotime('+1 month'));
        } else {
            if ($hari_ini <= 14) {
                $jatuh_tempo_baru = date('Y-m-20 23:59:59');
            } else {
                $jatuh_tempo_baru = date('Y-m-20 23:59:59', strtotime('+1 month'));
            }
        }

        // Simpan jatuh tempo lama untuk restore saat batal bayar
        $stmt_old = $pdo->prepare("SELECT jatuh_tempo FROM pelanggan WHERE id = ?");
        $stmt_old->execute([$invoice['pelanggan_id']]);
        $old_due = $stmt_old->fetchColumn();
        if ($old_due) {
            $stmt_save = $pdo->prepare("UPDATE invoice SET jatuh_tempo_sebelum = ? WHERE id = ?");
            $stmt_save->execute([$old_due, $invoice['id']]);
        }

        $stmt = $pdo->prepare("UPDATE pelanggan SET jatuh_tempo = ? WHERE id = ?");
        $stmt->execute([$jatuh_tempo_baru, $invoice['pelanggan_id']]);
        error_log("Jatuh tempo updated: $jatuh_tempo_baru");

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

            // Aktifkan PPPoE langsung via API Mikrotik
            $stmt = $pdo->prepare("
                SELECT pk.router_id 
                FROM pelanggan p 
                LEFT JOIN paket_internet pk ON p.paket_id = pk.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$invoice['pelanggan_id']]);
            $paket = $stmt->fetch();
            $router_id = $paket['router_id'] ?? null;

            if ($router_id) {
                require_once __DIR__ . '/../../includes/mikrotik_api.php';
                $enable_result = enable_pppoe_user($router_id, $customer_id);
                error_log("Enable PPPoE: " . json_encode($enable_result));
            }
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
