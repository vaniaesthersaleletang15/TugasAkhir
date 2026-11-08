import { useEffect, useState } from "react";
import { api } from "../../api";
import { ErrorState, LoadingState } from "../../components/AsyncState";

function Notice({ value }) {
  return value ? (
    <div className={`alert ${value.ok ? "success" : ""}`}>{value.text}</div>
  ) : null;
}

export function ProfilePage({ user }) {
  const [profile, setProfile] = useState({
    full_name: user.full_name || "",
    email: user.email || "",
    phone: user.phone || "",
    current_password: "",
  });
  const [password, setPassword] = useState({
    current_password: "",
    password: "",
    password_confirmation: "",
  });
  const [notice, setNotice] = useState(null);
  const submitProfile = async (event) => {
    event.preventDefault();
    try {
      const result = await api.updateProfile(profile);
      setNotice({ ok: true, text: result.message });
      setProfile((v) => ({ ...v, current_password: "" }));
    } catch (error) {
      setNotice({ text: error.message });
    }
  };
  const submitPassword = async (event) => {
    event.preventDefault();
    try {
      const result = await api.changePassword(password);
      setNotice({ ok: true, text: result.message });
      setPassword({
        current_password: "",
        password: "",
        password_confirmation: "",
      });
    } catch (error) {
      setNotice({ text: error.message });
    }
  };
  return (
    <main className="page">
      <div className="page-heading">
        <div>
          <p className="eyebrow red">Akun</p>
          <h1>Profil saya</h1>
          <p>
            Perbarui data pribadi dan kata sandi dengan verifikasi password saat
            ini.
          </p>
        </div>
      </div>
      <Notice value={notice} />
      <section className="system-settings-grid">
        <form className="panel form-grid" onSubmit={submitProfile}>
          <h2>Data profil</h2>
          <label>
            Nama lengkap
            <input
              required
              value={profile.full_name}
              onChange={(e) =>
                setProfile({ ...profile, full_name: e.target.value })
              }
            />
          </label>
          <label>
            Email
            <input
              required
              type="email"
              value={profile.email}
              onChange={(e) =>
                setProfile({ ...profile, email: e.target.value })
              }
            />
          </label>
          <label>
            Nomor telepon
            <input
              value={profile.phone}
              onChange={(e) =>
                setProfile({ ...profile, phone: e.target.value })
              }
            />
          </label>
          <label>
            Password saat ini
            <input
              required
              type="password"
              value={profile.current_password}
              onChange={(e) =>
                setProfile({ ...profile, current_password: e.target.value })
              }
            />
          </label>
          <button className="button primary full">Simpan profil</button>
        </form>
        <form className="panel form-grid" onSubmit={submitPassword}>
          <h2>Ubah password</h2>
          <label>
            Password saat ini
            <input
              required
              type="password"
              value={password.current_password}
              onChange={(e) =>
                setPassword({ ...password, current_password: e.target.value })
              }
            />
          </label>
          <label>
            Password baru
            <input
              required
              minLength="8"
              type="password"
              value={password.password}
              onChange={(e) =>
                setPassword({ ...password, password: e.target.value })
              }
            />
          </label>
          <label>
            Konfirmasi password
            <input
              required
              type="password"
              value={password.password_confirmation}
              onChange={(e) =>
                setPassword({
                  ...password,
                  password_confirmation: e.target.value,
                })
              }
            />
          </label>
          <small>
            Password minimal 8 karakter dan memuat huruf besar, huruf kecil,
            angka, serta simbol.
          </small>
          <button className="button primary full">Ubah password</button>
        </form>
      </section>
    </main>
  );
}

export function MasterTransportersPage() {
  const [state, setState] = useState({ loading: true, rows: [], error: "" });
  const [form, setForm] = useState(null);
  const [notice, setNotice] = useState(null);
  const load = async () => {
    try {
      const r = await api.masterTransporters();
      setState({ loading: false, rows: r.data || [], error: "" });
    } catch (e) {
      setState({ loading: false, rows: [], error: e.message });
    }
  };
  useEffect(() => {
    load();
  }, []);
  const save = async (event) => {
    event.preventDefault();
    try {
      const r = await api.saveMasterTransporter(form);
      setNotice({ ok: true, text: r.message });
      setForm(null);
      load();
    } catch (e) {
      setNotice({ text: e.message });
    }
  };
  const toggle = async (row) => {
    try {
      await api.toggleMasterTransporter(row.id, !row.is_active);
      load();
    } catch (e) {
      setNotice({ text: e.message });
    }
  };
  return (
    <main className="page">
      <div className="page-heading">
        <div>
          <p className="eyebrow red">Master data</p>
          <h1>Perusahaan transportir</h1>
          <p>
            Kelola identitas perusahaan dan relasinya dengan akun serta
            kendaraan.
          </p>
        </div>
        <button
          className="button primary"
          onClick={() =>
            setForm({ code: "", name: "", email: "", phone: "", address: "" })
          }
        >
          Tambah transportir
        </button>
      </div>
      <Notice value={notice} />
      {form && (
        <form className="panel form-grid" onSubmit={save}>
          {[
            ["code", "Kode"],
            ["name", "Nama perusahaan"],
            ["email", "Email"],
            ["phone", "Telepon"],
            ["address", "Alamat"],
          ].map(([key, label]) => (
            <label key={key}>
              {label}
              <input
                required={["code", "name"].includes(key)}
                type={key === "email" ? "email" : "text"}
                value={form[key] || ""}
                onChange={(e) => setForm({ ...form, [key]: e.target.value })}
              />
            </label>
          ))}
          <div className="row-buttons full">
            <button className="button primary">Simpan</button>
            <button
              type="button"
              className="button secondary"
              onClick={() => setForm(null)}
            >
              Batal
            </button>
          </div>
        </form>
      )}
      {state.loading ? (
        <LoadingState />
      ) : state.error ? (
        <ErrorState message={state.error} retry={load} />
      ) : (
        <section className="panel mobile-card-table">
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Transportir</th>
                  <th>Kontak</th>
                  <th>Akun</th>
                  <th>Kendaraan</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {state.rows.map((row) => (
                  <tr key={row.id}>
                    <td data-label="Kode">{row.code}</td>
                    <td data-label="Transportir">
                      <strong>{row.name}</strong>
                      <small>{row.address}</small>
                    </td>
                    <td data-label="Kontak">
                      {row.email}
                      <small>{row.phone}</small>
                    </td>
                    <td data-label="Akun">{row.users_count}</td>
                    <td data-label="Kendaraan">{row.vehicles_count}</td>
                    <td data-label="Status">
                      {row.is_active ? "Aktif" : "Nonaktif"}
                    </td>
                    <td data-label="Aksi">
                      <div className="row-buttons">
                        <button onClick={() => setForm(row)}>Edit</button>
                        <button onClick={() => toggle(row)}>
                          {row.is_active ? "Nonaktifkan" : "Aktifkan"}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </main>
  );
}

export function RestorePage({ user }) {
  const [state, setState] = useState({ loading: true, rows: [], error: "" });
  const [form, setForm] = useState({ filename: "", request_notes: "" });
  const [notice, setNotice] = useState(null);
  const load = async () => {
    try {
      const r = await api.restoreRequests();
      setState({ loading: false, rows: r.data || [], error: "" });
    } catch (e) {
      setState({ loading: false, rows: [], error: e.message });
    }
  };
  useEffect(() => {
    load();
  }, []);
  const request = async (e) => {
    e.preventDefault();
    try {
      const r = await api.requestRestore(form);
      setNotice({ ok: true, text: r.message });
      setForm({ filename: "", request_notes: "" });
      load();
    } catch (x) {
      setNotice({ text: x.message });
    }
  };
  const decide = async (id, decision) => {
    const notes = window.prompt("Catatan keputusan (wajib):");
    if (!notes) return;
    try {
      await api.decideRestore(id, { decision, approval_notes: notes });
      load();
    } catch (e) {
      setNotice({ text: e.message });
    }
  };
  const execute = async (id) => {
    const current_password = window.prompt(
      "Masukkan password saat ini untuk autentikasi ulang:",
    );
    if (!current_password) return;
    if (!window.confirm("Jalankan restore database yang telah disetujui?"))
      return;
    try {
      const r = await api.executeRestore(id, { current_password });
      setNotice({ ok: true, text: r.message });
      load();
    } catch (e) {
      setNotice({ text: e.message });
    }
  };
  return (
    <main className="page">
      <div className="page-heading">
        <div>
          <p className="eyebrow red">Backup & recovery</p>
          <h1>Persetujuan restore database</h1>
          <p>
            Restore memerlukan permohonan Administrator, persetujuan Manager,
            validasi checksum, dan autentikasi ulang.
          </p>
        </div>
      </div>
      <Notice value={notice} />
      {user.role === "admin" && (
        <form className="panel form-grid" onSubmit={request}>
          <label>
            Nama file backup
            <input
              required
              value={form.filename}
              onChange={(e) => setForm({ ...form, filename: e.target.value })}
              placeholder="prima-backup-....sql"
            />
          </label>
          <label className="full">
            Alasan restore
            <textarea
              required
              value={form.request_notes}
              onChange={(e) =>
                setForm({ ...form, request_notes: e.target.value })
              }
            />
          </label>
          <button className="button primary full">Ajukan restore</button>
        </form>
      )}
      {state.loading ? (
        <LoadingState />
      ) : state.error ? (
        <ErrorState message={state.error} retry={load} />
      ) : (
        <section className="panel mobile-card-table">
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>File</th>
                  <th>Pemohon</th>
                  <th>Status</th>
                  <th>Catatan</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {state.rows.map((row) => (
                  <tr key={row.id}>
                    <td data-label="File">{row.filename}</td>
                    <td data-label="Pemohon">{row.requester_name}</td>
                    <td data-label="Status">{row.status}</td>
                    <td data-label="Catatan">
                      {row.request_notes}
                      <small>{row.approval_notes}</small>
                    </td>
                    <td data-label="Aksi">
                      <div className="row-buttons">
                        {user.role === "manager_hsse" &&
                          row.status === "pending" && (
                            <>
                              <button onClick={() => decide(row.id, "approve")}>
                                Setujui
                              </button>
                              <button
                                className="danger"
                                onClick={() => decide(row.id, "reject")}
                              >
                                Tolak
                              </button>
                            </>
                          )}
                        {user.role === "admin" && row.status === "approved" && (
                          <button onClick={() => execute(row.id)}>
                            Jalankan
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </main>
  );
}

export function RegionalDocumentsPage() {
  const [filters, setFilters] = useState({
    terminal_id: "",
    transporter_id: "",
    search: "",
  });
  const [terminals, setTerminals] = useState([]);
  const [transporters, setTransporters] = useState([]);
  const [state, setState] = useState({ loading: true, rows: [], error: "" });
  const load = async () => {
    try {
      const [docs, terms] = await Promise.all([
        api.regionalDocuments(filters),
        api.terminals(),
      ]);
      setState({ loading: false, rows: docs.data || [], error: "" });
      setTerminals(terms.data || []);
    } catch (e) {
      setState({ loading: false, rows: [], error: e.message });
    }
  };
  useEffect(() => {
    load();
  }, []);
  useEffect(() => {
    api
      .filterTransporters(filters.terminal_id)
      .then((response) => setTransporters(response.data || []))
      .catch(() => setTransporters([]));
  }, [filters.terminal_id]);
  return (
    <main className="page">
      <div className="page-heading">
        <div>
          <p className="eyebrow red">Regional Sulawesi</p>
          <h1>Kelengkapan dokumen kendaraan</h1>
          <p>Pemantauan hanya-baca per terminal dan transportir.</p>
        </div>
      </div>
      <form
        className="checklist-filter"
        onSubmit={(e) => {
          e.preventDefault();
          load();
        }}
      >
        <select
          value={filters.terminal_id}
          onChange={(e) =>
            setFilters({
              ...filters,
              terminal_id: e.target.value,
              transporter_id: "",
            })
          }
        >
          <option value="">Semua terminal</option>
          {terminals.map((t) => (
            <option key={t.id} value={t.id}>
              {t.name}
            </option>
          ))}
        </select>
        <select
          value={filters.transporter_id}
          onChange={(e) =>
            setFilters({ ...filters, transporter_id: e.target.value })
          }
        >
          <option value="">Semua transportir</option>
          {transporters.map((transporter) => (
            <option key={transporter.id} value={transporter.id}>
              {transporter.name}
            </option>
          ))}
        </select>
        <input
          placeholder="Polisi atau transportir"
          value={filters.search}
          onChange={(e) => setFilters({ ...filters, search: e.target.value })}
        />
        <button className="button secondary">Filter</button>
      </form>
      {state.loading ? (
        <LoadingState />
      ) : state.error ? (
        <ErrorState message={state.error} retry={load} />
      ) : (
        <section className="panel mobile-card-table">
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Terminal</th>
                  <th>Kendaraan</th>
                  <th>Transportir</th>
                  <th>Jenis</th>
                  <th>Kelengkapan</th>
                  <th>Status</th>
                  <th>Dokumen disetujui</th>
                </tr>
              </thead>
              <tbody>
                {state.rows.map((r) => (
                  <tr key={r.vehicle_id}>
                    <td data-label="Terminal">{r.terminal_name}</td>
                    <td data-label="Kendaraan">{r.nomor_polisi}</td>
                    <td data-label="Transportir">{r.transporter_name}</td>
                    <td data-label="Jenis">{r.jenis}</td>
                    <td data-label="Kelengkapan">
                      {r.approved_count}/{r.required_count}
                    </td>
                    <td data-label="Status">
                      {r.is_complete ? "Lengkap" : "Belum lengkap"}
                    </td>
                    <td data-label="Dokumen disetujui">
                      {(r.approved_documents || []).length ? (
                        <div className="row-buttons">
                          {r.approved_documents.map((document) => (
                            <button
                              type="button"
                              key={document.id}
                              onClick={() =>
                                api.openRegionalDocument(
                                  document.id,
                                  document.nama_file_asli,
                                )
                              }
                            >
                              {document.jenis_dokumen}
                            </button>
                          ))}
                        </div>
                      ) : (
                        "—"
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </main>
  );
}
