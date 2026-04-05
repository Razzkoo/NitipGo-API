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

#### Traveler
- Manajemen profil & foto
- Manajemen akun payout (bank/e-wallet)
- Manajemen trip (buka, update status, hapus)
- Live tracking lokasi perjalanan
- Manajemen order (terima/tolak/update status/update harga)

#### Customer
- Manajemen profil & foto
- Buat & lihat order titipan
- Upload bukti pembayaran
- Batalkan order
- Lihat trip yang tersedia & detail trip
- Pantau tracking perjalanan traveler

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
git clone <repo-url>
cd NitipGo-API

# 2. Install dependensi PHP
composer install

# 3. Install dependensi Node
npm install

# 4. Salin file environment
cp .env.example .env

# 5. Generate application key
php artisan key:generate

# 6. Konfigurasi database di .env, lalu jalankan migrasi
php artisan migrate

# 7. (Opsional) Jalankan seeder
php artisan db:seed
```

### Konfigurasi .env Penting

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nitipgo
DB_USERNAME=root
DB_PASSWORD=

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=

# Email (Resend)
RESEND_API_KEY=
MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

### Menjalankan Server

```bash
# Development (server + queue worker)
composer dev

# Atau manual
php artisan serve
php artisan queue:work
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

### Traveler (`/traveler/*`)
| Method | Endpoint | Deskripsi |
|---|---|---|
| GET/PUT/POST/DELETE | `/traveler/profile` | Manajemen profil |
| GET/POST/DELETE/PATCH | `/traveler/payout-accounts` | Akun payout |
| GET/POST/GET/PATCH/DELETE | `/traveler/trips/{id?}` | Manajemen trip |
| POST/POST/POST/GET | `/traveler/trips/{tripId}/tracking/*` | Live tracking |
| GET/GET/PATCH/PATCH/PATCH/PATCH | `/traveler/orders/{id?}` | Manajemen order |

### Customer (`/customer/*`)
| Method | Endpoint | Deskripsi |
|---|---|---|
| GET/PUT/POST/DELETE | `/customer/profile` | Manajemen profil |
| POST/GET/GET/POST/PATCH | `/customer/orders/{id?}` | Manajemen order |
| GET | `/trips/available` | Trip yang tersedia |
| GET | `/trips/{id}/detail` | Detail trip |
| GET | `/trips/{tripId}/tracking` | Pantau tracking |

### Public
| Method | Endpoint | Deskripsi |
|---|---|---|
| GET | `/settings/public` | Pengaturan sistem publik |
| POST | `/user-requests` | Ajukan pendaftaran user |
| POST | `/traveler-requests` | Ajukan pendaftaran traveler |

---

## Struktur Database (Tabel Utama)

- `users` — data akun customer & admin
- `travelers` — data akun traveler
- `trips` — trip yang dibuat traveler
- `trip_trackings` — riwayat lokasi perjalanan
- `order_processes` — proses order titipan
- `transactions` — transaksi keuangan
- `payments` — bukti pembayaran
- `payout_accounts` — akun payout traveler
- `withdraw_requests` — permintaan penarikan saldo
- `user_requests` / `traveler_requests` — pendaftaran menunggu approval
- `ratings` — penilaian
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
