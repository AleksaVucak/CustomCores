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
│   ├── themes/            # Switchable site themes (Stage 10): rgb-gaming.css (10.1), minimal-pro.css (10.2)
│   ├── js/                # External JavaScript
│   ├── images/            # Copyright-safe images: products/, hero/, categories/, ui/, media/, og/, map/ (Stage 8.1)
│   └── media/             # Video/audio learning items + captions/ (Stage 8.2)
├── config/                # App and database configuration (Commit 1.2+)
├── database/              # schema.sql, seeds, admin setup (Stage 2)
├── docs/                  # Planning and project documentation
├── help/                  # Static Help wiki HTML (Stage 11)
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
| `admin/` | Protected admin UI | 9.x |
| `api/` | Builder price, compatibility, search, chart data | 5.x, 8.x, 9.x |
| `assets/css/` | External CSS (`main.css`, `admin.css`, later `print.css`) | 1.5, 9.1 |
| `assets/themes/` | RGB Gaming (`rgb-gaming.css`, 10.1), Minimal Professional (`minimal-pro.css`, 10.2), Cyber Grid | 10.x |
| `assets/js/` | External JS (`main.js`, builder, cart, checkout, reviews, contact, `store-map.js`, `catalogue-chart.js`, `admin-reports.js`, validation, charts) | 1.6, 8.4, 8.5 |
| `assets/images/` | ≥ 20 documented images | 8.1 |
| `assets/media/` | ≥ 3 video/audio items + captions | 8.2 |
| `config/` | `database.example.php`, `app.php`; real `database.php` gitignored | 1.2–1.3 |
| `database/` | Schema, seeds, create-admin script | 2.x |
| `docs/` | Business case, rubric, sitemap, wireframes, ER design, media credits, image prompts, guides | 0.x–12.x, 8.7 |
| `help/` | Static Help + training HTML (`pc-builder.html` shipped in 5.9; full wiki in 11.x) | 5.9, 11.x |
| `includes/` | Header, footer, nav, helpers, auth, CSRF, flash, cart, orders, wishlist, reviews, consultations, contact, media, catalogue-stats, theme, admin, admin-nav, admin-products, admin-product-form, admin-options, admin-compatibility, admin-orders, admin-users, admin-consultations, admin-reviews, admin-reports, compatibility, performance | 1.3–1.8, 4.x, 5.x, 6.x, 7.x, 8.x, 9.x, 10.x, 14.x |
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
