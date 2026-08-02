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
│   ├── themes/            # Three switchable site themes (Stage 10)
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
| `assets/themes/` | RGB Gaming, Minimal Professional, Cyber Grid | 10.x |
| `assets/js/` | External JS (`main.js`, builder, cart, checkout, reviews, contact, `store-map.js`, `catalogue-chart.js`, validation, charts) | 1.6, 8.4, 8.5 |
| `assets/images/` | ≥ 20 documented images | 8.1 |
| `assets/media/` | ≥ 3 video/audio items + captions | 8.2 |
| `config/` | `database.example.php`, `app.php`; real `database.php` gitignored | 1.2–1.3 |
| `database/` | Schema, seeds, create-admin script | 2.x |
| `docs/` | Business case, rubric, sitemap, wireframes, ER design, media credits, image prompts, guides | 0.x–12.x, 8.7 |
| `help/` | Static Help + training HTML (`pc-builder.html` shipped in 5.9; full wiki in 11.x) | 5.9, 11.x |
| `includes/` | Header, footer, nav, helpers, auth, CSRF, flash, cart, orders, wishlist, reviews, consultations, contact, media, catalogue-stats, admin, admin-nav, admin-products, admin-product-form, admin-options, admin-compatibility, admin-orders, compatibility, performance | 1.3–1.8, 4.x, 5.x, 6.x, 7.x, 8.x, 9.x, 14.x |
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

Next: **Commit 9.6** — administrator user management.
