# CustomCore — Complete Installation Guide

**Document type:** Stage 12 documentation (Commit 12.4)
**Purpose:** Let a new developer or grader install CustomCore from a clean checkout to a fully working site — locally or on a fresh server — without relying on undocumented steps.
**Audience:** Developers and graders with basic command-line and MySQL familiarity.
**Related:** database detail in [`docs/database-import.md`](database-import.md); config in [`config/README.md`](../config/README.md); production/hosting specifics and problem-solving in [`docs/deployment-troubleshooting.md`](deployment-troubleshooting.md).

> Live-hosting specifics (for example `myweb.cs.uwindsor.ca`) and a troubleshooting table live in the companion **[deployment & troubleshooting guide](deployment-troubleshooting.md)**. This document gets you running from scratch; that one gets you online and unstuck.

---

## 1. Requirements

| Component | Minimum | Notes |
| --------- | ------- | ----- |
| PHP | 8.0+ | Uses typed code, `str_contains`, `match`-era syntax; PDO + `finfo` extensions required. `declare(strict_types=1)` throughout. |
| MySQL / MariaDB | InnoDB, `utf8mb4` | Foreign keys and `utf8mb4_unicode_ci` are used. |
| Web server | Apache, Nginx, or PHP's built-in server | Plain `.php` URLs — **no URL rewriting required**. |
| Git | any recent version | To clone the repository. |
| Command line | `php` and `mysql` on PATH | For the connection test, DB import, and admin creation. |

CustomCore intentionally uses **no** Composer, Node, Docker, or build step. There are no dependencies to install beyond PHP + MySQL. Chart.js and Leaflet load from a CDN only on the pages that need them.

---

## 2. Get the code

```bash
git clone <your-repository-url> customcore
cd customcore
```

Confirm the expected top-level folders exist (`admin/`, `api/`, `assets/`, `config/`, `database/`, `docs/`, `help/`, `includes/`, `uploads/`) — see [`docs/directory-structure.md`](directory-structure.md).

---

## 3. Configure the database credentials

The real credentials file is **gitignored** and never committed. Create it from the template:

```bash
cp config/database.example.php config/database.php
```

Edit `config/database.php` and set:

- `host` (often `localhost`) and `port` (default `3306`)
- `dbname` (the database you will create in §4)
- `username` and `password`
- leave `charset` as `utf8mb4`

While you are here, review the non-secret `config/app.php`:

- `debug` → keep **`false`** for anything public (only turn on temporarily for local debugging).
- `base_url` → leave empty to use relative URLs (recommended); set it only if a host needs absolute links. When set, absolute SEO URLs (canonical tags and `sitemap.php`) use it — regenerate the static snapshot with `php sitemap.php --write` after changing it.
- `timezone`, `session_*` timeouts, `default_theme`, upload limits, and `store_location` can be adjusted later (see [`docs/content-update-guide.md`](content-update-guide.md)).

---

## 4. Create the MySQL database

```sql
CREATE DATABASE customcore
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Grant your application user privileges on that database (host-specific). Then verify PHP can connect:

```bash
php database/test-connection.php
```

Expected: `CustomCore database connection: OK` (the password is never printed). If this fails, fix credentials/host before continuing — see [deployment & troubleshooting](deployment-troubleshooting.md).

---

## 5. Import the schema and seed data

Run from the **project root**, substituting your username and database name (you'll be prompted for the MySQL password). Order matters — schema first, then seeds:

```bash
# 1. Schema — all tables, keys, indexes
mysql -u your_username -p your_database_name < database/schema.sql

# 2. Catalogue categories + 20 products
mysql -u your_username -p your_database_name < database/seed-products.sql

# 3. Product options (>= 2 per product)
mysql -u your_username -p your_database_name < database/seed-product-options.sql

# 4. Builder categories + 60 components
mysql -u your_username -p your_database_name < database/seed-components.sql

# 5. Compatibility rules (7 checks)
mysql -u your_username -p your_database_name < database/seed-compatibility.sql

# 6. Themes + site settings (default theme = RGB Gaming)
mysql -u your_username -p your_database_name < database/seed-themes.sql

# 7. Demo approved reviews (recommended for catalogue UI)
mysql -u your_username -p your_database_name < database/seed-reviews.sql
```

Full per-file detail, re-seeding notes, and verification queries are in [`docs/database-import.md`](database-import.md).

---

## 6. Create an administrator account

```bash
php database/create-admin.php
```

Answer the prompts (email, name, password ≥ 8 characters). The password is stored as a bcrypt hash — no plain text is ever saved. This is required to reach the `admin/` back office.

---

## 7. Prepare the upload directories

CustomCore writes uploaded files to two folders that ship with `.gitkeep` placeholders (their contents are gitignored):

- `uploads/products/` — administrator product images
- `uploads/consultation/` — customer consultation attachments

Ensure both exist and are **writable by the web server user**:

```bash
mkdir -p uploads/products uploads/consultation
chmod -R u+rwX uploads
```

Each upload directory is protected by an `index.php` guard so it cannot be browsed directly, and files are validated (real MIME type + size) before being stored.

---

## 8. Run the site

### Option A — PHP built-in server (quickest for local dev)

```bash
php -S localhost:8000
```

Open <http://localhost:8000/index.php>.

### Option B — Apache / Nginx

Point the document root (or a user directory) at the project folder. No rewrite rules are needed because the app uses real `.php` URLs. On shared hosting, copy the whole project into your web space (for example `public_html/customcore/`) and browse to the corresponding URL.

---

## 9. Verify the installation

Work through this quick smoke test:

- [ ] `php database/test-connection.php` prints `OK`.
- [ ] Homepage (`index.php`) loads with featured products and styling.
- [ ] Catalogue (`catalogue.php`) shows ≥ 20 products and the "at a glance" tier chart (with a data table beside it).
- [ ] A product page (`product.php?id=…`) shows options and price adjustments.
- [ ] Register a test customer, log in, and confirm the nav switches to the logged-in state.
- [ ] PC Builder (`builder.php`) updates the live total and shows compatibility messages.
- [ ] Add to cart → checkout → order confirmation produces an order number (`CC-YYYYMMDD-XXXXXX`).
- [ ] Log in as the admin and open the dashboard (`admin/index.php`) — live counts appear.
- [ ] Admin → Themes: switch theme and confirm the public site restyles immediately.
- [ ] The main menu collapses to a working toggle below 900px (resize the window).
- [ ] View source on a public page: `main.css` is linked, then the theme CSS last; `main.js` is linked in the footer.

Deeper database verification queries (table counts, options per product, rules, themes) are in [`docs/database-import.md`](database-import.md) §3.

---

## 10. Post-install checklist

- [ ] `config/database.php` created and **not** committed (it is gitignored).
- [ ] `config/app.php → debug` is `false` for anything reachable publicly.
- [ ] Admin account created via `create-admin.php`.
- [ ] `uploads/products/` and `uploads/consultation/` exist and are writable.
- [ ] Store details in `config/app.php → store_location` updated if needed.
- [ ] A backup routine is understood (see [`docs/database-import.md`](database-import.md) §5).
- [ ] `robots.txt` and `sitemap.xml` / `sitemap.php` are present at the project root (private/admin routes are excluded).

---

## 11. Updating an existing install

To pull new code without losing data:

```bash
git pull
```

`config/database.php` and `uploads/*` are gitignored, so a pull never overwrites your credentials or uploaded files. If a change adds new tables or columns, re-run the relevant `database/*.sql` file (the catalogue/options/components/themes seeds are safe to re-run; always run `schema.sql` first on a fresh database). Back up before applying schema changes to a populated database.

---

## 12. Status

**Commit 12.4 complete.** A clean checkout can be installed end-to-end — requirements, code, credentials, database creation, schema + seed import, secure admin creation, writable uploads, running locally or on a server, and a verification smoke test — using only documented steps and no build tooling. Supports rubric row **#5 (well-documented setup)** and **B14 (installation documentation for another server)**.
