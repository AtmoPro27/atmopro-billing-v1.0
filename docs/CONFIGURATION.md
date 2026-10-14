# Panduan Konfigurasi — Billing System

Setelah instalasi selesai, ikuti panduan ini untuk mengkonfigurasi sistem.

---

## 1. Konfigurasi Awal (Wajib)

### 1.1 Ganti Password Admin

Setelah login pertama:
1. Klik **Profil** (kanan atas)
2. Isi **Password Baru**
3. Klik **Simpan**

⚠️ **WAJIB** — jangan skip langkah ini!

### 1.2 Konfigurasi Perusahaan

1. Buka **Settings → General**
2. Isi:
   - **Nama Perusahaan**
   - **Tagline**
   - **No. Telepon / WA**
   - **Logo** (upload)
   - **Favicon** (upload)
3. Klik **Simpan**

---

## 2. Konfigurasi MikroTik (PPPoE)

### 2.1 Persyaratan MikroTik

- RouterOS v6.48+ atau v7
- User API dengan **full access**
- Port API (default 8728 atau 8730 untuk SSL)
- IP router **bisa diakses** dari server billing

### 2.2 Tambah Router

1. Buka **Menu Router → Tambah Router**
2. Isi:
   - **Nama Router** (misal: ROUTER_PUSAT)
   - **IP Address** (misal: 192.168.1.1)
   - **Port API** (default: 8728)
   - **Username** (user API MikroTik)
   - **Password**
3. Klik **Test Connection** — harus **sukses**
4. Klik **Simpan**

### 2.3 Setup Parameter di Billing (WAJIB)

Semua parameter MikroTik dibuat **dari billing** — bukan manual di MikroTik.

#### A. Buka Dashboard MikroTik

1. Buka **Manajemen MikroTik** di sidebar
2. Pilih **Dashboard MikroTik**
3. Pilih **Router** dari dropdown (misal: ROUTER_PUSAT)

#### B. Setting Bandwidth

1. Klik tab **Bandwidth**
2. Tambah bandwidth:
   - **Nama** (misal: 10Mbps, 20Mbps)
   - **Rate Limit** (misal: 10M/10M)
3. Klik **Simpan**

#### C. Setting IP Pool

1. Klik tab **IP Pool**
2. Tambah IP Pool:
   - **Nama** (misal: pool-pppoe)
   - **Range** (misal: 10.10.10.2-10.10.10.254)
3. Klik **Simpan**

#### D. Setting PPPoE Profile

1. Klik tab **PPPoE Profile**
2. Tambah profile:
   - **Nama** (misal: paket-10mbps)
   - **Local Address** (misal: 10.10.10.1)
   - **Remote Address** (pilih IP Pool)
   - **Rate Limit** (pilih Bandwidth)
   - **DNS** (misal: 8.8.8.8, 8.8.4.4)
3. Klik **Simpan**

#### E. Sinkronisasi ke MikroTik

Setelah semua parameter di-setup di billing:

1. Klik tombol **Sinkronisasi MikroTik**
2. Tunggu proses (1-5 menit)

Sistem akan otomatis **membuat** di MikroTik:
- ✅ **PPP Profile** untuk tiap paket
- ✅ **IP Pool**
- ✅ **Bandwidth / Queue**
- ✅ **PPP Secret** untuk tiap pelanggan

**MikroTik tidak perlu di-setup manual!** Semua dari billing.

### 2.4 Tambah Pelanggan

Setelah sync berhasil:

1. Buka **Pelanggan → Tambah Pelanggan**
2. Isi:
   - **ID Pelanggan**
   - **Nama Lengkap**
   - **Alamat**
   - **No. Telepon**
   - **Paket** (pilih dari dropdown)
   - **Router** (pilih dari dropdown)
   - **Username PPPoE** (otomatis dari ID Pelanggan)
   - **Password PPPoE**
3. Klik **Simpan**

Sistem otomatis:
- ✅ Buat **PPP Secret** di MikroTik
- ✅ Buat **Invoice** pertama
- ✅ Kirim **notifikasi WA** (kalau aktif)

### 2.5 Sinkronisasi Rutin

Setiap perubahan di billing (tambah/edit/hapus pelanggan) akan **otomatis sync** ke MikroTik — **tidak perlu** klik sinkronisasi manual.

**Klik Sinkronisasi** hanya untuk:
- Setup awal
- Setelah ubah parameter (Bandwidth, IP Pool, PPPoE Profile)
- Troubleshooting (kalau ada data tidak sinkron)

---

## 3. Konfigurasi GenieACS (Opsional)

### 3.1 Persyaratan

- GenieACS terinstall di server **terpisah** (atau lokal)
- Port **7557** (NBI) terbuka
- Port **7547** (CWMP) terbuka untuk ONT

### 3.2 Konfigurasi

1. Buka **Settings → GenieACS**
2. Isi:
   - **GenieACS URL** (misal: `http://localhost:7557`)
   - **Username** (kosongkan kalau tidak pakai auth)
   - **Password**
   - **Timeout** (default: 30)
3. Klik **Test Connection**
4. Klik **Simpan**

### 2.4 Tambah Pelanggan

Setelah sync berhasil:

1. Buka **Pelanggan → Tambah Pelanggan**
2. Isi:
   - **ID Pelanggan**
   - **Nama Lengkap**
   - **Alamat**
   - **No. Telepon**
   - **Paket** (pilih dari dropdown)
   - **Router** (pilih dari dropdown)
   - **Username PPPoE** (otomatis dari ID Pelanggan)
   - **Password PPPoE**
3. Klik **Simpan**

Sistem otomatis:
- ✅ Buat **PPP Secret** di MikroTik
- ✅ Buat **Invoice** pertama
- ✅ Kirim **notifikasi WA** (kalau aktif)

### 2.5 Sinkronisasi Rutin

Setiap perubahan di billing (tambah/edit/hapus pelanggan) akan **otomatis sync** ke MikroTik — **tidak perlu** klik sinkronisasi manual.

**Klik Sinkronisasi** hanya untuk:
- Setup awal
- Setelah ubah parameter (Bandwidth, IP Pool, PPPoE Profile)
- Troubleshooting (kalau ada data tidak sinkron)

---

## 4. Konfigurasi WhatsApp Gateway

### 4.1 Provider yang Didukung

- **Fonnte** — https://fonnte.com
- **Wablas** — https://wablas.com
- **Woowa** — https://woo-wa.com
- **Custom** (API sendiri)

### 4.2 Konfigurasi

1. Buka **Settings → WA Gateway**
2. Isi:
   - **Provider** (pilih dari dropdown)
   - **API URL**
   - **API Key** (dari dashboard provider)
   - **Nomor Pengirim**
3. Klik **Test Koneksi** — kirim pesan tes
4. Klik **Simpan**

### 4.3 Template Pesan

Buka **Settings → WA Gateway → Template**:
- **Invoice** — template tagihan
- **Pembayaran** — konfirmasi bayar
- **Isolir** — pemberitahuan isolir
- **Ticket** — balasan tiket

Gunakan placeholder:
- `{customer_name}` — nama pelanggan
- `{invoice_no}` — no invoice
- `{amount}` — jumlah
- `{due_date}` — jatuh tempo
- `{company_name}` — nama perusahaan

---

## 5. Konfigurasi Telegram

### 5.1 Buat Bot

1. Chat **@BotFather** di Telegram
2. Ketik `/newbot`
3. Ikuti instruksi — dapat **token**
4. Simpan token

### 5.2 Konfigurasi

1. Buka **Settings → Telegram**
2. Isi:
   - **Bot Token**
   - **Chat ID Admin** (untuk notifikasi)
3. Klik **Test**
4. Klik **Simpan**

---

## 6. Konfigurasi Payment Gateway (Duitku)

### 6.1 Daftar Duitku

1. Buka https://duitku.com
2. Daftar akun
3. Ambil **Merchant Code** + **API Key** dari dashboard

### 6.2 Konfigurasi

1. Buka **Settings → Payment Gateway**
2. Pilih **Duitku**
3. Isi:
   - **Merchant Code**
   - **API Key**
   - **Mode** — Sandbox (testing) / Production (live)
4. Set **Callback URL** di dashboard Duitku:https://domain-anda.com/api/duitku_callback.php5. Klik **Test Connection**

---


---

## 7. Setup Cron (WAJIB)

Buka `crontab -e`:
crontab -e

text

Copy-paste isi dari `install/cron.txt` — **ganti**:
- `/path/to/billing` → **path install Anda**
- `user_billing` → **user PHP-FPM Anda**
- `php8.3` → **versi PHP Anda**

Simpan.

Verifikasi:
crontab -l

text

---

## 8. Konfigurasi Nginx

Contoh config Nginx:
server {
listen 80;
listen 443 ssl http2;
server_name domain-anda.com;

root /var/www/domain-anda;
index index.php index.html;

ssl_certificate /etc/letsencrypt/live/domain-anda.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/domain-anda.com/privkey.pem;

location = /.env {
deny all;
return 403;
}

location ~ /. {
deny all;
return 403;
}

location ^~ /config/ {
deny all;
return 403;
}

location ~* ^/uploads/.*.php$ {
deny all;
return 403;
}

location / {
try_files 
u
r
i
uriuri/ /index.php?$query_string;
}

location ~ .php$ {
include snippets/fastcgi-php.conf;
fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
}
}

text

---

## 9. Keamanan (Opsional tapi Disarankan)

### 9.1 PHP-FPM Pool Terpisah

Buat pool khusus di `/etc/php/8.3/fpm/pool.d/billing.conf`:
[billing]
user = billing_user
group = billing_user
listen = /run/php/php8.3-fpm-billing.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

php_admin_value[open_basedir] = /var/www/domain-anda:/var/lib/php/sessions/billing:/tmp
php_admin_value[session.save_path] = /var/lib/php/sessions/billing
php_admin_value[session.gc_maxlifetime] = 1800
php_admin_value[session.gc_probability] = 1
php_admin_value[session.gc_divisor] = 100
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen

text

### 9.2 Firewall
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 22/tcp
ufw enable

text

### 9.3 Backup Otomatis

Buat cron backup:
0 2 * * * mysqldump -u user_database -p'password' nama_database | gzip > /root/backup/billing_$(date +%Y%m%d).sql.gz
0 3 * * * find /root/backup -name "billing_*.sql.gz" -mtime +30 -delete

text

---

## 10. Troubleshooting

| Masalah | Solusi |
|---|---|
| **Login gagal** | Cek `.env` — DB_USER, DB_PASS |
| **Halaman blank** | Cek error log: `tail /var/log/nginx/error.log` |
| **Cron tidak jalan** | Cek log: `tail /var/log/billing_*.log` |
| **MikroTik tidak connect** | Cek IP, port, user API |
| **Sync MikroTik gagal** | Cek user API punya akses full, cek log |
| **GenieACS tidak connect** | Cek port 7557 & URL |
| **WA tidak terkirim** | Cek API key & kuota |
| **Session timeout** | Ubah di `includes/auth_check.php` — `IDLE_TIMEOUT` |

---

## 11. Support

- **WA**: 082298411981
- **Telegram**: @mbahatmo
- **Email**: atmopro3@gmail.com

---

**AtmoPro** — Billing System untuk ISP
