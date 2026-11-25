# Sistem LMS (Learning Management System)

Aplikasi web berbasis PHP native dan database relasional terpisah per peran (`lms_mahasiswa`, `lms_dosen`, dan `lms_admin`) untuk pengelolaan fakultas, prodi, kelas, tugas/materi, pengumpulan tugas, penilaian, serta profil pengguna.

---

## Arsitektur & Keamanan Dasar

### 1. Multi-Database per Peran
Sistem memisahkan data domain ke beberapa database:
* **`lms_mahasiswa`**: Menyimpan data mahasiswa (`mahasiswa`) serta riwayat pengumpulan tugas (`pengumpulan`)[cite: 6, 8, 11].
* **`lms_dosen`**: Menyimpan data dosen (`dosen`), tugas (`tugas`), materi (`materi`), serta nilai mahasiswa (`nilai`)[cite: 8, 11, 14].
* **`lms_admin`**: Menyimpan data struktur akademik (`fakultas`, `prodi`, `kelas`) serta relasi pendaftaran mahasiswa ke kelas (`kelas_mhs`)[cite: 6, 8, 14].

### 2. Perlindungan CSRF (`csrf()` & `chk()`)
* Setiap form `POST` menyertakan token CSRF melalui `<?php echo csrf(); ?>` yang menghasilkan input tersembunyi `name="t"`.
* Di sisi server pada request `POST`, fungsi `chk()` memvalidasi kecocokan token `$_POST['t']` dengan token yang tersimpan di `$_SESSION['t']`.
* **Catatan penting:** Nama input form lain sebaiknya tidak menggunakan `name="t"` (gunakan nama spesifik seperti `name="tugas_id"`) agar tidak menimpa token CSRF saat form dikirim[cite: 8, 11].

---

## Penjelasan Kode & Logika per File

### 1. Profil Pengguna (`profile.php`)
* **Akses & Sesi:** Mengecek `$_SESSION['u']`; jika kosong, pengguna diarahkan ke halaman login (`index.php`)[cite: 2].
* **Resolusi Target Database & Kolom:** Menentukan database (`lms_mahasiswa`, `lms_dosen`, atau `lms_admin`) berdasarkan `$_SESSION['u']['role']`, lalu memeriksa kolom tabel yang tersedia (`SHOW COLUMNS`) untuk menyesuaikan nama kolom nama, email, nomor HP, password, dan identifier (`WHERE`).
* **Logika Penyimpanan (`POST`):**
  1. Validasi CSRF (`chk()`) dan memastikan `nama` serta `email` tidak kosong.
  2. Melakukan `UPDATE` ke tabel target sesuai peran (password di-hash menggunakan `password_hash(..., PASSWORD_DEFAULT)` jika diisi).
  3. Memperbarui data di `$_SESSION['u']` agar perubahan langsung terlihat di halaman profil maupun layout/sidebar tanpa harus login ulang.

### 2. Dashboard Mahasiswa (`mahasiswa/dashboard.php`)
* **Pembatasan Role:** Menggunakan `need('mahasiswa')` untuk membatasi akses hanya bagi mahasiswa[cite: 6].
* **Pengurutan & Rekap Tugas:**
  * Mendukung pengurutan daftar tugas berdasarkan `tanggal` (`dl`) atau `matkul` (`k.nama, dl`) melalui parameter `GET` (`?s=`)[cite: 6].
  * Mengambil tugas dari `lms_dosen.tugas` yang berelasi dengan kelas yang diikuti mahasiswa (`lms_admin.kelas_mhs`), sekaligus mengecek status pengumpulan pada `lms_mahasiswa.pengumpulan`[cite: 6].
* **Penanda Kalender (`$marks`):** Memetakan tanggal deadline pada bulan berjalan agar tampil di komponen kalender (`cal($marks)`)[cite: 6].

### 3. Tugas & Materi Mahasiswa (`mahasiswa/tugas.php`)
* **Pembatasan Role:** Menggunakan `need('mahasiswa')`[cite: 8].
* **Logika Pengumpulan Tugas (`POST`):**
  1. Menjalankan `chk()` dan mengambil ID tugas (`tugas_id`) serta catatan (`cat`)[cite: 8].
  2. Memvalidasi bahwa tugas ada dan mahasiswa terdaftar di kelas tugas tersebut (`JOIN lms_admin.kelas_mhs`)[cite: 8].
  3. Mengecek batas waktu (`strtotime($tugas['dl']) < time()`); jika lewat, pengumpulan ditolak[cite: 8].
  4. Mengunggah file melalui fungsi `upload(...)` lalu menyimpan/memperbarui data di `lms_mahasiswa.pengumpulan` menggunakan `INSERT ... ON DUPLICATE KEY UPDATE`[cite: 8].
* **Tampilan Tugas & Materi:** Menampilkan daftar tugas (beserta status keterlambatan, perpanjangan deadline, dan tombol *Kumpulkan* / *Kumpulkan ulang*) serta daftar materi per kelas yang diikuti mahasiswa[cite: 8].

### 4. Penilaian Dosen (`dosen/nilai.php`)
* **Pembatasan Role:** Menggunakan `need('dosen')`[cite: 11].
* **Pemilihan Tugas:** Dosen dapat memilih tugas aktif melalui dropdown `GET` (`?t=`), dengan default tugas terbaru milik dosen tersebut[cite: 11].
* **Logika Simpan Nilai (`POST`):**
  1. Menjalankan `chk()` dan membaca `tugas_id`, `m` (ID mahasiswa), `nilai`, serta `cat`[cite: 11].
  2. Memastikan tugas memang milik dosen yang sedang login (`dosen_id = ?`) dan rentang nilai berada di antara `0` sampai `100`[cite: 11].
  3. Menyimpan atau memperbarui nilai ke `lms_dosen.nilai` dengan `INSERT ... ON DUPLICATE KEY UPDATE`[cite: 11].

### 5. Manajemen Fakultas, Prodi & Kelas (`admin/kelas.php`)
* **Pembatasan Role:** Menggunakan `need('admin')`[cite: 14].
* **Aksi Administratif (`POST`):**
  * `addfak` & `addprodi`: Menambahkan data fakultas dan prodi ke `lms_admin`[cite: 14].
  * `add` & `del`: Membuat atau menghapus data kelas di `lms_admin.kelas`[cite: 14].
  * `unenroll`: Mengeluarkan mahasiswa dari kelas pada `lms_admin.kelas_mhs`[cite: 14].
* **Logika Validasi Pendaftaran (`enroll`):**
  1. Mencari mahasiswa berdasarkan `npm` atau `email` di `lms_mahasiswa.mahasiswa`[cite: 14].
  2. Mengambil data kelas (`lms_admin.kelas`) dan dosen pengampu (`lms_dosen.dosen`)[cite: 14].
  3. Membandingkan informasi prodi/fakultas mahasiswa dengan dosen pengampu kelas; jika tidak sesuai (lintas prodi/fakultas), pendaftaran dibatalkan dengan pesan error sebelum masuk ke `lms_admin.kelas_mhs`[cite: 14].

### 6. Komponen Layout & Styling (`includes/layout.php`, `css/forms-buttons.css`)
* `includes/layout.php` mengatur kerangka halaman (`top()`, `bottom()`, `auth_top()`), navigasi, serta pemuatan stylesheet[cite: 2, 6, 8, 11, 14].
* `css/forms-buttons.css` mengatur gaya kartu/box, form input, tombol aksi, tata letak form pengumpulan tugas (`.submit-tugas-form`), serta keselarasan form input nilai di dalam tabel (`td form`)[cite: 7].
