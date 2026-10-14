<?php
// db_connect.php - Koneksi database untuk portal
require_once __DIR__ . '/../config/database.php';

function getDB() {
    return Database::getInstance()->getConnection();
}

// Untuk kompatibilitas dengan kode lama
$pdo = Database::getInstance()->getConnection();
?>
