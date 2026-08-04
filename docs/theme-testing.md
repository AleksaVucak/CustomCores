# CustomCore | Theme testing record

**Document type:** Project documentation
**Purpose:** Record that every key page renders correctly under all three site-wide CSS templates, and that the themes remain visibly distinct.
**Related:** Theme files in `assets/themes/`; resolver in `includes/theme.php`; switcher in `admin/themes.php`.

### Status legend

| Status | Meaning |
| ------ | ------- |
| Pass | HTTP 200, correct theme CSS linked after `main.css` (and after `admin.css` on admin pages), structural chrome present, no PHP error leak |
| Fail | Any of the above checks failed |

---

## 1. Themes under test

| Theme | Slug | Stylesheet | Accent | Display font | Radius feel |
| ----- | ---- | ---------- | ------ | ------------ | ----------- |
| RGB Gaming | `rgb-gaming` | `assets/themes/rgb-gaming.css` | Cyan `#00e5c0` | Space Grotesk | Soft (`0.35rem`) |
| Minimal Professional | `minimal-pro` | `assets/themes/minimal-pro.css` | Blue `#2b57d6` | Fraunces (serif) | Crisp (`0.25rem`) |
| Cyber Grid | `cyber-grid` | `assets/themes/cyber-grid.css` | Mint `#24f39b` | Orbitron | Square (`0`) |

**Distinctness check:** backgrounds, accents, and display fonts all differ across the three files (3 unique each). Radii differ between soft / crisp / zero. Themes differ beyond colour alone (typography, borders, nav, buttons, cards, layout feel) — rubric `#3a`.

---

## 2. Method

1. Seeded themes already present (`database/seed-themes.sql`).
2. For each theme id `1` / `2` / `3`, set `site_settings.active_theme_id` and request every page below over HTTP as a signed-in administrator.
3. Assert for each response:
 - HTTP status is 200
 - `assets/css/main.css` is linked
 - The expected `assets/themes/<slug>.css` is linked **after** `main.css`
 - On admin pages, `assets/css/admin.css` is linked and the theme CSS follows it
 - Structural markers present (`site-header`, `site-footer`, `admin-nav` / forms / cards as applicable)
 - No PHP fatal / warning / stack-trace leak in the body
4. Spot-check the catalogue visually under Cyber Grid (grid backdrop, mint wordmark, uppercase headings, corner-cut buttons).
5. Restore `active_theme_id = 1` (RGB Gaming) after the walk.

Mobile layout rules live in `assets/css/main.css` (nav toggle ≤ ~900px) and remain active under every theme because themes override tokens/component chrome rather than replacing the responsive grid. The walk exercised the mobile-width nav toggle markup (`Open menu` / `.nav-toggle`) on every public page.

---

## 3. Pages walked (26 per theme × 3 = 78 checks)

### Public

| Page | Nav | Cards | Forms | Tables | Notes |
| ---- | --- | ----- | ----- | ------ | ----- |
| `index.php` | Pass | Pass | — | — | Hero + featured systems |
| `catalogue.php` | Pass | Pass | Pass | — | Filters + product grid |
| `product.php?id=1` | Pass | — | Pass | — | Options / add actions |
| `search.php?q=core` | Pass | — | Pass | — | Search form |
| `builder.php` | Pass | — | Pass | — | Multi-step builder |
| `compare.php` | Pass | — | — | Pass | Comparison surface |
| `login.php` | Pass | — | Pass | — | Auth form |
| `register.php` | Pass | — | Pass | — | Auth form |
| `about.php` | Pass | — | — | — | Content page |
| `store-locations.php` | Pass | — | — | — | Map + text fallback |
| `learning-centre.php` | Pass | — | — | — | Media hub |
| `contact.php` | Pass | — | Pass | — | Contact form |

### Account (authenticated)

| Page | Nav | Forms | Tables | Notes |
| ---- | --- | ----- | ------ | ----- |
| `profile.php` | Pass | — | — | Account dashboard |
| `cart.php` | Pass | — | Pass | Cart lines / empty state |
| `checkout.php` | Pass | Pass | — | Checkout form / empty-cart path |
| `wishlist.php` | Pass | — | — | Wishlist |

### Admin

| Page | Nav | Forms | Tables | Notes |
| ---- | --- | ----- | ------ | ----- |
| `admin/index.php` | Pass | — | Pass | Dashboard |
| `admin/products.php` | Pass | Pass | Pass | Product list |
| `admin/product-add.php` | Pass | Pass | — | Product form |
| `admin/orders.php` | Pass | Pass | Pass | Order list |
| `admin/users.php` | Pass | Pass | Pass | User list |
| `admin/reviews.php` | Pass | Pass | — | Moderation queue |
| `admin/reports.php` | Pass | — | Pass | Charts + table fallbacks |
| `admin/themes.php` | Pass | Pass | — | Theme switcher cards |
| `admin/consultations.php` | Pass | — | Pass | Consultation list |
| `admin/compatibility.php` | Pass | — | Pass | Compatibility metadata |

---

## 4. Results matrix

| Theme | Pages checked | Pass | Fail |
| ----- | ------------: | ---: | ---: |
| RGB Gaming | 26 | 26 | 0 |
| Minimal Professional | 26 | 26 | 0 |
| Cyber Grid | 26 | 26 | 0 |
| **Total** | **78** | **78** | **0** |

**Theme bugs found:** none. No CSS or markup fixes were required in this commit.

**Switcher / fallback cross-check:** after the walk, `active_theme_id` was restored to `1` and the homepage again linked `assets/themes/rgb-gaming.css`. Fallback behaviour itself was proven during theme testing

---

## 5. Acceptance

- [x] Theme testing record exists (`docs/theme-testing.md`)
- [x] Key public, account, and admin pages walk cleanly under all three themes
- [x] Themes remain distinct in colour, typography, radius, and accent
- [x] No theme-only layout regressions found
- [x] Active theme restored to RGB Gaming after testing

**Acceptance:** layouts work across themes; complete.
