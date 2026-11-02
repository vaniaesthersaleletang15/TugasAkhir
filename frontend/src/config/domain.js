export const ROLE_LABELS = Object.freeze({
  admin: "SPV HSSE / Administrator Sistem",
  hsse: "Tim HSSE",
  manager_hsse: "Integrated Terminal Manager",
  regional_manager: "Regional Manager Supply & Distribution",
  pengurus: "Pengurus Kendaraan",
});

export const USER_MANAGEMENT_ROLE_LABELS = Object.freeze({
  pengurus: "Pengurus",
  hsse: "HSSE",
  manager_hsse: "Integrated Terminal Manager",
  regional_manager: "Regional Manager Supply & Distribution",
  admin: "SPV HSSE / Administrator Sistem",
});

export const MANAGED_OPERATIONAL_ROLE_LABELS = Object.freeze({
  hsse: "Tim HSSE",
  manager_hsse: "Integrated Terminal Manager",
  pengurus: "Transportir",
});

export const MANAGED_OPERATIONAL_ROLES = Object.freeze(
  Object.keys(MANAGED_OPERATIONAL_ROLE_LABELS),
);

export const CHECKLIST_STATUS_LABELS = Object.freeze({
  draft: "Draft",
  pending_hsse: "Menunggu HSSE",
  signed_hsse: "Menunggu Manager",
  approved: "Disetujui",
  rejected: "Ditolak",
});
