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

/**
 * Update profile + password PPPoE user, lalu kick sesi aktif
 * Dipakai saat pelanggan ganti paket.
 */
function update_pppoe_profile($router_id, $username, $password, $profile) {
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
            return ['success' => false, 'message' => 'User tidak ditemukan di MikroTik'];
        }

        $user_id = $result[0]['.id'];

        // Update profile + password
        $api->write('/ppp/secret/set', false);
        $api->write('=.id=' . $user_id, false);
        $api->write('=profile=' . $profile, false);
        $api->write('=password=' . $password);
        $api->read();

        // Kick sesi aktif agar reconnect dengan profile baru
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
        return ['success' => true, 'message' => 'Profile PPPoE diperbarui, sesi di-kick'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Pindah user PPPoE antar router (ganti paket beda MikroTik):
 * - Hapus Secret di router lama + kick sesi aktif
 * - Buat Secret baru di router baru
 */
function move_pppoe_user($old_router_id, $new_router_id, $username, $password, $profile) {
    try {
        // 1. Hapus di router lama + kick sesi
        $conn_old = connectMikrotik($old_router_id);
        if ($conn_old['success']) {
            $api_old = $conn_old['api'];

            // Kick sesi aktif dulu
            $api_old->write('/ppp/active/print', false);
            $api_old->write('?name=' . $username);
            $active = $api_old->read();
            if (!empty($active)) {
                $active_id = $active[0]['.id'];
                $api_old->write('/ppp/active/remove', false);
                $api_old->write('=.id=' . $active_id);
                $api_old->read();
            }

            // Hapus Secret di router lama
            $api_old->write('/ppp/secret/print', false);
            $api_old->write('?name=' . $username);
            $old_result = $api_old->read();
            if (!empty($old_result)) {
                $old_id = $old_result[0]['.id'];
                $api_old->write('/ppp/secret/remove', false);
                $api_old->write('=.id=' . $old_id);
                $api_old->read();
            }

            $api_old->disconnect();
        }

        // 2. Buat Secret baru di router baru
        $conn_new = connectMikrotik($new_router_id);
        if (!$conn_new['success']) {
            return ['success' => false, 'message' => 'Gagal connect ke router baru: ' . $conn_new['message']];
        }
        $api_new = $conn_new['api'];

        // Cek apakah Secret sudah ada (kalau ada, update profile-nya)
        $api_new->write('/ppp/secret/print', false);
        $api_new->write('?name=' . $username);
        $existing = $api_new->read();

        if (!empty($existing)) {
            $new_id = $existing[0]['.id'];
            $api_new->write('/ppp/secret/set', false);
            $api_new->write('=.id=' . $new_id, false);
            $api_new->write('=profile=' . $profile, false);
            $api_new->write('=password=' . $password, false);
            $api_new->write('=service=pppoe');
            $api_new->read();
        } else {
            $api_new->write('/ppp/secret/add', false);
            $api_new->write('=name=' . $username, false);
            $api_new->write('=password=' . $password, false);
            $api_new->write('=profile=' . $profile, false);
            $api_new->write('=service=pppoe');
            $api_new->read();
        }

        $api_new->disconnect();
        return ['success' => true, 'message' => 'Secret dipindah ke router baru'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

