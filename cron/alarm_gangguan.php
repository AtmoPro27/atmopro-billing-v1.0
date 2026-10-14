<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/genieacs_functions.php';
require_once __DIR__ . '/../includes/telegram_alarm_notification.php';

date_default_timezone_set('Asia/Jakarta');

$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo date('[Y-m-d H:i:s]') . " START - Alarm Gangguan\n";

const RX_LEMAH        = -27;
const GRACE_PERIOD    = 15;
const MIN_MASAL       = 5;
const RX_NORMAL_TARGET = 2;

$devices = getGenieACSDevices(2000);
$deviceMap = [];
foreach ($devices as $d) {
    if (!empty($d['id'])) $deviceMap[$d['id']] = $d;
}
echo "📦 Device: " . count($deviceMap) . "\n";

$stmt = $pdo->query("
    SELECT p.id, p.id_pelanggan, p.nama_lengkap, p.alamat, p.no_telepon,
           p.is_online, p.serial_number, p.down_since, pk.nama_paket
    FROM pelanggan p
    LEFT JOIN paket_internet pk ON pk.id = p.paket_id
    WHERE p.status = 'active' AND p.status_aktif = 'aktif'
");
$pelanggan = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "👥 Pelanggan aktif: " . count($pelanggan) . "\n";

$totalDown = 0;
$totalRX = 0;
$totalRecovery = 0;
$totalRXNormal = 0;
$totalSkip = 0;

foreach ($pelanggan as $p) {
    $idPel = $p['id_pelanggan'];
    $dev = $deviceMap[$idPel] ?? null;
    $pppoeOnline = ($p['is_online'] == 1);
    
    // ============================================================
    // PELANGGAN DOWN
    // ============================================================
    if (!$pppoeOnline) {
        
        if (empty($p['down_since'])) {
            $pdo->prepare("UPDATE pelanggan SET down_since = NOW() WHERE id = ?")
                ->execute([$p['id']]);
            echo "  ⏱️  DOWN baru: {$idPel} ({$p['nama_lengkap']}) - tunggu " . GRACE_PERIOD . "m\n";
            $totalSkip++;
            continue;
        }
        
        $durasiDown = round((time() - strtotime($p['down_since'])) / 60);
        
        if ($durasiDown < GRACE_PERIOD) {
            $totalSkip++;
            continue;
        }
        
        $cek = $pdo->prepare("
            SELECT id FROM alarm_aktif 
            WHERE pelanggan_id = ? AND tipe_alarm = 'DOWN' AND status = 'aktif'
        ");
        $cek->execute([$p['id']]);
        $sudahAda = $cek->fetchColumn();
        
        if ($sudahAda) {
            $totalSkip++;
            continue;
        }
        
        $waktuDown = date('d/m/Y H:i', strtotime($p['down_since']));
        $pesan = formatAlarmDown($p, $dev ?: ['rx' => '-'], $durasiDown, $waktuDown);
        $result = sendTelegramAlarm($pdo, $pesan, 'alarm_down', $p['id'], $idPel, 'DOWN');
        
        if ($result['success']) {
            echo "  🚨 DOWN: {$idPel} ({$p['nama_lengkap']}) - {$durasiDown}m → {$result['total']} penerima\n";
            $totalDown++;
        }
        continue;
    }
    
    // ============================================================
    // PELANGGAN ONLINE → CEK RECOVERY DOWN
    // ============================================================
    $cek = $pdo->prepare("
        SELECT id, mulai, status FROM alarm_aktif 
        WHERE pelanggan_id = ? AND tipe_alarm = 'DOWN'
        ORDER BY CASE WHEN status = 'aktif' THEN 1 ELSE 2 END, id DESC
        LIMIT 1
    ");
    $cek->execute([$p['id']]);
    $alarmDown = $cek->fetch(PDO::FETCH_ASSOC);
    
    if ($alarmDown) {
        $durasiGangguan = round((time() - strtotime($alarmDown['mulai'])) / 60);
        $sudahDiAck = ($alarmDown['status'] == 'selesai');
        
        $pesan = formatRecovery($p, $durasiGangguan, $sudahDiAck);
        $result = sendTelegramAlarm($pdo, $pesan, 'alarm_down', $p['id'], $idPel, 'RECOVERY');
        
        if ($result['success']) {
            echo "  ✅ RECOVERY: {$idPel} ({$p['nama_lengkap']}) - pulih setelah {$durasiGangguan}m\n";
            $totalRecovery++;
        }
        
        $pdo->prepare("DELETE FROM alarm_aktif WHERE pelanggan_id = ? AND tipe_alarm = 'DOWN'")
            ->execute([$p['id']]);
    }
    
    if (!empty($p['down_since'])) {
        $pdo->prepare("UPDATE pelanggan SET down_since = NULL WHERE id = ?")
            ->execute([$p['id']]);
    }
    
    // ============================================================
    // CEK RX
    // ============================================================
    if (!$dev) continue;
    $rx = $dev['rx'] ?? null;
    if ($rx === null || $rx === '-' || $rx === '') continue;
    
    $rx = (float) $rx;
    $rxNormal = ($rx >= RX_LEMAH);
    
    // Cek alarm RX aktif untuk pelanggan ini
    $cek = $pdo->prepare("
        SELECT id, rx_normal_count FROM alarm_aktif 
        WHERE pelanggan_id = ? AND tipe_alarm = 'RX_LEMAH' AND status = 'aktif'
    ");
    $cek->execute([$p['id']]);
    $alarmRX = $cek->fetch(PDO::FETCH_ASSOC);
    
    if ($rxNormal) {
        // RX normal sekarang
        if ($alarmRX) {
            // Sudah ada alarm RX → increment counter
            $newCount = ($alarmRX['rx_normal_count'] ?? 0) + 1;
            
            if ($newCount >= RX_NORMAL_TARGET) {
                // Sudah 2x berturut-turut normal → hapus alarm + kirim notif
                $rxLama = 'RX lemah sebelumnya';
                
                $pesan = formatRecoveryRX($p, $rxLama, $rx);
                $result = sendTelegramAlarm($pdo, $pesan, 'alarm_rx', $p['id'], $idPel, 'RX_NORMAL');
                
                if ($result['success']) {
                    echo "  ✅ RX NORMAL: {$idPel} ({$p['nama_lengkap']}) - RX: {$rx} dBm\n";
                    $totalRXNormal++;
                }
                
                $pdo->prepare("DELETE FROM alarm_aktif WHERE id = ?")->execute([$alarmRX['id']]);
            } else {
                // Update counter
                $pdo->prepare("UPDATE alarm_aktif SET rx_normal_count = ?, rx_last_check = NOW() WHERE id = ?")
                    ->execute([$newCount, $alarmRX['id']]);
                echo "  🔄 RX normal {$idPel} ({$newCount}/" . RX_NORMAL_TARGET . ")\n";
                $totalSkip++;
            }
        }
        // Kalau tidak ada alarm RX dan RX normal → tidak ada aksi
        continue;
    }
    
    // RX lemah → cek alarm
    if ($alarmRX) {
        // Sudah ada alarm → reset counter
        $pdo->prepare("UPDATE alarm_aktif SET rx_normal_count = 0 WHERE id = ?")
            ->execute([$alarmRX['id']]);
        $totalSkip++;
        continue;
    }
    
    // Kirim notif RX baru
    $pesan = formatAlarmRX($p, $dev, 'RX_LEMAH');
    $result = sendTelegramAlarm($pdo, $pesan, 'alarm_rx', $p['id'], $idPel, 'RX_LEMAH');
    
    if ($result['success']) {
        echo "  ⚠️  RX LEMAH: {$idPel} ({$rx} dBm) → {$result['total']} penerima\n";
        $totalRX++;
    }
}

// ============================================================
// DETEKSI GANGGUAN MASAL PER WILAYAH
// ============================================================
echo "\n🔍 Cek gangguan masal per wilayah...\n";

// --- DOWN MASAL ---
$stmt = $pdo->query("
    SELECT p.id_pelanggan, p.nama_lengkap, p.alamat, p.down_since
    FROM pelanggan p
    WHERE p.status = 'active' 
      AND p.status_aktif = 'aktif'
      AND p.is_online = 0
      AND p.down_since IS NOT NULL
");
$downPelanggan = $stmt->fetchAll(PDO::FETCH_ASSOC);

$groupDown = [];
foreach ($downPelanggan as $dp) {
    $wilayah = ekstrakWilayah($dp['alamat']);
    $groupDown[$wilayah][] = $dp;
}

$totalMasal = 0;

foreach ($groupDown as $wilayah => $list) {
    if (count($list) < MIN_MASAL) continue;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM telegram_logs
        WHERE message LIKE ?
          AND type = 'alarm_masal'
          AND status = 'sent'
          AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $stmt->execute(["%GANGGUAN MASAL — {$wilayah}%"]);
    $sudahKirim = $stmt->fetchColumn() > 0;
    
    if ($sudahKirim) {
        echo "  ⏭️  Skip masal DOWN {$wilayah} (cooldown 30 menit)\n";
        continue;
    }
    
    $pesan = formatAlarmMasal($wilayah, $list);
    $result = sendTelegramAlarm($pdo, $pesan, 'alarm_masal', 0, '', 'MASAL');
    
    if ($result['success']) {
        echo "  🚨 MASAL DOWN: {$wilayah} - " . count($list) . " pelanggan → {$result['total']} penerima\n";
        $totalMasal++;
    }
}

// --- RX LEMAH MASAL ---
$rxLemahPelanggan = [];
foreach ($pelanggan as $p) {
    if ($p['is_online'] != 1) continue;
    $dev = $deviceMap[$p['id_pelanggan']] ?? null;
    if (!$dev) continue;
    $rx = $dev['rx'] ?? null;
    if ($rx === null || $rx === '-' || $rx === '') continue;
    $rx = (float) $rx;
    if ($rx >= RX_LEMAH) continue;
    
    $p['rx'] = $rx;
    $wilayah = ekstrakWilayah($p['alamat']);
    $rxLemahPelanggan[$wilayah][] = $p;
}

$totalMasalRX = 0;

foreach ($rxLemahPelanggan as $wilayah => $list) {
    if (count($list) < MIN_MASAL) continue;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM telegram_logs
        WHERE message LIKE ?
          AND type = 'alarm_masal_rx'
          AND status = 'sent'
          AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $stmt->execute(["%RX LEMAH MASAL — {$wilayah}%"]);
    $sudahKirim = $stmt->fetchColumn() > 0;
    
    if ($sudahKirim) {
        echo "  ⏭️  Skip masal RX {$wilayah} (cooldown 30 menit)\n";
        continue;
    }
    
    $pesan = formatAlarmMasalRX($wilayah, $list);
    $result = sendTelegramAlarm($pdo, $pesan, 'alarm_masal_rx', 0, '', 'MASAL');
    
    if ($result['success']) {
        echo "  ⚠️  MASAL RX: {$wilayah} - " . count($list) . " pelanggan → {$result['total']} penerima\n";
        $totalMasalRX++;
    }
}

echo date('[Y-m-d H:i:s]') . " SELESAI\n";
echo "Total DOWN: {$totalDown} | RX LEMAH: {$totalRX} | RECOVERY: {$totalRecovery} | RX NORMAL: {$totalRXNormal} | Skip: {$totalSkip} | Masal DOWN: {$totalMasal} | Masal RX: {$totalMasalRX}\n";
