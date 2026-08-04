# CustomCore | Rubric Compliance Checklist

**Document type:** Project documentation
**Purpose:** Map every graded requirement to planned evidence (page, file, and test).
**Rule:** Do not mark an item **Complete** until the live evidence exists and has been checked.
**Last updated:** August 2026

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
| 5 | PHP code and MySQL database well documented | 20 | PHP file/function comments; `database/schema.sql` comments; `docs/database-design.md` (+ ER diagram); import notes in `docs/database-import.md`; install notes in `docs/installation-guide.md`; front-end architecture in `docs/frontend-documentation.md` | Another developer can understand schema relationships and major PHP modules from comments + docs | 2.8, 12.1, 12.4, 14.6–14.7 | Complete — ER design, schema comments, and import/backup guide (2.8); full installation guide (12.4), deployment/troubleshooting (12.5), and front-end architecture (12.1) published; code-comment audit done — a token scan confirms all 282 PHP functions carry docblocks and every PHP file has a file-responsibility header (14.7) |
| 6 | All code properly commented (HTML, CSS, JS, and related sources) | 8 | Structured comments in HTML/PHP views, `assets/css/*`, `assets/js/*`, SQL seeds | Major sections documented; comments explain purpose, not obvious syntax | 14.6–14.7 | Complete — CSS fully commented (numbered section headers/dividers in `main.css` + `admin.css` + all three themes); HTML views carry purpose-first `<!-- section -->` comments across all 41 templates (14.6); every JS file has a file header and JSDoc on all named functions (14.7); SQL seeds/schema commented (2.1) |
| 7 | Help wiki: at least 5 different pages; context-sensitive Help links from the site | 10 | Static Help: `help/index.html`, `help/accounts.html`, `help/catalogue.html`, `help/pc-builder.html`, `help/orders.html`, `help/support.html` (6 pages; 5 required + hub). Context links from profile, catalogue, builder, checkout, consultation pages | Each Help article opens as its own page; feature pages link to the matching article (not only one generic Help link) | 11.1–11.7 | Complete — Help wiki has 7 pages (hub + six guides). Context-sensitive links audited in (`docs/help-context-links.md`): every customer feature page opens the matching article with a section anchor where useful (register/login/profile/edit-profile→`accounts.html`; catalogue/product/search/compare/wishlist→`catalogue.html`; builder/results/saved→`pc-builder.html`; cart/checkout/confirmation/history/details→`orders.html`; consultation/history/reviews/contact/store-locations→`support.html`). Homepage/About/Learning Centre keep the hub as an entry point and also link training. Main nav + footer remain on the hub. Exceeds the 5-page minimum; spot-check verified all Help HTML files HTTP 200 and mapped anchors present |
| 9 | Site has a main menu that is responsive across screen sizes | 4 | `includes/navigation.php` + responsive rules in `assets/css/main.css` / themes; behaviour in `assets/js/main.js`; layout contract in `docs/wireframes.md` | Desktop and mobile layouts usable; keyboard/touch menu works | 1.5, 1.7 | Complete — desktop horizontal nav; mobile toggle, Escape, focus trap |
| 10a | About ~20 dynamic HTML/PHP pages | 4 | Purposeful `.php` pages listed in `docs/sitemap.md` | Count distinct purposeful dynamic pages; no empty placeholder pages | 1–9, 13 | Complete — **50** purposeful dynamic PHP files verified in the 15.8 audit (30 public/customer `*.php` + 17 `admin/*.php` + 3 `api/*.php`), far above the ~20 minimum; public + Help pages return HTTP 200 and private/admin pages correctly gate to login (303) — no empty placeholders. All admin tooling live: dashboard (9.1), products (9.2), options (9.3), compatibility (9.4), orders (9.5), users (9.6), consultations (9.7), reviews (9.8), reports (9.9), themes (10.4), monitoring (13); recorded in [`rubric-audit.md`](rubric-audit.md) |
| 10b | At least 1 external CSS file | 2 | `assets/css/main.css` (plus admin/print/theme CSS as extras) | View source shows external stylesheet link(s) | 1.5 | Complete — `main.css` linked from shared header |
| 10c | At least 1 external JavaScript file | 2 | `assets/js/main.js` (plus builder/cart/validation/charts/map as extras) | View source shows external script link(s); no console errors on core pages | 1.6, 15.3 | Complete — `main.js` linked from shared footer; 15.3 console pass ([`js-validation.md`](js-validation.md)) |
| 10d | At least 20 copyright-free images | 4 | `assets/images/` (≥ 20 files); credits in `docs/media-credits.md` | Images load; filenames meaningful; alt text present; licences documented | 8.1, 8.7 | Complete — 33 images under `assets/images/` (20 product + 13 site extras); meaningful filenames; alt text via `customcore_image_url` wiring; licences and prompts in `docs/media-credits.md` + `docs/image-prompts.md` (8.7) |
| 10e | At least 3 video or audio files | 4 | `assets/media/` (≥ 3 items); Learning Centre `media.php` | All three play with browser controls; documented in credits | 8.2–8.3, 8.7 | Complete — 2× MP4 + 1× MP3 with native controls, WebVTT captions, and transcripts on `media.php` (8.2–8.3); sources/licences documented in `docs/media-credits.md` (8.7) |
| 10f | Instructions so a non-programmer can update contents (products/images/video/audio) | 2 | `docs/content-update-guide.md`; referenced from admin guide + README | Non-programmer can follow steps to change catalogue/media without editing core logic | 12.3 | Complete — `docs/content-update-guide.md` (12.3): products/prices/stock/images/options are all changed through the admin website (no code); store details/branding via labelled values in `config/app.php`; adding a Learning Centre video/audio is a copy-files + paste-one-block task in `includes/media.php`; explicit "what NOT to edit" section |
| 11 | Website available online live (preferably `myweb.cs.uwindsor.ca`) | 2 | Production URL recorded in README; production requirements + safe config templates + deployment docs in `docs/` / `config/` | Homepage loads publicly without PHP fatals; core flows work on host | 16.3–16.5 | Complete — live at [https://vucaka.myweb.cs.uwindsor.ca/customcore/](https://vucaka.myweb.cs.uwindsor.ca/customcore/) (subfolder deploy on myweb); CSS/JS paths fixed; MySQL seeded; admin confirmed; README holds the public URL plus demonstration instructions (16.5). Deeper live E2E records remain optional in 16.6–16.7 |
| 12 | Advanced appropriate CSS (fonts, menus, boxes/cards, transitions, layouts) | 4 | Base CSS + three themes demonstrating typography, nav, cards, transitions, grids, form states | Visual review on desktop and mobile across themes | 1.5, 10.x, 14.5 | Complete — base foundation in `main.css` (1.5); three themes add distinct type/nav/buttons/cards/radius/motion (10.1–10.3); cross-theme walk of public + admin pages recorded in `docs/theme-testing.md` (10.6); advanced interaction pass adds `:focus-within` card elevation, an animated mobile-menu reveal, focus-driven form-label states, and a hover-capability-gated card lift (14.5) |
| 13 | SEO-friendly meta: icon, title, description, keywords, etc. | 4 | Per-page metadata in layout/header; favicon; `sitemap.xml`; `robots.txt`; semantic HTML | Important public pages have unique title/description; private/admin URLs excluded from sitemap | 14.1–14.3 | Complete — per-page unique title/description/keywords; SVG favicon + manifest + theme-color; subfolder-safe canonical/`og:url`; auto-noindex for admin + private pages (14.1). Public-only `sitemap.xml` + `robots.txt` (+ live `sitemap.php` / `includes/seo.php`) exclude admin, APIs, uploads, internals, and private customer/action scripts; verified well-formed with no private locs (14.2). Semantic landmarks (`header`/`nav`/`main`/`footer` + `section`/`article`) and valid heading hierarchy audited site-wide, `admin/reports.php` `h1→h3` skip fixed (14.3) |

**Section A subtotal: 100 points**

Point check: 2+4+12+4+8+20+8+10+4+4+2+2+4+4+2+2+4+4 = **100**.

---

## Section B — Supporting course criteria (required by instructions; tracked for completeness)

These appear in the project instructions and package requirements. They support a full mark but are not listed as separate additive points beyond the 100-point table.

| ID | Criterion | Planned evidence | Target stage | Status |
| -- | --------- | ---------------- | ------------ | ------ |
| B1 | HTML5, CSS, JavaScript front end with full interactive functionality | Entire public/customer UI | 1–8 | Planned |
| B2 | Multimedia: images, video/audio, interactive map, interactive menus, data visualization/graphs | `media.php`, `store-locations.php`, nav, Chart.js (or equivalent) on public + admin reports + builder chart | 8.x, 5.8, 9.9 | In progress — public multimedia complete: images (8.1) + playable media & Learning Centre (8.2–8.3) + Leaflet/OSM map (8.4) + catalogue chart from MySQL (8.5) + accessible fallbacks (8.6) + media credits (8.7); builder performance chart (5.8); admin report charts from MySQL with accessible tables (9.9) |
| B3 | Minimum 20 unique dynamic pages and minimum 5 static pages | Dynamic PHP set (48 planned) + static Help wiki (7 pages) — `docs/sitemap.md` | Throughout; Help in 11.x | Complete for static Help — 7 Help/training HTML pages live (11.1–11.7); dynamic page count remains tracked under #10a |
| B4 | Public and private areas (registration, authentication, user profile) | `register.php`, `login.php`, `profile.php`, `edit-profile.php`, auth includes | 4.x, 15.6 | Complete — public catalogue vs login-gated cart/checkout/orders/profile/wishlist/saved-builds/consultation; registration, login/logout, session hardening, and profile edit + password change all verified end-to-end in 15.6 ([`customer-workflows.md`](customer-workflows.md), 41/41 assertions) |
| B5 | Front-end documentation | `docs/frontend-documentation.md` | 12.1 | Complete — `docs/frontend-documentation.md` (12.1) documents the shared HTML shell, the `--cc-*` token/theme system (`main.css`/`admin.css`/`assets/themes/*`), the vanilla-JS modules (builder, cart, checkout, map, charts, help-hub), the 900px responsive nav toggle, and the `includes/theme.php` resolver — all pointing at real files |
| B6 | End-user documentation; interactive training or step-by-step guide | Help wiki + training walkthrough | 11.x | Complete — six-guide Help wiki plus `help/training.html` (11.6), a numbered step-by-step walkthrough (account → shop or build → order → review) linking into the live site and each guide; hub search in `help-hub.js` |
| B7 | Admin: edit data records (products/services/options) | `admin/products.php`, `product-add.php`, `product-edit.php`, `product-options.php`, `admin/compatibility.php`, `admin/orders.php`, `admin/order-details.php` | 9.2–9.5, 15.7 | Done — products CRUD (9.2), product options CRUD (9.3), compatibility metadata (9.4), and order management (9.5: search/filter/paginate, ENUM-validated status changes, and admin notes); all re-verified end-to-end in 15.7 ([`admin-workflows.md`](admin-workflows.md), 51/51 with DB-row confirmation) |
| B8 | Admin: user account administration (e.g. disable accounts) | `admin/users.php`, `admin/user-edit.php` | 9.6, 15.7 | Done — search by name/email + role/status filters + pagination; enable/disable logins and Customer↔Administrator role changes; `includes/admin-users.php` enforces no self-lockout and protects the last active admin; CSRF + PRG; verified over HTTP e2e including the self-lockout guard in 15.7 ([`admin-workflows.md`](admin-workflows.md)) |
| B9 | Admin user documentation | `docs/administrator-guide.md` | 12.2 | Complete — `docs/administrator-guide.md` (12.2) covers gaining admin access (`create-admin.php` + promotion), the live dashboard, and every shipped tool (products, options, compatibility, orders, users, consultations, reviews, reports, themes) with actions and safety rules |
| B10 | Backend monitoring page (online / warning / offline for site and feature services) | `admin/monitoring.php` + health checks in `includes/monitoring.php` + `docs/monitoring-troubleshooting.md` | 13.x | Complete — health-check engine (13.1) + admin dashboard with online/warning/offline table (13.2) + live statistics (13.3) + production-safe error hardening (13.4) + monitoring troubleshooting guide (13.5: documents the dashboard and a symptom-driven fix reference for all seven checks, the live-stats panel, and safe-messaging behaviour, with a verified CLI snippet). complete |
| B11 | Database with at least 20 records | Seeded `products` (and related tables) | 2.x | Complete — 20 products (+ options, components, rules, themes); verified via import guide queries |
| B12 | PHP functionality for dynamic pages | All catalogue/account/admin PHP | 3–9 | Planned |
| B13 | Software repository (e.g. GitHub) with code history | GitHub remote on `main`; meaningful commits | 0.1 ongoing | In progress |
| B14 | Installation documentation for another server | `docs/production-requirements.md`, `docs/production-configuration.md`, `docs/installation-guide.md`, `docs/deployment-troubleshooting.md`, `config/*example*` | 12.4–12.5, 16.1–16.5 | Complete — host requirements, secret-free config templates, install/deploy guides, and a confirmed myweb deployment with the live URL documented in the project README |
| B15 | Desktop and mobile responsiveness (at least one desktop and one mobile) | Responsive CSS; test checklists in `docs/` | 1.5, 15.4–15.5 | **Complete** — desktop verified (15.4, [`docs/responsiveness-desktop.md`](responsiveness-desktop.md): public + customer + admin at 1024/1280/1440/1920, 0 unintended overflow, container caps/centres, 0 defects) and mobile verified (15.5, [`docs/responsiveness-mobile.md`](responsiveness-mobile.md): same 40 page states at 320/360/390/414/700/768, 40/40 Pass, 9 avoidable overflow defects found and fixed) |

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
| `docs/production-requirements.md` | B14, #11 (host readiness) |
| `docs/production-configuration.md` | B14, #11 (secrets stay out of Git) |
| `docs/deployment-troubleshooting.md` | B14, #11 |

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
