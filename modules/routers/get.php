<?php date_default_timezone_set("Asia/Jakarta"); ?>
<?php
require_once '../../config/database.php';

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM routers WHERE id = ?");
$stmt->execute([$_GET['id']]);
$router = $stmt->fetch();
unset($router['password']);
echo json_encode($router);
