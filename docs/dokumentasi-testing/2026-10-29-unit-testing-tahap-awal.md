# Dokumentasi Testing — 2026-10-29 — Unit Testing Tahap Awal

> Status: tahap awal ini telah diperluas menjadi 76 test. Lihat
> [Analisis Lanjutan Unit Testing](2026-10-29-unit-testing-analisis-lanjutan.md).

## Referensi perubahan

[Dokumentasi perubahan 29 Oktober 2026](../perubahan-harian/2026-10-29.md)

## Posisi dalam V-Model

Pengujian ini merupakan tahap **Unit Testing** yang dipasangkan dengan tahap
**Module Design**. Setiap unit diuji secara terpisah tanpa request HTTP, database,
browser, atau layanan SMTP sungguhan.

## Ruang lingkup

- Aturan klasifikasi status masa berlaku E-KIM.
- Perhitungan jumlah hari tersisa.
- Pemeriksaan kelengkapan konfigurasi SMTP.
- Pemeriksaan format email pengirim SMTP.

## Lingkungan pengujian

- Sistem operasi: Windows 11.
- PHP: 8.2.12.
- PHPUnit: 11.5.56.
- Database: tidak digunakan oleh unit test.
- Jaringan dan SMTP eksternal: tidak digunakan.
- Tanggal acuan status E-KIM: 29 Oktober 2026.

## Skenario unit status E-KIM

| ID | Skenario | Masukan utama | Hasil yang diharapkan | Hasil aktual | Status |
|---|---|---|---|---|---|
| UT-EKIM-001 | Tanggal masa berlaku tidak tersedia | `null` | Status `unavailable`, hari tersisa `null` | Sesuai | Lulus |
| UT-EKIM-002 | Masa berlaku telah lewat satu hari | 28 Oktober 2026 | Status `expired`, hari tersisa `-1` | Sesuai | Lulus |
| UT-EKIM-003 | Masa berlaku berakhir hari ini | 29 Oktober 2026 | Status `expiring`, hari tersisa `0` | Sesuai | Lulus |
| UT-EKIM-004 | Tepat pada batas peringatan | 30 hari lagi | Status `expiring`, hari tersisa `30` | Sesuai | Lulus |
| UT-EKIM-005 | Melewati batas peringatan | 31 hari lagi | Status `active`, hari tersisa `31` | Sesuai | Lulus |
| UT-EKIM-006 | Nilai periode peringatan negatif | `-10` hari | Diproses sebagai `0`; status tetap `active` | Sesuai | Lulus |

## Skenario unit konfigurasi SMTP

| ID | Skenario | Masukan utama | Hasil yang diharapkan | Hasil aktual | Status |
|---|---|---|---|---|---|
| UT-MAIL-001 | Konfigurasi minimum lengkap | Host, port, dan email valid | Siap digunakan | Sesuai | Lulus |
| UT-MAIL-002 | Email pengirim tidak tersedia | Host dan port saja | Belum siap | Sesuai | Lulus |
| UT-MAIL-003 | Format email pengirim salah | `bukan-email` | Belum siap | Sesuai | Lulus |
| UT-MAIL-004 | SMTP memakai username tanpa password | Username terisi, password kosong | Belum siap | Sesuai | Lulus |
| UT-MAIL-005 | SMTP terautentikasi lengkap | Username dan password terisi | Siap digunakan | Sesuai | Lulus |

## Perintah pengujian

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite Unit
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe vendor\bin\pint --test
```

## Ringkasan hasil

- Unit test: 11 test lulus.
- Assertion unit: 17 assertion lulus.
- Waktu unit test: 0,137 detik.
- Regression keseluruhan: 48 test dan 237 assertion lulus.
- Laravel Pint: lulus.
- Status akhir: lulus.

## Temuan dan perbaikan

| Temuan | Penyebab | Perbaikan | Hasil pengujian ulang |
|---|---|---|---|
| Aturan status E-KIM berada langsung di controller sehingga sulit diuji sebagai unit | Logika domain bercampur dengan HTTP dan query | Memindahkan klasifikasi ke `EkimStatusCalculator` | Unit test dan regression test lulus |

Tidak ditemukan kegagalan assertion pada pelaksanaan unit test tahap awal.

## File bukti

- `backend/tests/Unit/EkimStatusCalculatorTest.php`
- `backend/tests/Unit/SystemMailerTest.php`
- `backend/app/Domain/Ekim/EkimStatusCalculator.php`
