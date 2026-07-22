# CustomCore — Rubric Compliance Checklist

**Document type:** Stage 0 planning (Commit 0.3+)  
**Purpose:** Map every graded requirement to planned evidence (page, file, and test).  
**Rule:** Do not mark an item **Complete** until the live evidence exists and has been checked.  
**Last updated:** Stage 3 / Commit 3.8 (approved product reviews — Stage 3 complete)

### Status legend

| Status | Meaning |
| ------ | ------- |
| Planned | Location and evidence are decided; not built yet |
| In progress | Partially implemented |
| Complete | Evidence exists, was tested, and is ready for grading |
| Blocked | Waiting on hosting, credentials, or an earlier stage |

### Point total

| Block | Points |
| ----- | -----: |
| Graded rubric rows (items 1–13 below) | **100** |
| Supporting course criteria (tracked, not double-counted) | See Section B |

---

## Section A — Official 100-point grading scheme

| # | Requirement | Pts | Planned evidence (page / file) | How it will be verified | Target stage | Status |
| - | ----------- | --: | ------------------------------ | ----------------------- | ------------ | ------ |
| 1 | Business case: at least one paragraph describing the catalogue/project (e.g. on About) | 2 | `about.php` (public paragraph); planning source `docs/business-case.md`; README summary | About page shows a clear business paragraph; matches CustomCore catalogue idea | 0.2 (docs), 1.4/3.2 (About) | Complete — full business case published on `about.php` (3.2) |
| 2 | No fewer than 20 products; each product has at least 2 options | 4 | MySQL `products` + `product_options`; seeds `database/seed-products.sql` + `database/seed-product-options.sql`; UI `catalogue.php`, `product.php` | SQL count ≥ 20 active products; query confirms every product has ≥ 2 options; options visible on product pages | 2.2–2.3, 3.3–3.4 | Complete — 20 products + options seeded; catalogue grid live (3.3); options visible on product detail (3.4) |
| 3a | At least 3 different site-wide CSS templates (distinct look/layout) | 12 | `assets/themes/rgb-gaming.css`, `assets/themes/minimal-pro.css`, `assets/themes/cyber-grid.css` | Themes differ in colour, typography, nav, buttons, cards, spacing, borders, and layout treatment | 10.1–10.3 | Planned |
| 3b | Ability to change the template dynamically | 4 | Admin `admin/themes.php`; MySQL `themes` / `site_settings`; theme loaded in shared header include | Admin selects theme → setting saved → public and admin pages load the chosen CSS | 2.6, 10.4–10.5 | In progress — theme + settings rows seeded (2.6); CSS files and switcher in Stage 10 |
| 4 | Dynamic HTML forms on at least two pages (e.g. quote/calculator style) | 8 | Primary: `builder.php` (live price + options); `checkout.php` (validated order form). Extra safety: `register.php`, `consultation.php`, `contact.php` | Forms submit to PHP; builder prices recalculate; checkout creates order records without real payment data | 5.x, 6.4–6.5, 4.1, 7.x | Planned |
| 5 | PHP code and MySQL database well documented | 20 | PHP file/function comments; `database/schema.sql` comments; `docs/database-design.md` (+ ER diagram); import notes in `docs/database-import.md`; install notes in `docs/installation-guide.md` | Another developer can understand schema relationships and major PHP modules from comments + docs | 2.8, 12.4, 14.6–14.7 | In progress — ER design, schema comments, and import/backup guide complete (2.8); full installation guide later |
| 6 | All code properly commented (HTML, CSS, JS, and related sources) | 8 | Structured comments in HTML/PHP views, `assets/css/*`, `assets/js/*`, SQL seeds | Major sections documented; comments explain purpose, not obvious syntax | 14.6–14.7 | Planned |
| 7 | Help wiki: at least 5 different pages; context-sensitive Help links from the site | 10 | Static Help: `help/index.html`, `help/accounts.html`, `help/catalogue.html`, `help/pc-builder.html`, `help/orders.html`, `help/support.html` (6 pages; 5 required + hub). Context links from profile, catalogue, builder, checkout, consultation pages | Each Help article opens as its own page; feature pages link to the matching article (not only one generic Help link) | 11.1–11.7 | Planned |
| 9 | Site has a main menu that is responsive across screen sizes | 4 | `includes/navigation.php` + responsive rules in `assets/css/main.css` / themes; behaviour in `assets/js/main.js`; layout contract in `docs/wireframes.md` | Desktop and mobile layouts usable; keyboard/touch menu works | 1.5, 1.7 | Complete — desktop horizontal nav; mobile toggle, Escape, focus trap |
| 10a | About ~20 dynamic HTML/PHP pages | 4 | Target **48** purposeful `.php` pages listed in `docs/sitemap.md` (17 public + 12 private + 15 admin + 4 API) | Count distinct purposeful dynamic pages; no empty placeholder pages | 1–9, 13 | Planned — sitemap documented (0.4); pages not built yet |
| 10b | At least 1 external CSS file | 2 | `assets/css/main.css` (plus admin/print/theme CSS as extras) | View source shows external stylesheet link(s) | 1.5 | Complete — `main.css` linked from shared header |
| 10c | At least 1 external JavaScript file | 2 | `assets/js/main.js` (plus builder/cart/validation/charts/map as extras) | View source shows external script link(s); no console errors on core pages | 1.6 | Complete — `main.js` linked from shared footer |
| 10d | At least 20 copyright-free images | 4 | `assets/images/` (≥ 20 files); credits in `docs/media-credits.md` | Images load; filenames meaningful; alt text present; licences documented | 8.1, 8.7 | Planned |
| 10e | At least 3 video or audio files | 4 | `assets/media/` (≥ 3 items); Learning Centre `media.php` | All three play with browser controls; documented in credits | 8.2–8.3 | Planned |
| 10f | Instructions so a non-programmer can update contents (products/images/video/audio) | 2 | `docs/content-update-guide.md`; referenced from Help / admin docs | Non-programmer can follow steps to change catalogue/media without editing core logic | 12.3 | Planned |
| 11 | Website available online live (preferably `myweb.cs.uwindsor.ca`) | 2 | Production URL recorded in README; deployment docs in `docs/` | Homepage loads publicly without PHP fatals; core flows work on host | 16.x | Planned / Blocked until hosting |
| 12 | Advanced appropriate CSS (fonts, menus, boxes/cards, transitions, layouts) | 4 | Base CSS + three themes demonstrating typography, nav, cards, transitions, grids, form states | Visual review on desktop and mobile across themes | 1.5, 10.x, 14.5 | In progress — base foundation in `main.css` (1.5); themes later |
| 13 | SEO-friendly meta: icon, title, description, keywords, etc. | 4 | Per-page metadata in layout/header; favicon; `sitemap.xml`; `robots.txt`; semantic HTML | Important public pages have unique title/description; private/admin URLs excluded from sitemap | 14.1–14.3 | Planned |

**Section A subtotal: 100 points**

Point check: 2+4+12+4+8+20+8+10+4+4+2+2+4+4+2+2+4+4 = **100**.

---

## Section B — Supporting course criteria (required by instructions; tracked for completeness)

These appear in the project instructions and package requirements. They support a full mark but are not listed as separate additive points beyond the 100-point table.

| ID | Criterion | Planned evidence | Target stage | Status |
| -- | --------- | ---------------- | ------------ | ------ |
| B1 | HTML5, CSS, JavaScript front end with full interactive functionality | Entire public/customer UI | 1–8 | Planned |
| B2 | Multimedia: images, video/audio, interactive map, interactive menus, data visualization/graphs | `media.php`, `store-locations.php`, nav, Chart.js (or equivalent) on public + admin reports + builder chart | 8.x, 5.8, 9.9 | Planned |
| B3 | Minimum 20 unique dynamic pages and minimum 5 static pages | Dynamic PHP set (48 planned) + static Help wiki (7 planned) — `docs/sitemap.md` | Throughout; Help in 11.x | Planned — counts documented in 0.4 |
| B4 | Public and private areas (registration, authentication, user profile) | `register.php`, `login.php`, `profile.php`, `edit-profile.php`, auth includes | 4.x | Planned |
| B5 | Front-end documentation | `docs/frontend-documentation.md` | 12.1 | Planned |
| B6 | End-user documentation; interactive training or step-by-step guide | Help wiki + training walkthrough (Commit 11.8) | 11.x | Planned |
| B7 | Admin: edit data records (products/services/options) | `admin/products.php`, `product-add.php`, `product-edit.php`, `product-options.php` | 9.2–9.3 | Planned |
| B8 | Admin: user account administration (e.g. disable accounts) | `admin/users.php`, `admin/user-edit.php` | 9.6 | Planned |
| B9 | Admin user documentation | `docs/administrator-guide.md` | 12.2 | Planned |
| B10 | Backend monitoring page (online / warning / offline for site and feature services) | `admin/monitoring.php` + health checks | 13.x | Planned |
| B11 | Database with at least 20 records | Seeded `products` (and related tables) | 2.x | Complete — 20 products (+ options, components, rules, themes); verified via import guide queries |
| B12 | PHP functionality for dynamic pages | All catalogue/account/admin PHP | 3–9 | Planned |
| B13 | Software repository (e.g. GitHub) with code history | GitHub remote on `main`; meaningful commits | 0.1 ongoing | In progress |
| B14 | Installation documentation for another server | `docs/installation-guide.md`, deployment/troubleshooting docs | 12.4–12.5, 16.x | Planned |
| B15 | Desktop and mobile responsiveness (at least one desktop and one mobile) | Responsive CSS; test checklists in Stage 15 | 1.5, 15.4–15.5 | Planned |

---

## Section C — Evidence index by artefact (quick lookup)

| Artefact | Rubric rows it supports |
| -------- | ----------------------- |
| `about.php` + `docs/business-case.md` | #1 |
| `database/schema.sql`, seeds, `docs/database-import.md`, `product.php` | #2, #5, B11 |
| `assets/themes/*.css` + `admin/themes.php` | #3a, #3b, #12 |
| `builder.php`, `checkout.php` | #4 |
| PHP/SQL comments + `docs/database-design.md` | #5, #6 |
| `help/*.html` + context links | #7, B6 |
| `includes/navigation.php` | #9, B2 (menus) |
| ≥ 35 `.php` pages (planned sitemap) | #10a, B3 — see `docs/sitemap.md` (48 planned) |
| `assets/css/main.css` | #10b, #12 |
| `assets/js/main.js` | #10c |
| `assets/images/` + `docs/media-credits.md` | #10d, B2 |
| `assets/media/` + `media.php` | #10e, B2 |
| `docs/content-update-guide.md` | #10f |
| Live URL in README | #11 |
| Meta tags, favicon, `sitemap.xml`, `robots.txt` | #13 |
| Auth + profile pages | B4 |
| Admin product/user tools | B7, B8 |
| `admin/monitoring.php` | B10 |
| GitHub repository | B13 |
| `docs/installation-guide.md` | B14 |

---

## Section D — Safety interpretations (explicit)

| Topic | Decision for CustomCore |
| ----- | ----------------------- |
| Dynamic page count | Aim for **≥ 35** purposeful PHP pages (assignment says ~20; master plan prefers headroom) |
| Static pages | At least **5** separate Help HTML pages (plus Help hub recommended) |
| Forms | Builder + checkout are the two primary “dynamic form” evidences; additional forms strengthen the case |
| Themes | Three **complete** templates that differ beyond colour alone; admin can switch sitewide |
| Products | **20** configurable prebuilts, **≥ 2 options each** (target ≥ 4 option groups) |
| Checkout | Simulated only — store payment-method label, never card numbers |
| Hosting | Prefer `myweb.cs.uwindsor.ca`; any working public URL satisfies #11 if permitted |

---

## Section E — Update log

| Commit / stage | Checklist change |
| -------------- | ---------------- |
| 0.2 | Business-case planning doc created (`docs/business-case.md`) — supports #1 planning evidence |
| **0.3** | This checklist created; all Section A rows have evidence columns; statuses set to Planned except B13 in progress |
| **0.4** | `docs/sitemap.md` added; #10a / B3 evidence updated to 48 dynamic + 7 static planned pages |
| **0.5** | `docs/wireframes.md` added; #9 notes desktop/mobile nav visible on home, catalogue, builder, profile, cart, admin |
| **0.6** | `docs/database-design.md` added with ER diagram and 21-table plan; #5 planning evidence updated |
| **1.1** | Application directories created; `docs/directory-structure.md` and root `index.php` added |
| **1.2** | Secure config templates: `config/app.php`, `config/database.example.php`; `database.php` remains gitignored |
| **1.3** | PDO helper `includes/database.php` + CLI `database/test-connection.php`; errors hide credentials when debug is off |
| **1.4** | Shared `header.php`, `footer.php`, `navigation.php`, `functions.php`; `index.php` + `about.php` reuse layout |
| **1.5** | Base responsive `assets/css/main.css` (variables, nav, forms, cards, grids, breakpoints); #10b complete |
| **1.6** | Shared `assets/js/main.js` utilities (`CustomCore` helpers); #10c complete |
| **1.7** | Responsive nav toggle (open/close, Escape, focus trap, resize reset); #9 complete |
| **1.8** | Flash system (`includes/flash.php`) with success/warning/error; one-redirect lifetime; Stage 1 complete |
| **2.1** | `database/schema.sql` — all 21 InnoDB tables with PKs, FKs, indexes, constraints, and comments; #5 now In progress |
| **2.2** | `database/seed-products.sql` — 4 tiers + 20 active products (5 each); #2 / B11 now In progress |
| **2.3** | `database/seed-product-options.sql` — 323 options; every product has ≥ 2 (typically 14–19); verification query ready |
| **2.4** | `database/seed-components.sql` — 10 builder categories + 60 parts with compatibility attributes |
| **2.5** | `database/seed-compatibility.sql` — 7 active rules with JSON config; demo queries confirm compatible + incompatible cases |
| **2.6** | `database/seed-themes.sql` — 3 themes + `active_theme_id` (default RGB Gaming); #3b now In progress |
| **2.7** | `database/create-admin.php` — CLI-only; bcrypt hash; validates email/password; handles duplicates |
| **2.8** | `docs/database-import.md` — full import/verify/backup guide; ER design aligned; Stage 2 complete; B11 Complete; #5 updated |
| **3.1** | Dynamic `index.php` — featured products + categories from MySQL; hero CTAs; media teaser placeholder |
| **3.2** | Expanded `about.php` — full public business case; rubric #1 Complete |
| **3.3** | `catalogue.php` — responsive MySQL product grid; optional category slug; all active products |
| **3.4** | `product.php?id=N` — full detail page with option groups, price deltas, specs, stock; rubric #2 Complete |
| **3.5** | `search.php` — search name/category/brand/description/specs; empty + no-results states; catalogue search form |
| **3.6** | `catalogue.php` rewritten with filter sidebar + 6 sort modes; all filters combinable via prepared statements |
| **3.7** | `compare.php` — side-by-side comparison (2–4 products); catalogue checkboxes + product detail entry |
| **3.8** | `reviews.php` + product reviews section — `status = approved` only; `seed-reviews.sql` demo data |

---

## Section F — Next checklist actions

1. ~~Begin Stage 2 — Commit 2.1 MySQL database schema.~~ Done.  
2. ~~Commit 2.2 — seed twenty products.~~ Done (4 tiers × 5).  
3. ~~Commit 2.3 — product options.~~ Done (≥ 2 options per product; 323 total).  
4. ~~Stage 2 complete~~ — import guide published (`docs/database-import.md`).  
5. Begin **Stage 3** — public catalogue pages; mark #2 **Complete** when catalogue/product pages show options.  
   - [x] 3.1 Dynamic homepage  
   - [x] 3.2 Business case About page (#1 Complete)  
   - [x] 3.3 Database-driven catalogue  
   - [x] 3.4 Product detail pages (#2 Complete)  
   - [x] 3.5 Product search  
   - [x] 3.6 Filters and sorting  
   - [x] 3.7 Product comparison  
   - [x] 3.8 Approved reviews — **Stage 3 complete**  
6. After Stage 10 — mark #3a / #3b **Complete** after theme switch test.
7. After Stage 16 — mark #11 **Complete** with the live URL.  
8. Stage 15.8 — final audit: every Section A row must be **Complete** with tested evidence.

**Commit 0.3 acceptance:** Every rubric item in Section A has a points value, planned evidence column, verification method, target stage, and status. No graded row is left without an evidence plan.  
**Commit 0.4 acceptance:** Sitemap documents ≥ 20 dynamic and ≥ 5 static pages with purposeful routes (48 + 7 planned).  
**Commit 0.5 acceptance:** Wireframes for homepage, catalogue, builder, profile, cart, and admin show core navigation on desktop and mobile.  
**Commit 0.6 acceptance:** ER diagram and table plan represent all major feature relationships (Stage 0 complete).  
**Commit 1.1 acceptance:** Repository folder layout matches the documented architecture; upload/asset/config/includes locations exist without fake feature-page stubs.  
**Commit 1.2 acceptance:** Example database config and app config exist; real `config/database.php` is gitignored and not present in the repository.  
**Commit 1.3 acceptance:** Reusable PDO helper exists; connection failures do not expose passwords; CLI test script is not a public web probe.  
**Commit 1.4 acceptance:** Multiple pages (`index.php`, `about.php`) reuse the same header, navigation, and footer includes.  
**Commit 1.5 acceptance:** External `main.css` provides variables, layout, nav, forms, cards, and desktop/mobile breakpoints.  
**Commit 1.6 acceptance:** External `main.js` loads shared utilities without requiring page-specific DOM nodes.  
**Commit 1.7 acceptance:** Mobile menu toggle is keyboard and touch usable; desktop layout remains a horizontal menu.  
**Commit 1.8 acceptance:** Flash messages support success/warning/error, survive one redirect, then clear.

### Stage 1 acceptance

- [x] Shared header and footer load correctly
- [x] Main menu works on desktop and mobile
- [x] CSS and JavaScript are external
- [x] Database connection helper exists (test with local `config/database.php`)
- [x] Flash messages survive one redirect and clear
- [x] No intentional PHP warnings in normal layout use


### Stage 0 acceptance (all met in docs)

- [x] Business idea finalized (`docs/business-case.md`)
- [x] Rubric requirements have planned evidence (`docs/rubric-checklist.md`)
- [x] Sitemap exceeds minimum page count (`docs/sitemap.md`)
- [x] Core wireframes show navigation on desktop and mobile (`docs/wireframes.md`)
- [x] Database tables and relationships planned (`docs/database-design.md`)
- [x] No major application coding before Stage 0 completion

### Stage 1 progress

- [x] 1.1 Directory structure
- [x] 1.2 Secure configuration templates
- [x] 1.3 PDO database connection
- [x] 1.4 Shared header, footer, navigation includes
- [x] 1.5 Base responsive stylesheet
- [x] 1.6 Shared JavaScript utilities
- [x] 1.7 Responsive main navigation
- [x] 1.8 Flash message system

### Stage 2 progress

- [x] 2.1 MySQL database schema (`database/schema.sql`)
- [x] 2.2 Twenty configurable PC products (`database/seed-products.sql`)
- [x] 2.3 Product options for every PC (`database/seed-product-options.sql`)
- [x] 2.4 Custom builder components (`database/seed-components.sql`)
- [x] 2.5 Simplified compatibility rules (`database/seed-compatibility.sql`)
- [x] 2.6 Themes and site settings (`database/seed-themes.sql`)
- [x] 2.7 Secure admin creation script (`database/create-admin.php`)
- [x] 2.8 Import guide and documentation (`docs/database-import.md`)

### Stage 2 acceptance

- [x] Clean import from scratch documented
- [x] 20 configurable products, ≥ 2 options each
- [x] Builder components + compatibility values exist
- [x] Theme + site-setting records exist
- [x] Secure admin setup (no plain-text password in Git)
- [x] Import/backup guide and ER alignment documented

### Stage 3 progress

- [x] 3.1 Dynamic homepage (`index.php` — featured products from MySQL)
- [x] 3.2 Business case About page (`about.php`)
- [x] 3.3 Database-driven catalogue (`catalogue.php`)
- [x] 3.4 Product detail pages (`product.php`)
- [x] 3.5 Product search (`search.php`)
- [x] 3.6 Catalogue filters and sorting (`catalogue.php`)
- [x] 3.7 Product comparison (`compare.php`)
- [x] 3.8 Approved product reviews (`reviews.php` + product section)

