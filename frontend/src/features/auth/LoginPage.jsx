import { useEffect, useRef, useState } from "react";
import {
  AlertTriangle,
  ClipboardCheck,
  Eye,
  EyeOff,
  ShieldCheck,
} from "lucide-react";
import { api, assetUrl } from "../../api";

let turnstileLoader;

function loadTurnstile() {
  if (window.turnstile) return Promise.resolve(window.turnstile);
  if (turnstileLoader) return turnstileLoader;

  turnstileLoader = new Promise((resolve, reject) => {
    const existing = document.getElementById("cloudflare-turnstile-script");
    const script = existing || document.createElement("script");
    const loaded = () =>
      window.turnstile
        ? resolve(window.turnstile)
        : reject(new Error("Layanan CAPTCHA tidak tersedia."));

    script.addEventListener("load", loaded, { once: true });
    script.addEventListener(
      "error",
      () => reject(new Error("Layanan CAPTCHA gagal dimuat.")),
      { once: true },
    );
    if (!existing) {
      script.id = "cloudflare-turnstile-script";
      script.src =
        "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
      script.async = true;
      script.defer = true;
      document.head.appendChild(script);
    }
  });

  return turnstileLoader;
}

function TurnstileCaptcha({ siteKey, resetKey, onVerify, onError }) {
  const container = useRef(null);
  const callbacks = useRef({ onVerify, onError });
  callbacks.current = { onVerify, onError };

  useEffect(() => {
    let active = true;
    let widgetId;

    loadTurnstile()
      .then((turnstile) => {
        if (!active || !container.current) return;
        widgetId = turnstile.render(container.current, {
          sitekey: siteKey,
          action: "login",
          theme: "light",
          size: "flexible",
          callback: (token) => callbacks.current.onVerify(token),
          "expired-callback": () =>
            callbacks.current.onError(
              "CAPTCHA kedaluwarsa. Silakan verifikasi kembali.",
            ),
          "error-callback": () =>
            callbacks.current.onError(
              "CAPTCHA gagal diverifikasi. Silakan coba kembali.",
            ),
        });
      })
      .catch((error) => callbacks.current.onError(error.message));

    return () => {
      active = false;
      if (widgetId !== undefined && window.turnstile) {
        window.turnstile.remove(widgetId);
      }
    };
  }, [siteKey, resetKey]);

  return <div className="turnstile-widget" ref={container} />;
}

export default function LoginPage({ onLogin, portal }) {
  const [form, setForm] = useState({ email: "", password: "" });
  const [showPassword, setShowPassword] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [captchaConfig, setCaptchaConfig] = useState(null);
  const [captchaToken, setCaptchaToken] = useState("");
  const [captchaError, setCaptchaError] = useState("");
  const [captchaReset, setCaptchaReset] = useState(0);

  useEffect(() => {
    let active = true;
    api
      .captchaConfig()
      .then((response) => {
        if (active) setCaptchaConfig(response.data);
      })
      .catch((requestError) => {
        if (active) setCaptchaError(requestError.message);
      });

    return () => {
      active = false;
    };
  }, []);

  const submit = async (event) => {
    event.preventDefault();
    if (captchaConfig?.enabled && !captchaToken) {
      setCaptchaError("Selesaikan verifikasi CAPTCHA terlebih dahulu.");
      return;
    }
    setBusy(true);
    setError("");
    try {
      const result = await api.login({
        ...form,
        portal,
        captcha_token: captchaToken,
      });
      onLogin(result.data);
    } catch (err) {
      setError(err.message);
      setCaptchaToken("");
      setCaptchaReset((value) => value + 1);
    } finally {
      setBusy(false);
    }
  };
  return (
    <main className="login-page">
      <section
        className="login-panel brand-panel"
        style={{ "--login-bg": `url("${assetUrl("foto/bgpertamina.png")}")` }}
      >
        <div className="brand-kicker">
          <span /> Sistem inspeksi terintegrasi
        </div>
        <div className="brand-lockup">
          <img
            className="brand-prima-logo"
            src={assetUrl("foto/logo-prima.png")}
            alt="Logo PRIMA"
          />
          <div className="brand-divider" />
          <div className="brand-partners">
            <img
              className="brand-hsse-logo"
              src={assetUrl("foto/Logo HSSE 2022.png")}
              alt="Logo HSSE Integrated Terminal Bitung"
            />
            <img
              className="brand-pertamina-logo"
              src={assetUrl("foto/PT_Pertamina_Patra_Niaga.png")}
              alt="Pertamina Patra Niaga"
            />
          </div>
        </div>
        <p className="eyebrow">PRIMA</p>
        <h1>Pertamina Checklist Mobil Tangki</h1>
        <p>
          Kelola inspeksi, persetujuan, dan kelayakan kendaraan dalam satu ruang
          kerja yang aman.
        </p>
        <div className="brand-points">
          <span>
            <ShieldCheck /> Data terproteksi
          </span>
          <span>
            <ClipboardCheck /> Alur terdokumentasi
          </span>
        </div>
      </section>
      <section className="login-panel form-panel">
        <div className="login-form-wrap">
          <div className="login-mobile-brand">
            <img src={assetUrl("foto/logo-prima.png")} alt="Logo PRIMA" />
            <div>
              <strong>PRIMA</strong>
              <span>Pertamina Checklist Mobil Tangki</span>
            </div>
          </div>
          <p className="eyebrow red">
            {portal === "internal" ? "Portal internal" : "Portal transportir"}
          </p>
          <h2>
            {portal === "internal"
              ? "Masuk sebagai petugas"
              : "Masuk sebagai transportir"}
          </h2>
          <p className="muted">
            {portal === "internal"
              ? "Khusus SPV HSSE, Tim HSSE, dan Manager."
              : "Kelola kendaraan dan dokumen menggunakan akun transportir yang telah disetujui."}
          </p>
          {error && (
            <div className="alert">
              <AlertTriangle size={18} />
              {error}
            </div>
          )}
          <form onSubmit={submit}>
            <label>
              Email
              <input
                autoFocus
                type="email"
                autoComplete="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                required
              />
            </label>
            <label>
              Password
              <span className="password-input-wrap">
                <input
                  type={showPassword ? "text" : "password"}
                  autoComplete="current-password"
                  value={form.password}
                  onChange={(e) =>
                    setForm({ ...form, password: e.target.value })
                  }
                />
                <button
                  type="button"
                  className="password-toggle"
                  onClick={() => setShowPassword((value) => !value)}
                  aria-label={
                    showPassword ? "Sembunyikan password" : "Tampilkan password"
                  }
                  title={
                    showPassword ? "Sembunyikan password" : "Tampilkan password"
                  }
                >
                  {showPassword ? <EyeOff size={19} /> : <Eye size={19} />}
                </button>
              </span>
            </label>
            <div className="captcha-field">
              <span>Verifikasi keamanan</span>
              {!captchaConfig && !captchaError && (
                <div className="captcha-loading">
                  <span className="spinner" /> Memuat CAPTCHA…
                </div>
              )}
              {captchaConfig?.enabled && (
                <TurnstileCaptcha
                  siteKey={captchaConfig.site_key}
                  resetKey={captchaReset}
                  onVerify={(token) => {
                    setCaptchaToken(token);
                    setCaptchaError("");
                  }}
                  onError={(message) => {
                    setCaptchaToken("");
                    setCaptchaError(message);
                  }}
                />
              )}
              {captchaConfig?.enabled === false && (
                <small>CAPTCHA dinonaktifkan pada lingkungan ini.</small>
              )}
              {captchaError && (
                <small className="captcha-error">{captchaError}</small>
              )}
            </div>
            <button
              className="button primary wide"
              disabled={
                busy ||
                !captchaConfig ||
                !!captchaError ||
                (captchaConfig.enabled && !captchaToken)
              }
            >
              {busy ? "Memproses…" : "Masuk"}
            </button>
          </form>
          {portal === "transportir" && (
            <p className="register-link">
              Belum punya akun?{" "}
              <a href="#/transportir/register">Ajukan pendaftaran</a>
            </p>
          )}
          {portal === "transportir" && (
            <p className="register-link">
              Lupa password?{" "}
              <a href="#/transportir/forgot-password">Reset melalui email</a>
            </p>
          )}
        </div>
      </section>
    </main>
  );
}
