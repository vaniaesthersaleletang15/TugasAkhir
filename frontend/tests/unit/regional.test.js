import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const source = (path) =>
  readFileSync(new URL(`../../${path}`, import.meta.url), "utf8");

test("regional manager memiliki drill-down kendaraan yang hanya-baca", () => {
  const shell = source("src/layouts/AppShell.jsx");
  const regional = source("src/RegionalPages.jsx");

  assert.match(shell, /Kendaraan Regional/);
  assert.match(shell, /path="\/regional\/vehicles"/);
  assert.match(regional, /export function RegionalVehiclesPage/);
  assert.match(regional, /Mode hanya-baca/);
  assert.doesNotMatch(regional, /api\.saveVehicle/);
});

test("dashboard regional menyediakan filter terminal dan periode", () => {
  const regional = source("src/RegionalPages.jsx");

  assert.match(regional, /date_from/);
  assert.match(regional, /date_to/);
  assert.match(regional, /Checklist ditolak/);
  assert.match(regional, /E-KIM aktif/);
  assert.match(regional, /Akan berakhir/);
});

test("monitoring E-KIM membaca query filter dan menyediakan pagination", () => {
  const regional = source("src/RegionalPages.jsx");

  assert.match(regional, /searchParams\.get\("status"\)/);
  assert.match(regional, /pagination\.totalPages/);
  assert.match(regional, />\s*Next\s*<\/button>/);
  assert.match(regional, />\s*Prev\s*<\/button>/);
});

test("dokumen regional hanya membuka dokumen melalui endpoint terotorisasi", () => {
  const requirements = source("src/pages/requirements/RequirementPages.jsx");
  const api = source("src/api.js");

  assert.match(requirements, /approved_documents/);
  assert.match(requirements, /api\.openRegionalDocument/);
  assert.match(api, /\/regional\/documents\/\$\{id\}\/download/);
});
