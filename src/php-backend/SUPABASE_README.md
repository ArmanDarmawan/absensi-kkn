# Integrasi Supabase dengan Sistem Absensi

Dokumen ini menjelaskan cara mengintegrasikan Supabase sebagai database untuk sistem absensi.

## Prasyarat

- Node.js (versi 14 atau lebih baru)
- NPM atau Yarn
- Akun Supabase (https://supabase.com)

## Instalasi

1. Instal dependensi yang diperlukan:

```bash
npm install
```

Atau jika menggunakan Yarn:

```bash
yarn install
```

## Konfigurasi

1. Buat file `.env` di direktori root proyek (sudah dibuat)
2. Tambahkan kredensial Supabase Anda ke file `.env` (sudah dikonfigurasi):

```
SUPABASE_URL=https://delsplywmweierlppnnp.supabase.co
SUPABASE_KEY=your_supabase_key
```

3. Pastikan file `.env` ditambahkan ke `.gitignore` untuk keamanan (sudah dikonfigurasi)

## Struktur Database Supabase

Buat tabel berikut di Supabase:

### Tabel `public_attendance`

```sql
CREATE TABLE public_attendance (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    nim VARCHAR(50) NOT NULL,
    prodi VARCHAR(100) NOT NULL,
    photo VARCHAR(255),
    date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    latitude_in DECIMAL(10,8),
    longitude_in DECIMAL(11,8),
    latitude_out DECIMAL(10,8),
    longitude_out DECIMAL(11,8),
    verified BOOLEAN DEFAULT FALSE,
    verified_by INTEGER,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Tabel `users`

```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Penggunaan

### Mengimpor Supabase Client

```javascript
import supabase from './supabase.js';
```

### Contoh Penggunaan

Lihat file `supabase-example.js` untuk contoh cara menggunakan Supabase untuk:

1. Mengambil data absensi
2. Menyimpan data absensi baru

## Migrasi dari MySQL ke Supabase

Untuk migrasi data dari MySQL ke Supabase:

1. Ekspor data dari MySQL sebagai CSV atau JSON
2. Impor data ke Supabase menggunakan fitur impor di dashboard Supabase

Atau gunakan script migrasi khusus jika diperlukan.

## Keamanan

- Jangan pernah menyimpan kunci API Supabase di kode sumber yang dapat diakses publik
- Selalu gunakan file `.env` untuk menyimpan kredensial
- Terapkan Row Level Security (RLS) di Supabase untuk kontrol akses yang lebih baik

## Troubleshooting

### Masalah Koneksi

Jika Anda mengalami masalah koneksi ke Supabase:

1. Pastikan URL dan kunci API Supabase benar
2. Periksa apakah file `.env` dimuat dengan benar
3. Periksa koneksi internet Anda

### Error CORS

Jika Anda mengalami masalah CORS:

1. Tambahkan domain Anda ke daftar domain yang diizinkan di pengaturan Supabase

## Sumber Daya

- [Dokumentasi Supabase](https://supabase.com/docs)
- [Supabase JavaScript Client](https://supabase.com/docs/reference/javascript/introduction)