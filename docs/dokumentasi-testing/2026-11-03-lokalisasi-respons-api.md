# Pengujian Lokalisasi Respons API — 3 November 2026

## Tujuan

Memastikan pesan teknis bawaan seperti `Unauthenticated.` tidak tampil kepada pengguna, seluruh respons umum menggunakan bahasa Indonesia, dan istilah role lama telah diganti menjadi `Tim HSSE`.

## Cakupan

- respons autentikasi dan otorisasi;
- endpoint atau metode yang tidak tersedia;
- validasi input Laravel;
- rate limit, sesi kedaluwarsa, dan kesalahan server produksi;
- kegagalan koneksi frontend;
- label role HSSE pada antarmuka;
- regresi backend dan frontend;
- build image Docker produksi.

## Riwayat hasil gagal dan perbaikan

### Pengujian pertama

Feature test awal menghasilkan tiga lulus dan satu gagal. Respons 404 masih menampilkan:

```text
The route api/endpoint-tidak-ada could not be found.
```

Penyebabnya adalah pesan 404 Laravel memuat nama route secara dinamis sehingga tidak cocok dengan daftar frasa statis. Pemeriksaan format awal juga menemukan koreksi Pint pada `bootstrap/app.php`.

### Pengujian kedua

Setelah pola 404 diperluas, respons 405 masih menampilkan:

```text
The POST method is not supported for route api/test-forbidden.
```

Penyebabnya adalah pola awal mengharapkan kata tambahan di antara `method` dan `is not supported`. Pola kemudian diperbaiki untuk mengenali pesan metode dinamis Laravel.

### Pengujian ulang terfokus

```text
LocalizedApiResponseTest
4 test lulus, 13 assertion
0 gagal
```

Skenario yang lulus:

- token tidak tersedia menghasilkan `Sesi Anda telah berakhir. Silakan masuk kembali.`;
- akses 403 menghasilkan `Akses ditolak.`;
- endpoint 404 dan metode 405 menghasilkan pesan Indonesia;
- validasi wajib menghasilkan `Kolom Integrated Terminal wajib diisi.`;
- error produksi 500 tidak membocorkan detail Inggris/internal.

## Regresi akhir

### Backend

```text
C:\xampp\php\php.exe artisan test
222 test lulus
669 assertion
0 gagal, 0 warning
Durasi 4,73 detik
```

### Frontend

```text
npm.cmd run test:unit
12 test lulus
0 gagal
Durasi 121,2832 ms

npm.cmd run build
1894 modul ditransformasi
Build berhasil dalam 381 ms
```

Tiga test frontend baru memverifikasi terjemahan pesan autentikasi, fallback berdasarkan status HTTP, serta perlindungan agar pesan khusus backend tetap dipertahankan.

### Pemeriksaan kualitas dan deployment

```text
composer validate --strict: lulus
Laravel Pint: lulus
Prettier src dan tests: lulus
Image Docker backend: berhasil dibangun
Image Docker frontend: berhasil dibangun
```

Pencarian source akhir tidak menemukan lagi penamaan role HSSE yang lama. Teks Inggris yang tersisa pada `apiMessages.js` hanya berfungsi sebagai kunci pendeteksi respons server lama dan tidak ditampilkan kepada pengguna.

## Kesimpulan

Status akhir: **lulus**. Respons standar API dan fallback frontend telah menggunakan bahasa Indonesia, sedangkan nama role antarmuka telah konsisten menjadi `Tim HSSE`.

## Referensi perubahan

Lihat [perubahan harian 3 November 2026](../perubahan-harian/2026-11-03.md).
