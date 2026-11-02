# Verifikasi Kesesuaian FR dan NFR — 1 November 2026

## Tujuan dan baseline

Pengujian memverifikasi implementasi terhadap 79 kebutuhan fungsional (FR-001–FR-079) dan 10 kebutuhan nonfungsional (NFR-001–NFR-010) pada proposal. Pemeriksaan dilakukan melalui audit source code, migrasi, route/otorisasi, pengujian unit dan integrasi, build frontend, serta inspeksi konfigurasi Docker dan responsive CSS.

## Matriks keterlacakan kebutuhan fungsional

| ID | Status implementasi | Bukti utama |
|---|---|---|
| FR-001–FR-004 | Sesuai | Login email/password, CAPTCHA backend, lockout, logout dan pencabutan token diuji pada `PrimaApiTest` dan `SecurityAndLifecycleIntegrationTest`. |
| FR-005 | Sesuai | API dan halaman Profil; test verifikasi password, update profil/password, serta audit pada `RequirementsComplianceTest`. |
| FR-006–FR-010 | Sesuai | Review pendaftaran bercatatan, role operasional, lifecycle akun, terminal/transporter scope, dan matriks otorisasi backend/frontend. |
| FR-011–FR-014 | Sesuai | Seeder terminal Sulawesi, foreign key/scope terminal, dan monitoring Regional Manager diuji pada `TerminalIsolationTest` serta `RegionalManagerTest`. |
| FR-015–FR-016 | Sesuai | CRUD/status master transportir dan relasi many-to-many Pengurus; diuji pada `RequirementsComplianceTest`. |
| FR-017–FR-020 | Sesuai | Registrasi lengkap, normalisasi/keunikan polisi, approval kendaraan SPV, dan pencatatan keputusan/riwayat. |
| FR-021–FR-024 | Sesuai | Update kendaraan dibatasi relasi transportir; HSSE/Manager/Regional read-only; `vehicle_histories` menyimpan snapshot, aktor, waktu, IP. |
| FR-025–FR-027 | Sesuai | Private storage, validasi upload, dan foreign key dokumen ke kendaraan/transportir/terminal. |
| FR-028–FR-032 | Sesuai | Review bercatatan, delete hanya Pending, tanggal berlaku, authorized download, dan halaman kelengkapan dokumen regional read-only. |
| FR-033–FR-035 | Sesuai | Template SPBU/Industri, tema berbeda, dan autofill kendaraan dari master aktif tersedia pada editor checklist. |
| FR-036–FR-040 | Sesuai | Validasi pemeriksa/tanggal/E-KIM, XOR Baik/Tidak, catatan Tidak, kebijakan tanggal surat, serta hash versi template. |
| FR-041–FR-043 | Sesuai | Status internal memetakan Draft/Submitted/Approved HSSE/Approved Final/Rejected/Expired; final immutable; filter lengkap terminal–periode. |
| FR-044–FR-047 | Sesuai | Approval HSSE memeriksa checklist+dokumen; Manager hanya setelah HSSE; nama harus akun aktif; approval snapshot tersimpan. |
| FR-048–FR-051 | Sesuai | HMAC/hash, QR verifikasi minimal, alasan reject wajib, dan endpoint final hanya untuk Manager. |
| FR-052–FR-053 | Sesuai | Kalkulator status hanya memakai E-KIM Valid Until; tanggal dokumen hanya referensi/validasi pemeriksa. |
| FR-054–FR-058 | Sesuai | Scheduler/log, email sebelum dan setelah kedaluwarsa, notification key idempoten, serta scope notifikasi Pengurus. |
| FR-059–FR-060 | Sesuai | Statistik E-KIM terminal untuk SPV/HSSE/Manager dan agregasi regional per terminal. |
| FR-061–FR-065 | Sesuai | Dashboard role-specific untuk SPV, HSSE, Manager, Pengurus, dan Regional Manager. |
| FR-066–FR-070 | Sesuai | Drill-down/filter regional, agregat status, laporan regional, dan penolakan seluruh endpoint mutasi untuk Regional Manager. |
| FR-071–FR-072 | Sesuai | CSS print landscape dan ekspor XLSX terfilter/scope; arsip XLSX diuji dapat dibuka. |
| FR-073–FR-075 | Sesuai | Audit aktivitas penting, scope terminal SPV, dan audit regional read-only dengan filter. |
| FR-076 | Sesuai | Pengaturan sesi, retensi, backup, maintenance, dan audit perubahan. |
| FR-077–FR-078 | Sesuai | Backup terjadwal+checksum; restore berlapis dengan checksum, approval Manager, re-auth SPV, proses dan audit. |
| FR-079 | Sesuai | `process_runs` dan halaman monitoring menampilkan waktu, status, hasil, serta error notifikasi/backup/restore. |

Catatan terminologi status: `pending_hsse` = Submitted, `signed_hsse` = Approved HSSE, dan `approved` = Approved Final. Pemetaan ini dipertahankan agar kompatibel dengan data dan kode lama.

## Matriks kebutuhan nonfungsional

| ID | Status implementasi | Bukti utama |
|---|---|---|
| NFR-001 | Sesuai | Hash password, CAPTCHA, throttle/lockout, Sanctum, timeout sesi, security tests. |
| NFR-002 | Sesuai | Role, terminal, dan transporter scoping; cross-scope tests serta role authorization matrix. |
| NFR-003 | Sesuai | Disk private dan download controller berotorisasi; lifecycle dokumen diuji. |
| NFR-004 | Sesuai pada test normal | Empat endpoint umum diuji `<3 detik`; seluruhnya lulus. Uji beban produksi tetap diperlukan untuk kapasitas nyata. |
| NFR-005 | Sesuai pada implementasi | Media query mobile/tablet dan build frontend lulus. Validasi akhir pada perangkat nyata masuk UAT. |
| NFR-006 | Sesuai | Audit menyimpan terminal, pengguna, aksi, objek/deskripsi, waktu, dan IP untuk aktivitas penting. |
| NFR-007 | Sesuai | Backup terjadwal dan restore terkontrol dengan checksum, Manager approval, re-auth, process log, audit. |
| NFR-008 | Sesuai | Monitoring `process_runs`, status scheduler/email/backup, dan error terakhir tersedia untuk SPV. |
| NFR-009 | Sesuai konfigurasi | `compose.yaml` memuat frontend, backend, PostgreSQL, scheduler, queue; overlay produksi memuat Caddy. |
| NFR-010 | Sesuai | Domain/service modular, migrasi, dokumentasi harian/testing, Pint, unit/integration/system tests. |

## Riwayat temuan dan perbaikan

Hasil gagal tidak dihapus dari dokumentasi:

1. Setelah validasi FR diperketat, regression awal menghasilkan 4 kegagalan karena fixture lama belum mengirim `transporter_id` atau catatan approval. Fixture diperbarui agar merepresentasikan kontrak baru.
2. Pengujian baru pertama kali menghasilkan 4 kegagalan: asumsi status HTTP master transportir, pergantian guard autentikasi antaraktor pada test, fixture kendaraan belum lengkap, dan ekstensi backup test tidak sesuai policy. Seluruh penyebab diperbaiki lalu diuji ulang.
3. Audit scheduler menemukan query hanya memilih status `approved` setelah data kedaluwarsa telah diubah menjadi `expired`. Query diperbaiki agar email kedaluwarsa tetap dikirim.
4. Perintah `php artisan test` menampilkan warning karena file `.env` lokal tidak tersedia. Pengujian final dijalankan langsung melalui PHPUnit dengan seluruh variabel testing dari `phpunit.xml`, sehingga selesai tanpa warning.
5. Perintah `npm test` gagal karena script tersebut memang tidak didefinisikan. Perintah resmi project adalah `npm run test:unit`; pengujian ulang dengan perintah benar lulus.
6. Pemanggilan Pint melalui wrapper pertama gagal karena executable `php` tidak ada pada `PATH`; dijalankan ulang menggunakan `C:\xampp\php\php.exe` dan formatting berhasil.
7. `docker compose config --quiet` selesai dengan exit code 0, tetapi Docker CLI menampilkan warning bahwa `C:\Users\Pongo\.docker\config.json` tidak dapat dibaca pada sandbox. Struktur Compose tetap valid; warning berasal dari izin konfigurasi Docker pengguna, bukan sintaks project.
8. Penguatan scope transportir pada daftar/upload/unduh/hapus dokumen dan notifikasi menghasilkan 4 kegagalan regression karena fixture lama tidak memiliki relasi master transportir. Fixture dilengkapi dengan kendaraan, `transporter_id`, dan `transporter_user`; pengujian ulang kembali lulus 228/228.
9. Penguatan scope saat pembuatan notifikasi menemukan 1 fixture alur approval final tanpa relasi master transportir. Fixture dilengkapi dan regression akhir kembali lulus seluruhnya.

## Hasil akhir

### Backend

```text
C:\xampp\php\php.exe vendor/bin/phpunit
OK (228 tests, 676 assertions)
Waktu: 4.476 detik
Memory: 60 MB
```

### Frontend unit

```text
npm.cmd run test:unit
5 test lulus, 0 gagal
```

### Frontend build

```text
npm.cmd run build
Vite: 1894 modul ditransformasi; build berhasil
```

### Formatting

```text
C:\xampp\php\php.exe vendor/bin/pint [file terdampak]
Berhasil; 3 file diperbaiki otomatis

npx.cmd prettier --write [file frontend terdampak]
Berhasil
```

### Konfigurasi Docker

```text
docker compose config --quiet
Exit code: 0 (valid)
Warning lingkungan: akses ke konfigurasi Docker pengguna ditolak oleh sandbox
```

## Kesimpulan

Tidak ada gap implementasi FR/NFR yang diketahui setelah pengujian ulang. Status “sesuai” pada dokumen ini berarti kebutuhan sudah tersedia dan lolos verifikasi code-level/otomatis sesuai bukti di atas. Sertifikasi penerimaan 100% secara operasional tetap memerlukan UAT pihak Pertamina serta validasi pada SMTP, PostgreSQL restore, beban, browser, dan perangkat produksi yang sebenarnya.

Dokumentasi perubahan: [Perubahan Harian 1 November 2026](../perubahan-harian/2026-11-01.md).
