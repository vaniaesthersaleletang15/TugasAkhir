const DEFAULT_API_MESSAGES = Object.freeze({
  "Unauthenticated.": "Sesi Anda telah berakhir. Silakan masuk kembali.",
  Unauthorized: "Anda tidak memiliki izin untuk mengakses halaman ini.",
  Forbidden: "Akses ditolak.",
  "Not Found": "Data yang diminta tidak ditemukan.",
  "Method Not Allowed": "Metode permintaan tidak diizinkan.",
  "Too Many Attempts.":
    "Terlalu banyak percobaan. Silakan coba kembali beberapa saat lagi.",
  "Server Error": "Terjadi kesalahan pada server. Silakan coba kembali.",
});

const STATUS_MESSAGES = Object.freeze({
  401: "Sesi Anda telah berakhir. Silakan masuk kembali.",
  403: "Akses ditolak.",
  404: "Data yang diminta tidak ditemukan.",
  405: "Metode permintaan tidak diizinkan.",
  419: "Sesi telah kedaluwarsa. Silakan muat ulang halaman.",
  422: "Data yang diberikan tidak valid.",
  429: "Terlalu banyak permintaan. Silakan coba kembali beberapa saat lagi.",
  500: "Terjadi kesalahan pada server. Silakan coba kembali.",
  502: "Server belum dapat dihubungi. Silakan coba kembali.",
  503: "Layanan sedang tidak tersedia. Silakan coba kembali nanti.",
});

export function localizeApiMessage(message, status) {
  const normalized = String(message || "").trim();
  if (DEFAULT_API_MESSAGES[normalized]) return DEFAULT_API_MESSAGES[normalized];
  if (normalized) return normalized;

  return STATUS_MESSAGES[status] || `Permintaan gagal (${status}).`;
}
