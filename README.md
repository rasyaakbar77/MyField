# MyField

Platform pemesanan lapangan olahraga berbasis web yang memudahkan proses booking lapangan futsal, badminton, dan basket secara online.

---

## Deskripsi

MyField adalah aplikasi web untuk manajemen dan pemesanan lapangan olahraga. Customer dapat melihat ketersediaan lapangan, melakukan booking, mengunggah bukti pembayaran, dan memberikan ulasan. Admin dan pemilik dapat mengelola lapangan, mengonfirmasi booking, serta memverifikasi pembayaran melalui panel admin.

---

## Fitur

### Customer
- Registrasi dan login akun
- Melihat daftar lapangan dengan filter jenis olahraga
- Booking lapangan dengan pilihan slot waktu per jam (08:00–22:00)
- Riwayat booking beserta status booking dan pembayaran
- Upload bukti transfer untuk pembayaran
- Memberikan ulasan dan rating setelah selesai bermain
- Kelola profil (edit nama, nomor telepon, ganti password)

### Admin / Pemilik
- Dashboard rekap booking dan statistik
- Kelola lapangan (tambah, edit, hapus, ubah status)
- Konfirmasi dan manajemen booking customer
- Verifikasi pembayaran dari bukti transfer yang diunggah

---

## Struktur Folder

```
MyField/
├── index.php                  # Entry point, redirect berdasarkan role
├── README.md
│
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── config/
│   └── connection.php         # Konfigurasi koneksi database
│
├── customer/
│   ├── homepage.php           # Daftar & filter lapangan
│   ├── bookingform.php        # Form booking lapangan
│   ├── bookinghistory.php     # Riwayat booking customer
│   ├── pembayaran.php         # Upload bukti pembayaran
│   ├── review.php             # Form ulasan & rating
│   └── profile.php            # Profil & ganti password
│
├── admin/
│   ├── dashboard.php          # Dashboard admin
│   ├── field_manage.php       # Kelola lapangan
│   ├── booking_manage.php     # Kelola booking
│   └── payment_confirm.php    # Konfirmasi pembayaran
│
├── assets/
│   ├── css/
│   │   └── customer.css
│   ├── partials/
│   │   └── navbar.php
│   └── uploads/               # Bukti transfer (auto-generated)
│
└── database/
    └── myfield.sql            # Dump database
```

---

## Database

Database: `myfield` (MariaDB / MySQL)

| Tabel              | Keterangan                                      |
|--------------------|-------------------------------------------------|
| `pengguna`         | Data user (customer, admin, pemilik)            |
| `lapangan`         | Data lapangan olahraga                          |
| `jadwal_lapangan`  | Slot waktu lapangan (kosong / booked)           |
| `booking`          | Transaksi booking oleh customer                 |
| `pembayaran`       | Data pembayaran dan bukti transfer              |
| `ulasan`           | Rating dan komentar dari customer               |

### Role Pengguna
| Role      | Akses                                      |
|-----------|--------------------------------------------|
| `customer`  | Homepage, booking, riwayat, profil       |
| `admin`     | Semua halaman admin                      |
| `pemilik`   | Semua halaman admin                      |

---

## Cara Instalasi (Lokal)

### Prasyarat
- XAMPP (Apache + MySQL) atau server PHP lokal lainnya
- PHP >= 7.4
- MySQL / MariaDB

### Langkah-langkah

1. **Clone / download** project ini ke folder `htdocs`:
   ```
   C:\xampp\htdocs\MyField\
   ```

2. **Jalankan XAMPP** — pastikan Apache dan MySQL aktif (hijau).

3. **Buat database** di phpMyAdmin:
   - Buka `http://localhost/phpmyadmin`
   - Buat database baru bernama `myfield`
   - Pilih database tersebut → tab **Import** → upload file `database/myfield.sql`

4. **Sesuaikan konfigurasi** di `config/connection.php`:
   ```php
   $host     = "localhost";
   $user     = "root";
   $password = "";          // sesuaikan jika ada password
   $database = "myfield";
   ```

5. **Akses aplikasi** di browser:
   ```
   http://localhost/MyField/
   ```

---

## Akun Default (Data Sample)

>  Password di bawah hanya berlaku setelah menjalankan query update hash di langkah berikutnya.

Jalankan query ini di phpMyAdmin untuk mengaktifkan login akun sample (password: `password`):

```sql
UPDATE pengguna
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE id_pengguna IN (1, 2, 3, 4, 5);
```

| Nama            | Email                        | Password   | Role     |
|-----------------|------------------------------|------------|----------|
| Ahmad Fauzi     | ahmad.fauzi@email.com        | `password` | customer |
| Budi Santoso    | budi.santoso@email.com       | `password` | customer |
| Siti Aminah     | siti.aminah@email.com        | `password` | customer |
| Andika Pratama  | andika.pemilik@email.com     | `password` | pemilik  |
| Admin Super     | admin.booking@email.com      | `password` | admin    |

Atau daftar akun baru langsung lewat halaman Register.

---

##  Alur Penggunaan

```
Customer                         Admin
─────────                        ─────
Register / Login
    │
    ▼
Pilih lapangan
    │
    ▼
Booking (status: pending)
    │                            Terima notif booking
    │                                │
    │                                ▼
    │                         Konfirmasi booking
    │                         (status: dikonfirmasi)
    │
    ▼
Upload bukti bayar
(pembayaran: pending)
    │                            Cek bukti transfer
    │                                │
    │                                ▼
    │                         Konfirmasi pembayaran
    │                         (status: lunas)
    │                         (booking: selesai)
    ▼
Beri ulasan & rating
```

---

##  Teknologi

| Komponen   | Teknologi                         |
|------------|-----------------------------------|
| Backend    | PHP (Procedural)                  |
| Database   | MySQL / MariaDB                   |
| Frontend   | Bootstrap 5.3, Font Awesome 6.4   |
| Server     | Apache (XAMPP)                    |

---

##  Keamanan

- Password di-hash menggunakan `password_hash()` (bcrypt)
- Autentikasi menggunakan PHP Session
- Query database menggunakan **Prepared Statement** (mencegah SQL Injection)
- Output HTML di-sanitasi menggunakan `htmlspecialchars()` (mencegah XSS)
- Validasi file upload: ekstensi (JPG/PNG/PDF) dan ukuran maksimal 2MB
- Proteksi halaman berdasarkan role session di setiap file

---

##  Tim Pengembang

Rasya Islami Akbar - 140810250009
Razan Ibrahim Nabil - 140810250090
Ghiyats Khairul Mala - 140810250102

---

##  Lisensi

Project ini dibuat untuk keperluan akademik — Tugas Besar Sistem Basis Data, Universitas Padjadjaran.
