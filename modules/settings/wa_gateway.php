<?php
require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SESSION['user_role'] != 'super_admin') {
    header("Location: /dashboard.php");
    exit();
}

include '../../includes/header.php';

try {
    $pdo = require_once __DIR__ . "/../../config/database.php";
    $pdo = Database::getInstance()->getConnection();

    // Buat tabel jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS wa_settings (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        api_url VARCHAR(255),
        api_key VARCHAR(255),
        sender_number VARCHAR(20),
        is_active TINYINT(1) DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Buat tabel template
    $pdo->exec("CREATE TABLE IF NOT EXISTS wa_templates (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(100) NOT NULL,
        template_message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Proses update pengaturan
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
        $api_url = $_POST['api_url'];
        $api_key = $_POST['api_key'];
        $sender_number = $_POST['sender_number'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("DELETE FROM wa_settings");
        $stmt->execute();

        $provider = $_POST["provider"] ?? "fonnte";
        $stmt = $pdo->prepare("INSERT INTO wa_settings (provider, api_url, api_key, sender_number, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$provider, $api_url, $api_key, $sender_number, $is_active]);

        $success = "Pengaturan WA Gateway berhasil disimpan!";
    }

    // Proses update template
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_template'])) {
        $template_message = $_POST['template_message'];
        $stmt = $pdo->prepare("UPDATE wa_templates SET template_message = ? WHERE template_name = 'invoice_paid'");
        $stmt->execute([$template_message]);
        $success_template = "Template WA berhasil disimpan!";
    }

    // Ambil data pengaturan
    $stmt = $pdo->query("SELECT * FROM wa_settings LIMIT 1");
    $wa = $stmt->fetch();

    // Daftar nomor superadmin untuk dropdown tes
    $testNumbers = [];
    try {
        $stmtSA = $pdo->query("SELECT id, username, role, phone FROM users WHERE is_active = 1 AND phone IS NOT NULL AND phone != '' ORDER BY FIELD(role, 'super_admin', 'admin', 'teknisi', 'kasir'), username ASC");
        $testNumbers = $stmtSA->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $testNumbers = [];
    }


    // Ambil template
    $stmt = $pdo->query("SELECT template_message FROM wa_templates WHERE template_name = 'invoice_paid'");
    $template = $stmt->fetchColumn();
    if (!$template) {
        $template = "*TERIMA KASIH ATAS PEMBAYARAN ANDA*\n\n*{company_name}*\n\nKepada Yth. {customer_name}\n\nNo Invoice: {invoice_no}\nTanggal: {invoice_date}\nJatuh Tempo: {due_date}\nJumlah: {amount}\n\nStatus: LUNAS\nTgl Bayar: {payment_date}\n\n[KLIK SINI] {portal_url}\n\nKonfirmasi pembayaran via WA: {wa_number}\n\nTerima kasih telah menggunakan layanan kami.\n---\n{company_name} - {tagline}";
    }

} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="card">
    <div class="card-header">
        <i class="fab fa-whatsapp"></i> Pengaturan WA Gateway
        <a href="index.php" class="btn btn-secondary btn-sm float-end">Kembali</a>
    </div>
    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($success_template)): ?>
            <div class="alert alert-success"><?= $success_template ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs" id="waTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="gateway-tab" data-bs-toggle="tab" data-bs-target="#gateway" type="button" role="tab">Gateway</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="template-tab" data-bs-toggle="tab" data-bs-target="#template" type="button" role="tab">Template WA</button>
            </li>
        </ul>
        
        <div class="tab-content mt-3">
            <!-- Tab Gateway -->
            <div class="tab-pane fade show active" id="gateway" role="tabpanel">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Gunakan API WhatsApp Gateway seperti Fonnte, Wablas, atau Qontak.
                </div>

                <form method="POST">
                    <input type="hidden" name="save_settings" value="1">
                    <div class="mb-3">
                        <label class="form-label">Provider WA Gateway</label>
                        <select name="provider" class="form-control" id="provider_select" onchange="toggleProviderFields()">
                            <?php $selectedProvider = $wa["provider"] ?? "fonnte"; ?>
                            <option value="fonnte" <?= $selectedProvider == "fonnte" ? "selected" : "" ?>>Fonnte</option>
                            <option value="wablas" <?= $selectedProvider == "wablas" ? "selected" : "" ?>>Wablas</option>
                            <option value="woowa" <?= $selectedProvider == "woowa" ? "selected" : "" ?>>Woowa</option>
                            <option value="rapiwa" <?= $selectedProvider == "rapiwa" ? "selected" : "" ?>>Rapiwa</option>
                            <option value="custom" <?= $selectedProvider == "custom" ? "selected" : "" ?>>Custom / Lainnya</option>
                        </select>
                        <small class="text-muted" id="provider_hint">Pilih provider WA gateway.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">API URL</label>
                        <input type="url" name="api_url" class="form-control" value="<?= htmlspecialchars($wa['api_url'] ?? '') ?>" placeholder="https://api.fonnte.com/send">
                        <small class="text-muted">Contoh: https://api.fonnte.com/send</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">API Key / Token</label>
                        <input type="text" name="api_key" class="form-control" value="<?= htmlspecialchars($wa['api_key'] ?? '') ?>">
                        <small class="text-muted">API Key dari penyedia layanan WA Gateway</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nomor Pengirim</label>
                        <input type="text" name="sender_number" class="form-control" value="<?= htmlspecialchars($wa['sender_number'] ?? '') ?>" placeholder="628123456789">
                        <small class="text-muted">Nomor WhatsApp yang terdaftar di gateway</small>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" <?= ($wa['is_active'] ?? 0) == 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Aktifkan WA Gateway</label>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pengaturan</button>
                      <button type="button" class="btn btn-success ms-2" id="btn_test_wa" onclick="testWAConnection()">
                          <i class="fas fa-vial"></i> Test Koneksi
                      </button>

                      <div class="mt-3">
                          <label class="form-label">Nomor Tujuan Tes</label>
                          <select id="test_wa_number" class="form-control" style="max-width:350px;">
                              <?php if (empty($testNumbers)): ?>
                                  <option value="">— Tidak ada nomor superadmin —</option>
                              <?php else: ?>
                                  <?php foreach ($testNumbers as $tn): ?>
                                      <option value="<?= htmlspecialchars($tn['phone']) ?>">
                                          <?= htmlspecialchars($tn['username']) ?> [<?= htmlspecialchars($tn['role']) ?>] — <?= htmlspecialchars($tn['phone']) ?>
                                      </option>
                                  <?php endforeach; ?>
                              <?php endif; ?>
                          </select>
                          <small class="text-muted d-block">Format: 08xxx atau 628xxx</small>
                      </div>

                      <div id="test_wa_result" class="mt-3"></div>
                </form>
            </div>

            <!-- Tab Template WA -->
            <div class="tab-pane fade" id="template" role="tabpanel">
                <div class="alert alert-info">
                    <strong>Placeholder yang tersedia:</strong><br>
                    <code>{company_name}</code> - Nama perusahaan<br>
                    <code>{customer_name}</code> - Nama pelanggan<br>
                    <code>{customer_id}</code> - ID pelanggan<br>
                    <code>{invoice_no}</code> - Nomor invoice<br>
                    <code>{invoice_date}</code> - Tanggal invoice<br>
                    <code>{due_date}</code> - Tanggal jatuh tempo<br>
                    <code>{amount}</code> - Jumlah tagihan<br>
                    <code>{payment_date}</code> - Tanggal pembayaran<br>
                    <code>{portal_url}</code> - Link portal pelanggan<br>
                    <code>{wa_number}</code> - Nomor WA kasir<br>
                    <code>{tagline}</code> - Tagline perusahaan
                </div>

                <form method="POST">
                    <input type="hidden" name="save_template" value="1">
                    <div class="mb-3">
                        <label class="form-label">Template WA Invoice Lunas</label>
                        <textarea name="template_message" rows="15" class="form-control font-monospace"><?= htmlspecialchars($template) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Template</button>
                </form>
            </div>
        </div>
    </div>
</div>





<script>
// Aktifkan tab based on URL hash
$(document).ready(function() {
    if (window.location.hash === '#template') {
        $('.nav-tabs button[data-bs-target="#template"]').tab('show');
    }
});

function testWAConnection() {
    var btn = document.getElementById("btn_test_wa");
    var resultDiv = document.getElementById("test_wa_result");
    var selectEl = document.getElementById("test_wa_number");
    var phone = selectEl ? selectEl.value : "";

    if (!phone) { alert("Pilih nomor tujuan tes dulu"); return; }

    var apiUrl = document.querySelector("input[name=api_url]");
    var apiKey = document.querySelector("input[name=api_key]");
    var sender = document.querySelector("input[name=sender_number]");

    if (!apiUrl || !apiUrl.value) { alert("Isi API URL dulu"); return; }
    if (!apiKey || !apiKey.value) { alert("Isi API Key dulu"); return; }

    // Kirim setting dari form (biar tes pakai data yang baru diketik, belum disimpan)
    var payload = {
        phone: phone,
        use_saved: false,
        api_url: apiUrl.value,
        api_key: apiKey.value,
        sender_number: sender ? sender.value : ""
    };

    btn.disabled = true;
    btn.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Mengirim...";
    resultDiv.innerHTML = "<div class=\"alert alert-info\">Mengirim pesan tes ke " + phone + "...</div>";

    fetch("test_wa_connection.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var cls = data.success ? "alert-success" : "alert-danger";
        var icon = data.success ? "fa-check-circle" : "fa-times-circle";
        var html = "<div class=\"alert " + cls + "\"><i class=\"fas " + icon + "\"></i> <strong>" + (data.success ? "BERHASIL" : "GAGAL") + "</strong><br>" + (data.message || "");
        if (data.elapsed_ms) html += " <small>(" + data.elapsed_ms + " ms)</small>";
        if (data.raw_response) html += "<hr><small><strong>Respons:</strong> " + data.raw_response + "</small>";
        html += "</div>";
        resultDiv.innerHTML = html;
        btn.disabled = false;
        btn.innerHTML = "<i class=\"fas fa-vial\"></i> Test Koneksi";
    })
    .catch(function(err) {
        resultDiv.innerHTML = "<div class=\"alert alert-danger\">Error: " + err.message + "</div>";
        btn.disabled = false;
        btn.innerHTML = "<i class=\"fas fa-vial\"></i> Test Koneksi";
    });
}

function toggleProviderFields() {
    var sel = document.getElementById("provider_select");
    if (!sel) return;
    var provider = sel.value;
    var hint = document.getElementById("provider_hint");
    var urlInput = document.querySelector("input[name=api_url]");
    var senderInput = document.querySelector("input[name=sender_number]");

    var configs = {
        "fonnte": { url: "https://api.fonnte.com/send", hint: "Fonnte — Auth: Header Authorization. Field: target, message", sender: true },
        "wablas": { url: "https://<server>.wablas.com/api/send-message", hint: "Wablas — Ganti <server> dengan domain Anda. Field: phone, message", sender: false },
        "woowa":  { url: "https://api.woo-wa.com/...", hint: "Woowa — Butuh sender + API key. Field: number, message", sender: true },
        "rapiwa": { url: "https://api.rapiwa.id/send-message", hint: "Rapiwa — Auth: Header Authorization. Format JSON", sender: false },
        "custom": { url: "", hint: "Custom — Isi URL endpoint sendiri. Format: target, message", sender: false }
    };

    var cfg = configs[provider] || configs["custom"];
    hint.textContent = cfg.hint;

    if (urlInput && !urlInput.dataset.userEdited) {
        urlInput.value = cfg.url;
    }
    if (senderInput) {
        senderInput.parentElement.style.display = cfg.sender ? "" : "none";
    }
}

document.addEventListener("DOMContentLoaded", function() {
    var urlInput = document.querySelector("input[name=api_url]");
    if (urlInput) {
        urlInput.addEventListener("input", function() {
            urlInput.dataset.userEdited = "1";
        });
    }
    toggleProviderFields();
});

</script>

<?php include '../../includes/footer.php'; ?>
