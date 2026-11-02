import { NavLink } from "react-router-dom";
import {
  AlertTriangle,
  BarChart3,
  CheckCircle2,
  ChevronRight,
  ClipboardCheck,
  Database,
  Factory,
  FileText,
  FilePlus2,
  RefreshCw,
  ShieldCheck,
  Truck,
  UserRound,
  Users,
} from "lucide-react";
import { api } from "../../api";
import { ErrorState, LoadingState } from "../../components/AsyncState";
import { ROLE_LABELS } from "../../config/domain";
import useAsync from "../../hooks/useAsync";

function PageHeader({ eyebrow, title, description, actions }) {
  return (
    <div className="page-heading">
      <div>
        <p className="eyebrow red">{eyebrow}</p>
        <h1>{title}</h1>
        <p>{description}</p>
      </div>
      {actions && <div className="heading-actions">{actions}</div>}
    </div>
  );
}

export default function DashboardPage({ user }) {
  const { data, loading, error, reload } = useAsync(api.dashboard, []);
  if (loading) return <LoadingState />;
  if (error) return <ErrorState message={error} retry={reload} />;
  const cards =
    user.role === "pengurus"
      ? [
          ["Kendaraan dikelola", data.my_vehicles, Truck, "navy"],
          ["Dokumen menunggu", data.my_pending_documents, FileText, "amber"],
          [
            "Dokumen disetujui",
            data.my_approved_documents,
            CheckCircle2,
            "green",
          ],
          ["Dokumen ditolak", data.my_rejected_documents, AlertTriangle, "red"],
        ]
      : [
          ["Total checklist", data.checklists, Database, "navy"],
          ["Draft", data.draft, FilePlus2, "slate"],
          ["Menunggu HSSE", data.pending_hsse, ClipboardCheck, "amber"],
          ["Menunggu Manager", data.signed_hsse, ShieldCheck, "blue"],
          ["Disetujui", data.approved, CheckCircle2, "green"],
        ];
  if (user.role === "admin")
    cards.push(
      ["Pengguna aktif", data.active_users, Users, "red"],
      ["Dokumen menunggu", data.pending_documents, FileText, "amber"],
      ["Kendaraan menunggu", data.pending_vehicles, Truck, "amber"],
    );
  if (["admin", "hsse", "manager_hsse"].includes(user.role))
    cards.push(
      [
        "Dokumen menunggu pemeriksaan",
        data.pending_documents,
        FileText,
        "amber",
      ],
      ["Antrean HSSE", data.pending_hsse_queue, ClipboardCheck, "amber"],
      ["Antrean Manager", data.pending_manager_queue, ShieldCheck, "blue"],
      ["E-KIM aktif", data.ekim_active, CheckCircle2, "green"],
      ["E-KIM akan habis", data.ekim_expiring, AlertTriangle, "amber"],
      ["E-KIM kedaluwarsa", data.ekim_expired, AlertTriangle, "red"],
    );
  return (
    <main className="page">
      <PageHeader
        eyebrow="Ringkasan operasional"
        title={`Selamat datang, ${user.full_name || user.email}`}
        description="Pantau progres inspeksi dan status persetujuan mobil tangki."
        actions={
          <button className="button secondary" onClick={reload}>
            <RefreshCw size={15} />
            Perbarui
          </button>
        }
      />
      <section className="metric-grid">
        {cards.map(([label, value, Icon, tone]) => (
          <article className={`metric-card ${tone}`} key={label}>
            <div className="metric-icon">
              <Icon />
            </div>
            <div>
              <strong>{value ?? 0}</strong>
              <span>{label}</span>
            </div>
          </article>
        ))}
      </section>
      {user.role === "manager_hsse" && <ManagerStatistics data={data} />}
      {user.role === "manager_hsse" && <UserProfilePanel user={user} />}
      {user.role === "pengurus" && !!data.ekim_notifications?.length && (
        <section className="pengurus-ekim-notifications">
          <div className="pengurus-notification-title">
            <AlertTriangle />
            <div>
              <strong>Notifikasi status EKIM</strong>
              <span>
                Pantau hasil penerbitan atau penolakan E-KIM kendaraan yang Anda
                kelola.
              </span>
            </div>
          </div>
          {data.ekim_notifications.map((notification) => (
            <article
              className={!notification.is_read ? "is-new" : ""}
              key={notification.id}
            >
              <strong>{notification.nomor_polisi}</strong>
              <span>{notification.pesan}</span>
              <small>
                {notification.status === "issued"
                  ? "E-KIM berhasil diterbitkan"
                  : "E-KIM gagal diterbitkan/ditolak"}
                {" · "}
                {notification.created_at}
              </small>
            </article>
          ))}
        </section>
      )}
      {user.role === "pengurus" && <PengurusStatistics data={data} />}
      {user.role === "pengurus" && (
        <section className="panel mobile-card-table">
          <div className="panel-title">
            <div>
              <p className="eyebrow red">Status pemeriksaan</p>
              <h2>Checklist kendaraan saya</h2>
            </div>
          </div>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Kendaraan</th>
                  <th>Jenis</th>
                  <th>Tanggal</th>
                  <th>Status</th>
                  <th>E-KIM berlaku</th>
                </tr>
              </thead>
              <tbody>
                {(data.my_checklists || []).map((row) => (
                  <tr key={row.id}>
                    <td data-label="Kendaraan">{row.nomor_polisi}</td>
                    <td data-label="Jenis">{row.jenis_kendaraan}</td>
                    <td data-label="Tanggal">
                      {row.tanggal_pemeriksaan || "-"}
                    </td>
                    <td data-label="Status">{row.status_approval}</td>
                    <td data-label="E-KIM berlaku">
                      {row.ekim_valid_until || "-"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
      {user.role === "pengurus" && <UserProfilePanel user={user} />}
      {user.role === "admin" ? (
        <AdminMonitoring data={data} />
      ) : !["manager_hsse", "pengurus"].includes(user.role) ? (
        <section className="content-grid">
          <article className="panel span-2">
            <div className="panel-title">
              <div>
                <p className="eyebrow red">Akses cepat</p>
                <h2>Aktivitas utama</h2>
              </div>
            </div>
            <div className="quick-grid">
              <NavLink to="/checklists" className="quick-card">
                <Database />
                <strong>Database checklist</strong>
                <span>Cari dan pantau seluruh hasil inspeksi.</span>
                <ChevronRight />
              </NavLink>
              {["admin", "hsse"].includes(user.role) && (
                <NavLink to="/checklists/new/SPBU" className="quick-card">
                  <FilePlus2 />
                  <strong>Checklist SPBU</strong>
                  <span>Mulai inspeksi kendaraan SPBU.</span>
                  <ChevronRight />
                </NavLink>
              )}
              {["admin", "hsse"].includes(user.role) && (
                <NavLink to="/checklists/new/INDUSTRI" className="quick-card">
                  <Factory />
                  <strong>Checklist Industri</strong>
                  <span>Mulai inspeksi kendaraan Industri.</span>
                  <ChevronRight />
                </NavLink>
              )}
              {["admin", "hsse", "manager_hsse"].includes(user.role) && (
                <NavLink to="/approvals" className="quick-card">
                  <ShieldCheck />
                  <strong>Antrian persetujuan</strong>
                  <span>Tinjau dokumen yang menunggu tindakan.</span>
                  <ChevronRight />
                </NavLink>
              )}
            </div>
          </article>
          <aside className="panel">
            <p className="eyebrow red">Akun aktif</p>
            <div className="profile-block">
              <div className="avatar large">
                {(user.full_name || user.email)[0].toUpperCase()}
              </div>
              <h3>{user.full_name || user.email}</h3>
              <span className="role-pill">
                {ROLE_LABELS[user.role] || user.role}
              </span>
              <dl>
                <div>
                  <dt>Email</dt>
                  <dd>{user.email || "—"}</dd>
                </div>
              </dl>
            </div>
          </aside>
        </section>
      ) : null}
    </main>
  );
}

function UserProfilePanel({ user }) {
  const displayName = user.full_name || user.email;
  return (
    <section className="panel user-profile-panel">
      <div className="panel-title">
        <div>
          <p className="eyebrow red">Profil pengguna</p>
          <h2>Informasi akun</h2>
        </div>
        <UserRound />
      </div>
      <div className="user-profile-content">
        <div className="avatar large">
          {displayName.slice(0, 1).toUpperCase()}
        </div>
        <div className="user-profile-name">
          <strong>{displayName}</strong>
          <span className="role-pill">
            {ROLE_LABELS[user.role] || user.role}
          </span>
        </div>
        <dl>
          <div>
            <dt>Email</dt>
            <dd>{user.email || "—"}</dd>
          </div>
        </dl>
      </div>
    </section>
  );
}

function ManagerStatistics({ data }) {
  const total = Math.max(Number(data.checklists) || 0, 1);
  const workflow = [
    ["Draft", data.draft, "slate"],
    ["Menunggu HSSE", data.pending_hsse, "amber"],
    ["Menunggu persetujuan Manager", data.signed_hsse, "blue"],
    ["Disetujui", data.approved, "green"],
    ["Ditolak", data.rejected, "red"],
  ];
  return (
    <section className="user-statistics-grid manager-statistics-grid">
      <article className="panel">
        <div className="panel-title">
          <div>
            <p className="eyebrow red">Statistik persetujuan</p>
            <h2>Distribusi status checklist</h2>
          </div>
          <BarChart3 />
        </div>
        <div className="workflow-bars">
          {workflow.map(([label, value, tone]) => (
            <div className="workflow-row" key={label}>
              <div>
                <span>{label}</span>
                <strong>{value || 0}</strong>
              </div>
              <div className="workflow-track">
                <span
                  className={tone}
                  style={{
                    width: `${Math.min(((value || 0) / total) * 100, 100)}%`,
                  }}
                />
              </div>
            </div>
          ))}
        </div>
      </article>
      <article className="panel user-data-summary">
        <div className="panel-title">
          <div>
            <p className="eyebrow red">Statistik data</p>
            <h2>Ringkasan pemeriksaan kendaraan</h2>
          </div>
          <Database />
        </div>
        <div className="watch-list">
          <div>
            <span>Total checklist</span>
            <strong>{data.checklists || 0}</strong>
          </div>
          <div>
            <span>Checklist SPBU</span>
            <strong>{data.spbu || 0}</strong>
          </div>
          <div>
            <span>Checklist Industri</span>
            <strong>{data.industri || 0}</strong>
          </div>
          <div>
            <span>Inspeksi bulan ini</span>
            <strong>{data.bulan_ini || 0}</strong>
          </div>
          <div>
            <span>Persentase disetujui</span>
            <strong>
              {Math.round(((Number(data.approved) || 0) / total) * 100)}%
            </strong>
          </div>
        </div>
      </article>
    </section>
  );
}

function PengurusStatistics({ data }) {
  return (
    <section className="user-statistics-grid pengurus-statistics-grid">
      <article className="panel user-data-summary">
        <div className="panel-title">
          <div>
            <p className="eyebrow red">Data tanggung jawab</p>
            <h2>Ringkasan kendaraan dan dokumen</h2>
          </div>
          <Truck />
        </div>
        <div className="watch-list">
          <div>
            <span>Kendaraan yang dikelola</span>
            <strong>{data.my_vehicles || 0}</strong>
          </div>
          <div>
            <span>Dokumen menunggu review</span>
            <strong>{data.my_pending_documents || 0}</strong>
          </div>
          <div>
            <span>Dokumen disetujui</span>
            <strong>{data.my_approved_documents || 0}</strong>
          </div>
          <div>
            <span>Dokumen ditolak</span>
            <strong>{data.my_rejected_documents || 0}</strong>
          </div>
        </div>
      </article>
    </section>
  );
}

function AdminMonitoring({ data }) {
  const total = Math.max(Number(data.checklists) || 0, 1);
  const workflow = [
    ["Draft", data.draft, "slate"],
    ["Menunggu HSSE", data.pending_hsse, "amber"],
    ["Menunggu Manager", data.signed_hsse, "blue"],
    ["Disetujui", data.approved, "green"],
    ["Ditolak", data.rejected, "red"],
  ];
  const watch = [
    ["Pendaftaran akun menunggu review", data.pending_registrations],
    ["Dokumen pengurus menunggu review", data.pending_documents],
    ["Kendaraan dengan peringatan inspeksi/KIM", data.vehicle_alerts],
    ["Total kendaraan terdaftar", data.vehicles],
    ["Akun pengguna aktif", data.active_users],
    [
      "Akun pengguna nonaktif",
      Math.max((data.users || 0) - (data.active_users || 0), 0),
    ],
  ];
  return (
    <section className="admin-monitor-grid">
      <article className="panel workflow-monitor">
        <div className="panel-title">
          <div>
            <p className="eyebrow red">Pantauan checklist</p>
            <h2>Distribusi status workflow</h2>
          </div>
          <BarChart3 />
        </div>
        <div className="workflow-bars">
          {workflow.map(([label, value, tone]) => (
            <div className="workflow-row" key={label}>
              <div>
                <span>{label}</span>
                <strong>{value || 0}</strong>
              </div>
              <div className="workflow-track">
                <span
                  className={tone}
                  style={{
                    width: `${Math.min(((value || 0) / total) * 100, 100)}%`,
                  }}
                />
              </div>
            </div>
          ))}
        </div>
      </article>
      <article className="panel admin-watch-panel">
        <div className="panel-title">
          <div>
            <p className="eyebrow red">Pantauan admin</p>
            <h2>Data yang perlu diperhatikan</h2>
          </div>
          <ShieldCheck />
        </div>
        <div className="watch-list">
          {watch.map(([label, value]) => (
            <div key={label}>
              <span>{label}</span>
              <strong>{value || 0}</strong>
            </div>
          ))}
        </div>
      </article>
    </section>
  );
}
