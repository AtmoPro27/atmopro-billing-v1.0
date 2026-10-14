<?php
// router_helper.php - Helper untuk koneksi router
require_once __DIR__ . '/../config/database.php';

function getRouterConnection() {
    return Database::getInstance()->getConnection();
}

function getRouterById($id) {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("SELECT * FROM routers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAllRouters() {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->query("SELECT * FROM routers WHERE status = 'active'");
    return $stmt->fetchAll();
}
?>
