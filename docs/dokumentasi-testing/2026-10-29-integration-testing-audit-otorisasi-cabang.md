# Dokumentasi Testing - 2026-10-29 - Audit Otorisasi dan Cabang Integration Testing

## Referensi

- [Audit menyeluruh Integration Testing](2026-10-29-integration-testing-audit-menyeluruh.md)
- [Perubahan harian 29 Oktober 2026](../perubahan-harian/2026-10-29.md)

## Latar belakang

Audit sebelumnya menghasilkan 63 test dan memastikan 61 route API menerima
request integrasi. Audit ini memperdalam pengujian dari cakupan route menjadi
cakupan cabang otorisasi, role, status autentikasi, dan isolasi Integrated
Terminal. Satu route yang lulus untuk satu role belum membuktikan bahwa role lain
ditolak atau bahwa mutasi lintas terminal aman.

## Fokus audit

1. enam role aplikasi terhadap kelompok endpoint utama;
2. request tanpa token terhadap route terproteksi;
3. create/update/delete checklist lintas terminal;
4. workflow checklist lintas terminal;
5. tanda tangan HSSE dan Manager lintas terminal;
6. regression seluruh workflow setelah aturan terminal diperketat.

## Matriks role yang diuji

Enam role diuji terhadap enam kelompok endpoint sehingga menghasilkan 36
kombinasi data provider.

| Endpoint | Admin | User | HSSE | Manager HSSE | Transportir | Regional Manager |
|---|---:|---:|---:|---:|---:|---:|
| `GET /checklists` | 200 | 200 | 200 | 200 | 403 | 200 |
| `GET /users` | 200 | 403 | 403 | 403 | 403 | 403 |
| `GET /documents` | 200 | 403 | 403 | 403 | 200 | 403 |
| `GET /regional/terminals` | 403 | 403 | 403 | 403 | 403 | 200 |
| `GET /my-vehicles` | 403 | 403 | 403 | 403 | 200 | 403 |
| `GET /user-vehicles` | 403 | 200 | 403 | 403 | 403 | 403 |

Semua 36 kombinasi menghasilkan status sesuai matriks. Enam kelompok endpoint
terproteksi juga diuji tanpa token dan seluruhnya menghasilkan HTTP 401.

## Defect keamanan yang ditemukan

### DEF-INT-002 - Mutasi checklist lintas terminal

Hasil awal tiga test keamanan:

```text
Tests: 3, Assertions: 3, Failures: 3
```

Ketiga request yang seharusnya HTTP 403 justru HTTP 200:

- Admin Terminal Bitung dapat membuat checklist dari kendaraan Terminal Kendari;
- Admin Terminal Bitung dapat update, workflow, atau delete checklist Kendari;
- HSSE/Manager Bitung dapat menandatangani checklist Kendari.

Penyebab:

- kendaraan dan checklist mutasi dicari berdasarkan ID tanpa pembatasan
  `terminal_id` pengguna;
- endpoint list dan detail sudah memiliki scope, tetapi endpoint mutasi belum
  menggunakan aturan yang sama.

Perbaikan:

- menambahkan pemeriksaan terminal terpusat pada `PrimaController`;
- create/update memastikan terminal kendaraan sama dengan terminal user;
- update memastikan checklist lama juga berada di terminal user;
- delete, workflow, dan signature menolak checklist terminal lain sebelum
  perubahan database atau pembuatan signature log;
- response lintas terminal menjadi HTTP 403 tanpa membocorkan atau mengubah data.

Hasil pengujian ulang:

```text
OK (3 tests, 10 assertions)
```

Tidak ada digital signature log yang dibuat dan status checklist asing tetap
tidak berubah.

## Kegagalan regression dan koreksi fixture

Setelah otorisasi terminal diperketat, regression pertama menghasilkan tiga
kegagalan lama:

| Test lama | Penyebab | Koreksi |
|---|---|---|
| Reject E-KIM dan notifikasi owner | Fixture checklist/kendaraan tidak mempunyai `terminal_id` | Menambahkan terminal milik HSSE pada fixture |
| Submit checklist gagal dan notifikasi owner | Fixture checklist/kendaraan tidak mempunyai `terminal_id` | Menambahkan terminal milik HSSE pada fixture |
| Save-sign-verify checklist | Fixture kendaraan tidak mempunyai `terminal_id` | Menambahkan terminal milik Admin pada fixture |

Proteksi aplikasi tidak dilonggarkan. Fixture diperbaiki agar merepresentasikan
data operasional multi-terminal yang valid. Regression berikutnya lulus.

## Distribusi Integration Testing akhir

| File | Test | Assertion |
|---|---:|---:|
| `AdministrationIntegrationTest.php` | 8 | 42 |
| `ChecklistLifecycleIntegrationTest.php` | 4 | 36 |
| `ChecklistTerminalAuthorizationIntegrationTest.php` | 3 | 10 |
| `OperationalFeaturesTest.php` | 16 | 75 |
| `PrimaApiTest.php` | 15 | 90 |
| `RegionalManagerTest.php` | 3 | 38 |
| `RemainingRoutesIntegrationTest.php` | 4 | 28 |
| `RoleAuthorizationMatrixIntegrationTest.php` | 37 | 42 |
| `SecurityAndLifecycleIntegrationTest.php` | 7 | 46 |
| `TerminalIsolationTest.php` | 3 | 17 |
| `VehicleAndRegionalIntegrationTest.php` | 3 | 26 |
| **Total** | **103** | **450** |

## Hasil akhir

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite Feature
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe artisan route:list --path=api/v1 --except-vendor
```

| Pemeriksaan | Hasil | Status |
|---|---|---|
| Integration/Feature suite | 103 test, 450 assertion, 3,927 detik | Lulus |
| Regression Unit + Integration | 222 test, 633 assertion, 4,155 detik | Lulus |
| Laravel Pint | Tidak ada pelanggaran format | Lulus |
| Route API | 61 route berhasil dimuat | Lulus |

## Interpretasi hasil

Penambahan dari 63 menjadi 103 test berasal dari 36 kombinasi role-endpoint,
satu pengujian kelompok tanpa token, dan tiga skenario keamanan terminal. Test
tidak dipecah hanya untuk menaikkan angka; setiap dataset merepresentasikan
keputusan akses yang berbeda dan dapat gagal secara independen.

Audit ini menunjukkan manfaat Integration Testing yang tidak dapat digantikan
Unit Testing: policy workflow dapat benar secara terisolasi, tetapi controller
tetap dapat memiliki celah karena tidak menerapkan scope terminal sebelum
memanggil policy tersebut.

## Batas selanjutnya

Backend telah memiliki cakupan route, matriks role utama, dan isolasi terminal
pada mutasi kritis. Pengujian berikutnya yang bernilai tinggi berada pada System
Testing: PostgreSQL nyata, backup/restore, SMTP, reverse proxy, scheduler host,
browser React, performa, serta recovery setelah kegagalan infrastruktur.
