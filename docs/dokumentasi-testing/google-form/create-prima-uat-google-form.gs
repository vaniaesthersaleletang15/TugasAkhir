/**
 * Membuat Google Form UAT PRIMA lengkap beserta spreadsheet respons.
 * Jalankan fungsi createPrimaUatForm() dari https://script.google.com.
 */
const CRITICAL_SCENARIO_IDS = [
  "UAT-SPV-01",
  "UAT-SPV-03",
  "UAT-SPV-08",
  "UAT-SPV-09",
  "UAT-HSSE-01",
  "UAT-HSSE-05",
  "UAT-HSSE-07",
  "UAT-HSSE-08",
  "UAT-HSSE-10",
  "UAT-HSSE-11",
  "UAT-ITM-01",
  "UAT-ITM-03",
  "UAT-ITM-04",
  "UAT-ITM-05",
  "UAT-ITM-08",
  "UAT-TRP-01",
  "UAT-TRP-02",
  "UAT-TRP-04",
  "UAT-TRP-06",
  "UAT-TRP-07",
];

function createPrimaUatForm() {
  const title = "User Acceptance Testing (UAT) - Sistem PRIMA E-KIM";
  const form = FormApp.create(title)
    .setDescription(
      "UAT PRIMA E-KIM untuk PT Pertamina Patra Niaga Integrated Terminal. Pengujian ini menilai kesesuaian sistem dengan proses kerja SPV HSSE, Tim HSSE, Transportir/Pengurus Mobil Tangki, dan Integrated Terminal Manager. Pengujian tidak menilai kemampuan responden.",
    )
    .setCollectEmail(true)
    .setProgressBar(true)
    .setShuffleQuestions(false)
    .setConfirmationMessage(
      "Terima kasih. Respons UAT PRIMA telah tersimpan. Jika menemukan masalah kritis, segera informasikan kepada pendamping pengujian.",
    );

  const timestamp = Utilities.formatDate(
    new Date(),
    "Asia/Makassar",
    "yyyy-MM-dd HH.mm.ss",
  );
  const folder = DriveApp.createFolder("UAT PRIMA - " + timestamp);
  const responseSheet = SpreadsheetApp.create(
    "Respons UAT PRIMA - " + timestamp,
  );
  form.setDestination(
    FormApp.DestinationType.SPREADSHEET,
    responseSheet.getId(),
  );
  DriveApp.getFileById(form.getId()).moveTo(folder);
  DriveApp.getFileById(responseSheet.getId()).moveTo(folder);

  form
    .addSectionHeaderItem()
    .setTitle("Identitas Responden")
    .setHelpText(
      "Data digunakan untuk dokumentasi pengujian skripsi dan evaluasi sistem.",
    );
  form.addTextItem().setTitle("Nama responden").setRequired(true);
  form.addTextItem().setTitle("Jabatan").setRequired(true);
  form
    .addTextItem()
    .setTitle("Integrated Terminal / unit kerja")
    .setRequired(true);
  form.addDateItem().setTitle("Tanggal pengujian").setRequired(true);
  form
    .addTextItem()
    .setTitle("Perangkat dan browser yang digunakan")
    .setRequired(true);
  form.addTextItem().setTitle("Nama pendamping pengujian").setRequired(false);
  form
    .addSectionHeaderItem()
    .setTitle("Petunjuk Pelaksanaan")
    .setHelpText(
      "Gunakan akun dan data pengujian yang disiapkan pendamping. Jalankan seluruh skenario sesuai urutan. Lulus berarti hasil aktual sama dengan hasil yang diharapkan; Gagal berarti berbeda; Tidak diuji berarti tidak dapat dilakukan dan wajib dijelaskan pada catatan.",
    );

  const roleItem = form
    .addMultipleChoiceItem()
    .setTitle("Pilih role yang diuji")
    .setHelpText(
      "Pilih satu role yang benar-benar digunakan saat pengujian. Form akan membuka skenario khusus role tersebut.",
    )
    .setRequired(true);

  const roleDefinitions = [
    {
      label: "SPV HSSE / Administrator Sistem",
      code: "SPV",
      tasks: [
        task(
          "UAT-SPV-01",
          "Login melalui portal internal",
          "Dashboard SPV HSSE tampil dan akun dikenali pada Integrated Terminal yang benar",
        ),
        task(
          "UAT-SPV-02",
          "Melihat pendaftaran akun Transportir",
          "Hanya pendaftaran pada Integrated Terminal SPV yang tampil",
        ),
        task(
          "UAT-SPV-03",
          "Menyetujui pendaftaran Transportir",
          "Akun menjadi aktif, terhubung ke transportir, dan dapat digunakan untuk login",
        ),
        task(
          "UAT-SPV-04",
          "Menolak pendaftaran Transportir dengan alasan",
          "Status ditolak dan alasan penolakan tersimpan serta dapat ditelusuri",
        ),
        task(
          "UAT-SPV-05",
          "Membuat atau memperbarui akun Tim HSSE, ITM, dan Transportir",
          "Akun tersimpan dengan role, status, terminal, dan relasi transportir yang benar",
        ),
        task(
          "UAT-SPV-06",
          "Menonaktifkan akun operasional",
          "Akun menjadi tidak aktif dan token akses lama tidak dapat digunakan",
        ),
        task(
          "UAT-SPV-07",
          "Membuat dan mengelola master transportir",
          "Data transportir tersimpan hanya pada terminal SPV dan status aktif/nonaktif berfungsi",
        ),
        task(
          "UAT-SPV-08",
          "Meninjau lalu menyetujui atau menolak dokumen kendaraan dengan catatan",
          "Status dokumen, pemeriksa, waktu, dan catatan keputusan tersimpan",
        ),
        task(
          "UAT-SPV-09",
          "Meninjau pengajuan kendaraan dari Transportir",
          "SPV dapat menyetujui/menolak kendaraan dan keputusan tercatat pada riwayat",
        ),
        task(
          "UAT-SPV-10",
          "Melihat dashboard, audit, dan peringatan E-KIM",
          "Ringkasan hanya memuat data terminal SPV dan sesuai dengan data operasional",
        ),
        task(
          "UAT-SPV-11",
          "Mengekspor laporan checklist",
          "File XLSX berhasil diunduh, dapat dibuka, dan mengikuti filter serta scope terminal",
        ),
        task(
          "UAT-SPV-12",
          "Mengubah pengaturan sesi, retensi, notifikasi, atau maintenance",
          "Pengaturan tersimpan dan perubahan tercatat dalam audit log",
        ),
        task(
          "UAT-SPV-13",
          "Mengajukan permohonan restore database",
          "Permohonan tercatat dan menunggu keputusan Integrated Terminal Manager",
        ),
      ],
      statements: [
        "Dashboard menampilkan informasi yang diperlukan SPV HSSE.",
        "Proses persetujuan atau penolakan akun Transportir mudah dipahami.",
        "Pengelolaan akun Tim HSSE, ITM, dan Transportir sesuai kewenangan SPV.",
        "Proses verifikasi dokumen dan persetujuan kendaraan sesuai proses kerja aktual.",
        "Peringatan masa berlaku E-KIM membantu kegiatan monitoring.",
        "Audit trail cukup jelas untuk menelusuri aktivitas pengguna.",
        "Data terminal lain tidak terlihat atau tidak dapat diubah oleh SPV.",
        "Pengaturan sistem, audit, dan laporan XLSX memenuhi kebutuhan operasional.",
        "Pesan kesalahan dan konfirmasi sistem mudah dimengerti.",
        "Secara keseluruhan, sistem layak digunakan oleh SPV HSSE.",
      ],
    },
    {
      label: "Tim HSSE",
      code: "HSSE",
      tasks: [
        task(
          "UAT-HSSE-01",
          "Login melalui portal internal",
          "Dashboard Tim HSSE tampil dan hanya memuat data terminal sendiri",
        ),
        task(
          "UAT-HSSE-02",
          "Mencari dan memilih kendaraan yang akan diperiksa",
          "Hanya kendaraan aktif pada terminal sendiri yang dapat dipilih",
        ),
        task(
          "UAT-HSSE-03",
          "Membuka checklist kendaraan SPBU",
          "Template dan item pemeriksaan SPBU tampil lengkap",
        ),
        task(
          "UAT-HSSE-04",
          "Membuka checklist kendaraan Industri",
          "Template dan item pemeriksaan Industri tampil lengkap",
        ),
        task(
          "UAT-HSSE-05",
          "Mengisi identitas pemeriksa, tanggal, masa berlaku E-KIM, dan seluruh item",
          "Data dapat diisi dan setiap item hanya menerima pilihan Baik atau Tidak",
        ),
        task(
          "UAT-HSSE-06",
          "Memberi nilai Tidak tanpa keterangan lalu melengkapinya",
          "Sistem menolak data tanpa keterangan dan menerima setelah alasan temuan diisi",
        ),
        task(
          "UAT-HSSE-07",
          "Menyimpan, membuka, dan memperbarui draft checklist",
          "Draft tersimpan dan seluruh isian dapat dilanjutkan kembali",
        ),
        task(
          "UAT-HSSE-08",
          "Mengirim checklist yang lengkap",
          "Checklist berpindah ke tahap persetujuan HSSE dan data pemeriksaan tersimpan",
        ),
        task(
          "UAT-HSSE-09",
          "Mengirim checklist ketika dokumen wajib belum disetujui",
          "Sistem menolak pengiriman dan menjelaskan dokumen yang belum memenuhi syarat",
        ),
        task(
          "UAT-HSSE-10",
          "Meninjau lalu menandatangani atau menolak checklist",
          "Nama, waktu, tanda tangan, status, dan alasan keputusan HSSE tersimpan",
        ),
        task(
          "UAT-HSSE-11",
          "Mencoba mengubah checklist terminal lain atau checklist final",
          "Sistem menolak akses/perubahan yang tidak sesuai kewenangan",
        ),
      ],
      statements: [
        "Daftar kendaraan mudah dicari dan dipilih.",
        "Item checklist SPBU/Industri sesuai dengan pemeriksaan lapangan.",
        "Istilah Baik, Tidak, keterangan, dan tanggal berlaku mudah dipahami.",
        "Validasi tanggal dokumen membantu mencegah kesalahan pemeriksaan.",
        "Draft checklist memudahkan pekerjaan yang belum selesai.",
        "Alasan penolakan menggambarkan temuan pemeriksaan dengan benar.",
        "Proses tanda tangan digital HSSE mudah dilakukan.",
        "Status workflow menunjukkan posisi checklist dengan jelas.",
        "Sistem mencegah perubahan checklist yang sudah masuk tahap berikutnya.",
        "Secara keseluruhan, sistem mendukung pemeriksaan HSSE di lapangan.",
      ],
    },
    {
      label: "Integrated Terminal Manager (ITM)",
      code: "ITM",
      tasks: [
        task(
          "UAT-ITM-01",
          "Login melalui portal internal",
          "Dashboard ITM tampil dan hanya memuat data Integrated Terminal sendiri",
        ),
        task(
          "UAT-ITM-02",
          "Membuka antrean checklist yang sudah disetujui HSSE",
          "Hanya checklist pada tahap persetujuan ITM yang relevan yang tampil",
        ),
        task(
          "UAT-ITM-03",
          "Membuka detail checklist dan hasil pemeriksaan HSSE",
          "Data kendaraan, seluruh item, tanggal, dokumen, serta bukti persetujuan HSSE tampil lengkap",
        ),
        task(
          "UAT-ITM-04",
          "Menyetujui dan menandatangani checklist",
          "Status menjadi disetujui final dan bukti tanda tangan/verifikasi terbentuk",
        ),
        task(
          "UAT-ITM-05",
          "Menolak checklist dengan alasan",
          "Status menjadi ditolak dan alasan keputusan tersimpan",
        ),
        task(
          "UAT-ITM-06",
          "Membuka riwayat persetujuan sendiri",
          "Riwayat menampilkan keputusan yang dilakukan oleh ITM tersebut",
        ),
        task(
          "UAT-ITM-07",
          "Memeriksa informasi verifikasi tanda tangan/E-KIM",
          "Status keaslian bukti, masa berlaku, dan pihak penandatangan dapat dipahami",
        ),
        task(
          "UAT-ITM-08",
          "Mencoba mengubah isi checklist atau kendaraan",
          "Sistem menolak perubahan karena ITM hanya berwenang memberi keputusan akhir",
        ),
        task(
          "UAT-ITM-09",
          "Meninjau permohonan restore dari SPV",
          "ITM dapat menyetujui/menolak dengan catatan dan keputusan tersimpan dalam audit",
        ),
      ],
      statements: [
        "Daftar checklist yang menunggu persetujuan mudah ditemukan.",
        "Detail hasil pemeriksaan HSSE cukup untuk mengambil keputusan.",
        "Status dan tanda tangan HSSE terlihat jelas sebelum persetujuan.",
        "Proses persetujuan dan tanda tangan ITM mudah dilakukan.",
        "Sistem mencegah persetujuan jika tahapan sebelumnya belum lengkap.",
        "Proses penolakan dan pemberian alasan mudah dipahami.",
        "Riwayat persetujuan dan keputusan restore membantu penelusuran keputusan ITM.",
        "Data terminal lain terlindungi dari akses yang tidak sesuai.",
        "Informasi verifikasi E-KIM mudah dipahami.",
        "Secara keseluruhan, sistem layak digunakan dalam proses persetujuan ITM.",
      ],
    },
    {
      label: "Transportir / Pengurus Mobil Tangki",
      code: "TRP",
      tasks: [
        task(
          "UAT-TRP-01",
          "Mendaftarkan akun dengan memilih Integrated Terminal dan transportir",
          "Pendaftaran terkirim ke SPV terminal yang dipilih dengan status menunggu",
        ),
        task(
          "UAT-TRP-02",
          "Login melalui portal Transportir setelah akun disetujui",
          "Dashboard Transportir tampil dan akun dikenali dengan benar",
        ),
        task(
          "UAT-TRP-03",
          "Memperbarui profil dan mengganti password",
          "Profil tersimpan dan password baru dapat digunakan",
        ),
        task(
          "UAT-TRP-04",
          "Mengajukan kendaraan mobil tangki",
          "Data kendaraan tersimpan dengan status menunggu persetujuan SPV",
        ),
        task(
          "UAT-TRP-05",
          "Memperbarui data kendaraan yang dikelola",
          "Perubahan tersimpan dan kembali menunggu persetujuan SPV",
        ),
        task(
          "UAT-TRP-06",
          "Mengunggah dokumen kendaraan beserta jenis dan tanggal berlaku",
          "File tersimpan secara privat dengan status menunggu verifikasi",
        ),
        task(
          "UAT-TRP-07",
          "Mencoba mengakses atau mengunggah dokumen kendaraan milik pihak lain",
          "Sistem menolak akses terhadap kendaraan di luar kewenangan akun",
        ),
        task(
          "UAT-TRP-08",
          "Melihat, mengunduh, dan menghapus dokumen sendiri yang masih menunggu",
          "Dokumen sendiri dapat dikelola dan penghapusan hanya diizinkan saat masih menunggu",
        ),
        task(
          "UAT-TRP-09",
          "Melihat status dan catatan dokumen yang ditolak",
          "Status serta catatan SPV terlihat dan dokumen dapat diperbaiki/diunggah ulang",
        ),
        task(
          "UAT-TRP-10",
          "Melihat daftar kendaraan dan status checklist/E-KIM",
          "Hanya kendaraan yang dikelola akun beserta status terbarunya yang tampil",
        ),
        task(
          "UAT-TRP-11",
          "Melihat lalu menandai notifikasi penerbitan/penolakan E-KIM",
          "Notifikasi sesuai kendaraan dan jumlah notifikasi belum dibaca berkurang",
        ),
        task(
          "UAT-TRP-12",
          "Meminta dan menggunakan reset password",
          "Password baru dapat digunakan dan token reset hanya berlaku satu kali",
        ),
      ],
      statements: [
        "Proses pendaftaran akun mudah dipahami.",
        "Pemilihan Integrated Terminal pada pendaftaran sudah jelas.",
        "Daftar kendaraan menampilkan kendaraan yang benar.",
        "Proses unggah dokumen kendaraan mudah dilakukan.",
        "Jenis dokumen dan tanggal berlaku mudah dipahami.",
        "Status menunggu, disetujui, atau ditolak terlihat jelas.",
        "Catatan penolakan dokumen atau E-KIM membantu proses perbaikan.",
        "Notifikasi E-KIM diterima oleh akun dan kendaraan yang tepat.",
        "Fitur reset password mudah dan aman digunakan.",
        "Secara keseluruhan, sistem memenuhi kebutuhan Transportir/Pengurus Mobil Tangki.",
      ],
    },
  ];

  const rolePages = [];
  roleDefinitions.forEach(function (role) {
    const page = form
      .addPageBreakItem()
      .setTitle("UAT Role " + role.label)
      .setHelpText(
        "Jalankan setiap skenario. Pilih Tidak diuji jika skenario tidak dapat dilakukan dan jelaskan pada kolom catatan.",
      );
    rolePages.push(page);
    addTaskQuestions(form, role.tasks);
    form
      .addParagraphTextItem()
      .setTitle("Catatan hasil skenario " + role.label)
      .setHelpText(
        "Tuliskan ID atau nama skenario yang gagal/tidak diuji dan jelaskan hasil aktualnya.",
      )
      .setRequired(false);
    addScaleGrid(form, "Penilaian penerimaan " + role.label, role.statements);
  });

  const commonPage = form
    .addPageBreakItem()
    .setTitle("Penilaian Umum dan Keputusan UAT")
    .setHelpText("Bagian ini wajib diisi oleh seluruh role.");

  const commonStatements = [
    "Proses login mudah dilakukan.",
    "Menu dan tombol mudah ditemukan.",
    "Istilah yang digunakan mudah dipahami.",
    "Informasi yang ditampilkan lengkap dan relevan.",
    "Waktu respons sistem dapat diterima.",
    "Pesan berhasil, gagal, dan validasi mudah dimengerti.",
    "Tampilan dapat digunakan pada perangkat kerja responden.",
    "Data hanya dapat diakses oleh pihak yang berwenang.",
    "Sistem mengurangi pekerjaan manual dibanding proses sebelumnya.",
    "Saya bersedia menggunakan sistem ini dalam pekerjaan.",
  ];
  addScaleGrid(form, "Penilaian umum sistem", commonStatements);

  form
    .addParagraphTextItem()
    .setTitle("Fitur apa yang paling membantu pekerjaan Anda?")
    .setRequired(true);
  form
    .addParagraphTextItem()
    .setTitle("Bagian apa yang paling sulit atau membingungkan?")
    .setRequired(false);
  form
    .addParagraphTextItem()
    .setTitle("Apakah ada data atau informasi penting yang belum ditampilkan?")
    .setRequired(false);
  form
    .addParagraphTextItem()
    .setTitle(
      "Apakah alur sistem sudah sesuai proses kerja aktual? Jelaskan jika belum.",
    )
    .setRequired(true);
  form
    .addParagraphTextItem()
    .setTitle(
      "Perbaikan apa yang paling diprioritaskan sebelum sistem digunakan?",
    )
    .setRequired(false);
  form
    .addParagraphTextItem()
    .setTitle("Catatan defect/masalah yang ditemukan")
    .setHelpText(
      "Cantumkan langkah, hasil aktual, hasil yang diharapkan, dan tingkat dampak: Kritis/Tinggi/Sedang/Rendah.",
    )
    .setRequired(false);
  form
    .addMultipleChoiceItem()
    .setTitle("Tingkat defect tertinggi yang ditemukan")
    .setChoiceValues([
      "Tidak ada defect",
      "Rendah",
      "Sedang",
      "Tinggi",
      "Kritis",
    ])
    .setRequired(true);
  form
    .addTextItem()
    .setTitle("Tautan bukti screenshot atau video (jika ada)")
    .setRequired(false);
  form
    .addMultipleChoiceItem()
    .setTitle("Keputusan penerimaan sistem")
    .setChoiceValues([
      "Sistem diterima tanpa perbaikan",
      "Sistem diterima dengan perbaikan minor",
      "Sistem diterima dengan syarat perbaikan tertentu",
      "Sistem belum dapat diterima",
    ])
    .setRequired(true);
  form
    .addParagraphTextItem()
    .setTitle("Alasan keputusan penerimaan")
    .setRequired(true);
  form
    .addCheckboxItem()
    .setTitle("Pernyataan responden")
    .setChoiceValues([
      "Saya menyatakan bahwa jawaban ini sesuai dengan hasil pengujian yang saya lakukan.",
    ])
    .setRequired(true);
  form
    .addTextItem()
    .setTitle("Nama lengkap sebagai pengganti tanda tangan elektronik")
    .setRequired(true);

  roleItem.setChoices(
    roleDefinitions.map(function (role, index) {
      return roleItem.createChoice(role.label, rolePages[index]);
    }),
  );
  rolePages.forEach(function (page) {
    page.setGoToPage(commonPage);
  });
  commonPage.setGoToPage(FormApp.PageNavigationType.SUBMIT);

  const totalTasks = roleDefinitions.reduce(function (total, role) {
    return total + role.tasks.length;
  }, 0);
  const infoSheet = responseSheet.insertSheet("PETUNJUK DAN REKAP");
  infoSheet.getRange("A1:B12").setValues([
    ["UAT PRIMA", "Petunjuk rekapitulasi"],
    ["Versi instrumen", "2 November 2026 - empat role"],
    ["Edit Form", form.getEditUrl()],
    ["Form Responden", form.getPublishedUrl()],
    ["Folder Google Drive", folder.getUrl()],
    [
      "Role UAT",
      roleDefinitions
        .map(function (role) {
          return role.label;
        })
        .join("; "),
    ],
    ["Jumlah skenario", totalTasks],
    ["Kriteria 1", "Seluruh skenario kritis harus Lulus"],
    ["Kriteria 2", "Keberhasilan skenario minimal 80%"],
    ["Kriteria 3", "Nilai penerimaan minimal 80%"],
    ["Kriteria 4", "Tidak ada defect Kritis/Tinggi yang belum selesai"],
    [
      "Catatan",
      "Jangan mengubah jawaban asli responden. Catat UAT ulang sebagai respons baru.",
    ],
  ]);
  infoSheet.autoResizeColumns(1, 2);

  console.log("EDIT_FORM_URL=" + form.getEditUrl());
  console.log("RESPONDENT_FORM_URL=" + form.getPublishedUrl());
  console.log("RESPONSE_SHEET_URL=" + responseSheet.getUrl());
  console.log("DRIVE_FOLDER_URL=" + folder.getUrl());
}

function task(id, action, expected) {
  return { id: id, action: action, expected: expected };
}

function addTaskQuestions(form, tasks) {
  tasks.forEach(function (scenario) {
    form
      .addMultipleChoiceItem()
      .setTitle(
        (CRITICAL_SCENARIO_IDS.includes(scenario.id) ? "[KRITIS] " : "") +
          scenario.id +
          " — " +
          scenario.action,
      )
      .setHelpText("Hasil yang diharapkan: " + scenario.expected)
      .setChoiceValues(["Lulus", "Gagal", "Tidak diuji"])
      .setRequired(true);
  });
}

function addScaleGrid(form, title, statements) {
  form
    .addGridItem()
    .setTitle(title)
    .setHelpText(
      "1 = Sangat tidak setuju; 2 = Tidak setuju; 3 = Netral; 4 = Setuju; 5 = Sangat setuju.",
    )
    .setRows(statements)
    .setColumns(["1", "2", "3", "4", "5"])
    .setRequired(true);
}
