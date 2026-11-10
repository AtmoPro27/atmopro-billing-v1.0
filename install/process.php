<?php
/**
 * AtmoPro Installer — Step 2: Proses Install
 */

// Blokir jika sudah terinstall
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file) && filesize($env_file) > 50) {
    die('Sudah terinstall. Hapus folder /install/.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$db_host  = trim($_POST['db_host'] ?? 'localhost');
$db_name  = trim($_POST['db_name'] ?? '');
$db_user  = trim($_POST['db_user'] ?? '');
$db_pass  = $_POST['db_pass'] ?? '';
$base_url = trim($_POST['base_url'] ?? '');
$timezone = trim($_POST['timezone'] ?? 'Asia/Jakarta');

if (!$db_name || !$db_user) {
    header('Location: index.php?error=' . urlencode('Nama database & user wajib diisi'));
    exit;
}

// ============================================================
// 1. Tes koneksi ke MySQL
// ============================================================
try {
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    header('Location: index.php?error=' . urlencode('Koneksi DB gagal: ' . $e->getMessage()));
    exit;
}

// ============================================================
// 2. Buat database jika belum ada
// ============================================================
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db_name`");
} catch (PDOException $e) {
    header('Location: index.php?error=' . urlencode('Gagal buat/pilih DB: ' . $e->getMessage()));
    exit;
}

// ============================================================
// 3. Cek apakah DB sudah ada tabel (cegah install dobel)
// ============================================================
$has_table = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
if ($has_table) {
    header('Location: index.php?error=' . urlencode('Database sudah berisi data. Gunakan database kosong atau hapus tabel dulu.'));
    exit;
}

// ============================================================
// 4. Import file SQL
// ============================================================
$sql_file = __DIR__ . '/database.sql';
if (!file_exists($sql_file)) {
    header('Location: index.php?error=' . urlencode('File install/database.sql tidak ditemukan'));
    exit;
}

$sql = file_get_contents($sql_file);
// Hapus komentar & baris kosong
$sql = preg_replace('/^--.*$/m', '', $sql);
$sql = preg_replace('/^\/\*.*?\*\/$/ms', '', $sql);

try {
    $pdo->exec($sql);
} catch (PDOException $e) {
    header('Location: index.php?error=' . urlencode('Import SQL gagal: ' . $e->getMessage()));
    exit;
}

// ============================================================
// 5. Tulis file .env
// ============================================================
$env_content = "# ============================================================\n";
$env_content .= "# ENVIRONMENT CONFIGURATION — BILLING SYSTEM\n";
$env_content .= "# ============================================================\n\n";
$env_content .= "DB_HOST=$db_host\n";
$env_content .= "DB_NAME=$db_name\n";
$env_content .= "DB_USER=$db_user\n";
$env_content .= "DB_PASS=$db_pass\n\n";
$env_content .= "BASE_URL=$base_url\n\n";
$env_content .= "TIMEZONE=$timezone\n";

if (file_put_contents($env_file, $env_content) === false) {
    header('Location: index.php?error=' . urlencode('Gagal tulis file .env. Cek permission folder root.'));
    exit;
}
@chmod($env_file, 0640);

// ============================================================
// 6. Selesai — redirect ke finish
// ============================================================
header('Location: finish.php');
exit;
