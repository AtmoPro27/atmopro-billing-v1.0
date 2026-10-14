</div>
<div class="footer">
    <i class="fas fa-plug"></i> <?= htmlspecialchars($company_name) ?> Billing System by Demo &copy; <?= date('Y') ?> - All Rights Reserved
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- PWA Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/portal/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful with scope: ', registration.scope);
            })
            .catch(function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
    });
}

// PWA Install Prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    // Jangan preventDefault dulu, biarkan browser handle
    // e.preventDefault(); // <- COMMENT INI
    
    // Stash the event so it can be triggered later
    deferredPrompt = e;
    console.log('Install prompt available');
    
    // Tampilkan tombol install
    const installBtn = document.getElementById('installAppBtn');
    if (installBtn) {
        installBtn.style.display = 'inline-block';
        installBtn.addEventListener('click', function() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    } else {
                        console.log('User dismissed the install prompt');
                    }
                    deferredPrompt = null;
                    this.style.display = 'none';
                });
            }
        });
    }
});

// Check if app is installed
window.addEventListener('appinstalled', (evt) => {
    console.log('App installed successfully');
    const installBtn = document.getElementById('installAppBtn');
    if (installBtn) {
        installBtn.style.display = 'none';
    }
});
</script>

<!-- Tombol Install (Opsional) -->
<div id="installBanner" style="display:none; position:fixed; bottom:0; left:0; right:0; background:#0d5c1e; color:white; padding:15px; text-align:center; z-index:9999;">
    <span style="font-weight:bold;">📱 Install Aplikasi Demo</span>
    <span style="margin:0 15px;">Akses lebih cepat & offline</span>
    <button onclick="installApp()" style="background:white; color:#0d5c1e; border:none; padding:8px 20px; border-radius:5px; font-weight:bold; cursor:pointer;">Install</button>
    <button onclick="document.getElementById('installBanner').style.display='none'" style="background:transparent; color:white; border:1px solid white; padding:8px 15px; border-radius:5px; cursor:pointer; margin-left:10px;">Tutup</button>
</div>

<script>
function installApp() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the install prompt');
            } else {
                console.log('User dismissed the install prompt');
            }
            deferredPrompt = null;
            document.getElementById('installBanner').style.display = 'none';
        });
    }
}

// Tampilkan banner install jika tersedia
window.addEventListener('beforeinstallprompt', (e) => {
    document.getElementById('installBanner').style.display = 'block';
});
</script>
</body>
</html>
