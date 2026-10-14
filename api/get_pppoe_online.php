<?php
require_once __DIR__ . '/../includes/auth_check_api.php';
header('Content-Type: application/json');

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'total' => 0,
    'users' => [],
    'data' => [],
    'routers' => []
];

// Total
if (file_exists('/tmp/pppoe_online_count_demo.txt')) {
    $response['total'] = (int)file_get_contents('/tmp/pppoe_online_count_demo.txt');
}

// Users
if (file_exists('/tmp/pppoe_online_users_demo.txt')) {
    $response['users'] = array_filter(explode("\n", file_get_contents('/tmp/pppoe_online_users_demo.txt')));
}

// Data detail
if (file_exists('/tmp/pppoe_user_data_demo.json')) {
    $response['data'] = json_decode(file_get_contents('/tmp/pppoe_user_data_demo.json'), true);
}

// Router stats
if (file_exists('/tmp/pppoe_router_stats_demo.json')) {
    $response['routers'] = json_decode(file_get_contents('/tmp/pppoe_router_stats_demo.json'), true);
}

echo json_encode($response);
?>
