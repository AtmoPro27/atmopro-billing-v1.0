<?php
require_once __DIR__ . '/../../includes/auth_check.php';

require_once '../../includes/settings_helper.php';

header('Content-Type: application/json');

// Baca field dengan nama yang sama seperti form (genieacs_*)
$url = $_POST['genieacs_url'] ?? $_GET['genieacs_url'] ?? null;
$username = $_POST['genieacs_username'] ?? $_GET['genieacs_username'] ?? null;
$password = $_POST['genieacs_password'] ?? $_GET['genieacs_password'] ?? null;
$timeout = (int)($_POST['genieacs_timeout'] ?? $_GET['genieacs_timeout'] ?? 10);

// Kalau form kirim kosong, jangan fallback ke DB — paksa kosong
if ($url === '') $url = '';
if ($username === '') $username = '';
if ($password === '') $password = '';

$result = testGenieACSConnection($url, $username, $password, $timeout);
echo json_encode($result);
