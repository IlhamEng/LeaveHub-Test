# Leave Request Management (LeaveHub)

Aplikasi **Sistem Pengajuan Cuti (LeaveHub)** menggunakan arsitektur monorepo yang memisahkan sisi Backend (REST API) dan Frontend (Single Page Application).

- **Backend:** Laravel 12 + PostgreSQL + Sanctum
- **Frontend:** Vue.js 3 + TypeScript + TailwindCSS + Pinia + Vite

---

## 🚀 Prasyarat Sistem

Pastikan sistem Anda sudah menginstal aplikasi berikut:
1. **PHP** (Minimal versi 8.2) dan Composer.
2. **PostgreSQL** (Service database berjalan lokal atau remote).
3. **Node.js** (Minimal versi 18.x) dan NPM.
4. Pastikan *Extension PDO PostgreSQL* (`pdo_pgsql`) sudah aktif di `php.ini` Anda.

---

## 📂 Struktur Proyek

```text
LeaveHub/
├── backend/         # Kode sumber REST API Laravel
├── frontend/        # Kode sumber SPA Vue.js 3
├── AI_USAGE.md      # Riwayat prompt & penggunaan AI
└── README.md        # Panduan aplikasi ini (Anda membaca ini saat ini)
```

---

## 🛠️ 1. Setup & Instalasi

### Bagian Backend (API)
Ikuti langkah berikut untuk mengonfigurasi backend server:

1. Buka terminal dan masuk ke folder backend:
   ```bash
   cd backend
   ```
2. Instal dependensi PHP menggunakan Composer:
   ```bash
   composer install
   ```
3. Salin file environment:
   ```bash
   cp .env.example .env
   ```
4. Buka file `.env` dan konfigurasikan koneksi PostgreSQL Anda:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=nama_database_anda
   DB_USERNAME=username_anda
   DB_PASSWORD=password_anda
   ```
5. *Generate* App Key Laravel:
   ```bash
   php artisan key:generate
   ```
6. Jalankan Migrasi dan Database Seeder:
   *(Perintah ini otomatis membangun tabel dan mengisi data Admin Default & Tipe Cuti)*
   ```bash
   php artisan migrate:fresh --seed
   ```

### Bagian Frontend (Web Vue.js)
Ikuti langkah berikut untuk mengonfigurasi frontend client:

1. Buka terminal baru dan masuk ke folder frontend:
   ```bash
   cd frontend
   ```
2. Instal dependensi Node.js:
   ```bash
   npm install
   ```

---

## 💻 2. Cara Menjalankan Aplikasi

Untuk menjalankan aplikasi ini secara fungsional di lokal Anda, Anda **harus menjalankan Backend dan Frontend secara bersamaan** di dua terminal yang berbeda.

### Menjalankan Backend
Di terminal pertama (di dalam folder `backend/`):
```bash
php artisan serve
```
Backend API Anda sekarang berjalan di: `http://127.0.0.1:8000`.

*(Opsional: Anda bisa melihat Dokumentasi Swagger API di `http://127.0.0.1:8000/api/documentation`)*

### Menjalankan Frontend
Di terminal kedua (di dalam folder `frontend/`):
```bash
npm run dev
```
Frontend Vue Anda sekarang berjalan. Klik tautan lokal yang muncul (biasanya `http://localhost:3000`).

💡 **Informasi Login Default:**
- **Email:** `admin@leavehub.com`
- **Password:** `password123`

---

## 🧪 3. Cara Menjalankan Testing

Proyek ini dilengkapi dengan Unit Test dan Feature Test di semua layernya untuk menjamin sistem berjalan dengan baik berdasarkan standar bisnis logic.

### Testing Backend (PHPUnit)
Backend menggunakan **PHPUnit** dengan database SQLite in-memory untuk uji coba, jadi tidak akan merusak data PostgreSQL Anda. Testing memvalidasi Authentication, limitasi Max 2 Users, validasi kuota cuti, dan overlap cuti.

1. Buka terminal di folder `backend/`.
2. Jalankan perintah:
   ```bash
   php artisan test
   ```
   *(Semua 37 Test Cases harus berwarna 🟢 PASS)*.

### Testing Frontend (Vitest)
Frontend menggunakan **Vitest** dan **Vue Test Utils** untuk memvalidasi interaksi store Pinia (Login/Logout Auth) dan komunikasi Service API/Axios.

1. Buka terminal di folder `frontend/`.
2. Jalankan perintah:
   ```bash
   npm test
   ```
   *(Ini otomatis menjalankan 12 Test Cases untuk komponen `auth.store` dan `admin.service.ts`)*.
