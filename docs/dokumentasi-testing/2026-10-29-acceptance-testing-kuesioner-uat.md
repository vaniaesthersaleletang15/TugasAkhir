# Instrumen User Acceptance Testing (UAT) PRIMA

> Dokumen ini merupakan snapshot awal 29 Oktober 2026 dan masih memuat role
> legacy User Internal serta Regional Manager. Instrumen Google Form terbaru
> menggunakan empat role dan didokumentasikan pada
> [UAT empat role 2 November 2026](2026-11-02-acceptance-testing-google-form-empat-role.md).

> Versi Google Form dapat dibuat otomatis menggunakan
> [script Google Apps Script](google-form/create-prima-uat-google-form.gs).
> Ikuti [petunjuk pembuatan Google Form](google-form/README.md).

## Identitas pengujian

| Data | Isian |
|---|---|
| Nama sistem | PRIMA - Sistem Pengelolaan Elektronik Kartu Izin Masuk |
| Lokasi pengujian | PT Pertamina Patra Niaga Integrated Terminal __________ |
| Tanggal | __________ |
| Nama responden | __________ |
| Jabatan | __________ |
| Role aplikasi | __________ |
| Perangkat/browser | __________ |
| Pendamping pengujian | __________ |

## Tujuan

UAT dilakukan untuk mengetahui apakah fungsi PRIMA telah sesuai dengan proses
kerja pengguna. Responden menjalankan skenario sesuai role, mencatat hasil
aktual, kemudian mengisi pertanyaan penerimaan. UAT tidak menilai kemampuan
responden, melainkan kesesuaian dan kemudahan sistem.

## Petunjuk pengisian

Untuk tabel skenario, pilih salah satu hasil:

- **Lulus**: hasil aktual sesuai dengan hasil yang diharapkan;
- **Gagal**: fungsi tidak berjalan atau hasilnya tidak sesuai;
- **Tidak diuji**: skenario tidak dapat dilakukan, disertai alasannya.

Untuk pertanyaan persepsi, gunakan skala berikut:

| Nilai | Jawaban |
|---:|---|
| 1 | Sangat tidak setuju |
| 2 | Tidak setuju |
| 3 | Netral/ragu-ragu |
| 4 | Setuju |
| 5 | Sangat setuju |

Jika responden memilih nilai 1 atau 2, tuliskan alasan dan saran perbaikannya.

---

# Form UAT Role SPV HSSE / Administrator Sistem

## Skenario tugas

| ID | Tugas yang dilakukan | Hasil yang diharapkan | Hasil | Catatan |
|---|---|---|---|---|
| UAT-SPV-01 | Login melalui portal internal | Dashboard SPV tampil dan akun dikenali dengan benar | ___ | ___ |
| UAT-SPV-02 | Membuka daftar pendaftaran Transportir | Hanya pendaftaran untuk Integrated Terminal SPV yang tampil | ___ | ___ |
| UAT-SPV-03 | Menyetujui satu pendaftaran Transportir | Akun Transportir aktif dan dapat digunakan untuk login | ___ | ___ |
| UAT-SPV-04 | Menolak satu pendaftaran beserta alasan | Status menjadi ditolak dan alasan tersimpan | ___ | ___ |
| UAT-SPV-05 | Membuat atau memperbarui akun Tim HSSE/ITM/Transportir | Akun tersimpan dengan role dan terminal yang benar | ___ | ___ |
| UAT-SPV-06 | Menonaktifkan akun operasional | Akun tidak dapat mengakses sistem lagi | ___ | ___ |
| UAT-SPV-07 | Melihat dan memverifikasi dokumen kendaraan | Status dokumen dan catatan verifikasi tersimpan | ___ | ___ |
| UAT-SPV-08 | Melihat dashboard, audit, dan peringatan E-KIM | Data hanya berasal dari terminal SPV dan sesuai data aktual | ___ | ___ |
| UAT-SPV-09 | Mengubah pengaturan sesi, retensi, atau notifikasi | Pengaturan tersimpan dan tercatat pada audit | ___ | ___ |
| UAT-SPV-10 | Mengekspor laporan checklist | File XLSX dapat diunduh dan dibuka | ___ | ___ |

## Pertanyaan penerimaan

| ID | Pernyataan | Nilai 1-5 | Catatan |
|---|---|---:|---|
| Q-SPV-01 | Dashboard menampilkan informasi yang diperlukan SPV HSSE. | ___ | ___ |
| Q-SPV-02 | Proses persetujuan atau penolakan akun Transportir mudah dipahami. | ___ | ___ |
| Q-SPV-03 | Pengelolaan akun Tim HSSE, ITM, dan Transportir sesuai kewenangan SPV. | ___ | ___ |
| Q-SPV-04 | Proses verifikasi dokumen kendaraan sesuai proses kerja aktual. | ___ | ___ |
| Q-SPV-05 | Peringatan masa berlaku E-KIM membantu kegiatan monitoring. | ___ | ___ |
| Q-SPV-06 | Audit trail cukup jelas untuk menelusuri aktivitas pengguna. | ___ | ___ |
| Q-SPV-07 | Data terminal lain tidak terlihat atau tidak dapat diubah oleh SPV. | ___ | ___ |
| Q-SPV-08 | Laporan XLSX memenuhi kebutuhan pelaporan operasional. | ___ | ___ |
| Q-SPV-09 | Pesan kesalahan dan konfirmasi sistem mudah dimengerti. | ___ | ___ |
| Q-SPV-10 | Secara keseluruhan, sistem layak digunakan oleh SPV HSSE. | ___ | ___ |

---

# Form UAT Role Tim HSSE

## Skenario tugas

| ID | Tugas yang dilakukan | Hasil yang diharapkan | Hasil | Catatan |
|---|---|---|---|---|
| UAT-HSSE-01 | Login melalui portal internal | Dashboard Tim HSSE tampil | ___ | ___ |
| UAT-HSSE-02 | Memilih kendaraan yang akan diperiksa | Kendaraan terminal sendiri dapat ditemukan | ___ | ___ |
| UAT-HSSE-03 | Membuka template checklist SPBU atau Industri | Item checklist sesuai jenis kendaraan | ___ | ___ |
| UAT-HSSE-04 | Mengisi seluruh item pemeriksaan dan masa berlaku dokumen | Nilai dan tanggal dapat disimpan dengan benar | ___ | ___ |
| UAT-HSSE-05 | Menyimpan checklist sebagai draft | Draft dapat dibuka dan diperbarui kembali | ___ | ___ |
| UAT-HSSE-06 | Mengirim checklist yang seluruh itemnya baik | Status berpindah ke tahap pemeriksaan/tanda tangan HSSE | ___ | ___ |
| UAT-HSSE-07 | Mengirim atau menolak checklist dengan item tidak baik | Status ditolak dan alasan kegagalan terbentuk jelas | ___ | ___ |
| UAT-HSSE-08 | Menandatangani checklist sebagai HSSE | Nama, waktu, dan tanda tangan tersimpan | ___ | ___ |
| UAT-HSSE-09 | Mencoba membuka checklist terminal lain | Data tidak dapat dilihat atau diubah | ___ | ___ |

## Pertanyaan penerimaan

| ID | Pernyataan | Nilai 1-5 | Catatan |
|---|---|---:|---|
| Q-HSSE-01 | Daftar kendaraan mudah dicari dan dipilih. | ___ | ___ |
| Q-HSSE-02 | Item checklist SPBU/Industri sesuai dengan pemeriksaan lapangan. | ___ | ___ |
| Q-HSSE-03 | Istilah “Baik”, “Tidak”, keterangan, dan tanggal berlaku mudah dipahami. | ___ | ___ |
| Q-HSSE-04 | Validasi tanggal dokumen membantu mencegah kesalahan pemeriksaan. | ___ | ___ |
| Q-HSSE-05 | Draft checklist memudahkan pekerjaan yang belum selesai. | ___ | ___ |
| Q-HSSE-06 | Alasan penolakan menggambarkan temuan pemeriksaan dengan benar. | ___ | ___ |
| Q-HSSE-07 | Proses tanda tangan digital HSSE mudah dilakukan. | ___ | ___ |
| Q-HSSE-08 | Status workflow menunjukkan posisi checklist dengan jelas. | ___ | ___ |
| Q-HSSE-09 | Sistem mencegah perubahan checklist yang sudah masuk tahap berikutnya. | ___ | ___ |
| Q-HSSE-10 | Secara keseluruhan, sistem mendukung pemeriksaan HSSE di lapangan. | ___ | ___ |

---

# Form UAT Role Integrated Terminal Manager (ITM)

## Skenario tugas

| ID | Tugas yang dilakukan | Hasil yang diharapkan | Hasil | Catatan |
|---|---|---|---|---|
| UAT-ITM-01 | Login melalui portal internal | Dashboard ITM tampil | ___ | ___ |
| UAT-ITM-02 | Membuka daftar checklist yang menunggu persetujuan | Hanya checklist pada tahap ITM dan terminal sendiri yang relevan | ___ | ___ |
| UAT-ITM-03 | Membuka detail checklist dan hasil pemeriksaan HSSE | Data kendaraan, item, tanggal, dan tanda tangan HSSE lengkap | ___ | ___ |
| UAT-ITM-04 | Menyetujui dan menandatangani checklist | Status menjadi disetujui dan bukti verifikasi terbentuk | ___ | ___ |
| UAT-ITM-05 | Menolak checklist dengan alasan | Status menjadi ditolak dan alasan diterima pihak terkait | ___ | ___ |
| UAT-ITM-06 | Membuka riwayat persetujuan sendiri | Hanya checklist yang ditandatangani ITM tersebut yang tampil | ___ | ___ |
| UAT-ITM-07 | Mencoba mengubah isi checklist | Sistem menolak perubahan isi oleh ITM | ___ | ___ |

## Pertanyaan penerimaan

| ID | Pernyataan | Nilai 1-5 | Catatan |
|---|---|---:|---|
| Q-ITM-01 | Daftar checklist yang menunggu persetujuan mudah ditemukan. | ___ | ___ |
| Q-ITM-02 | Detail hasil pemeriksaan HSSE cukup untuk mengambil keputusan. | ___ | ___ |
| Q-ITM-03 | Status dan tanda tangan HSSE terlihat jelas sebelum persetujuan. | ___ | ___ |
| Q-ITM-04 | Proses persetujuan dan tanda tangan ITM mudah dilakukan. | ___ | ___ |
| Q-ITM-05 | Sistem mencegah persetujuan jika tahapan sebelumnya belum lengkap. | ___ | ___ |
| Q-ITM-06 | Proses penolakan dan pemberian alasan mudah dipahami. | ___ | ___ |
| Q-ITM-07 | Riwayat persetujuan membantu penelusuran keputusan ITM. | ___ | ___ |
| Q-ITM-08 | Data dari terminal lain terlindungi dari akses yang tidak sesuai. | ___ | ___ |
| Q-ITM-09 | Informasi verifikasi E-KIM mudah dipahami. | ___ | ___ |
| Q-ITM-10 | Secara keseluruhan, sistem layak digunakan dalam proses persetujuan ITM. | ___ | ___ |

---

# Form UAT Role User Internal

## Skenario tugas

| ID | Tugas yang dilakukan | Hasil yang diharapkan | Hasil | Catatan |
|---|---|---|---|---|
| UAT-USER-01 | Login melalui portal internal | Dashboard User tampil | ___ | ___ |
| UAT-USER-02 | Memilih akun Transportir pada terminal sendiri | Hanya Transportir aktif di terminal yang sesuai tersedia | ___ | ___ |
| UAT-USER-03 | Mendaftarkan kendaraan dan memasukkan email Transportir | Kendaraan dan relasi Transportir tersimpan | ___ | ___ |
| UAT-USER-04 | Membuka daftar kendaraan yang pernah didaftarkan | Kendaraan buatan User tampil dengan Transportir terkait | ___ | ___ |
| UAT-USER-05 | Mencoba mendaftarkan nomor polisi yang sama | Sistem menolak duplikasi dengan pesan yang jelas | ___ | ___ |
| UAT-USER-06 | Membuat dan menyimpan draft checklist | Draft tersimpan dan dapat dibuka kembali | ___ | ___ |
| UAT-USER-07 | Mengirim checklist ke tahap berikutnya | Status workflow berubah sesuai proses | ___ | ___ |

## Pertanyaan penerimaan

| ID | Pernyataan | Nilai 1-5 | Catatan |
|---|---|---:|---|
| Q-USER-01 | Form registrasi kendaraan mudah dipahami. | ___ | ___ |
| Q-USER-02 | Pemilihan akun Transportir sesuai dengan kebutuhan operasional. | ___ | ___ |
| Q-USER-03 | Normalisasi nomor polisi dan email membantu mengurangi kesalahan data. | ___ | ___ |
| Q-USER-04 | Sistem memberikan pesan yang jelas ketika nomor polisi sudah terdaftar. | ___ | ___ |
| Q-USER-05 | Daftar kendaraan menampilkan hubungan User dan Transportir dengan benar. | ___ | ___ |
| Q-USER-06 | Pembuatan draft checklist mudah dilakukan. | ___ | ___ |
| Q-USER-07 | Status pengajuan checklist mudah dipantau. | ___ | ___ |
| Q-USER-08 | User hanya dapat mengakses data pada terminal yang menjadi kewenangannya. | ___ | ___ |
| Q-USER-09 | Tampilan dan navigasi sistem mudah digunakan. | ___ | ___ |
| Q-USER-10 | Secara keseluruhan, sistem memenuhi kebutuhan User internal. | ___ | ___ |

---

# Form UAT Role Transportir / Pengurus Kendaraan

## Skenario tugas

| ID | Tugas yang dilakukan | Hasil yang diharapkan | Hasil | Catatan |
|---|---|---|---|---|
| UAT-TRP-01 | Melakukan pendaftaran akun Transportir | Pendaftaran terkirim ke SPV terminal yang dipilih | ___ | ___ |
| UAT-TRP-02 | Login melalui portal Transportir setelah akun disetujui | Dashboard Transportir tampil | ___ | ___ |
| UAT-TRP-03 | Melihat atau menambahkan kendaraan yang menjadi tanggung jawab | Hanya kendaraan terminal sendiri dapat ditautkan | ___ | ___ |
| UAT-TRP-04 | Mengunggah dokumen kendaraan | File tersimpan dengan status menunggu verifikasi | ___ | ___ |
| UAT-TRP-05 | Mencoba mengunggah dokumen untuk kendaraan yang bukan tanggung jawab | Sistem menolak akses | ___ | ___ |
| UAT-TRP-06 | Melihat dan mengunduh dokumen sendiri | Dokumen dapat dilihat/diunduh dan dokumen pihak lain terlindungi | ___ | ___ |
| UAT-TRP-07 | Menghapus dokumen yang masih berstatus menunggu | File dan data dokumen terhapus | ___ | ___ |
| UAT-TRP-08 | Melihat notifikasi penerbitan atau penolakan E-KIM | Notifikasi sesuai kendaraan dan hasil pemeriksaan | ___ | ___ |
| UAT-TRP-09 | Menandai notifikasi sebagai sudah dibaca | Jumlah notifikasi belum dibaca berkurang | ___ | ___ |
| UAT-TRP-10 | Meminta dan menggunakan reset password | Password baru dapat digunakan dan token hanya berlaku sekali | ___ | ___ |

## Pertanyaan penerimaan

| ID | Pernyataan | Nilai 1-5 | Catatan |
|---|---|---:|---|
| Q-TRP-01 | Proses pendaftaran akun mudah dipahami. | ___ | ___ |
| Q-TRP-02 | Pemilihan Integrated Terminal pada pendaftaran sudah jelas. | ___ | ___ |
| Q-TRP-03 | Daftar kendaraan menampilkan kendaraan yang benar. | ___ | ___ |
| Q-TRP-04 | Proses unggah dokumen kendaraan mudah dilakukan. | ___ | ___ |
| Q-TRP-05 | Jenis dokumen dan tanggal berlaku mudah dipahami. | ___ | ___ |
| Q-TRP-06 | Status menunggu, disetujui, atau ditolak terlihat jelas. | ___ | ___ |
| Q-TRP-07 | Catatan penolakan dokumen atau E-KIM membantu proses perbaikan. | ___ | ___ |
| Q-TRP-08 | Notifikasi E-KIM diterima oleh akun dan kendaraan yang tepat. | ___ | ___ |
| Q-TRP-09 | Fitur reset password mudah dan aman digunakan. | ___ | ___ |
| Q-TRP-10 | Secara keseluruhan, sistem memenuhi kebutuhan Transportir. | ___ | ___ |

---

# Form UAT Role Regional Manager Supply & Distribution

## Skenario tugas

| ID | Tugas yang dilakukan | Hasil yang diharapkan | Hasil | Catatan |
|---|---|---|---|---|
| UAT-REG-01 | Login melalui portal internal | Dashboard Regional tampil | ___ | ___ |
| UAT-REG-02 | Melihat ringkasan seluruh terminal Sulawesi | Lima terminal dan ringkasannya tampil | ___ | ___ |
| UAT-REG-03 | Memfilter dashboard berdasarkan terminal dan periode | Ringkasan berubah sesuai filter | ___ | ___ |
| UAT-REG-04 | Memantau E-KIM aktif, akan berakhir, dan kedaluwarsa | Kategori dan jumlah sesuai data | ___ | ___ |
| UAT-REG-05 | Membuka laporan dan audit regional | Data seluruh terminal dalam regional dapat dipantau | ___ | ___ |
| UAT-REG-06 | Membuat akun SPV HSSE untuk terminal | Akun SPV dibuat pada terminal yang dipilih | ___ | ___ |
| UAT-REG-07 | Menonaktifkan atau mengaktifkan SPV | Status berubah dan token lama dicabut | ___ | ___ |
| UAT-REG-08 | Mereset password SPV | Password baru dapat digunakan oleh SPV | ___ | ___ |
| UAT-REG-09 | Mencoba melakukan mutasi checklist/kendaraan | Sistem menolak karena Regional Manager bersifat monitoring/read-only | ___ | ___ |

## Pertanyaan penerimaan

| ID | Pernyataan | Nilai 1-5 | Catatan |
|---|---|---:|---|
| Q-REG-01 | Dashboard Regional memberikan ringkasan yang dibutuhkan manajemen. | ___ | ___ |
| Q-REG-02 | Perbandingan data antar-terminal mudah dipahami. | ___ | ___ |
| Q-REG-03 | Filter terminal, periode, dan status E-KIM berfungsi sesuai kebutuhan. | ___ | ___ |
| Q-REG-04 | Monitoring E-KIM membantu menentukan prioritas tindak lanjut. | ___ | ___ |
| Q-REG-05 | Laporan regional cukup untuk kebutuhan evaluasi. | ___ | ___ |
| Q-REG-06 | Audit regional membantu penelusuran aktivitas terminal. | ___ | ___ |
| Q-REG-07 | Pengelolaan akun SPV per terminal mudah dilakukan. | ___ | ___ |
| Q-REG-08 | Pembatasan akses read-only Regional Manager sudah sesuai. | ___ | ___ |
| Q-REG-09 | Data dari luar Regional Sulawesi tidak tampil. | ___ | ___ |
| Q-REG-10 | Secara keseluruhan, sistem mendukung monitoring Regional Sulawesi. | ___ | ___ |

---

# Pertanyaan Umum untuk Semua Role

| ID | Pernyataan | Nilai 1-5 | Catatan |
|---|---|---:|---|
| Q-UMUM-01 | Proses login mudah dilakukan. | ___ | ___ |
| Q-UMUM-02 | Menu dan tombol mudah ditemukan. | ___ | ___ |
| Q-UMUM-03 | Istilah yang digunakan mudah dipahami. | ___ | ___ |
| Q-UMUM-04 | Informasi yang ditampilkan sudah lengkap dan relevan. | ___ | ___ |
| Q-UMUM-05 | Waktu respons sistem dapat diterima. | ___ | ___ |
| Q-UMUM-06 | Pesan berhasil, gagal, dan validasi mudah dimengerti. | ___ | ___ |
| Q-UMUM-07 | Tampilan dapat digunakan pada perangkat kerja responden. | ___ | ___ |
| Q-UMUM-08 | Responden merasa data hanya dapat diakses oleh pihak berwenang. | ___ | ___ |
| Q-UMUM-09 | Sistem mengurangi pekerjaan manual dibanding proses sebelumnya. | ___ | ___ |
| Q-UMUM-10 | Responden bersedia menggunakan sistem dalam pekerjaan. | ___ | ___ |

## Pertanyaan terbuka

1. Fitur apa yang paling membantu pekerjaan Anda?

   Jawaban: ________________________________________________________________

2. Bagian apa yang paling sulit atau membingungkan?

   Jawaban: ________________________________________________________________

3. Apakah ada data atau informasi penting yang belum ditampilkan?

   Jawaban: ________________________________________________________________

4. Apakah alur sistem sudah sesuai dengan proses kerja aktual? Jika belum,
   jelaskan perbedaannya.

   Jawaban: ________________________________________________________________

5. Perbaikan apa yang paling Anda prioritaskan sebelum sistem digunakan?

   Jawaban: ________________________________________________________________

## Catatan defect atau temuan UAT

| ID | Role | Langkah saat masalah terjadi | Hasil aktual | Hasil yang diharapkan | Tingkat dampak | Bukti |
|---|---|---|---|---|---|---|
| UAT-FIND-___ | ___ | ___ | ___ | ___ | Kritis/Tinggi/Sedang/Rendah | Screenshot/video |

Klasifikasi dampak:

- **Kritis**: sistem tidak dapat digunakan, data hilang, atau keamanan utama bocor;
- **Tinggi**: proses utama role tidak dapat diselesaikan;
- **Sedang**: fungsi tetap dapat dilakukan dengan cara alternatif;
- **Rendah**: masalah tampilan, istilah, atau kenyamanan yang tidak menghentikan proses.

## Rekomendasi kriteria penerimaan

Kriteria berikut dapat disesuaikan bersama dosen pembimbing dan pihak Pertamina:

1. seluruh skenario kritis setiap role harus berstatus Lulus;
2. tingkat keberhasilan skenario minimal 80%;
3. nilai kepuasan minimal 80% dari skor maksimum;
4. tidak ada defect Kritis atau Tinggi yang belum diselesaikan;
5. responden menyatakan sistem dapat diterima, diterima dengan perbaikan, atau
   belum diterima.

Rumus tingkat keberhasilan tugas:

```text
Jumlah skenario Lulus / Jumlah skenario yang diuji x 100%
```

Rumus nilai penerimaan kuesioner:

```text
Total nilai jawaban / (Jumlah pertanyaan x 5) x 100%
```

## Keputusan responden

Pilih satu:

- [ ] Sistem diterima tanpa perbaikan.
- [ ] Sistem diterima dengan perbaikan minor.
- [ ] Sistem diterima dengan syarat perbaikan tertentu.
- [ ] Sistem belum dapat diterima.

Alasan keputusan:

____________________________________________________________________________

____________________________________________________________________________

| Pihak | Nama | Tanda tangan | Tanggal |
|---|---|---|---|
| Responden | __________ | __________ | __________ |
| Pendamping pengujian | __________ | __________ | __________ |
| Penanggung jawab unit | __________ | __________ | __________ |

## Rekapitulasi seluruh responden

| No. | Role | Nama/inisial | Skenario lulus | Skenario diuji | Keberhasilan | Skor kuesioner | Persentase | Keputusan |
|---:|---|---|---:|---:|---:|---:|---:|---|
| 1 | ___ | ___ | ___ | ___ | ___% | ___ | ___% | ___ |

Catatan: jangan mengubah jawaban responden. Jika ditemukan kegagalan, catat hasil
awal, perbaikan yang dilakukan, dan hasil UAT ulang sebagai bukti proses V-Model.
