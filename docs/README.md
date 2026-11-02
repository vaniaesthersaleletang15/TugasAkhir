# Indeks Dokumentasi PRIMA

Dokumentasi proyek dipisahkan berdasarkan tujuan agar riwayat pengembangan dan
bukti pengujian mudah ditelusuri.

## Struktur dokumentasi

- [`ARCHITECTURE.md`](ARCHITECTURE.md): arsitektur aplikasi dan pedoman
  pemeliharaan codebase.
- [`perubahan-harian/`](perubahan-harian/README.md): catatan seluruh perubahan
  kode, konfigurasi, database, dan dokumentasi berdasarkan tanggal pengerjaan.
- [`dokumentasi-testing/`](dokumentasi-testing/README.md): hasil pengujian yang
  dilakukan setelah perubahan, termasuk kegagalan, warning, perbaikan, dan hasil
  pengujian ulang.

## Aturan wajib

Setiap pekerjaan yang mengubah proyek harus menghasilkan:

1. satu entri pada dokumentasi perubahan harian;
2. satu entri dokumentasi testing apabila perubahan memengaruhi kode,
   konfigurasi, database, build, atau perilaku aplikasi;
3. keterkaitan antara catatan perubahan dengan laporan testing pada tanggal yang
   sama;
4. hasil pengujian yang ditulis apa adanya—termasuk gagal, warning, atau belum
   diuji.

Perubahan dokumentasi murni yang tidak memengaruhi aplikasi cukup dicatat pada
perubahan harian dan diperiksa secara manual.
