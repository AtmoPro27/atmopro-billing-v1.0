<?php
require_once __DIR__ . "/telegram_notification.php";

function analisisRX($rx) {
    if ($rx === null || $rx === '-' || $rx === '' || $rx == 0) {
        return ['analisis' => 'RX tidak tersedia', 'kemungkinan' => 'ONT belum pernah kirim data TR-069', 'aksi' => 'Cek provisioning / TR-069 ONT'];
    }
    $rx = (float) $rx;
    if ($rx >= -24) return ['analisis' => 'RX normal sebelum putus', 'kemungkinan' => 'Kabel putus / listrik mati / ONT rusak', 'aksi' => 'Cek listrik, lalu fisik kabel drop'];
    if ($rx >= -27) return ['analisis' => 'RX lemah sebelum putus', 'kemungkinan' => 'Konektor longgar / redaman naik', 'aksi' => 'Cek konektor & drop cable'];
    return ['analisis' => 'RX kritis sebelum putus', 'kemungkinan' => 'Binding / redaman tinggi / ODP bermasalah', 'aksi' => 'Cek ODP & splitter, prioritas tinggi'];
}

function kategoriRX($rx) {
    if ($rx === null || $rx === '-' || $rx === '' || $rx == 0) return 'Tidak ada data';
    $rx = (float) $rx;
    if ($rx >= -24) return 'Bagus';
    if ($rx >= -27) return 'Sedang';
    return 'Kritis';
}

function formatDurasi($menit) {
    if ($menit == 0) return "baru saja";
    if ($menit < 60) return "{$menit} menit lalu";
    $jam = floor($menit / 60);
    $sisa = $menit % 60;
    return "{$jam} jam {$sisa} menit lalu";
}

function formatAlarmDown($p, $dev, $selisihMenit, $waktuDown = null) {
    $rx = $dev['rx'] ?? '-';
    $rxKosong = ($rx === '-' || $rx === '' || $rx === null || $rx == 0);
    
    if ($rxKosong) {
        if (!empty($p['serial_number'])) {
            $info = ['analisis' => 'ONT terdaftar tapi tidak kirim data', 'kemungkinan' => 'TR-069 mati / ONT hang / baru reboot', 'aksi' => 'Cek koneksi TR-069 & restart ONT'];
            $rxTampil = "Tidak ada data (ONT terdaftar)";
        } else {
            $info = ['analisis' => 'ONT belum terdaftar di GenieACS', 'kemungkinan' => 'Belum provisioning / salah konfigurasi TR-069', 'aksi' => 'Daftarkan ONT ke GenieACS'];
            $rxTampil = "Tidak ada data (belum terdaftar)";
        }
    } else {
        $info = analisisRX($rx);
        $rxTampil = "{$rx} dBm (" . kategoriRX($rx) . ")";
    }
    
    $durasi = formatDurasi($selisihMenit);
    
    $pesan  = "🚨 *PELANGGAN DOWN*\n";
    $pesan .= "──────────────────\n";
    $pesan .= "🆔 *ID*    : `{$p['id_pelanggan']}`\n";
    $pesan .= "👤 *Nama*  : {$p['nama_lengkap']}\n";
    $pesan .= "📱 *No*    : {$p['no_telepon']}\n";
    $pesan .= "📍 *Alamat*: {$p['alamat']}\n";
    if (!empty($p['nama_paket'])) $pesan .= "📦 *Paket* : {$p['nama_paket']}\n";
    $pesan .= "──────────────────\n";
    $pesan .= "🔌 *PPPoE* : *OFFLINE* (tidak dapat IP)\n";
    if ($waktuDown) $pesan .= "⏱️ *Down sejak*: {$waktuDown} ({$durasi})\n";
    else $pesan .= "⏱️ *Down*: {$durasi}\n";
    $pesan .= "──────────────────\n";
    $pesan .= "📶 *RX terakhir*: {$rxTampil}\n";
    $pesan .= "🔍 *Analisis*: {$info['analisis']}\n";
    $pesan .= "💡 *Kemungkinan*: {$info['kemungkinan']}\n";
    $pesan .= "✅ *Aksi*: {$info['aksi']}\n";
    
    return $pesan;
}

function formatAlarmRX($p, $dev, $jenisRX) {
    $rx = $dev['rx'] ?? '-';
    $kategori = kategoriRX($rx);
    
    $pesan  = "⚠️ *RX LEMAH*\n";
    $pesan .= "──────────────────\n";
    $pesan .= "🆔 *ID*    : `{$p['id_pelanggan']}`\n";
    $pesan .= "👤 *Nama*  : {$p['nama_lengkap']}\n";
    $pesan .= "📱 *No*    : {$p['no_telepon']}\n";
    $pesan .= "📍 *Alamat*: {$p['alamat']}\n";
    if (!empty($p['nama_paket'])) $pesan .= "📦 *Paket* : {$p['nama_paket']}\n";
    $pesan .= "──────────────────\n";
    $pesan .= "📶 *RX*    : {$rx} dBm ({$kategori})\n";
    $pesan .= "🔌 *PPPoE* : Online (masih jalan)\n";
    $pesan .= "📡 *ONT*   : {$dev['status']}\n";
    $pesan .= "──────────────────\n";
    $pesan .= "🔍 *Analisis*: RX lemah / loss\n";
    $pesan .= "💡 *Kemungkinan*: Konektor longgar / kabel drop tertekuk / ODP bermasalah\n";
    $pesan .= "✅ *Aksi*: Cek konektor & drop cable\n";
    
    return $pesan;
}

function formatRecovery($p, $durasi, $sudahDiAck = false) {
    if ($sudahDiAck) {
        $judul = "✅ *GANGGUAN SELESAI*";
        $footer = "Pelanggan sudah terhubung kembali.\nGangguan sudah ditandai selesai oleh teknisi.";
    } else {
        $judul = "✅ *PELANGGAN ONLINE KEMBALI*";
        $footer = "Pelanggan sudah terhubung kembali secara otomatis.\n(Tidak perlu tindakan lanjutan.)";
    }
    
    if ($durasi < 60) {
        $durasiTeks = "{$durasi} menit";
    } else {
        $jam = floor($durasi / 60);
        $sisa = $durasi % 60;
        $durasiTeks = "{$jam} jam {$sisa} menit";
    }
    
    $pesan  = "{$judul}\n";
    $pesan .= "──────────────────\n";
    $pesan .= "🆔 *ID*    : `{$p['id_pelanggan']}`\n";
    $pesan .= "👤 *Nama*  : {$p['nama_lengkap']}\n";
    $pesan .= "📍 *Alamat*: {$p['alamat']}\n";
    $pesan .= "──────────────────\n";
    $pesan .= "🔌 *PPPoE* : *ONLINE* kembali\n";
    $pesan .= "⏱️ *Durasi gangguan*: {$durasiTeks}\n";
    $pesan .= "──────────────────\n";
    $pesan .= "{$footer}\n";
    
    return $pesan;
}

function formatRecoveryRX($p, $rxLama, $rxBaru) {
    $pesan  = "✅ *RX NORMAL KEMBALI*\n";
    $pesan .= "──────────────────\n";
    $pesan .= "🆔 *ID*    : `{$p['id_pelanggan']}`\n";
    $pesan .= "👤 *Nama*  : {$p['nama_lengkap']}\n";
    $pesan .= "📍 *Alamat*: {$p['alamat']}\n";
    $pesan .= "──────────────────\n";
    $pesan .= "📶 *RX sebelumnya*: {$rxLama} dBm\n";
    $pesan .= "📶 *RX sekarang*: {$rxBaru} dBm (Normal)\n";
    $pesan .= "──────────────────\n";
    $pesan .= "Kualitas sinyal sudah normal kembali. ✅\n";
    
    return $pesan;
}

/**
 * Kirim alarm ke penerima + simpan daftar penerima untuk broadcast
 */
function sendTelegramAlarm($pdo, $message, $alarmType, $pelangganId, $idPelanggan, $tipeAlarm) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM telegram_ticket_notifications
            WHERE ticket_type = ? AND is_active = 1
        ");
        $stmt->execute([$alarmType]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($configs)) return ['success' => false, 'message' => 'Konfigurasi tidak ada'];

        $sentTo = [];
        $penerima = [];

        foreach ($configs as $config) {
            $chatIds = [];
            if (strpos($config['chat_ids'], 'auto_') === 0) {
                $role = str_replace('auto_', '', $config['chat_ids']);
                $stmt = $pdo->prepare("
                    SELECT telegram_chat_id FROM users
                    WHERE role = ? AND is_active = 1
                      AND receive_telegram_notification = 1
                      AND telegram_chat_id IS NOT NULL
                      AND telegram_chat_id != ''
                ");
                $stmt->execute([$role]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $chatIds[] = $row['telegram_chat_id'];
                }
            } else {
                $chatIds = array_map('trim', explode(',', $config['chat_ids']));
            }

            foreach ($chatIds as $chatId) {
                if (empty($chatId)) continue;

                if (in_array($tipeAlarm, ['DOWN', 'RX_LEMAH'])) {
                    $result = sendTelegramWithButton($pdo, $chatId, $message, $alarmType, $pelangganId, $idPelanggan, $tipeAlarm);
                    if ($result['success']) {
                        $sentTo[] = $chatId;
                        if (!empty($result['alarm_id']) && !empty($result['message_id'])) {
                            $penerima[] = [
                                'chat_id' => $chatId,
                                'alarm_id' => $result['alarm_id'],
                                'message_id' => $result['message_id']
                            ];
                        }
                    }
                } else {
                    $result = sendTelegramNotification($pdo, $chatId, $message, $alarmType);
                    if ($result['success']) $sentTo[] = $chatId;
                }

                sleep(1);
            }
        }

        if (!empty($penerima) && $pelangganId && $tipeAlarm) {
            $pdo->prepare("
                UPDATE alarm_aktif SET penerima = ?
                WHERE pelanggan_id = ? AND tipe_alarm = ? AND status = 'aktif'
            ")->execute([json_encode($penerima), $pelangganId, $tipeAlarm]);
        }

        return ['success' => !empty($sentTo), 'sent_to' => $sentTo, 'total' => count($sentTo)];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Kirim Telegram dengan tombol inline
 */
function sendTelegramWithButton($pdo, $chatId, $message, $alarmType, $pelangganId, $idPelanggan, $tipeAlarm) {
    try {
        $stmt = $pdo->query("SELECT value FROM pengaturan WHERE key_setting = 'telegram_bot_token'");
        $token = $stmt->fetchColumn();

        if (empty($token)) return ['success' => false];

        $cek = $pdo->prepare("SELECT id FROM alarm_aktif WHERE pelanggan_id = ? AND tipe_alarm = ?");
        $cek->execute([$pelangganId, $tipeAlarm]);
        $alarmId = $cek->fetchColumn();

        if ($alarmId) {
            $pdo->prepare("UPDATE alarm_aktif SET status='aktif', mulai=NOW(), selesai=NULL, chat_id=?, rx_normal_count=0 WHERE id = ?")
                ->execute([$chatId, $alarmId]);
        } else {
            $ins = $pdo->prepare("
                INSERT INTO alarm_aktif (pelanggan_id, id_pelanggan, tipe_alarm, chat_id, status, rx_normal_count)
                VALUES (?, ?, ?, ?, 'aktif', 0)
            ");
            $ins->execute([$pelangganId, $idPelanggan, $tipeAlarm, $chatId]);
            $alarmId = $pdo->lastInsertId();
        }

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '✅ Selesai Perbaikan', 'callback_data' => "ack_{$alarmId}"]
            ]]
        ];

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $messageId = null;
        if ($httpCode == 200) {
            $resp = json_decode($response, true);
            $messageId = $resp['result']['message_id'] ?? null;
        }

        $status = ($httpCode == 200) ? 'sent' : 'failed';
        $clean = mb_substr($message, 0, 500, "UTF-8");
        $cleanResp = is_string($response) ? mb_substr($response, 0, 500, "UTF-8") : '';
        $pdo->prepare("INSERT INTO telegram_logs (chat_id, message, status, response, type, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
            ->execute([$chatId, $clean, $status, $cleanResp, $alarmType]);

        return [
            'success' => $httpCode == 200,
            'alarm_id' => $alarmId,
            'message_id' => $messageId
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Ekstrak nama wilayah dari alamat pelanggan
 */
function ekstrakWilayah($alamat) {
    $alamat = trim($alamat);
    if (empty($alamat)) return 'TIDAK DIKETAHUI';
    
    $alamat = preg_replace('/[,\.]+/', ' ', $alamat);
    $alamat = preg_replace('/\s+/', ' ', trim($alamat));
    
    if (preg_match('/^([^\s]+)\s+rt\b/i', $alamat, $m)) {
        $wilayah = $m[1];
    } else {
        $parts = explode(' ', $alamat);
        $wilayah = $parts[0] ?? '';
    }
    
    $wilayah = strtoupper($wilayah);
    
    $mapping = [
        'NGREEJNG'    => 'NGREJENG',
        'NGREEJENG'   => 'NGREJENG',
        'MACANMTAI'   => 'MACANMATI',
        'ORO'         => 'ORO-ORO',
        'OROORO'      => 'ORO-ORO',
        'KANDANGSAPI' => 'KANDANG',
        'POPNGAN'     => 'POPONGAN',
    ];
    
    return $mapping[$wilayah] ?? $wilayah;
}

/**
 * Format notif gangguan masal per wilayah (DOWN)
 */
function formatAlarmMasal($wilayah, $pelangganList) {
    $jumlah = count($pelangganList);
    $maxTampil = 15;
    
    $pesan  = "🚨 *GANGGUAN MASAL — {$wilayah}*\n";
    $pesan .= "──────────────────\n";
    $pesan .= "📍 *Wilayah*: {$wilayah}\n";
    $pesan .= "👥 *Pelanggan down*: {$jumlah}\n";
    $pesan .= "──────────────────\n\n";
    
    foreach (array_slice($pelangganList, 0, $maxTampil) as $p) {
        $pesan .= "• `{$p['id_pelanggan']}` — {$p['nama_lengkap']}\n";
        $pesan .= "  _{$p['alamat']}_\n";
    }
    
    if ($jumlah > $maxTampil) {
        $pesan .= "\n_… dan " . ($jumlah - $maxTampil) . " pelanggan lainnya_\n";
    }
    
    $pesan .= "\n──────────────────\n";
    $pesan .= "⚠️ *Kemungkinan*: Masalah ODP / feeder wilayah {$wilayah}\n";
    $pesan .= "✅ *Aksi*: Cek ODP {$wilayah} dulu sebelum ke lokasi pelanggan\n";
    
    return $pesan;
}

/**
 * Format notif gangguan masal per wilayah (RX LEMAH)
 */
function formatAlarmMasalRX($wilayah, $pelangganList) {
    $jumlah = count($pelangganList);
    $maxTampil = 15;
    
    $pesan  = "⚠️ *RX LEMAH MASAL — {$wilayah}*\n";
    $pesan .= "──────────────────\n";
    $pesan .= "📍 *Wilayah*: {$wilayah}\n";
    $pesan .= "👥 *Pelanggan RX lemah*: {$jumlah}\n";
    $pesan .= "──────────────────\n\n";
    
    foreach (array_slice($pelangganList, 0, $maxTampil) as $p) {
        $rx = $p['rx'] ?? '-';
        $pesan .= "• `{$p['id_pelanggan']}` — {$p['nama_lengkap']}\n";
        $pesan .= "  _{$p['alamat']} — RX: {$rx} dBm_\n";
    }
    
    if ($jumlah > $maxTampil) {
        $pesan .= "\n_… dan " . ($jumlah - $maxTampil) . " pelanggan lainnya_\n";
    }
    
    $pesan .= "\n──────────────────\n";
    $pesan .= "⚠️ *Kemungkinan*: Masalah ODP / splitter / feeder wilayah {$wilayah}\n";
    $pesan .= "✅ *Aksi*: Cek ODP {$wilayah} dulu sebelum ke lokasi pelanggan\n";
    
    return $pesan;
}
