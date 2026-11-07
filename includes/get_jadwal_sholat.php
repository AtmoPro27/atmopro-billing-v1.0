<?php
// Mengambil jadwal sholat untuk Sragen menggunakan API Aladhan
// Koordinat Sragen: Latitude -7.4264, Longitude 111.0222

function getJadwalSholatSragen() {
    $date = date('d-m-Y');
    $url = "https://api.aladhan.com/v1/timings/{$date}?latitude=-7.4264&longitude=111.0222&method=20";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['data']['timings'])) {
            $t = $data['data']['timings'];
            return [
                'imsak' => substr($t['Imsak'], 0, 5),
                'subuh' => substr($t['Fajr'], 0, 5),
                'dzuhur' => substr($t['Dhuhr'], 0, 5),
                'ashar' => substr($t['Asr'], 0, 5),
                'maghrib' => substr($t['Maghrib'], 0, 5),
                'isya' => substr($t['Isha'], 0, 5),
                'terbit' => substr($t['Sunrise'], 0, 5),
                'dhuha' => ''
            ];
        }
    }
    
    // Fallback jika API gagal
    return [
        'imsak' => '04:11',
        'subuh' => '04:21',
        'dzuhur' => '11:38',
        'ashar' => '14:57',
        'maghrib' => '17:28',
        'isya' => '18:41'
    ];
}

// Cache 1 jam
$cache_file = __DIR__ . '/../cache/jadwal_sholat_cache.json';
$cache_time = 86400;

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    $sholat_today = json_decode(file_get_contents($cache_file), true);
} else {
    $sholat_today = getJadwalSholatSragen();
    file_put_contents($cache_file, json_encode($sholat_today));
}
?>
