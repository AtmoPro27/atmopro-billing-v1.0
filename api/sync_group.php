<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    die("Access denied");
}

$id = $_GET['id'];
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT * FROM group_profil WHERE id = ?");
$stmt->execute([$id]);
$group = $stmt->fetch();

if (!$group) {
    die("Group tidak ditemukan");
}

// Di sini nanti akan ditambahkan kode untuk koneksi ke Mikrotik
// dan mengirim konfigurasi IP Pool

echo "<h2>Sync Group Profil ke Mikrotik</h2>";
echo "<p>Group: " . $group['nama_group'] . "</p>";
echo "<p>IP Pool: " . $group['ip_awal'] . " - " . $group['ip_akhir'] . "</p>";
echo "<p>IP Lokal: " . $group['ip_lokal'] . "</p>";
echo "<hr>";
echo "<p>Command yang akan dikirim ke Mikrotik:</p>";
echo "<pre>";
echo "/ip pool add name=\"" . $group['nama_group'] . "_pool\" ranges=" . $group['ip_awal'] . "-" . $group['ip_akhir'] . " comment=\"BILLING\"\n";
echo "/ip address add address=" . $group['ip_lokal'] . " interface=bridge comment=\"BILLING\"";
echo "</pre>";
echo "<a href='../modules/paket/group_profil/index.php'>Kembali</a>";
?>
