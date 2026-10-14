<?php
// File ini di-include dari detail.php
// Variabel yang dibutuhkan: $device_id, $customer
$genieacsDeviceIdJs = htmlspecialchars($device_id ?? ($customer['genieacs_device_id'] ?? ''), ENT_QUOTES);
$customerIdJs = htmlspecialchars($customer['id_pelanggan'] ?? '', ENT_QUOTES);
?>

<!-- PERANGKAT TERHUBUNG -->
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card border-info">
            <div class="card-header bg-info text-white">
                <i class="fas fa-mobile-alt"></i> PERANGKAT TERHUBUNG
                <button class="btn btn-sm btn-light float-end" onclick="loadDevices()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
            <div class="card-body">
                <div id="deviceListContainer">
                    <div class="text-center text-muted py-2">
                        <div class="spinner-border spinner-border-sm"></div> Memuat...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REBOOT ONT -->
<div class="row mt-3">
    <div class="col-md-12">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-power-off"></i> REBOOT ONT
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    Restart perangkat ONT. Koneksi internet akan terputus sekitar 1-2 menit.
                </p>
                <button class="btn btn-warning" onclick="rebootONT()">
                    <i class="fas fa-sync"></i> Reboot Sekarang
                </button>
                <div id="rebootStatus" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const GENIEACS_DEVICE_ID = '<?= $genieacsDeviceIdJs ?>';
    const CUSTOMER_ID = '<?= $customerIdJs ?>';

    window.loadDevices = function() {
        const el = document.getElementById('deviceListContainer');
        if (!el) return;

        if (!GENIEACS_DEVICE_ID) {
            el.innerHTML = '<div class="alert alert-warning mb-0">ONT belum terdaftar di GenieACS. Hubungi teknisi.</div>';
            return;
        }

        el.innerHTML = '<div class="text-center text-muted py-2"><div class="spinner-border spinner-border-sm"></div> Memuat...</div>';

        fetch('/api/get_connected_devices.php?device_id=' + encodeURIComponent(GENIEACS_DEVICE_ID))
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    el.innerHTML = '<div class="alert alert-danger mb-0">' + data.message + '</div>';
                    return;
                }
                if (data.total === 0) {
                    el.innerHTML = '<div class="alert alert-info mb-0"><i class="fas fa-info-circle"></i> Tidak ada perangkat terhubung saat ini.</div>';
                    return;
                }

                let html = '<div class="table-responsive"><table class="table table-sm table-hover mb-0">';
                html += '<thead class="table-light"><tr><th>Nama</th><th>IP</th><th>MAC</th></tr></thead><tbody>';
                data.devices.forEach(d => {
                    html += '<tr>';
                    html += '<td><i class="fas fa-mobile-alt text-primary"></i> ' + escapeHtml(d.hostname || '-') + '</td>';
                    html += '<td><code>' + escapeHtml(d.ip || '-') + '</code></td>';
                    html += '<td><small class="text-muted">' + escapeHtml(d.mac || '-') + '</small></td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                html += '<small class="text-muted d-block mt-2"><i class="fas fa-check-circle text-success"></i> Total: ' + data.total + ' perangkat terhubung</small>';

                el.innerHTML = html;
            })
            .catch(e => {
                el.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat: ' + e.message + '</div>';
            });
    };

    window.rebootONT = function() {
        if (!GENIEACS_DEVICE_ID) {
            alert('ONT belum terdaftar di GenieACS.');
            return;
        }

        if (!confirm('Yakin ingin reboot ONT?\n\nKoneksi internet akan terputus sekitar 1-2 menit.')) {
            return;
        }

        const el = document.getElementById('rebootStatus');
        el.innerHTML = '<div class="alert alert-info mb-0"><div class="spinner-border spinner-border-sm"></div> Mengirim perintah reboot...</div>';

        fetch('/api/reboot_ont.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'device_id=' + encodeURIComponent(GENIEACS_DEVICE_ID)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                el.innerHTML = '<div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> ' + escapeHtml(data.message) + '</div>';
                setTimeout(() => { el.innerHTML = ''; }, 15000);
            } else {
                el.innerHTML = '<div class="alert alert-danger mb-0"><i class="fas fa-times-circle"></i> ' + escapeHtml(data.message) + '</div>';
            }
        })
        .catch(e => {
            el.innerHTML = '<div class="alert alert-danger mb-0">Error: ' + e.message + '</div>';
        });
    };

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[m]);
    }

    // Auto-load saat halaman siap
    document.addEventListener('DOMContentLoaded', function() {
        window.loadDevices();
    });
})();
</script>
