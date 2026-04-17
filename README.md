# NitipGo API

REST API backend untuk platform **NitipGo** — layanan titip barang berbasis perjalanan (peer-to-peer jastip) yang menghubungkan *traveler* dengan *customer*.

---

## Deskripsi Proyek

NitipGo adalah platform yang memungkinkan customer menitipkan pembelian barang kepada traveler yang sedang melakukan perjalanan. Traveler membuka trip, customer memesan titipan, traveler membawa barang tersebut sesuai rute perjalanannya.

---

## Tech Stack

| Komponen | Teknologi |
|---|---|
| Framework | Laravel 11 |
| PHP | >= 8.2 |
| Autentikasi | Laravel Sanctum + Laravel Socialite (Google OAuth) |
| Email | Resend |
| Pembayaran | Midtrans (Snap) |
| API Docs | Scramble (Dedoc) |
| Geolokasi | stevebauman/location |
| Database | MySQL / SQLite |

---

## Fitur Utama

### Autentikasi
- Register & login customer/traveler
- Login dengan Google OAuth
- Refresh token
- Lupa & reset password
- Logout single/all device
- Deteksi login perangkat baru (notifikasi email)

### Role
Sistem memiliki tiga role: **Admin**, **Traveler**, dan **Customer**.

#### Admin
- Manajemen user & traveler (CRUD + update status)
- Approval/reject pendaftaran user & traveler
- Konfigurasi system settings
- Lihat rute trip yang tersedia
- Manajemen booster (paket iklan traveler)
- Manajemen iklan (advertisement) — approve/reject
- Manajemen dispute/laporan
- Manajemen tiket bantuan (FAQ & help tickets)
- Manajemen penarikan saldo platform
- Notifikasi in-app

#### Traveler
- Manajemen profil & foto
- Manajemen akun payout (bank/e-wallet)
- Manajemen trip (buka, update status, hapus)
- Live tracking lokasi perjalanan
- Manajemen order (terima/tolak/update status/update harga)
- Manajemen wallet & penarikan saldo
- Pembelian booster trip via Midtrans
- Notifikasi in-app

#### Customer
- Manajemen profil & foto
- Buat & lihat order titipan
- Upload bukti pembayaran via Midtrans
- Batalkan order
- Lihat trip yang tersedia & detail trip
- Pantau tracking perjalanan traveler
- Rating & ulasan traveler
- Laporan/dispute order
- Tiket bantuan
- Notifikasi in-app

---

## Instalasi

### Prasyarat
- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL atau SQLite

### Langkah Instalasi

```bash
# 1. Clone repositori
git clone <https://github.com/Razzkoo/NitipGo-API.git>
cd NitipGo-API

# 2. Install dependensi PHP
composer install

# 3. Install dependensi Node
npm install

# 4. Salin file environment
cp .env.example .env

# 5. Generate application key
php artisan key:generate

# 6. Konfigurasi .env (lihat bagian API Keys di bawah)

# 7. Jalankan migrasi
php artisan migrate

# 8. (Opsional) Jalankan seeder
php artisan db:seed
```

> **Windows:** Gunakan `copy .env.example .env` sebagai pengganti `cp .env.example .env`

### Menjalankan Server

```bash
# Development (server + queue worker sekaligus)
composer dev

# Atau manual terpisah
php artisan serve
php artisan queue:work
```

---

## Konfigurasi API Keys

Proyek ini menggunakan beberapa layanan eksternal yang memerlukan API key. Semua key dikonfigurasi melalui file `.env`.

### 1. Google OAuth (untuk login dengan Google)

Buat project di [Google Cloud Console](https://console.cloud.google.com/), aktifkan **Google+ API** / **Google Identity**, lalu buat OAuth 2.0 Client ID.

```env
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

> Tambahkan `GOOGLE_REDIRECT_URI` ke daftar **Authorized redirect URIs** di Google Cloud Console.

---

### 2. Resend (untuk pengiriman email)

Daftar di [resend.com](https://resend.com), buat API key, lalu verifikasi domain pengirim.

```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="NitipGo"

RESEND_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxx
```

> Tanpa verifikasi domain, email hanya bisa dikirim ke alamat yang sudah didaftarkan di Resend (mode sandbox).

---

### 3. Midtrans (untuk pembayaran order & booster)

Daftar di [midtrans.com](https://midtrans.com), masuk ke **Dashboard > Settings > Access Keys**.

```env
MIDTRANS_MERCHANT_ID=xxxxxxxx
MIDTRANS_SERVER_KEY=Mid-server-xxxxxxxxxxxxxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=Mid-client-xxxxxxxxxxxxxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_SANITIZED=true
MIDTRANS_IS_3DS=true
```

> - Prefix `SB-` berarti **Sandbox** (mode testing). Untuk production, gunakan key tanpa prefix `SB-` dan set `MIDTRANS_IS_PRODUCTION=true`.
> - Agar notifikasi pembayaran bekerja, daftarkan URL berikut di dashboard Midtrans bagian **Settings > Payment Notification URL**:
>   ```
>   https://yourdomain.com/api/midtrans/notification
>   https://yourdomain.com/api/midtrans/booster/notification
>   ```

---

### Contoh Lengkap `.env`

```env
APP_NAME=NitipGo
APP_ENV=local
APP_KEY=                          # diisi otomatis oleh: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta

FRONTEND_URL=http://localhost:5173      # frontend url: access vite from NitipGo-web

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nitipgo
DB_USERNAME=root
DB_PASSWORD=

# Queue
QUEUE_CONNECTION=database

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback

# Email (Resend)
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="NitipGo"
RESEND_KEY=

# Midtrans Payment Gateway
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_SANITIZED=true
MIDTRANS_IS_3DS=true
```

---

## Struktur API

Base URL: `/api`

### Auth
| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/auth/google` | Redirect ke Google OAuth |
| GET | `/auth/google/callback` | Callback Google OAuth |
| POST | `/auth/google/token` | Login dengan Google token |
| POST | `/auth/register-customer` | Daftar sebagai customer |
| POST | `/auth/register-traveler` | Daftar sebagai traveler |
| POST | `/auth/login` | Login (semua role) |
| POST | `/auth/refresh-token` | Refresh access token |
| POST | `/auth/forgot-password` | Kirim email reset password |
| POST | `/auth/reset-password` | Reset password |
| GET | `/auth/me` | Data user yang login |
| POST | `/auth/logout` | Logout device ini |
| POST | `/auth/logout-all` | Logout semua device |

### Admin (`/admin/*`)
| Method | Endpoint | Deskripsi |
|---|---|---|
| GET/PUT/POST/DELETE | `/admin/profile` | Manajemen profil admin |
| GET/PUT/PATCH | `/admin/settings` | System settings |
| GET/POST/GET/PUT/PATCH/DELETE | `/admin/users/{id?}` | Manajemen user |
| GET/POST/PATCH/DELETE | `/admin/user-requests/{id?}` | Approval pendaftaran user |
| GET/POST/GET/PUT/PATCH/DELETE | `/admin/travelers/{id?}` | Manajemen traveler |
| GET/POST/PATCH/DELETE | `/admin/traveler-requests/{id?}` | Approval pendaftaran traveler |
| GET | `/admin/routes` | Daftar rute trip |
| GET/POST/PUT/PATCH/DELETE | `/admin/boosters/{id?}` | Manajemen paket booster |
| GET/PATCH | `/admin/boosters/monitoring` | Monitoring & status booster traveler |
| GET | `/admin/wallet/booster` | Wallet booster platform |
| GET | `/admin/wallet/advertisements` | Wallet iklan platform |
| GET/POST/GET/PATCH/DELETE | `/admin/platform-withdraw/{id?}` | Penarikan saldo platform |
| GET/POST/PATCH/DELETE | `/admin/disputes/{id?}` | Manajemen laporan/dispute |
| GET/POST/PATCH/DELETE | `/admin/help/tickets/{id?}` | Manajemen tiket bantuan |
| GET/POST/PUT/DELETE | `/admin/help/faqs/{id?}` | Manajemen FAQ |
| GET/PATCH/DELETE | `/admin/advertisements/{id?}` | Manajemen iklan |
| GET/PATCH | `/admin/ratings` | Manajemen rating |
| GET | `/admin/transactions` | Daftar transaksi |
| GET/PATCH | `/admin/notifications` | Notifikasi in-app admin |

### Traveler (`/traveler/*`)
| Method | Endpoint | Deskripsi |
|---|---|---|
| GET/PUT/POST/DELETE | `/traveler/profile` | Manajemen profil |
| GET | `/traveler/dashboard` | Dashboard traveler |
| GET/POST/DELETE/PATCH | `/traveler/payout-accounts/{id?}` | Akun payout |
| GET/POST/GET/PATCH/DELETE | `/traveler/trips/{id?}` | Manajemen trip |
| POST/POST/POST/GET | `/traveler/trips/{tripId}/tracking/*` | Live tracking |
| GET/GET/PATCH/PATCH/PATCH/POST | `/traveler/orders/{id?}` | Manajemen order |
| GET/POST/GET | `/traveler/wallet` | Wallet & riwayat |
| POST/GET | `/traveler/wallet/withdraw` | Penarikan saldo |
| GET/POST/GET/GET | `/traveler/boosters/*` | Manajemen booster |
| GET/POST | `/traveler/disputes/{id?}` | Dispute/laporan |
| GET/PATCH/DELETE | `/notifications` | Notifikasi in-app |

### Customer (`/customer/*`)
| Method | Endpoint | Deskripsi |
|---|---|---|
| GET/PUT/POST/DELETE | `/customer/profile` | Manajemen profil |
| POST/GET/GET/PATCH | `/customer/orders/{id?}` | Manajemen order |
| POST/GET | `/customer/orders/{id}/pay` | Pembayaran via Midtrans |
| POST | `/customer/orders/{id}/rating` | Beri rating traveler |
| POST/GET | `/customer/orders/{id}/report` | Laporan/dispute |
| POST/GET | `/customer/help/tickets` | Tiket bantuan |
| GET/PATCH/DELETE | `/notifications` | Notifikasi in-app |

### Public
| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/settings/public` | Pengaturan sistem publik |
| GET | `/faqs` | Daftar FAQ |
| POST | `/user-requests` | Ajukan pendaftaran user |
| POST | `/traveler-requests` | Ajukan pendaftaran traveler |
| GET | `/trips/available` | Trip yang tersedia |
| GET | `/trips/{id}/public` | Detail trip publik |
| GET | `/advertisements/live` | Iklan aktif |
| POST | `/midtrans/notification` | Webhook notifikasi Midtrans |

---

## Struktur Database (Tabel Utama)

- `users` — data akun customer & admin
- `travelers` — data akun traveler
- `trips` — trip yang dibuat traveler
- `trip_trackings` — riwayat lokasi perjalanan
- `order_processes` — proses order titipan
- `transactions` — transaksi keuangan
- `payments` — bukti pembayaran
- `payment_boosters` — pembayaran booster
- `payout_accounts` — akun payout traveler
- `withdraw_requests` — permintaan penarikan saldo traveler
- `platform_withdraw_requests` — penarikan saldo platform oleh admin
- `user_requests` / `traveler_requests` — pendaftaran menunggu approval
- `ratings` — penilaian
- `reports` — laporan/dispute transaksi
- `boosters` — paket booster trip
- `traveler_boosters` — booster aktif milik traveler
- `advertisements` / `advertisement_payments` — iklan & pembayarannya
- `help_tickets` — tiket bantuan customer
- `notifications` — notifikasi in-app
- `system_settings` — konfigurasi sistem
- `login_activities` — log aktivitas login

---

## Dokumentasi API

Setelah server berjalan, dokumentasi API otomatis tersedia di:

```
http://localhost:8000/docs/api
```

(Di-generate oleh [Scramble](https://scramble.dedoc.co/))

---

## Lisensi

Proyek ini dibuat untuk keperluan PKL (Praktik Kerja Lapangan).
