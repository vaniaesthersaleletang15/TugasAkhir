import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const source = (path) =>
  readFileSync(new URL(`../../${path}`, import.meta.url), "utf8");

test("layout utama memiliki breakpoint dan drawer navigasi mobile", () => {
  const css = source("src/styles.css");
  const shell = source("src/layouts/AppShell.jsx");

  assert.match(css, /@media \(max-width: 820px\)/);
  assert.match(css, /\.sidebar\.open/);
  assert.match(css, /body\.mobile-menu-open/);
  assert.match(shell, /aria-controls="primary-navigation"/);
  assert.match(shell, /mobileOpen \? "open" : ""/);
});

test("form login beralih menjadi satu kolom pada tablet dan ponsel", () => {
  const css = source("src/styles.css");

  assert.match(css, /\.login-page \{[\s\S]*?display: block;/);
  assert.match(css, /\.brand-panel \{[\s\S]*?display: none;/);
  assert.match(css, /min-height: 100dvh/);
});

test("tabel operasional memiliki label untuk tampilan kartu mobile", () => {
  const css = source("src/styles.css");
  const dashboard = source("src/pages/dashboard/DashboardPage.jsx");
  const requirements = source("src/pages/requirements/RequirementPages.jsx");
  const regional = source("src/RegionalPages.jsx");

  assert.match(css, /\.mobile-card-table td\[data-label\]/);
  assert.match(css, /content: attr\(data-label\)/);
  assert.match(dashboard, /className="panel mobile-card-table"/);
  assert.match(requirements, /data-label="Kelengkapan"/);
  assert.match(regional, /data-label="Integrated Terminal"/);
});

test("layout mencegah overflow horizontal pada viewport minimum", () => {
  const css = source("src/styles.css");

  assert.match(css, /min-width: 320px/);
  assert.match(css, /overflow-x: hidden/);
  assert.match(css, /\.main-column \{[\s\S]*?min-width: 0;/);
  assert.match(css, /@media \(max-width: 430px\)/);
});

test("item checklist memakai struktur kartu terpisah dan keadaan kosong di ponsel", () => {
  const css = source("src/styles.css");
  const modules = source("src/Modules.jsx");

  assert.match(css, /\.checklist-mobile-cards \{[\s\S]*?display: none;/);
  assert.match(
    css,
    /\.professional-checklist \.checklist-desktop-table \{[\s\S]*?display: none;/,
  );
  assert.match(css, /\.professional-checklist \.checklist-mobile-cards/);
  assert.match(modules, /className="checklist-mobile-item"/);
  assert.match(modules, /className="checklist-mobile-empty"/);
  assert.match(modules, /Item pemeriksaan tidak berhasil dimuat/);
});
