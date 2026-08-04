# CustomCore | Mobile Responsiveness Verification

**Document type:** Project documentation
**Purpose:** Prove that every core page renders correctly on mobile-class viewports — no unintended horizontal scrolling, no clipped or overlapping content, no broken grids, and usable tap targets — down to a 320 px small-phone width.
**Acceptance:** Core public, customer, and administrator pages are usable at representative mobile widths; any avoidable layout defects found are corrected.
**Related:** Desktop counterpart [`responsiveness-desktop.md`](responsiveness-desktop.md); layout contract in [`wireframes.md`](wireframes.md); token/breakpoint system in [`frontend-documentation.md`](frontend-documentation.md) §3; base rules in [`assets/css/main.css`](../assets/css/main.css) + [`assets/css/admin.css`](../assets/css/admin.css); earlier test records [`html-validation.md`](html-validation.md), [`css-validation.md`](css-validation.md), and [`js-validation.md`](js-validation.md).

### Status legend

| Status | Meaning |
| --- | --- |
| **Pass** | No horizontal document overflow (`documentElement.scrollWidth ≤ innerWidth`), no element spilling past the viewport outside an intended scroll wrapper, layout intact |
| **Cond** | Content wider than the shell is contained in an **intended** horizontal scroll wrapper (e.g. wide admin data tables, comparison table, catalogue stats table) — documented, not a defect |
| **Fail** | Unintended horizontal scrollbar, clipped/overlapping content, or broken grid on a core page |

---

## 1. Scope

### 1.1 Layout system under test

- **Shared shell** — `.site-header__inner`, `.site-main`, `.site-footer__inner` use `width: min(100% - 2rem, var(--cc-width-max))` with `margin-inline: auto`, so below the 1120 px cap the content stays fluid with a 1 rem gutter each side; on phones it fills the width minus that gutter.
- **Viewport meta** — `includes/header.php` emits `<meta name="viewport" content="width=device-width, initial-scale=1.0">`, so pages honour the device width and never zoom out to a desktop canvas.
- **Mobile-first single column** — every multi-column `.layout-split*` grid, the catalogue/account grids, and the admin content grid collapse to a **single column** below their breakpoints (`600 / 640 / 700 / 768 / 800 / 900 px`). At 320 px every one of these mobile `max-width` rules is active.
- **Shrinkable single columns** — single-column grids use `grid-template-columns: minmax(0, 1fr)` (not a bare `1fr`) so a wide child (data table, chart canvas, long email) can shrink/scroll **inside** the column instead of stretching it past the viewport.
- **Wide tables** — public comparison table, catalogue stats table, and admin data tables sit in `overflow-x: auto` wrappers so a wide table scrolls **inside its card** on a phone rather than forcing a whole-page scrollbar (intended `Cond`).
- **Fluid card grids** — catalogue/featured/search grids use `repeat(auto-fit|auto-fill, minmax(min(…, 100%), 1fr))`, so they drop to one card per row on narrow phones.
- **Charts & map are container-bound** — Chart.js canvases are forced to `width/height: 100%` and the Leaflet map clips with `overflow: hidden`, so neither can exceed its card on a phone.

### 1.2 Mobile widths exercised

| Width | Role |
| --- | --- |
| **320 × …** | Smallest supported phone (iPhone SE 1st-gen / small Android) — every page checked here (most stringent; activates all `max-width` mobile rules) |
| **360 × …** | Very common Android width (Galaxy A/S class) |
| **390 × …** | iPhone 12/13/14/15 class |
| **414 × …** | Large phones (iPhone Plus / Max, Pixel XL) |
| **700 × …** | Phablet / small-tablet band (tables leave card-view but the viewport is still narrow) |
| **768 × …** | Tablet portrait — multi-column tablet grids activate |

### 1.3 Surfaces exercised

| Audience | Pages |
| --- | --- |
| Public | Home, catalogue, product (guest + signed-in), builder, compare (4-way), about, store locations (Leaflet map), reviews, contact, search, login, register, media/learning centre, accessibility, `help/index.html` |
| Customer | Profile, edit-profile, saved builds, saved build detail, wishlist, order history, order details, order confirmation, cart, checkout, consultation, consultation history |
| Administrator | Dashboard, products, orders, order details, users, user edit, reviews, reports (Chart.js), monitoring, themes, compatibility, product add, product edit |

---

## 2. Method

1. **Layout audit** — reviewed the container, breakpoint, single-column-shrink, and scroll-wrapper rules above in `main.css` / `admin.css` to establish the intended mobile behaviour and which horizontal scroll wrappers are deliberate.
2. **Automated overflow probe** — the project PHP built-in server (`http://localhost:8000`) was driven in a real Chromium tab. Each page was loaded in an isolated fixed-width iframe (320 / 360 / 390 / 414 / 700 / 768 px) with a cache-busted stylesheet so the correct media queries applied, then a script measured:
 - `documentElement.scrollWidth` vs `innerWidth` (page-level horizontal overflow), and
 - every **leaf** element whose bounding box extends past the viewport **whose ancestors are not** an `overflow-x: auto/scroll` or `overflow: hidden` container — i.e. **real** offenders, filtering out intended scroll wrappers and clipped map tiles.
 - Suspected offenders were then re-measured with a `min-content` clone probe to identify the specific child forcing the width (canvas, table, or unbreakable string) before choosing a fix.
3. **Breakpoint-band coverage** — 320 px exercises every `max-width` mobile rule; 700 px covers the band where tables exit card-view; 768 px activates the tablet multi-column grids. The 360 / 390 / 414 px widths fall inside the same `≤600`/`≤640` single-column regime as 320 px.
4. **Signed-in + admin coverage** — a disposable customer was registered and used to exercise cart, wishlist, saved builds, consultation, order history/details/confirmation, and edit-profile. The same account was briefly promoted to administrator via a temporary DB helper to reach every admin route, then the account, its order, its cart, and the helper file were **deleted** afterward (store returned to its pre-test state: the three original seed customers, zero orders, zero carts).

---

## 3. Results summary

**All core pages: Pass** at 320 / 360 / 390 / 414 / 700 / 768 px after the fixes in §5. No unintended horizontal scrollbars, clipped content, or broken grids remain. The only elements wider than the shell are the comparison table, the catalogue stats table, and the admin data tables, which scroll **inside their own cards** by design (`Cond`), never stretching the page.

| Group | Pages checked | Pass | Cond (intended inner scroll) | Fail |
| --- | --- | --- | --- | --- |
| Public | 15 | 15 | comparison table, catalogue stats table (contained) | 0 |
| Customer | 11 | 11 | — | 0 |
| Administrator | 14 | 14 | wide admin tables (contained) | 0 |

Two apparent `documentElement.scrollWidth` excesses (profile and admin order-details at 320 px, ~13 px) were confirmed to be the **iframe vertical-scrollbar width artifact** on long pages, not real layout overflow: an ancestor-aware leaf scan reported **zero** offenders on both, and real mobile browsers use overlay scrollbars. Likewise, `store-locations.php` map tiles that extend past the viewport are **clipped** by `.leaflet-container { overflow: hidden }` (map box measured at 308 px inside a 320 px viewport) — a false positive, not overflow.

---

## 4. Page-level evidence (primary width 320 px; re-checked 360 / 390 / 414 / 700 / 768)

### 4.1 Public

| Page | Result | Notes |
| --- | --- | --- |
| `index.php` | Pass | hero + featured/tier grids collapse to one column |
| `catalogue.php` | Pass / **Cond** | product grid → one card per row; stats **table** contained in new `.catalogue-chart__table-wrap` (`overflow-x:auto`); chart canvas fluid |
| `product.php` (guest & signed-in) | Pass | header split stacks; options/review form fit |
| `builder.php` | Pass | step nav + summary stack; **performance chart canvas** now fluid (was forcing 300 px min) |
| `compare.php` (4 systems) | Pass / **Cond** | `.compare-table` contained in its `overflow-x:auto` wrapper; no page overflow |
| `about.php` | Pass | feature grid → single column |
| `store-locations.php` | Pass | Leaflet map clips tiles (`overflow:hidden`); details stack below map |
| `reviews.php` | Pass | review list column fluid |
| `contact.php` | Pass | form fields full-width |
| `search.php` | Pass | results grid matches catalogue |
| `login.php` / `register.php` | Pass | form cards fill width |
| `media.php` | Pass | lesson articles + credits stack |
| `accessibility.php` | Pass | content sections fit |
| `help/index.html` | Pass | topic-card grid → single column; filter fits |

### 4.2 Customer (signed-in)

| Page | Result | Notes |
| --- | --- | --- |
| `profile.php` | Pass | account nav + dashboard cards stack; long **email** in details list now wraps (`overflow-wrap: anywhere`) |
| `edit-profile.php` | Pass | details + change-password sections stack |
| `saved-builds.php` / `saved-build.php` | Pass | list + build detail contained |
| `wishlist.php` | Pass | empty-state / items contained |
| `order-history.php` | Pass | history table fits within card |
| `order-details.php` | Pass | shipping/payment/items sections stack |
| `order-confirmation.php` | Pass | summary fits; **totals footer** now uses a responsive 3-col (narrow) / 4-col (wide) row pair — fixes a phantom-column misalignment |
| `cart.php` | Pass | table switches to card-view ≤640; `min-width` reset so it goes fluid; summary stacks |
| `checkout.php` | Pass | shipping form + order summary stack |
| `consultation.php` | Pass | request form + file field fit |
| `consultation-history.php` | Pass | list / empty-state contained |

### 4.3 Administrator

| Page | Result | Notes |
| --- | --- | --- |
| `admin/index.php` | Pass / **Cond** | sidebar collapses; activity grid single-column; four dashboard tables wrapped in `.admin-table-wrap`; long sub-text (emails) wraps |
| `admin/products.php` | Pass / **Cond** | product table scrolls in-card |
| `admin/orders.php` | Pass / **Cond** | filter row stacks; results table scrolls in-card |
| `admin/order-details.php` | Pass / **Cond** | detail lists (`.admin-dl`) shrink + wrap long emails; items table scrolls in-card |
| `admin/users.php` | Pass / **Cond** | user table scrolls in-card |
| `admin/user-edit.php` | Pass | profile + status/role controls stack |
| `admin/reviews.php` | Pass | moderation cards stack |
| `admin/reports.php` | Pass | Chart.js canvases shrink to card (`minmax(0,1fr)` + `minmax(min(280px,100%),1fr)`); data tables scroll in-card |
| `admin/monitoring.php` | Pass / **Cond** | status/live-stat cards stack; monitor table wrapped in `.admin-table-wrap` |
| `admin/themes.php` | Pass | theme cards → single column |
| `admin/compatibility.php` | Pass / **Cond** | rule tables scroll in-card |
| `admin/product-add.php` | Pass | product form fields stack |
| `admin/product-edit.php` | Pass | form stacks; option sub-text wraps |

---

## 5. Defects found and fixed

Each defect below caused **unintended horizontal overflow at a mobile width** and was fixed at the CSS/markup level (no change to page logic). Root cause in every case was an element whose **intrinsic `min-content` width** (a chart canvas, a data table, or an unbreakable email string) exceeded the phone viewport, so the single-column grid stretched instead of shrinking.

| # | Page(s) / width | Symptom | Fix |
| --- | --- | --- | --- |
| 1 | `builder.php` @320 | Performance-chart `<canvas>` kept its 300 px intrinsic width and widened the layout | `assets/css/main.css`: `.perf-chart__canvas-wrap > canvas { width:100% !important; height:100% !important }` so the canvas is truly fluid |
| 2 | `catalogue.php` @320 | Stats table (271 px `min-content`) overflowed | `catalogue.php`: wrapped the table in `.catalogue-chart__table-wrap`; `main.css`: `.catalogue-chart__table-wrap { overflow-x:auto }` and `.catalogue-chart { grid-template-columns: minmax(0,1fr) }` |
| 3 | `cart.php` (account split) @390 | `.data-table` `min-width:36rem` stayed set in card-view, and the split grid’s bare `1fr` let it stretch | `main.css`: `.layout-split { grid-template-columns: minmax(0,1fr) }`; `.cart-table { min-width:0 }` inside `@media (max-width:640px)` |
| 4 | `profile.php` @320 | Long email in `<dd>` would not break, widening the details card | `main.css`: `.profile-details__list dd { overflow-wrap: anywhere }` (`anywhere` also shrinks `min-content`, so the column narrows) |
| 5 | `order-confirmation.php` @320 | Totals `<tfoot>` used `colspan="3"` sized for a 4-col desktop table while a column was hidden on mobile → phantom column / overflow | `order-confirmation.php`: two total rows (`--wide` colspan 3, `--narrow` colspan 2); `main.css`: toggle them with `@media (max-width:768px)` |
| 6 | `admin/index.php`, `admin/product-edit.php` @390 | Activity/product tables and long email sub-text overflowed | `admin.css`: `.admin-table__sub { overflow-wrap: anywhere }` and `.admin-activity__grid { grid-template-columns: minmax(0,1fr) }`; `admin/index.php`: wrapped all four dashboard tables in `.admin-table-wrap` |
| 7 | `admin/order-details.php` @390 | `.admin-dl` detail lists with long customer emails overflowed | `admin.css`: `.admin-dl { grid-template-columns: minmax(0,max-content) minmax(0,1fr) }` and `.admin-dl dd { overflow-wrap: anywhere }` |
| 8 | `admin/monitoring.php` @390 | Monitor checks table overflowed | `admin/monitoring.php`: wrapped `.admin-table--monitor` in `.admin-table-wrap` |
| 9 | `admin/reports.php` @320 | Chart column used a hard `minmax(280px,1fr)` that could not fit at 320 px | `admin.css`: `@media (max-width:800px).admin-report-chart { grid-template-columns: minmax(0,1fr) }` and `.admin-report-users { grid-template-columns: repeat(auto-fit, minmax(min(280px,100%),1fr)) }` |

After these fixes, the automated 320 px sweep reports **OK (0 real offenders)** for every public, customer, and admin page, and the 360 / 390 / 414 / 700 / 768 px sweeps are clean.

---

## 6. Design notes

- **`minmax(0, 1fr)` over bare `1fr`.** A plain `1fr` track keeps an implicit `min-content` minimum, so one wide child can stretch a single mobile column past the screen. Using `minmax(0, 1fr)` lets the track shrink and the child scroll/wrap inside it — the core pattern behind fixes 2, 3, 6, and 9.
- **`overflow-wrap: anywhere` (not `break-word`) for unbreakable values.** `anywhere` also reduces the string’s intrinsic `min-content`, so the containing grid column can narrow below the email’s length — required for fixes 4, 6, and 7.
- **Wide-table scrolling is intentional.** Data tables (admin, cart, catalogue stats) and the comparison table can exceed a phone’s width; they scroll locally inside `overflow-x: auto` cards rather than forcing a whole-page scrollbar. Recorded as `Cond`, not `Fail`.
- **Charts and the map are container-bound.** Chart.js canvases are forced to `100%` (fix 1) and the Leaflet map clips with `overflow: hidden`, so neither overflows on a phone.
- **Scrollbar artifact ≠ overflow.** In the fixed-width iframe harness a long page’s vertical scrollbar inflates `scrollWidth` by ~13 px; this does not occur with mobile overlay scrollbars and was confirmed as a non-defect by the ancestor-aware leaf scan (zero real offenders).

---

## 7. How to re-run

```
# 1. Serve from the project root (connects to MySQL from config/database.php):
php -S localhost:8000

# 2. On a phone-sized viewport (DevTools device toolbar or a real phone) at
# 320 / 360 / 390 / 414 / 768 px, open each page in §4 and confirm there is
# NO horizontal scrollbar on the page itself and no clipped/overlapping
# content. Wide tables (admin, cart, catalogue stats, comparison) may scroll
# INSIDE their card — that is expected.

# 3. Quick overflow check in the browser console on any page:
# (document.documentElement.scrollWidth <= window.innerWidth) // expect true
```

Signed-in and admin pages require a logged-in customer / administrator account; the audit used a disposable account that was deleted afterward.

---

## 8. Sign-off

| Criterion | Result |
| --- | --- |
| Core pages free of unintended horizontal overflow (320–768 px) | **Yes** |
| Single-column collapse + fluid grids verified on phones | **Yes** |
| Wide tables/charts contained in intended scroll wrappers or shrunk to fit | **Yes** |
| Avoidable mobile layout defects corrected | **9 found, 9 fixed** |
| Mobile responsiveness results recorded | **This document** |

**Summary.** Desktop and mobile responsiveness are now both verified, satisfying rubric criterion **B15**.
