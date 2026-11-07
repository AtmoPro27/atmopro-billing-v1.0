<?php
/**
 * Ambil setting GenieACS dari database
 */
function getGenieACSSetting($key = null) {
    static $config = null;
    if ($config === null) {
        try {
            require_once __DIR__ . '/settings_helper.php';
            $config = [
                'genieacs_url' => getSetting('genieacs_url', 'http://localhost:7557'),
                'genieacs_username' => getSetting('genieacs_username', ''),
                'genieacs_password' => getSetting('genieacs_password', ''),  // otomatis didekripsi
                'genieacs_timeout' => getSetting('genieacs_timeout', 30),
            ];
        } catch (Exception $e) {
            $config = [];
        }
    }
    if ($key !== null) {
        return $config[$key] ?? null;
    }
    return $config;
}

/**
 * Ambil data devices dari GenieACS NBI
 */
function getGenieACSDevices($limit = 100) {
    // Ambil setting dari DB (bukan hardcode)
    $base_url = getGenieACSSetting('genieacs_url') ?: 'http://localhost:7557';
    // Auto-prefix http:// kalau belum ada protokol
    if (!preg_match('#^https?://#i', $base_url)) {
        $base_url = 'http://' . $base_url;
    }
    $timeout = (int)(getGenieACSSetting('genieacs_timeout') ?: 30);
    $projection = urlencode("_id,_lastInform,VirtualParameters,InternetGatewayDevice.DeviceInfo.SerialNumber,InternetGatewayDevice.DeviceInfo.ProductClass,InternetGatewayDevice.DeviceInfo.Manufacturer,InternetGatewayDevice.DeviceInfo.Temperature,InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPAddress,InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.PPPoE.Username,InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.Username,InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID,InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase,InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase,InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations,InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.RxRate,InternetGatewayDevice.LANDevice.1.Hosts");
    $url = rtrim($base_url, "/") . "/devices?limit={$limit}&projection={$projection}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // Basic Auth (kalau diisi)
    $auth_user = getGenieACSSetting('genieacs_username');
    $auth_pass = getGenieACSSetting('genieacs_password');
    if ($auth_user && $auth_pass) {
        curl_setopt($ch, CURLOPT_USERPWD, "$auth_user:$auth_pass");
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode != 200 || empty($response)) {
        return [];
    }
    
    $data = json_decode($response, true);
    
    // Cek format response
    if (is_array($data) && isset($data[0])) {
        $devices_data = $data;
    } elseif (isset($data['data']) && is_array($data['data'])) {
        $devices_data = $data['data'];
    } else {
        return [];
    }
    
    if (empty($devices_data)) {
        return [];
    }
    
    $devices = [];
    foreach ($devices_data as $item) {
        // =============================================================
        // EKSTRAK PARAMETER DARI BERBAGAI LOKASI
        // =============================================================
        $params = [];
        
        // Cari parameter di semua level
        array_walk_recursive($item, function($value, $key) use (&$params) {
            if (is_string($value) || is_numeric($value)) {
                $params[$key] = $value;
            }
        });
        
        // Cari parameter dengan format _value
        foreach ($item as $key => $value) {
            if (is_array($value) && isset($value['_value'])) {
                $params[$key] = $value['_value'];
            }
            if (is_array($value) && isset($value['_object']) && $value['_object'] === true) {
                foreach ($value as $subKey => $subValue) {
                    if (is_array($subValue) && isset($subValue['_value'])) {
                        $params[$key . '.' . $subKey] = $subValue['_value'];
                    }
                }
            }
        }
        
        // Cari parameter di VirtualParameters
        if (isset($item['VirtualParameters']) && is_array($item['VirtualParameters'])) {
            $vp = $item['VirtualParameters'];
            foreach ($vp as $key => $value) {
                if (is_array($value) && isset($value['_value'])) {
                    $params['VirtualParameters.' . $key] = $value['_value'];
                }
            }
        }
        
        // Cari parameter di InternetGatewayDevice
        if (isset($item['InternetGatewayDevice']) && is_array($item['InternetGatewayDevice'])) {
            $igw = $item['InternetGatewayDevice'];
            foreach ($igw as $key => $value) {
                if (is_array($value) && isset($value['_value'])) {
                    $params['InternetGatewayDevice.' . $key] = $value['_value'];
                }
                if (is_array($value) && isset($value['_object']) && $value['_object'] === true) {
                    foreach ($value as $subKey => $subValue) {
                        if (is_array($subValue) && isset($subValue['_value'])) {
                            $params['InternetGatewayDevice.' . $key . '.' . $subKey] = $subValue['_value'];
                        }
                    }
                }
            }
        }
        
        // Helper function untuk ambil parameter
        $getParam = function($paths) use ($params, $item) {
            foreach ((array)$paths as $path) {
                // Cek di params
                if (isset($params[$path]) && $params[$path] !== '') {
                    return $params[$path];
                }
                
                // Cek path pendek (tanpa prefix)
                $shortPath = preg_replace('/^(InternetGatewayDevice\.|LANDevice\.|WANDevice\.|DeviceInfo\.|VirtualParameters\.)/', '', $path);
                if (isset($params[$shortPath]) && $params[$shortPath] !== '') {
                    return $params[$shortPath];
                }
                
                // Cek bagian terakhir
                $parts = explode('.', $path);
                $lastPart = end($parts);
                if (isset($params[$lastPart]) && $params[$lastPart] !== '') {
                    return $params[$lastPart];
                }
                
                // Cek di item dengan nested object
                $current = $item;
                $found = true;
                foreach ($parts as $part) {
                    if (isset($current[$part]) && is_array($current[$part])) {
                        $current = $current[$part];
                    } else {
                        $found = false;
                        break;
                    }
                }
                if ($found && isset($current['_value']) && $current['_value'] !== '') {
                    return $current['_value'];
                }
                if ($found && is_string($current) && $current !== '') {
                    return $current;
                }
            }
            return null;
        };
        
        // =============================================================
        // AMBIL PARAMETER YANG DIBUTUHKAN
        // =============================================================
        $deviceId = $item['_deviceId'] ?? [];
        $serialNumber = $deviceId['_SerialNumber'] ?? '';
        $productClass = $deviceId['_ProductClass'] ?? '';
        $manufacturer = $deviceId['_Manufacturer'] ?? '';
        
        // SSID
        $ssid = $getParam([
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
            'VirtualParameters.ssid',
            'ssid',
            'SSID',
            'WLANConfiguration.1.SSID'
        ]) ?? '-';
        
        // WiFi Password
        $wifiPassword = $getParam([
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase',
            'wifiPassword',
            'password'
        ]) ?? '';
        
        // RX Power
        $rx = $getParam([
            'VirtualParameters.RXPower',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.RxRate',
            'rxPower',
            'rx'
        ]) ?? '-';
        
        // Temperature
        $temp = $getParam([
            'VirtualParameters.gettemp',
            'InternetGatewayDevice.DeviceInfo.Temperature',
            'temperature',
            'temp'
        ]) ?? '-';
        
        // IP PPPoE
        $ipPppoe = $getParam([
            'VirtualParameters.pppoeIP',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPAddress',
            'ip_pppoe',
            'ipAddress'
        ]) ?? '-';
        
        // =============================================================
        // AMBIL PPPOE USERNAME SEBAGAI ID
        // =============================================================
        $pppoeUser = $getParam([
            'VirtualParameters.pppoeUsername',
            'pppoeUsername',
            'username',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.PPPoE.Username',
            'InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.Username'
        ]) ?? '';
        
        // Jika tidak ada PPPoE username, KOSONGKAN
        if (empty($pppoeUser)) {
            $pppoeUser = '';
        }
        
        // Last comm
        $lastComm = $item['_lastInform'] ?? $item['_last_comm'] ?? '-';
        
        // Status (Online jika last comm dalam 5 menit)
        $status = 'Offline';
        if (!empty($lastComm) && $lastComm !== '-') {
            $last_comm = strtotime($lastComm);
            if (time() - $last_comm < 300) {
                $status = 'Online';
            }
        }
        
        // Active clients
        $activeClients = intval($getParam([
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations',
            'VirtualParameters.activedevices',
            'activeClients'
        ]) ?? 0);
        
        // =============================================================
        // BUILD DEVICE
        // =============================================================
        $device = [
            'id' => $pppoeUser,
            'device_id' => $item['_id'] ?? '',
            'status' => $status,
            'last_comm' => $lastComm,
            'ssid' => $ssid,
            'wifi_password' => $wifiPassword,
            'rx' => $rx,
            'temp' => $temp,
            'ip_pppoe' => $ipPppoe,
            'active_clients' => $activeClients,
            'serial_number' => $serialNumber,
            'product_class' => $productClass,
            'manufacturer' => $manufacturer,
            'parameters' => $item
        ];
        
        $devices[] = $device;
    }
    
    return $devices;
}

/**
 * Ambil detail device berdasarkan customer ID (PPPoE Username)
 */
function getDeviceDetailByCustomerId($customerId) {
    $devices = getGenieACSDevices(2000);
    
    foreach ($devices as $device) {
        if ($device['id'] == $customerId) {
            return [
                'found' => true,
                'device_id' => $device['device_id'],
                'ssid' => $device['ssid'] ?? '-',
                'wifi_password' => $device['wifi_password'] ?? '******',
                'status' => $device['status'] ?? 'Offline',
                'rx_power' => $device['rx'] ?? '-',
                'temp' => $device['temp'] ?? '-',
                'ip_pppoe' => $device['ip_pppoe'] ?? '-',
                'active_clients' => $device['active_clients'] ?? 0
            ];
        }
    }
    
    return ['found' => false];
}

/**
 * Ambil device berdasarkan ID PPPoE (alias)
 */
function getDeviceByPppoeUsername($pppoeUsername) {
    return getDeviceDetailByCustomerId($pppoeUsername);
}

/**
 * Update parameter device di GenieACS
 */
function updateGenieACSParameter($deviceId, $parameter, $value) {
    $base_url = getGenieACSSetting('genieacs_url') ?: 'http://localhost:7557';
    // Auto-prefix http:// kalau belum ada protokol
    if (!preg_match('#^https?://#i', $base_url)) {
        $base_url = 'http://' . $base_url;
    }
    $url = rtrim($base_url, '/') . "/devices/{$deviceId}/tasks?connection_request";
    
    $payload = [
        'name' => 'setParameterValues',
        'parameterValues' => [
            [$parameter, $value, 'xsd:string']
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => $httpCode == 200 || $httpCode == 201 || $httpCode == 202 || $httpCode == 204,
        'code' => $httpCode,
        'response' => $response
    ];
}

// Alias untuk update
function updateDeviceParameter($deviceId, $parameter, $value) {
    $result = updateGenieACSParameter($deviceId, $parameter, $value);
    return $result['success'] ?? false;
}
?>
