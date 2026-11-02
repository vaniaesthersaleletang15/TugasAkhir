# Dokumentasi Testing - 2026-10-29 - Analisis System Testing Tahap Awal

## Referensi

- [Audit otorisasi dan cabang Integration Testing](2026-10-29-integration-testing-audit-otorisasi-cabang.md)
- [Perubahan harian 29 Oktober 2026](../perubahan-harian/2026-10-29.md)

## Tujuan

System Testing memeriksa PRIMA sebagai sistem yang dikemas dan dijalankan,
bukan hanya fungsi atau interaksi class. Audit mencakup runtime PHP, dependency,
migrasi, scheduler, route cache, HTTP server, security header, frontend build,
Docker Compose, CI/CD, PostgreSQL, storage, backup/restore, SMTP, Turnstile,
reverse proxy, dan kesiapan deployment.

## Lingkungan aktual

| Komponen | Nilai |
|---|---|
| Sistem operasi | Windows |
| PHP | 8.2.12 dari XAMPP |
| Laravel | 12.69.2 |
| Node.js | 22.22.0 |
| npm | 10.9.4 |
| Docker CLI | 29.7.2 |
| Docker Compose | 5.5.0 |
| Database smoke test | SQLite 3.39.2 sementara |
| Timezone aplikasi | Asia/Makassar |

PHP lokal menyediakan extension penting: `pdo_pgsql`, `pgsql`, `pdo_sqlite`,
`openssl`, `curl`, `intl`, `mbstring`, `dom`, `fileinfo`, dan `zip`.

## Matriks modul System Testing

| Modul sistem | Yang harus dibuktikan | Hasil tahap awal |
|---|---|---|
| Backend runtime | PHP, extension, Composer, bootstrap Laravel | Lulus |
| Database schema | Migrasi dari database kosong dan jumlah tabel | Lulus pada SQLite sementara |
| Scheduler | Jadwal notifikasi, backup, dan retensi terdaftar | Lulus setelah database dimigrasikan |
| HTTP application | Health endpoint, public API, protected API | Lulus pada Laravel local server |
| Security headers | nosniff, frame, referrer, permission dan no-store API | Lulus |
| Route packaging | Seluruh route dapat di-cache dan dimuat | Lulus |
| Frontend | Unit config, production build, asset generation | Lulus |
| Docker Compose | Sintaks development dan production overlay | Lulus setelah perbaikan CI env |
| CI pipeline | Backend test, frontend test/build, Compose validation | Diperbaiki dan tervalidasi statis |
| Docker runtime | PostgreSQL/backend/frontend/scheduler/queue bersama | Belum dijalankan: akses daemon ditolak |
| PostgreSQL nyata | Migrasi, query katalog, transaksi, concurrency | Belum dijalankan |
| Backup/restore nyata | `pg_dump`, checksum, `pg_restore`, disaster recovery | Belum dijalankan |
| SMTP dan Turnstile nyata | Delivery email dan validasi Cloudflare melalui internet | Belum dijalankan |
| Reverse proxy/TLS | Nginx, Caddy, HTTPS dan forwarded headers | Belum dijalankan end-to-end |
| Browser React | Navigasi, form, responsivitas dan compatibility | Belum dijalankan |
| Performance | Response time, concurrent user dan kapasitas | Belum dijalankan |

Status "belum dijalankan" tidak dianggap lulus atau gagal aplikasi. Bagian
tersebut membutuhkan Docker daemon, layanan eksternal, browser, atau lingkungan
yang mendekati produksi.

## Skenario yang dijalankan

| ID | Skenario | Hasil aktual | Status |
|---|---|---|---|
| SYS-001 | Pemeriksaan versi runtime PHP/Node/npm | PHP 8.2.12, Node 22.22.0, npm 10.9.4 | Lulus |
| SYS-002 | Pemeriksaan extension PHP | Extension database, crypto, HTTP, XML dan ZIP tersedia | Lulus |
| SYS-003 | Validasi `composer.json` strict | `composer.json is valid` | Lulus |
| SYS-004 | Regression backend | 222 test, 633 assertion | Lulus |
| SYS-005 | Laravel Pint | Tidak ada pelanggaran format | Lulus |
| SYS-006 | Migrasi database kosong | Seluruh 10 migration selesai | Lulus |
| SYS-007 | Pemeriksaan schema | 23 tabel terbentuk | Lulus |
| SYS-008 | Scheduler registration | Tiga jadwal terdaftar | Lulus setelah perbaikan lingkungan test |
| SYS-009 | Route cache | Route cache berhasil dibuat dan dibersihkan | Lulus |
| SYS-010 | Unit frontend pada pipeline | 5 test, 5 lulus | Lulus |
| SYS-011 | Production frontend build | 1.889 module, asset CSS dan JS terbentuk | Lulus |
| SYS-012 | Prettier frontend | Seluruh file test/config sesuai format | Lulus |
| SYS-013 | Compose development config | Konfigurasi dapat di-resolve | Lulus dengan warning akses Docker config lokal |
| SYS-014 | Compose production overlay | Gagal awal karena env Regional Manager hilang; lulus setelah perbaikan | Lulus setelah perbaikan |
| SYS-015 | HTTP health | `GET /up` menghasilkan 200 | Lulus |
| SYS-016 | Public terminal API | HTTP 200 dan lima terminal Sulawesi | Lulus |
| SYS-017 | CAPTCHA config API | HTTP 200 dan disabled sesuai env testing | Lulus |
| SYS-018 | Protected API tanpa token | HTTP 401 | Lulus |
| SYS-019 | HTTP security headers | Seluruh header utama hadir; API `no-store` | Lulus |
| SYS-020 | Docker container runtime | Daemon tidak dapat diakses dari sesi pengujian | Belum dijalankan |

## Detail HTTP smoke test

Aplikasi dijalankan dengan Laravel local server pada `127.0.0.1:8765` dan
database SQLite sementara yang sudah dimigrasikan. Hasil:

| Pemeriksaan | Nilai |
|---|---|
| `/up` | 200 |
| `/api/v1/public/terminals` | 200, lima terminal |
| `/api/v1/auth/captcha-config` | 200 |
| `/api/v1/users` tanpa token | 401 |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | kamera, mikrofon, dan geolocation dinonaktifkan |
| `Cache-Control` API | `no-store, private` |

Server dihentikan setelah smoke test dan file database sementara dihapus.

## Kegagalan dan perbaikan

### FAIL-SYS-001 - Production Compose gagal divalidasi di CI

Hasil awal:

```text
PRIMA_REGIONAL_MANAGER_EMAIL is missing a value
PRIMA_REGIONAL_MANAGER_PASSWORD is missing a value
```

Overlay produksi mewajibkan kedua variabel, tetapi job `Validasi Compose` pada
GitHub Actions belum menyediakannya. Perbaikan:

- menambahkan email dan password dummy Regional Manager hanya untuk validasi CI;
- menjalankan ulang `docker compose ... config -q`;
- production overlay berhasil di-resolve.

### FAIL-SYS-002 - Scheduler list gagal pada database lokal kosong

Eksekusi awal `artisan schedule:list` gagal karena cache driver database mencari
tabel `cache_locks` pada SQLite lokal yang belum dimigrasikan. Ini merupakan
masalah kesiapan environment lokal, bukan kesalahan definisi jadwal.

Pengujian ulang dilakukan pada SQLite sementara setelah `migrate:fresh` dengan
cache array. Tiga jadwal berhasil dimuat:

- notifikasi E-KIM setiap hari pukul 06:00;
- backup database setiap hari pukul 01:00;
- retensi setiap hari pukul 02:00.

### AUD-SYS-001 - CI frontend belum menjalankan Unit Testing

Job frontend sebelumnya hanya menjalankan `npm run build`. Pipeline diperbarui
agar menjalankan `npm run test:unit` sebelum build, sehingga kegagalan konfigurasi
role/status dapat menghentikan pipeline.

## Pemeriksaan Docker dan deployment

Validasi file Compose tidak memerlukan daemon dan berhasil. Pengujian runtime
container belum dapat dilakukan karena sesi ini menerima:

```text
permission denied while trying to connect to the docker API
```

Terdapat juga warning bahwa `C:\Users\Pongo\.docker\config.json` tidak dapat
dibaca. Karena itu hasil berikut belum boleh diklaim lulus:

- health container PostgreSQL, backend, frontend, scheduler dan queue;
- koneksi Nginx ke backend melalui network Compose;
- Caddy TLS dan redirect HTTPS;
- named volume serta permission user `www-data`;
- recovery/restart container;
- Mailpit dan import legacy MariaDB.

## Perintah utama

```powershell
docker compose -f compose.yaml config -q
docker compose -f compose.yaml -f compose.production.yaml config -q

cd backend
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe C:\xampp\php\composer.phar validate --strict --no-check-publish
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe artisan route:cache
C:\xampp\php\php.exe artisan route:clear

cd ..\frontend
npm.cmd run test:unit
npm.cmd run build
npx.cmd prettier --check package.json tests/unit/domain.test.js
```

## Kesimpulan tahap awal

System Testing lokal membuktikan build frontend, bootstrap Laravel, migration,
scheduler definition, route cache, HTTP smoke flow, autentikasi boundary, dan
security headers. Satu defect CI produksi berhasil ditemukan dan diperbaiki.

System Testing belum lengkap sampai stack Docker dengan PostgreSQL nyata dapat
dijalankan serta backup/restore, SMTP, Turnstile, reverse proxy, browser, dan
performance selesai diuji. Dokumen ini sengaja membedakan hasil lulus dari hasil
belum dijalankan agar bukti V-Model tidak menyesatkan.
