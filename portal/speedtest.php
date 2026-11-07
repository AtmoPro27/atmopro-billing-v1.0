<?php
require_once __DIR__ . "/../includes/auth_check_portal.php";
if (!isset($_SESSION['portal_login'])) {
    http_response_code(403);
    exit('Forbidden');
}
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', 'Off');
@ini_set('implicit_flush', 1);
while (ob_get_level()) ob_end_clean();

if (isset($_GET['ping'])) {
    header('Content-Type: text/plain');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo 'pong';
    exit;
}
if (isset($_GET['download'])) {
    $size = 10 * 1024 * 1024;
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $size);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $chunk = str_repeat('0', 8192);
    $sent = 0;
    while ($sent < $size) {
        echo $chunk;
        $sent += strlen($chunk);
        flush();
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $start = microtime(true);
    $received = 0;
    $handle = fopen('php://input', 'rb');
    if ($handle) {
        while (!feof($handle)) {
            $received += strlen(fread($handle, 8192));
        }
        fclose($handle);
    }
    $duration = microtime(true) - $start;
    echo json_encode(['success' => true, 'received' => $received, 'duration' => $duration]);
    exit;
}
http_response_code(400);
echo 'Bad Request';
