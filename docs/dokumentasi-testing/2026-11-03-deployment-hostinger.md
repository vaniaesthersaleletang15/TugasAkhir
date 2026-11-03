# Pengujian Deployment Hostinger — 3 November 2026

## Lingkungan

- Hostinger VPS, Ubuntu 24.04 LTS.
- Docker 29.9.2 dan Docker Compose 5.7.0.
- Domain staging/UAT: `ekim.179-197-254-89.sslip.io`.
- Layanan: PostgreSQL, Laravel/FrankenPHP, React/Nginx, queue, scheduler, dan Caddy.

## Temuan deployment awal

Build image backend dan frontend berhasil. PostgreSQL berhasil aktif, migration dan seeder berjalan, tetapi container backend masuk status `unhealthy`. Layanan frontend, queue, scheduler, dan Caddy belum dijalankan karena menunggu backend sehat.

Log container menunjukkan kegagalan berikut:

```text
The "/app/resources/views" directory does not exist.
```

Penyebabnya adalah entrypoint produksi menjalankan `php artisan optimize`, sedangkan pembersihan scaffold Laravel sebelumnya menghapus seluruh folder `resources/views`. Walaupun sistem tidak memakai Blade sebagai frontend, perintah cache view Laravel tetap memerlukan direktorinya.

## Perbaikan

Menambahkan `backend/resources/views/.gitignore` agar direktori standar Laravel selalu tersedia pada clone dan Docker build tanpa mengembalikan halaman Blade yang tidak digunakan.

## Verifikasi lanjutan

Regression test backend setelah perbaikan struktur:

```text
222 passed (669 assertions)
0 gagal
```

Hasil rebuild backend, health check container, HTTPS, API, dan smoke test dicatat setelah deployment ulang selesai.

### Temuan smoke test autentikasi

Request biasa tanpa header `Accept: application/json` ke endpoint terlindungi semula menghasilkan HTTP 500 dengan log `Route [login] not defined`. Middleware autentikasi Laravel mencoba redirect web karena request tidak menyatakan format JSON, sedangkan backend ini bersifat API-only.

Perbaikan mengatur redirect tamu untuk path `api/*` menjadi `null`, sehingga `AuthenticationException` selalu diproses oleh handler JSON Indonesia. Ditambahkan regression test khusus request tanpa header Accept JSON.

```text
LocalizedApiResponseTest: 5 passed (17 assertions)
Seluruh backend: 223 passed (673 assertions)
```
# Hasil Akhir Deployment dan Smoke Test

Deployment aplikasi ke VPS Hostinger telah berhasil diselesaikan pada 3 November 2026. Aplikasi sementara dapat diakses melalui `https://ekim.179-197-254-89.sslip.io` sambil menunggu domain permanen.

Hasil verifikasi akhir:

- Enam container utama berjalan: frontend, backend, queue, scheduler, PostgreSQL, dan Caddy.
- Health check backend dan PostgreSQL berstatus sehat.
- Seluruh 12 migrasi database berstatus `Ran`.
- HTTP dialihkan ke HTTPS dengan status 308 dan halaman utama HTTPS merespons 200.
- Sertifikat HTTPS Let's Encrypt berhasil diterbitkan dan tervalidasi.
- Endpoint terminal publik merespons 200 dalam sekitar 0,22 detik.
- Endpoint terlindungi tanpa sesi merespons 401 JSON berbahasa Indonesia, bukan error 500.
- Header keamanan CSP, HSTS, X-Content-Type-Options, X-Frame-Options, Permissions-Policy, dan Referrer-Policy tersedia.
- Pengujian backend lulus: 223 pengujian dengan 673 assertion.
- Backup awal database berhasil dibuat di `storage/app/private/backups/prima-db-2026-11-03.dump` dan tetap tersedia setelah container dibuat ulang.
- Tidak ditemukan log baru dengan level ERROR, CRITICAL, atau EMERGENCY setelah deployment akhir.

## Temuan Saat Deployment

1. Backend sempat tidak sehat karena direktori `backend/resources/views` tidak tersedia pada image. Direktori tersebut kemudian dipertahankan melalui `.gitignore` dan image dibangun ulang.
2. Permintaan endpoint API terlindungi tanpa header `Accept: application/json` sempat menghasilkan error 500 karena framework mencari route `login`. Redirect guest API diperbaiki dan dilindungi dengan regression test; hasil akhirnya adalah respons 401 JSON berbahasa Indonesia.

## Status Kesiapan

Lingkungan ini siap digunakan untuk smoke test lanjutan dan UAT. Sebelum dinyatakan sebagai produksi final, masih diperlukan:

- pemasangan domain permanen dan penerbitan ulang sertifikat untuk domain tersebut;
- penggantian Cloudflare Turnstile testing key dengan key untuk domain produksi;

## Penyesuaian akun SPV HSSE

Email login akun SPV HSSE pada hosting diubah menjadi `spvhsse@gmail.com`. Perubahan diverifikasi pada database dan `PRIMA_ADMIN_EMAIL` di `.env` produksi. Akun tetap berstatus aktif, password tidak berubah, serta file kredensial awal diperbaiki agar menggunakan label `email` sesuai kontrak endpoint login.
- konfigurasi SMTP produksi melalui pengaturan sistem;
- penyimpanan backup di lokasi terpisah dari VPS;
- pelaksanaan UAT dan verifikasi manual alur login untuk seluruh role.
