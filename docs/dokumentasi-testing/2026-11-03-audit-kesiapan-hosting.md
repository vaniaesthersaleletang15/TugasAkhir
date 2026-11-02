# Audit Kesiapan Hosting dan Regresi — 3 November 2026

## Tujuan

Memastikan aplikasi dapat diuji, dibangun, dan dikemas untuk produksi tanpa kerentanan dependency yang diketahui, serta mencatat temuan gagal/warning dan hasil perbaikannya sesuai proses V-Model.

## Lingkungan

- Windows, PowerShell, PHP 8.2.12, PHPUnit 11.5.56.
- Node.js/npm dengan Vite 8.3.5.
- Docker Engine 29.7.2.
- Database test SQLite in-memory; konfigurasi produksi tervalidasi untuk PostgreSQL.

## Riwayat temuan awal

| Pemeriksaan | Hasil awal | Status |
|---|---|---|
| Backend regression | 218 test dan 656 assertion lulus | Lulus |
| Frontend unit | 5 test lulus | Lulus |
| Frontend production build | Build berhasil | Lulus |
| Composer security audit | 4 advisory pada 3 package; 2 advisory tingkat tinggi | Gagal/harus diperbaiki |
| npm production audit | 2 dependency terdampak advisory tingkat tinggi | Gagal/harus diperbaiki |
| Laravel `optimize:clear` tanpa environment deployment | Gagal menghapus database cache karena konfigurasi default menunjuk SQLite kosong tanpa tabel `cache` | Warning lingkungan lokal |
| Test setelah SQLite kosong dihapus | 218 test lulus, tetapi 119 test memiliki warning pembacaan `.env` yang tidak tersedia | Warning |
| Prettier | `main.jsx` dan `styles.css` belum sesuai format | Gagal format |
| Compose dasar dan overlay produksi | Konfigurasi valid; muncul warning akses `config.json` Docker dari sandbox | Lulus dengan warning lingkungan |

Kegagalan `optimize:clear` tidak terjadi pada konfigurasi produksi karena container menerima koneksi PostgreSQL serta menjalankan migration sebelum service aktif. SQLite kosong kemudian dihapus karena bukan database aplikasi maupun database test.

## Perbaikan

- Memperbarui CommonMark dan Symfony ke versi yang menutup advisory.
- Memperbarui Vite ke 8.3.5 dan mengunci dependency frontend.
- Menambahkan `.env.testing` khusus SQLite in-memory agar test tidak membaca `.env` produksi/lokal.
- Menghapus kode serta scaffold yang tidak digunakan dan menambahkan Docker ignore untuk kedua konteks build.
- Memperbaiki karakter mojibake pada halaman login dan dashboard yang ditemukan melalui pencarian statis source.
- Menjalankan Prettier terhadap file frontend yang dilaporkan.

## Hasil verifikasi akhir

### Backend regression

```text
C:\xampp\php\php.exe artisan test
218 passed (656 assertions)
0 gagal, 0 warning
Durasi 5,31 detik
```

### Frontend

```text
npm.cmd run test:unit
5 test lulus, 0 gagal

npm.cmd run build
Vite 8.3.5
1893 modul ditransformasi
Build final berhasil dalam 466 ms

npm.cmd exec prettier -- --check src
All matched files use Prettier code style
```

### Keamanan dependency

```text
composer audit --locked --no-dev
No security vulnerability advisories found.

npm audit --omit=dev
found 0 vulnerabilities
```

### Kualitas backend

```text
composer validate --strict
composer.json is valid

vendor/bin/pint --test
passed
```

### Konfigurasi dan image deployment

```text
docker compose -f compose.yaml config --quiet
Exit code 0

docker compose -f compose.yaml -f compose.production.yaml config --quiet
Exit code 0 dengan seluruh variabel wajib dummy untuk validasi

docker compose -f compose.yaml build backend frontend
prima-regional-backend:latest Built
prima-regional-frontend:latest Built
```

Docker build frontend juga menjalankan `npm ci` dan melaporkan nol kerentanan. Docker build backend memasang dependency production dari lock file yang telah diaudit.

## Kesimpulan

Status teknis akhir: **lulus dan siap dilanjutkan ke deployment staging/hosting**. Tidak ditemukan kegagalan test, build, format, compose, image build, maupun advisory dependency pada pemeriksaan akhir.

Kesimpulan ini belum menggantikan UAT. Setelah aplikasi tersedia melalui domain HTTPS, lakukan smoke test pada server dan UAT empat role untuk memastikan DNS, TLS, email SMTP, Turnstile, persistent volume, dan proses kerja aktual berjalan pada lingkungan nyata.

## Referensi perubahan

Lihat [perubahan harian 3 November 2026](../perubahan-harian/2026-11-03.md).
