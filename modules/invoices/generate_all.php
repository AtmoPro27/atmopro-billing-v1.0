<?php
require_once __DIR__ . '/../../includes/auth_check.php';

include '../../includes/header.php';

$result = '';
$created = 0;
$failed = 0;

if (isset($_POST['generate'])) {
    try {
        require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
        
        $stmt = $pdo->query("
            SELECT p.id, p.id_pelanggan, p.nama_lengkap, p.paket_id, pk.harga, pk.nama_paket 
            FROM pelanggan p 
            LEFT JOIN paket_internet pk ON p.paket_id = pk.id 
            WHERE p.status = 'active' 
            AND pk.id IS NOT NULL
            AND pk.harga > 0
            AND (p.tipe_pelanggan = 'reguler' OR p.tipe_pelanggan IS NULL)
        ");
        $customers = $stmt->fetchAll();
        
        foreach ($customers as $customer) {
            $harga = $customer['harga'] ?? 0;
            if ($harga <= 0) continue;
            
            $cek = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE pelanggan_id = ? AND MONTH(tgl_invoice) = MONTH(CURDATE()) AND YEAR(tgl_invoice) = YEAR(CURDATE())");
            $cek->execute([$customer['id']]);
            if ($cek->fetchColumn() > 0) continue;
            
            $no_invoice = 'INV/' . date('Ym') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $jatuh_tempo = date('Y-m-d', strtotime('+30 days'));
            $keterangan = "Tagihan bulanan untuk paket " . ($customer['nama_paket'] ?? 'Internet');
            
            $ins = $pdo->prepare("INSERT INTO invoice (no_invoice, pelanggan_id, jumlah, tgl_invoice, tgl_jatuh_tempo, status, keterangan) VALUES (?, ?, ?, NOW(), ?, 'pending', ?)");
            $ins->execute([$no_invoice, $customer['id'], $harga, $jatuh_tempo, $keterangan]);
            $created++;
        }
        
        $result = "<div class='alert alert-success'>Berhasil generate $created tagihan!</div>";
    } catch(PDOException $e) {
        $result = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-file-invoice"></i> Generate Tagihan Manual
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?= $result ?>
        
        <p>Fitur ini akan membuat tagihan bulanan untuk semua pelanggan aktif yang belum memiliki tagihan bulan ini.</p>
        
        <form method="POST">
            <button type="submit" name="generate" class="btn btn-primary" onclick="return confirm('Generate tagihan untuk semua pelanggan?')">
                <i class="fas fa-sync-alt"></i> Generate Tagihan Sekarang
            </button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
