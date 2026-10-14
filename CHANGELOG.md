# Changelog

## [1.1] - 2026-10-12
### Added
- Session timeout (auto logout setelah 30 menit idle, 8 jam absolute)
- Session timeout portal pelanggan
- Cache jadwal sholat per project (tidak konflik dengan project lain)

### Fixed
- Portal loading lama (permission cache jadwal sholat)
- Dashboard portal hang (MikroTik connect timeout)
- Syntax error di dashboard_fix.php
- Typo `$pdo = require_once` di beberapa file
- Path include session_check di 47+ file
- Permission session file

### Improved
- Keamanan: session regenerate id
- Performance: dashboard portal cepat (< 1 detik)
- Dokumentasi: panduan instalasi lengkap

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
