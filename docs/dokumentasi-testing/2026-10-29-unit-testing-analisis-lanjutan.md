# Dokumentasi Testing — 2026-10-29 — Analisis Lanjutan Unit Testing

> Catatan: dokumen ini adalah snapshot tahap 76 test. Hasil terbaru ada pada
> [Audit Unit Testing Menyeluruh](2026-10-29-audit-unit-testing-menyeluruh.md)
> dengan 119 test dan 183 assertion.

## Referensi perubahan

- [Dokumentasi perubahan 29 Oktober 2026](../perubahan-harian/2026-10-29.md)
- [Unit Testing tahap awal](2026-10-29-unit-testing-tahap-awal.md)

## Tujuan analisis

Analisis ini memperluas Unit Testing dari 11 menjadi 76 test. Penambahan test
tidak dilakukan hanya untuk menaikkan jumlah, tetapi berdasarkan aturan bisnis
yang dapat dijalankan secara terisolasi tanpa HTTP, database, filesystem, browser,
atau layanan eksternal.

## Pemetaan modul terhadap jenis pengujian

| Modul proposal | Aturan yang layak menjadi Unit Test | Cakupan saat ini | Bagian yang diuji pada tahap lain |
|---|---|---|---|
| Autentikasi | Kesiapan konfigurasi SMTP | 8 test | Login, reset password, CAPTCHA, session, dan rate limit menggunakan Feature/Integration Test |
| Pengelolaan pengguna | Kewenangan role pada workflow dan tanda tangan | 20 test kebijakan checklist | CRUD pengguna dan isolasi terminal tetap diuji melalui Feature Test |
| Registrasi kendaraan | Normalisasi nomor polisi dan email transportir | 6 test | Penyimpanan serta relasi akun-kendaraan menggunakan Integration Test |
| Dokumen kendaraan | Aturan dokumen yang wajib memiliki masa berlaku | 7 test bersama checklist | Upload, private storage, review, dan sinkronisasi database menggunakan Integration Test |
| Checklist SPBU/Industri | Validasi tanggal dokumen, alasan kegagalan, dan transisi workflow | 22 test | Penyimpanan item dan perubahan status database menggunakan Integration Test |
| Persetujuan elektronik | Kewenangan tanda tangan, canonical hash, HMAC, dan evaluasi bukti | 25 test | Penyimpanan tanda tangan dan log tetap diuji melalui Feature Test |
| Status E-KIM | Aktif, akan berakhir, kedaluwarsa, tidak tersedia, dan batas hari | 6 test | Filter serta agregasi dashboard menggunakan Integration Test |
| Notifikasi email | Isi subjek/body dan kelengkapan konfigurasi SMTP | 11 test gabungan | Pengiriman melalui Laravel Mail dan pencatatan riwayat menggunakan Integration Test |
| Audit trail | Batas minimum dan maksimum masa retensi | 3 test kebijakan retensi audit | Penghapusan audit tetap memerlukan Integration Test database |
| Backup/restore | Batas retensi backup | 3 test kebijakan retensi backup | Pembuatan file, checksum, PostgreSQL, dan storage merupakan Integration/System Test |
| Laporan/XLSX | Konversi nilai dan kolom dapat dipisahkan kemudian | Belum menjadi unit terpisah | Pembuatan arsip XLSX dan query laporan merupakan Integration Test |
| Regional Manager | Status E-KIM memakai unit domain yang sama | Dicakup status E-KIM | Scope terminal, agregasi, dan akses hanya-baca menggunakan Integration Test |

## Distribusi Unit Test

| Kelas unit test | Unit yang diuji | Test | Assertion |
|---|---|---:|---:|
| `EkimStatusCalculatorTest` | Status dan batas waktu E-KIM | 6 | 12 |
| `SystemMailerTest` | Kesiapan konfigurasi SMTP | 8 | 8 |
| `ChecklistExpiryPolicyTest` | Masa berlaku dokumen checklist | 7 | 7 |
| `ChecklistFailureReasonBuilderTest` | Alasan penolakan checklist | 5 | 5 |
| `EkimVerificationEvaluatorTest` | Keaslian dan status verifikasi E-KIM | 6 | 17 |
| `EkimNotificationMessageTest` | Subjek dan isi notifikasi | 3 | 5 |
| `VehicleDataNormalizerTest` | Normalisasi nomor polisi dan email | 6 | 6 |
| `ChecklistWorkflowPolicyTest` | Transisi submit, reject, reset, dan delete | 10 | 18 |
| `ChecklistSignaturePolicyTest` | Role, tahap, nama, dan kelayakan tanda tangan | 10 | 16 |
| `EkimSignatureServiceTest` | Canonical hash, HMAC, dan verifikasi integritas | 9 | 12 |
| `RetentionPolicyTest` | Batas retensi audit dan backup | 6 | 6 |
| **Total** | **11 unit** | **76** | **112** |

## Skenario tambahan yang diuji

### Checklist dan dokumen

- Dokumen STNK tanpa tanggal masa berlaku ditolak.
- Dokumen yang kedaluwarsa sebelum tanggal pemeriksaan ditolak.
- Masa berlaku yang sama dengan tanggal pemeriksaan diterima.
- SIMFIT tidak diwajibkan untuk kendaraan SPBU.
- SIMFIT diwajibkan untuk kendaraan Industri.
- Item non-dokumen tidak dipaksa memiliki tanggal berlaku.
- Validasi mengembalikan kesalahan pertama secara konsisten.

### Alasan penolakan

- Tidak ada item gagal menghasilkan alasan kosong.
- Item tanpa catatan tetap menampilkan nama item.
- Catatan item ditambahkan dalam tanda kurung.
- Beberapa item dan catatan SPV digabungkan teratur.
- Nama item kosong dan catatan kosong diabaikan.

### Verifikasi E-KIM

- Dokumen approved dengan seluruh bukti valid dinyatakan autentik.
- Dokumen autentik tetapi kedaluwarsa berstatus `expired`.
- Dokumen rejected berstatus `invalid`.
- Dokumen draft berstatus `pending`.
- Bukti HSSE tidak valid menggagalkan autentikasi.
- Detail setiap bukti dan algoritma tetap dilaporkan.

### Kendaraan dan notifikasi

- Nomor polisi dinormalisasi menjadi huruf besar.
- Email transportir dinormalisasi menjadi huruf kecil.
- Field `email_transportir` diprioritaskan.
- Field lama `email_kontraktor` tetap menjadi fallback kompatibilitas.
- Pesan membedakan E-KIM akan kedaluwarsa dan telah kedaluwarsa.
- Subjek selalu memuat nomor polisi.

### Workflow dan tanda tangan

- Hanya Admin, User internal, dan HSSE yang dapat submit draft.
- HSSE hanya dapat menolak tahap `pending_hsse`.
- Manager hanya dapat menolak tahap `signed_hsse`.
- Checklist approved tidak dapat ditolak atau di-reset.
- Hanya Admin yang dapat menghapus checklist non-draft.
- Tanda tangan HSSE hanya dapat dilakukan HSSE atau Admin.
- Tanda tangan Manager hanya dapat dilakukan Integrated Terminal Manager.
- Nama penandatangan harus sesuai akun tanpa membedakan kapitalisasi.
- Item tidak baik dan E-KIM kedaluwarsa memblokir tanda tangan.

### Hash, integritas, dan retensi

- Data bisnis yang sama menghasilkan canonical hash yang sama.
- Field tanda tangan dan verifikasi tidak mengubah canonical hash.
- Perubahan nomor polisi atau item checklist mengubah hash.
- SHA-256 dan SHA-512 menghasilkan panjang hash yang sesuai.
- HMAC memakai format URL-safe dan hanya valid dengan hash serta secret benar.
- Retensi audit dibatasi antara 90–3.650 hari.
- Retensi backup dibatasi antara 7–365 hari.

## Perintah pengujian

```powershell
cd backend
C:\xampp\php\php.exe vendor\bin\phpunit --testsuite Unit
C:\xampp\php\php.exe vendor\bin\phpunit
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe artisan route:list --path=api/v1 --except-vendor
```

## Hasil aktual

- Unit Testing: **76 test, 112 assertion, seluruhnya lulus**.
- Durasi Unit Testing: **0,217 detik**.
- Regression keseluruhan: **113 test, 332 assertion, seluruhnya lulus**.
- Feature/Integration Test lama: **37 test, 220 assertion**.
- Laravel Pint: lulus.
- Route API: 61 route berhasil dimuat.

## Temuan dan perbaikan

| ID | Temuan | Penyebab | Perbaikan | Hasil uji ulang |
|---|---|---|---|---|
| FIND-UT-001 | Aturan masa berlaku dokumen bercampur dengan controller | Belum ada kelas domain checklist | Membuat `ChecklistExpiryPolicy` | Unit dan regression test lulus |
| FIND-UT-002 | Format alasan penolakan hanya dapat diuji melalui database | Query dan penyusunan teks berada pada satu method | Membuat `ChecklistFailureReasonBuilder` | Unit dan regression test lulus |
| FIND-UT-003 | Penentuan valid/expired/invalid/pending berada di endpoint verifikasi | Evaluasi bukti bercampur dengan HTTP | Membuat `EkimVerificationEvaluator` | Unit dan regression test lulus |
| FIND-UT-004 | Normalisasi kendaraan diduplikasi pada beberapa endpoint | Belum ada normalizer bersama | Membuat `VehicleDataNormalizer` | Unit dan regression test lulus |
| FIND-UT-005 | Isi email E-KIM dibentuk langsung pada command | Format pesan bercampur dengan pengiriman email | Membuat `EkimNotificationMessage` | Unit dan regression test lulus |
| FIND-UT-006 | Aturan submit, reject, reset, dan delete tersebar di controller | Belum ada kebijakan transisi terpusat | Membuat `ChecklistWorkflowPolicy` | Unit dan regression test lulus |
| FIND-UT-007 | Kewenangan dan kelayakan tanda tangan bercampur dengan endpoint | Validasi role, status, nama, dan isi dilakukan inline | Membuat `ChecklistSignaturePolicy` | Unit dan regression test lulus |
| FIND-UT-008 | Canonical hash dan HMAC hanya dapat diuji melalui endpoint | Logika kriptografi berada pada method private controller | Membuat `EkimSignatureService` | Unit dan regression test lulus |
| FIND-UT-009 | Batas retensi ditulis langsung dalam service database | Kebijakan dan proses penghapusan bercampur | Membuat `RetentionPolicy` | Unit dan regression test lulus |

Tidak ditemukan assertion gagal pada pelaksanaan Unit Testing lanjutan.

## Batasan dan tahap berikutnya

Jumlah 76 test tidak berarti seluruh sistem telah diuji pada tingkat unit. Bagian
yang bergantung pada database, routing, storage, email transport, dan Docker harus
tetap diuji pada Integration atau System Testing. Kandidat Unit Testing berikutnya:

1. validasi metadata dan checksum backup;
2. pemetaan kolom dan escaping data XLSX;
3. kebijakan lockout login dan masa berlaku token reset password;
4. pembentukan statistik laporan tanpa query database;
5. sanitasi nama file dan validasi tipe dokumen.
