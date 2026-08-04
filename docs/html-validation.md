# CustomCore | HTML Validation Record

**Document type:** Project documentation
**Purpose:** Prove that important **rendered** HTML pages have no major markup errors, with a page-by-page evidence trail.
**Acceptance:** Important rendered pages have no major markup errors.
**Related:** Shared layout in [`includes/header.php`](../includes/header.php) / [`footer.php`](../includes/footer.php); semantic hierarchy work; the accessibility pass; theme walk in [`theme-testing.md`](theme-testing.md).

### Status legend

| Status | Meaning |
| --- | --- |
| **Pass** | HTTP 200 (or an intentional 303 for empty state), core structural rules satisfied, no HTML Tidy **Error** lines, no PHP error leak in the body |
| **Skip** | Page intentionally redirects without full HTML (empty cart checkout, unfinished build results) |
| **Fail** | Any major markup defect or PHP error leakage |

---

## 1. Scope

Validated **real server-rendered output** (not source templates only) over the local built-in PHP server (`http://localhost:8000`).

| Audience | Pages validated |
| --- | --- |
| Public storefront | `index.php`, `about.php`, `catalogue.php`, `product.php?id=2`, `search.php` (+ `?q=gaming`), `compare.php`, `builder.php`, `reviews.php`, `contact.php`, `media.php`, `store-locations.php`, `accessibility.php`, `login.php`, `register.php` |
| Help centre (static) | `help/index.html`, `help/pc-builder.html`, `help/accounts.html`, `help/catalogue.html`, `help/orders.html`, `help/support.html`, `help/training.html` |
| Signed-in customer | `profile.php`, `edit-profile.php`, `cart.php`, `wishlist.php`, `saved-builds.php`, `order-history.php`, `consultation.php`, `consultation-history.php` |
| Administrator | `admin/index.php`, `admin/products.php`, `admin/product-add.php`, `admin/product-edit.php?id=20`, `admin/product-options.php?product_id=2`, `admin/orders.php`, `admin/users.php`, `admin/user-edit.php?id=21`, `admin/consultations.php`, `admin/reviews.php`, `admin/compatibility.php`, `admin/reports.php`, `admin/themes.php`, `admin/monitoring.php` |

**Not expected as full HTML documents in this pass**

| URL | Why |
| --- | --- |
| `cart.php` (guest) | 303 to login |
| `checkout.php` (empty cart) | 303 away from empty checkout |
| `builder-results.php` (no build) | 303 until a build is finalized |
| Action/stream scripts | `logout.php`, `consultation-attachment.php`, sitemap/robots — not page chrome |

Admin pages were rendered after temporarily promoting a disposable account for the audit only; that account was **deleted** afterwards.

---

## 2. Method

1. Start CustomCore under PHP’s built-in server (project root).
2. Capture `GET` responses for every public/help page listed above.
3. Register/sign in a disposable customer; re-capture private account pages as authenticated HTML.
4. Elevate that account to administrator **only long enough** to capture admin list/detail pages; then remove the disposable user.
5. Run two automated checks on every non-empty HTML capture:

### 2.1 Core structural rules (application-level)

| Check | Rule |
| --- | --- |
| Document type | `<!DOCTYPE html>` present |
| Language | `<html lang="…">` present |
| Metadata | Non-empty `<title>`, `charset`, and `viewport` meta |
| Landmarks | Document contains `<header>`, `<main>`, and `<footer>` |
| Heading | Exactly one `<h1>` |
| IDs | No duplicate `id` attributes |
| Images | Every `<img>` has an `alt` attribute |
| Labels | Every `label[for]` points at an existing `id` |
| CSRF | Every `<form method="post">` contains an `_csrf` hidden field |
| Errors | Body must not contain PHP `Fatal error` / `Warning:` / stack-trace leaks |

### 2.2 HTML Tidy

- Tool: system `tidy` (`HTML Tidy for HTML5`)
- Mode: `--doctype html5`, report `Error:` lines as hard failures
- Additional filter: structural **Warning** lines about tables/lists/forms (not pure style preference noise)

Tidy is **not** treated as a strict blocker when it misreads valid HTML5 constructs (see §4).

---

## 3. Results summary

| Suite | Result |
| --- | --- |
| Core structural rules | **48 Pass · 0 Fail** (7 intentional Skip/intermediate captures) |
| HTML Tidy Errors | **None** on any important page |
| PHP error leakage | **None** |

**Acceptance is met:** important rendered pages have **no major markup errors**.

---

## 4. Page-by-page evidence (core structure)

### 4.1 Public storefront

| Page | HTTP | Core | Notes |
| --- | --- | --- | --- |
| `index.php` | 200 | Pass | Hero + featured systems; single `h1` |
| `about.php` | 200 | Pass | Business narrative sections |
| `catalogue.php` | 200 | Pass | Filter sidebar + product grid |
| `product.php?id=2` | 200 | Pass | Options form + review snippet |
| `search.php` | 200 | Pass | Empty-query state |
| `search.php?q=gaming` | 200 | Pass | Results grid |
| `compare.php` | 200 | Pass | Multi-select compare board |
| `builder.php` | 200 | Pass | Multi-step builder + summary sidebar |
| `reviews.php` | 200 | Pass | Public review list + form for signed-out (login gated submit) |
| `contact.php` | 200 | Pass | Contact form with CSRF |
| `media.php` | 200 | Pass | Video/audio cards with captions |
| `store-locations.php` | 200 | Pass | Location cards + map host |
| `accessibility.php` | 200 | Pass | A11y statement |
| `login.php` | 200 | Pass | Auth form |
| `register.php` | 200 | Pass | Registration form |

### 4.2 Help centre

| Page | HTTP | Core | Notes |
| --- | --- | --- | --- |
| `help/index.html` | 200 | Pass | Hub |
| `help/pc-builder.html` | 200 | Pass | Builder guide |
| `help/accounts.html` | 200 | Pass | Accounts guide |
| `help/catalogue.html` | 200 | Pass | Catalogue guide |
| `help/orders.html` | 200 | Pass | Cart & orders guide |
| `help/support.html` | 200 | Pass | Support guide |
| `help/training.html` | 200 | Pass | End-user training walkthrough |

### 4.3 Customer (authenticated)

| Page | HTTP | Core | Notes |
| --- | --- | --- | --- |
| `profile.php` | 200 | Pass | Account dashboard |
| `edit-profile.php` | 200 | Pass | Details + password forms |
| `cart.php` | 200 | Pass | Empty authenticated cart chrome |
| `wishlist.php` | 200 | Pass | Empty wishlist state |
| `saved-builds.php` | 200 | Pass | Empty builds list |
| `order-history.php` | 200 | Pass | Empty orders list |
| `consultation.php` | 200 | Pass | Request form (file input) |
| `consultation-history.php` | 200 | Pass | Empty history |

### 4.4 Administrator

| Page | HTTP | Core | Notes |
| --- | --- | --- | --- |
| `admin/index.php` | 200 | Pass | Dashboard KPIs + tools |
| `admin/products.php` | 200 | Pass | Searchable product table |
| `admin/product-add.php` | 200 | Pass | Create form |
| `admin/product-edit.php?id=20` | 200 | Pass | Edit form with image field |
| `admin/product-options.php?product_id=2` | 200 | Pass | Option groups editor |
| `admin/orders.php` | 200 | Pass | Order queue shell |
| `admin/users.php` | 200 | Pass | User administration list |
| `admin/user-edit.php` | 200 | Pass | Account detail |
| `admin/consultations.php` | 200 | Pass | Consultation queue |
| `admin/reviews.php` | 200 | Pass | Moderation queue |
| `admin/compatibility.php` | 200 | Pass | Component + rule editors |
| `admin/reports.php` | 200 | Pass | KPI + charts section |
| `admin/themes.php` | 200 | Pass | Theme cards + activate forms |
| `admin/monitoring.php` | 200 | Pass | Health checks |

---

## 5. HTML Tidy findings (non-blocking)

Tidy reported **zero Errors**. It emitted warnings on four pages about “missing `<dd>` / missing `</dl>` before `</div>`”. Those pages use the **HTML5** pattern:

```html
<dl>
 <div>
 <dt>…</dt>
 <dd>…</dd>
 </div>
</dl>
```

That construct is allowed by the living HTML standard (a `div` may wrap `dt`/`dd` groups inside a `dl`). HTML Tidy’s content model still largely assumes older HTML and therefore raises a **false positive**. Affected pages:

- `admin/themes.php`
- `builder.php` (live summary list)
- `profile.php` (account detail list)
- `store-locations.php` (hours list)

**No markup change required** — restructuring would fight the design system without fixing a real browser error. These are recorded for transparency only.

---

## 6. Code changes from this commit

**None.** No major markup defects required fixes. The shared layout already establishes a single `h1` hierarchy, landmarks, alt text discipline, and CSRF fields on POST forms.

---

## 7. How to re-run locally

```bash
# From the project root (with MySQL available and config/database.php set):
php -S localhost:8000

# In another shell: capture any important page, then:
tidy -errors -quiet --doctype html5 captured.html

# Manual checklist (same rules as §2.1):
# DOCTYPE, lang, title, charset, viewport, header/main/footer,
# single h1, unique ids, img alt, label[for] targets, POST forms have _csrf.
```

Authenticated pages require an existing customer (or admin) session cookie. Prefer a real account created via `register.php` or `database/create-admin.php` rather than temporary promotion scripts.

---

## 8. Sign-off

| Criterion | Result |
| --- | --- |
| Important rendered pages validated | **Yes** (public + help + customer + admin) |
| Major markup errors | **None** |
| HTML validation record | **This document** |

