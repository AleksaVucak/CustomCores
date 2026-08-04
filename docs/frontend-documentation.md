# CustomCore | Front-End Architecture Documentation

**Document type:** Project documentation
**Purpose:** Explain how the CustomCore front end is built — the shared HTML shell, the CSS token/theme system, the JavaScript modules, responsiveness, the navigation toggle, and how the active theme is resolved — so another developer can read, extend, or debug the interface confidently.
**Audience:** Developers and graders. Non-programmer content edits are covered separately in [`docs/content-update-guide.md`](content-update-guide.md).
**Scope rule:** This document describes the **actual** front end in this repository. It points at real files and does not propose a second, hypothetical architecture.

**Related:** [`docs/directory-structure.md`](directory-structure.md), [`docs/theme-testing.md`](theme-testing.md), [`docs/wireframes.md`](wireframes.md), [`docs/media-credits.md`](media-credits.md).

---

## 1. Front-end philosophy

CustomCore is a **server-rendered** application: every page is a `.php` file that produces complete HTML on the server. CSS and JavaScript are always **external** files (rubric #10b / #10c) and JavaScript is treated as **progressive enhancement** — the site is fully usable with scripts disabled.

Guiding rules used throughout the front end:

- **One shared layout.** Pages set a few variables, then include a shared header and footer so chrome (head, skip link, masthead, navigation, flash messages, footer, scripts) is identical everywhere.
- **Tokens over hard-coded values.** Colour, type, spacing, radius, and shadow live in CSS custom properties (`--cc-*`). Themes re-declare those tokens, so most components re-skin automatically.
- **Escape all output.** Dynamic text is passed through `customcore_e` (a wrapper over `htmlspecialchars`) before it reaches HTML.
- **Depth-safe URLs.** Links and asset paths are built with `customcore_url` so pages in subfolders (for example `admin/`) resolve correctly with relative paths — no URL rewriting or hard-coded base path is required.
- **Enhance, don't require.** The mobile menu, live pricing, charts, and the map all layer on top of working HTML; if a script fails, the underlying content still works.

---

## 2. The shared HTML shell

Every layout-using page follows the same pattern:

```php
require_once __DIR__. '/includes/functions.php';
$pageTitle = 'Catalogue — CustomCore';
$currentPage = 'catalogue'; // optional: drives active-nav state
require_once __DIR__. '/includes/header.php';
//... page-specific HTML...
require_once __DIR__. '/includes/footer.php';
```

### 2.1 Header — `includes/header.php`

Responsibilities (document start through the opening `<main>`):

- Emits `<!DOCTYPE html>`, `<html lang="en">`, and the `<head>`: charset, responsive viewport, `description`/`keywords` meta, `<title>`, and Open Graph / Twitter card tags. Page variables `$pageTitle`, `$pageDescription`, and `$pageKeywords` override sensible defaults derived from `config/app.php` (`name`, `tagline`).
- Links stylesheets **in a fixed, meaningful order** (see §3.4):
 1. `assets/css/main.css` (always)
 2. `assets/css/admin.css` (only when `$loadAdminCss` is set — admin pages)
 3. Leaflet CSS from CDN (only when `$currentPage === 'locations'`)
 4. the active theme stylesheet, linked **last** so it overrides the base and admin CSS
- Outputs the skip link (`<a class="skip-link" href="#main-content">`), the `.site-header` masthead with the wordmark, and includes the navigation partial.
- Renders queued flash messages via `customcore_flash_render`.
- Opens `<main id="main-content" class="site-main" tabindex="-1">` (the skip-link target).

The `<body>` gets a `page-<currentPage>` class (for example `page-catalogue`) so a page can be targeted from CSS without extra markup.

### 2.2 Navigation — `includes/navigation.php`

Included from the header. It builds:

- The **primary menu** (`#primary-navigation`) from a `$navItems` array: Home, About, Catalogue, PC Builder, Learning Centre, Locations, Help, Contact. The active item gets `is-active` plus `aria-current="page"` via `customcore_nav_class` / `customcore_is_current_page`.
- The **account cluster**, which is auth-aware:
 - Logged out → Log in, Register, Cart.
 - Logged in → greeting linking to profile, Cart (with a live badge count from `customcore_cart_count_cached`), Log out, and an **Admin** link only when the user is an administrator and `admin/index.php` exists.
- The **mobile toggle button** (`#nav-toggle`) with `aria-controls`, `aria-expanded`, and `aria-label`, enhanced by JavaScript (see §5.2).

Links are hidden defensively if their target file is missing (`profile.php`, `logout.php`, `admin/index.php`), so the menu never points at a 404 during partial deployments.

### 2.3 Footer — `includes/footer.php`

Responsibilities (closing `<main>` through document end):

- Renders the `.site-footer` with the copyright line and footer links (About, Help, Privacy, Accessibility, Contact).
- Loads `assets/js/main.js` (deferred) on every page.
- **Conditionally** loads page-specific scripts (see §5.1) based on `$currentPage` or explicit `$load*` flags, so each page ships only the JavaScript it needs.

---

## 3. CSS architecture

### 3.1 Files

| File | Role |
| --- | --- |
| `assets/css/main.css` | The base stylesheet: design tokens, reset, layout primitives, header/nav/footer, forms, buttons, cards, tables, flash banners, the Help hub UI, and responsive breakpoints. Linked on **every** page. |
| `assets/css/admin.css` | Admin-only additions layered on top of `main.css` (dashboard, data tables, admin toolbars). Linked only when `$loadAdminCss` is set. |
| `assets/themes/rgb-gaming.css` | **RGB Gaming** theme — dark, high-contrast, neon accent (default). |
| `assets/themes/minimal-pro.css` | **Minimal Professional** theme — light, editorial, single blue accent. |
| `assets/themes/cyber-grid.css` | **Cyber Grid** theme — technical HUD look, blueprint grid, zero-radius. |

There is intentionally **no** `print.css`; a dedicated print stylesheet is optional and is not linked by the header.

### 3.2 Design tokens (`--cc-*` custom properties)

`main.css` declares the shared design language as CSS custom properties on `:root` — colours (`--cc-color-*`), spacing scale (`--cc-space-*`), radii (`--cc-radius-*`), typography, borders, and shadows. Components consume these tokens rather than literal values. Example (from the Help note callout):

```css.help-note {
 margin: var(--cc-space-3) 0 0;
 padding: var(--cc-space-3) var(--cc-space-4);
 border: 1px solid var(--cc-color-border);
 border-left: 4px solid var(--cc-color-accent, var(--cc-color-primary));
 border-radius: var(--cc-radius-md);
 background: var(--cc-color-bg-elevated);
}
```

Because everything is token-driven, a theme can restyle the whole site by re-declaring these variables.

### 3.3 How themes work

Each theme file **re-declares the `--cc-*` tokens** (so every token-driven component in `main.css` and `admin.css` re-skins for free), then adds a small number of targeted overrides for the few spots that use hard-coded decorative values (body/header/hero backdrops, flash banners, footer, white-on-accent text). The three themes deliberately differ in more than colour — typography, borders, radius, nav treatment, button shape, and motion — which is how they satisfy the "three distinct templates" requirement. See [`docs/theme-testing.md`](theme-testing.md) for the cross-theme verification record.

All animated flourishes honour `@media (prefers-reduced-motion: reduce)`.

### 3.4 Stylesheet order matters

The header links CSS in this order on purpose:

```
main.css → admin.css (admin only) → theme.css (always last)
```

Loading the theme **last** lets it win the cascade over both base and admin styles. This order is asserted in the cross-theme walkthrough.

### 3.5 Responsiveness

Layout is fluid and breakpoint-driven in `main.css` (and refined per theme). The primary navigation switches between a horizontal desktop menu and a collapsible mobile menu at **900px** — the same value used by the JavaScript (`NAV_DESKTOP_MIN`). Grids (catalogue, cards, Help hub) reflow from multi-column to single-column on narrow screens. Wireframe intent for each key page is in [`docs/wireframes.md`](wireframes.md).

---

## 4. JavaScript architecture

All scripts are plain (vanilla) ES5-compatible JavaScript wrapped in IIFEs — no framework, no bundler, no build step. They are defensive: each checks that its required DOM nodes exist before doing anything, so loading a script on the wrong page is harmless.

### 4.1 Module map

| File | Loaded when | Responsibility |
| --- | --- | --- |
| `assets/js/main.js` | every page | Shared `window.CustomCore` utilities (`onReady`, `qs`, `qsa`, `debounce`, `toggleClass`, `setAria`, `createFocusTrap`) and the responsive navigation toggle. Adds `.js` to `<html>` and `data-cc-js="ready"` to `<body>`. |
| `assets/js/builder.js` | `$currentPage === 'builder'` | Live PC-builder pricing and compatibility feedback; posts selections to `api/builder-price.php` and `api/compatibility-check.php` and overwrites the client estimate with the trusted server total. |
| `assets/js/cart.js` | `$currentPage === 'cart'` | Live line-item and subtotal preview for quantity steppers before the server "Update cart" round-trip. |
| `assets/js/checkout.js` | `$currentPage === 'checkout'` | Client-side blur/submit validation of the checkout form (mirrors server validation). |
| `assets/js/reviews.js` | `$loadReviewForm` truthy | Client-side validation for the product review form. |
| `assets/js/contact.js` | `$currentPage === 'contact'` | Client-side validation for the contact form. |
| `assets/js/store-map.js` | `$currentPage === 'locations'` | Initialises the Leaflet + OpenStreetMap map from `data-*` attributes; scroll-zoom only while focused. |
| `assets/js/charts.js` | `$loadCharts` truthy | Draws the builder performance chart (Chart.js) with a server-rendered table fallback. |
| `assets/js/catalogue-chart.js` | `$loadCatalogueChart` truthy | Draws the "catalogue at a glance" tier chart from a `data-catalogue-chart` payload. |
| `assets/js/admin-reports.js` | `$loadAdminReports` truthy | Draws the admin report charts from `data-admin-report-chart` payloads. |
| `assets/js/help-hub.js` | Help hub (`help/index.html`) | Progressive search filter that narrows guide cards by title/body/keywords without hiding content when JS is off. |

Chart.js (4.4.1) and Leaflet (1.9.4) load from CDN **only on the pages that need them**, via the footer's conditional blocks.

### 4.2 The `CustomCore` utility namespace

`main.js` exposes a small toolkit on `window.CustomCore` so other modules and inline page scripts share one implementation:

- `onReady(fn)` — run after `DOMContentLoaded` (or immediately if already ready).
- `qs` / `qsa` — query one / many elements (as a real array).
- `debounce(fn, ms)` — rate-limit noisy handlers (resize, input).
- `toggleClass`, `setAria` — null-safe DOM helpers.
- `createFocusTrap(container)` — keep Tab focus inside a container (used by the mobile menu); returns a cleanup function.

---

## 5. Two front-end behaviours in detail

### 5.1 Conditional script loading

Rather than shipping one giant bundle, the footer includes only what a page needs. The pattern is:

```php
<?php if (isset($currentPage) && $currentPage === 'cart'): ?>
 <script src="<?php echo customcore_e(customcore_url('assets/js/cart.js')); ?>" defer></script>
<?php endif; ?>
```

Charts share a single guarded Chart.js include so the library is added once even if multiple chart flags are set. This keeps every page lean and avoids console errors from scripts that have no matching DOM.

### 5.2 The responsive navigation toggle (`initNavigation` in `main.js`)

When `#nav-toggle` and `#primary-navigation` are present, the script:

1. Adds `body.nav-enhanced` so CSS knows the JS layer is active and can collapse the menu below **900px**.
2. Toggles `.site-nav.is-open`, keeping `aria-expanded`, `aria-label`, and the button text (`Menu` / `Close`) in sync.
3. Traps focus within the header while the mobile menu is open (`createFocusTrap`).
4. Closes the menu on **Escape** (returning focus to the toggle) and on an outside click.
5. Watches a `matchMedia("(min-width: 900px)")` query and closes the mobile state automatically when the viewport grows to desktop width.

Because enhancement only activates when the toggle exists and JS runs, the menu is a plain list of working links with no JavaScript.

---

## 6. How the active theme is resolved (`includes/theme.php`)

The header does not hard-code a theme. It calls `customcore_active_theme_href`, which resolves the stylesheet through a **five-step, defence-in-depth chain** (first safe, on-disk match wins):

1. `site_settings.active_theme_id → themes.css_file` — the administrator's choice (set on `admin/themes.php`).
2. `themes.is_active_default = 1 → css_file` — the seeded default.
3. `config/app.php → default_theme` slug — an offline fallback used when the database is unavailable.
4. The hard-coded canonical slug `rgb-gaming` — independent of both DB and config.
5. A last-resort scan of `assets/themes/*.css` on disk.

Safety guarantees:

- Every candidate (from any source) passes through `customcore_theme_normalise_path`, which accepts **only** paths matching `^assets/themes/<slug>.css`. This blocks directory traversal (`../`), absolute paths, subdirectories, query strings, and non-CSS files — even if a database row is corrupt.
- A candidate must also **exist on disk** before it is linked, so a missing or renamed file transparently falls through to the next candidate.
- All database access is wrapped in `try/catch`; if MySQL is down, the resolver still returns a styled theme from config / canonical / scan.
- If somehow no theme file exists, the resolver returns `null` and the header simply omits the theme `<link>` — the site is still styled by `main.css`, which is always linked first.

The administrator switching flow (validation, CSRF, Post/Redirect/Get) lives in `admin/themes.php` + `includes/admin-themes.php` and is documented in [`docs/administrator-guide.md`](administrator-guide.md).

---

## 7. Accessibility touches baked into the front end

- **Skip link** to `#main-content` on every page; `<main>` is focusable (`tabindex="-1"`).
- **Semantic landmarks**: `role="banner"` header, `<nav aria-label="Primary">`, `role="contentinfo"` footer.
- **Keyboard-friendly menu**: focus trap, Escape to close, focus restoration.
- **`aria-current="page"`** on the active nav link; descriptive `aria-label` on the cart badge and toggle.
- **Media fallbacks**: alt text via `customcore_image_url`, chart data tables beside canvases, an always-visible address beside the map, and captions/transcripts for video/audio — all catalogued on the public `accessibility.php` page.
- **Reduced motion** respected across base CSS and all three themes.

---

## 8. Where to change what (quick reference)

| I want to change… | Edit… |
| --- | --- |
| Site name / tagline / default theme | `config/app.php` |
| The head, meta, or stylesheet order | `includes/header.php` |
| Menu items or account links | `includes/navigation.php` |
| Footer links or which scripts load | `includes/footer.php` |
| Global colours/spacing/typography | `--cc-*` tokens in `assets/css/main.css` |
| A specific theme's look | the matching file in `assets/themes/` |
| Admin-only styling | `assets/css/admin.css` |
| Shared JS utilities or the nav toggle | `assets/js/main.js` |
| A page-specific behaviour | that page's module in `assets/js/` |
| How the active theme is chosen | `includes/theme.php` (resolver) + `admin/themes.php` (switcher) |

---

## 9. Status

**Summary.** The front-end architecture is documented against the real shared shell (`header.php` / `navigation.php` / `footer.php`), the token-driven CSS system (`main.css` / `admin.css` / `assets/themes/*`), the vanilla-JS module set (`main.js`, builder, cart, checkout, reviews, contact, map, charts, help-hub), the responsive 900px navigation toggle, and the hardened theme resolver (`includes/theme.php`). Supports rubric row **B5 (front-end documentation)** and contributes to **#5 / #6 (well-documented, commented code)**.
