# Dokumentasi Testing - 2026-10-29 - Integration Testing Tahap Awal

> Catatan: dokumen ini adalah snapshot tahap 44 test. Hasil audit terbaru ada
> pada [Analisis Lanjutan Integration Testing](2026-10-29-integration-testing-analisis-lanjutan.md)
> dengan 59 test dan 370 assertion.

## Referensi

- [Perubahan harian 29 Oktober 2026](../perubahan-harian/2026-10-29.md)
- [Audit Unit Testing menyeluruh](2026-10-29-audit-unit-testing-menyeluruh.md)

## Tujuan

Integration Testing memastikan beberapa unit dan adapter aplikasi dapat bekerja
bersama melalui route HTTP, middleware, autentikasi Sanctum, controller,
database SQLite in-memory, private storage palsu, mail palsu, dan HTTP client
palsu. Tahap ini merupakan pasangan pengujian terhadap perancangan arsitektur
dan antarmuka antarmodul pada V-Model.

## Batas pengujian

Yang termasuk pada tahap ini:

- request dan validation melalui route API;
- autentikasi, token Sanctum, role, dan middleware;
- controller yang membaca atau mengubah beberapa tabel;
- lifecycle registrasi, pengguna, kendaraan, dokumen, checklist, dan E-KIM;
- isolasi data antar-Integrated Terminal;
- storage, mail, CAPTCHA, scheduler, retensi, dan ekspor dengan boundary palsu;
- regional monitoring dan audit trail.

Yang belum dianggap sebagai Integration Testing pada lingkungan produksi:

- PostgreSQL, SMTP, dan Cloudflare Turnstile sebenarnya;
- executable `pg_dump` dan `pg_restore`;
- Docker, Nginx, cron host, browser, dan jaringan produksi;
- performa, beban, keamanan penetrasi, serta User Acceptance Testing.

Bagian tersebut ditempatkan pada System Testing atau Acceptance Testing.

## Lingkungan

| Komponen | Konfigurasi pengujian |
|---|---|
| PHP | 8.2.12 |
| PHPUnit | 11.5.56 |
| Framework | Laravel 12 |
| Database | SQLite in-memory dengan `RefreshDatabase` |
| Authentication | Laravel Sanctum |
| Storage | Laravel `Storage::fake` untuk skenario file |
| Email | Laravel `Mail::fake` |
| HTTP eksternal | Laravel `Http::fake` |

## Baseline sebelum penambahan

Perintah:

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite Feature
```

Hasil baseline: **37 test, 220 assertion, seluruhnya lulus** dalam 1,744 detik.

## Audit cakupan integrasi

| Modul integrasi | Bukti pengujian |
|---|---|
| Authentication dan registrasi | Login, portal internal/transportir, CAPTCHA, registrasi publik, reset password, lockout, akun nonaktif, logout token |
| Authorization | Role protection, regional read-only, admin scope, transportir ownership, maintenance bypass admin |
| Database | Relasi pengguna-kendaraan, checklist-item, registrasi-user, dokumen-kendaraan, audit, notifikasi |
| Checklist dan E-KIM | Simpan, tanda tangan HSSE/manager, approval, update kendaraan, notifikasi, verifikasi publik |
| Dokumen kendaraan | Upload, ownership, review, sinkronisasi tanggal, private download, delete file dan record |
| Multi-terminal | Isolasi registrasi, akun operasional, checklist, kendaraan, laporan, dan audit |
| Operasional | Session extension, retensi, scheduler, SMTP palsu, XLSX, seeder, delete user |
| Regional manager | Monitoring lintas terminal Sulawesi dan penolakan endpoint mutasi |

## Skenario baru hasil audit

| ID | Skenario | Interaksi yang diverifikasi | Hasil |
|---|---|---|---|
| INT-038 | Lima password salah mengunci akun | Route login, hash password, counter database, timestamp lockout | Lulus |
| INT-039 | Login setelah lockout berakhir | Timestamp, login, reset counter, pembuatan token | Lulus |
| INT-040 | Akun nonaktif ditolak | Login controller dan middleware authenticated route | Lulus |
| INT-041 | Maintenance membatasi non-admin | System setting, login, middleware, admin bypass | Lulus |
| INT-042 | Logout mencabut token aktif saja | Route logout, Sanctum token database, autentikasi request berikutnya | Lulus |
| INT-043 | Token reset 16 menit ditolak | Reset endpoint, token hash, timestamp database, password model | Lulus |
| INT-044 | Registrasi disetujui menjadi akun aktif | Public registration, password hash, approval admin, user creation, login transportir | Lulus |
| INT-045 | Lifecycle dokumen private | Upload, ownership, private download, delete file, delete record | Lulus |

Nomor skenario mencerminkan rincian perilaku. Implementasinya dikelompokkan
menjadi tujuh method test karena satu method lifecycle memverifikasi beberapa
tahap yang saling bergantung.

## Distribusi Integration/Feature Test

| File | Fokus | Test | Assertion |
|---|---|---:|---:|
| `OperationalFeaturesTest.php` | Operasional, dokumen, notifikasi, ekspor, retensi | 16 | 75 |
| `PrimaApiTest.php` | API utama, autentikasi, checklist, tanda tangan, verifikasi | 15 | 90 |
| `RegionalManagerTest.php` | Monitoring dan pembatasan regional | 3 | 38 |
| `TerminalIsolationTest.php` | Isolasi data antar-terminal | 3 | 17 |
| `SecurityAndLifecycleIntegrationTest.php` | Keamanan autentikasi dan lifecycle lintas komponen | 7 | 46 |
| **Total** |  | **44** | **266** |

## Kegagalan, analisis, dan perbaikan

Eksekusi pertama suite baru menghasilkan **7 test, 40 assertion, 2 gagal**.

| ID | Kegagalan awal | Penyebab | Perbaikan | Hasil uji ulang |
|---|---|---|---|---|
| FAIL-INT-001 | Token yang sudah logout tampak masih dapat mengakses `/auth/me` | Guard Laravel menyimpan user request sebelumnya dalam proses test yang sama | Memanggil `forgetGuards()` sebelum request dengan token berikutnya | Lulus; token lama 401 dan token kedua tetap 200 |
| FAIL-INT-002 | Transportir lain tampak dapat mengunduh dokumen owner | Pergantian `withToken()` masih memakai guard owner dari request upload | Mereset guard sebelum request user lain | Lulus; user lain 403 dan owner 200 |

Kedua kegagalan berasal dari isolasi test, bukan defect aplikasi. Pengujian ulang
suite baru menghasilkan **7 test, 46 assertion, seluruhnya lulus**.

## Hasil akhir dan regression

```powershell
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite Feature
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe artisan route:list --path=api/v1 --except-vendor
```

| Pemeriksaan | Hasil | Status |
|---|---|---|
| Integration/Feature suite | 44 test, 266 assertion, 1,997 detik | Lulus |
| Regression seluruh backend | 163 test, 449 assertion, 2,501 detik | Lulus |
| Laravel Pint | Tidak ada pelanggaran format | Lulus |
| Route API | 61 route berhasil dimuat | Lulus |

## Kesimpulan tahap awal

Integration Testing bertambah dari 37 menjadi 44 test. Jalur kritis keamanan,
maintenance, token, approval registrasi, dan private document lifecycle sekarang
memiliki bukti integrasi. Belum seluruh kombinasi endpoint dan error database
tercakup; tahap lanjutan dapat memprioritaskan backup/restore portable,
pengaturan SMTP, penolakan dokumen, perubahan status pengguna, serta konsistensi
transaksi ketika dependency gagal.
