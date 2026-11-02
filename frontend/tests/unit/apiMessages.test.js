import test from "node:test";
import assert from "node:assert/strict";

import { localizeApiMessage } from "../../src/config/apiMessages.js";

test("pesan autentikasi bawaan diterjemahkan ke bahasa Indonesia", () => {
  assert.equal(
    localizeApiMessage("Unauthenticated.", 401),
    "Sesi Anda telah berakhir. Silakan masuk kembali.",
  );
  assert.equal(localizeApiMessage("Forbidden", 403), "Akses ditolak.");
});

test("status tanpa pesan menggunakan respons bahasa Indonesia", () => {
  assert.equal(
    localizeApiMessage("", 404),
    "Data yang diminta tidak ditemukan.",
  );
  assert.equal(
    localizeApiMessage(null, 500),
    "Terjadi kesalahan pada server. Silakan coba kembali.",
  );
});

test("pesan khusus dari backend tetap dipertahankan", () => {
  assert.equal(
    localizeApiMessage("Nomor polisi sudah digunakan.", 422),
    "Nomor polisi sudah digunakan.",
  );
});
