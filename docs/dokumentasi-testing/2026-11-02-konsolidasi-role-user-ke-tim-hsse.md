# Pengujian Konsolidasi Role User ke Tim HSSE — 2 November 2026

## Tujuan

Memastikan role legacy `user` tidak lagi menjadi aktor aplikasi, akun lama dimigrasikan tanpa kehilangan data, alur kendaraan mengikuti FR, dan perubahan tidak merusak fungsi backend maupun frontend.

## Cakupan

- daftar role dan portal autentikasi;
- policy workflow checklist;
- menu serta route guard frontend;
- endpoint registrasi kendaraan legacy;
- migrasi akun `user` menjadi `hsse`;
- matriks otorisasi seluruh role;
- regresi backend, unit frontend, dan build produksi.

## Skenario penting

| Skenario | Hasil yang diharapkan | Status akhir |
|---|---|---|
| Akun legacy memiliki role `user` saat migrasi dijalankan | Akun tetap ada, aktif, dan role menjadi `hsse` | Lulus |
| HSSE membuka endpoint `/api/v1/user-vehicles` | Route tidak ditemukan karena alur legacy ditutup | Lulus |
| Pengurus mendaftarkan/mengklaim kendaraan pada scope transportirnya | Diproses melalui `/api/v1/my-vehicles` | Lulus |
| SPV membuat/mengelola kendaraan dan nomor polisi duplikat | Data valid tersimpan; duplikasi ditolak 422 | Lulus |
| Role di luar SPV/HSSE mengakses formulir checklist baru | Ditolak oleh backend/frontend guard | Lulus |
| Konfigurasi role frontend | Tidak memuat role `user` | Lulus |

## Riwayat hasil gagal dan koreksi

### 1. Regression backend pertama

Perintah:

```powershell
C:\xampp\php\php.exe vendor/bin/phpunit
```

Hasil awal:

```text
217 tests, 655 assertions, 1 failure
OperationalFeaturesTest::test_vehicle_registration_normalizes_and_stores_transportir_email
Expected: pesan duplikasi khusus endpoint legacy
Actual: The nomor polisi has already been taken.
```

Penyebab: test telah dipindahkan dari endpoint legacy `/user-vehicles` ke endpoint resmi SPV `/vehicles/manage`, tetapi masih mengharapkan teks validasi milik endpoint lama. Perilaku wajib sudah benar, yaitu HTTP 422 dan data duplikat tidak disimpan.

Koreksi: assertion difokuskan pada kontrak penolakan duplikasi HTTP 422. Pengujian endpoint lama dipisahkan menjadi test bahwa GET dan POST menghasilkan 404.

### 2. Kesalahan lokasi eksekusi perintah

Perintah `artisan`, `npm run test:unit`, dan `npm run build` sempat dijalankan dari folder induk `C:\PRIMA_CHECKLIST_REGIONAL`. Perintah gagal menemukan `artisan` dan `package.json` karena keduanya berada pada subfolder berbeda. Pengujian diulang dari `backend` dan `frontend`; ini merupakan kesalahan lokasi terminal, bukan defect aplikasi.

### 3. Pemeriksaan route legacy

`php artisan route:list --path=user-vehicles` menampilkan bahwa tidak ada route yang cocok. Pesan CLI tersebut merupakan hasil yang diharapkan dan diperkuat oleh feature test GET/POST yang lulus dengan respons 404.

## Hasil pengujian ulang akhir

### Backend

```text
C:\xampp\php\php.exe vendor/bin/phpunit
OK (218 tests, 656 assertions)
Waktu final setelah penghapusan kode legacy: 7.072 detik
Memory: 60 MB
```

Tambahan test baru memverifikasi migrasi data legacy tanpa penghapusan akun. Berkurangnya jumlah dataset dibanding baseline 1 November berasal dari penghapusan role `user` pada matriks otorisasi; satu test migrasi baru ditambahkan.

### Frontend unit

```text
npm.cmd run test:unit
5 test lulus, 0 gagal
Durasi final: 103.7582 ms
```

### Frontend build

```text
npm.cmd run build
1894 modul ditransformasi
Build final berhasil dalam 383 ms
```

### Pemeriksaan sintaks PHP

```text
AdminController.php: tidak ada kesalahan sintaks
2026_11_02_000000_consolidate_user_role_into_hsse.php: tidak ada kesalahan sintaks
RoleConsolidationMigrationTest.php: tidak ada kesalahan sintaks
```

### Formatting

```text
Laravel Pint: berhasil; AdminController.php dirapikan otomatis
Prettier: seluruh file frontend yang diperiksa sudah sesuai format
```

## Kesimpulan

Implementasi lulus pengujian otomatis akhir. Role operasional `user` telah digantikan oleh Tim HSSE, endpoint kendaraan legacy ditutup, alur kendaraan mengikuti Pengurus–SPV HSSE, dan data akun lama dipertahankan melalui migrasi.

Validasi UAT per role pada lingkungan kantor tetap diperlukan sebagai bukti acceptance testing, terutama untuk memastikan istilah role dan alur kerja dipahami pengguna aktual.

## Referensi perubahan

Lihat [perubahan harian 2 November 2026](../perubahan-harian/2026-11-02.md).
