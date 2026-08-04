# CustomCore | Final Defect Resolution

**Document type:** Project documentation
**Completion test:** *No known critical or rubric-blocking issue remains.*
**Method:** A four-part final sweep of the whole project — static discovery, an `E_ALL` runtime crawl of **every** page (public + authenticated customer + admin), a usability / status-code / edge-case pass, and targeted fixes with re-verification. This builds on the earlier QA records for HTML, CSS, JavaScript and the console, desktop and mobile responsiveness, and customer and administrator workflows.

**Outcome:1 real usability/SEO defect found and fixed** (soft-404 on nonexistent products). Everything else came back clean. No critical or rubric-blocking issue remains.

---

## 1. Static discovery — clean

| Check | Command | Result |
| --- | --- | --- |
| Real defect markers | grep `TODO`/`FIXME`/`XXX`/`HACK`/`BUG`/"not implemented"/"coming soon" across all `*.php` | **0** genuine markers — every hit is a legitimate `placeholder="…"` input attribute, a `customcore_is_debug` guard, or an SQL `$placeholders` variable |
| PHP syntax | `php -l` on **all 94** PHP files | **0** syntax errors |

## 2. Runtime `E_ALL` crawl — clean

A throwaway PHP development server was started with the strictest possible error surface — `error_reporting=E_ALL`, `log_errors=1`, `display_errors=0` — so **any** notice, warning, deprecation, or fatal (even ones the app swallows in production) would be written to a log:

```bash
php -d display_errors=0 -d log_errors=1 -d error_reporting=E_ALL \
 -d error_log=/tmp/cc_php_errors.log -S 127.0.0.1:8155
```

Then every page was crawled:

- **Public + edge cases (30 URLs):** home, about, catalogue (+ sort/filter), product (valid / nonexistent / non-numeric / missing id), builder, compare, search (+ query with `'"<>` injection probe), store-locations, media, accessibility, reviews, contact, login, register, consultation, sitemap.php / sitemap.xml / robots.txt, the three `api/*` endpoints, and a nonexistent path.
- **Authenticated customer + admin (25 URLs):** driven by a disposable admin created directly in the DB and logged in over HTTP with a cookie jar + CSRF — profile, edit-profile, cart, wishlist, saved-builds, order-history, consultation, consultation-history, builder-results, and the full `admin/*` surface (dashboard, products, product-add/edit/options, orders, order-details, users, user-edit, consultations, consultation-details, reviews, compatibility, reports, monitoring, themes).

**Result:** every page returned the correct status (200 for pages, 303 PRG for foreign/nonexistent record ids and guarded GETs), and **the `E_ALL` error log was empty — 0 notices, 0 warnings, 0 deprecations, 0 fatals, 0 error leakage** across the entire site. The disposable admin and its cookie jar were deleted afterward (0 sweep users remain; store untouched).

## 3. Usability / status-code pass

| Observation | Verdict |
| --- | --- |
| **Nonexistent / invalid product returned HTTP 200** with a friendly "Product not found" message (`product.php?id=999999`, `?id=abc`, `?id=0`, no id) | **Defect — fixed** (see below): a missing resource must return **404**, not a 200 "soft 404" |
| `api/builder-price.php`, `api/compatibility-check.php`, `api/chart-data.php` return **405** for a bare `GET` | **Correct** — these are POST-only endpoints; the app calls them with the right method |
| `order-details.php` / `saved-build.php` / `admin/order-details.php` / `admin/consultation-details.php` return **303** for a foreign or nonexistent record id | **Correct** — ownership/existence guards redirect instead of exposing data or erroring |
| `builder-results.php` returns **303** on a bare GET (no build payload) | **Correct** — redirects back to the builder |
| A request for a nonexistent `*.php` file served the homepage with 200 under `php -S` | **Not an app defect** — this is the PHP built-in dev-server fallback; on real hosting (Apache/Nginx, e.g. `myweb.cs.uwindsor.ca`) a missing file returns the server's 404. No code change needed |
| Search with no results / injection-probe query | **Correct** — 200, escaped output, no error (prepared statements confirmed in) |

## 4. The fix — real 404 for missing products

Previously `product.php` set a friendly `$detailError` for an invalid id, a not-found row, **and** a transient DB exception, but always let the page finish with the default **200** status. Search engines would index the "not found" page as a valid product URL, and clients got no machine-readable signal.

The page now distinguishes *"this product does not exist"* from *"the database is temporarily unavailable"*:

```php
$notFound = false;

if ($productId < 1) {
 $detailError = 'Invalid product ID.';
 $notFound = true;
} else {
```

```php
 } catch (Throwable $exception) {
 $detailError = customcore_is_debug
 ? $exception->getMessage: 'Product data is temporarily unavailable.';
 }
}

// A genuinely invalid or missing product is a real "Not Found", so send a 404
// status (not a 200 "soft 404") to give browsers and search engines the correct
// signal while still rendering the friendly styled shell below. A transient DB
// error keeps the default 200 — the resource may exist, it is not "not found".
if ($notFound && !headers_sent) {
 http_response_code;
}
```

- **Invalid / missing / disabled product** (`id` < 1, non-numeric, absent, or `is_active = 0`) → **404** with the full styled shell + friendly message.
- **Valid product** → **200** (unchanged).
- **Transient DB error** → stays **200** (the resource may exist; it is not a "not found").

### Verification after the fix

| Request | Before | After |
| --- | --- | --- |
| `product.php?id=1` (valid) | 200 | **200** |
| `product.php?id=999999` (nonexistent) | 200 | **404** |
| `product.php?id=abc` (non-numeric) | 200 | **404** |
| `product.php?id=0` / `?id=-5` | 200 | **404** |
| `product.php` (no id) | 200 | **404** |

The 404 response still renders the complete page chrome (header, nav, footer) and the friendly "Product not found or no longer available." message (~6 KB body), so users see a helpful page, not a raw error. `php -l product.php` clean; editor linter clean.

---

## Result

- **Defects found:** 1 (soft-404 on nonexistent products). **Fixed and verified.**
- **PHP:** 94/94 files lint clean.
- **Runtime:** 0 notices / warnings / deprecations / fatals across the entire public + authenticated + admin surface under `E_ALL`.
- **Status codes:** correct across pages, guards, and edge cases.
- **Test data:** disposable admin removed; store back to baseline.

**No known critical issue remains — testing and QA is complete.** Live hosting is documented in the project README.
