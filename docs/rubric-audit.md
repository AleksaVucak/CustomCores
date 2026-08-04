# CustomCore | 100-Point Rubric Audit (Requirement → Evidence)

**Document type:** Project documentation
**Purpose:** Prove that every point of the official 100-point grading scheme maps to a concrete **page**, a concrete **file**, and a **test/result** — the completion criterion: *"Every point has a page, a file, and a test."*
**Method:** Each row below was re-verified against the running project (served with `php -S localhost:8000`) and the working tree on the audit date. Counts were taken directly from the live database and filesystem; HTTP status codes were captured with `curl`; behavioural claims cite the earlier test records (15.1 HTML, 15.2 CSS, 15.3 JS/console, 15.4 desktop, 15.5 mobile, 15.6 customer workflows, 15.7 administrator workflows).
**Result:** **100 / 100 points fully evidenced.** Item **#11 (live hosting)** was completed after university myweb deployment: the public site loads with styling and seeded catalogue data at the URL recorded in the project README (16.3–16.5).

---

## How this audit was run (reproducible)

Served locally and probed:

```bash
php -S localhost:8000
# Public + Help pages → HTTP 200; auth-gated pages → 303 to login (expected)
for p in index.php about.php catalogue.php "product.php?id=1" builder.php \
 compare.php "search.php?q=pc" store-locations.php media.php \
 accessibility.php reviews.php contact.php login.php register.php \
 sitemap.php sitemap.xml robots.txt favicon.svg site.webmanifest; do
 curl -s -o /dev/null -w "%{http_code} $p\n" "http://localhost:8000/$p"
done
```

Live counts (audit date):

| Metric | Query / command | Result |
| ------ | --------------- | -----: |
| Active products | `SELECT COUNT(*) FROM products WHERE is_active=1` | **20** |
| Products with < 2 active options | grouped `HAVING COUNT(options) < 2` | **0** |
| Product options total | `SELECT COUNT(*) FROM product_options` | **323** |
| Themes (DB rows) | `SELECT COUNT(*) FROM themes` | **3** |
| Theme CSS files | `ls assets/themes/*.css` | **3** |
| Help pages | `ls help/*.html` | **7** |
| Images | recursive image find under `assets/images/` | **33** (20 products + 13 site) |
| Media files | `assets/media/` (excl. captions) | **3** (2× MP4 + 1× MP3) |
| Dynamic PHP pages | `*.php` + `admin/*.php` + `api/*.php` | **50** (30 public/customer + 17 admin + 3 API) |
| SEO assets | `favicon.svg`, `site.webmanifest`, `sitemap.xml`, `robots.txt` | all present, all HTTP 200 |

All 19 public/SEO URLs returned **200**; all 7 Help pages returned **200**; `profile.php`/`cart.php`/`checkout.php`/`admin/index.php` returned **303 → login** (correct access control).

---

## Section A — 100-point requirement → evidence table

| # | Pts | Requirement | Page (URL) | File(s) | Test / Result | Status |
| - | --: | ----------- | ---------- | ------- | ------------- | ------ |
| 1 | 2 | Business case: ≥1 paragraph describing the project | `about.php` (HTTP 200) | `about.php`; planning `docs/business-case.md`; README summary | About page renders the CustomCore business paragraph; markup checked in 15.1 (48 Pass / 0 Fail) | **Complete** |
| 2 | 4 | ≥ 20 products; each with ≥ 2 options | `catalogue.php`, `product.php` (200) | `products` + `product_options` tables; `database/seed-products.sql`, `database/seed-product-options.sql` | **DB (15.8):** 20 active products, **0** with < 2 options, 323 options total; catalogue browse/sort/filter + option selection verified in 15.6 | **Complete** |
| 3a | 12 | ≥ 3 distinct site-wide CSS templates | any page + `admin/themes.php` | `assets/themes/rgb-gaming.css`, `minimal-pro.css`, `cyber-grid.css`; `themes` table | **Files (15.8):** 3 theme CSS + 3 DB rows; cross-theme walk of 26 pages × 3 themes recorded in `docs/theme-testing.md` (10.6) — distinct colour/type/nav/cards/radius | **Complete** |
| 3b | 4 | Change the template dynamically | `admin/themes.php` | `includes/theme.php`; `site_settings.active_theme_id` | **15.7 admin E2E:** activated a different theme (DB row change confirmed) then restored; 5-step hardened resolver verified in 10.4–10.5 (33 assertions) | **Complete** |
| 4 | 8 | Dynamic HTML forms on ≥ 2 pages | `builder.php`, `checkout.php` (200) | `builder.php`, `checkout.php`, `api/builder-price.php`; extras `register.php`, `consultation.php`, `contact.php` | **15.6 customer E2E:** builder recomputes live price/compatibility; checkout creates an order (PRG → `order-confirmation.php?id`) with no real payment data (41/41) | **Complete** |
| 5 | 20 | PHP + MySQL well documented | (docs) | `docs/database-design.md`, `database-import.md`, `installation-guide.md`, `deployment-troubleshooting.md`, `frontend-documentation.md`; `database/schema.sql` comments | **14.7 audit:** token scan confirms all **282** PHP functions carry docblocks and every PHP file has a responsibility header; schema/ER + import/install/deploy docs published | **Complete** |
| 6 | 8 | All code properly commented (HTML/CSS/JS/SQL) | (source) | `assets/css/*`, `assets/js/*`, HTML views (41 templates), SQL seeds/schema | **14.6–14.7:** numbered CSS section headers; purpose-first `<!-- -->` view comments; JS file headers + JSDoc on named functions; **15.3** static-checked 11 JS modules (0 failures) | **Complete** |
| 7 | 10 | Help wiki ≥ 5 pages + context-sensitive links | `help/index.html` + 6 guides (all 200) | `help/{index,accounts,catalogue,pc-builder,orders,support,training}.html`; `docs/help-context-links.md` | **15.8:** 7 Help pages all HTTP 200 (exceeds 5); **11.7** context-link audit maps each feature page to its matching article + anchor | **Complete** |
| 9 | 4 | Responsive main menu | every page (shared nav) | `includes/navigation.php`; `assets/css/main.css` + themes; `assets/js/main.js` | **15.4 + 15.5:** desktop (1024–1920) and mobile (320–768) nav usable; mobile toggle, Escape, focus trap; 40/40 page states Pass | **Complete** |
| 10a | 4 | ~ 20 dynamic HTML/PHP pages | 47 pages + 3 API endpoints | 30 public/customer `*.php`, 17 `admin/*.php`, 3 `api/*.php`; inventory in `docs/sitemap.md` | **15.8 count:** **50** purposeful dynamic PHP files (far above ~20); public/Help all 200, private/admin correctly gated — no empty placeholders | **Complete** |
| 10b | 2 | ≥ 1 external CSS file | view-source, any page | `assets/css/main.css` (+ admin/print/theme CSS) | **15.8:** `main.css` linked from `includes/header.php` (line 101); parsed clean in 15.2 (0 parse errors) | **Complete** |
| 10c | 2 | ≥ 1 external JavaScript file | view-source, any page | `assets/js/main.js` (+ builder/cart/validation/charts/map) | **15.8:** `main.js` linked (`defer`) from `includes/footer.php` (line 49); **15.3** console sweep = 0 uncaught core errors | **Complete** |
| 10d | 4 | ≥ 20 copyright-free images | catalogue/product/hero/media pages | `assets/images/**` (products, categories, hero, media, og, ui); credits `docs/media-credits.md` | **15.8 count:** **33** images (20 product + 13 site); every `img` has `alt` (15.1); licences/prompts documented (8.7) | **Complete** |
| 10e | 4 | ≥ 3 video or audio files | `media.php` | `assets/media/` (2× MP4 + 1× MP3) + `captions/` WebVTT | **15.8 count:** 3 media items; native controls + captions + transcripts verified (8.2–8.3) | **Complete** |
| 10f | 2 | Non-programmer content-update instructions | (docs) | `docs/content-update-guide.md`; referenced from admin guide + README | **12.3:** step-by-step for products/prices/stock/images/options via the admin site (no code) + media copy-paste block + "what NOT to edit" | **Complete** |
| 11 | 2 | Website available online live | production URL in README | README Live URL + demonstration section; docs production-requirements / production-configuration / deployment-troubleshooting / installation-guide; `config/database.example.php`, `config/app.production.example.php` | **Live host verified:** [https://vucaka.myweb.cs.uwindsor.ca/customcore/](https://vucaka.myweb.cs.uwindsor.ca/customcore/) loads styled homepage, MySQL catalogue, and authenticated admin after host DB import (16.3–16.5) | **Complete** |
| 12 | 4 | Advanced appropriate CSS | all pages across 3 themes | `assets/css/main.css` + 3 themes | **14.5 + 10.6 + 15.4/15.5:** typography, nav, cards, transitions (`:focus-within` lift, animated menu reveal, focus-driven labels, hover-gated lift), grids, form states; cross-theme + responsive verified | **Complete** |
| 13 | 4 | SEO-friendly meta (icon/title/description/keywords) | all public pages | `includes/header.php` + `includes/seo.php`; `favicon.svg`, `site.webmanifest`, `sitemap.xml`, `robots.txt` | **15.8:** all four SEO assets HTTP 200; per-page unique title/description/keywords + canonical/OG (14.1); public-only sitemap excludes admin/APIs/private (14.2); semantic landmarks + heading hierarchy (14.3); titles/descriptions present in 15.1 | **Complete** |

**Point tally:** 2 + 4 + 12 + 4 + 8 + 20 + 8 + 10 + 4 + 4 + 2 + 2 + 4 + 4 + 2 + 2 + 4 + 4 = **100**.

---

## Scoreboard

| Bucket | Points | Notes |
| ------ | -----: | ----- |
| Complete — page + file + passing test | **100** | Items 1–13 including #11 live myweb host |
| **Total accounted for** | **100** | Every point has a page, a file, and live or local evidence |

---

## Live hosting (#11) — completed at 16.3–16.5

Item 11 requires a public URL (preferably on `myweb.cs.uwindsor.ca`). After deployment:

- **Page:** README records  
  [https://vucaka.myweb.cs.uwindsor.ca/customcore/](https://vucaka.myweb.cs.uwindsor.ca/customcore/)  
  and a short demonstration path (public → seeded customers → admin overview).
- **File:** production requirements, production configuration, deployment, and installation guides remain the install path for a second server; secrets stay out of Git.
- **Live proof:** styled homepage, seeded MySQL catalogue/builder, and administrator access were confirmed on that host (project subfolder under the myweb document root).

Optional fuller customer and administrator live workflow records sit under Stage 16.6–16.7; they do not block the basic #11 live-availability mark once the public URL works and is documented.

---

## Cross-references

- HTML: [`html-validation.md`](html-validation.md) · CSS: [`css-validation.md`](css-validation.md) · JS/console: [`js-validation.md`](js-validation.md)
- Desktop responsive: [`responsiveness-desktop.md`](responsiveness-desktop.md) · Mobile: [`responsiveness-mobile.md`](responsiveness-mobile.md)
- Customer workflows: [`customer-workflows.md`](customer-workflows.md) · Administrator workflows: [`admin-workflows.md`](admin-workflows.md)
- Full requirement map + roadmap: [`rubric-checklist.md`](rubric-checklist.md) · Page inventory: [`sitemap.md`](sitemap.md)

**Conclusion:** Every one of the 100 graded points maps to a real page, a real file, and documented evidence. Live hosting (#11) is satisfied by the myweb URL and demonstration notes in the project README.
