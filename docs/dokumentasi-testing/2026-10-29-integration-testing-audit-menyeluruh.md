# Dokumentasi Testing - 2026-10-29 - Audit Menyeluruh Integration Testing

> Catatan: dokumen ini adalah snapshot tahap 63 test. Audit cabang otorisasi
> terbaru tersedia pada [Audit Otorisasi dan Cabang Integration Testing](2026-10-29-integration-testing-audit-otorisasi-cabang.md)
> dengan 103 test dan 450 assertion.

## Referensi

- [Integration Testing tahap awal](2026-10-29-integration-testing-tahap-awal.md)
- [Analisis lanjutan Integration Testing](2026-10-29-integration-testing-analisis-lanjutan.md)
- [Audit Unit Testing menyeluruh](2026-10-29-audit-unit-testing-menyeluruh.md)
- [Perubahan harian 29 Oktober 2026](../perubahan-harian/2026-10-29.md)

## Tujuan audit

Audit ini memeriksa seluruh modul backend berdasarkan route, middleware,
controller, model, domain policy, service, database, storage, mail, HTTP client,
console boundary, dan isolasi Integrated Terminal. Setelah pemetaan selesai,
seluruh route API diberi request Integration Test dan state hasil integrasi
diperiksa melalui response, database, token, file, atau audit trail.

Istilah "seluruh" pada dokumen ini berarti seluruh route dan modul backend telah
diaudit serta mempunyai bukti pada tingkat pengujian yang sesuai. Istilah ini
tidak berarti seluruh kombinasi input atau seluruh dependency produksi telah
dijalankan. Dependency produksi nyata tetap menjadi System Testing.

## Ringkasan hasil

| Tahap | Test | Assertion | Status |
|---|---:|---:|---|
| Baseline Integration Testing | 37 | 220 | Lulus |
| Setelah tahap awal | 44 | 266 | Lulus |
| Setelah analisis lanjutan | 59 | 370 | Lulus |
| **Setelah audit menyeluruh** | **63** | **398** | **Lulus** |
| Regression Unit + Integration | 182 | 581 | Lulus |

## Cakupan seluruh route API

Seluruh **61 dari 61 route API** sekarang sudah menerima request melalui HTTP
kernel pada Feature/Integration Test. Cakupan tersebut meliputi:

| Kelompok route | Contoh integrasi yang diverifikasi |
|---|---|
| Public/auth | CAPTCHA, register, login, lockout, reset password, verification |
| Authenticated profile | Sanctum, session extension, availability, logout |
| Checklist | Create, list, detail, update, delete, workflow, signature, stats, template |
| Vehicle | Master vehicle, User registration, Transportir claim, assignment dan list |
| Document | List, upload, review approve/reject, private download dan delete |
| User/registration | CRUD, status, reset password, approval dan rejection |
| Notification/scheduler | SMTP settings, cron secret, scheduler success/failure |
| System settings | Read health, save policy, retention endpoint dan backup endpoint |
| Regional | Terminal, dashboard, E-KIM, report, audit, create/toggle/reset SPV |
| Reporting | Dashboard, alert, audit dan XLSX export |

Route coverage digunakan sebagai checklist antarmuka. Branch coverage tetap
lebih luas karena satu route dapat memiliki banyak role, status, dan kondisi
error.

## Audit per lapisan aplikasi

| Lapisan | Hasil audit integrasi | Status |
|---|---|---|
| Domain policy | Digunakan oleh controller pada workflow, signature, expiry, status, retensi dan backup validation | Terintegrasi; detail aturan dicakup Unit Test |
| Middleware | Security header, Sanctum, session extension, active account dan maintenance | Tercakup |
| Controller | Seluruh public route action pada Prima, Admin dan Regional menerima request test | Tercakup |
| Model | User hashing/cast, terminal relation dan lifecycle token digunakan dalam database test | Tercakup |
| Database | Registrasi, user, kendaraan, assignment, dokumen, checklist, signature, audit, setting dan notification | Tercakup pada SQLite |
| Storage | Upload, download, delete, retention, daftar backup dan checksum | Tercakup dengan fake disk |
| Mail | Reset dan notifikasi scheduler | Tercakup dengan fake mail |
| HTTP eksternal | Turnstile success/reject/failure | Tercakup dengan fake HTTP |
| Console/service | Notification command dan retention service | Tercakup |
| Backup service | Route-controller-service contract dicakup dengan service fake | Actual PostgreSQL backup masuk System Test |
| PostgreSQL metadata | Production memakai `pg_stat_user_tables`; test memakai adapter SQLite portabel | Tercakup per driver |
| Frontend React | Bukan bagian HTTP backend Integration Test | Masuk System/E2E Testing |

## Empat route terakhir yang ditambahkan

### Daftar dokumen

`GET /api/v1/documents` diuji untuk memastikan:

- Transportir hanya melihat dokumen yang diunggah sendiri;
- Admin hanya melihat dokumen terminalnya;
- HSSE ditolak karena endpoint hanya ditujukan untuk Admin dan Transportir.

### Settings dan health

`GET /api/v1/settings` sebelumnya mengunci query ke katalog PostgreSQL sehingga
tidak dapat diuji pada SQLite. Pengambilan metrik tabel dibuat berdasarkan
driver:

- PostgreSQL tetap menggunakan `pg_stat_user_tables` dan ukuran tabel asli;
- SQLite menggunakan `sqlite_master`, jumlah row, dan ukuran `0` sebagai nilai
  yang tidak tersedia pada katalog SQLite.

Response contract settings tidak berubah. Nama database dan label driver kini
berasal dari koneksi yang benar-benar aktif.

### Retention endpoint

`POST /api/v1/settings/audit-retention` diuji dari route sampai service,
database, fake storage, checksum sidecar, dan audit trail. Data lama dihapus,
sedangkan data yang masih berlaku dipertahankan.

### Backup endpoint

`POST /api/v1/settings/backup` diuji dari route, authorization, controller,
service contract, system settings, response metadata, dan audit trail. Service
backup dipalsukan agar test tidak menjalankan `pg_dump` atau menulis backup
database produksi. Eksekusi backup sebenarnya tetap menjadi System Test.

## Defect dan temuan selama seluruh tahap

| ID | Jenis | Temuan | Perbaikan/keputusan |
|---|---|---|---|
| FAIL-INT-001 | Isolasi test | Guard menyimpan user setelah logout | Reset guard sebelum token berikutnya |
| FAIL-INT-002 | Isolasi test | Guard owner terbawa ke request user lain | Reset guard sebelum pergantian user |
| DEF-INT-001 | Defect aplikasi | PUT checklist HTTP 500 karena policy tidak diinjeksi oleh closure route | Route diarahkan langsung ke controller dan route ID diteruskan ke action |
| FAIL-INT-004 | Ekspektasi test | Test menganggap Admin tidak boleh menghapus non-draft | Skenario disesuaikan dengan policy; HSSE yang diuji ditolak |
| AUD-INT-001 | Testability | Settings hanya dapat membaca katalog PostgreSQL | Metrik tabel dibuat portabel berdasarkan driver |

Tidak ada kegagalan tersisa setelah perbaikan dan pengujian ulang.

## Distribusi Integration Test akhir

| File | Test | Assertion |
|---|---:|---:|
| `AdministrationIntegrationTest.php` | 8 | 42 |
| `ChecklistLifecycleIntegrationTest.php` | 4 | 36 |
| `OperationalFeaturesTest.php` | 16 | 75 |
| `PrimaApiTest.php` | 15 | 90 |
| `RegionalManagerTest.php` | 3 | 38 |
| `RemainingRoutesIntegrationTest.php` | 4 | 28 |
| `SecurityAndLifecycleIntegrationTest.php` | 7 | 46 |
| `TerminalIsolationTest.php` | 3 | 17 |
| `VehicleAndRegionalIntegrationTest.php` | 3 | 26 |
| **Total** | **63** | **398** |

## Perintah dan hasil akhir

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite Feature
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe artisan route:list --path=api/v1 --except-vendor
```

| Pemeriksaan | Hasil aktual | Status |
|---|---|---|
| Integration/Feature suite | 63 test, 398 assertion, 2,534 detik | Lulus |
| Regression seluruh backend | 182 test, 581 assertion, 2,951 detik | Lulus |
| Laravel Pint | Tidak ada pelanggaran format | Lulus |
| Route API | 61 route berhasil dimuat | Lulus |

## Batas yang wajib masuk System Testing

Integration Testing ini belum dan tidak seharusnya mengklaim pengujian terhadap:

- PostgreSQL produksi dan transaksi pada volume data nyata;
- executable `pg_dump` dan `pg_restore` serta restore destruktif;
- SMTP nyata dan reputasi/delivery email;
- Cloudflare Turnstile melalui internet;
- cron host, Docker, Nginx, HTTPS, reverse proxy, dan permission container;
- browser React, responsivitas UI, kompatibilitas perangkat, serta performa;
- backup disaster recovery end-to-end.

Semua boundary tersebut telah diaudit dan sengaja dipindahkan ke System Testing
agar klasifikasi V-Model pada laporan skripsi tetap benar.

## Kesimpulan

Audit menyeluruh Integration Testing selesai dengan 63 test dan 398 assertion.
Seluruh 61 route API mempunyai bukti request integrasi, seluruh regression lulus,
dan satu defect endpoint update checklist berhasil ditemukan serta diperbaiki.
Tahap berikutnya bukan menambah Integration Test secara acak, melainkan System
Testing pada dependency produksi dan alur browser end-to-end.
