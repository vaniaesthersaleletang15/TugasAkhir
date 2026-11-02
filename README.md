# PRIMA Regional — Sistem Checklist Mobil Tangki

Aplikasi checklist mobil tangki berbasis Laravel (backend) dan React (frontend).

Indeks dokumentasi tersedia pada [`docs/README.md`](docs/README.md), sedangkan
struktur aplikasi dan aturan pemeliharaan tersedia pada
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

- Pengembangan lokal: `docker compose up -d --build`, lalu buka http://localhost:8092
- Produksi: image diambil dari GHCR, lihat `compose.production.yaml`

## CI/CD

Setiap push ke `main` menjalankan test backend, build frontend, dan validasi
Docker Compose. Jika seluruh CI sukses, workflow release akan:

1. membangun image backend dan frontend;
2. menerbitkannya ke GHCR dengan tag commit yang immutable;
3. terhubung ke server production melalui SSH;
4. menjalankan `git pull --ff-only`, menarik image baru, dan mengganti container.

Konfigurasikan GitHub Environment bernama `production` dengan secrets berikut:

- `PROD_SSH_HOST`: domain atau IP server;
- `PROD_SSH_PORT`: port SSH (opsional, default `22`);
- `PROD_SSH_USER`: user deployment di server;
- `PROD_SSH_PRIVATE_KEY`: private key SSH khusus GitHub Actions;
- `PROD_SSH_KNOWN_HOSTS`: hasil `ssh-keyscan -H <host>` yang sudah diverifikasi.

Tambahkan environment variable `PROD_DEPLOY_PATH` berisi direktori checkout repo
di server, misalnya `/opt/prima`. Direktori tersebut harus memiliki `.env`
production, remote Git yang dapat diakses, serta akses untuk menjalankan Docker.
Jika package GHCR bersifat private, login satu kali di server menggunakan akun
atau token yang memiliki izin `read:packages`.

Setelah seluruh secret dan server siap, tambahkan variable
`PROD_DEPLOY_ENABLED=true` pada GitHub Environment `production`. Tanpa variable
tersebut, image tetap dibangun dan diterbitkan, tetapi job deployment dilewati
agar repository baru tidak mencoba mengakses server yang belum dikonfigurasi.

Perubahan lokal baru masuk pipeline setelah di-commit dan di-push ke `main`.

## Regional Manager Supply & Distribution

Role `regional_manager` menyediakan akses hanya-baca untuk PT Pertamina Patra Niaga Regional Sulawesi. Menu yang tersedia meliputi Dashboard Regional, Data Checklist Regional, Monitoring Masa Berlaku E-KIM, Laporan Regional/XLSX, dan Audit Aktivitas Regional.

Migrasi regional membuat lima master terminal tetap: **IT Bitung, IT Gorontalo, IT Donggala, IT Kendari, dan IT Makassar**. Data lama ditempatkan pada IT Bitung. Daftar terminal tidak dikelola melalui antarmuka agar struktur Regional Sulawesi tetap konsisten.

Regional Manager membuat akun SPV HSSE dan menentukan terminalnya melalui menu **Kelola SPV HSSE**. Setiap SPV hanya dapat mengelola akun Tim HSSE, Integrated Terminal Manager, dan Transportir pada terminalnya sendiri. Pendaftaran Transportir wajib memilih terminal tujuan dan hanya muncul pada SPV terminal tersebut.

Akun regional dapat dibuat melalui menu **Kelola Akun User** atau otomatis oleh seeder menggunakan variabel berikut:

```env
PRIMA_REGIONAL_MANAGER_USERNAME=regional_manager
PRIMA_REGIONAL_MANAGER_NAME="Regional Manager Supply & Distribution"
PRIMA_REGIONAL_MANAGER_EMAIL=regional.manager@example.pertamina.com
PRIMA_REGIONAL_MANAGER_PASSWORD=ganti-dengan-password-kuat
```

Setelah memperbarui konfigurasi, jalankan `php artisan migrate --force` dan `php artisan db:seed --force` pada backend.
