# Arsitektur dan Pedoman Pemeliharaan PRIMA

## Struktur utama

- `backend/app/Http/Controllers/Api`: menerima request, melakukan validasi HTTP,
  dan menyusun response. Logika infrastruktur yang dapat digunakan ulang tidak
  ditempatkan di controller.
- `backend/app/Http/Controllers/Concerns`: perilaku HTTP bersama seperti format
  response, pemeriksaan role, pengaturan sistem, dan audit trail.
- `backend/app/Services`: proses aplikasi atau infrastruktur yang dapat diuji dan
  digunakan dari controller maupun command, seperti backup, retensi, SMTP, dan
  verifikasi CAPTCHA.
- `backend/app/Domain`: aturan bisnis murni yang tidak bergantung pada HTTP atau
  database dan menjadi sasaran utama Unit Testing.
- `backend/app/Console/Commands`: pintu masuk proses terjadwal atau administratif.
- `frontend/src/components`: komponen tampilan yang dapat dipakai lintas halaman.
- `frontend/src/features`: komponen yang khusus untuk satu fitur, misalnya
  halaman login dan integrasi CAPTCHA.
- `frontend/src/layouts`: kerangka aplikasi terautentikasi, navigasi, dan
  pengaturan route berdasarkan role.
- `frontend/src/pages`: komposisi halaman berdasarkan area bisnis, misalnya
  dashboard beserta komponen statistiknya.
- `frontend/src/hooks`: hook React yang dapat digunakan ulang tanpa bergantung
  pada halaman tertentu.
- `frontend/src/config`: konstanta domain bersama. Label role dan status tidak
  boleh didefinisikan ulang di halaman.
- `frontend/src/api.js`: satu-satunya pintu akses HTTP dari frontend.
- `frontend/src/App.jsx`: composition root frontend. File ini hanya mengatur
  status autentikasi dan memilih route publik atau layout terautentikasi.

## Aturan penempatan kode

1. Controller hanya menangani otorisasi, validasi request, pemanggilan service,
   dan response.
2. Proses yang dipakai lebih dari satu controller/command harus menjadi service
   atau concern bersama.
3. Nilai role dan status harus menggunakan konstanta domain bersama.
4. Data rahasia dan konfigurasi mesin tidak boleh ditulis langsung di source code.
5. Penataan ulang kode tidak boleh mengubah kontrak endpoint tanpa pembaruan test dan
   dokumentasi.
6. Setiap perubahan wajib dicatat pada `docs/perubahan-harian`, sedangkan hasil
   pengujiannya dicatat pada `docs/dokumentasi-testing`.
7. Komponen halaman, layout, fitur, dan hook tidak ditempatkan kembali di
   `App.jsx`; gunakan folder sesuai tanggung jawabnya.

## Pemeriksaan sebelum perubahan digabungkan

Backend:

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe vendor\bin\phpunit
```

Frontend:

```powershell
cd frontend
npm exec prettier -- --check src
npm run build
```

Gunakan database pengujian terpisah. Konfigurasi PHPUnit saat ini memakai SQLite
in-memory sehingga tidak mengubah database operasional.

Untuk menjalankan Unit Testing saja:

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite Unit
```
