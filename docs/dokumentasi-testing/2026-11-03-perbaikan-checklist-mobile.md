# Perbaikan Formulir Checklist Mobile — 3 November 2026

## Masalah

Pada ponsel, bagian **Item Pemeriksaan** hanya memperlihatkan header tabel dan petunjuk geser. Daftar item tidak terlihat jelas, penghitung menampilkan `0 item diperiksa`, dan pengguna langsung melihat bagian **Hasil Akhir dan Pengesahan**.

## Analisis

- Formulir menggunakan tabel dengan lebar minimum 1.040 px pada semua ukuran layar.
- Aturan mobile sebelumnya hanya mengaktifkan scroll horizontal, sehingga item pemeriksaan tidak tersusun sesuai lebar ponsel.
- Penghitung hanya menampilkan jumlah item yang telah dipilih tanpa jumlah total, sehingga `0 item diperiksa` dapat disalahartikan sebagai tidak ada template.
- Bila endpoint template mengembalikan daftar kosong, antarmuka tidak memberikan penjelasan atau tindakan pemulihan.
- Backend membaca template dari `resources/checklists/spbu.html` dan `resources/checklists/industri.html`, tetapi belum memvalidasi file hilang, tidak terbaca, atau hasil parsing kosong.

## Perbaikan

- Mengubah setiap baris pemeriksaan menjadi kartu vertikal pada layar maksimal 820 px.
- Menampilkan label pemeriksaan, pelaksana, prioritas, kondisi Baik/Tidak, keterangan, serta tanggal kedaluwarsa tanpa scroll horizontal.
- Mengubah ringkasan menjadi format `x dari y item diperiksa`.
- Menambahkan keadaan kosong yang terlihat jelas beserta tombol **Muat ulang halaman**.
- Menghentikan pemuatan form baru bila respons template tidak berisi item dan menampilkan pesan Indonesia.
- Menambahkan validasi backend untuk file template yang hilang, kosong, tidak terbaca, atau tidak menghasilkan item.
- Memperkuat integration test agar endpoint template SPBU wajib menghasilkan 25 item dengan struktur lengkap.

## Hasil Pengujian

Frontend:

```text
13 unit test lulus
0 gagal
Build Vite berhasil — 1.894 modul ditransformasi
```

Backend:

```text
ChecklistLifecycleIntegrationTest: 4 test lulus, 45 assertion
Seluruh backend: 223 test lulus, 682 assertion
```

## Verifikasi Manual Setelah Deployment

1. Buka formulir checklist SPBU dan Industri dari ponsel.
2. Pastikan ringkasan menunjukkan `0 dari 25 item diperiksa` sebelum pengisian.
3. Pastikan 25 kartu item tampil di antara Identitas Kendaraan dan Hasil Akhir.
4. Pilih Baik/Tidak, isi keterangan, dan periksa field tanggal pada dokumen yang memiliki masa berlaku.
5. Pastikan tidak ada scroll horizontal pada daftar item dan seluruh kontrol dapat disentuh.

## Koreksi Layout Lanjutan

Smoke test pada ponsel setelah implementasi pertama menunjukkan teks item masih terjepit menjadi satu huruf per baris. Penyebabnya adalah elemen tabel tetap dipengaruhi `colgroup`, `table-layout`, `rowspan`, dan aturan tabel responsif lain walaupun baris serta sel sudah diubah menjadi `display: block`.

Koreksi akhir menggunakan dua struktur tampilan yang terpisah:

- tabel semantik asli hanya ditampilkan pada desktop;
- kartu React khusus mobile ditampilkan pada layar maksimal 820 px;
- kartu mobile tidak menggunakan elemen `table`, `colgroup`, atau `rowspan`;
- setiap kartu memiliki judul item, pelaksana, prioritas, tombol kondisi, keterangan, dan tanggal masa berlaku;
- seluruh kontainer memakai `min-width: 0` dan `overflow-wrap` untuk mencegah teks mendorong atau menyempitkan layout.

Regresi frontend setelah koreksi akhir tetap lulus: 13 unit test, 0 gagal, dan build Vite berhasil dengan 1.894 modul.

## Penguatan Pengalihan Mobile

Tangkapan layar berikutnya menunjukkan tabel desktop masih dapat muncul akibat urutan selector responsif. Pengalihan kemudian diperkuat pada tiga lapisan:

1. `window.matchMedia('(max-width: 820px)')` menentukan struktur aktif dan mengikuti perubahan orientasi/lebar layar.
2. Atribut `hidden`, `aria-hidden`, serta inline `display` memastikan tabel desktop tidak terlihat pada mobile dan kartu tidak terlihat pada desktop.
3. Selector penjaga menggunakan `display: none !important` untuk tabel desktop dan `display: grid !important` untuk kartu mobile.

Dengan perlindungan ini, tampilan mobile tidak lagi bergantung pada urutan atau spesifisitas aturan tabel lama. Regresi tetap lulus 13/13 dan build produksi menghasilkan asset CSS/JS baru dengan hash berbeda.

## Verifikasi Bundle dan Perangkat Sentuh

Screenshot pengguna masih memperlihatkan teks versi lama `0 item diperiksa`, sedangkan source terbaru menggunakan `0 dari 25 item diperiksa`. Pemeriksaan langsung ke domain dan cache-buster menunjukkan server telah mengirim bundle baru dengan `Cache-Control: no-cache`; kondisi tersebut mengindikasikan tab/browser masih menjalankan bundle lama.

Deteksi mobile kemudian diperluas dari lebar viewport menjadi `(max-width: 820px), (pointer: coarse)` agar mode kartu tetap digunakan ketika browser ponsel memakai mode situs desktop. Label **Mode ponsel aktif** ditambahkan pada header sebagai indikator yang dapat diverifikasi langsung. Unit test tetap lulus 13/13 dan build produksi berhasil.

## Temuan Lingkungan Localhost

Pengguna ternyata membuka `http://localhost:8092`, sedangkan deployment sebelumnya hanya memperbarui VPS. Audit Docker lokal menemukan container `prima-regional-frontend-1` masih menggunakan image berumur delapan hari. Frontend lokal kemudian dibangun ulang dan container frontend saja dibuat ulang tanpa menyentuh PostgreSQL maupun data lokal.

Verifikasi localhost setelah rebuild:

```text
HTTP 200
Asset JavaScript: index-BWLctCOA.js
Label Mode ponsel aktif: tersedia
Deteksi pointer coarse: tersedia
Cache-Control: no-cache
```

## Koreksi Layar Putih pada WebView Mobile

Setelah frontend lokal diperbarui, browser dengan user-agent iPhone berhasil memuat HTML, CSS, JavaScript, autentikasi, dan endpoint `auth/me`, tetapi berhenti saat editor checklist dipasang. Halaman login dapat dirender normal melalui screenshot Chrome headless. Titik risiko berada pada listener `MediaQueryList` di editor checklist: beberapa Safari/WebView hanya menyediakan API lama `addListener/removeListener` dan tidak menyediakan `addEventListener/removeEventListener`.

Listener deteksi mobile kemudian dibuat kompatibel:

- browser modern menggunakan `addEventListener('change', ...)`;
- Safari/WebView lama menggunakan fallback `addListener(...)`;
- cleanup memakai pasangan `removeEventListener` atau `removeListener` yang sesuai.

Setelah koreksi, 13 unit test lulus, build Vite berhasil, image frontend localhost dibangun ulang, dan container frontend dibuat ulang tanpa menyentuh database.

## Akar Masalah Layar Putih Setelah Login

Reproduksi terautentikasi di `http://localhost:8092/#/checklists/new/INDUSTRI` menunjukkan bahwa layar putih tidak lagi berasal dari tabel atau media query. Pemeriksaan DOM memperlihatkan elemen `#root` kosong, sedangkan Chrome DevTools Protocol menangkap exception berikut:

```text
ReferenceError: Dashboard is not defined
```

`AppShell` mengimpor komponen dengan nama `DashboardPage`, tetapi route utama masih mencoba merender `<Dashboard>`. Karena seluruh daftar route dibuat saat `AppShell` dirender, kesalahan tersebut menghentikan semua halaman pengguna setelah login, termasuk route checklist yang sedang dibuka.

Koreksi mengganti route tersebut menjadi `<DashboardPage user={user} />` dan menambahkan unit test regresi yang memastikan import dan pemakaian nama komponen tetap konsisten. Verifikasi browser terautentikasi kemudian wajib memastikan elemen root tidak kosong dan teks **Item Pemeriksaan** beserta daftar item tampil.

Hasil verifikasi setelah container localhost dibangun ulang:

```text
14 unit test frontend lulus
Build Vite berhasil (1.894 modul)
Route: /#/checklists/new/INDUSTRI
Root HTML: 45.715 karakter
Ringkasan: 0 dari 25 item diperiksa
Kartu terdeteksi: STNK, PAJAK, SIMFIT (Industri), dan item berikutnya
Exception JavaScript: 0
```

