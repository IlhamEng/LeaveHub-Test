# AI Usage Documentation

File ini mendokumentasikan penggunaan AI (Model: **Google DeepMind Antigravity / Gemini**) beserta *tools* dan *prompts* yang dijalankan untuk mengembangkan sistem backend **Leave Request Management** (Laravel 12, PostgreSQL).

---

## 🤖 AI Model Used
- **Model**: Google DeepMind Antigravity (berbasis Gemini, Claude 4.6 sonnet)
- **Role**: Software Engineer Agent (Full Autonomous Coding)

---

## 📝 Prompts & Requests History

Berikut tahapan dan instruksi (*prompts*) yang diberikan oleh **USER** kepada **AI** selama pengembangan:

### Tahap 1: Inisiasi Proyek & Requirements Dasar
> **Prompt:** "Buat aplikasi Leave Request Management. Aplikasi ini memungkinkan karyawan mengajukan permohonan cuti, dan admin menyetujui atau menolak permohonan tersebut. Menggunakan konsep monorepo Jadi API dan Frontend jadi 1 repo. Fokus pada pengembangan backend terlebih dahulu.
> Requirements: Backend Laravel 12 (REST API, min PHP 8.2), Database PostgreSQL (sertakan migration & seeder), Authentification Laravel Sanctum (token disimpan di DB, revoke saat logout), Test PHPUnit setelah fitur jadi.
> Role admin (1 akun), Leave Type (Annual Leave 12, Sick Leave 6), dan skema table *users, leave_types, leave_balances, leave_requests* disertakan tipe ENUM."

### Tahap 2: Laporan Error & Fixing Swagger Endpoint
> **Prompt:** "[Ditampilkan response CURL dari Swagger UI untuk endpoint PUT /admin/users/{id} yang mengalami Error 405 Method Not Allowed] dan [Response POST /leave-requests yang mengembalikan 422 Kuota cuti tidak ditemukan]. Banyak yang belum tervalidasi di documentation swagger, coba evaluasi lagi, perbaiki semua validation API nya jika ada yang kurang, perbaiki swaggernya."

### Tahap 3: Perbaikan Aturan Batas User
> **Prompt:** "perbaiki untuk logika user,admin tidak termasuk pada list user,jadi admin bisa membuat user max 2"

### Tahap 4: Permintaan Prompt untuk Web Frontend
> **Prompt:** "buatkan prompt untuk semua informasi program backend ini untuk membuat prompt frontend nanti di model yang akan saya pakai"

### Tahap 5: Dokumentasi Penggunaan AI (File Ini)
> **Prompt:** "setelah serangkaian yang kita hadapi,buatkan file AI_USAGE.md untuk backend. Isinya daftar prompt yang digunakan beserta nama mcp dan toolnya saat menggunakan AI"

### Tahap 6: Eksekusi Pembangunan Frontend (via Claude 4.6 sonnet)
> **Prompt:** "Saya sedang membangun aplikasi Leave Request Management (Sistem Pengajuan Cuti) dengan arsitektur monorepo (API dan Frontend dalam satu repo). Bagian Backend (API) sudah selesai dan berjalan di http://127.0.0.1:8000/api dan di folder /backend.
> Tugas kamu adalah membangun bagian Frontend untuk berinteraksi dengan API ini.
> Kebutuhan Teknologi Frontend (Tolong sesuaikan framework jika perlu):
> Framework: Vue.js 3 + TypeScript
> Styling: tailwindCSS
> HTTP Client: Composition API, axios untuk HTTP call
> Konteks & Aturan Bisnis API:
> Otentikasi: Menggunakan Laravel Sanctum (Personal Access Token). Token harus dikirim di header Authorization: Bearer {token} untuk setiap request yang terproteksi.
> Role User: Ada 2 role yaitu admin dan user.
> Kredensial Admin Default: admin@leavehub.com / password123
> Validasi Backend (Frontend wajib handle error 422):
> Admin hanya bisa membuat maksimal 2 user biasa (role = 'user'). API akan menolak user ke-3.
> User tidak bisa mengajukan cuti jika tanggalnya overlapping (bentrok) dengan cuti lain yang berstatus pending atau approved.
> User tidak bisa mengajukan cuti jika kuota sudah habis.
> Alur Status Cuti (pending, approved, rejected, canceled):
> Saat user mengajukan cuti, statusnya pending.
> User hanya bisa membatalkan (canceled) cuti yang masih pending.
> Admin bisa approve atau reject cuti yang masih pending. Menyetujui (approve) akan otomatis memotong sisa kuota cuti user di sisi backend.
> User hanya bisa menghapus (Soft Delete) history cuti yang sudah berstatus canceled atau rejected.
> Admin hanya bisa menghapus (Soft Delete) history cuti yang sudah memilki status final (approved, rejected, canceled).
> Endpoint API yang Tersedia
> Catatan: Semua response sukses bernilai HTTP 20X dan validasi error bernilai HTTP 422 Unprocessable Content.
> [Detail API Endpoint...]
> UI/UX yang Diharapkan:
> Tolong buatkan kode frontend yang memuat halaman-halaman berikut:
> Halaman Login (Redirect ke dashboard admin/user berdasarkan field role).
> Dashboard Admin:
> Menu Kelola Karyawan: Tabel daftar karyawan, tombol "Tambah Karyawan" (modal form, maks 2 user), tombol "Edit".
> Menu Persetujuan Cuti: Tabel semua pengajuan masuk. Jika status pending, tampilkan tombol aksi "Approve" dan "Reject" (dengan prompt catatan opsi). Untuk status final, sediakan tombol "Hapus".
> Dashboard User:
> Card informasi sisa kuota cuti (Tahunan dan Sakit).
> Tombol "Ajukan Cuti" (membuka modal form pilih tipe cuti, tanggal mulai, tanggal selesai, alasan). Tangani error bentrok tanggal atau kuota habis secara elegan (toast notification/alert).
> Tabel riwayat cuti. Jika status cuti masih pending, munculkan tombol "Batal". Jika statusnya canceled atau rejected, munculkan tombol "Hapus History".
> Struktur folder frontend harus mengikuti sebagai berikut
> [Struktur folder...]
> Tidak menggunakan boilerplate yang sudah include semua fitur.
> untuk referensi tampilan ada di gambar yang terlampir
> Tolong buatkan fondasi foldernya (contoh routing/pages) terlebih dahulu, dan mulai berikan kode untuk halaman Login dan setup Axios/Otentikasi HTTP Client-nya."

---

## 🛠️ AI Tools & MCP (Model Context Protocol) Used

Selama proses pengerjaan otomatis (*autonomous coding*), sistem AI saya menggunakan berbagai *internal tools* untuk menganalisis, mengekstrak, mengedit, menguji, dan memverifikasi kode secara otonom di *environment* lokal Anda:

1. **`run_command`**  
   - **Fungsi:** Menjalankan perintah terminal/bash (OS Windows Server PowerShell).
   - **Konteks Penggunaan:** Menginisialisasi Laravel (`composer create-project`), menjalankan test (`php artisan test`), melakukan migrasi database (`php artisan migrate:fresh --seed`), serta me-*generate* Swagger UI (`php artisan l5-swagger:generate`).
2. **`write_to_file`**  
   - **Fungsi:** Membuat file baru beserta foldernya dari awal (from scratch).
   - **Konteks Penggunaan:** Membuat file Migration (beserta CHECK constraints), Seeder, Model, Controllers, Form Requests, Middleware, konfigurasi PHPUnit, dan class dokumen `SwaggerDocumentation.php` (berisi *PHP 8 Attributes*).
3. **`view_file` & `list_dir`**  
   - **Fungsi:** Membaca isi kode (file system read).
   - **Konteks Penggunaan:** Menginspeksi struktur proyek Laravel, memastikan setting koneksi PostgreSQL di dalam file `.env`, mengecek setup di `bootstrap/app.php`, mengidentifikasi rute *routes/api.php*, dan menganalisis kode testing lama.
4. **`replace_file_content` & `multi_replace_file_content`**  
   - **Fungsi:** Mengedit kode dalam suatu file tanpa membuat file dari ulang (in-place modification diff).
   - **Konteks Penggunaan:** Mendaftarkan Admin Middleware `.alias()` ke dalam *App Builder*, memperbaiki kondisi logic max-2 users, mengganti nama argumen `$user` ke `$id` pada `UserController.php`, serta melakukan modifikasi CHECK Constraint pada SQLite di file Migrations secara efisien.
5. **`browser_subagent`** *(Browser Automation/Puppeteer)*
   - **Fungsi:** Menjalankan agen otomatis ("Browser Subagent") untuk membuka browser Chrome / browser system, memanipulasi *DOM*, melakukan *scrolling*, dan menekan tombol secara visual.
   - **Konteks Penggunaan:** Melakukan pengetesan visual (End-to-End browser test) pada URL `http://127.0.0.1:8000/api/documentation` (Swagger UI). Mengklik tombol otorisasi, mencoba login endpoint secara *realtime* di antarmuka web, mengisi data token, serta mencatat response API yang tampak di layar untuk memverifikasi kebenaran fungsi backend.
6. **`task_boundary` & `notify_user`**
   - **Fungsi:** Alat komunikasi mode "Agentic" dengan sistem eksekusi untuk mengorganisasi rencana, *Execution*, dan *Verification test-driven results* serta memberikan feedback pemberitahuan kepada User secara asinkron.

---
*Generated by Antigravity AI on 17 March 2026*
