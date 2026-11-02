# Pengujian Responsif Mobile — 3 November 2026

## Tujuan

Memastikan struktur frontend tetap rapi pada tablet dan ponsel, khususnya pada lebar 820 px, 700 px, 430 px, dan batas minimum 320 px, tanpa mengubah fungsi bisnis atau hak akses pengguna.

## Cakupan

- login dan CAPTCHA;
- topbar serta drawer sidebar;
- heading, tombol, filter, form, panel, dan kartu metrik;
- tabel dashboard Transportir;
- tabel master Transportir, restore database, dan dokumen regional;
- seluruh tabel Regional Manager;
- tabel checklist yang tetap menggunakan scroll horizontal karena memiliki banyak kolom pemeriksaan;
- build image frontend produksi.

## Temuan audit source

| Temuan | Risiko | Koreksi |
|---|---|---|
| Login baru berubah satu kolom pada 580 px | Layar 581–820 px dapat memaksa dua panel minimum 820 px dan terasa sempit/terpotong | Breakpoint login satu kolom dipindahkan ke 820 px |
| Beberapa tabel tidak memiliki label mobile | Data hanya dapat dibaca melalui scroll horizontal dan kurang terstruktur | Menambahkan `data-label` dan tampilan kartu mobile |
| Tombol aksi tabel tetap berjajar bebas | Tombol berpotensi terlalu kecil atau keluar kartu | Menggunakan grid satu/dua kolom dan tinggi sentuh minimum 42 px |
| Elemen flex/grid tertentu belum memiliki `min-width: 0` | Teks panjang dapat mendorong viewport | Menambahkan batas lebar dan wrapping aman |
| Separator sidebar/notifikasi salah encoding | Teks tampil sebagai karakter asing | Mengganti dengan karakter `·` yang benar |

## Pengujian otomatis

Perintah:

```powershell
cd frontend
npm.cmd run test:unit
```

Hasil akhir:

```text
9 test lulus
0 gagal
Durasi 205,1263 ms
```

Empat test responsif baru memverifikasi breakpoint dan drawer navigasi, login satu kolom, tabel kartu berlabel, serta perlindungan overflow sampai viewport 320 px.

## Build dan formatting

```text
npm.cmd run build
1893 modul ditransformasi
Build berhasil dalam 443 ms

npm.cmd exec prettier -- --check src tests
All matched files use Prettier code style

docker compose -f compose.yaml build frontend
prima-regional-frontend:latest Built
Build Vite di dalam Docker berhasil dalam 391 ms
```

## Status dan keterbatasan

Status pengujian source, unit test, format, build lokal, dan build Docker: **lulus**.

Lingkungan ini tidak menyediakan browser visual terintegrasi untuk mengambil screenshot berbagai perangkat. Karena itu, setelah hosting tetap lakukan smoke test manual melalui Chrome/Edge responsive mode serta minimal satu ponsel aktual. Periksa orientasi portrait dan landscape, terutama tabel checklist panjang, unggah dokumen, form tanda tangan, dan keyboard virtual.

## Referensi perubahan

Lihat [perubahan harian 3 November 2026](../perubahan-harian/2026-11-03.md).
