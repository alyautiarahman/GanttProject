# Sistem Manajemen Proyek Gantt Chart (PHP Native)

Sistem aplikasi web manajemen proyek berbasis PHP Native dengan visualisasi **Gantt Chart Interaktif**, pemantauan progres tugas, relasi dependensi antartugas (*Finish-to-Start*), penanda *Milestone*, dan pengelolaan anggaran proyek.

---
## Tampilan Antarmuka (UI)
<img width="1917" height="970" alt="Screenshot 2026-10-22 021911" src="https://github.com/user-attachments/assets/261a7546-08e5-4dca-8a96-92ece9f23f5a" />
<img width="1917" height="967" alt="Screenshot 2026-10-22 022059" src="https://github.com/user-attachments/assets/b357f6e1-d417-4e7a-956c-fedd1f6f0cab" />

## 🚀 Fitur Utama

1. **Gantt Chart Interaktif Modern**:
   - Visualisasi timeline dengan skala waktu **Harian**, **Mingguan**, dan **Bulanan**.
   - **Drag & Drop**: Geser task bar langsung di timeline untuk memundurkan/memajukan tanggal mulai dan selesai secara *real-time*.
   - **Interactive Resizing**: Tarik pegangan kiri/kanan task bar untuk mengubah durasi tugas.
   - **Dependency Linking (SVG Path)**: Visualisasi garis panah ketergantungan antar-tugas (*predecessor* -> *successor*).
   - **Milestone Marker**: Penanda berbentuk diamond khusus untuk capaian kritis proyek.
   - Indikator hari ini (*Today Indicator*) dan arsiran akhir pekan (*Weekend styling*).

2. **Manajemen Proyek Lengkap (CRUD)**:
   - Buat dan kelola multi-proyek sekaligus.
   - Dashboard metrik: Total tugas, progress rata-rata terhitung otomatis, dan estimasi anggaran (IDR).
   - Status proyek (*Planning*, *In Progress*, *Completed*, *On Hold*).

3. **Manajemen Tugas & Aktivitas (Task Management)**:
   - Form modal lengkap: Judul, deskripsi, tanggal mulai & selesai, slider progress (0-100%), prioritas (*Urgent*, *High*, *Medium*, *Low*), penanggung jawab (PIC), dan palet warna kustom.
   - Dukungan ketergantungan jamak (*multiple dependencies*).
   - Sinkronisasi instan saat tugas diperbarui, ditambah, atau dihapus.

4. **Arsitektur Ringan & Fleksibel**:
   - **PHP Native (PDO)**: Arsitektur bersih tanpa framework berat.
   - **Zero Configuration Database**: Menggunakan SQLite bawaan PHP secara default (langsung jalan tanpa perlu install/impor database manual).
   - **MySQL/MariaDB Ready**: Opsi beralih ke MySQL hanya dengan mengganti konstanta di file konfigurasi.
   - **RESTful JSON API**: Pemisahan modul endpoint backend (`api/projects.php` dan `api/tasks.php`) yang rapi.

---

## 📂 Struktur Direktori

```text
ganttProject/
├── api/
│   ├── projects.php          # REST API CRUD Proyek (JSON)
│   └── tasks.php             # REST API CRUD Tugas & Quick Update Drag (JSON)
├── assets/
│   ├── css/
│   │   └── style.css         # Styling modern clean responsif & timeline Gantt
│   └── js/
│       └── gantt.js          # Core engine interaktif Gantt Chart & SVG dependencies
├── config/
│   └── database.php          # Konfigurasi PDO SQLite & MySQL
├── database/
│   ├── migrate.php           # Auto migrasi skema tabel & data seeder
│   └── project_management.sqlite # Database SQLite lokal otomatis terbuat
├── includes/
│   ├── Project.php           # Model Proyek & kalkulasi agregat progress
│   └── Task.php              # Model Tugas & relasi dependencies
├── index.php                 # Tampilan utama Dashboard & Gantt Chart
└── README.md                 # Dokumentasi proyek
```

---

## 🛠️ Cara Menjalankan Aplikasi

Pastikan PHP versi 8.x atau 7.4+ sudah terpasang.

### Opsi 1: Menjalankan dengan PHP Built-in Server (Paling Cepat)
Buka terminal / command prompt di folder proyek ini, lalu jalankan:

```bash
php -S localhost:8000
```

Buka browser dan akses:
👉 **[http://localhost:8000](http://localhost:8000)**

Database SQLite dan data contoh (Proyek ERP & Redesign Website) akan di-generate otomatis saat pertama kali dibuka!

---

### Opsi 2: Menjalankan via Apache / XAMPP
1. Salin atau arahkan folder ini ke `htdocs`:
   ```text
   C:\xampp\htdocs\ganttProject
   ```
2. Nyalakan modul Apache di XAMPP Control Panel.
3. Buka browser ke:
   👉 `http://localhost/ganttProject/`

---

### Opsi Konfigurasi MySQL (Opsional)
Jika ingin menggunakan MySQL/MariaDB alih-alih SQLite:
1. Buka file `config/database.php`.
2. Ubah `define('DB_DRIVER', 'sqlite');` menjadi `define('DB_DRIVER', 'mysql');`.
3. Sesuaikan `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.
4. Buat database kosong bernama `gantt_pm_db`, sistem akan otomatis melakukan migrasi tabel saat aplikasi pertama kali dijalankan.

---

## 💡 Cara Penggunaan

1. **Pilih Proyek**: Klik salah satu proyek di panel sidebar kiri untuk memuat timeline tugasnya.
2. **Tambah Proyek Baru**: Klik tombol `Proyek Baru` di pojok kanan atas navbar.
3. **Tambah Tugas**: Klik tombol `Tambah Tugas` di navbar, masukkan nama, durasi, prioritas, PIC, dan tentukan dependensinya jika ada.
4. **Geser Timeline (Drag & Drop)**: Klik dan tahan batang tugas di timeline Gantt lalu geser ke kiri atau ke kanan untuk memindahkan jadwal tanggal. Tanggal akan otomatis tersimpan ke backend.
5. **Ubah Durasi (Resize)**: Arahkan kursor ke ujung kiri atau kanan batang tugas hingga kursor berubah bentuk, lalu tarik untuk memperpanjang/memperpendek durasi.
6. **Edit / Hapus Tugas**: Klik dua kali pada batang tugas di grafik atau klik baris tugas di tabel kiri.
