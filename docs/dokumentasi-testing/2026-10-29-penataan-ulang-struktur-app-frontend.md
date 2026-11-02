# Pengujian Penataan Ulang Struktur App Frontend

## Tujuan

Memastikan pemecahan `frontend/src/App.jsx` berdasarkan tanggung jawab tidak
mengubah perilaku aplikasi, kontrak API, maupun route yang tersedia.

## Ruang lingkup perubahan

- `App.jsx` menjadi composition root untuk autentikasi dan route publik.
- Login dan Cloudflare Turnstile dipindahkan ke
  `features/auth/LoginPage.jsx`.
- Layout, menu, dan route terautentikasi dipindahkan ke
  `layouts/AppShell.jsx`.
- Dashboard dan komponen statistik dipindahkan ke
  `pages/dashboard/DashboardPage.jsx`.
- Hook pemuatan data asinkron dipindahkan ke `hooks/useAsync.js`.
- Komponen daftar checklist lama yang tidak direferensikan dihapus. Route aktif
  tetap menggunakan `ChecklistDataPage` dari `Modules.jsx`.

Jumlah baris `App.jsx` berkurang dari 1.517 menjadi 57 baris sebelum
formatting, sehingga file utama tidak lagi menampung seluruh elemen frontend.

## Lingkungan pengujian

- Sistem operasi: Windows
- Node.js: versi yang aktif pada Laragon
- Build tool: Vite 8.2.2
- Tanggal: 29 Oktober 2026

## Hasil pengujian

| No. | Pengujian | Perintah | Hasil aktual | Status |
|---:|---|---|---|---|
| 1 | Build awal melalui alias PowerShell | `npm run build` | Gagal dijalankan karena kebijakan PowerShell memblokir `npm.ps1` | Gagal lingkungan |
| 2 | Unit test awal melalui alias PowerShell | `npm run test:unit` | Gagal dijalankan karena kebijakan PowerShell memblokir `npm.ps1` | Gagal lingkungan |
| 3 | Build produksi melalui executable Windows | `npm.cmd run build` | 1.893 modul berhasil ditransformasi dan build selesai | Lulus |
| 4 | Unit test frontend | `npm.cmd run test:unit` | 5 test lulus, 0 gagal | Lulus |
| 5 | Formatting file hasil penataan ulang | `npx.cmd prettier --write ...` | Seluruh file target berhasil diformat | Lulus |

## Catatan kegagalan

Kegagalan pertama bukan defect aplikasi. PowerShell menolak menjalankan
`C:\laragon\bin\nodejs\node-v22\npm.ps1` karena execution policy. Pengujian
diulang menggunakan `npm.cmd`, lalu build dan seluruh unit test frontend lulus.

## Kesimpulan

Penataan ulang dinyatakan lulus pengujian otomatis. Route dan perilaku utama tetap
dipertahankan, sementara struktur frontend menjadi lebih mudah dipelihara.
Pengujian interaksi visual per role tetap menjadi bagian dari System Testing/UAT.

Dokumentasi perubahan terkait:
[Perubahan Harian 29 Oktober 2026](../perubahan-harian/2026-10-29.md).
