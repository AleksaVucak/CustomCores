# CustomCore

CustomCore is a database-driven custom gaming PC store and PC-building website.
Customers can browse configurable prebuilt systems, use a guided custom builder
with compatibility feedback, manage accounts and saved builds, and complete a
simulated checkout. Administrators manage catalogue data, orders, reviews,
consultations, themes, reports, and site monitoring.

This repository is a university web-development project intended for deployment
on standard shared PHP/MySQL hosting (for example `myweb.cs.uwindsor.ca`).

## Technology stack

- HTML5
- External CSS (including three switchable site themes)
- Vanilla JavaScript
- PHP with sessions
- MySQL via PDO prepared statements
- Git / GitHub

No React, Vue, Angular, Node.js, Laravel, Docker, Composer, or URL rewriting is
required. The application uses ordinary `.php` URLs for hosting compatibility.

## Documentation

- [Business case and project objectives](docs/business-case.md)
- [Rubric compliance checklist](docs/rubric-checklist.md)
- [Application sitemap](docs/sitemap.md)
- [Desktop and mobile wireframes](docs/wireframes.md)
- [Database entity-relationship design](docs/database-design.md)
- [Database import, verification, and backup](docs/database-import.md)
- [Application directory structure](docs/directory-structure.md)
- [Front-end architecture documentation](docs/frontend-documentation.md)
- [Administrator user guide](docs/administrator-guide.md)
- [Content update guide (non-programmers)](docs/content-update-guide.md)
- [Complete installation guide](docs/installation-guide.md)
- [Deployment and troubleshooting guide](docs/deployment-troubleshooting.md)
- [Help context-link audit](docs/help-context-links.md)
- [Flash message usage](docs/flash-messages.md)
- [Multimedia credits and licences](docs/media-credits.md)
- [Stage 8 image prompt record](docs/image-prompts.md)
- [Theme testing record](docs/theme-testing.md)

### Quick start

New to the project? Start with the **[installation guide](docs/installation-guide.md)** to get
running from a clean checkout, then the **[administrator guide](docs/administrator-guide.md)**
to manage the store. Non-programmers updating catalogue content should read the
**[content update guide](docs/content-update-guide.md)**.

## Current status

**Commit 13.3 complete** — monitoring statistics.

`customcore_monitoring_stats()` in `includes/monitoring.php` adds live counts for
**products, users, orders, consultation requests, images, and stock** to the monitoring
dashboard. Database totals **reuse `customcore_admin_dashboard_stats()`** so they never
diverge from the admin home screen (verified: products 20/20, users, orders, consultations,
low stock 4, pending reviews — all match). Product and site image counts are read from
disk (`assets/images/products`, `uploads/products`, other image folders) so they remain
available even when MySQL is offline; Learning Centre media availability is included as
supporting context. The stats panel on `admin/monitoring.php` is loaded **separately**
from the health-check table: a DB failure shows a safe warning flash and still displays
filesystem image/media counts without blanking the online/warning/offline status table.
Safe production error messaging for monitoring is hardened further in **Commit 13.4**.

**Commit 13.2 complete** — administrator monitoring dashboard.

`admin/monitoring.php` renders the Stage 13 health-check report (from
`customcore_monitoring_run()`) as an **online / warning / offline** status table behind
`customcore_require_admin()`. It shows an overall status banner with per-status counts
and a check timestamp, a per-service table (service · status badge · summary + safe
details), and a legend explaining each status. Because the engine runs each check in
isolation and never throws — and the admin guard uses session state only — the page
**loads and displays every other service even when one check fails** (verified with the
database offline: the DB row shows Offline while all other rows still render). Status
badges reuse the shared `.admin-badge--ok/--warn/--danger` styles via
`customcore_monitoring_status_badge_class()`; scoped `.monitor-*` styles were added to
`admin.css`. The admin nav and dashboard tool card now light up the Monitoring link
automatically (the tool registry detects the page file). Live statistics land in
**Commit 13.3**.

**Commit 13.1 complete** — application health checks (monitoring engine).

`includes/monitoring.php` is the Stage 13 health-check engine. It runs seven
independent, self-contained checks — **PHP runtime** (version + PDO/fileinfo/session
extensions), **database** (PDO connect + `SELECT 1`), **sessions** (support + writable
store), **core files** (critical includes/config/assets present), **upload storage**
(`uploads/products` + `uploads/consultation` exist and writable), **site theme**
(active stylesheet resolves via `includes/theme.php`), and **Learning Centre media**
(declared catalogue vs. files on disk) — each returning a controlled
`online` / `warning` / `offline` status with a production-safe message (database
errors reuse `customcore_database_error_message()`; no credentials, paths, or stack
traces are exposed). `customcore_monitoring_run()` aggregates them into an overall
status and never throws, so one failing dependency only downgrades its own row. To
support the media check without duplicating data, `includes/media.php` now exposes the
declared lesson list via `customcore_media_catalogue()` (behaviour of
`customcore_media_items()` is unchanged). The administrator dashboard that renders
this report arrives in **Commit 13.2** (`admin/monitoring.php`).

**Stage 12 complete (Commits 12.1–12.6)** — technical, administrator, and installation documentation.

The project documentation set is now complete. Five new guides were added and the
README/structure/rubric docs updated:

- [`docs/frontend-documentation.md`](docs/frontend-documentation.md) (12.1) — the shared
  HTML shell (`header`/`navigation`/`footer`), the `--cc-*` token/theme system
  (`main.css` / `admin.css` / `assets/themes/*`), the vanilla-JS modules (builder, cart,
  checkout, map, charts, help-hub), the 900px responsive nav toggle, and how the active
  theme resolves (`includes/theme.php`).
- [`docs/administrator-guide.md`](docs/administrator-guide.md) (12.2) — task-oriented guide
  to every admin tool (dashboard, products, options, compatibility, orders, users,
  consultations, reviews, reports, themes) with its safety rules.
- [`docs/content-update-guide.md`](docs/content-update-guide.md) (12.3) — non-programmer
  instructions for products, images, options, store details, and adding Learning Centre
  media (rubric #10f).
- [`docs/installation-guide.md`](docs/installation-guide.md) (12.4) — clean-checkout install
  (requirements → code → config → database → admin → uploads → run → verify).
- [`docs/deployment-troubleshooting.md`](docs/deployment-troubleshooting.md) (12.5) —
  shared-hosting deployment, HTTPS/session behaviour, a troubleshooting table, and
  backup/rollback.

All five guides point at real files and describe the single, actual architecture. Next:
**Stage 13** — backend monitoring page.

**Commit 11.7 complete** — context-sensitive Help links audited and polished.

Every customer feature page now opens the **matching** Help article (with a
section anchor where useful), not only the Help hub. Auth pages deep-link into
`accounts.html`; catalogue/search/compare/product/wishlist into `catalogue.html`;
builder/results/saved builds into `pc-builder.html`; cart/checkout/orders into
`orders.html`; consultation/reviews/contact/store-locations into `support.html`.
Homepage, About, and Learning Centre keep the hub as an entry point and also
link the training walkthrough. Main nav and footer still open the hub. Audit
map and completion evidence live in `docs/help-context-links.md`.

**Commit 11.6 complete** — End-user training walkthrough.

`help/training.html` is a numbered, beginner-friendly walkthrough matching the
shared Help shell (skip link, header nav, TOC, footer). It guides a new user from
creating an account, through shopping the catalogue or building a custom PC, to
placing an order and reviewing the first purchase — each step links into the live
site and to the matching detailed guide. Anchors (`#start`, `#account`, `#shop`,
`#build`, `#order`, `#review`, `#help`) back the hub deep-links, and `about.php`
now links directly to the walkthrough. This completes the six-guide Help wiki
(accounts, catalogue, PC Builder, orders, support, training) plus the hub, so
rubric #7 is satisfied.

**Commit 11.5 complete** — Consultation & support Help page.

`help/support.html` is a full consultation-and-support guide matching the shared
Help shell (skip link, header nav, TOC, footer). It covers requesting a PC
consultation, attaching files, tracking requests and responses, the four
consultation statuses, writing product reviews, and the contact form — with copy
and rules (login-gated consultations/reviews, required fields and length limits,
PDF/TXT/PNG/JPG/WEBP attachments up to 5 files at ~2 MB each with real-MIME
validation, guest-friendly contact, reviews pending-until-approved) matched to
the live pages. Anchors (`#consultation`, `#attachments`, `#history`,
`#responses`, `#reviews`, `#contact`) back the hub deep-links and the
context-help links on `consultation.php`, `consultation-history.php`,
`reviews.php`, and `contact.php`.

**Commit 11.4 complete** — Cart & orders Help page.

`help/orders.html` is a full cart-and-orders guide matching the shared Help shell
(skip link, header nav, TOC, footer). It covers the cart, updating quantities and
removing items, checkout, the four simulated payment methods, order confirmation
numbers (`CC-YYYYMMDD-XXXXXX`), order history with its status filter, order
details, and the five order statuses — with copy and rules (login required,
quantity 1–99 clamped to stock, required checkout fields and lengths, trusted
server-side price snapshot, no real payment data) matched to the live pages.
Anchors (`#cart`, `#quantities`, `#checkout`, `#payment`, `#confirmation`,
`#history`, `#details`, `#status`) back the hub deep-links and the context-help
links on `cart.php`, `checkout.php`, `order-confirmation.php`, `order-history.php`,
and `order-details.php`.

**Commit 11.3 complete** — Catalogue & products Help page.

`help/catalogue.html` is a full catalogue guide matching the shared Help shell
(skip link, header nav, TOC, footer). It covers browsing and tiers, searching,
filtering and sorting, the product page, configuration options and pricing,
comparing 2–4 systems, wishlists, and reviews — with copy and rules (four tiers,
$50 price steps, up-to-50 search results, 2–4 compare range, options price
deltas, review rating/title/20-char body, pending-until-approved) matched to the
live pages. Anchors (`#browse`, `#search`, `#filters`, `#sort`, `#product`,
`#options`, `#compare`, `#wishlist`, `#reviews`) back the hub deep-links and the
context-help links on `catalogue.php`, `product.php`, `search.php`,
`compare.php`, and `wishlist.php` (now pointed at `#wishlist`).

**Commit 11.2 complete** — Accounts & profile Help page.

`help/accounts.html` is a full account guide matching the shared Help shell
(skip link, header nav, TOC, footer). It walks through creating an account,
logging in and out, the profile dashboard, editing details, changing your
password, session timeouts, and disabled accounts — with copy and validation
rules (name/email/phone/password limits, 30-minute idle / 12-hour absolute
session timeouts, generic login errors) matched to the live pages. Anchors
(`#register`, `#login`, `#logout`, `#profile`, `#edit-details`, `#password`,
`#sessions`, `#disabled`) back the hub deep-links and the context-help links on
`register.php`, `login.php`, `profile.php`, and `edit-profile.php`. A themed
`.help-note` callout was added to `main.css`.

**Commit 11.1 complete** — Help centre homepage.

`help/index.html` is now a full Help hub: six guide cards (Accounts, Catalogue,
PC Builder, Cart & orders, Consultation & support, End-user training) with jump
TOC anchors, section deep-links, and related live-site links. A progressive
search filter (`assets/js/help-hub.js`) narrows cards by title, body, and
keywords without hiding content when JavaScript is off. Shared Help chrome
matches `pc-builder.html` (skip link, header nav, footer). Hub styles in
`main.css` add the search panel and a two-column card grid on wider viewports.
The existing PC Builder guide remains linked and live; remaining article files
arrive in Commits 11.2–11.6 and 11.8.

**Commit 10.6 complete** — cross-theme verification (Stage 10 finished).

Key public, account, and admin pages were walked under all three themes
(RGB Gaming, Minimal Professional, Cyber Grid). Every check asserted HTTP 200,
correct stylesheet order (`main.css` → optional `admin.css` → theme), structural
chrome (header/nav/footer/forms/tables/cards), and no PHP error leaks.
**78 / 78 checks passed**; themes remain distinct in background, accent,
display font, and radius. No theme-only layout bugs turned up. Results are
recorded in `docs/theme-testing.md`. Active theme restored to RGB Gaming.

**Commit 10.5 complete** — safe theme fallback hardening.

`includes/theme.php` now resolves the active stylesheet through a five-step,
defence-in-depth chain: (1) `site_settings.active_theme_id → themes.css_file`,
(2) `themes.is_active_default = 1`, (3) `config/app.php → default_theme`,
(4) a hard-coded canonical `rgb-gaming` slug (independent of DB and config), and
(5) a last-resort scan of `assets/themes/*.css`. Every candidate — from any
source — is validated by `customcore_theme_normalise_path()` (only
`^assets/themes/<slug>.css` is accepted, blocking `../` traversal, absolute
paths, subdirectories, query strings, and non-CSS files) and must exist on disk
before it is linked, so a missing/renamed/corrupt value transparently falls
through. Database access stays wrapped in try/catch, and because `main.css` is
always linked first the site is never left unstyled. Verified with 33 automated
assertions (path traversal rejection, missing/invalid ids, corrupt paths, empty
tables, corrupt config → canonical) plus HTTP checks confirming a bad
`active_theme_id` still yields RGB Gaming and a traversal `css_file` never leaks
`config/app.php`.

**Commit 10.4 complete** — administrator theme switching.

`admin/themes.php` lists the three seeded site themes (RGB Gaming, Minimal
Professional, Cyber Grid) and lets an administrator activate one sitewide.
Selection is written to `site_settings.active_theme_id` with CSRF protection and
Post/Redirect/Get. Helpers in `includes/admin-themes.php` validate the chosen
`themes.id`, confirm the stylesheet path is safe and present on disk, then
insert/update the setting. The shared header already resolves that value via
`includes/theme.php` (10.1), so public and admin pages load the new CSS
immediately. Themes with a missing CSS file cannot be activated. Guests are
blocked by `customcore_require_admin()`. Verified over HTTP: login → activate
Minimal Professional / Cyber Grid / RGB Gaming → homepage stylesheet updates;
bad CSRF and invalid theme ids rejected; setting restored to RGB Gaming.

**Commit 10.3 complete** — the **Cyber Grid** site theme (all three templates shipped).

`assets/themes/cyber-grid.css` is the third switchable theme and a technical
"HUD / holo-terminal" look, distinct from the other two by design: a visible
blueprint **grid backdrop**, angular sci-fi **Orbitron** display + **Chakra
Petch** body + **Share Tech Mono** labels, **zero-radius** square edges,
**corner-cut** buttons, uppercase monospaced nav, and a mint-green primary with
an animated mint→magenta accent rail. Like the others it re-declares the shared
`--cc-*` tokens (public + admin re-skin automatically) and refines the
hard-coded decorative spots (body, header, hero, cards, flash banners, footer);
motion respects `prefers-reduced-motion`. No PHP changes were needed — it uses
the `includes/theme.php` resolver + shared-header wiring from 10.1. With this
commit the three required distinct templates (RGB Gaming, Minimal Professional,
Cyber Grid) all exist and differ in colour, typography, borders, radius, nav,
buttons, cards, and layout feel. Verified in-browser across public pages and
forms.

**Commit 10.2 complete** — the **Minimal Professional** site theme.

`assets/themes/minimal-pro.css` is the second switchable theme and a deliberate
counterpoint to RGB Gaming: a calm, editorial, light "ink-on-paper" look. It
re-declares the shared `--cc-*` tokens (so public and admin components re-skin
automatically) and differs from the base and RGB themes in more than colour —
an editorial **Fraunces** serif display paired with the humanist **Manrope**
sans, hairline borders, crisper corners, flatter surfaces (elevation from thin
borders rather than heavy shadow), a single professional blue accent, and a
letter-spaced uppercase masthead nav. Solid ink-blue buttons and clean outline
variants replace RGB's neon gradients. It plugs into the same
`includes/theme.php` resolver and shared-header wiring from 10.1, so no PHP
changes were needed. Verified over HTTP + in-browser across public pages and
forms; the stylesheet loads last so it overrides `main.css`/`admin.css`.

**Commit 10.1 complete** — the **RGB Gaming** site theme.

`assets/themes/rgb-gaming.css` is a bold, dark "battlestation" theme layered on
top of `assets/css/main.css`. It re-declares the shared design tokens (the
`--cc-*` CSS custom properties) so every token-driven component re-skins
automatically, then targets the few places `main.css`/`admin.css` use
hard-coded light colours (body/header backdrops, hero, flash banners, footer,
and white-on-accent text). The palette is a high-contrast near-black surface
with a cyan primary accent, an electric-blue focus ring, and a multi-hue RGB
gradient reserved for expressive flourishes (gradient wordmark, animated header
rail, active-nav glow). The active theme is resolved by `includes/theme.php`,
which reads `site_settings.active_theme_id → themes.css_file` from MySQL and
falls back safely to the seeded default theme and then to the
`config/app.php` → `default_theme` slug when the database is unavailable; every
candidate is path-validated and must exist on disk before it is linked. The
shared header links the theme **last** so it overrides both public and admin
surfaces, and all motion respects `prefers-reduced-motion`. Verified over HTTP
that public and admin pages render with the correct stylesheet order
(`main.css` → `admin.css` → theme).

**Commit 9.9 complete** — administrator reports (Stage 9 finished).

`admin/reports.php` charts live MySQL aggregates for **orders by status**, **active
products by performance tier**, **user accounts by role and by status**, and
**inventory health** (healthy / low / out of stock / disabled). Each chart embeds a
Chart.js payload in a `data-admin-report-chart` attribute and is drawn by
`assets/js/admin-reports.js` (Chart.js 4.4.1 loads only on this page via
`$loadAdminReports`). A server-rendered accessible data table sits beside every
canvas and remains the source of truth if JavaScript fails. Logic lives in
`includes/admin-reports.php` — prepared/parameterless PDO queries only; verified
that product-tier and inventory bucket totals match the live catalogue. KPI summary
cards sit at the top. Non-admins are blocked from the page.

**Commit 9.8 complete** — administrator review moderation.

`admin/reviews.php` is the moderation queue for every product review. Search by
title, body, product, or customer; filter by pending / approved / hidden (with live
counts); pending reviews sort to the top. Per-review cards show the full body and
offer CSRF-protected Approve, Hide, Mark pending, and Delete actions via
Post/Redirect/Get. Logic lives in `includes/admin-reviews.php` (prepared statements;
ENUM-validated status writes; intentional hard delete for moderation). Public
catalogue and product pages continue to show only `status = 'approved'` — verified
over HTTP that approving makes a review appear on `product.php` and hiding removes
it again. Non-admins are blocked; CSRF-less deletes are rejected. Dashboard pending
reviews now deep-link into the queue.

**Commit 9.7 complete** — administrator consultation management.

`admin/consultations.php` is a searchable, status-filterable, paginated queue of
every PC advice request (search by customer name, email, or budget; live per-status
counts) that surfaces open and in-progress requests first.
`admin/consultation-details.php` shows the full request — customer + account status,
budget, games/software/performance goals/notes, and every attachment — and lets an
admin change the status and write or clear a response. Saving a non-empty response
timestamps it and auto-advances an open/in-progress request to “Answered” (visible to
the customer in their consultation history). `admin/consultation-attachment.php`
streams any customer's uploads to staff, reusing the customer endpoint's hardening
(admin-only, basename-guarded stored name, path confined to the upload directory,
`nosniff`, sanitized RFC 5987 filename). Logic lives in
`includes/admin-consultations.php` (prepared statements throughout; ENUM-validated
status writes), and both detail writes are behind CSRF + Post/Redirect/Get. Verified
against live MySQL and over HTTP end-to-end (list, search, status filter/empty-state,
detail, attachment download byte-for-byte, response auto-advance, status change,
CSRF-less POST rejected, and non-admins blocked from both the queue and downloads).

**Commit 9.6 complete** — administrator user management.

`admin/users.php` is a searchable, role- and status-filterable, paginated account
index (search by name or email; live role/status counts) with a one-click
enable/disable toggle. `admin/user-edit.php` shows a full account — profile,
activity summary (orders, lifetime spend, reviews, consultations, wishlist), and
recent orders — and lets an admin enable/disable the login and change the role
(Customer ↔ Administrator). Logic lives in `includes/admin-users.php`: prepared
statements throughout, the password hash is never loaded into an admin view, role
writes are validated against the `users.role` ENUM, and `customcore_admin_user_guard()`
enforces two critical invariants — **an admin can never disable or demote their own
account** (no self-lockout) and **the last active administrator can never be disabled
or demoted**. Every write is behind CSRF + Post/Redirect/Get with flash
confirmations. Verified against live MySQL and over HTTP end-to-end (list, search,
role filter, disable/enable, promote, and rejection of self-disable, self-demote, and
CSRF-less POSTs), with all accounts restored afterwards.

**Commit 9.5 complete** — administrator order management.

`admin/orders.php` is a searchable, status-filterable, paginated index of every
customer order: search by order number, customer name, or email; filter by status
(with live per-status counts); and page through results 25 at a time.
`admin/order-details.php` shows the full order — customer, account status, shipping
snapshot, payment-method label, and every frozen line item (with decoded product
options and custom-build components) plus totals — and lets an admin change the
fulfilment status and record internal administrator notes (never shown to the
customer, stored `NULL` when blank). Logic lives in `includes/admin-orders.php`:
prepared-statement search/list with pagination, admin-scope fetch of any order and
its items, status writes validated against the `orders.status` ENUM allow-list, and
notes writes. Both writes are behind CSRF + Post/Redirect/Get with flash
confirmations. Verified end-to-end against live MySQL and over HTTP (admin login →
list → search → empty-state → detail → status change persists with success flash →
notes save → a CSRF-less status POST is rejected and leaves the order unchanged),
with the seeded data restored afterwards.

**Commit 9.4 complete** — administrator compatibility metadata management.

`admin/compatibility.php` lets an admin edit the simplified compatibility metadata
the PC&nbsp;Builder relies on, in two panels. **Component attributes**: each builder
part is edited through a form that shows only the fields relevant to its category
(e.g. CPU → socket/power/scores, Case → form factor/GPU & cooler clearance/supported
cooling), with per-type validation and an enable/disable toggle that controls
whether the part appears in the builder. **Compatibility rules**: the seven seeded
checks can be renamed, re-described, switched between error/warning severity, and
enabled/disabled; each rule's JSON wiring is shown read-only so the evaluator stays
intact. Logic lives in `includes/admin-compatibility.php`, which writes only a fixed
allow-list of columns (user input never names a column) via prepared statements, all
behind CSRF + Post/Redirect/Get. Verified end-to-end against live MySQL that edits
persist, non-edited columns (name/price) are untouched, and the builder's checker
correctly reports a socket mismatch as incompatible afterward.

> Setup note: this database's `compatibility_rules` table was empty, so the builder
> was running zero checks. The canonical seven rules from
> `database/seed-compatibility.sql` (idempotent; touches only that table) have been
> (re)imported so the builder and this admin page work as intended.

**Commit 9.3 complete** — administrator product options management.

`admin/product-options.php` lets an admin manage the configurable choices buyers
pick on the product and PC&nbsp;Builder pages (RAM, Storage, Colour, Warranty, …).
Pick a product (or arrive via the new "Options" link on the product list), then add,
edit, reorder, price (positive **or** negative delta), enable/disable, set the
default, and delete options — grouped by option group. Logic lives in
`includes/admin-options.php`, which validates input and enforces the key invariant
that each group keeps **exactly one active default** (auto-promoting a replacement
when a default is disabled, deleted, or moved to another group) so the storefront
and builder always price a valid configuration. Every action uses CSRF + the
Post/Redirect/Get pattern with flash confirmations, and an advisory banner warns
when a product drops below two active options or a group loses its default.
Verified end-to-end against live MySQL (create, set-default, edit with a negative
delta, disable-with-auto-promotion, and delete) with all test rows rolled back.

**Commit 9.2 complete** — administrator product management (catalogue CRUD).

Admins can now add, edit, price, stock, image, feature, and disable catalogue
products from three protected screens — `admin/products.php` (searchable, filterable
list with an enable/disable toggle), `admin/product-add.php`, and
`admin/product-edit.php` — all behind `customcore_require_admin()`. Shared logic
lives in `includes/admin-products.php`: input validation, automatic unique-slug
generation, list/search queries, prepared-statement create/update, soft
enable/disable (never a hard delete, so order and review history stay intact), and
secure image uploads. Uploads never trust the browser: the real MIME type is
detected with `finfo`, matched to a JPG/PNG/WEBP/GIF allowlist, capped at 2 MB, and
saved under `uploads/products/` with a randomly generated filename; replaced or
removed images are cleaned off disk. A shared form partial
(`includes/admin-product-form.php`) keeps the add and edit screens identical, and
`customcore_product_image_url()` lets uploaded images render across the catalogue,
product, search, wishlist, and homepage pages alongside seeded assets. Every write
uses CSRF protection and the Post/Redirect/Get pattern with flash confirmations.
Verified end-to-end against live MySQL: login → create with image upload → edit
(price/stock/category/image replace + remove) → disable, with the seeded catalogue
count restored afterwards.

**Commit 9.1 complete** — administrator dashboard from live MySQL.

`admin/index.php` is now a real operations dashboard (still behind
`customcore_require_admin()`). Counts for products, orders, users, reviews,
consultations, contact inbox, and stock warnings are computed by
`includes/admin.php` from the database — never hard-coded. Attention alerts,
recent activity tables (orders, pending reviews, open consultations, low stock),
and a Stage 9–13 tool registry are included. Unavailable tools stay unlinked
(“Coming in commit …”) so the nav never 404s; later commits light up
automatically when their PHP files land. Shared chrome: `includes/admin-nav.php`
and `assets/css/admin.css` (loaded via `$loadAdminCss`). Verified: guests are
redirected to login; an administrator session renders live counts (e.g. pending
reviews and low-stock products from seed data).

**Commit 8.7 complete** — multimedia credits (Stage 8 finished).

`docs/media-credits.md` records the origin, licence, and creation date for every
Stage 8 asset: all 33 images (20 product + 13 site extras), both educational
MP4s, the MP3 audio guide, three WebVTT caption tracks, Chart.js 4.4.1 (MIT),
and Leaflet 1.9.4 + OpenStreetMap tiles. Full AI image prompts are retained in
`docs/image-prompts.md`. Credits are linked from the README, the Learning Centre,
the accessibility statement, and the store-locations map note. Rubric #10d and
#10e licence documentation is now complete.

**Commit 8.6 complete** — accessible multimedia fallbacks.

Every piece of Stage 8 multimedia now has a documented text equivalent, so no
information is locked inside media. A new public `accessibility.php` statement
(linked from the footer) explains each fallback and links to it: descriptive
`alt` text with graceful placeholders for images; native controls + English
captions + expandable transcripts + download links for the video/audio guides;
the live-data table beside the catalogue chart (8.5); the text score summary
beside the builder performance chart (5.8); and the always-visible address,
hours, and `<noscript>` message for the store map (8.4). The homepage video
teaser now links straight to the guide's transcript/captions, and the stylesheet
honours `prefers-reduced-motion`. Verified in a browser: the statement page lists
all six multimedia types with working links and the teaser transcript link
resolves to the correct Learning Centre lesson.

**Commit 8.5 complete** — public catalogue data visualization from live MySQL.

`catalogue.php` opens with a "Catalogue at a glance" section that charts the
number of active products in each performance tier. Counts and price ranges are
computed server-side from the database by `customcore_catalogue_tier_stats()`
(in `includes/catalogue-stats.php`) — never hard-coded — so the graph always
reflects real seeded/administered data. The payload is JSON-encoded into a
`data-catalogue-chart` attribute and drawn by `assets/js/catalogue-chart.js`
using Chart.js (loaded from CDN only on this page via `$loadCatalogueChart`). An
accessible data table listing each tier's count and price range is rendered
server-side beside the canvas and remains the source of truth if Chart.js fails
to load. A separate `try/catch` keeps a stats failure from blanking the product
grid. Verified in a real browser: four bars (Budget/Esports/High-Performance/
Creator, 5 each) render and the table mirrors the same figures.

**Commit 8.4 complete** — interactive store & service map with text fallback.

`store-locations.php` shows the fictional CustomCore Campus Service Desk. An
always-visible `<address>` block (name, street, city/region/postal, `tel:` and
`mailto:` links), an hours list, and a storefront photo remain fully usable even
if JavaScript, Leaflet, or the map tiles fail. The interactive map is a
progressive enhancement: `assets/js/store-map.js` initialises Leaflet +
OpenStreetMap from `data-*` attributes on the map container (no inline script,
popup built from DOM text nodes), with scroll-wheel zoom enabled only while the
map is focused so keyboard users are never trapped. Location data is centralised
in `config/app.php` (`store_location`) for easy, non-programmer edits. Leaflet's
CSS/JS load only on this page via the shared header/footer. Verified in a real
browser: map tiles + marker render and the text address stays visible.

**Commit 8.3 complete** — multimedia Learning Centre showcase.

`media.php` is now an organized, responsive Learning Centre. A lesson directory
at the top summarises the mix ("3 short lessons — 2 videos and 1 audio guide")
and offers poster-thumbnail cards with type/duration badges that jump to each
full player below. Each lesson plays with native `<video controls>` /
`<audio controls>`, English caption tracks, learning outcomes, and an expandable
transcript. Accessibility refinements: the standalone audio poster now carries a
descriptive `alt`, video posters are treated as decorative, jumped-to lessons get
a visible `:target` highlight and focus handling, and heading levels nest
correctly (h1 → directory/lesson h2/h3 → outcomes h4).

**Commit 8.2 complete** — three educational media items play with native controls
(2× MP4, 1× MP3 under `assets/media/` with WebVTT captions; `customcore_media_url()`
helper; homepage teaser embeds the PC Builder walkthrough).

**Commit 8.1 complete** — copyright-safe imagery integrated site-wide.

**Stage 12 complete (Commits 12.1–12.6).** Front-end, administrator, content-update, installation, and deployment/troubleshooting documentation shipped, and the README/structure/rubric docs updated. Next: **Stage 13** — backend monitoring page (`admin/monitoring.php`).

## Security notes

- Never commit real database credentials.
- The live config file `config/database.php` is ignored by Git.
- Use [`config/database.example.php`](config/database.example.php) as the template
  (see [`config/README.md`](config/README.md)).
- Never commit plain-text passwords or private customer data.

## Licence

See [LICENSE](LICENSE) for terms.
