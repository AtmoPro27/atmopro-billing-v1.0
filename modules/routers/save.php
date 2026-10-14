<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once '../../config/database.php';

$db = Database::getInstance()->getConnection();

$nama = $_POST['nama_router'];
$ip = $_POST['ip_address'];
$port = $_POST['port_api'];
$user = $_POST['username'];
$pass = $_POST['password'];
$status = $_POST['status'];

$stmt = $db->prepare("INSERT INTO routers (nama_router, ip_address, port_api, username, password, status) VALUES (?,?,?,?,?,?)");
$stmt->execute([$nama, $ip, $port, $user, $pass, $status]);

header("Location: index.php");
exit();
?>
