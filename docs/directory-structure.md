# CustomCore | Application Directory Structure

**Document type:** Project documentation
**Purpose:** Record the repository folder layout so new files land in the correct locations.
**Rule:** Do not add empty feature pages only to inflate page counts. Routes from `docs/sitemap.md` map to real features, not empty stubs.

---

## 1. Top-level layout

```text
customcore/
├── admin/ # Administrator PHP pages
├── api/ # Lightweight JSON/data endpoints
├── assets/
│ ├── css/ # Base, admin, and print stylesheets
│ ├── themes/ # Switchable site themes: rgb-gaming.css, minimal-pro.css, cyber-grid.css
│ ├── js/ # External JavaScript
│ ├── images/ # Copyright-safe images: products/, hero/, categories/, ui/, media/, og/, map/
│ └── media/ # Video/audio learning items + captions/
├── config/ # App and database configuration
├── database/ # schema.sql, seeds, admin setup
├── docs/ # Planning and project documentation
├── help/ # Static Help wiki (complete): hub + 6 guides; context links in docs/help-context-links.md
├── includes/ # Shared PHP layout and helpers
├── uploads/
│ ├── consultation/ # Safe consultation attachments
│ └── products/ # Admin product image uploads
├── index.php # Application entry / homepage
├── README.md
├── LICENSE
└──.gitignore
```

Root feature pages (`about.php`, `catalogue.php`, `builder.php`, …) are real implementations, not empty stubs.

---

## 2. Directory responsibilities

| Path | Responsibility |
| --- | --- |
| `admin/` | Protected admin UI (products, options, compatibility, orders, users, consultations, reviews, reports, themes, monitoring) |
| `api/` | Builder price, compatibility check, and chart data JSON endpoints |
| `assets/css/` | External CSS (`main.css` for the storefront, `admin.css` for the back office) |
| `assets/themes/` | RGB Gaming (`rgb-gaming.css`), Minimal Professional (`minimal-pro.css`), Cyber Grid (`cyber-grid.css`) |
| `assets/js/` | External JS (`main.js`, builder, cart, checkout, reviews, contact, `store-map.js`, `catalogue-chart.js`, `admin-reports.js`, `help-hub.js`, validation, charts) |
| `assets/images/` | ≥ 20 documented images |
| `assets/media/` | ≥ 3 video/audio items + captions |
| `config/` | `app.php` (non-secret settings), `app.production.example.php` (production flags template), `database.example.php` (credential template), gitignored `database.php` (real secrets) |
| `database/` | Schema, seeds, create-admin script, connection test, `verify-config.php` hygiene checker |
| `docs/` | Business case, rubric checklist, sitemap, wireframes, ER design, database import, media credits, image prompts, theme testing, Help context-link audit, production server requirements, production configuration (secrets and paths), installation / administrator / content / deployment guides, monitoring troubleshooting, security audit, repository cleanup, documentation finalization, and the QA records (HTML, CSS, JavaScript, desktop and mobile responsiveness, customer and administrator workflows, production customer/admin workflow records, rubric audit, final defect resolution) |
| `help/` | Static Help hub (`index.html`) plus topic articles (`pc-builder.html`, `accounts.html`, `catalogue.html`, `orders.html`, `support.html`, `training.html`) |
| `includes/` | Header, footer, nav, helpers, auth, CSRF, flash, cart, orders, wishlist, reviews, consultations, contact, media, catalogue-stats, theme, admin, admin-nav, admin-products, admin-product-form, admin-options, admin-compatibility, admin-orders, admin-users, admin-consultations, admin-reviews, admin-reports, admin-themes, compatibility, performance, monitoring, seo |
| `uploads/consultation/` | Validated consultation files; `index.php` + `.htaccess` deny all direct web access (served only via download endpoints) |
| `uploads/products/` | Product images uploaded by admin; `index.php` + `.htaccess` block script execution (images still served) |

Root feature pages include the public storefront, accounts, PC builder, privacy and accessibility statements, SEO `sitemap.php`, and related customer tools. Count: **51** purposeful dynamic PHP files (31 project-root + 17 admin + 3 API).

---

## 3. Git tracking notes

| Path | Tracking rule |
| --- | --- |
| `uploads/consultation/*` | Ignored except `.gitkeep`, `index.php`, `.htaccess` |
| `uploads/products/*` | Ignored except `.gitkeep`, `index.php`, `.htaccess` |
| `config/database.php` | Ignored (secrets) |
| `config/database.example.php` | Tracked |
| `.gitkeep` files | Keep empty directories in Git until real files replace them |

---

## 4. Alignment checks

- [x] Folders match the architecture described in the project architecture and `docs/sitemap.md`
- [x] Upload directories exist and are ready for ignored user content
- [x] Asset, config, database, includes, admin, api, and help locations exist
- [x] No fake catalogue/admin feature pages were added solely for page count