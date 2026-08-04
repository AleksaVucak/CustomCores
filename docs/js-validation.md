# CustomCore | JavaScript & Browser Console Verification

**Document type:** Project documentation
**Purpose:** Prove that every project JavaScript module is syntactically sound and that core feature pages load and run without uncaught application errors in a real browser.
**Acceptance:** Core features produce **no uncaught JavaScript errors**; any avoidable defects found are corrected.
**Related:** Module map in [`frontend-documentation.md`](frontend-documentation.md) §4–5; script load conditions in [`includes/footer.php`](../includes/footer.php); earlier test records [`html-validation.md`](html-validation.md) and [`css-validation.md`](css-validation.md).

### Status legend

| Status | Meaning |
| --- | --- |
| **Pass** | Page loaded (HTTP &lt; 400 after redirects), no `pageerror` / uncaught exception, no application console **error**, required local scripts present when the page state loads them |
| **Cond** | Feature script correctly **not** loaded for this page state (progressive enhancement / auth gates) — documented, not a defect |
| **Fail** | Uncaught error, broken local asset, or avoidable app console error on a core feature |

---

## 1. Scope

### 1.1 Project JavaScript modules

| File | Role | Load condition |
| --- | --- | --- |
| `assets/js/main.js` | Shared utilities, nav toggle, post-fail focus | Every layout page (`includes/footer.php`) |
| `assets/js/builder.js` | Live builder price + compatibility XHR | `$currentPage === 'builder'` |
| `assets/js/charts.js` | Builder/results performance Chart.js | `$loadCharts` |
| `assets/js/catalogue-chart.js` | Catalogue tier bar chart | `$loadCatalogueChart` |
| `assets/js/cart.js` | Qty steppers, live line totals, remove confirm | `$currentPage === 'cart'` |
| `assets/js/checkout.js` | Checkout field validation | `$currentPage === 'checkout'` |
| `assets/js/reviews.js` | Review form validation | `$loadReviewForm` |
| `assets/js/contact.js` | “Other” subject row toggle | `$currentPage === 'contact'` |
| `assets/js/store-map.js` | Leaflet map init from data-* attrs | `$currentPage === 'locations'` |
| `assets/js/admin-reports.js` | Admin report Chart.js panels | `$loadAdminReports` |
| `assets/js/help-hub.js` | Help hub live filter | `help/index.html` only |

Third-party (CDN, progressive enhancement with text fallbacks): Chart.js 4.4.1, Leaflet 1.9.4.

### 1.2 Browser surfaces exercised

| Audience | Pages / interactions |
| --- | --- |
| Public | Home, about, catalogue (chart), product (guest), search, compare, builder (radio select + live price/compat + chart), reviews list, contact (subject=Other + submit event), media, store locations (map), accessibility, login, register |
| Help | `help/index.html` (filter “builder”), `help/pc-builder.html` |
| Customer (signed-in) | Profile, edit-profile, **cart** (qty step), **checkout** (empty submit validation), wishlist, saved-builds, order-history, consultation, **reviews** form (submit validation), product |
| Administrator | Dashboard, products, **reports** (charts), monitoring, compatibility, reviews |

---

## 2. Method

1. **Static syntax** — `node --check` on every file under `assets/js/` (no build step; ES5-compatible IIFEs).
2. **Manual code review** — defensive guards (missing DOM nodes, missing `window.Chart` / `L`, JSON parse try/catch, XHR non-200 handling) for all eleven modules.
3. **Browser console sweep** — Chromium headless via puppeteer-core against `http://localhost:8000` (project-root PHP built-in server):
 - Capture `pageerror`, console `error`/`warning`, and failed same-origin `assets/js` / `assets/css` requests.
 - Install page-level `error` / `unhandledrejection` listeners before navigation.
 - Exercise core interactions (nav toggle, builder radio, contact Other, help filter, cart steppers, checkout submit, review form submit).
 - Admin phase used a disposable customer promoted via a temporary CLI-style helper, then **deleted** after capture.
4. **Authenticated focus pass** — separate session that registers → logs in → posts `add_product` so cart/checkout and reviews form scripts load as designed (register alone redirects to login without auto-session).

CDN tile/resource noise (blocked OpenStreetMap tiles, optional offline CDN) is classified as **environment**, not application failure, when the progressive text fallback remains usable.

---

## 3. Results summary

| Suite | Result |
| --- | --- |
| `node --check` (11 modules) | **11 Pass · 0 Fail** |
| Public console sweep (16 pages) | **16 Pass · 0 app uncaught · 0 page errors** |
| Customer pages (auth) | **Pass** (profile suite + dedicated cart/checkout/reviews with feature scripts) |
| Admin pages | **6 Pass · 0 app uncaught** (`admin-reports.js` + Chart.js present on reports) |
| Application uncaught errors | **0** after fix in §5 |
| Avoidable defect corrected | **1** (Leaflet CSS Subresource Integrity hash) |

**Acceptance is met:** core features produce no uncaught JavaScript errors; the one avoidable console defect found was fixed and re-verified.

---

## 4. Page-level evidence

### 4.1 Public and Help

| Page | Scripts observed | Interactions | Result |
| --- | --- | --- | --- |
| `index.php` | `main.js` | Nav toggle | Pass |
| `about.php` | `main.js` | Load | Pass |
| `catalogue.php` | `main.js`, Chart.js, `catalogue-chart.js` | Load | Pass |
| `product.php?id=2` (guest) | `main.js` | Load | Pass — `reviews.js` only when `$loadReviewForm` (signed-in eligible); **Cond** for guests |
| `search.php?q=gaming` | `main.js` | Load | Pass |
| `compare.php` | `main.js` | Load | Pass |
| `builder.php` | `main.js`, `builder.js`, Chart.js, `charts.js` | Select CPU radio | Pass — live total / compat / chart paths exercised |
| `reviews.php` (guest) | `main.js` | Load | Pass — no form; **Cond** omit `reviews.js` |
| `contact.php` | `main.js`, `contact.js` | Subject=Other, submit event | Pass |
| `media.php` | `main.js` | Load | Pass |
| `store-locations.php` | `main.js`, Leaflet, `store-map.js` | Map marker/zoom UI | Pass after SRI fix |
| `accessibility.php` | `main.js` | Load | Pass |
| `login.php` / `register.php` | `main.js` | Load | Pass |
| `help/index.html` | `help-hub.js` | Filter “builder” | Pass |
| `help/pc-builder.html` | (article chrome; no hub filter) | Load | Pass — **Cond** `help-hub.js` hub-only |

### 4.2 Signed-in customer (feature modules)

| Page | Scripts observed | Interactions | Result |
| --- | --- | --- | --- |
| `profile.php`, `edit-profile.php` | `main.js` | Load | Pass |
| `cart.php` (with line items) | `main.js`, `cart.js` | Qty +/− | Pass |
| `checkout.php` (non-empty cart) | `main.js`, `checkout.js` | Empty-field submit | Pass — prevents submit, focuses first invalid |
| `wishlist.php`, `saved-builds.php`, `order-history.php`, `consultation.php` | `main.js` | Load | Pass |
| `reviews.php` (eligible form) | `main.js`, `reviews.js` | Submit empty form | Pass — client validation only |
| Empty-cart `checkout.php` | Redirect → cart | n/a | **Cond** — server gate, not a JS defect |

### 4.3 Administrator

| Page | Scripts observed | Result |
| --- | --- | --- |
| `admin/index.php`, `products.php`, `monitoring.php`, `compatibility.php`, `reviews.php` | `main.js` | Pass |
| `admin/reports.php` | `main.js`, Chart.js, `admin-reports.js` | Pass |

---

## 5. Defect found and fixed

### 5.1 Leaflet CSS Subresource Integrity mismatch

| --- | --- |
| --- | --- |
| **Symptom** | Console error on `store-locations.php`: *Failed to find a valid digest in the 'integrity' attribute for resource `leaflet.css`… The resource has been blocked.* |
| **Cause** | `includes/header.php` shipped an incorrect `integrity` value for Leaflet 1.9.4 CSS (`sha256-p4NxAoJBhIINfQ3ynhTUQFgfPUV3ppxA4IuaMPnLDjM=`). The browser-computed digest for the CDN file is `sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=`. JS integrity already matched. |
| **Impact** | Stylesheet blocked (map still initialised via JS; text address fallback usable). Avoidable application console error on a core locations feature. |
| **Fix** | Updated the CSS SRI hash; set `crossorigin="anonymous"` on both Leaflet CSS and JS tags (recommended with SRI). |
| **Files** | `includes/header.php`, `includes/footer.php` |
| **Re-test** | Locations loads `main.js` + `leaflet.js` + `store-map.js`; `typeof L === "object"`; **zero** integrity/console errors. |

No other application JS bugs were found. Modules already handle missing CDN libraries without throw (chart/map text fallbacks).

---

## 6. Design notes (not defects)

| Observation | Rationale |
| --- | --- |
| Feature scripts are conditional | Rubric progressive enhancement — do not ship cart/checkout/chart code on every page. |
| `reviews.js` only with form | `$loadReviewForm` — login + eligibility; avoids console work on read-only review listing. |
| Help articles omit hub filter JS | Filter DOM exists only on `help/index.html`. |
| Register does not auto-login | Intended auth UX; checkout/cart require a subsequent login session. |
| Chart.js / Leaflet on CDN | Documented progressive enhancement; text tables/address remain if CDN fails. |

---

## 7. How to re-run

```bash
# 1) Syntax (from project root)
for f in assets/js/*.js; do node --check "$f" || exit 1; done

# 2) Serve
php -S localhost:8000

# 3) Browser: open each page in §4 with DevTools → Console
# Core interactions: builder radio, contact Other, help search,
# signed-in cart qty, checkout blur/submit, locations map.
# Expect: no red uncaught errors from /assets/js/*.
```

Optional automated sweep (not shipped): headless Chromium with console + `pageerror` listeners, disposable user promote/delete for admin reports.

---

## 8. Sign-off

| Criterion | Result |
| --- | --- |
| All project JS files syntax-valid | **Yes** |
| Core pages free of uncaught app JS errors | **Yes** |
| Avoidable console defect corrected | **Yes** (Leaflet CSS SRI) |
| JS / console test results recorded | **This document** |

