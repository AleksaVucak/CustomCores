# CustomCore — Rubric Compliance Checklist

**Document type:** Stage 0 planning (Commit 0.3+)  
**Purpose:** Map every graded requirement to planned evidence (page, file, and test).  
**Rule:** Do not mark an item **Complete** until the live evidence exists and has been checked.  
**Last updated:** Stage 13 / Commit 13.5 (monitoring troubleshooting guide — Stage 13 complete)

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
| 3a | At least 3 different site-wide CSS templates (distinct look/layout) | 12 | `assets/themes/rgb-gaming.css`, `assets/themes/minimal-pro.css`, `assets/themes/cyber-grid.css` | Themes differ in colour, typography, nav, buttons, cards, spacing, borders, and layout treatment | 10.1–10.3, 10.6 | Complete — all three distinct templates shipped and verified across 26 key pages each (`docs/theme-testing.md`, 10.6): **RGB Gaming** dark/neon (Space Grotesk, soft radius), **Minimal Professional** light editorial (Fraunces/Manrope, hairline), **Cyber Grid** HUD grid (Orbitron, zero-radius, corner-cut buttons) |
| 3b | Ability to change the template dynamically | 4 | Admin `admin/themes.php`; MySQL `themes` / `site_settings`; theme loaded in shared header include | Admin selects theme → setting saved → public and admin pages load the chosen CSS | 2.6, 10.1, 10.4–10.5 | Complete — admin switcher live (`admin/themes.php`, 10.4): CSRF + PRG writes `site_settings.active_theme_id`; header resolves via `includes/theme.php` so public + admin pages load the chosen CSS immediately; verified end-to-end across all three themes. Fallback hardened (10.5): 5-step resolution with path-traversal-safe validation, on-disk checks, and a canonical last resort — a bad setting still yields a styled site (33 automated assertions + HTTP checks) |
| 4 | Dynamic HTML forms on at least two pages (e.g. quote/calculator style) | 8 | Primary: `builder.php` (live price + options); `checkout.php` (validated order form). Extra safety: `register.php`, `consultation.php`, `contact.php` | Forms submit to PHP; builder prices recalculate; checkout creates order records without real payment data | 5.x, 6.4–6.5, 4.1, 7.x | Complete — `builder.php` (5.x) and `checkout.php` (6.4–6.5) create orders without real payment data |
| 5 | PHP code and MySQL database well documented | 20 | PHP file/function comments; `database/schema.sql` comments; `docs/database-design.md` (+ ER diagram); import notes in `docs/database-import.md`; install notes in `docs/installation-guide.md`; front-end architecture in `docs/frontend-documentation.md` | Another developer can understand schema relationships and major PHP modules from comments + docs | 2.8, 12.1, 12.4, 14.6–14.7 | In progress — ER design, schema comments, and import/backup guide complete (2.8); full installation guide (12.4), deployment/troubleshooting (12.5), and front-end architecture (12.1) now published; final code-comment audit remains (14.6–14.7) |
| 6 | All code properly commented (HTML, CSS, JS, and related sources) | 8 | Structured comments in HTML/PHP views, `assets/css/*`, `assets/js/*`, SQL seeds | Major sections documented; comments explain purpose, not obvious syntax | 14.6–14.7 | Planned |
| 7 | Help wiki: at least 5 different pages; context-sensitive Help links from the site | 10 | Static Help: `help/index.html`, `help/accounts.html`, `help/catalogue.html`, `help/pc-builder.html`, `help/orders.html`, `help/support.html` (6 pages; 5 required + hub). Context links from profile, catalogue, builder, checkout, consultation pages | Each Help article opens as its own page; feature pages link to the matching article (not only one generic Help link) | 11.1–11.7 | Complete — Help wiki has 7 pages (hub + six guides). Context-sensitive links audited in Commit 11.7 (`docs/help-context-links.md`): every customer feature page opens the matching article with a section anchor where useful (register/login/profile/edit-profile→`accounts.html`; catalogue/product/search/compare/wishlist→`catalogue.html`; builder/results/saved→`pc-builder.html`; cart/checkout/confirmation/history/details→`orders.html`; consultation/history/reviews/contact/store-locations→`support.html`). Homepage/About/Learning Centre keep the hub as an entry point and also link training. Main nav + footer remain on the hub. Exceeds the 5-page minimum; spot-check verified all Help HTML files HTTP 200 and mapped anchors present |
| 9 | Site has a main menu that is responsive across screen sizes | 4 | `includes/navigation.php` + responsive rules in `assets/css/main.css` / themes; behaviour in `assets/js/main.js`; layout contract in `docs/wireframes.md` | Desktop and mobile layouts usable; keyboard/touch menu works | 1.5, 1.7 | Complete — desktop horizontal nav; mobile toggle, Escape, focus trap |
| 10a | About ~20 dynamic HTML/PHP pages | 4 | Target **48** purposeful `.php` pages listed in `docs/sitemap.md` (17 public + 12 private + 15 admin + 4 API) | Count distinct purposeful dynamic pages; no empty placeholder pages | 1–9, 13 | In progress — public/private catalogue + account pages live; admin dashboard live (`admin/index.php`, 9.1) product management live (`admin/products.php`, `product-add.php`, `product-edit.php`, 9.2), product options live (`admin/product-options.php`, 9.3) and compatibility metadata live (`admin/compatibility.php`, 9.4), order management live (`admin/orders.php`, `admin/order-details.php`, 9.5), user management live (`admin/users.php`, `admin/user-edit.php`, 9.6), consultation management live (`admin/consultations.php`, `admin/consultation-details.php`, `admin/consultation-attachment.php`, 9.7), review moderation live (`admin/reviews.php`, 9.8), reports live (`admin/reports.php`, 9.9), theme switcher live (`admin/themes.php`, 10.4); remaining monitoring 13 |
| 10b | At least 1 external CSS file | 2 | `assets/css/main.css` (plus admin/print/theme CSS as extras) | View source shows external stylesheet link(s) | 1.5 | Complete — `main.css` linked from shared header |
| 10c | At least 1 external JavaScript file | 2 | `assets/js/main.js` (plus builder/cart/validation/charts/map as extras) | View source shows external script link(s); no console errors on core pages | 1.6 | Complete — `main.js` linked from shared footer |
| 10d | At least 20 copyright-free images | 4 | `assets/images/` (≥ 20 files); credits in `docs/media-credits.md` | Images load; filenames meaningful; alt text present; licences documented | 8.1, 8.7 | Complete — 33 images under `assets/images/` (20 product + 13 site extras); meaningful filenames; alt text via `customcore_image_url()` wiring; licences and prompts in `docs/media-credits.md` + `docs/image-prompts.md` (8.7) |
| 10e | At least 3 video or audio files | 4 | `assets/media/` (≥ 3 items); Learning Centre `media.php` | All three play with browser controls; documented in credits | 8.2–8.3, 8.7 | Complete — 2× MP4 + 1× MP3 with native controls, WebVTT captions, and transcripts on `media.php` (8.2–8.3); sources/licences documented in `docs/media-credits.md` (8.7) |
| 10f | Instructions so a non-programmer can update contents (products/images/video/audio) | 2 | `docs/content-update-guide.md`; referenced from admin guide + README | Non-programmer can follow steps to change catalogue/media without editing core logic | 12.3 | Complete — `docs/content-update-guide.md` (12.3): products/prices/stock/images/options are all changed through the admin website (no code); store details/branding via labelled values in `config/app.php`; adding a Learning Centre video/audio is a copy-files + paste-one-block task in `includes/media.php`; explicit "what NOT to edit" section |
| 11 | Website available online live (preferably `myweb.cs.uwindsor.ca`) | 2 | Production URL recorded in README; deployment docs in `docs/` | Homepage loads publicly without PHP fatals; core flows work on host | 16.x | Planned / Blocked until hosting |
| 12 | Advanced appropriate CSS (fonts, menus, boxes/cards, transitions, layouts) | 4 | Base CSS + three themes demonstrating typography, nav, cards, transitions, grids, form states | Visual review on desktop and mobile across themes | 1.5, 10.x, 14.5 | Complete — base foundation in `main.css` (1.5); three themes add distinct type/nav/buttons/cards/radius/motion (10.1–10.3); cross-theme walk of public + admin pages recorded in `docs/theme-testing.md` (10.6) |
| 13 | SEO-friendly meta: icon, title, description, keywords, etc. | 4 | Per-page metadata in layout/header; favicon; `sitemap.xml`; `robots.txt`; semantic HTML | Important public pages have unique title/description; private/admin URLs excluded from sitemap | 14.1–14.3 | Planned |

**Section A subtotal: 100 points**

Point check: 2+4+12+4+8+20+8+10+4+4+2+2+4+4+2+2+4+4 = **100**.

---

## Section B — Supporting course criteria (required by instructions; tracked for completeness)

These appear in the project instructions and package requirements. They support a full mark but are not listed as separate additive points beyond the 100-point table.

| ID | Criterion | Planned evidence | Target stage | Status |
| -- | --------- | ---------------- | ------------ | ------ |
| B1 | HTML5, CSS, JavaScript front end with full interactive functionality | Entire public/customer UI | 1–8 | Planned |
| B2 | Multimedia: images, video/audio, interactive map, interactive menus, data visualization/graphs | `media.php`, `store-locations.php`, nav, Chart.js (or equivalent) on public + admin reports + builder chart | 8.x, 5.8, 9.9 | In progress — Stage 8 public multimedia complete: images (8.1) + playable media & Learning Centre (8.2–8.3) + Leaflet/OSM map (8.4) + catalogue chart from MySQL (8.5) + accessible fallbacks (8.6) + media credits (8.7); builder performance chart (5.8); admin report charts from MySQL with accessible tables (9.9) |
| B3 | Minimum 20 unique dynamic pages and minimum 5 static pages | Dynamic PHP set (48 planned) + static Help wiki (7 pages) — `docs/sitemap.md` | Throughout; Help in 11.x | Complete for static Help — 7 Help/training HTML pages live (11.1–11.7); dynamic page count remains tracked under #10a |
| B4 | Public and private areas (registration, authentication, user profile) | `register.php`, `login.php`, `profile.php`, `edit-profile.php`, auth includes | 4.x | Planned |
| B5 | Front-end documentation | `docs/frontend-documentation.md` | 12.1 | Complete — `docs/frontend-documentation.md` (12.1) documents the shared HTML shell, the `--cc-*` token/theme system (`main.css`/`admin.css`/`assets/themes/*`), the vanilla-JS modules (builder, cart, checkout, map, charts, help-hub), the 900px responsive nav toggle, and the `includes/theme.php` resolver — all pointing at real files |
| B6 | End-user documentation; interactive training or step-by-step guide | Help wiki + training walkthrough | 11.x | Complete — six-guide Help wiki plus `help/training.html` (11.6), a numbered step-by-step walkthrough (account → shop or build → order → review) linking into the live site and each guide; hub search in `help-hub.js` |
| B7 | Admin: edit data records (products/services/options) | `admin/products.php`, `product-add.php`, `product-edit.php`, `product-options.php`, `admin/compatibility.php`, `admin/orders.php`, `admin/order-details.php` | 9.2–9.5 | Done — products CRUD (9.2), product options CRUD (9.3), compatibility metadata (9.4), and order management (9.5: search/filter/paginate, ENUM-validated status changes, and admin notes) |
| B8 | Admin: user account administration (e.g. disable accounts) | `admin/users.php`, `admin/user-edit.php` | 9.6 | Done — search by name/email + role/status filters + pagination; enable/disable logins and Customer↔Administrator role changes; `includes/admin-users.php` enforces no self-lockout and protects the last active admin; CSRF + PRG; verified over HTTP e2e |
| B9 | Admin user documentation | `docs/administrator-guide.md` | 12.2 | Complete — `docs/administrator-guide.md` (12.2) covers gaining admin access (`create-admin.php` + promotion), the live dashboard, and every shipped tool (products, options, compatibility, orders, users, consultations, reviews, reports, themes) with actions and safety rules |
| B10 | Backend monitoring page (online / warning / offline for site and feature services) | `admin/monitoring.php` + health checks in `includes/monitoring.php` + `docs/monitoring-troubleshooting.md` | 13.x | Complete — health-check engine (13.1) + admin dashboard with online/warning/offline table (13.2) + live statistics (13.3) + production-safe error hardening (13.4) + monitoring troubleshooting guide (13.5: documents the dashboard and a symptom-driven fix reference for all seven checks, the live-stats panel, and safe-messaging behaviour, with a verified CLI snippet). Stage 13 complete |
| B11 | Database with at least 20 records | Seeded `products` (and related tables) | 2.x | Complete — 20 products (+ options, components, rules, themes); verified via import guide queries |
| B12 | PHP functionality for dynamic pages | All catalogue/account/admin PHP | 3–9 | Planned |
| B13 | Software repository (e.g. GitHub) with code history | GitHub remote on `main`; meaningful commits | 0.1 ongoing | In progress |
| B14 | Installation documentation for another server | `docs/installation-guide.md`, `docs/deployment-troubleshooting.md` | 12.4–12.5, 16.x | In progress — complete clean-checkout install guide (`docs/installation-guide.md`, 12.4) and shared-hosting deployment + troubleshooting + backup/rollback guide (`docs/deployment-troubleshooting.md`, 12.5) published; final confirmation pending an actual live host (16.x) |
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
| **4.1** | `register.php` — server-side validation, `password_hash()`, duplicate-email rejection, CSRF; `includes/csrf.php` + `includes/auth.php` |
| **4.2** | `login.php` — `password_verify()`, disabled-account block, `session_regenerate_id()`, session creation; nav login state |
| **4.3** | `logout.php` — session wipe, cookie expiry, flash + redirect; `customcore_logout()` helper |
| **4.4** | Auth middleware — `customcore_require_login()` (guest redirect + return-to), `customcore_require_guest()`, `customcore_is_safe_local_path()` |
| **4.5** | `profile.php` — private dashboard; own profile + activity counts/recent activity only; `includes/account-nav.php` |
| **4.6** | `edit-profile.php` — name/email/phone/address + password change; CSRF; email-unique (self-excluded); rehash + session regen |
| **4.7** | `includes/admin-auth.php` (`customcore_require_admin()`); protected `admin/index.php`; admins-only Admin nav link; customers redirected from admin URLs |
| **4.8** | Session hardening — strict cookie flags (HttpOnly, SameSite=Lax, Secure/HTTPS), UA binding, idle (30m) + absolute (12h) timeouts, ID rotation (15m); `customcore_session_harden()` / `customcore_session_expire()`; timeouts in `config/app.php` |
| **5.1** | `builder.php` — multi-step custom PC builder form; 10 categories from DB; session-stored selections; live summary sidebar; CSRF; optional skip; reset |
| **5.2** | Live client price calc — `assets/js/builder.js`; `data-price` on radios; this-step subtotal + running total update immediately on selection change |
| **5.3** | Trusted server-side price — `api/builder-price.php` (POST JSON → DB-verified total); `builder.js` calls it on change; client total overwritten by server response |
| **5.4** | Compatibility checking — `api/compatibility-check.php`; 7 rules (socket, RAM, form factor, PSU wattage, GPU clearance, cooler fit, storage); JS live badge + per-rule results |
| **5.5** | Build summary — `builder-results.php` + `includes/compatibility.php`; parts, trusted prices, compatibility, power/performance estimates |
| **5.6** | Save builds — `builder-results.php` POST: CSRF + login + completeness + compat guard → `saved_builds` + `saved_build_items` insert; session cleared |
| **5.7** | Saved-build management — `saved-builds.php` (list) + `saved-build.php` (view/rename/delete/edit-in-builder); ownership enforced |
| **5.8** | Performance visualization — `api/chart-data.php`, Chart.js bar chart (gaming / productivity / upgrade headroom) + text fallback |
| **5.9** | Context-sensitive builder Help — `help/pc-builder.html` (every step) + anchored links from builder/results/saved pages |
| **6.1** | Shopping cart — `cart.php` (session-stored DB cart); `includes/cart.php` helpers; "Add to cart" on product + saved-build pages; quantity update, remove, clear; server-side price verification; CSRF |
| **6.2** | Cart quantity & removal controls — bulk Update cart, +/- steppers, stock clamps, remove + clear with confirm; `assets/js/cart.js` live line/subtotal preview; helpers `customcore_cart_update_*` / `remove` / `clear` |
| **6.3** | Persist carts for accounts — DB-backed `carts`/`cart_items` survive logout/login; session-cached count (`customcore_cart_count_cached`); login pre-loads count; nav badge (`.site-nav__badge`); all mutations refresh cache |
| **6.4** | Validated checkout form — `checkout.php` with server + client validation; shipping address, phone, payment method (simulated); pre-filled from profile; `assets/js/checkout.js` blur/submit validation; CSS order-summary sidebar; data stored in session for Commit 6.5; rubric #4 now In progress |
| **6.5** | Place order — `order-confirmation.php`: transaction inserts `orders` + `order_items` (frozen prices, names, build snapshots via `customcore_snapshot_build`); unique order number (CC-YYYYMMDD-XXXXXX); clears cart + refreshes count; confirmation page with receipt; CSS styles for order confirmation |
| **6.6** | Order confirmation page — `order-confirmation.php` places order then redirects to `?id=` and reloads confirmation from DB so the receipt matches the saved order |
| **6.7** | Customer order history — `order-history.php` owner-scoped table (number, date, status, items, payment label, total); optional status filter; `includes/orders.php` shared helpers; users see only their orders |
| **6.8** | Customer order details — `order-details.php` itemized receipt (shipping, payment label, frozen options/build snapshots); `customcore_order_fetch_owned` + `fetch_items` (JOIN ownership); foreign order IDs denied identically; Stage 6 complete |
| **7.1** | Customer wishlist — `wishlist.php` (list, remove, clear, move-to-cart with server-recomputed default-config price); "Save to wishlist" on `product.php`; `includes/wishlist.php` helpers (`_add`/`_remove`/`_items`/`_contains`/`_count`); `INSERT IGNORE` dedupe + `UNIQUE(wishlist_id, product_id)`; all queries scoped to `user_id`; CSRF on every action |
| **7.2** | Product review submission — form on `product.php` + `reviews.php` (rating 1–5, title, body); CSRF; login required; insert with `status = pending`; `includes/reviews.php` helpers + `assets/js/reviews.js` client validation; one pending/approved review per user/product; public lists still show approved only |
| **7.3** | PC consultation request — `consultation.php` (budget, games, software, performance goals, optional notes); CSRF; login required; insert into `consultation_requests` with `status = open`; `includes/consultations.php` helpers (statuses/labels/classes, budget whitelist, validate, create); scoped to session `user_id`; account-nav entry |
| **7.4** | Secure consultation attachments — multipart upload on `consultation.php`; validated by real MIME (`finfo`) + size (`upload_max_bytes`) + count; generated on-disk names in `uploads/consultation/` (guarded by `index.php`); `consultation_attachments` rows; request + files written in one transaction with cleanup on failure; bad type/size rejected |
| **7.5** | Customer contact form — `contact.php` (name, email, subject, message); guests OK; optional session `user_id`; CSRF + server validation; subject whitelist + “Other”; PRG flash confirmation; `includes/contact.php` helpers; `assets/js/contact.js` |
| **7.6** | Consultation request history — `consultation-history.php` owner-scoped list (status, submitted details, admin response, attachments); optional status filter; secure per-owner downloads via `consultation-attachment.php` (JOIN ownership, path realpath guard, `nosniff`, RFC 5987 filename); foreign IDs return 404; `includes/consultations.php` list/fetch helpers; Stage 7 complete |
| **9.1** | Administrator dashboard — live MySQL counts, alerts, and recent activity on `admin/index.php`; `includes/admin.php` + `admin-nav.php`; `assets/css/admin.css`; tool registry lights up as later admin pages land |
| **9.2** | Administrator product management — `admin/products.php` (search/filter list + enable/disable toggle), `admin/product-add.php`, `admin/product-edit.php`; `includes/admin-products.php` (validation, unique slugs, prepared CRUD, soft disable, secure `finfo`-checked image uploads to `uploads/products/`) + shared `admin-product-form.php`; `customcore_product_image_url()` renders uploads across the store; CSRF + PRG throughout |
| **9.3** | Administrator product options management — `admin/product-options.php` (product picker, grouped list, add/edit/reorder, positive-or-negative price deltas, enable/disable, set-default, delete); `includes/admin-options.php` enforces exactly one active default per group (auto-promotes on disable/delete/group-move) and an advisory banner for < 2 active options; CSRF + PRG; product list links via a per-row "Options" action |
| **9.4** | Administrator compatibility metadata management — `admin/compatibility.php` edits component attributes (category-relevant fields only, enable/disable) and the seven compatibility rules (name/description/severity/active; JSON config read-only); `includes/admin-compatibility.php` writes only whitelisted columns via prepared statements; CSRF + PRG. Re-seeded `compatibility_rules` so the builder runs its checks |
| **9.5** | Administrator order management — `admin/orders.php` (search by number/name/email, status filter with live per-status counts, 25-per-page pagination) and `admin/order-details.php` (customer + account status, shipping snapshot, payment label, frozen line items with decoded options/build parts, totals, ENUM-validated status change, and internal admin notes stored NULL when blank); `includes/admin-orders.php` uses prepared statements throughout; both writes CSRF + PRG; verified over HTTP end-to-end (list/search/empty-state/detail/status/notes + CSRF-less POST rejected) |
| **9.6** | Administrator user management — `admin/users.php` (search by name/email, role + status filters with live counts, pagination, enable/disable toggle) and `admin/user-edit.php` (profile, activity summary + lifetime spend, recent orders, status change, Customer↔Administrator role change); `includes/admin-users.php` never loads the password hash into admin views, validates roles against the ENUM, and enforces no self-lockout + last-active-admin protection via `customcore_admin_user_guard()`; CSRF + PRG throughout; verified over HTTP end-to-end (disable/enable, promote, and rejection of self-disable, self-demote, and CSRF-less POSTs) |
| **9.7** | Administrator consultation management — `admin/consultations.php` (search by name/email/budget, status filter with live counts, pagination, open/in-progress first) and `admin/consultation-details.php` (customer + account status, full request, attachments, ENUM-validated status change, and a response that timestamps and auto-advances open/in-progress → answered); `admin/consultation-attachment.php` streams any customer's uploads to staff with the customer endpoint's hardening (admin-only, basename-guarded, path confined to upload dir, nosniff, RFC 5987 filename); `includes/admin-consultations.php` uses prepared statements throughout; CSRF + PRG on writes; verified over HTTP end-to-end (list/search/filter, attachment download byte-for-byte, response auto-advance, CSRF-less POST rejected, non-admins blocked from queue + downloads) |
| **9.8** | Administrator review moderation — `admin/reviews.php` (search by title/body/product/customer, status filter with live counts, pagination, pending first) with Approve / Hide / Mark pending / Delete actions; `includes/admin-reviews.php` uses prepared statements and ENUM-validated status writes; public pages still show only `status = 'approved'`; CSRF + PRG throughout; verified over HTTP end-to-end (approve→visible on product page, hide→removed, delete, CSRF-less rejected, non-admin blocked) |
| **9.9** | Administrator reports — `admin/reports.php` charts live MySQL aggregates for orders by status, products by performance tier, user accounts (role + status), and inventory health; each chart has a server-rendered accessible data table; Chart.js 4.4.1 loads only on this page via `$loadAdminReports` + `assets/js/admin-reports.js`; `includes/admin-reports.php` computes all figures from PDO queries (verified totals match the live catalogue); Stage 9 complete |
| **10.1** | RGB Gaming theme — `assets/themes/rgb-gaming.css` re-declares the shared `--cc-*` tokens (dark near-black surface, cyan accent, electric-blue focus, multi-hue RGB gradient) so public + admin components re-skin, plus targeted overrides for hard-coded light spots (body/header/hero, flash banners, footer, white-on-accent text); `includes/theme.php` resolves the active stylesheet from `site_settings.active_theme_id → themes.css_file` with a path-validated fallback to the seeded default then `config/app.php → default_theme`; shared header links the theme last; motion honours `prefers-reduced-motion`; verified stylesheet order `main.css → admin.css → theme` over HTTP; #3a/#3b/#12 advanced |
| **10.2** | Minimal Professional theme — `assets/themes/minimal-pro.css`, a light editorial counterpoint to RGB Gaming; imports Fraunces (serif display) + Manrope (sans body), re-declares the `--cc-*` tokens (ink-on-paper palette, single professional blue accent, hairline borders, crisp radii, flat low-shadow surfaces, roomier max width, letter-spaced uppercase nav) so public + admin re-skin, plus refined header/nav/buttons/cards/footer rules; solid ink-blue buttons + outline variants replace neon gradients; reuses the 10.1 resolver + header wiring (no PHP changes); verified in-browser across public pages and forms with correct stylesheet order; #3a advanced (2 of 3 themes shipped) |
| **10.3** | Cyber Grid theme — `assets/themes/cyber-grid.css`, a technical HUD/grid look; imports Orbitron (angular display) + Chakra Petch (body) + Share Tech Mono (labels), re-declares the `--cc-*` tokens (near-black surfaces, visible blueprint grid backdrop, mint-green primary + mint→magenta rail, zero-radius square edges, hard neon shadows) so public + admin re-skin, plus header/hero/cards/flash/footer refinements, corner-cut (`clip-path`) monospace buttons, uppercase mono nav; motion honours `prefers-reduced-motion`; reuses the 10.1 resolver + header wiring (no PHP changes); verified in-browser (public + forms); **#3a now Complete (all 3 distinct templates shipped)** — dynamic switch (#3b) lands with the admin switcher in 10.4 |
| **10.4** | Administrator theme switching — `admin/themes.php` lists seeded themes with activate forms; CSRF + PRG writes `site_settings.active_theme_id`; `includes/admin-themes.php` validates theme id + on-disk path-safe CSS before insert/update; missing CSS cannot be activated; guests blocked by `customcore_require_admin()`; admin tool registry marks Themes available (commit 10.4); verified over HTTP that activating Minimal Professional / Cyber Grid / RGB Gaming updates the public homepage stylesheet immediately, bad CSRF and invalid ids are rejected; **#3b Complete** |
| **10.5** | Safe theme fallback — hardened `includes/theme.php` into a five-step resolution chain (active setting → `is_active_default` → config `default_theme` → hard-coded canonical `rgb-gaming` → `assets/themes/*.css` scan), all path-validated (`^assets/themes/<slug>.css`, rejects `../`, absolute paths, subdirs, query strings, non-CSS) and disk-checked before linking; DB access try/catch-wrapped; `main.css` always linked so the site is never unstyled and a corrupt `css_file` cannot leak a foreign path; proven by 33 automated assertions (transaction-rolled-back DB scenarios + traversal rejection + corrupt-config→canonical) and HTTP checks (invalid `active_theme_id` still renders RGB Gaming; traversal `css_file` does not expose `config/app.php`); #3b hardened |
| **10.6** | Cross-theme verification — walked 26 key public / account / admin pages under all three themes (78 checks): HTTP 200, `main.css` → optional `admin.css` → theme CSS order, structural chrome, no PHP error leaks; themes remain distinct (bg / accent / font / radius); no theme bugs found; results recorded in `docs/theme-testing.md`; active theme restored to RGB Gaming; **Stage 10 complete**; #3a / #12 marked Complete |
| **11.1** | Help centre homepage — expanded `help/index.html` into a searchable hub with six guide cards (Accounts, Catalogue, PC Builder, Orders, Support, Training), jump TOC, section deep-links, and related live-site links; progressive filter via `assets/js/help-hub.js`; hub search/grid styles in `main.css`; shared Help chrome matches `pc-builder.html`; #7 In progress |
| **12.1** | Front-end architecture documentation — `docs/frontend-documentation.md` documents the shared HTML shell (`includes/header.php`/`navigation.php`/`footer.php`), the `--cc-*` design-token/theme system (`assets/css/main.css` + `admin.css` + `assets/themes/*`, stylesheet order main→admin→theme), every vanilla-JS module (`main.js` utilities + nav toggle, builder, cart, checkout, reviews, contact, store-map, charts, catalogue-chart, admin-reports, help-hub) with load conditions, the 900px responsive navigation toggle, and the five-step hardened theme resolver (`includes/theme.php`); points at real files only; **B5 Complete**; supports #5/#6 |
| **12.2** | Administrator user guide — `docs/administrator-guide.md` covers gaining admin access (`database/create-admin.php` + promotion, no self-lockout / last-admin protection), sign-in and session timeouts, the live dashboard (`admin/index.php` + `includes/admin.php`), and every shipped tool: products (9.2), options (9.3), compatibility (9.4), orders (9.5), users (9.6), consultations (9.7), reviews (9.8), reports (9.9), themes (10.4), plus the planned monitoring page (13.x); **B9 Complete** |
| **12.3** | Content-update guide for non-programmers — `docs/content-update-guide.md` shows adding/editing products, prices, stock, images, and options entirely through the admin website; moderating reviews/consultations; editing store details and branding via labelled values in `config/app.php`; and adding a Learning Centre video/audio by copying files and pasting one clearly-marked block into `includes/media.php`; includes a "what NOT to edit" section and a quick-reference table; **#10f Complete** |
| **12.4** | Complete installation guide — `docs/installation-guide.md` walks a clean checkout to a working site: requirements (PHP 8+, MySQL utf8mb4, no build step), get the code, `config/database.php` from the example + `config/app.php` review, create the database, ordered schema+seed import, `create-admin.php`, writable `uploads/` dirs, running via PHP built-in server or Apache/Nginx, a smoke-test verification list, and update/upgrade notes; supports #5 and **B14** |
| **12.5** | Deployment & troubleshooting guide — `docs/deployment-troubleshooting.md` covers shared-hosting deployment principles (plain `.php` URLs, depth-safe `customcore_url()` links, no build step), pre-deploy checklist, file transfer, per-environment credentials, permissions, host database import, HTTPS/session hardening behaviour, live verification, a symptom→cause→fix troubleshooting table, and backup/restore/rollback; supports **B14** and preps #11 |
| **12.6** | Documentation set completion — README documentation list + quick-start expanded with the five Stage 12 guides and the current status set to "Stage 12 complete"; `docs/directory-structure.md` `docs/` row + status updated; this rubric checklist updated (#10f, B5, B9 → Complete; #5, B14 advanced; Section E rows 12.1–12.6); **Stage 12 complete** |
| **13.5** | Monitoring troubleshooting guide — new `docs/monitoring-troubleshooting.md` documents the `admin/monitoring.php` dashboard end to end: where to find it (admin-only, loads even when the DB is offline), how to read the overall banner / online-warning-offline vocabulary / per-service table / live-statistics panel, and a symptom-driven troubleshooting reference for all seven checks (PHP runtime, database, sessions, core files, upload storage, site theme, Learning Centre media) with exact message wording, likely cause, and fix. Also covers the live-stats panel, why detail is generic in production (13.4), how to safely get more detail locally, and a CLI snippet verified against the live engine. Linked from README, directory-structure, and cross-linked to the installation/deployment/content-update/admin guides. Completes B10 and Stage 13. |
| **13.4** | Production-safe monitoring errors — new `customcore_monitoring_safe_message()` in `includes/monitoring.php` is a defence-in-depth sanitizer applied to every dynamic error string the monitoring page can display: it cuts stack traces (`Stack trace:` / `#0` / `thrown in <path>`), reduces absolute Unix/Windows filesystem paths to `[path]`, redacts credential fragments (`password=`/`pwd=`/`pass=`), collapses whitespace, truncates to 200 chars, and falls back to a generic line when nothing safe remains. Wired into the database health check, `customcore_monitoring_stats()`, and `admin/monitoring.php`'s report fallback, so production shows generic lines and even debug detail never leaks secrets. Verified via `php -l` (clean), a 7-case sanitizer unit test (all pwd/path/trace leaks removed), and a full page render with MySQL offline (output contains no `/Users/` path, no `Stack trace`, and no password fragment). B10 In progress |
| **13.3** | Monitoring statistics — `customcore_monitoring_stats()` returns live products, users, orders, consultation requests, images (seeded + uploaded product images + site extras from disk), and stock (low/out) counts. Database totals reuse `customcore_admin_dashboard_stats()` so they match the admin dashboard (verified against live MySQL). Filesystem image/media counts remain available when MySQL is offline. `admin/monitoring.php` renders a Live statistics panel (reusing `.admin-stats` cards) loaded separately from the health-check table so a DB failure never blanks status rows. B10 In progress |
| **13.2** | Administrator monitoring dashboard — `admin/monitoring.php` (behind `customcore_require_admin()`) renders `customcore_monitoring_run()` as an online/warning/offline status table: an overall status banner with per-status counts + check timestamp, a per-service table (service · status badge · summary + safe detail list), and a status legend. Because the engine never throws and the admin guard is session-based, the page loads and shows every other service even when one check fails (render-verified with the database offline: DB row Offline, all other rows present, no PHP errors). Status badges reuse `.admin-badge--ok/--warn/--danger` via new `customcore_monitoring_status_badge_class()`; scoped `.monitor-*` styles added to `assets/css/admin.css`. The admin nav + dashboard tool card light up the Monitoring link automatically (registry detects the file). B10 In progress |
| **13.1** | Application health checks — `includes/monitoring.php` adds the Stage 13 monitoring engine: seven independent, self-contained checks (PHP runtime + extensions, database PDO connect + `SELECT 1`, sessions + writable store, core files present, upload dirs exist/writable, active theme resolves, Learning Centre media declared-vs-on-disk) each returning a controlled `online`/`warning`/`offline` status with a production-safe summary (DB errors reuse `customcore_database_error_message()`; no credentials/paths/stack traces). `customcore_monitoring_run()` aggregates to an overall status and never throws so a single failure only downgrades its own row; helpers `customcore_monitoring_status_rank/label/worst`. `includes/media.php` now exposes `customcore_media_catalogue()` for the media check (no change to `customcore_media_items()`). Verified via CLI: `php -l` clean and a run returns controlled results (DB offline handled safely, other checks online); B10 In progress |

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
6. Begin **Stage 4** — accounts and auth.  
   - [x] 4.1 Customer registration  
   - [x] 4.2 Secure customer login  
   - [x] 4.3 Secure logout  
   - [x] 4.4 Protect private customer routes  
   - [x] 4.5 Customer profile dashboard  
   - [x] 4.6 Profile editing  
   - [x] 4.7 Role-based permissions  
   - [x] 4.8 Session hardening  
7. After Stage 10 — #3a / #3b / #12 **Complete** (templates + switcher + fallback + `docs/theme-testing.md` walkthrough).
8. After Stage 16 — mark #11 **Complete** with the live URL.  
9. Stage 15.8 — final audit: every Section A row must be **Complete** with tested evidence.

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

### Stage 4 progress

- [x] 4.1 Customer registration (`register.php` + `includes/csrf.php`, `includes/auth.php`)
- [x] 4.2 Secure customer login (`login.php` — session creation + regeneration)
- [x] 4.3 Secure logout (`logout.php` + `customcore_logout()`)
- [x] 4.4 Protect private customer routes (`customcore_require_login()` / `require_guest()`)
- [x] 4.5 Customer profile dashboard (`profile.php` + `includes/account-nav.php`)
- [x] 4.6 Profile editing (`edit-profile.php` — details + password change)
- [x] 4.7 Role-based permissions (`includes/admin-auth.php` + protected `admin/index.php`)
- [x] 4.8 Session hardening  

### Stage 5 progress

- [x] 5.1 Multi-step PC Builder form (`builder.php`)
- [x] 5.2 Live client-side price calculation (`assets/js/builder.js`)
- [x] 5.3 Server-side price recalculation (`api/builder-price.php`)
- [x] 5.4 Component compatibility checking (`api/compatibility-check.php`)
- [x] 5.5 Build summary page (`builder-results.php`)
- [x] 5.6 Save builds (`builder-results.php` POST → `saved_builds` + `saved_build_items`)
- [x] 5.7 Saved-build management (`saved-builds.php` + `saved-build.php`)
- [x] 5.8 Build performance visualization (`api/chart-data.php` + `assets/js/charts.js`)
- [x] 5.9 Context-sensitive builder Help (`help/pc-builder.html`)

### Stage 6 progress

- [x] 6.1 Shopping cart (`cart.php` + `includes/cart.php`)
- [x] 6.2 Cart quantity and removal controls (`update` / `update_all` / `remove` / `clear` + `assets/js/cart.js`)
- [x] 6.3 Persist carts for customer accounts (DB-backed; session-cached nav badge count; login pre-load)
- [x] 6.4 Validated checkout form (`checkout.php` + `assets/js/checkout.js`; server + client validation)
- [x] 6.5 Place order (`order-confirmation.php` — transaction: `orders` + `order_items`; frozen snapshots; clear cart)
- [x] 6.6 Order confirmation page (`order-confirmation.php?id=` — confirmation matches saved DB order)
- [x] 6.7 Customer order history (`order-history.php` + `includes/orders.php`; owner-scoped; status filters)
- [x] 6.8 Customer order details (`order-details.php` — itemized; ownership helpers; block other users' IDs)

### Stage 6 acceptance

- [x] Cart holds catalogue products and saved custom builds
- [x] Quantity update, remove, and clear keep totals accurate
- [x] Cart persists across logout/login for the same account
- [x] Checkout form validates shipping + simulated payment (no real card data)
- [x] Cart converts to `orders` + `order_items` with frozen prices/snapshots
- [x] Confirmation number matches the saved database order
- [x] Order history lists only the current user's orders
- [x] Order details deny access to another user's order ID

## Stage 7 progress — wishlist, reviews, and consultations

- [x] 7.1 Customer wishlist (`wishlist.php` + `includes/wishlist.php`; save from `product.php`; move-to-cart; owner-scoped; CSRF)
- [x] 7.2 Product review submission (`reviews.php` + `product.php` form; `status = pending`; `includes/reviews.php`; client validation)
- [x] 7.3 PC consultation request form (`consultation.php` + `includes/consultations.php`; `status = open`; owner-scoped; CSRF)
- [x] 7.4 Secure consultation attachments (`finfo` MIME + size + count validation; generated names; transactional; cleanup on failure)
- [x] 7.5 Customer contact form (`contact.php` + `includes/contact.php`; guests OK; optional `user_id`; flash confirmation)
- [x] 7.6 Consultation request history (`consultation-history.php` + `consultation-attachment.php`; owner-scoped; secure downloads; foreign IDs blocked)

### Stage 7.1 acceptance

- [x] Logged-in customers can save a product to a private wishlist
- [x] A product appears on a wishlist at most once (unique constraint + `INSERT IGNORE`)
- [x] Items can be removed individually or cleared in bulk
- [x] Move-to-cart adds the product (price recomputed server-side) and removes it from the wishlist
- [x] Every wishlist query is scoped to the session `user_id`

### Stage 7.2 acceptance

- [x] Logged-in customers can submit rating, title, and body
- [x] New reviews are stored with `status = pending` (not shown publicly)
- [x] CSRF and server-side validation reject incomplete/invalid submissions
- [x] Guests are prompted to log in; duplicate pending/approved reviews are blocked

### Stage 7.3 acceptance

- [x] Logged-in customers can submit a consultation request
- [x] Valid submissions create a `consultation_requests` row with `status = open`
- [x] Budget is validated against a whitelist; required text fields enforced
- [x] The request is tied to the session `user_id` (never a form-supplied id)

### Stage 7.4 acceptance

- [x] Customers can attach PDF/TXT/PNG/JPG/WEBP files to a request
- [x] Files are validated by real MIME type, size limit, and max count
- [x] Invalid type/oversized files are rejected without creating the request
- [x] Accepted files are stored with generated names under `uploads/consultation/`
- [x] A `consultation_attachments` row is written per file; failure rolls back and cleans up

### Stage 7.5 acceptance

- [x] Guests and logged-in customers can submit a contact message
- [x] Valid messages are stored in `contact_messages` with confirmation shown
- [x] CSRF and server-side validation reject incomplete/invalid submissions
- [x] Logged-in `user_id` is taken from the session only (optional FK)

### Stage 7.6 acceptance

- [x] Customers see a list of only their own consultation requests
- [x] Each request shows its status and any administrator response
- [x] A foreign request ID is never exposed (owner-scoped queries)
- [x] Attachment downloads are owner-checked, path-guarded, and streamed safely

### Stage 7 acceptance (overall)

- [x] Wishlist works and is private to each user
- [x] Reviews save as pending (moderation)
- [x] Consultation requests save with `status = open`
- [x] Upload validation rejects bad type/size; good files stored securely
- [x] Private request history is protected (owner-only)

