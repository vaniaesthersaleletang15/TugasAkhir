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
