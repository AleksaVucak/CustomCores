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
│   ├── images/            # Copyright-safe images (Stage 8)
│   └── media/             # Video/audio learning items (Stage 8)
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
| `assets/css/` | External CSS (`main.css`, later `admin.css`, `print.css`) | 1.5 |
| `assets/themes/` | RGB Gaming, Minimal Professional, Cyber Grid | 10.x |
| `assets/js/` | External JS (`main.js`, later builder/cart/etc.) | 1.6 |
| `assets/images/` | ≥ 20 documented images | 8.1 |
| `assets/media/` | ≥ 3 video/audio items | 8.2 |
| `config/` | `database.example.php`, `app.php`; real `database.php` gitignored | 1.2–1.3 |
| `database/` | Schema, seeds, create-admin script | 2.x |
| `docs/` | Business case, rubric, sitemap, wireframes, ER design, guides | 0.x–12.x |
| `help/` | Static Help + training HTML | 11.x |
| `includes/` | Header, footer, nav, helpers, auth, CSRF, validation, flash | 1.3–1.8, 4.x, 14.x |
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

**Commit 3.3 complete.**  
Catalogue: `catalogue.php` — all active products from MySQL; tier chips via `?category=`.

Next: **Commit 3.4** — dynamic product detail pages.
