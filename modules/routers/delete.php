<?php
require_once __DIR__ . '/../../includes/auth_check.php';

$id = $_GET['id'] ?? '';
if (empty($id)) {
    header("Location: index.php");
    exit();
}

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cek apakah router masih dipakai pelanggan aktif
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pelanggan p
        LEFT JOIN paket_internet pk ON pk.id = p.paket_id
        WHERE pk.router_id = ? AND p.status = 'active' AND p.status_aktif = 'aktif'
    ");
    $stmt->execute([$id]);
    $pelangganAktif = $stmt->fetchColumn();

    if ($pelangganAktif > 0) {
        header("Location: index.php?error=still_used&count=" . $pelangganAktif);
        exit();
    }

    $pdo->beginTransaction();

    // Ambil daftar tabel yang punya kolom router_id + status nullable
    $stmt = $pdo->query("
        SELECT TABLE_NAME, IS_NULLABLE 
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND COLUMN_NAME = 'router_id'
          AND TABLE_NAME NOT LIKE '%_bak_%'
          AND TABLE_NAME NOT LIKE '%_bak2_%'
    ");
    $tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tables[$row['TABLE_NAME']] = $row['IS_NULLABLE'];
    }

    // Handle tiap tabel
    foreach ($tables as $table => $nullable) {
        if ($nullable == 'YES') {
            // Nullable — set NULL
            $pdo->prepare("UPDATE `$table` SET router_id = NULL WHERE router_id = ?")->execute([$id]);
        } else {
            // NOT NULL — hapus baris yang refer ke router ini
            $pdo->prepare("DELETE FROM `$table` WHERE router_id = ?")->execute([$id]);
        }
    }

    // Hapus router
    $pdo->prepare("DELETE FROM routers WHERE id = ?")->execute([$id]);

    $pdo->commit();

    header("Location: index.php?delete=1");
    exit();
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete router error: " . $e->getMessage());
    header("Location: index.php?error=db_error&msg=" . urlencode($e->getMessage()));
    exit();
}
?>
