<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'super_admin') {
    die("Akses ditolak.");
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/mikrotik_api.php";

$pdo = Database::getInstance()->getConnection();

echo "<h3>Sinkronisasi Pelanggan ke MikroTik</h3>";
echo "<pre>";

$synced = 0;
$exists = 0;
$errors = 0;
$limit = 50;

$sql = "SELECT p.id, p.id_pelanggan, p.paket_id, p.nama_lengkap,
               pk.nama_paket, pk.profil_ppp, pk.router_id
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        WHERE p.status = 'active'
        AND (p.is_synced = 0 OR p.is_synced IS NULL)
        AND pk.router_id IS NOT NULL
        LIMIT $limit";
$stmt = $pdo->query($sql);
$customers = $stmt->fetchAll();

if (empty($customers)) {
    die("Tidak ada pelanggan baru yang perlu disinkronkan ke MikroTik.");
}

foreach ($customers as $cust) {
    $username_pppoe = $cust['id_pelanggan'];
    $password_pppoe = '123456';
    $profile = $cust['profil_ppp'] ?: 'default';
    $router_id = $cust['router_id'];

    echo "➤ Memproses: $username_pppoe - {$cust['nama_lengkap']} ... ";

    // Tambah user via API
    $result = add_pppoe_user($router_id, $username_pppoe, $password_pppoe, $profile);

    if ($result['success']) {
        $update = $pdo->prepare("UPDATE pelanggan SET is_synced = 1, updated_at = NOW() WHERE id = ?");
        $update->execute([$cust['id']]);
        $synced++;
        echo "✓ BERHASIL ditambahkan<br>";
    } elseif (strpos($result['message'], 'sudah ada') !== false) {
        $update = $pdo->prepare("UPDATE pelanggan SET is_synced = 1, updated_at = NOW() WHERE id = ?");
        $update->execute([$cust['id']]);
        $exists++;
        echo "⚠ SUDAH ADA (ditandai sinkron)<br>";
    } else {
        $errors++;
        echo "✗ GAGAL: " . $result['message'] . "<br>";
    }
    flush();
    ob_flush();
}

echo "<hr>";
echo "✅ Selesai!<br>";
echo "Berhasil ditambahkan: $synced<br>";
echo "Sudah ada sebelumnya: $exists<br>";
echo "Gagal: $errors<br>";
echo "</pre>";
echo "<a href='index.php' class='btn btn-primary'>Kembali ke Daftar Pelanggan</a>";
?>
