# Verifikasi Google Form UAT Empat Role — 2 November 2026

## Tujuan

Menyiapkan instrumen acceptance testing terakhir dalam bentuk generator Google
Form yang sesuai dengan role dan fungsi terbaru sistem PRIMA E-KIM.

## Role responden

| Role | Jumlah skenario |
|---|---:|
| SPV HSSE / Administrator Sistem | 13 |
| Tim HSSE | 11 |
| Transportir / Pengurus Mobil Tangki | 12 |
| Integrated Terminal Manager | 9 |
| **Total** | **45** |

Regional Manager tidak dimasukkan atas keputusan pelaksanaan UAT karena penguji
regional diwakili Tim HSSE. Perwakilan tersebut mengisi bagian Tim HSSE, bukan
mensimulasikan kewenangan Regional Manager. Role User Internal juga tidak
dimasukkan karena telah dikonsolidasikan menjadi Tim HSSE.

## Struktur Google Form

- identitas, unit kerja, tanggal, perangkat/browser, dan pendamping pengujian;
- pilihan role dengan percabangan otomatis;
- satu pertanyaan Lulus, Gagal, atau Tidak diuji untuk setiap skenario agar
  tindakan dan hasil yang diharapkan tetap terbaca pada perangkat seluler;
- ID, tindakan, dan hasil yang diharapkan pada setiap skenario;
- 20 skenario autentikasi, proses utama, dan keamanan diberi penanda `[KRITIS]`;
- sepuluh pernyataan skala 1–5 khusus setiap role;
- sepuluh pernyataan umum seluruh role;
- pertanyaan terbuka, catatan defect, tingkat dampak, dan tautan bukti;
- keputusan penerimaan dan pernyataan responden;
- spreadsheet respons dan lembar petunjuk rekap otomatis.

## Cakupan skenario

- SPV HSSE: akun, transportir, dokumen, kendaraan, dashboard, audit, laporan,
  pengaturan, dan permohonan restore.
- Tim HSSE: kendaraan, checklist SPBU/Industri, validasi isian, draft, dokumen
  wajib, persetujuan/tanda tangan HSSE, dan pembatasan akses.
- Transportir: pendaftaran, login, profil, kendaraan, dokumen privat, catatan
  penolakan, status checklist/E-KIM, notifikasi, dan reset password.
- ITM: antrean final, detail pemeriksaan HSSE, persetujuan/penolakan, tanda
  tangan, riwayat, verifikasi E-KIM, pembatasan mutasi, dan keputusan restore.

## Verifikasi statis

| Pemeriksaan | Hasil | Status |
|---|---|---|
| Jumlah role | Tepat 4 role | Lulus |
| Jumlah skenario | 45 skenario | Lulus |
| Distribusi ID | SPV 13, HSSE 11, TRP 12, ITM 9 | Lulus |
| Skenario kritis | 20 ID unik dan berada dalam daftar 45 skenario | Lulus |
| Role User Internal | Tidak ditemukan | Lulus |
| Role Regional Manager | Tidak ditemukan | Lulus |
| Sintaks JavaScript | `node --check -` tanpa output error | Lulus |
| Format JavaScript | Prettier selesai tanpa perubahan lanjutan | Lulus |

Pemeriksaan awal dengan `node --check` langsung pada file menghasilkan error
`ERR_UNKNOWN_FILE_EXTENSION` karena Node tidak mengenali ekstensi Google Apps
Script `.gs`. Isi file kemudian diberikan melalui standard input; pemeriksaan
sintaks lulus. Error pertama bukan kesalahan sintaks instrumen.

Pemanggilan Prettier dari root project sempat menunggu karena dependency Node
berada di subfolder `frontend`. Proses tersebut dihentikan, lalu formatting
dijalankan menggunakan binary lokal `frontend/node_modules/.bin/prettier.cmd`
dan selesai berhasil. Hal ini merupakan kesalahan lokasi executable, bukan
defect pada generator Form.

## Batas verifikasi

Google Form aktual belum dibuat dari lingkungan lokal karena pembuatannya
memerlukan autentikasi dan izin akun Google pemilik. Jalankan fungsi
`createPrimaUatForm()` melalui Google Apps Script, lalu lakukan Preview untuk
memeriksa keempat percabangan sebelum tautan dibagikan. Respons UAT belum ada
dan tidak boleh dinyatakan lulus sebelum pengujian di kantor dilaksanakan.
Backend/frontend aplikasi tidak dijalankan ulang karena perubahan ini hanya
memengaruhi artefak Google Apps Script dan dokumentasi, bukan runtime aplikasi.

## Artefak

- [Generator Google Form](google-form/create-prima-uat-google-form.gs)
- [Petunjuk pembuatan](google-form/README.md)
- [Perubahan harian](../perubahan-harian/2026-11-02.md)
