import { useCallback, useEffect, useMemo, useState } from "react";
import { Link, useParams, useSearchParams } from "react-router-dom";
import {
  AlertTriangle,
  BarChart3,
  Building2,
  CheckCircle2,
  ClipboardList,
  Database,
  Download,
  Eye,
  EyeOff,
  KeyRound,
  Plus,
  RefreshCw,
  Search,
  ShieldCheck,
  Truck,
} from "lucide-react";
import { api } from "./api";
import { ErrorState, LoadingState } from "./components/AsyncState";

const formatDate = (value) =>
  value
    ? new Intl.DateTimeFormat("id-ID", { dateStyle: "medium" }).format(
        new Date(`${String(value).slice(0, 10)}T00:00:00`),
      )
    : "—";

function RegionalHeader({ title, description, children }) {
  return (
    <div className="page-heading regional-heading">
      <div>
        <p className="eyebrow red">
          PT Pertamina Patra Niaga · Regional Sulawesi
        </p>
        <h1>{title}</h1>
        <p>{description}</p>
      </div>
      {children && <div className="heading-actions">{children}</div>}
    </div>
  );
}

function TerminalFilter({ terminals, value, onChange }) {
  return (
    <label className="regional-terminal-filter">
      Integrated Terminal
      <select value={value} onChange={(event) => onChange(event.target.value)}>
        <option value="">Seluruh Regional Sulawesi</option>
        {terminals.map((terminal) => (
          <option value={terminal.id} key={terminal.id}>
            {terminal.name}
          </option>
        ))}
      </select>
    </label>
  );
}

function useRegionalData(loader, parameters) {
  const [state, setState] = useState({
    loading: true,
    data: null,
    pagination: null,
    error: "",
  });
  const serialized = JSON.stringify(parameters);
  const load = useCallback(async () => {
    setState((current) => ({ ...current, loading: true, error: "" }));
    try {
      const response = await loader(parameters);
      setState({
        loading: false,
        data: response.data,
        pagination: response.pagination || null,
        error: "",
      });
    } catch (error) {
      setState({
        loading: false,
        data: null,
        pagination: null,
        error: error.message,
      });
    }
  }, [loader, serialized]);
  useEffect(() => {
    load();
  }, [load]);
  return { ...state, reload: load };
}

function useTerminals() {
  const [terminals, setTerminals] = useState([]);
  useEffect(() => {
    api
      .terminals()
      .then((response) => setTerminals(response.data || []))
      .catch(() => setTerminals([]));
  }, []);
  return terminals;
}

export function RegionalDashboard({ user }) {
  const terminals = useTerminals();
  const [filters, setFilters] = useState({
    terminal_id: "",
    date_from: "",
    date_to: "",
  });
  const [applied, setApplied] = useState(filters);
  const params = useMemo(
    () =>
      Object.fromEntries(Object.entries(applied).filter(([, value]) => value)),
    [applied],
  );
  const state = useRegionalData(api.regionalDashboard, params);
  if (state.loading && !state.data)
    return <LoadingState message="Memuat data regional…" />;
  if (state.error && !state.data)
    return <ErrorState message={state.error} retry={state.reload} />;
  const summary = state.data?.summary || {};
  const comparison = state.data?.comparison || [];
  const maximum = Math.max(
    1,
    ...comparison.map((row) => Number(row.checklists) || 0),
  );
  const cards = [
    ["Integrated Terminal", summary.terminals, Building2, "navy", null],
    [
      "Total kendaraan",
      summary.vehicles,
      Truck,
      "blue",
      `/regional/vehicles?${new URLSearchParams({ terminal_id: applied.terminal_id })}`,
    ],
    [
      "Total checklist",
      summary.checklists,
      Database,
      "slate",
      `/checklists?${new URLSearchParams({ terminal_id: applied.terminal_id, dateFrom: applied.date_from, dateTo: applied.date_to })}`,
    ],
    [
      "Menunggu proses",
      summary.pending,
      ClipboardList,
      "amber",
      `/checklists?${new URLSearchParams({ terminal_id: applied.terminal_id, dateFrom: applied.date_from, dateTo: applied.date_to, status: "pending" })}`,
    ],
    [
      "Checklist disetujui",
      summary.approved,
      CheckCircle2,
      "green",
      `/checklists?${new URLSearchParams({ terminal_id: applied.terminal_id, dateFrom: applied.date_from, dateTo: applied.date_to, status: "approved" })}`,
    ],
    [
      "Checklist ditolak",
      summary.rejected,
      AlertTriangle,
      "red",
      `/checklists?${new URLSearchParams({ terminal_id: applied.terminal_id, dateFrom: applied.date_from, dateTo: applied.date_to, status: "rejected" })}`,
    ],
    [
      "E-KIM kedaluwarsa",
      summary.ekim_expired,
      AlertTriangle,
      "red",
      `/regional/ekim?${new URLSearchParams({ terminal_id: applied.terminal_id, status: "expired" })}`,
    ],
  ];
  return (
    <main className="page regional-page">
      <RegionalHeader
        title={`Dashboard Regional${user?.full_name ? ` — ${user.full_name}` : ""}`}
        description="Pemantauan lintas Integrated Terminal dengan akses hanya-baca."
      >
        <TerminalFilter
          terminals={terminals}
          value={filters.terminal_id}
          onChange={(value) => setFilters({ ...filters, terminal_id: value })}
        />
        <label>
          Dari tanggal
          <input
            type="date"
            value={filters.date_from}
            onChange={(event) =>
              setFilters({ ...filters, date_from: event.target.value })
            }
          />
        </label>
        <label>
          Sampai tanggal
          <input
            type="date"
            value={filters.date_to}
            onChange={(event) =>
              setFilters({ ...filters, date_to: event.target.value })
            }
          />
        </label>
        <button className="button primary" onClick={() => setApplied(filters)}>
          Terapkan
        </button>
        <button className="button secondary" onClick={state.reload}>
          <RefreshCw size={16} />
          Perbarui
        </button>
      </RegionalHeader>
      <div className="readonly-banner">
        <Eye size={18} />
        <span>
          <strong>Mode hanya-baca</strong> Anda dapat memantau dan mengunduh
          laporan tanpa mengubah atau menyetujui data operasional.
        </span>
      </div>
      <section className="metric-grid regional-metrics">
        {cards.map(([label, value, Icon, tone, to]) => {
          const content = (
            <>
              <div className="metric-icon">
                <Icon />
              </div>
              <div>
                <strong>{value ?? 0}</strong>
                <span>{label}</span>
              </div>
            </>
          );
          return to ? (
            <Link className={`metric-card ${tone}`} key={label} to={to}>
              {content}
            </Link>
          ) : (
            <article className={`metric-card ${tone}`} key={label}>
              {content}
            </article>
          );
        })}
      </section>
      <section className="regional-grid">
        <article className="panel regional-comparison">
          <div className="panel-title">
            <div>
              <p className="eyebrow red">Perbandingan terminal</p>
              <h2>Volume checklist</h2>
            </div>
            <BarChart3 />
          </div>
          {comparison.length ? (
            comparison.map((row) => (
              <div className="regional-bar-row" key={row.id}>
                <div>
                  <strong>{row.name}</strong>
                  <span>{row.checklists} checklist</span>
                </div>
                <span className="regional-bar-track">
                  <i
                    style={{
                      width: `${(Number(row.checklists) / maximum) * 100}%`,
                    }}
                  />
                </span>
              </div>
            ))
          ) : (
            <p className="muted">Belum ada Integrated Terminal aktif.</p>
          )}
        </article>
        <article className="panel ekim-overview">
          <div className="panel-title">
            <div>
              <p className="eyebrow red">Status masa berlaku</p>
              <h2>E-KIM Regional</h2>
            </div>
            <ShieldCheck />
          </div>
          <div className="ekim-status-cards">
            <Link
              to={`/regional/ekim?${new URLSearchParams({ terminal_id: applied.terminal_id, status: "active" })}`}
            >
              <strong>{summary.ekim_active || 0}</strong>
              <span>Aktif</span>
            </Link>
            <Link
              to={`/regional/ekim?${new URLSearchParams({ terminal_id: applied.terminal_id, status: "expiring" })}`}
              className="warning"
            >
              <strong>{summary.ekim_expiring || 0}</strong>
              <span>Akan berakhir</span>
            </Link>
            <Link
              to={`/regional/ekim?${new URLSearchParams({ terminal_id: applied.terminal_id, status: "expired" })}`}
              className="danger"
            >
              <strong>{summary.ekim_expired || 0}</strong>
              <span>Kedaluwarsa</span>
            </Link>
          </div>
        </article>
      </section>
      <section className="panel">
        <div className="panel-title">
          <div>
            <p className="eyebrow red">Rekap regional</p>
            <h2>Ringkasan per Integrated Terminal</h2>
          </div>
        </div>
        <div className="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Terminal</th>
                <th>Kendaraan</th>
                <th>Checklist</th>
                <th>Pending</th>
                <th>Disetujui</th>
                <th>Ditolak</th>
                <th>E-KIM aktif</th>
                <th>Akan berakhir</th>
                <th>E-KIM kedaluwarsa</th>
                <th>Drill-down</th>
              </tr>
            </thead>
            <tbody>
              {comparison.map((row) => (
                <tr key={row.id}>
                  <td data-label="Terminal">
                    <strong>{row.name}</strong>
                    <small>
                      {row.code}
                      {row.city ? ` · ${row.city}` : ""}
                    </small>
                  </td>
                  <td data-label="Kendaraan">{row.vehicles}</td>
                  <td data-label="Checklist">{row.checklists}</td>
                  <td data-label="Pending">{row.pending}</td>
                  <td data-label="Disetujui">{row.approved}</td>
                  <td data-label="Ditolak">{row.rejected}</td>
                  <td data-label="E-KIM aktif">{row.ekim_active}</td>
                  <td data-label="Akan berakhir">{row.ekim_expiring}</td>
                  <td data-label="E-KIM kedaluwarsa">{row.expired}</td>
                  <td data-label="Drill-down">
                    <div className="row-buttons">
                      <Link to={`/regional/vehicles?terminal_id=${row.id}`}>
                        Kendaraan
                      </Link>
                      <Link
                        to={`/checklists?${new URLSearchParams({ terminal_id: row.id, dateFrom: applied.date_from, dateTo: applied.date_to })}`}
                      >
                        Checklist
                      </Link>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </main>
  );
}

export function RegionalVehiclesPage() {
  const terminals = useTerminals();
  const [searchParams, setSearchParams] = useSearchParams();
  const initialFilters = {
    terminal_id: searchParams.get("terminal_id") || "",
    transporter_id: searchParams.get("transporter_id") || "",
    search: searchParams.get("search") || "",
    jenis: searchParams.get("jenis") || "",
    status: searchParams.get("status") || "",
    page: Number(searchParams.get("page")) || 1,
    limit: 25,
  };
  const [transporters, setTransporters] = useState([]);
  const [filters, setFilters] = useState(initialFilters);
  const [applied, setApplied] = useState(initialFilters);
  const state = useRegionalData(api.regionalVehicles, applied);
  useEffect(() => {
    api
      .filterTransporters(filters.terminal_id)
      .then((response) => setTransporters(response.data || []))
      .catch(() => setTransporters([]));
  }, [filters.terminal_id]);
  const synchronize = (next) => {
    setApplied(next);
    setSearchParams(
      Object.fromEntries(
        Object.entries(next).filter(([, value]) => value && value !== 25),
      ),
    );
  };
  const pagination = state.pagination || {};
  const page = pagination.page || applied.page || 1;
  const pages = pagination.totalPages || 1;
  return (
    <main className="page regional-page">
      <RegionalHeader
        title="Kendaraan Regional"
        description="Drill-down kendaraan seluruh Integrated Terminal dalam wilayah Sulawesi dengan akses hanya-baca."
      />
      <div className="readonly-banner">
        <Eye size={18} />
        <span>
          <strong>Mode hanya-baca.</strong> Data kendaraan tidak dapat ditambah,
          diubah, disetujui, ditolak, atau dinonaktifkan oleh Regional Manager.
        </span>
      </div>
      <form
        className="regional-filter panel"
        onSubmit={(event) => {
          event.preventDefault();
          synchronize({ ...filters, page: 1 });
        }}
      >
        <TerminalFilter
          terminals={terminals}
          value={filters.terminal_id}
          onChange={(value) =>
            setFilters({ ...filters, terminal_id: value, transporter_id: "" })
          }
        />
        <label>
          Transportir
          <select
            value={filters.transporter_id}
            onChange={(event) =>
              setFilters({ ...filters, transporter_id: event.target.value })
            }
          >
            <option value="">Semua transportir</option>
            {transporters.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          Jenis
          <select
            value={filters.jenis}
            onChange={(event) =>
              setFilters({ ...filters, jenis: event.target.value })
            }
          >
            <option value="">Semua jenis</option>
            <option value="SPBU">SPBU</option>
            <option value="INDUSTRI">Industri</option>
          </select>
        </label>
        <label>
          Status
          <select
            value={filters.status}
            onChange={(event) =>
              setFilters({ ...filters, status: event.target.value })
            }
          >
            <option value="">Semua status</option>
            <option value="AKTIF">Aktif</option>
            <option value="NONAKTIF">Nonaktif</option>
          </select>
        </label>
        <label className="regional-search">
          Pencarian
          <input
            value={filters.search}
            onChange={(event) =>
              setFilters({ ...filters, search: event.target.value })
            }
            placeholder="Nomor polisi, merek, atau transportir"
          />
        </label>
        <button className="button primary">
          <Search size={16} />
          Terapkan
        </button>
      </form>
      {state.loading ? (
        <LoadingState />
      ) : state.error ? (
        <ErrorState message={state.error} retry={state.reload} />
      ) : (
        <section className="panel">
          <p className="muted">
            Menampilkan {state.data?.length || 0} dari {pagination.total || 0}{" "}
            kendaraan.
          </p>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Terminal</th>
                  <th>Nomor polisi</th>
                  <th>Transportir</th>
                  <th>Jenis</th>
                  <th>Merk/Tahun</th>
                  <th>Produk/Kapasitas</th>
                  <th>Status</th>
                  <th>E-KIM</th>
                  <th>Riwayat</th>
                </tr>
              </thead>
              <tbody>
                {(state.data || []).length ? (
                  state.data.map((row) => (
                    <tr key={row.id}>
                      <td data-label="Terminal">
                        <strong>{row.terminal_name}</strong>
                        <small>{row.terminal_code}</small>
                      </td>
                      <td data-label="Nomor polisi">
                        <strong>{row.nomor_polisi}</strong>
                        <small>{row.nomor_urut || "—"}</small>
                      </td>
                      <td data-label="Transportir">
                        {row.nama_transport || "—"}
                      </td>
                      <td data-label="Jenis">{row.jenis}</td>
                      <td data-label="Merk/Tahun">
                        {row.merk_mobil || "—"} {row.tahun_kendaraan || ""}
                      </td>
                      <td data-label="Produk/Kapasitas">
                        {row.produk || "—"} / {row.kapasitas || "—"}
                      </td>
                      <td data-label="Status">{row.status}</td>
                      <td data-label="E-KIM">
                        {formatDate(row.ekim_valid_until)}
                      </td>
                      <td data-label="Riwayat">
                        <Link to={`/regional/vehicles/${row.id}/history`}>
                          Lihat riwayat
                        </Link>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="9">
                      <div className="empty">
                        Tidak ada kendaraan sesuai filter.
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="checklist-pagination">
            <button
              disabled={page <= 1}
              onClick={() => synchronize({ ...applied, page: 1 })}
            >
              First
            </button>
            <button
              disabled={page <= 1}
              onClick={() => synchronize({ ...applied, page: page - 1 })}
            >
              Prev
            </button>
            <span>
              Halaman <strong>{page}</strong> dari <strong>{pages}</strong>
            </span>
            <button
              disabled={page >= pages}
              onClick={() => synchronize({ ...applied, page: page + 1 })}
            >
              Next
            </button>
            <button
              disabled={page >= pages}
              onClick={() => synchronize({ ...applied, page: pages })}
            >
              Last
            </button>
          </div>
        </section>
      )}
    </main>
  );
}

export function RegionalVehicleHistoryPage() {
  const { id } = useParams();
  const [state, setState] = useState({ loading: true, rows: [], error: "" });
  const load = useCallback(async () => {
    setState((current) => ({ ...current, loading: true, error: "" }));
    try {
      const response = await api.vehicleHistory(id);
      setState({ loading: false, rows: response.data || [], error: "" });
    } catch (error) {
      setState({ loading: false, rows: [], error: error.message });
    }
  }, [id]);
  useEffect(() => {
    load();
  }, [load]);
  return (
    <main className="page regional-page">
      <RegionalHeader
        title="Riwayat Kendaraan"
        description="Riwayat perubahan kendaraan berdasarkan aktor dan waktu dengan akses hanya-baca."
      >
        <Link className="button secondary" to="/regional/vehicles">
          Kembali
        </Link>
      </RegionalHeader>
      {state.loading ? (
        <LoadingState />
      ) : state.error ? (
        <ErrorState message={state.error} retry={load} />
      ) : (
        <section className="panel">
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Aksi</th>
                  <th>Pelaksana</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                {state.rows.length ? (
                  state.rows.map((row) => (
                    <tr key={row.id}>
                      <td data-label="Waktu">{row.created_at}</td>
                      <td data-label="Aksi">{row.action}</td>
                      <td data-label="Pelaksana">
                        {row.actor_name || "Sistem"}
                      </td>
                      <td data-label="Catatan">{row.notes || "—"}</td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="4">
                      <div className="empty">
                        Belum ada riwayat perubahan kendaraan.
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </main>
  );
}

export function RegionalEkimPage() {
  const terminals = useTerminals();
  const [searchParams, setSearchParams] = useSearchParams();
  const [transporters, setTransporters] = useState([]);
  const [filters, setFilters] = useState({
    terminal_id: searchParams.get("terminal_id") || "",
    status: searchParams.get("status") || "",
    transporter_id: searchParams.get("transporter_id") || "",
    search: searchParams.get("search") || "",
    page: Number(searchParams.get("page")) || 1,
    limit: 25,
  });
  const [applied, setApplied] = useState(filters);
  const state = useRegionalData(api.regionalEkim, applied);
  useEffect(() => {
    api
      .filterTransporters(filters.terminal_id)
      .then((response) => setTransporters(response.data || []))
      .catch(() => setTransporters([]));
  }, [filters.terminal_id]);
  const apply = (event) => {
    event.preventDefault();
    const next = { ...filters, page: 1 };
    setApplied(next);
    setSearchParams(
      Object.fromEntries(
        Object.entries(next).filter(([, value]) => value && value !== 25),
      ),
    );
  };
  const pagination = state.pagination || {};
  const page = pagination.page || applied.page || 1;
  const pages = pagination.totalPages || 1;
  const go = (nextPage) => {
    const next = { ...applied, page: nextPage };
    setApplied(next);
    setSearchParams(
      Object.fromEntries(
        Object.entries(next).filter(([, value]) => value && value !== 25),
      ),
    );
  };
  return (
    <main className="page regional-page">
      <RegionalHeader
        title="Monitoring Masa Berlaku E-KIM"
        description="Identifikasi kendaraan yang memerlukan tindak lanjut perpanjangan di seluruh Regional Sulawesi."
      />
      <form className="regional-filter panel" onSubmit={apply}>
        <TerminalFilter
          terminals={terminals}
          value={filters.terminal_id}
          onChange={(value) => setFilters({ ...filters, terminal_id: value })}
        />
        <label>
          Transportir
          <select
            value={filters.transporter_id}
            onChange={(e) =>
              setFilters({ ...filters, transporter_id: e.target.value })
            }
          >
            <option value="">Semua transportir</option>
            {transporters.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          Status E-KIM
          <select
            value={filters.status}
            onChange={(e) => setFilters({ ...filters, status: e.target.value })}
          >
            <option value="">Semua status</option>
            <option value="active">Aktif</option>
            <option value="expiring">Akan berakhir (≤30 hari)</option>
            <option value="expired">Kedaluwarsa</option>
            <option value="unavailable">Belum tersedia</option>
          </select>
        </label>
        <label className="regional-search">
          Kendaraan/transportir
          <input
            value={filters.search}
            onChange={(e) => setFilters({ ...filters, search: e.target.value })}
            placeholder="Cari nomor polisi atau transportir"
          />
        </label>
        <button className="button primary">
          <Search size={16} />
          Terapkan
        </button>
      </form>
      {state.loading ? (
        <LoadingState />
      ) : state.error ? (
        <ErrorState message={state.error} retry={state.reload} />
      ) : (
        <section className="panel">
          <p className="muted">
            Menampilkan {(state.data || []).length} dari {pagination.total || 0}{" "}
            kendaraan.
          </p>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Integrated Terminal</th>
                  <th>Nomor Polisi</th>
                  <th>Transportir</th>
                  <th>Jenis</th>
                  <th>Berlaku sampai</th>
                  <th>Status</th>
                  <th>Sisa hari</th>
                </tr>
              </thead>
              <tbody>
                {(state.data || []).length ? (
                  state.data.map((row) => (
                    <tr key={row.id}>
                      <td data-label="Integrated Terminal">
                        {row.terminal_name}
                      </td>
                      <td data-label="Nomor Polisi">
                        <strong>{row.nomor_polisi}</strong>
                      </td>
                      <td data-label="Transportir">
                        {row.nama_transport || "—"}
                      </td>
                      <td data-label="Jenis">{row.jenis}</td>
                      <td data-label="Berlaku sampai">
                        {formatDate(row.ekim_valid_until)}
                      </td>
                      <td data-label="Status">
                        <span className={`regional-status ${row.ekim_status}`}>
                          {
                            {
                              active: "Aktif",
                              expiring: "Akan berakhir",
                              expired: "Kedaluwarsa",
                              unavailable: "Belum tersedia",
                            }[row.ekim_status]
                          }
                        </span>
                      </td>
                      <td data-label="Sisa hari">
                        {row.days_remaining ?? "—"}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="7">
                      <div className="empty">Tidak ada data sesuai filter.</div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="checklist-pagination">
            <button disabled={page <= 1} onClick={() => go(1)}>
              First
            </button>
            <button disabled={page <= 1} onClick={() => go(page - 1)}>
              Prev
            </button>
            <span>
              Halaman <strong>{page}</strong> dari <strong>{pages}</strong>
            </span>
            <button disabled={page >= pages} onClick={() => go(page + 1)}>
              Next
            </button>
            <button disabled={page >= pages} onClick={() => go(pages)}>
              Last
            </button>
          </div>
        </section>
      )}
    </main>
  );
}

export function RegionalReportPage() {
  const terminals = useTerminals();
  const today = new Date();
  const initialFrom = `${today.getFullYear()}-01-01`;
  const initialTo = today.toISOString().slice(0, 10);
  const [filters, setFilters] = useState({
    terminal_id: "",
    date_from: initialFrom,
    date_to: initialTo,
  });
  const [applied, setApplied] = useState(filters);
  const state = useRegionalData(api.regionalReport, applied);
  const maximum = Math.max(
    1,
    ...(state.data?.trend || []).map((row) => Number(row.total) || 0),
  );
  const exportReport = () =>
    api.exportChecklists({
      terminal_id: applied.terminal_id,
      dateFrom: applied.date_from,
      dateTo: applied.date_to,
    });
  return (
    <main className="page regional-page">
      <RegionalHeader
        title="Laporan Regional"
        description="Rekap administrasi mobil tangki untuk evaluasi Supply & Distribution Regional Sulawesi."
      >
        <button className="button primary" onClick={exportReport}>
          <Download size={16} />
          Unduh XLSX
        </button>
      </RegionalHeader>
      <form
        className="regional-filter panel"
        onSubmit={(e) => {
          e.preventDefault();
          setApplied(filters);
        }}
      >
        <TerminalFilter
          terminals={terminals}
          value={filters.terminal_id}
          onChange={(value) => setFilters({ ...filters, terminal_id: value })}
        />
        <label>
          Dari tanggal
          <input
            type="date"
            value={filters.date_from}
            onChange={(e) =>
              setFilters({ ...filters, date_from: e.target.value })
            }
          />
        </label>
        <label>
          Sampai tanggal
          <input
            type="date"
            value={filters.date_to}
            onChange={(e) =>
              setFilters({ ...filters, date_to: e.target.value })
            }
          />
        </label>
        <button className="button primary">Tampilkan</button>
      </form>
      {state.loading ? (
        <LoadingState />
      ) : state.error ? (
        <ErrorState message={state.error} retry={state.reload} />
      ) : (
        <>
          <section className="panel regional-trend">
            <div className="panel-title">
              <div>
                <p className="eyebrow red">Tren bulanan</p>
                <h2>Jumlah checklist</h2>
              </div>
              <BarChart3 />
            </div>
            <div className="trend-bars">
              {(state.data?.trend || []).map((row) => (
                <div className="trend-column" key={row.month}>
                  <span>
                    <i
                      style={{
                        height: `${Math.max(3, (Number(row.total) / maximum) * 100)}%`,
                      }}
                    />
                  </span>
                  <strong>{row.total}</strong>
                  <small>{row.label}</small>
                </div>
              ))}
            </div>
          </section>
          <section className="panel">
            <div className="panel-title">
              <div>
                <p className="eyebrow red">Rekapitulasi</p>
                <h2>Per Integrated Terminal</h2>
              </div>
            </div>
            <div className="table-scroll">
              <table>
                <thead>
                  <tr>
                    <th>Terminal</th>
                    <th>Kendaraan</th>
                    <th>Checklist</th>
                    <th>Disetujui</th>
                    <th>E-KIM bermasalah</th>
                  </tr>
                </thead>
                <tbody>
                  {(state.data?.terminals || []).map((row) => (
                    <tr key={row.id}>
                      <td data-label="Terminal">
                        <strong>{row.name}</strong>
                        <small>{row.code}</small>
                      </td>
                      <td data-label="Kendaraan">{row.vehicles}</td>
                      <td data-label="Checklist">{row.checklists}</td>
                      <td data-label="Disetujui">{row.approved}</td>
                      <td data-label="E-KIM bermasalah">{row.ekim_expired}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}
    </main>
  );
}

export function RegionalAuditPage() {
  const terminals = useTerminals();
  const [searchParams, setSearchParams] = useSearchParams();
  const [filters, setFilters] = useState({
    terminal_id: searchParams.get("terminal_id") || "",
    search: searchParams.get("search") || "",
    date_from: searchParams.get("date_from") || "",
    date_to: searchParams.get("date_to") || "",
    page: Number(searchParams.get("page")) || 1,
    limit: 50,
  });
  const [applied, setApplied] = useState(filters);
  const state = useRegionalData(api.regionalAudits, applied);
  const synchronize = (next) => {
    setApplied(next);
    setSearchParams(
      Object.fromEntries(
        Object.entries(next).filter(([, value]) => value && value !== 50),
      ),
    );
  };
  const pagination = state.pagination || {};
  const page = pagination.page || applied.page || 1;
  const pages = pagination.totalPages || 1;
  return (
    <main className="page regional-page">
      <RegionalHeader
        title="Audit Aktivitas Regional"
        description="Riwayat aktivitas penting dari Integrated Terminal dalam wilayah Sulawesi—hanya-baca."
      />
      <form
        className="regional-filter panel"
        onSubmit={(e) => {
          e.preventDefault();
          synchronize({ ...filters, page: 1 });
        }}
      >
        <TerminalFilter
          terminals={terminals}
          value={filters.terminal_id}
          onChange={(value) => setFilters({ ...filters, terminal_id: value })}
        />
        <label>
          Pencarian
          <input
            value={filters.search}
            onChange={(e) => setFilters({ ...filters, search: e.target.value })}
            placeholder="Pengguna, aksi, atau detail"
          />
        </label>
        <label>
          Dari
          <input
            type="date"
            value={filters.date_from}
            onChange={(e) =>
              setFilters({ ...filters, date_from: e.target.value })
            }
          />
        </label>
        <label>
          Sampai
          <input
            type="date"
            value={filters.date_to}
            onChange={(e) =>
              setFilters({ ...filters, date_to: e.target.value })
            }
          />
        </label>
        <button className="button primary">
          <Search size={16} />
          Cari
        </button>
      </form>
      {state.loading ? (
        <LoadingState />
      ) : state.error ? (
        <ErrorState message={state.error} retry={state.reload} />
      ) : (
        <section className="panel">
          <p className="muted">
            Menampilkan {(state.data || []).length} dari {pagination.total || 0}{" "}
            aktivitas.
          </p>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Terminal</th>
                  <th>Pengguna</th>
                  <th>Aksi</th>
                  <th>Kendaraan</th>
                  <th>Detail</th>
                </tr>
              </thead>
              <tbody>
                {(state.data || []).length ? (
                  state.data.map((row) => (
                    <tr key={row.id}>
                      <td data-label="Waktu">{row.created_at}</td>
                      <td data-label="Terminal">{row.terminal_name || "—"}</td>
                      <td data-label="Pengguna">{row.user_name || "Sistem"}</td>
                      <td data-label="Aksi">
                        <span className="operation">{row.action}</span>
                      </td>
                      <td data-label="Kendaraan">{row.nomor_polisi || "—"}</td>
                      <td data-label="Detail">{row.description}</td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="6">
                      <div className="empty">
                        Belum ada audit sesuai filter.
                      </div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="checklist-pagination">
            <button
              disabled={page <= 1}
              onClick={() => synchronize({ ...applied, page: 1 })}
            >
              First
            </button>
            <button
              disabled={page <= 1}
              onClick={() => synchronize({ ...applied, page: page - 1 })}
            >
              Prev
            </button>
            <span>
              Halaman <strong>{page}</strong> dari <strong>{pages}</strong>
            </span>
            <button
              disabled={page >= pages}
              onClick={() => synchronize({ ...applied, page: page + 1 })}
            >
              Next
            </button>
            <button
              disabled={page >= pages}
              onClick={() => synchronize({ ...applied, page: pages })}
            >
              Last
            </button>
          </div>
        </section>
      )}
    </main>
  );
}

export function RegionalSpvPage() {
  const terminals = useTerminals();
  const state = useRegionalData(api.regionalSpvs, {});
  const blank = {
    full_name: "",
    email: "",
    phone: "",
    terminal_id: "",
    password: "",
  };
  const [form, setForm] = useState(null);
  const [resetTarget, setResetTarget] = useState(null);
  const [newPassword, setNewPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [notice, setNotice] = useState(null);
  const create = async (event) => {
    event.preventDefault();
    try {
      const response = await api.createRegionalSpv(form);
      setNotice({ ok: true, text: response.message });
      setForm(null);
      setShowPassword(false);
      state.reload();
    } catch (error) {
      setNotice({ text: error.message });
    }
  };
  const toggle = async (spv) => {
    try {
      const response = await api.toggleRegionalSpv(
        spv.id,
        spv.status === "active" ? "inactive" : "active",
      );
      setNotice({ ok: true, text: response.message });
      state.reload();
    } catch (error) {
      setNotice({ text: error.message });
    }
  };
  const resetPassword = async (event) => {
    event.preventDefault();
    try {
      const response = await api.resetRegionalSpvPassword(
        resetTarget.id,
        newPassword,
      );
      setNotice({ ok: true, text: response.message });
      setResetTarget(null);
      setNewPassword("");
    } catch (error) {
      setNotice({ text: error.message });
    }
  };
  return (
    <main className="page regional-page">
      <RegionalHeader
        title="Kelola Akun SPV HSSE"
        description="Regional Manager membuat satu akun SPV HSSE untuk setiap Integrated Terminal."
      >
        <button
          className="button primary"
          onClick={() => {
            setForm(blank);
            setResetTarget(null);
          }}
        >
          <Plus size={16} />
          Buat akun SPV
        </button>
      </RegionalHeader>
      <div className="readonly-banner">
        <ShieldCheck size={18} />
        <span>
          <strong>Pengecualian administratif</strong> Regional Manager tetap
          hanya-baca terhadap data operasional; halaman ini hanya mengelola akun
          SPV HSSE.
        </span>
      </div>
      {notice && (
        <div className={`alert ${notice.ok ? "success" : ""}`}>
          {notice.ok ? <CheckCircle2 /> : <AlertTriangle />}
          {notice.text}
        </div>
      )}
      {form && (
        <section className="panel">
          <form className="form-grid" onSubmit={create}>
            <div className="full">
              <p className="eyebrow red">Akun baru</p>
              <h2>Daftarkan SPV HSSE</h2>
            </div>
            <label>
              Nama lengkap
              <input
                value={form.full_name}
                onChange={(e) =>
                  setForm({ ...form, full_name: e.target.value })
                }
                required
              />
            </label>
            <label>
              Email
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                required
              />
            </label>
            <label>
              Nomor telepon
              <input
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
              />
            </label>
            <label>
              Integrated Terminal
              <select
                value={form.terminal_id}
                onChange={(e) =>
                  setForm({ ...form, terminal_id: e.target.value })
                }
                required
              >
                <option value="">Pilih terminal</option>
                {terminals.map((terminal) => (
                  <option value={terminal.id} key={terminal.id}>
                    {terminal.name}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Password awal
              <span className="password-input-wrap">
                <input
                  type={showPassword ? "text" : "password"}
                  value={form.password}
                  onChange={(e) =>
                    setForm({ ...form, password: e.target.value })
                  }
                  minLength="8"
                  required
                />
                <button
                  type="button"
                  className="password-toggle"
                  onClick={() => setShowPassword((value) => !value)}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </span>
              <small>
                Minimal 8 karakter dengan huruf besar, huruf kecil, angka, dan
                simbol.
              </small>
            </label>
            <div className="row-buttons full">
              <button className="button primary">Buat akun SPV</button>
              <button type="button" onClick={() => setForm(null)}>
                Batal
              </button>
            </div>
          </form>
        </section>
      )}
      {resetTarget && (
        <section className="panel">
          <form className="form-grid" onSubmit={resetPassword}>
            <div className="full">
              <p className="eyebrow red">Reset password SPV</p>
              <h2>{resetTarget.full_name}</h2>
              <p className="muted">
                {resetTarget.terminal_name} · {resetTarget.email}
              </p>
            </div>
            <label>
              Password baru
              <input
                type="password"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                minLength="8"
                required
              />
            </label>
            <div className="row-buttons full">
              <button className="button primary">Simpan password</button>
              <button type="button" onClick={() => setResetTarget(null)}>
                Batal
              </button>
            </div>
          </form>
        </section>
      )}
      {state.loading ? (
        <LoadingState />
      ) : state.error ? (
        <ErrorState message={state.error} retry={state.reload} />
      ) : (
        <section className="panel">
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Integrated Terminal</th>
                  <th>SPV HSSE</th>
                  <th>Kontak</th>
                  <th>Status</th>
                  <th>Login terakhir</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                {(state.data || []).map((spv) => (
                  <tr key={spv.id}>
                    <td data-label="Integrated Terminal">
                      <strong>{spv.terminal_name}</strong>
                      <small>{spv.terminal_code}</small>
                    </td>
                    <td data-label="SPV HSSE">
                      <strong>{spv.full_name}</strong>
                    </td>
                    <td data-label="Kontak">
                      {spv.email}
                      <small>{spv.phone || "—"}</small>
                    </td>
                    <td data-label="Status">
                      <span
                        className={`regional-status ${spv.status === "active" ? "active" : "expired"}`}
                      >
                        {spv.status === "active" ? "Aktif" : "Nonaktif"}
                      </span>
                    </td>
                    <td data-label="Login terakhir">
                      {spv.last_login || "Belum pernah"}
                    </td>
                    <td data-label="Aksi">
                      <div className="row-buttons">
                        <button
                          onClick={() => {
                            setResetTarget(spv);
                            setForm(null);
                          }}
                        >
                          <KeyRound size={15} />
                          Reset password
                        </button>
                        <button
                          className={spv.status === "active" ? "danger" : ""}
                          onClick={() => toggle(spv)}
                        >
                          {spv.status === "active" ? "Nonaktifkan" : "Aktifkan"}
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
