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
- [Flash message usage](docs/flash-messages.md)
- [Multimedia credits and licences](docs/media-credits.md)
- [Stage 8 image prompt record](docs/image-prompts.md)

## Current status

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

**Stage 9 complete.** Next: **Commit 10.1** — site-wide CSS theme templates.

## Security notes

- Never commit real database credentials.
- The live config file `config/database.php` is ignored by Git.
- Use [`config/database.example.php`](config/database.example.php) as the template
  (see [`config/README.md`](config/README.md)).
- Never commit plain-text passwords or private customer data.

## Licence

See [LICENSE](LICENSE) for terms.
