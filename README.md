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

1. Copy file ke `/var/www/domain-anda`
2. Buat database & user
3. Import `database.sql`
4. Copy `.env.example` ke `.env`, sesuaikan
5. Set permission: `chown -R www-data:www-data .`
6. Konfigurasi nginx (lihat contoh di folder `docs/`)
7. Setup cron (lihat `cron/README.md`)
8. Login: `admin` / `admin123`

⚠️ **GANTI PASSWORD SETELAH LOGIN PERTAMA!**

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

[Kontak Anda]
