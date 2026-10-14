<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/genieacs_functions.php';

date_default_timezone_set('Asia/Jakarta');

// Ambil setting GenieACS dari DB
$baseUrl = getGenieACSSetting('genieacs_url') ?: 'http://localhost:7557';
if (!preg_match('#^https?://#i', $baseUrl)) $baseUrl = 'http://' . $baseUrl;
$baseUrl = rtrim($baseUrl, '/');

$auth_user = getGenieACSSetting('genieacs_username');
$auth_pass = getGenieACSSetting('genieacs_password');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/tasks/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

if ($auth_user && $auth_pass) {
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$auth_user:$auth_pass");
}

$response = curl_exec($ch);
curl_close($ch);

$tasks = json_decode($response, true);
if (!is_array($tasks)) {
    echo date('[Y-m-d H:i:s]') . " Gagal ambil tasks\n";
    exit;
}

$now = time();
$deleted = 0;

foreach ($tasks as $task) {
    $taskTime = strtotime($task['timestamp'] ?? '');
    if (!$taskTime) continue;

    if (($now - $taskTime) > 3600) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/tasks/' . $task['_id']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($auth_user && $auth_pass) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, "$auth_user:$auth_pass");
        }

        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code == 200 || $code == 204) $deleted++;
    }
}

echo date('[Y-m-d H:i:s]') . " Cleanup: {$deleted} task dihapus\n";
