# CustomCore — Desktop Responsiveness Verification (Commit 15.4)

**Document type:** Stage 15 verification  
**Purpose:** Prove that every core page renders correctly on desktop-class viewports — no unintended horizontal scrolling, no clipped or overlapping content, and the shared container caps and centres as designed on wide screens.  
**Acceptance:** Core public, customer, and administrator pages are usable at representative desktop widths; any avoidable layout defects found are corrected.  
**Related:** Layout contract in [`wireframes.md`](wireframes.md); token/breakpoint system in [`frontend-documentation.md`](frontend-documentation.md) §3; base rules in [`assets/css/main.css`](../assets/css/main.css) + [`assets/css/admin.css`](../assets/css/admin.css); prior Stage 15 records [`html-validation.md`](html-validation.md), [`css-validation.md`](css-validation.md), and [`js-validation.md`](js-validation.md). Mobile responsiveness is verified separately in Commit 15.5.

### Status legend

| Status | Meaning |
| ------ | ------- |
| **Pass** | No horizontal document overflow (`documentElement.scrollWidth ≤ innerWidth`), no element spilling past the viewport outside an intended scroll wrapper, layout intact |
| **Cond** | Content wider than the shell is contained in an **intended** horizontal scroll wrapper (e.g. wide admin data tables, comparison table) — documented, not a defect |
| **Fail** | Unintended horizontal scrollbar, clipped/overlapping content, or broken grid on a core page |

---

## 1. Scope

### 1.1 Layout system under test

- **Shared shell** — `.site-header__inner`, `.site-main`, `.site-footer__inner` use `width: min(100% - 2rem, var(--cc-width-max))` with `--cc-width-max: 70rem` (1120 px) and `margin-inline: auto`. Above ~1136 px the content caps at 1120 px and centres; below that it stays fluid with a 1 rem gutter each side.
- **Primary breakpoint** — `900px` (matches `docs/wireframes.md`): multi-column `.layout-split*` grids (home, builder, catalogue, account, product header, media teaser, about features) activate at and above desktop widths.
- **Fluid grids** — catalogue/featured/search card grids use `repeat(auto-fit|auto-fill, minmax(…, 1fr))`, so column count scales with available width without overflow.
- **Wide tables** — admin data tables (`.admin-table` in `.admin-table-wrap`) and the public `.compare-table` sit in `overflow-x: auto` wrappers so a wide table scrolls **inside its card** instead of stretching the page (intended `Cond`).
- **Admin area** — `assets/css/admin.css` promotes the admin content to a sidebar-plus-content grid at `min-width: 800px`.

### 1.2 Desktop widths exercised

| Width | Role |
| ----- | ---- |
| **1024 × 768** | Small desktop / tablet-landscape; still in the fluid range (below the 1120 px cap) |
| **1280 × 800** | Primary desktop sweep width (common laptop) — every page checked here |
| **1440 × 900** | Standard desktop; just above the cap (content centres) |
| **1920 × 1080** | Full-HD / wide desktop; verifies the container cap + centring and absence of full-width stretch |

### 1.3 Surfaces exercised

| Audience | Pages |
| -------- | ----- |
| Public | Home, catalogue, product (guest + signed-in), builder, compare (4-way), about, store locations (map), reviews, contact, search, login, register, media/learning centre, accessibility, `help/index.html` |
| Customer | Profile, edit-profile, saved builds, wishlist, order history (empty + populated), order details, order confirmation, cart, checkout, consultation, consultation history |
| Administrator | Dashboard, products, orders, users, reviews, reports (Chart.js), monitoring, themes, compatibility, product add, product edit, product options, order details, user edit |

---

## 2. Method

1. **Layout audit** — reviewed the container, breakpoint, and grid rules above in `main.css` / `admin.css` to establish the intended desktop behaviour and which horizontal scroll wrappers are deliberate.
2. **Automated overflow probe** — the project PHP built-in server (`http://localhost:8000`) was driven in a real Chromium tab. Viewport size was set per width with the DevTools `Emulation.setDeviceMetricsOverride`, then for each page a script measured:
   - `document.scrollWidth` vs `window.innerWidth` (page-level horizontal overflow), and
   - every element whose bounding box extends past the viewport **and** whose ancestors are **not** an `overflow-x: auto/scroll/hidden` scroll container (i.e. real offenders, filtering out intended scroll wrappers).
   - For pages with tables/charts, the wrapper's `overflow-x` and `scrollWidth > clientWidth` were also captured to confirm any wide table is contained.
3. **Wide-screen centring check** — at 1920 px, `.site-main` width and left offset were measured to confirm the 1120 px cap and symmetric gutters (no edge-to-edge stretch).
4. **Signed-in + admin coverage** — a disposable customer was registered and used to exercise cart, checkout, a placed order (populating order history/details/confirmation), wishlist, saved builds, consultation, and edit-profile. The same account was briefly promoted to administrator via a temporary DB helper to reach every admin route, then the account, its order, and the helper files were **deleted** afterward (store returned to its pre-test state).

---

## 3. Results summary

**All core pages: Pass.** No unintended horizontal scrollbars, clipped content, or broken grids were found at 1024, 1280, 1440, or 1920 px. At 1920 px the shell caps at exactly **1120 px** and centres (≈400 px gutter each side); at 1440 px it caps and centres (≈160 px gutter); at 1024/1280 px the fluid shell fills the width with its 1 rem gutter. The only elements wider than the shell are the admin data tables and the public comparison table, which scroll **inside their own cards** by design (`Cond`), never stretching the page.

| Group | Pages checked | Pass | Cond (intended inner scroll) | Fail |
| ----- | ------------- | ---- | ---------------------------- | ---- |
| Public | 15 | 15 | comparison table wrapper (contained) | 0 |
| Customer | 11 | 11 | — | 0 |
| Administrator | 14 | 14 | wide admin tables (contained) | 0 |

---

## 4. Page-level evidence

### 4.1 Public (primary width 1280 px, spot-checked 1024 / 1440 / 1920)

| Page | Result | Notes |
| ---- | ------ | ----- |
| `index.php` | Pass | `layout-split--home`, featured + tier grids reflow cleanly; 1120 px centred at 1920 |
| `catalogue.php` | Pass | auto-fill product grid; filters row wraps; clean at 1024 and 1920 |
| `product.php` (guest & signed-in) | Pass | `product-detail__header` split (1fr/1.4fr) at ≥900; options/review form fit; 1440 centred |
| `builder.php` | Pass | `layout-split--builder` (1.3fr/0.9fr); step nav + summary fit |
| `compare.php` (4 systems) | Pass / **Cond** | `.compare-table` (1068 px) contained in its `overflow-x:auto` wrapper; no page overflow |
| `about.php` | Pass | 3-column feature grid at ≥900 |
| `store-locations.php` | Pass | Leaflet map + text details side-by-side; map sized to container |
| `reviews.php` | Pass | review list column capped |
| `contact.php` | Pass | form column capped |
| `search.php` | Pass | results grid matches catalogue |
| `login.php` / `register.php` | Pass | narrow form cards centred |
| `media.php` | Pass | lesson articles + credits fit |
| `accessibility.php` | Pass | content sections fit |
| `help/index.html` | Pass | topic-card grid + filter fit |

### 4.2 Customer (signed-in, 1280 px)

| Page | Result | Notes |
| ---- | ------ | ----- |
| `profile.php` | Pass | account nav + dashboard cards (`layout-split--account`) |
| `edit-profile.php` | Pass | account details + change-password sections |
| `saved-builds.php` | Pass | empty-state contained |
| `wishlist.php` | Pass | empty-state contained |
| `order-history.php` (empty + 1 order) | Pass | `.order-history-table` fits within card |
| `order-details.php` | Pass | shipping/payment/items sections fit |
| `order-confirmation.php` | Pass | confirmation summary fits |
| `cart.php` (1 item) | Pass | qty controls + summary fit |
| `checkout.php` | Pass | shipping form + order summary fit |
| `consultation.php` | Pass | request form + file field fit |
| `consultation-history.php` | Pass | empty-state contained |

### 4.3 Administrator (1280 px; dashboard also at 1920 px)

| Page | Result | Notes |
| ---- | ------ | ----- |
| `admin/index.php` | Pass | sidebar + dashboard cards/tables; caps at 1120 px, centred at 1920 |
| `admin/products.php` | Pass / **Cond** | `.admin-table--products` in `.admin-table-wrap`; fits at 1280, scrolls in-card only if narrower |
| `admin/orders.php` | Pass | filter row + results table fit |
| `admin/users.php` | Pass / **Cond** | user table in scroll wrapper; fits at 1280 |
| `admin/reviews.php` | Pass | moderation cards fit |
| `admin/reports.php` | Pass | 5 Chart.js canvases sized to their cards; matching data tables fit |
| `admin/monitoring.php` | Pass | status + live-stat cards fit |
| `admin/themes.php` | Pass | theme cards fit |
| `admin/compatibility.php` | Pass / **Cond** | two `.admin-table` grids in scroll wrappers; no page overflow |
| `admin/product-add.php` | Pass | product form fits |
| `admin/product-edit.php` | Pass | product form fits |
| `admin/product-options.php` | Pass / **Cond** | per-group option tables in scroll wrappers |
| `admin/order-details.php` | Pass | order sections + status/notes forms fit |
| `admin/user-edit.php` | Pass | profile + status/role controls fit |

---

## 5. Defects found and fixed

**None.** The desktop sweep found no unintended horizontal overflow, clipped content, or broken grids on any core page at any tested width. The existing responsive foundation (fluid `min()` container, `auto-fit`/`auto-fill` grids, and deliberate `overflow-x` wrappers around wide tables) already handles the desktop range correctly, so no CSS changes were required for this commit.

---

## 6. Design notes

- **Wide-table scrolling is intentional.** Admin data tables and the comparison table can exceed the 1120 px content column; they are wrapped in `overflow-x: auto` cards so they scroll locally rather than forcing a whole-page scrollbar. This is recorded as `Cond`, not `Fail`.
- **No edge-to-edge stretch.** The 70 rem (`--cc-width-max`) cap keeps line length and card sizing comfortable on 1440–1920 px displays; content centres rather than spanning the full monitor width.
- **Charts and the map are container-bound.** Chart.js canvases and the Leaflet map size to their parent cards, so they scale with the desktop layout without overflowing.

---

## 7. How to re-run

```
# 1. Serve from the project root (connects to MySQL from config/database.php):
php -S localhost:8000

# 2. In a desktop browser, open each page in §4 and, for each width
#    (1024, 1280, 1440, 1920), confirm there is NO horizontal scrollbar
#    on the page itself and no clipped/overlapping content. Wide admin
#    tables and the comparison table may scroll INSIDE their card — that
#    is expected.

# 3. Quick overflow check in the browser console on any page:
#    (document.documentElement.scrollWidth <= window.innerWidth)  // expect true
```

Signed-in and admin pages require a logged-in customer / administrator account; the audit used a disposable account that was deleted afterward.

---

## 8. Sign-off

| Criterion | Result |
| --------- | ------ |
| Core pages free of unintended horizontal overflow (1024–1920 px) | **Yes** |
| Content caps at 1120 px and centres on wide screens | **Yes** |
| Wide tables contained in intended scroll wrappers | **Yes** |
| Avoidable desktop layout defects corrected | **None found** |
| Desktop responsiveness results recorded | **This document** |

**Commit 15.4 complete.** Next: Commit **15.5** — mobile responsiveness testing.
