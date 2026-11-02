# Dokumentasi Testing

Folder ini menyimpan bukti pengujian untuk setiap perubahan aplikasi. Hasil gagal
dan warning tetap dicatat agar proses perbaikan dan regression testing dapat
ditelusuri untuk kebutuhan V-Model dan laporan skripsi.

## Penamaan file

Gunakan format:

```text
YYYY-MM-DD-nama-pengujian.md
```

## Isi minimum

- perubahan yang diuji;
- lingkungan dan data pengujian;
- perintah atau langkah pengujian;
- hasil yang diharapkan dan hasil aktual;
- jumlah test dan assertion apabila tersedia;
- status lulus, gagal, warning, atau belum diuji;
- temuan, perbaikan, dan hasil pengujian ulang;
- tautan ke dokumentasi perubahan harian.

Jangan mengubah kegagalan menjadi hasil lulus tanpa mencatat perbaikan dan
pengujian ulang. Gunakan [`TEMPLATE.md`](TEMPLATE.md) untuk laporan baru.
