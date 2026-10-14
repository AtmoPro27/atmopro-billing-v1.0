<?php
require_once __DIR__ . '/../../includes/upload_validator.php';
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard.php");
    exit();
}

include '../../includes/header.php';

// Buat folder uploads jika belum ada
$upload_dir = '../../uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Buat folder logo dan favicon
$logo_dir = '../../uploads/logo/';
$favicon_dir = '../../uploads/favicon/';
if (!file_exists($logo_dir)) mkdir($logo_dir, 0777, true);
if (!file_exists($favicon_dir)) mkdir($favicon_dir, 0777, true);

try {
    $pdo = require_once __DIR__ . "/../../config/database.php";
$pdo = Database::getInstance()->getConnection();
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS pengaturan (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        key_setting VARCHAR(100) NOT NULL UNIQUE,
        value TEXT,
        description TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengaturan");
    if ($stmt->fetchColumn() == 0) {
        $defaults = [
            ['company_name', 'DEMO COMPANY', 'Nama Perusahaan'],
            ['company_tagline', 'HIGH SPEED INTERNET CONNECTION', 'Tagline Perusahaan'],
            ['company_logo', '', 'Logo Perusahaan'],
            ['company_favicon', '', 'Favicon Perusahaan'],
            ['company_address', '', 'Alamat Perusahaan'],
            ['company_phone', '', 'No Telepon Perusahaan'],
            ['company_email', '', 'Email Perusahaan'],
            ['currency_symbol', 'Rp', 'Simbol Mata Uang'],
            ['date_format', 'd-m-Y', 'Format Tanggal'],
            ['timezone', 'Asia/Jakarta', 'Zona Waktu']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO pengaturan (key_setting, value, description) VALUES (?, ?, ?)");
        foreach ($defaults as $default) {
            $stmt->execute($default);
        }
    }
    
    // Proses upload
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Update text settings
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $setting_key = substr($key, 8);
                $stmt = $pdo->prepare("UPDATE pengaturan SET value = ? WHERE key_setting = ?");
                $stmt->execute([$value, $setting_key]);
            }
        }
        
        // Upload Logo (PATCHED)
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
            $check = validate_image_upload('company_logo', 5);
            if ($check['ok']) {
                $filename = safe_save_upload('company_logo', $logo_dir, 'logo');
                if ($filename) {
                    $logo_path = 'uploads/logo/' . $filename;
                    $stmt = $pdo->prepare("INSERT INTO pengaturan (key_setting, value) VALUES ('company_logo', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
                    $stmt->execute([$logo_path]);
                }
            } else {
                $error = "Logo: " . $check['error'];
            }
        }
        
        // Upload Favicon (PATCHED)
        if (isset($_FILES['company_favicon']) && $_FILES['company_favicon']['error'] == 0) {
            $check = validate_image_upload('company_favicon', 2);
            if ($check['ok']) {
                $filename = safe_save_upload('company_favicon', $favicon_dir, 'favicon');
                if ($filename) {
                    $favicon_path = 'uploads/favicon/' . $filename;
                    $stmt = $pdo->prepare("INSERT INTO pengaturan (key_setting, value) VALUES ('company_favicon', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
                    $stmt->execute([$favicon_path]);
                }
            } else {
                $error = ($error ? $error . ' | ' : '') . "Favicon: " . $check['error'];
            }
        }
        
        $success = "Pengaturan berhasil disimpan!";
    }
    
    $stmt = $pdo->query("SELECT * FROM pengaturan");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key_setting']] = $row['value'];
    }
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="card">
    <div class="card-header">
        <i class="fas fa-building"></i> Pengaturan Umum
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Perusahaan</label>
                    <input type="text" name="setting_company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tagline Perusahaan</label>
                    <input type="text" name="setting_company_tagline" class="form-control" value="<?= htmlspecialchars($settings['company_tagline'] ?? '') ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Logo Perusahaan</label>
                    <input type="file" name="company_logo" class="form-control" accept="image/*">
                    <?php if (!empty($settings['company_logo']) && file_exists($settings['company_logo'])): ?>
                        <div class="mt-2">
                            <img src="/<?= $settings['company_logo'] ?>" alt="Logo" style="max-height: 60px;">
                            <br><small class="text-muted">Logo saat ini</small>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Favicon</label>
                    <input type="file" name="company_favicon" class="form-control" accept="image/x-icon,image/png,image/jpeg,image/gif">
                    <?php if (!empty($settings['company_favicon']) && file_exists($settings['company_favicon'])): ?>
                        <div class="mt-2">
                            <img src="/<?= $settings['company_favicon'] ?>" alt="Favicon" style="max-height: 32px;">
                            <br><small class="text-muted">Favicon saat ini</small>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Alamat Perusahaan</label>
                    <textarea name="setting_company_address" class="form-control" rows="2"><?= htmlspecialchars($settings['company_address'] ?? '') ?></textarea>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">No Telepon Perusahaan</label>
                    <input type="text" name="setting_company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email Perusahaan</label>
                    <input type="email" name="setting_company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Simbol Mata Uang</label>
                    <input type="text" name="setting_currency_symbol" class="form-control" value="<?= htmlspecialchars($settings['currency_symbol'] ?? 'Rp') ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Format Tanggal</label>
                    <select name="setting_date_format" class="form-control">
                        <option value="d-m-Y" <?= ($settings['date_format'] ?? '') == 'd-m-Y' ? 'selected' : '' ?>>DD-MM-YYYY</option>
                        <option value="Y-m-d" <?= ($settings['date_format'] ?? '') == 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                        <option value="d/m/Y" <?= ($settings['date_format'] ?? '') == 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Zona Waktu</label>
                    <select name="setting_timezone" class="form-control">
                        <option value="Asia/Jakarta" <?= ($settings['timezone'] ?? '') == 'Asia/Jakarta' ? 'selected' : '' ?>>Jakarta (WIB)</option>
                        <option value="Asia/Makassar" <?= ($settings['timezone'] ?? '') == 'Asia/Makassar' ? 'selected' : '' ?>>Makassar (WITA)</option>
                        <option value="Asia/Jayapura" <?= ($settings['timezone'] ?? '') == 'Asia/Jayapura' ? 'selected' : '' ?>>Jayapura (WIT)</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pengaturan</button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
