<?php
require_once __DIR__ . '/../../includes/auth_check.php';

try {
    require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();

    $stmt = $pdo->query("
        SELECT
            p.id_pelanggan,
            p.nama_lengkap,
            p.alamat,
            p.no_telepon,
            p.tipe_pelanggan,
            pk.nama_paket,
            pk.harga,
            r.nama_router,
            p.jatuh_tempo,
            p.status,
            p.status_aktif,
            p.created_at
        FROM pelanggan p
        LEFT JOIN paket_internet pk ON p.paket_id = pk.id
        LEFT JOIN routers r ON pk.router_id = r.id
        ORDER BY p.id DESC
    ");
    $customers = $stmt->fetchAll();

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

$filename = 'data_pelanggan_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

fputcsv($output, [
    'ID Pelanggan',
    'Nama Lengkap',
    'Alamat',
    'No Telepon',
    'Tipe Pelanggan',
    'Paket',
    'Harga',
    'Router',
    'Jatuh Tempo',
    'Status',
    'Status Aktif',
    'Tanggal Daftar'
]);

foreach ($customers as $cust) {
    $status = ($cust['status'] == 'active') ? 'Aktif' : (($cust['status'] == 'inactive') ? 'Tidak Aktif' : 'Diblokir');
    
    fputcsv($output, [
        $cust['id_pelanggan'],
        $cust['nama_lengkap'],
        $cust['alamat'],
        $cust['no_telepon'],
        $cust['tipe_pelanggan'] ?? 'reguler',
        $cust['nama_paket'] ?? '-',
        $cust['harga'] ? number_format($cust['harga'], 0, ',', '.') : '-',
        $cust['nama_router'] ?? '-',
        $cust['jatuh_tempo'] ? date('d-m-Y', strtotime($cust['jatuh_tempo'])) : '-',
        $status,
        $cust['status_aktif'] ?? 'aktif',
        date('d-m-Y', strtotime($cust['created_at']))
    ]);
}

fclose($output);
exit();
?>
