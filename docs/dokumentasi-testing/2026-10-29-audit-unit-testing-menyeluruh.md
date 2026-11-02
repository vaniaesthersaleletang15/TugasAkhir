# Dokumentasi Testing - 2026-10-29 - Audit Unit Testing Menyeluruh

## Referensi perubahan

- [Perubahan harian 29 Oktober 2026](../perubahan-harian/2026-10-29.md)
- [Analisis lanjutan Unit Testing](2026-10-29-unit-testing-analisis-lanjutan.md)

## Tujuan dan batas audit

Audit dilakukan terhadap seluruh 34 file PHP pada folder `backend/app` dan
infrastruktur pengujian frontend. Tujuannya adalah menemukan aturan yang dapat
diuji secara terisolasi, memisahkan aturan yang masih bercampur dengan adapter,
dan menjalankan seluruh Unit Testing serta regression testing.

Unit Testing tidak dipaksakan untuk menggantikan Integration atau System
Testing. Query database, routing, autentikasi Sanctum, private storage, proses
`pg_dump`/`pg_restore`, scheduler, dan alur antarmuka lengkap membutuhkan
dependency nyata sehingga ditempatkan pada tingkat pengujian berikutnya dalam
V-Model.

## Lingkungan pengujian

| Komponen | Nilai |
|---|---|
| Tanggal | 29 Oktober 2026 |
| PHP | 8.2.12 |
| PHPUnit | 11.5.56 |
| Database test | SQLite in-memory untuk Feature Test |
| Mail test | Array mailer |
| CAPTCHA | HTTP client dipalsukan, tanpa koneksi eksternal |
| Coverage driver | Tidak tersedia; Xdebug dan PCOV tidak terpasang |

Karena coverage driver tidak tersedia, audit ini melaporkan cakupan berbasis
unit, skenario, dan pemetaan kelas. Persentase line/branch coverage tidak diklaim.

## Hasil inventaris seluruh codebase

### Backend

| Kelompok | File aplikasi | Hasil audit Unit Testing |
|---|---:|---|
| Domain Checklist | 4 | Seluruh kelas diuji langsung |
| Domain E-KIM | 4 | Seluruh kelas diuji langsung |
| Domain System | 4 | Seluruh kelas diuji langsung |
| Domain Vehicle | 1 | Seluruh kelas diuji langsung |
| API concern | 1 | Kontrak response dan otorisasi role diuji |
| Middleware | 3 | Header diuji langsung; aturan sesi dan maintenance diuji melalui policy |
| Model | 2 | Hidden attribute, password cast, dan boolean cast diuji |
| Service | 4 | `SystemMailer` dan `TurnstileVerifier` diuji langsung; service database diuji pada tingkat integration |
| Support | 1 | Struktur XLSX, escaping, kolom, filter, dan frozen pane diuji |
| Controller API | 4 | Adapter HTTP; tetap menjadi Feature/Integration Test |
| Console command | 5 | Aturan pesan, retensi, nama file, dan checksum diuji sebagai unit; eksekusi proses tetap System Test |
| Provider | 1 | Registrasi framework; diperiksa melalui bootstrap aplikasi/regression |
| **Total** | **34** | **20 file diuji langsung dan aturan dari adapter lain dipindahkan ke unit domain** |

### Frontend

Frontend memiliki delapan file sumber. Audit menambahkan runner unit bawaan
Node dan lima test untuk konfigurasi role/status yang bersifat murni. Komponen
React belum mempunyai DOM test environment. Bagian tersebut dicatat sebagai
**gap audit**, bukan dinyatakan lulus, dan memerlukan Vitest, jsdom, serta React
Testing Library agar pengujian render dan interaksi dapat dipercaya.

## Perbaikan berdasarkan hasil audit

| ID | Temuan audit | Perbaikan | Skenario utama |
|---|---|---|---|
| AUD-UT-001 | Batas sesi 15-480 menit ditulis langsung di middleware | Membuat `SessionPolicy` dan menggunakannya pada `ExtendPrimaSession` | null, string angka, batas minimum/maksimum, nilai di luar batas |
| AUD-UT-002 | Aturan akun aktif dan maintenance bercampur dengan query database | Membuat `SystemAvailabilityPolicy` dan menggunakannya pada middleware | akun aktif/nonaktif, admin bypass, non-admin diblokir, pesan fallback |
| AUD-UT-003 | Validasi nama dan checksum backup berada di command | Membuat `BackupFilePolicy` dan menggunakannya pada dua command backup | ekstensi valid, prefix, traversal, path, checksum cocok/berbeda/kosong |
| AUD-UT-004 | Verifikasi CAPTCHA belum diuji pada batas layanan eksternal | Menambahkan `TurnstileVerifierTest` dengan HTTP fake | disabled, konfigurasi kosong, sukses, ditolak, HTTP 500, koneksi gagal |
| AUD-UT-005 | Security header belum memiliki bukti unit | Menambahkan `SecurityHeadersTest` | header umum, cache API, cache non-API |
| AUD-UT-006 | Generator XLSX belum diuji | Menambahkan `SimpleXlsxTest` | entry ZIP, escaping XML, kolom Z/AA, dataset kosong |
| AUD-UT-007 | Kontrak response API dan pemeriksaan role belum diuji terisolasi | Menambahkan `InteractsWithPrimaApiTest` | success, failure, role diizinkan, role ditolak |
| AUD-UT-008 | Konfigurasi keamanan model belum memiliki unit test | Menambahkan `ModelConfigurationTest` | hidden fields, hash password, boolean cast |
| AUD-UT-009 | Frontend belum mempunyai test runner | Menambahkan `node:test`, script `test:unit`, dan lima test konfigurasi domain | 5 test lulus; komponen React masih menjadi gap |

Tidak ada kegagalan assertion saat suite baru pertama dijalankan. Temuan audit
di atas adalah kekurangan cakupan sebelum perbaikan, bukan kegagalan hasil test.

## Distribusi Unit Testing akhir

| Kelompok unit | Jumlah test | Assertion |
|---|---:|---:|
| Unit domain/service tahap sebelumnya | 76 | 112 |
| Backup file policy | 12 | 13 |
| Session policy | 7 | 7 |
| System availability policy | 4 | 10 |
| Turnstile verifier | 6 | 10 |
| Security headers | 3 | 6 |
| Simple XLSX | 4 | 12 |
| API concern | 4 | 7 |
| Model configuration | 3 | 6 |
| **Total** | **119** | **183** |

Data provider pada PHPUnit dihitung sebagai test terpisah. Karena itu jumlah
method test tidak selalu sama dengan jumlah test yang dieksekusi.

## Perintah dan hasil aktual

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite Unit
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe artisan route:list --path=api/v1 --except-vendor
cd ..\frontend
npm.cmd run test:unit
npm.cmd run build
npx.cmd prettier --check package.json tests/unit/domain.test.js
```

| Pengujian | Hasil aktual | Status |
|---|---|---|
| Unit suite | 119 test, 183 assertion, 1,268 detik | Lulus |
| Regression seluruh PHPUnit | 156 test, 403 assertion, 2,144 detik | Lulus |
| Feature/Integration suite yang ikut regression | 37 test, 220 assertion | Lulus |
| Laravel Pint | Tidak ada pelanggaran format | Lulus |
| Pemuatan route API | 61 route berhasil dimuat | Lulus |
| Unit frontend | 5 test, 5 lulus, 0 gagal | Lulus |
| Build frontend | 1.889 module ditransformasi | Lulus |
| Prettier file frontend baru | Seluruh file sesuai format | Lulus setelah perbaikan |

## Catatan hasil gagal dan perbaikan

| ID | Hasil awal | Penyebab | Perbaikan | Hasil uji ulang |
|---|---|---|---|---|
| FAIL-UT-001 | `npm`, `npm run build`, dan `npx` gagal dieksekusi | PowerShell execution policy memblokir wrapper `npm.ps1`/`npx.ps1` | Menjalankan executable Windows `npm.cmd` dan `npx.cmd` tanpa mengubah kebijakan sistem | Unit frontend dan build lulus |
| FAIL-UT-002 | Prettier menemukan style issue pada `package.json` dan `domain.test.js` | File baru belum diformat | Menjalankan Prettier `--write` hanya pada dua file | Pemeriksaan format lulus |

## Komponen yang bukan Unit Testing

Komponen berikut telah diaudit, tetapi bukti utamanya harus diletakkan pada
Integration atau System Testing:

- `PrimaController`, `AdminController`, dan `RegionalController`: request,
  validation, database, otorisasi, storage, serta response lintas lapisan;
- `DatabaseBackupService`, `RetentionService`, `ApplyRetentionPolicies`, dan
  command import/backup/restore: database, filesystem, gzip, serta proses OS;
- `EnsurePrimaAvailable` dan `ExtendPrimaSession`: adapter autentikasi/token dan
  database; aturan murninya sudah dicakup policy unit;
- `AppServiceProvider`: lifecycle dan bootstrap framework;
- pengiriman mail sebenarnya, scheduler, PostgreSQL backup/restore, private
  download, dan keseluruhan alur browser.

Klasifikasi tersebut mencegah laporan skripsi menyebut pengujian database atau
proses eksternal sebagai Unit Testing. Seluruh komponen ini tetap masuk matriks
pengujian V-Model pada tahap Integration dan System Testing.

## Kesimpulan audit

Cakupan Unit Testing backend meningkat dari 76 menjadi 119 test. Semua aturan
murni yang ditemukan dalam audit backend saat ini telah mempunyai pengujian,
baik secara langsung maupun melalui policy yang diekstrak. Regression test tidak
menunjukkan perubahan kontrak API atau kerusakan route. Gap yang masih terbuka
adalah Unit Testing komponen React dan pengukuran line/branch coverage; keduanya
telah dicatat secara eksplisit agar tidak dianggap sebagai hasil lulus.
