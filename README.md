# Billing System - Internet Service Provider

Sistem billing ISP dengan fitur:
- Manajemen pelanggan PPPoE
- Invoice & pembayaran
- Integrasi MikroTik & GenieACS
- Notifikasi WhatsApp & Telegram
- Portal pelanggan (PWA)

## Requirement
- PHP 8.2+ (pdo_mysql, curl, json, mbstring, gd, zip)
- MariaDB 10.5+ / MySQL 8.0+
- Nginx / Apache
- MikroTik RouterOS (opsional)
- GenieACS (opsional)

## Instalasi

### Cara Cepat — Web Installer (Rekomendasi)

1. Extract source ke `/var/www/domain-anda`
2. Buat database & user di MySQL/MariaDB
3. Konfigurasi Nginx/Apache
4. Buka **`https://domain-anda.com/install/`** di browser
5. Isi form (DB Host, DB Name, DB User, DB Pass, Base URL)
6. Klik **Install Sekarang** — installer otomatis:
   - Tes koneksi database
   - Import `install/database.sql`
   - Membuat file `.env`
7. **Hapus folder `/install/`** (WAJIB, untuk keamanan)
8. Set permission: `chmod 640 .env && chmod -R 755 uploads/ cache/ logs/`
9. Setup cron (copy isi `install/cron.txt` ke crontab)
10. Login: `admin` / `admin123`

### Cara Manual — Edit `.env`

Jika tidak bisa akses browser installer:

1. Extract source ke `/var/www/domain-anda`
2. Buat database & user
3. Import `install/database.sql`
4. `cp install/.env.example .env` lalu edit sesuai server
5. Set permission, konfigurasi nginx, setup cron
6. Login: `admin` / `admin123`

⚠️ **GANTI PASSWORD SETELAH LOGIN PERTAMA!**

📖 **Panduan lengkap**: lihat `PANDUAN_INSTALASI.txt`

## Login Default

**Admin:**
- Username: `admin`
- Password: `admin123`

**Pelanggan demo:**
- ID: `CUST001` / `CUST002` / `CUST003`
- Password: `123456`

## Konfigurasi

- **MikroTik:** Menu Router → Tambah Router
- **GenieACS:** Settings → GenieACS
- **WhatsApp:** Settings → WA Gateway
- **Telegram:** Settings → Telegram

## Support

WA : 6282298411981
Tele : @mbahatmo
