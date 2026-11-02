import test from "node:test";
import assert from "node:assert/strict";

import {
  CHECKLIST_STATUS_LABELS,
  MANAGED_OPERATIONAL_ROLE_LABELS,
  MANAGED_OPERATIONAL_ROLES,
  ROLE_LABELS,
  USER_MANAGEMENT_ROLE_LABELS,
} from "../../src/config/domain.js";

test("konfigurasi role utama memuat seluruh role aplikasi", () => {
  assert.deepEqual(Object.keys(ROLE_LABELS).sort(), [
    "admin",
    "hsse",
    "manager_hsse",
    "pengurus",
    "regional_manager",
  ]);
});

test("role operasional diturunkan dari sumber label yang sama", () => {
  assert.deepEqual(
    MANAGED_OPERATIONAL_ROLES,
    Object.keys(MANAGED_OPERATIONAL_ROLE_LABELS),
  );
  assert.deepEqual(MANAGED_OPERATIONAL_ROLES, [
    "hsse",
    "manager_hsse",
    "pengurus",
  ]);
});

test("label transportir dan SPV HSSE sesuai istilah antarmuka", () => {
  assert.equal(ROLE_LABELS.hsse, "Tim HSSE");
  assert.equal(MANAGED_OPERATIONAL_ROLE_LABELS.pengurus, "Transportir");
  assert.equal(
    USER_MANAGEMENT_ROLE_LABELS.admin,
    "SPV HSSE / Administrator Sistem",
  );
});

test("seluruh status workflow checklist memiliki label", () => {
  assert.deepEqual(Object.keys(CHECKLIST_STATUS_LABELS), [
    "draft",
    "pending_hsse",
    "signed_hsse",
    "approved",
    "rejected",
  ]);
});

test("objek konfigurasi tidak dapat dimutasi", () => {
  assert.equal(Object.isFrozen(ROLE_LABELS), true);
  assert.equal(Object.isFrozen(USER_MANAGEMENT_ROLE_LABELS), true);
  assert.equal(Object.isFrozen(MANAGED_OPERATIONAL_ROLE_LABELS), true);
  assert.equal(Object.isFrozen(MANAGED_OPERATIONAL_ROLES), true);
  assert.equal(Object.isFrozen(CHECKLIST_STATUS_LABELS), true);
});
