# Dokumentasi Testing — 2026-10-29 — Penataan Ulang Struktur Codebase

## Referensi perubahan

[Dokumentasi perubahan 29 Oktober 2026](../perubahan-harian/2026-10-29.md)

## Ruang lingkup

Pengujian memastikan penataan ulang concern API, service backup, service SMTP,
konstanta frontend, dan komponen tampilan bersama tidak mengubah perilaku sistem.

## Lingkungan pengujian

- Sistem operasi: Windows 11.
- Backend: PHP 8.2.12, Laravel 12, PHPUnit 11.5.56.
- Frontend: React dan Vite 8.2.2.
- Database test: SQLite in-memory melalui `backend/phpunit.xml`.
- Data operasional: tidak digunakan dan tidak diubah.

## Skenario dan hasil

| ID | Jenis pengujian | Skenario | Hasil yang diharapkan | Hasil aktual | Status |
|---|---|---|---|---|---|
| TEST-001 | Regression | Menjalankan seluruh Feature Test backend | Seluruh test dan assertion lulus | 37 test dan 220 assertion lulus | Lulus |
| TEST-002 | Build | Membuat build produksi frontend | Seluruh modul berhasil ditransformasi | 1.889 modul berhasil ditransformasi | Lulus |
| TEST-003 | Routing | Memuat seluruh route API | Route dapat didaftarkan tanpa error | 61 route API terdaftar | Lulus |
| TEST-004 | Static check | Memeriksa format seluruh backend | Laravel Pint tidak menemukan pelanggaran | Pemeriksaan lulus | Lulus |
| TEST-005 | Static check | Memeriksa file frontend yang diubah | Seluruh file sesuai Prettier | Pemeriksaan lulus | Lulus |
| TEST-006 | Syntax | Memeriksa syntax service dan concern PHP | Tidak terdapat syntax error | Tidak terdapat syntax error | Lulus |

## Perintah pengujian

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe artisan route:list --path=api/v1 --except-vendor

cd ..\frontend
C:\laragon\bin\nodejs\node-v22\npm.cmd exec prettier -- --check src\App.jsx src\Modules.jsx src\RegionalPages.jsx src\components\AsyncState.jsx src\config\domain.js .prettierrc.json
C:\laragon\bin\nodejs\node-v22\npm.cmd run build
```

## Ringkasan hasil

- Backend: 37 test lulus.
- Assertion: 220 assertion lulus.
- Frontend: build produksi berhasil.
- Routing: 61 route API berhasil didaftarkan.
- Format: Laravel Pint dan Prettier lulus.
- Status akhir: lulus.

## Temuan dan perbaikan

| Temuan | Penyebab | Perbaikan | Hasil pengujian ulang |
|---|---|---|---|
| Format `User.php` belum sesuai standar Pint | Format kode lama belum konsisten | Menjalankan Laravel Pint pada model pengguna | Seluruh pemeriksaan Pint lulus |
| Perintah `npm` PowerShell diblokir Execution Policy | PowerShell menolak `npm.ps1` | Menggunakan executable `npm.cmd` | Prettier dan build berhasil |

## Bukti tambahan

Output pengujian tersedia pada riwayat pengerjaan tanggal 29 Oktober 2026. Tidak
ada database operasional yang digunakan dalam regression test.
