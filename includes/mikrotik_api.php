<?php
/**
 * Fungsi MikroTik via API (bukan SSH)
 * Menggantikan mikrotik_ssh.php
 * Library: routeros_api_new.php (v1.6)
 */

require_once __DIR__ . '/routeros_api_new.php';

/**
 * Ambil data router dari database
 */
function getRouterData($router_id) {
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("SELECT ip_address, port_api, username, password FROM routers WHERE id = ? AND status = 'active'");
    $stmt->execute([$router_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Koneksi ke MikroTik via API
 */
function connectMikrotik($router_id) {
    $router = getRouterData($router_id);
    if (!$router) {
        return ['success' => false, 'message' => 'Router tidak ditemukan', 'api' => null];
    }
    
    $api = new RouterosAPI();
    $api->debug = false;
    $api->timeout = 5;
    $api->port = (int)$router['port_api'];  // ← port custom dari DB
    
    $connected = $api->connect($router['ip_address'], $router['username'], $router['password']);
    
    if (!$connected) {
        return ['success' => false, 'message' => 'Gagal koneksi ke Mikrotik', 'api' => null];
    }
    
    return ['success' => true, 'message' => 'OK', 'api' => $api];
}

/**
 * Tambah user PPPoE
 */
function add_pppoe_user($router_id, $username, $password, $profile) {
    try {
        $conn = connectMikrotik($router_id);
        if (!$conn['success']) {
            return ['success' => false, 'message' => $conn['message']];
        }
        $api = $conn['api'];
        
        // Cek apakah user sudah ada
        $api->write('/ppp/secret/print', false);
        $api->write('?name=' . $username);
        $existing = $api->read();
        
        if (!empty($existing)) {
            $api->disconnect();
            return ['success' => false, 'message' => 'User PPPoE sudah ada'];
        }
        
        // Tambah user
        $api->write('/ppp/secret/add', false);
        $api->write('=name=' . $username, false);
        $api->write('=password=' . $password, false);
        $api->write('=profile=' . $profile, false);
        $api->write('=service=pppoe');
        $api->read();
        
        $api->disconnect();
        return ['success' => true, 'message' => 'User PPPoE berhasil dibuat'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Hapus user PPPoE
 */
function delete_pppoe_user($router_id, $username) {
    try {
        $conn = connectMikrotik($router_id);
        if (!$conn['success']) {
            return ['success' => false, 'message' => $conn['message']];
        }
        $api = $conn['api'];
        
        // Hapus user
        $api->write('/ppp/secret/remove', false);
        $api->write('=.id=' . $username);
        $api->read();
        
        $api->disconnect();
        return ['success' => true, 'message' => 'User PPPoE berhasil dihapus'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Disable user PPPoE
 */
function disable_pppoe_user($router_id, $username) {
    try {
        $conn = connectMikrotik($router_id);
        if (!$conn['success']) {
            return ['success' => false, 'message' => $conn['message']];
        }
        $api = $conn['api'];
        
        // Cari user
        $api->write('/ppp/secret/print', false);
        $api->write('?name=' . $username);
        $result = $api->read();
        
        if (empty($result)) {
            $api->disconnect();
            return ['success' => false, 'message' => 'User tidak ditemukan'];
        }
        
        $user_id = $result[0]['.id'];
        
        // Disable user
        $api->write('/ppp/secret/disable', false);
        $api->write('=.id=' . $user_id);
        $api->read();
        
        // Disconnect active connection
        $api->write('/ppp/active/print', false);
        $api->write('?name=' . $username);
        $active = $api->read();
        
        if (!empty($active)) {
            $active_id = $active[0]['.id'];
            $api->write('/ppp/active/remove', false);
            $api->write('=.id=' . $active_id);
            $api->read();
        }
        
        $api->disconnect();
        return ['success' => true, 'message' => 'User PPPoE berhasil dinonaktifkan'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Enable user PPPoE
 */
function enable_pppoe_user($router_id, $username) {
    try {
        $conn = connectMikrotik($router_id);
        if (!$conn['success']) {
            return ['success' => false, 'message' => $conn['message']];
        }
        $api = $conn['api'];
        
        // Cari user
        $api->write('/ppp/secret/print', false);
        $api->write('?name=' . $username);
        $result = $api->read();
        
        if (empty($result)) {
            $api->disconnect();
            return ['success' => false, 'message' => 'User tidak ditemukan'];
        }
        
        $user_id = $result[0]['.id'];
        
        // Enable user
        $api->write('/ppp/secret/enable', false);
        $api->write('=.id=' . $user_id);
        $api->read();
        
        $api->disconnect();
        return ['success' => true, 'message' => 'User PPPoE berhasil diaktifkan'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
