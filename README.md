# CustomCore

**Aleksa Vucak | 110139920**
**COMP 3340, Final Project**
**August 5th, 2026**

**Live URL:** TBD

---

## What This Project Is

CustomCore is a custom gaming PC store built as a database driven PHP website. Visitors can browse a
catalogue of prebuilt systems, compare them, read and write reviews, and design a machine from
individual parts in a step by step PC Builder that checks part compatibility and prices the build as
it is assembled. Customers can register, save builds, keep a wishlist, request a consultation, and
place a simulated order. Staff get a separate protected back office for managing the catalogue,
orders, users, consultations, reviews, reports, themes, and service health.

Everything runs on plain PHP, MySQL, HTML, CSS, and vanilla JavaScript. There is no Composer, no
Node, no framework, and no build step, so the project can be copied onto a standard PHP host and run
as is. Chart.js and Leaflet are loaded from a CDN only on the pages that need a chart or a map.

Checkout is simulated for coursework. The order record stores a payment method label only and never
touches real card data.

---

## Requirements

| Component | Minimum | Notes |
| --------- | ------- | ----- |
| PHP | 8.0 or newer | Uses `declare(strict_types=1)` throughout. The PDO and `finfo` extensions are required. |
| MySQL or MariaDB | InnoDB with `utf8mb4` | Foreign keys and `utf8mb4_unicode_ci` are used. |
| Web server | Apache, Nginx, or the PHP built in server | Plain `.php` URLs, so no rewrite rules are needed. |
| Command line | `php` and `mysql` on the PATH | Needed for the database import and admin creation. |

For production hosting (including `myweb.cs.uwindsor.ca`), permissions, enabled PHP modules,
browser support, and a pre-upload host checklist, use the full
[production server requirements](docs/production-requirements.md) document.

How to keep secrets out of Git, set production flags, and configure storage paths is in
[production configuration](docs/production-configuration.md).

---

## Install

**1. Get the code and enter the folder.**

```bash
git clone <your-repository-url> customcore
cd customcore
```

**2. Create the database credentials file.** The real file is gitignored and never committed, so copy
the template and fill in your own values.

```bash
cp config/database.example.php config/database.php
```

**3. Create an empty database** using `utf8mb4` and the `utf8mb4_unicode_ci` collation.

**4. Import the schema, then the seed data.** Order matters, because the seeds depend on the tables
and on each other.

```bash
mysql -u your_username -p your_database_name < database/schema.sql
mysql -u your_username -p your_database_name < database/seed-products.sql
mysql -u your_username -p your_database_name < database/seed-product-options.sql
mysql -u your_username -p your_database_name < database/seed-components.sql
mysql -u your_username -p your_database_name < database/seed-compatibility.sql
mysql -u your_username -p your_database_name < database/seed-themes.sql
mysql -u your_username -p your_database_name < database/seed-reviews.sql
```

**5. Confirm the connection works.**

```bash
php database/test-connection.php
```

**6. Make the upload folders writable** so admin product images and consultation attachments can be
saved.

```bash
chmod 755 uploads uploads/products uploads/consultation
```

**7. Run the site.** For local work the built in server is quickest.

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/`. On Apache or Nginx, point the document root at the project folder
instead. Step by step detail, verification queries, and fixes for common problems live in the
[installation guide](docs/installation-guide.md) and the
[deployment and troubleshooting guide](docs/deployment-troubleshooting.md).

---

## Create An Administrator

The back office is closed to normal accounts, so the first administrator is created from the command
line rather than through the website.

```bash
php database/create-admin.php
```

Answer the prompts for email, name, and a password of at least 8 characters. The password is stored
as a bcrypt hash and is never written in plain text. Sign in at `login.php` and the administrator
navigation appears, starting at `admin/index.php`. Once one administrator exists, that account can
promote others from `admin/users.php`.

---

## Feature Overview

**Storefront**

- Catalogue of 20 configurable prebuilt systems across four tiers, with filters, sorting, search, and
  a side by side comparison view.
- Product pages with option groups, price adjustments, stock awareness, and approved customer
  reviews.
- A Learning Centre with playable video and audio lessons plus captions, and an interactive store and
  service map.
- A catalogue data visualisation and a builder performance chart, each with an accessible text or
  table fallback.

**PC Builder**

- Step by step selection across component categories, one step per category, with optional steps that
  can be skipped.
- Live subtotal and running total, plus server checked compatibility covering socket, memory type,
  form factor, power supply headroom, cooler and card clearance, and storage.
- A build summary page that can be saved to an account and later added to the cart.

**Accounts And Orders**

- Registration, login, profile editing, and a private account area.
- Cart supporting both catalogue products and saved custom builds, with stock aware quantities.
- Simulated checkout, an order confirmation with a full build snapshot, order history, and order
  detail pages.
- Wishlist, review submission, consultation requests with file attachments, and consultation history.

**Administration**

- Dashboard with live counts, attention alerts, and recent activity.
- Product create and edit with image upload, plus option group management and compatibility rule
  management.
- Order status and note management, user administration with self lockout and last administrator
  protection, consultation replies, and review moderation.
- Reports with charts backed by MySQL, a sitewide theme switcher offering three complete themes, and a
  service monitoring page reporting online, warning, or offline per check.

**Across The Site**

- Session hardening, CSRF protection on every state changing form, prepared statements everywhere,
  and content checked file uploads served through guarded endpoints.
- A seven page Help wiki with context sensitive links from the matching feature pages.
- Responsive layouts verified on desktop and mobile widths, an accessibility statement, and reduced
  motion and increased contrast support.

---

## Layout Overview

```
customcore/
├── admin/          Protected administrator pages
├── api/            Small JSON endpoints for builder price, compatibility, and chart data
├── assets/
│   ├── css/        main.css for the storefront, admin.css for the back office
│   ├── themes/     The three switchable site themes
│   ├── js/         Vanilla JavaScript modules, one per feature
│   ├── images/     Catalogue, component, and page imagery
│   └── media/      Learning Centre video and audio plus captions
├── config/         app.php settings and the gitignored database.php credentials
├── database/       schema.sql, seed files, and the create-admin script
├── docs/           Project documentation and QA records
├── help/           Static Help wiki, one HTML page per topic
├── includes/       Shared layout, helpers, auth, and per feature logic
├── uploads/        User supplied files, blocked from direct web access
└── *.php           Public and customer facing pages
```

The site is 50 purposeful dynamic PHP pages: 30 public and customer pages, 17 administrator pages, and
3 API endpoints, backed by a 21 table MySQL schema. Full detail is in
[directory-structure.md](docs/directory-structure.md).

---

## Key Documentation

**Setting Up And Running**

- [Production server requirements](docs/production-requirements.md)
- [Production configuration (secrets and paths)](docs/production-configuration.md)
- [Installation guide](docs/installation-guide.md)
- [Database import guide](docs/database-import.md)
- [Deployment and troubleshooting](docs/deployment-troubleshooting.md)

**Using And Maintaining The Site**

- [Administrator guide](docs/administrator-guide.md)
- [Content update guide](docs/content-update-guide.md)
- [Monitoring troubleshooting](docs/monitoring-troubleshooting.md)

**Design And Architecture**

- [Business case](docs/business-case.md)
- [Database design](docs/database-design.md)
- [Front end documentation](docs/frontend-documentation.md)
- [Sitemap](docs/sitemap.md)
- [Wireframes](docs/wireframes.md)
- [Security audit](docs/security-audit.md)

**Testing And Quality Records**

- [Rubric checklist](docs/rubric-checklist.md) and [rubric audit](docs/rubric-audit.md)
- [HTML validation](docs/html-validation.md), [CSS validation](docs/css-validation.md),
  [JavaScript validation](docs/js-validation.md)
- [Desktop responsiveness](docs/responsiveness-desktop.md) and
  [mobile responsiveness](docs/responsiveness-mobile.md)
- [Customer workflows](docs/customer-workflows.md) and
  [administrator workflows](docs/admin-workflows.md)
- [Theme testing](docs/theme-testing.md) and [final defect fixes](docs/final-defect-fixes.md)

---

## Notes For Graders

- Checkout is simulated. No payment gateway is contacted and no card data is stored.
- `config/database.php` is intentionally absent from version control. Copy
  `config/database.example.php` and add local credentials before running.
- Sample media and imagery are credited in [media-credits.md](docs/media-credits.md).
- The administrator area cannot be reached without an administrator account, so create one with
  `php database/create-admin.php` before reviewing those pages.
