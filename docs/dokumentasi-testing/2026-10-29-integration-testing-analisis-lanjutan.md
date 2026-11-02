# Dokumentasi Testing - 2026-10-29 - Analisis Lanjutan Integration Testing

> Catatan: dokumen ini adalah snapshot tahap 59 test. Audit akhir tersedia pada
> [Audit Menyeluruh Integration Testing](2026-10-29-integration-testing-audit-menyeluruh.md)
> dengan 63 test, 398 assertion, dan seluruh 61 route API tercakup.

## Referensi

- [Integration Testing tahap awal](2026-10-29-integration-testing-tahap-awal.md)
- [Perubahan harian 29 Oktober 2026](../perubahan-harian/2026-10-29.md)

## Latar belakang

Integration Testing tahap awal memiliki baseline 37 test dan kemudian bertambah
menjadi 44 test. Analisis lanjutan dilakukan karena jumlah test saja tidak cukup
untuk menunjukkan cakupan. Audit ini memetakan 61 route API, public method pada
tiga controller, dependency antarmodul, perubahan beberapa tabel, otorisasi,
isolasi terminal, serta boundary storage dan layanan eksternal.

## Metode audit

Setiap modul dinilai berdasarkan:

1. route benar-benar menerima request melalui HTTP kernel;
2. middleware autentikasi dan availability ikut dijalankan;
3. controller berinteraksi dengan database, model, policy, atau storage;
4. hasil response dan perubahan state database diperiksa;
5. jalur berhasil dan jalur gagal berisiko tinggi diuji;
6. data lintas terminal dan lintas pengguna tidak bocor.

## Cakupan route

Dari 61 route API, **57 route telah menerima request langsung dalam Feature atau
Integration Test**. Empat route berikut masih memerlukan tahap khusus:

| Route | Alasan belum diuji penuh | Tahap yang sesuai |
|---|---|---|
| `GET /documents` | Upload, review, download, dan delete sudah diuji; response daftar belum diuji tersendiri | Integration lanjutan |
| `GET /settings` | Query health memakai katalog PostgreSQL `pg_stat_user_tables`, tidak tersedia pada SQLite | Integration PostgreSQL/System Test |
| `POST /settings/backup` | Menjalankan pembuatan backup dan filesystem/database nyata | Integration PostgreSQL/System Test |
| `POST /settings/audit-retention` | Service retensi sudah diuji langsung; adapter endpoint belum diuji | Integration lanjutan |

Persentase route bukan pengganti branch coverage. Satu route dapat memiliki
banyak cabang status, role, validasi, dan kegagalan dependency.

## Matriks modul setelah audit lanjutan

| Modul | Integrasi yang diuji | Status |
|---|---|---|
| Authentication | CAPTCHA, portal, lockout, inactive account, reset token, logout dan revokasi token | Tercakup |
| User administration | Create, update, delete, toggle status, reset password, token revocation, audit | Tercakup |
| Registration | Submit, terminal scope, approve, reject, repeated review | Tercakup |
| System policy | Maintenance, session extension, settings clamp, audit setting | Tercakup kecuali `GET /settings` PostgreSQL |
| SMTP/notification | Validasi konfigurasi, enkripsi password, password tidak bocor, scheduler, cron regeneration | Tercakup dengan fake mail |
| Vehicle | Create, normalization, duplicate, assignment, claim idempotent, user/transportir lists, terminal scope | Tercakup |
| Vehicle document | Ownership upload, approve, reject, expiry sync, private download, delete storage dan record | Tercakup; endpoint list belum khusus |
| Checklist | Create, detail, update, submit, reject, reset, delete, query, filter, stats, template, scope | Tercakup |
| Digital signature/E-KIM | HSSE sign, manager approval, hash, verification, vehicle update, owner notification | Tercakup |
| Dashboard/report | Admin dashboard, regional dashboard, report, audit dan E-KIM filter | Tercakup |
| Multi-terminal | Registrasi, user, kendaraan, checklist, audit, alert, regional scope | Tercakup |
| Retention/backup | Retention service dan file cleanup | Sebagian; endpoint/backup PostgreSQL belum |

## Integration Test baru pada analisis lanjutan

### Administrasi

- penonaktifan user mencabut token dan menulis audit;
- reset password oleh SPV mengganti hash dan mencabut token lama;
- penolakan registrasi menyimpan alasan tanpa membuat user;
- penolakan dokumen menyimpan reviewer tanpa mengubah expiry checklist draft;
- nilai pengaturan sistem dibatasi dan dicatat ke audit;
- password SMTP dienkripsi dan tidak dikembalikan API;
- regenerasi cron membatalkan secret lama;
- audit dan alert admin terisolasi berdasarkan terminal.

### Checklist

- draft melewati create, detail, update, replace items, dan delete;
- checklist `pending_hsse` tidak dapat diedit;
- HSSE tidak dapat menghapus checklist non-draft;
- Admin dapat mereset ke draft dan melanjutkan lifecycle;
- dashboard, stats, list, template, dan transporter memakai scope terminal yang sama;
- detail checklist terminal lain menghasilkan 404.

### Kendaraan dan regional

- kendaraan yang dibuat User muncul pada daftar pembuat dan Transportir;
- Transportir hanya dapat claim kendaraan di terminal sendiri;
- claim kendaraan bersifat idempotent;
- Regional Manager dapat menonaktifkan dan mengaktifkan SPV;
- penonaktifan serta reset password SPV mencabut token dan menulis audit.

## Defect yang ditemukan

### DEF-INT-001 - Update checklist menghasilkan HTTP 500

Kondisi awal:

```text
PUT /api/v1/checklists/{id}
```

Route memakai closure dan memanggil `PrimaController::saveChecklist()` hanya
dengan objek Request. Setelah `ChecklistExpiryPolicy` menjadi dependency method,
closure tersebut tidak meneruskan dependency sehingga PHP melempar
`ArgumentCountError` dan response menjadi HTTP 500.

Perbaikan:

- route PUT diarahkan langsung ke controller action;
- parameter route ID diterima oleh `saveChecklist()`;
- ID route digabungkan ke request sebelum proses update;
- dependency `ChecklistExpiryPolicy` kembali di-resolve oleh container Laravel.

Hasil pengujian ulang:

- update draft berhasil HTTP 200;
- item lama diganti tanpa duplikasi;
- update non-draft ditolak HTTP 409;
- seluruh regression test lulus.

## Kegagalan dan koreksi pengujian

| ID | Hasil awal | Analisis | Tindakan | Hasil akhir |
|---|---|---|---|---|
| FAIL-INT-003 | Dua test lifecycle menerima HTTP 500 saat PUT | Defect route dependency injection | Memperbaiki route dan controller parameter | Jalur update lulus |
| FAIL-INT-004 | Test mengharapkan Admin gagal menghapus non-draft, aktual HTTP 200 | Kebijakan memang mengizinkan Admin; ekspektasi test tidak sesuai requirement saat ini | Menguji penolakan menggunakan role HSSE, sedangkan reset tetap Admin | Lulus dan sesuai policy |

FAIL-INT-003 merupakan defect aplikasi. FAIL-INT-004 merupakan koreksi skenario
test dan tidak dicatat sebagai defect aplikasi.

## Distribusi akhir

| File | Test | Assertion |
|---|---:|---:|
| `AdministrationIntegrationTest.php` | 8 | 42 |
| `ChecklistLifecycleIntegrationTest.php` | 4 | 36 |
| `OperationalFeaturesTest.php` | 16 | 75 |
| `PrimaApiTest.php` | 15 | 90 |
| `RegionalManagerTest.php` | 3 | 38 |
| `SecurityAndLifecycleIntegrationTest.php` | 7 | 46 |
| `TerminalIsolationTest.php` | 3 | 17 |
| `VehicleAndRegionalIntegrationTest.php` | 3 | 26 |
| **Total** | **59** | **370** |

## Hasil eksekusi akhir

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite Feature
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe artisan route:list --path=api/v1 --except-vendor
```

| Pemeriksaan | Hasil | Status |
|---|---|---|
| Integration/Feature suite | 59 test, 370 assertion, 2,414 detik | Lulus |
| Regression Unit + Integration | 178 test, 553 assertion, 2,894 detik | Lulus |
| Laravel Pint | Tidak ada pelanggaran format | Lulus |
| Route API | 61 route berhasil dimuat; PUT checklist menunjuk controller action | Lulus |

## Kesimpulan

Integration Testing tidak lagi hanya dinilai dari angka 37 test. Setelah audit
berbasis route dan dependency, suite memiliki 59 test dan 370 assertion serta
menemukan satu defect integrasi nyata yang tidak terlihat pada Unit Testing.
Empat route yang belum diuji penuh telah dicatat secara terbuka. Pengujian
PostgreSQL, backup/restore, SMTP nyata, dan infrastruktur deployment tetap harus
masuk System Testing agar hasil penelitian tidak melebih-lebihkan cakupan.
