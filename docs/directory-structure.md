# CustomCore — Application Directory Structure

**Document type:** Stage 1 foundation (Commit 1.1)  
**Purpose:** Record the repository folder layout so later commits place files in the correct locations.  
**Rule:** Do not add empty feature pages only to inflate page counts. Routes from `docs/sitemap.md` are implemented when their stage arrives.

---

## 1. Top-level layout

```text
customcore/
├── admin/                 # Administrator PHP pages (Stage 9+)
├── api/                   # Lightweight JSON/data endpoints (Stage 5+)
├── assets/
│   ├── css/               # Base, admin, and print stylesheets
│   ├── themes/            # Switchable site themes (Stage 10): rgb-gaming.css (10.1), minimal-pro.css (10.2), cyber-grid.css (10.3)
│   ├── js/                # External JavaScript
│   ├── images/            # Copyright-safe images: products/, hero/, categories/, ui/, media/, og/, map/ (Stage 8.1)
│   └── media/             # Video/audio learning items + captions/ (Stage 8.2)
├── config/                # App and database configuration (Commit 1.2+)
├── database/              # schema.sql, seeds, admin setup (Stage 2)
├── docs/                  # Planning and project documentation
├── help/                  # Static Help wiki (Stage 11 complete): hub + 6 guides; context links audited in 11.7
├── includes/              # Shared PHP layout and helpers (Commit 1.4+)
├── uploads/
│   ├── consultation/      # Safe consultation attachments
│   └── products/          # Admin product image uploads
├── index.php              # Application entry / homepage (expanded Stage 3)
├── README.md
├── LICENSE
└── .gitignore
```

Root feature pages (`about.php`, `catalogue.php`, `builder.php`, …) are added in their feature stages, not as empty stubs in Commit 1.1.

---

## 2. Directory responsibilities

| Path | Responsibility | First major commits |
| ---- | -------------- | ------------------- |
| `admin/` | Protected admin UI (products, options, compatibility, orders, users, consultations, reviews, reports, themes) | 9.x, 10.4 |
| `api/` | Builder price, compatibility, search, chart data | 5.x, 8.x, 9.x |
| `assets/css/` | External CSS (`main.css`, `admin.css`, later `print.css`) | 1.5, 9.1 |
| `assets/themes/` | RGB Gaming (`rgb-gaming.css`, 10.1), Minimal Professional (`minimal-pro.css`, 10.2), Cyber Grid (`cyber-grid.css`, 10.3) | 10.x |
| `assets/js/` | External JS (`main.js`, builder, cart, checkout, reviews, contact, `store-map.js`, `catalogue-chart.js`, `admin-reports.js`, `help-hub.js`, validation, charts) | 1.6, 8.4, 8.5, 11.1 |
| `assets/images/` | ≥ 20 documented images | 8.1 |
| `assets/media/` | ≥ 3 video/audio items + captions | 8.2 |
| `config/` | `database.example.php`, `app.php`; real `database.php` gitignored | 1.2–1.3 |
| `database/` | Schema, seeds, create-admin script | 2.x |
| `docs/` | Business case, rubric, sitemap, wireframes, ER design, database import, media credits, image prompts, theme testing, Help context-link audit, the Stage 12 guides (front-end architecture, administrator, content-update, installation, deployment/troubleshooting), and the Stage 13 monitoring troubleshooting guide | 0.x–13.x, 8.7, 10.6, 11.7, 12.1–12.6, 13.5 |
| `help/` | Static Help hub (`index.html`, 11.1) + topic articles (`pc-builder.html` from 5.9; `accounts.html` 11.2; `catalogue.html` 11.3; `orders.html` 11.4; `support.html` 11.5; `training.html` 11.6) | 5.9, 11.x |
| `includes/` | Header, footer, nav, helpers, auth, CSRF, flash, cart, orders, wishlist, reviews, consultations, contact, media, catalogue-stats, theme, admin, admin-nav, admin-products, admin-product-form, admin-options, admin-compatibility, admin-orders, admin-users, admin-consultations, admin-reviews, admin-reports, admin-themes, compatibility, performance, monitoring, seo | 1.3–1.8, 4.x, 5.x, 6.x, 7.x, 8.x, 9.x, 10.x, 13.x, 14.x |
| `uploads/consultation/` | Validated consultation files | 7.4 |
| `uploads/products/` | Product images uploaded by admin | 9.2 |

---

## 3. Git tracking notes

| Path | Tracking rule |
| ---- | ------------- |
| `uploads/consultation/*` | Ignored except `.gitkeep` |
| `uploads/products/*` | Ignored except `.gitkeep` |
| `config/database.php` | Ignored (secrets) |
| `config/database.example.php` | Tracked (Commit 1.2) |
| `.gitkeep` files | Keep empty directories in Git until real files replace them |

---

## 4. Alignment checks

- [x] Folders match the architecture described in the project roadmap and `docs/sitemap.md`
- [x] Upload directories exist and are ready for ignored user content
- [x] Asset, config, database, includes, admin, api, and help locations exist
- [x] No fake catalogue/admin feature pages were added solely for page count

---

## 5. Status

**Commit 14.9 complete — CSRF protection on all state-changing requests.**
Audited every POST form and handler against `includes/csrf.php`: all forms render
`customcore_csrf_field()` and all handlers verify + reject missing/invalid tokens; read-only
`api/` endpoints are non-mutating. Fixed the one gap — **logout** was a GET link
(logout-CSRF); it is now a token-verified POST form in `includes/navigation.php` and
`includes/account-nav.php`, and `logout.php` clears the session only on a valid POST. See
[`docs/security-audit.md`](security-audit.md) §5.

**Commit 14.8 complete — security audit (prepared statements & output escaping).**
New [`docs/security-audit.md`](security-audit.md) records an evidence-based review: every DB
call is a static literal or a bound prepared statement (dynamic `WHERE`/`IN`/`LIMIT`/`SET`
and the one interpolated table name use placeholders, clamped int casts, or whitelists — no
user input in SQL), and all output is escaped (`customcore_e()` server-side, textNode
`escapeHtml()` before any client `innerHTML`), with open-redirect guards confirmed. Result:
PASS, 0 vulnerabilities, no source changes.

**Commit 14.7 complete — JavaScript and PHP documentation.**
All `assets/js/*` files now have a file header plus JSDoc (purpose + `@param`/`@returns`) on
every named function. A token-based audit confirmed all 282 PHP functions carry docblocks
and every PHP file has a file-responsibility header (the three gaps found —
`catalogue_filter_url()` and two flash helpers — were documented). `node --check` (all JS)
and `php -l` (changed PHP) pass. Completes the Stage 14 comment pass (#5, #6).

**Commit 14.6 complete — HTML and CSS comments.**
CSS was already fully commented (section headers/dividers in `main.css`, `admin.css`, and
the three themes). This commit adds structured, purpose-first `<!-- section -->` comments to
the HTML template portion of all 41 view files (25 public/customer + 16 admin), marking
major landmarks (hero, filters, results grids, tables, forms, KPI cards, charts, flash/empty
states). Additions-only (0 deletions per `git diff --numstat`) and all `php -l` clean. JS +
PHP documentation follows in 14.7.

**Commit 14.5 complete — advanced CSS interactions.**
A dedicated section in `assets/css/main.css` adds `:focus-within` card elevation
(keyboard parity with `:hover`), an animated mobile-menu reveal (`@keyframes
cc-nav-reveal`), a focus-driven form-label highlight, and a hover-capability-gated
deeper card lift (`@media (hover: hover) and (pointer: fine)`). All effects use the
shared `--cc-*` tokens so the three themes inherit them and reduced-motion cancels the
timing.

**Commit 14.4 complete — accessibility and keyboard navigation.**
Core flows are fully keyboard-operable. Existing skip link, `:focus-visible` ring,
labelled controls, `aria-invalid`/`aria-describedby` errors, and `aria-live` flash
messages are complemented by new error-focus management in `assets/js/main.js`
(`initErrorFocus`): a failed submit moves focus to the first invalid field (or a
POST-form error alert) and scrolls it into view. `assets/css/main.css` adds a
`prefers-contrast: more` focus-ring reinforcement (outline-only). Stage 14 complete.

**Commit 14.3 complete — semantic HTML structure.**
Site-wide landmarks (`header`/`nav`/`main`/`footer` + `section`/`article` with
`aria-labelledby`) confirmed, and heading hierarchy audited across all pages. Fixed the
lone defect in `admin/reports.php` (KPI cards skipped `h1 → h3`) by wrapping them in a
labelled `<section>` with an `<h2>` so stat labels nest as `h3` (one `h1`, no skips).

**Commit 14.2 complete — sitemap and robots configuration.**
Root `robots.txt` Disallows admin/APIs/uploads/internals/private customer pages;
`sitemap.xml` lists only public storefront + Help URLs; live `sitemap.php` builds
absolute locs (plus active product detail URLs when MySQL is up) from the shared
catalogue in `includes/seo.php`. Private routes are excluded from both files.

**Commit 14.1 complete — page-specific SEO metadata.**
`includes/header.php` now emits a full SEO head: a scalable brand `favicon.svg` +
`site.webmanifest` + `theme-color`, a subfolder-safe self-referencing
`<link rel="canonical">` and `og:url` (`customcore_canonical_url()`), and a
`<meta name="robots">` that noindexes admin + private per-user pages
(`customcore_is_noindex_page()`). New root files: `favicon.svg`, `site.webmanifest`.

**Commit 13.5 complete — monitoring troubleshooting guide.**
`docs/monitoring-troubleshooting.md` documents the `admin/monitoring.php` dashboard: how to
read it and a symptom-driven troubleshooting reference for all seven health checks (message
wording, cause, fix), plus the live-statistics panel, production-safe messaging notes, and a
verified CLI snippet. This completes Stage 13.

`docs/security-audit.md` records the Commit 14.8 audit of prepared statements and output
escaping: methodology, per-pattern SQL findings (WHERE/IN/LIMIT/SET/whitelisted identifiers),
server- and client-side escaping evidence, related open-redirect guards, and a repeatable `rg`
recipe. Result: PASS with zero vulnerabilities.

**Commit 13.4 complete — production-safe monitoring error messages.**
`customcore_monitoring_safe_message()` in `includes/monitoring.php` strips stack traces,
absolute filesystem paths, and credential fragments from every dynamic error string the
monitoring page can display (database check, `customcore_monitoring_stats()`, and the page
fallback), even in debug mode. Render-verified with MySQL offline: no path, stack trace,
or password appears in the output.

**Commit 13.3 complete — monitoring statistics.**
`customcore_monitoring_stats()` adds live product / user / order / consultation /
image / stock counts to `admin/monitoring.php`. DB totals reuse
`customcore_admin_dashboard_stats()` (verified to match); image and media counts are
read from disk and remain available when MySQL is offline. Stats load separately from
the health-check table so a DB failure never blanks the status rows.

**Commit 13.2 complete — administrator monitoring dashboard.**
`admin/monitoring.php` renders the health-check report from
`customcore_monitoring_run()` as an online/warning/offline status table (overall banner
with per-status counts + timestamp, per-service rows, and a status legend) behind
`customcore_require_admin()`. It loads even when a check fails because the engine never
throws and the admin guard is session-based. Status badges reuse the shared
`.admin-badge` styles via `customcore_monitoring_status_badge_class()`; scoped
`.monitor-*` rules were added to `assets/css/admin.css`. The admin nav/dashboard
Monitoring links light up automatically. Live statistics arrive in Commit 13.3.

**Commit 13.1 complete — application health checks.**
`includes/monitoring.php` adds the Stage 13 monitoring engine: seven self-contained
checks (PHP runtime, database, sessions, core files, upload storage, site theme,
Learning Centre media) each returning a controlled `online`/`warning`/`offline`
status with production-safe messages, aggregated by `customcore_monitoring_run()`.
`includes/media.php` now exposes `customcore_media_catalogue()` for the media check
(no behaviour change to `customcore_media_items()`). The admin dashboard that renders
the report lands in Commit 13.2 (`admin/monitoring.php`).

**Stage 12 complete (Commits 12.1–12.6) — project documentation set finished.**
Five new guides were added under `docs/`: front-end architecture
(`frontend-documentation.md`, 12.1), administrator guide (`administrator-guide.md`,
12.2), non-programmer content-update guide (`content-update-guide.md`, 12.3),
installation guide (`installation-guide.md`, 12.4), and deployment/troubleshooting
(`deployment-troubleshooting.md`, 12.5). The README, this structure doc, and the
rubric checklist were updated (12.6). No application code changed in Stage 12.

**Commit 11.7 complete — context-sensitive Help links audited.**
Every customer feature page links to its matching Help article (with section
anchors where useful). Hub links remain on main nav, footer, and general entry
pages. Audit map and verification notes are in `docs/help-context-links.md`.
Stage 11 (Help wiki + training + context links) is finished.

**Commit 11.6 complete — End-user training walkthrough.**
`help/training.html` is a numbered walkthrough (account → shop or build → order →
review) that links into the live site and to each detailed guide, matching the
shared Help shell. Its anchors back the hub deep-links and `about.php` links to
it directly. This completes the six-guide Help wiki plus the hub.

**Commit 11.5 complete — Consultation & support Help page.**
`help/support.html` documents requesting a consultation, adding attachments,
tracking requests, responses and the four consultation statuses, writing product
reviews, and the contact form, matching the shared Help shell and the live
pages' copy and rules. Its anchors back the hub deep-links and the context-help
links on `consultation.php`, `consultation-history.php`, `reviews.php`, and
`contact.php`. Remaining articles land in 11.6–11.8.

**Commit 11.4 complete — Cart & orders Help page.**
`help/orders.html` documents the cart, quantity updates/removal, checkout,
simulated payment methods, order confirmation numbers, order history and its
status filter, order details, and the five order statuses, matching the shared
Help shell and the live pages' copy and rules. Its anchors back the hub
deep-links and the context-help links on `cart.php`, `checkout.php`,
`order-confirmation.php`, `order-history.php`, and `order-details.php`.
Remaining articles land in 11.5–11.8.

**Commit 11.3 complete — Catalogue & products Help page.**
`help/catalogue.html` documents browsing/tiers, searching, filtering, sorting,
the product page, configuration options and pricing, comparing 2–4 systems,
wishlists, and reviews, matching the shared Help shell and the live pages' copy
and rules. Its anchors back the hub deep-links and the context-help links on
`catalogue.php`, `product.php`, `search.php`, `compare.php`, and `wishlist.php`
(retargeted to `#wishlist`). Remaining articles land in 11.4–11.8.

**Commit 11.2 complete — Accounts & profile Help page.**
`help/accounts.html` documents registration, login/logout, the profile
dashboard, editing details, changing a password, session timeouts, and disabled
accounts, matching the shared Help shell and the live pages' copy and rules.
Its anchors back the hub deep-links and the context-help links on `register.php`,
`login.php`, `profile.php`, and `edit-profile.php`. A `.help-note` callout style
was added to `main.css`. Remaining articles land in 11.3–11.8.

**Commit 11.1 complete — Help centre homepage.**
`help/index.html` is a searchable Help hub with six guide cards (Accounts,
Catalogue, PC Builder, Orders, Support, Training), jump TOC, deep-links, and
related live-site links. Filter JS in `assets/js/help-hub.js` (progressive
enhancement). Hub styles in `main.css`. PC Builder article already live;
remaining articles land in 11.2–11.8.

**Commit 10.6 complete — cross-theme verification (Stage 10 finished).**  
Walked 26 key public / account / admin pages under all three themes
(78 checks). Every page returned HTTP 200 with the correct theme CSS linked
after `main.css` (and after `admin.css` on admin pages), structural chrome
present, and no PHP error leaks. Themes remain distinct (bg / accent / font /
radius). No theme bugs found. Record: `docs/theme-testing.md`.

**Commit 10.5 complete — safe theme fallback hardening.**  
`includes/theme.php` resolves the active stylesheet through a five-step chain
(active setting → is_active_default → config slug → canonical `rgb-gaming` →
`assets/themes/*.css` scan). Every candidate is path-validated
(`^assets/themes/<slug>.css`, no traversal) and must exist on disk; DB access is
try/catch-wrapped and `main.css` always loads, so the site is never unstyled and
`css_file` can never smuggle a foreign path. Covered by automated + HTTP tests
(invalid id, missing/corrupt paths, empty tables, corrupt config → canonical).

**Commit 10.4 complete — administrator theme switching.**  
`admin/themes.php` lists seeded themes and activates one via CSRF + PRG into
`site_settings.active_theme_id`. Logic in `includes/admin-themes.php` validates
the theme id and on-disk CSS path before writing. Shared header +
`includes/theme.php` apply the choice sitewide. Verified over HTTP
(activate → public CSS updates; CSRF / invalid id / guest blocked).

**Commit 10.3 complete — Cyber Grid theme (all three templates shipped).**  
`assets/themes/cyber-grid.css` is a technical HUD/grid look: visible blueprint
grid backdrop, Orbitron + Chakra Petch + Share Tech Mono type, zero-radius
square edges, corner-cut buttons, uppercase mono nav, and a mint-green primary
with an animated mint→magenta rail. Re-declares the shared `--cc-*` tokens
(public + admin re-skin) and refines header/hero/cards/flash/footer; honours
`prefers-reduced-motion`. Uses the 10.1 resolver + header wiring unchanged. The
three required distinct themes (RGB Gaming / Minimal Professional / Cyber Grid)
now all exist.

**Commit 10.2 complete — Minimal Professional theme.**  
`assets/themes/minimal-pro.css` is a light, editorial counterpoint to RGB
Gaming: Fraunces serif display + Manrope sans, hairline borders, crisp corners,
flat surfaces, and one professional blue accent. Re-declares the shared `--cc-*`
tokens (public + admin re-skin) and refines the header, nav, buttons, cards, and
footer. Uses the 10.1 `includes/theme.php` resolver + header wiring unchanged.

**Commit 10.1 complete — RGB Gaming theme.**  
`assets/themes/rgb-gaming.css` is a dark, high-contrast gaming theme layered over
`main.css`; it re-declares the shared `--cc-*` tokens (so token-driven public and
admin components re-skin for free) and overrides the few hard-coded light spots
(body/header/hero backdrops, flash banners, footer, white-on-accent text).
`includes/theme.php` resolves the active stylesheet from
`site_settings.active_theme_id → themes.css_file` with a path-validated fallback
to the seeded default and then `config/app.php → default_theme`; the shared
header links it **last** so it wins over `main.css`/`admin.css`. Motion respects
`prefers-reduced-motion`.

**Commit 9.9 complete — administrator reports (Stage 9 finished).**  
`admin/reports.php` charts live MySQL aggregates for orders by status, products by
performance tier, user accounts (role + status), and inventory health. Each chart has
a server-rendered accessible table fallback; Chart.js loads only on this page via
`$loadAdminReports` + `assets/js/admin-reports.js`. Logic in `includes/admin-reports.php`.

**Commit 9.8 complete — administrator review moderation.**  
`admin/reviews.php` lists pending/approved/hidden reviews (search + status filter +
pagination; pending first) with Approve / Hide / Mark pending / Delete actions.
Logic in `includes/admin-reviews.php` uses prepared statements and ENUM-validated
status writes; delete is intentional and permanent. Public pages still only show
`status = 'approved'`. CSRF + PRG throughout; verified over HTTP end-to-end
(approve→public, hide→gone, delete, CSRF-less rejected, non-admin blocked).

**Commit 9.7 complete — administrator consultation management.**  
`admin/consultations.php` (search by name/email/budget, status filter with live counts,
pagination, open/in-progress first) and `admin/consultation-details.php` (customer,
full request, attachments, status change, and a response that auto-advances an open
request to "Answered"). `admin/consultation-attachment.php` streams any customer's
uploads to staff with the same hardening as the customer endpoint (admin-only,
basename-guarded, path confined to the upload dir, `nosniff`). Logic lives in
`includes/admin-consultations.php`; CSRF + PRG on writes; verified over HTTP end-to-end
including attachment download and non-admin lockout.

**Commit 9.6 complete — administrator user management.**  
`admin/users.php` (search by name/email, role + status filters with live counts,
pagination, and an enable/disable toggle) and `admin/user-edit.php` (profile, activity
summary, recent orders, plus status and role changes). Logic in
`includes/admin-users.php` uses prepared statements, never loads the password hash into
an admin view, validates roles against the ENUM, and enforces two invariants via
`customcore_admin_user_guard()`: no self-lockout (an admin can't disable/demote their
own account) and the last active administrator can't be disabled or demoted. CSRF + PRG
on every write; verified over HTTP end-to-end.

**Commit 9.5 complete — administrator order management.**  
`admin/orders.php` (search by number/name/email, status filter with live counts, and
pagination) and `admin/order-details.php` (customer, shipping snapshot, payment label,
frozen line items with decoded options/build parts, totals, status change, and internal
admin notes). Logic lives in `includes/admin-orders.php`: prepared-statement
list/search with pagination, admin-scope order + item fetch, ENUM-validated status
writes, and notes writes. Both writes are CSRF + PRG; verified over HTTP end-to-end.

**Commit 9.4 complete — administrator compatibility metadata management.**  
`admin/compatibility.php` edits the metadata behind the PC Builder's checks: component
attributes (only fields relevant to each category, with enable/disable) and the seven
compatibility rules (name/description/severity/active; JSON config read-only). Logic in
`includes/admin-compatibility.php` writes only a whitelisted set of columns via prepared
statements; CSRF + PRG throughout. The `compatibility_rules` seed was (re)imported so the
builder runs its checks.

**Commit 9.3 complete — administrator product options management.**  
`admin/product-options.php` manages a product's configurable options (RAM, Storage,
Colour, Warranty, …): add/edit/reorder, positive-or-negative price deltas,
enable/disable, set-default, and delete, grouped by option group. Logic lives in
`includes/admin-options.php`, which validates input and keeps exactly one active
default per group (auto-promoting a replacement on disable/delete/move). CSRF + PRG
throughout; the product list links to it via a per-row "Options" action.

**Commit 9.2 complete — administrator product management.**  
`admin/products.php` (list/search/filter + enable/disable toggle),
`admin/product-add.php`, and `admin/product-edit.php` provide full catalogue CRUD
behind `customcore_require_admin()`. Logic lives in `includes/admin-products.php`
(validation, unique slugs, list queries, prepared create/update, soft
enable/disable, and secure `finfo`-checked image uploads to `uploads/products/`);
the add/edit forms share `includes/admin-product-form.php`. New
`customcore_product_image_url()` renders uploaded images beside seeded assets on the
catalogue, product, search, wishlist, and homepage pages. All writes use CSRF + PRG.

**Commit 9.1 complete — administrator dashboard.**  
`admin/index.php` shows live MySQL counts, attention alerts, recent activity, and a
Stage 9–13 tool registry. Helpers live in `includes/admin.php`; shared admin nav in
`includes/admin-nav.php`; styles in `assets/css/admin.css` (loaded via `$loadAdminCss`).
Unavailable tools remain unlinked until their pages exist.

**Commit 8.7 complete — multimedia credits (Stage 8 finished).**  
`docs/media-credits.md` documents origin, licence, and date for every image,
video, audio file, caption track, Chart.js, and Leaflet/OpenStreetMap resource.
Retained AI prompts live in `docs/image-prompts.md`. Credits are linked from the
README, Learning Centre, accessibility statement, and store-locations map note.

**Commit 8.6 complete — accessible multimedia fallbacks.**  
`accessibility.php` (public, linked from the shared footer) documents the text
equivalent for every multimedia feature — image `alt`/placeholders, video/audio
captions + transcripts + download links, the catalogue chart data table (8.5),
the builder chart text summary (5.8), and the store map address fallback (8.4).
The homepage teaser links to the guide transcript, and `main.css` honours
`prefers-reduced-motion`.

**Commit 8.5 complete — public catalogue data visualization.**  
`catalogue.php` charts active products per performance tier from live MySQL data.
`includes/catalogue-stats.php` computes the counts/price ranges and the Chart.js payload;
`assets/js/catalogue-chart.js` draws the bar chart from a `data-catalogue-chart` attribute
(Chart.js loads only on this page via `$loadCatalogueChart`). An accessible data table is
rendered server-side beside the canvas as the no-JS source of truth.

**Commit 8.4 complete — interactive store & service map.**  
`store-locations.php` renders the fictional CustomCore Campus Service Desk with a Leaflet +
OpenStreetMap map (`assets/js/store-map.js`, data-driven from `config/app.php`) plus an
always-visible `<address>`, hours, and storefront photo that stay usable without JavaScript.
Leaflet CSS/JS load only on this page via the shared header/footer.

Next: **Commit 10.1** — site-wide CSS theme templates.
