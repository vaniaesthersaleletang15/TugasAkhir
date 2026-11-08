import { useEffect, useState } from "react";
import {
  NavLink,
  Navigate,
  Route,
  Routes,
  useLocation,
} from "react-router-dom";
import {
  AlertTriangle,
  BarChart3,
  Bell,
  ClipboardCheck,
  Database,
  Factory,
  FilePlus2,
  FileText,
  LayoutDashboard,
  LogOut,
  Menu,
  ScrollText,
  Settings,
  UserRound,
  Truck,
  UserPlus,
  Users,
  X,
} from "lucide-react";
import { assetUrl } from "../api";
import { ROLE_LABELS } from "../config/domain";
import DashboardPage from "../pages/dashboard/DashboardPage";
import {
  AlertsPage,
  Approvals,
  AuditsPage,
  ChecklistDataPage,
  ChecklistEditor,
  DocumentsPage,
  EkimNotificationsPage,
  ManagerApprovalHistory,
  MyVehiclesPage,
  NotificationsPage,
  RegistrationsPage,
  SettingsPage,
  UsersPage,
  VehiclesPage,
  VerifyPage,
} from "../Modules";
import {
  RegionalAuditPage,
  RegionalDashboard,
  RegionalEkimPage,
  RegionalReportPage,
  RegionalSpvPage,
  RegionalVehicleHistoryPage,
  RegionalVehiclesPage,
} from "../RegionalPages";
import {
  MasterTransportersPage,
  ProfilePage,
  RegionalDocumentsPage,
  RestorePage,
} from "../pages/requirements/RequirementPages";

function menuFor(user) {
  const common = [
    { to: "/", label: "Dashboard", icon: LayoutDashboard },
    { to: "/profile", label: "Profil saya", icon: UserRound },
  ];
  if (user.role !== "pengurus")
    common.push({ to: "/checklists", label: "Data checklist", icon: Database });
  if (["admin", "hsse"].includes(user.role))
    common.push(
      { to: "/checklists/new/SPBU", label: "Formulir SPBU", icon: FilePlus2 },
      {
        to: "/checklists/new/INDUSTRI",
        label: "Formulir Industri",
        icon: Factory,
      },
    );
  if (user.role === "admin")
    common.push({ to: "/vehicles", label: "Kelola Kendaraan", icon: Truck });
  if (user.role === "pengurus")
    common.push({ to: "/my-vehicles", label: "Kendaraan saya", icon: Truck });
  if (user.role === "admin")
    common.push({
      to: "/documents",
      label: "Review Dokumen Pengurus",
      icon: FileText,
    });
  if (user.role === "pengurus")
    common.push({
      to: "/documents",
      label: "Upload Dokumen Kendaraan",
      icon: FileText,
    });
  if (["admin", "hsse", "manager_hsse"].includes(user.role))
    common.push({
      to: "/approvals",
      label: "Persetujuan Checklist",
      icon: ClipboardCheck,
    });
  if (user.role === "manager_hsse")
    common.push({
      to: "/approval-history",
      label: "Riwayat Persetujuan",
      icon: ScrollText,
    });
  if (user.role === "regional_manager")
    common.push(
      {
        to: "/regional/vehicles",
        label: "Kendaraan Regional",
        icon: Truck,
      },
      {
        to: "/regional/documents",
        label: "Dokumen Regional",
        icon: FileText,
      },
      {
        to: "/regional/spvs",
        label: "Kelola SPV HSSE",
        icon: Users,
      },
      {
        to: "/regional/ekim",
        label: "Monitoring E-KIM",
        icon: AlertTriangle,
      },
      {
        to: "/regional/reports",
        label: "Laporan Regional",
        icon: BarChart3,
      },
      {
        to: "/regional/audits",
        label: "Audit Regional",
        icon: ScrollText,
      },
    );
  if (user.role === "admin")
    common.push(
      { to: "/transporters", label: "Master Transportir", icon: Truck },
      { to: "/registrations", label: "Review Pendaftaran", icon: UserPlus },
      { to: "/users", label: "Kelola Pengguna", icon: Users },
      { to: "/alerts", label: "Notifikasi Inspeksi", icon: Bell },
      { to: "/audits", label: "Audit Log Sistem", icon: ScrollText },
      { to: "/notifications", label: "Notifikasi Email KIM", icon: Bell },
      { to: "/settings", label: "Pengaturan Sistem", icon: Settings },
      { to: "/restore", label: "Restore Database", icon: Database },
    );
  if (user.role === "manager_hsse")
    common.push({
      to: "/restore",
      label: "Persetujuan Restore",
      icon: Database,
    });
  return common;
}

export default function AppShell({ user, onLogout }) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const location = useLocation();

  useEffect(() => setMobileOpen(false), [location.pathname]);
  useEffect(() => {
    if (!mobileOpen) return undefined;

    const closeOnEscape = (event) => {
      if (event.key === "Escape") setMobileOpen(false);
    };
    document.body.classList.add("mobile-menu-open");
    window.addEventListener("keydown", closeOnEscape);

    return () => {
      document.body.classList.remove("mobile-menu-open");
      window.removeEventListener("keydown", closeOnEscape);
    };
  }, [mobileOpen]);
  return (
    <div className="app-shell">
      <aside
        id="primary-navigation"
        className={`sidebar ${mobileOpen ? "open" : ""}`}
      >
        <div className="sidebar-brand">
          <img
            className="sidebar-prima-logo"
            src={assetUrl("foto/logo-prima.png")}
            alt="Logo PRIMA"
          />
          <span>PRIMA · HSSE</span>
          <button
            className="sidebar-close"
            type="button"
            aria-label="Tutup menu navigasi"
            onClick={() => setMobileOpen(false)}
          >
            <X />
          </button>
        </div>
        <nav>
          <p className="nav-label">Menu utama</p>
          {menuFor(user).map(({ to, href, label, icon: Icon, badge }) =>
            href ? (
              <a key={label} className="nav-link" href={assetUrl(href)}>
                <Icon />
                <span className="nav-link-label">{label}</span>
                {!!badge && (
                  <span className="nav-badge">
                    {badge > 99 ? "99+" : badge}
                  </span>
                )}
              </a>
            ) : (
              <NavLink
                key={label}
                className={({ isActive }) =>
                  `nav-link ${isActive ? "active" : ""}`
                }
                to={to}
                end={to === "/" || to === "/checklists"}
              >
                <Icon />
                <span className="nav-link-label">{label}</span>
                {!!badge && (
                  <span className="nav-badge">
                    {badge > 99 ? "99+" : badge}
                  </span>
                )}
              </NavLink>
            ),
          )}
        </nav>
        <div className="sidebar-user">
          <div className="avatar">
            {(user.full_name || user.email).slice(0, 1).toUpperCase()}
          </div>
          <div>
            <strong>{user.full_name || user.email}</strong>
            <span>{ROLE_LABELS[user.role] || user.role}</span>
          </div>
        </div>
      </aside>
      {mobileOpen && (
        <button
          className="scrim"
          aria-label="Tutup menu"
          onClick={() => setMobileOpen(false)}
        />
      )}
      <div className="main-column">
        <header className="topbar">
          <button
            className="icon-button menu-button"
            type="button"
            aria-label="Buka menu navigasi"
            aria-controls="primary-navigation"
            aria-expanded={mobileOpen}
            onClick={() => setMobileOpen(true)}
          >
            <Menu />
          </button>
          <div className="topbar-brand">
            <img src={assetUrl("foto/logo-prima.png")} alt="" />
            <div className="topbar-brand-copy">
              <strong>PRIMA</strong>
              <span>Pertamina Checklist Mobil Tangki</span>
            </div>
          </div>
          <button className="button ghost" onClick={onLogout}>
            <LogOut size={16} />
            Keluar
          </button>
        </header>
        <Routes>
          <Route path="/profile" element={<ProfilePage user={user} />} />
          <Route
            path="/regional/vehicles"
            element={
              user.role === "regional_manager" ? (
                <RegionalVehiclesPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/regional/vehicles/:id/history"
            element={
              user.role === "regional_manager" ? (
                <RegionalVehicleHistoryPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/regional/documents"
            element={
              user.role === "regional_manager" ? (
                <RegionalDocumentsPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/"
            element={
              user.role === "regional_manager" ? (
                <RegionalDashboard user={user} />
              ) : (
                <DashboardPage user={user} />
              )
            }
          />
          <Route
            path="/checklists"
            element={
              user.role !== "pengurus" ? (
                <ChecklistDataPage user={user} />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/checklists/new/:jenis"
            element={
              ["admin", "hsse"].includes(user.role) ? (
                <ChecklistEditor user={user} />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/regional/ekim"
            element={
              user.role === "regional_manager" ? (
                <RegionalEkimPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/regional/reports"
            element={
              user.role === "regional_manager" ? (
                <RegionalReportPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/regional/audits"
            element={
              user.role === "regional_manager" ? (
                <RegionalAuditPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/regional/spvs"
            element={
              user.role === "regional_manager" ? (
                <RegionalSpvPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/checklists/:id"
            element={
              user.role !== "pengurus" ? (
                <ChecklistEditor user={user} />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/vehicles"
            element={
              user.role === "admin" ? (
                <VehiclesPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/transporters"
            element={
              user.role === "admin" ? (
                <MasterTransportersPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/my-vehicles"
            element={
              user.role === "pengurus" ? (
                <MyVehiclesPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/my-ekim-notifications"
            element={
              user.role === "pengurus" ? (
                <EkimNotificationsPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/approvals"
            element={
              ["admin", "hsse", "manager_hsse"].includes(user.role) ? (
                <Approvals user={user} />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/approval-history"
            element={
              user.role === "manager_hsse" ? (
                <ManagerApprovalHistory />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/documents"
            element={
              ["admin", "pengurus"].includes(user.role) ? (
                <DocumentsPage user={user} />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/registrations"
            element={
              user.role === "admin" ? (
                <RegistrationsPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/users"
            element={
              user.role === "admin" ? (
                <UsersPage currentUser={user} />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/alerts"
            element={
              user.role === "admin" ? (
                <AlertsPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/audits"
            element={
              user.role === "admin" ? (
                <AuditsPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/notifications"
            element={
              user.role === "admin" ? (
                <NotificationsPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/settings"
            element={
              user.role === "admin" ? (
                <SettingsPage />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route
            path="/restore"
            element={
              ["admin", "manager_hsse"].includes(user.role) ? (
                <RestorePage user={user} />
              ) : (
                <Navigate to="/" replace />
              )
            }
          />
          <Route path="/verify/:value" element={<VerifyPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </div>
    </div>
  );
}
