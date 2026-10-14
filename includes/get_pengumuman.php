<?php
// get_pengumuman.php - Ambil pengumuman dari database
try {
    require_once __DIR__ . "/../config/database.php";
$pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->prepare("SELECT isi FROM pengumuman WHERE status = 'aktif' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $pengumuman = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika tidak ada pengumuman aktif, set isi menjadi NULL (kosong)
    if (!$pengumuman || empty($pengumuman['isi'])) {
        $pengumuman = ['isi' => null];
    }
} catch(PDOException $e) {
    $pengumuman = ['isi' => null];
}
?>
