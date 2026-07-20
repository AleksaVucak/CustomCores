# CustomCore — Application Sitemap

**Document type:** Stage 0 planning (Commit 0.4)  
**Purpose:** Plan every customer, administrator, API, and static Help page so the project exceeds the minimum page counts with purposeful routes only.  
**URL style:** Ordinary `.php` / `.html` paths (no rewrite rules).  
**Related:** SEO machine sitemap (`sitemap.xml`) is added in Stage 14 and must **exclude** private customer and administrator URLs.

### Count targets

| Category | Assignment minimum | CustomCore plan |
| -------- | -----------------: | --------------: |
| Dynamic PHP pages | ~20 | **48** (purposeful pages listed below) |
| Static pages | 5 Help pages | **7** static Help/training HTML pages |
| Total planned user-facing documents | >25 | **55** |

Empty placeholder pages are not allowed. Every route below maps to a real feature in the roadmap.

---

## 1. Access legend

| Access | Meaning |
| ------ | ------- |
| Public | Anyone may open the page |
| Guest form | Public, but submits create an account or message |
| Customer | Requires logged-in customer role |
| Admin | Requires logged-in administrator role |
| API | JSON/data endpoint called by the front end (still a dynamic PHP file) |
| Static | Plain HTML Help/training (no PHP required) |

---

## 2. Public customer pages (dynamic PHP)

| # | File | Purpose | Access | Context-sensitive Help |
| - | ---- | ------- | ------ | ---------------------- |
| 1 | `index.php` | Homepage: hero, featured products, categories, media teaser, CTAs | Public | Help hub |
| 2 | `about.php` | Business case and company story (rubric business paragraph) | Public | Help hub |
| 3 | `catalogue.php` | Product grid with filters/sort entry points | Public | `help/catalogue.html` |
| 4 | `product.php` | Single product detail, options, price, stock, reviews section | Public | `help/catalogue.html` |
| 5 | `search.php` | Search results by name, category, brand, description | Public | `help/catalogue.html` |
| 6 | `compare.php` | Side-by-side comparison of selected products | Public | `help/catalogue.html` |
| 7 | `builder.php` | Multi-step custom PC builder form | Public (save requires login) | `help/pc-builder.html` |
| 8 | `builder-results.php` | Build summary: components, price, compatibility, estimates | Public | `help/pc-builder.html` |
| 9 | `reviews.php` | Review listing / submission entry for products | Public read; submit as customer | `help/support.html` |
| 10 | `media.php` | Multimedia learning centre (video/audio) | Public | Help hub |
| 11 | `store-locations.php` | Interactive map + text address fallback | Public | Help hub |
| 12 | `contact.php` | General contact / support message form | Guest form | `help/support.html` |
| 13 | `privacy.php` | Privacy policy (academic site practices) | Public | Help hub |
| 14 | `accessibility.php` | Accessibility statement and tips | Public | Help hub |
| 15 | `register.php` | Customer registration | Guest form | `help/accounts.html` |
| 16 | `login.php` | Customer/admin login | Guest form | `help/accounts.html` |
| 17 | `logout.php` | End session and redirect | Public (session clear) | `help/accounts.html` |

**Public subtotal: 17 dynamic pages**

---

## 3. Private customer pages (dynamic PHP)

| # | File | Purpose | Access | Context-sensitive Help |
| - | ---- | ------- | ------ | ---------------------- |
| 18 | `profile.php` | Account dashboard and activity summary | Customer | `help/accounts.html` |
| 19 | `edit-profile.php` | Edit name, email, phone, address, password | Customer | `help/accounts.html` |
| 20 | `saved-builds.php` | List saved custom builds | Customer | `help/pc-builder.html` |
| 21 | `saved-build.php` | View / rename / edit / delete one saved build; add to cart | Customer | `help/pc-builder.html` |
| 22 | `wishlist.php` | Wishlist manage; move items to cart | Customer | `help/catalogue.html` |
| 23 | `cart.php` | Shopping cart for products and custom builds | Customer | `help/orders.html` |
| 24 | `checkout.php` | Simulated checkout (shipping + payment-method label only) | Customer | `help/orders.html` |
| 25 | `order-confirmation.php` | Confirmation number and order summary | Customer | `help/orders.html` |
| 26 | `order-history.php` | Current user’s past orders | Customer | `help/orders.html` |
| 27 | `order-details.php` | Itemized single order (owner-only) | Customer | `help/orders.html` |
| 28 | `consultation.php` | PC consultation request + attachment upload | Customer | `help/support.html` |
| 29 | `consultation-history.php` | Customer’s requests and admin responses | Customer | `help/support.html` |

**Private customer subtotal: 12 dynamic pages**  
**Customer-facing total (public + private): 29**

---

## 4. Administrator pages (dynamic PHP)

All routes live under `admin/` and require administrator authorization.

| # | File | Purpose | Access |
| - | ---- | ------- | ------ |
| 30 | `admin/index.php` | Admin dashboard: counts, alerts, summaries | Admin |
| 31 | `admin/products.php` | List / search products; disable controls | Admin |
| 32 | `admin/product-add.php` | Create product record | Admin |
| 33 | `admin/product-edit.php` | Edit product, stock, price, image, status | Admin |
| 34 | `admin/product-options.php` | Manage options for a product | Admin |
| 35 | `admin/compatibility.php` | Edit simplified compatibility metadata | Admin |
| 36 | `admin/orders.php` | Search and list all orders | Admin |
| 37 | `admin/order-details.php` | Order detail, status, notes | Admin |
| 38 | `admin/users.php` | Search users; disable / re-enable | Admin |
| 39 | `admin/user-edit.php` | Inspect / update user account admin fields | Admin |
| 40 | `admin/consultations.php` | Review requests, respond, manage status | Admin |
| 41 | `admin/reviews.php` | Moderate reviews (approve / hide / delete) | Admin |
| 42 | `admin/themes.php` | Select and save active site theme | Admin |
| 43 | `admin/reports.php` | Charts for orders, products, users, inventory | Admin |
| 44 | `admin/monitoring.php` | Health checks: online / warning / offline | Admin |

**Admin subtotal: 15 dynamic pages**

---

## 5. API endpoints (dynamic PHP)

Lightweight endpoints used by JavaScript. They count toward the dynamic PHP file total and must use the same security rules (sessions/CSRF where state-changing, prepared statements, escaped output as applicable).

| # | File | Purpose | Access |
| - | ---- | ------- | ------ |
| 45 | `api/builder-price.php` | Trusted server-side price recalculation | Public POST with validation |
| 46 | `api/compatibility-check.php` | Server-side compatibility result (compatible / warning / incompatible) | Public POST with validation |
| 47 | `api/product-search.php` | JSON/HTML fragment search support for catalogue UI | Public GET |
| 48 | `api/chart-data.php` | Data for public and admin charts | Public or admin as appropriate |

**API subtotal: 4 dynamic pages**  
**Grand total dynamic PHP: 48**

---

## 6. Static Help wiki and training (HTML)

| # | File | Purpose | Linked from |
| - | ---- | ------- | ----------- |
| S1 | `help/index.html` | Help centre hub / navigation | Main nav, footer |
| S2 | `help/accounts.html` | Registration, login, profile, disabled accounts | `register.php`, `login.php`, `profile.php`, `edit-profile.php` |
| S3 | `help/catalogue.html` | Catalogue, search, filter, compare, options | `catalogue.php`, `product.php`, `search.php`, `compare.php`, `wishlist.php` |
| S4 | `help/pc-builder.html` | Builder steps, compatibility, pricing, saved builds | `builder.php`, `builder-results.php`, `saved-builds.php`, `saved-build.php` |
| S5 | `help/orders.html` | Cart, checkout, status, order history | `cart.php`, `checkout.php`, `order-*.php` |
| S6 | `help/support.html` | Consultation, attachments, reviews, contact | `consultation.php`, `consultation-history.php`, `reviews.php`, `contact.php` |
| S7 | `help/training.html` | Step-by-step end-user walkthrough (account → order) | Help hub, About, footer |

**Static subtotal: 7 pages** (assignment requires ≥ 5 distinct Help pages; hub + training included for clarity)

---

## 7. Shared includes and assets (not separate “pages”)

These support many routes but are **not** counted as unique pages:

| Path | Role |
| ---- | ---- |
| `includes/header.php`, `footer.php`, `navigation.php` | Shared layout and responsive menu |
| `includes/auth.php`, `admin-auth.php`, `csrf.php`, `validation.php`, `flash.php`, `functions.php` | Security and helpers |
| `assets/css/main.css`, `admin.css`, `print.css` | Base styles |
| `assets/themes/*.css` | Three switchable templates |
| `assets/js/*.js` | Main, builder, cart, validation, charts, map |
| `config/app.php`, `config/database.php` | Configuration |
| `uploads/` | User/admin uploads (not browsable catalogue pages) |

---

## 8. SEO and package files (not counted as content pages)

| File | Role |
| ---- | ---- |
| `sitemap.xml` | Public URL list for search engines (Stage 14) — **no** private/admin URLs |
| `robots.txt` | Crawler rules |
| `README.md`, `docs/*` | Documentation package |
| `database/*.sql` | Schema and seeds |

---

## 9. Primary navigation map (customer)

```text
Home
About
Catalogue ──► Product detail
              Search
              Compare
PC Builder ──► Build results ──► Save build (customer)
Learning Centre (Media)
Store Locations
Help ──► Accounts | Catalogue | Builder | Orders | Support | Training
Contact

Account (when logged in)
  Profile / Edit profile
  Saved builds
  Wishlist
  Cart ──► Checkout ──► Confirmation
  Orders / Order details
  Consultations / History
  Log out

Account (when logged out)
  Register | Log in
```

---

## 10. Administrator navigation map

```text
Dashboard
Products ──► Add / Edit / Options
Compatibility
Orders ──► Order details
Users ──► User edit
Consultations
Reviews
Themes
Reports
Monitoring
(Return to storefront)
```

---

## 11. Core user flows (page sequences)

| Flow | Sequence |
| ---- | -------- |
| Browse to purchase | `catalogue.php` → `product.php` → `cart.php` → `checkout.php` → `order-confirmation.php` → `order-history.php` → `order-details.php` |
| Custom build | `builder.php` → `builder-results.php` → `saved-builds.php` / `cart.php` |
| Account setup | `register.php` → `login.php` → `profile.php` → `edit-profile.php` |
| Support | `consultation.php` → `consultation-history.php` (admin replies in `admin/consultations.php`) |
| Theme change | `admin/themes.php` → any public page reflects active theme |

---

## 12. Page-count verification checklist

Use this before claiming rubric #10a / B3 complete:

- [ ] At least **20** distinct dynamic `.php` files exist and each serves a real purpose  
- [ ] Prefer confirming **48** planned files (or an updated count if the list changes)  
- [ ] At least **5** separate static Help `.html` files exist  
- [ ] Context-sensitive Help links match Section 2–3 and Section 6  
- [ ] `sitemap.xml` (SEO) lists only public URLs  
- [ ] No duplicate “shell” pages created only to inflate counts  

**Commit 0.4 acceptance:** This document plans **48 dynamic PHP pages** and **7 static Help/training pages**, which exceeds “at least 20 dynamic and five static pages.”

---

## 13. Status

**Commit 0.4 complete (documentation).** Implementation of these routes begins in Stage 1 (shared layout) and continues through Stages 3–13. Update this file if a route is renamed, merged, or split—and mirror the change in `docs/rubric-checklist.md`.
