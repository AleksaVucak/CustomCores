# CustomCore | Production Server Requirements

**Document type:** Project documentation
**Purpose:** State the exact server, database, permission, and browser requirements for hosting CustomCore in production, with University of Windsor shared hosting (`myweb.cs.uwindsor.ca`) as the primary target.
**Audience:** The student deploying the site, graders checking host suitability, and anyone installing CustomCore on a second server.
**Related:** install steps in [`installation-guide.md`](installation-guide.md); full upload checklist and troubleshooting in [`deployment-troubleshooting.md`](deployment-troubleshooting.md); credentials template in [`../config/database.example.php`](../config/database.example.php).

---

## 1. Why this document exists

CustomCore is built for ordinary university-style PHP and MySQL hosting. It does **not** need Composer, Node, Docker, a reverse-proxy rewrite layer, or a long-running process manager.

This page is the single requirements checklist for production. Use it before you open a host account, before you ask for a MySQL database, and before you upload files. The step-by-step upload and import sequence lives in the [deployment guide](deployment-troubleshooting.md); the deep local setup sequence lives in the [installation guide](installation-guide.md).

---

## 2. Target host

| Item | Expectation |
| ---- | ----------- |
| Primary target | `myweb.cs.uwindsor.ca` (or equivalent University of Windsor CS student web host) |
| Public URL shape | Typically `https://myweb.cs.uwindsor.ca/~yourUWinID/customcore/` for a subfolder deploy |
| Document root | The host web root (for example `public_html/`) or a folder under it |
| Alternative hosts | Any shared host that meets sections 3 through 6 also works: Apache or Nginx + PHP 8 + MySQL/MariaDB |

CustomCore uses depth-safe relative URLs via `customcore_url()`, so the project works at the domain root **or** in a `~user/customcore/` subfolder. Leave `config/app.php → base_url` empty unless you specifically need absolute SEO links.

---

## 3. PHP runtime

| Requirement | Minimum | Why it matters on this project |
| ----------- | ------- | ------------------------------ |
| PHP version | **8.0 or newer** | The codebase uses `declare(strict_types=1)`, typed parameters and returns, `str_contains`, `match`-era language features, and modern exception handling. |
| SAPI | Apache module, PHP-FPM, or CGI that runs `.php` files | Plain `.php` URLs only; no front controller rewrite rules. |
| `pdo` + `pdo_mysql` | Required | Every database query goes through PDO prepared statements (`includes/database.php`). |
| `fileinfo` (`finfo`) | Required | Product images and consultation attachments are accepted only after a content-based MIME check, not by file extension alone. |
| `mbstring` | Required | String handling, validation, and safe truncation across the storefront and admin UI. |
| `json` | Required | Builder price, compatibility, and chart API endpoints return JSON. |
| `session` | Required | Login sessions, cart badges, builder step state, CSRF tokens, and flash messages. |
| `filter` | Required | Input sanitization helpers (for example email validation). |
| `hash` / password hashing | Required | Bcrypt password hashes via `password_hash()` / `password_verify()`. |
| `iconv` | Recommended | Used when normalising a few string paths; hosts almost always ship it with `mbstring`. |
| `openssl` | Recommended | Preferred when the host terminates HTTPS so secure cookies can be set correctly. |
| `curl` | Not required by the app | No server-side outbound HTTP is required for core flows. Charts and the map load from a CDN in the browser only. |
| Composer / PEAR packages | **None** | There is no `vendor/` tree and no package manager step. |
| Node / npm / Webpack | **None** | JavaScript ships as plain files under `assets/js/`. |

### How to confirm on the host

If the host control panel lets you pick a PHP version, select **8.0 or higher** (8.1 / 8.2 / 8.3 are all fine). From SSH, when available:

```bash
php -v
php -m | grep -Ei 'pdo|pdo_mysql|fileinfo|mbstring|json|session|filter|openssl'
```

Every line above should report a version ≥ 8.0 and list at least `pdo`, `pdo_mysql`, `fileinfo`, `mbstring`, `json`, `session`, and `filter`.

If a required module is missing, enable it in the host panel (often labelled “Select PHP Version” / “Extensions”) or ask the host administrator. CustomCore will not run correctly without PDO MySQL or `fileinfo`.

---

## 4. MySQL / MariaDB

| Requirement | Minimum | Why it matters on this project |
| ----------- | ------- | ------------------------------ |
| Engine | **InnoDB** | Schema uses transactions and foreign keys (`database/schema.sql`). |
| Character set | **`utf8mb4`** | Full Unicode, including emoji-safe storage. |
| Collation | **`utf8mb4_unicode_ci`** (or host default compatible with `utf8mb4`) | Matches the schema and seed files. |
| Privileges for the app user | `SELECT`, `INSERT`, `UPDATE`, `DELETE` on the project database; `CREATE` / `DROP` if importing schema through that same user | The storefront and admin run day-to-day writes; schema import needs create rights once. |
| Access path | Usually `localhost` (or the host-supplied MySQL hostname) on port `3306` | Stored only in the gitignored `config/database.php`. |
| Seed / structure size | Small academic catalogue (tens of products, ~60 components, 7 compatibility rules, 3 themes) | Fits ordinary student MySQL quotas. |

Create an empty database first, then import `database/schema.sql` followed by the seed files **in the order** documented in [`installation-guide.md`](installation-guide.md) section 5. Never store real customer data or production passwords in Git or in seed files.

Verify from the project root on the host (or any machine that can reach the same MySQL instance):

```bash
php database/test-connection.php
# Expected: CustomCore database connection: OK
# The password is never printed.
```

---

## 5. Web server and filesystem permissions

| Requirement | Expectation |
| ----------- | ----------- |
| Web server | Apache is the most common match for `myweb.cs.uwindsor.ca`; Nginx is also fine. |
| Document handling | Serve `.php` through the PHP engine; serve static files from `assets/`, `help/`, and public roots directly. |
| URL rewriting | **Not required.** Do not depend on a project-wide `.htaccess` router. |
| Upload directories | `uploads/products/` and `uploads/consultation/` must exist and be **writable by the PHP process user**. |
| Directory modes | Directories typically `755` (or `775` if the host runs PHP under a shared group). |
| File modes | Application files typically `644`; secrets should not be world-writable. |
| Upload security files | Keep each upload folder’s `index.php` and `.htaccess` (where Apache honours them). They block directory listing and script execution under uploads. |
| Gitignored items created on the host | `config/database.php`; writable `uploads/products/` and `uploads/consultation/` contents |

Suggested permission commands after upload (adjust if your host uses a different ownership model):

```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R u+rwX uploads
```

**Never** commit production `config/database.php`. **Never** leave `config/app.php → debug` set to `true` on the live site. On the live copy, set `environment` to `production`.

Disk space is modest: source, documentation, seed images under `assets/images/`, and Learning Centre media under `assets/media/`. Leave headroom for admin product image uploads (default cap **2 MB** per file in `config/app.php → upload_max_bytes`).

---

## 6. Browser requirements

CustomCore is a progressive enhancement site: pages remain usable with JavaScript unavailable for reading content, but interactive features (builder live price, menu toggle, charts, map) need a modern browser.

| Audience | Supported baseline |
| -------- | ------------------ |
| Desktop | Current or recent **Chrome**, **Edge**, **Firefox**, or **Safari** |
| Mobile | Current iOS Safari and current Android Chrome (or the host browser equivalents) |
| Viewport | Phones from **320 px** wide through desktop **1920 px** (verified in responsiveness records) |
| JavaScript | ES2017-level features used by the vanilla modules in `assets/js/` (no bundler) |
| Cookies | Required for login sessions, CSRF, flash messages, and builder step memory |
| Media | Native `<video>` / `<audio>` with browser controls; WebVTT captions where provided |
| Optional CDN features | Chart.js and Leaflet are loaded only on pages that need charts or the store map. If a network policy blocks the CDN, the accessible table / text address fallbacks still work. |

No plugin (Flash, Java applets, ActiveX) is required. Screen readers and keyboard navigation are supported as documented in the accessibility statement and Help wiki.

---

## 7. Network and HTTPS

| Item | Production expectation |
| ---- | ---------------------- |
| Public HTTP(S) access | Visitors must reach the project folder without authenticating to the host shell |
| HTTPS | **Strongly recommended.** Session cookies receive the `Secure` flag automatically when the request is HTTPS (including when TLS is terminated upstream and `X-Forwarded-Proto: https` is set). |
| Outbound server HTTP | Not required for core application logic |
| Outbound browser requests | Used only for CDN chart/map assets and OpenStreetMap tiles on the locations page |

Session timeouts (idle 30 minutes, absolute 12 hours, session-id rotation every 15 minutes) are configured in `config/app.php` and apply on any host.

---

## 8. Features that do **not** need special host software

These are intentional so student hosting stays simple:

- No Composer, npm, or build pipeline
- No Redis, Memcached, or queue workers
- No mail server requirement for core demo flows (contact/consultation store into MySQL for the admin inbox)
- No payment gateway; checkout is simulated and stores a payment **method label** only
- No WebSocket server
- No mandatory cron jobs

---

## 9. Pre-upload host checklist

Confirm each row before transferring files to `myweb.cs.uwindsor.ca` (or another host):

- [ ] PHP **8.0+** is active for the account
- [ ] Extensions **pdo**, **pdo_mysql**, **fileinfo**, **mbstring**, **json**, **session**, and **filter** are enabled
- [ ] A MySQL or MariaDB database exists with **InnoDB** and **utf8mb4**
- [ ] You have the host name, database name, username, and password for `config/database.php`
- [ ] You know the public URL path (for example `/~yourUWinID/customcore/`)
- [ ] The web user can create files under `uploads/products/` and `uploads/consultation/`
- [ ] You can transfer files (SFTP, host file manager, or Git on the server)
- [ ] You can import SQL (SSH `mysql`, or phpMyAdmin / host MySQL panel)
- [ ] A modern desktop browser and a phone are available for post-deploy smoke tests

When every box is checked, continue with [deployment-troubleshooting.md](deployment-troubleshooting.md) sections 2 through 8.

---

## 10. Match to university myweb-style hosting

| Host trait (typical for CS student web) | CustomCore behaviour |
| --------------------------------------- | -------------------- |
| Shared Apache + PHP account | Plain `*.php` entry points; no rewrite rules required |
| Subfolder under `~userid/` | Relative `customcore_url()` links keep CSS, JS, images, and forms working |
| Separate MySQL credentials | Isolated in gitignored `config/database.php` |
| Limited shell or panel-only SQL | Seed files are plain `.sql` and import cleanly through phpMyAdmin in order |
| No root privileges | Application stays inside the project tree; no system packages needed |
| HTTPS on the university domain | Session hardening activates automatically when served over HTTPS |

If the host offers a **PHP version selector**, always pick 8.x. If MySQL is exposed only through a panel, import files in the exact seed order and create the first administrator with `php database/create-admin.php` over SSH when possible.

---

## 11. Related documents

| Document | Use it for |
| -------- | ---------- |
| [`production-configuration.md`](production-configuration.md) | Secret-free templates, paths, and verify-config |
| [`installation-guide.md`](installation-guide.md) | Clean checkout → working local or server install |
| [`deployment-troubleshooting.md`](deployment-troubleshooting.md) | Upload, production `app.php` flags, permissions, live smoke list, symptom table |
| [`database-import.md`](database-import.md) | Schema/seed order, verification queries, backup/restore |
| [`security-audit.md`](security-audit.md) | CSRF, sessions, uploads, and production error messaging |
| Project `README.md` | Course line, quick install, live URL (recorded after the site is public) |

---

## 12. Status

**Summary.** Production requirements for CustomCore are documented as an explicit host checklist: PHP 8.0+ with PDO MySQL, `fileinfo`, and `mbstring`; MySQL/MariaDB with InnoDB and `utf8mb4`; writable upload folders without world-exposed secrets; and modern desktop/mobile browsers with cookies and native media controls. The list is aligned with University of Windsor `myweb.cs.uwindsor.ca`-style shared hosting and with every runtime dependency the shipped code actually uses. Live file upload, database import, and the public URL itself are completed in the later deployment steps, not in this document.
