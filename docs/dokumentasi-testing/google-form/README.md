# Membuat Google Form UAT PRIMA

Google Form dapat dibuat otomatis menggunakan script
[`create-prima-uat-google-form.gs`](create-prima-uat-google-form.gs).

## Langkah pembuatan

1. Buka [Google Apps Script](https://script.google.com) menggunakan akun Google
   yang akan menjadi pemilik formulir.
2. Klik **New project**.
3. Hapus isi file `Code.gs`, lalu salin seluruh isi
   `create-prima-uat-google-form.gs` ke editor.
4. Pilih fungsi **createPrimaUatForm**, kemudian klik **Run**.
5. Pada eksekusi pertama, izinkan akses ke Google Forms, Drive, dan Sheets.
6. Buka **Execution log**. Script menampilkan:
   - `EDIT_FORM_URL`: tautan untuk mengedit Google Form;
   - `RESPONDENT_FORM_URL`: tautan yang dibagikan kepada responden;
   - `RESPONSE_SHEET_URL`: spreadsheet jawaban;
   - `DRIVE_FOLDER_URL`: folder yang berisi Form dan spreadsheet.

Jalankan fungsi hanya satu kali untuk satu pelaksanaan UAT. Menjalankannya lagi
akan membuat Form, spreadsheet, dan folder baru.

## Struktur Form

- identitas responden;
- pilihan role dengan percabangan otomatis;
- hanya memuat empat role UAT: SPV HSSE, Tim HSSE, Transportir/Pengurus Mobil
  Tangki, dan Integrated Terminal Manager;
- 45 skenario terukur: 13 SPV HSSE, 11 Tim HSSE, 12 Transportir, dan 9 ITM;
- setiap skenario menjadi pertanyaan Lulus/Gagal/Tidak diuji khusus role agar
  tetap terbaca jelas pada komputer maupun ponsel;
- setiap skenario memuat ID, tindakan, dan hasil yang diharapkan;
- 20 skenario autentikasi, proses utama, dan pembatasan akses diberi penanda
  `[KRITIS]` serta wajib lulus;
- pertanyaan skala 1-5 khusus role;
- pertanyaan umum untuk semua role;
- pertanyaan terbuka dan pencatatan defect;
- keputusan penerimaan;
- pernyataan dan nama lengkap sebagai tanda tangan elektronik;
- spreadsheet respons otomatis.

Sebelum membagikan Form, gunakan menu **Preview** untuk memastikan percabangan
setiap role menuju bagian yang benar. Jangan mengaktifkan shuffle question karena
urutan skenario UAT harus tetap konsisten.

Role Regional Manager tidak dimasukkan sesuai rencana pelaksanaan UAT. Perwakilan
Tim HSSE tidak perlu memilih atau meniru role Regional Manager; responden tersebut
menguji sistem menggunakan akun dan skenario Tim HSSE. Role legacy `User Internal`
juga tidak dimasukkan karena sudah dikonsolidasikan menjadi Tim HSSE.
