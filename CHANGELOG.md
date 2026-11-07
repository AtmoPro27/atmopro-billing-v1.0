# Changelog

## [1.2] - 2026-11-05
### Fixed
- Hardcode "Klasnet" di 8+ file (PDF export, print invoice, server widget)
- Cache PPPoE shared → per-project (demo_pppoe_*.txt)
- Dual auth system (session_check + auth_check) → **konsolidasi ke auth_check**
- Hardcode company name di 16+ file (demo + klasnet)
- PWA manifest hardcode "Demo Company"
- Favicon hardcode di header_portal.php
- File sampah klasnet (header.php.error, dashboard.php.broken)

### Added
- Sistem auth terpadu (auth_check.php) dengan AJAX handling
- Export PDF (invoice, laba rugi, investor)
- GenieACS search AJAX
- Cron auto_zakat, auto_bagi_hasil
- File test tombol (WA, Telegram, API)

### Improved
- Performance: dashboard < 1 detik
- Security: upload_validator (5 layer validasi)
- PWA manifest dinamis (dari DB)

## [1.1] - 2026-10-12
### Added
- Session timeout (auto logout 30 menit idle, 8 jam absolute)
- Session timeout portal pelanggan
- Cache jadwal sholat per project

### Fixed
- Portal loading lama (permission cache jadwal sholat)
- Dashboard portal hang (MikroTik connect timeout)
- Syntax error dashboard_fix.php
- Path include session_check 47+ file

## [1.0] - 2026-10-11
### Initial Release
- Billing ISP lengkap
- Integrasi Duitku Payment Gateway
- WhatsApp Multi-Provider
- Telegram Bot
- Speedtest Portal
- GenieACS Client (BYO-ACS)
- Kontrol ONT (reboot, SSID, perangkat)
- 30+ cron scripts
