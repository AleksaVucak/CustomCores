# CustomCore | Monitoring & Troubleshooting Guide

**Document type:** Project documentation
**Purpose:** Explain the administrator monitoring dashboard and give a practical, symptom‑driven troubleshooting reference for every health check it reports.
**Audience:** Administrators and whoever maintains the live site.
**Related:** admin tasks in [`docs/administrator-guide.md`](administrator-guide.md); first‑time setup in [`docs/installation-guide.md`](installation-guide.md); server deployment in [`docs/deployment-troubleshooting.md`](deployment-troubleshooting.md); database work in [`docs/database-import.md`](database-import.md).

---

## 1. What the monitoring page is

The monitoring dashboard is the site's **backend health page**. It answers one question at a glance: *is the site healthy right now, and if not, what exactly is wrong?*

- **URL:** `admin/monitoring.php`
- **Access:** administrators only. The page is behind `customcore_require_admin`, which uses **session state**, so it still loads even when the database is offline.
- **How to reach it:** sign in as an admin, then use the **Monitoring** link in the admin navigation or the **Monitoring** tool card on the admin dashboard. (The tool card lights up automatically because the tool registry detects the page file.)

The engine that powers the page lives in [`includes/monitoring.php`](../includes/monitoring.php). Each check is completely independent and is written so it **can never throw** — a failing dependency downgrades only its own row instead of taking down the whole page.

---

## 2. How to read the dashboard

### 2.1 Overall status banner

At the top, a banner shows the **worst** status across all checks, plus per‑status counts and the time the report was generated. If any single check is `offline`, the overall status is `offline`; if the worst is a `warning`, the overall is `warning`; otherwise `online`.

### 2.2 Status vocabulary

| Status | Meaning | Site impact |
| --- | --- | --- |
| **Online** | The service is fully operational. | None. |
| **Warning** | Degraded, or a non‑critical dependency is missing. | The core site still works (e.g. uploads not writable, a media file missing). |
| **Offline** | A critical dependency is unavailable. | Core functionality is affected (e.g. the database is down). |

### 2.3 Per‑service table

Each row shows a **service**, its **status badge**, a one‑line **summary**, and a short list of **safe details**. There are seven checks (see §3).

### 2.4 Live statistics panel

Below the health table, the **Live statistics** panel shows current counts (products, users, orders, consultation requests, images, and stock). It is loaded **separately** from the health‑check table: if the database is unavailable it shows a safe warning and still displays filesystem‑based image/media counts, without blanking the status rows above.

### 2.5 Why details look generic

Messages are deliberately **production‑safe**: they never expose passwords, DSN details, absolute filesystem paths, or stack traces. Database errors reuse `customcore_database_error_message` and every dynamic error string additionally passes through `customcore_monitoring_safe_message`, which strips traces, absolute paths, and credential fragments even in debug mode. If you need more detail while diagnosing locally, see §5.

---

## 3. Per‑service troubleshooting

Each subsection lists **what the check verifies**, the **statuses it can report** (with the exact message wording), the **likely cause**, and the **fix**.

### 3.1 PHP runtime

**Verifies:** PHP version (≥ 8.0), the `pdo` + `pdo_mysql` drivers, and the recommended `fileinfo` and `session` extensions.

| Status | Message you may see | Likely cause | Fix |
| --- | --- | --- | --- |
| Offline | `PHP 8.0 or newer is required.` | Server is running an old PHP. | Upgrade PHP, or select PHP 8.0+ in your host's control panel. |
| Offline | `The PDO MySQL extension is not available.` | `pdo_mysql` not enabled. | Enable `pdo`/`pdo_mysql` in `php.ini` (uncomment `extension=pdo_mysql`) and restart PHP. |
| Offline | `The session extension is not available.` | Session support disabled. | Enable the `session` extension in `php.ini`. |
| Warning | `fileinfo is missing; secure file uploads are disabled.` | `fileinfo` extension not loaded. | Enable `extension=fileinfo` so real‑MIME upload validation works. |
| Online | `PHP runtime and required extensions are present.` | — | No action. |

**Details row** always lists the PHP version and whether each extension is `loaded`/`missing`.

### 3.2 Database (MySQL)

**Verifies:** opens the shared PDO connection (`customcore_pdo`) and runs `SELECT 1`.

| Status | Message you may see | Likely cause | Fix |
| --- | --- | --- | --- |
| Offline | `The database is temporarily unavailable.` (or a sanitized detail) | MySQL is down, wrong credentials, wrong host/DB name, or the DB user lacks access. | Confirm MySQL is running; re‑check `config/database.php` (host, dbname, user, password); verify the database exists and the user has privileges. See [`docs/installation-guide.md`](installation-guide.md) §4–5 and [`docs/deployment-troubleshooting.md`](deployment-troubleshooting.md). |
| Offline | `The database connection opened but the test query failed.` | Connection succeeded but the server rejected a trivial query. | Check MySQL server health/logs and that the account can run `SELECT`. |
| Online | `Connected and responding to queries.` | — | No action. |

> The exact credentials and DSN are **never** shown here — only a safe message. To see the underlying error locally, enable debug (§5).

### 3.3 Sessions

**Verifies:** session support is available and, if a custom save path is configured, that it is writable. The save path itself is **never printed**.

| Status | Message you may see | Likely cause | Fix |
| --- | --- | --- | --- |
| Offline | `PHP sessions are disabled on this server.` | `session.save_handler` disabled. | Enable sessions in `php.ini`. |
| Warning | `The session storage location is not writable; logins may not persist.` | The configured `session.save_path` is read‑only or missing. | Make the session save directory writable by the web server user, or point `session.save_path` at a writable location. |
| Online | `Session handling is operational.` | — | No action. |

### 3.4 Core files

**Verifies:** all critical includes, config, and base assets exist (e.g. `config/app.php`, `config/database.php`, `includes/*.php`, `assets/css/main.css`, `assets/js/main.js`), plus a few recommended admin files. Only **relative** project paths are ever reported.

| Status | Message you may see | Likely cause | Fix |
| --- | --- | --- | --- |
| Offline | `N critical file(s) are missing.` (details list `Missing: <relative path>`) | Incomplete upload/checkout, or a file was deleted. | Re‑deploy the missing files from Git; confirm the transfer completed. `config/database.php` is created per environment — copy it from `config/database.example.php` and fill in credentials. |
| Warning | `N recommended file(s) are missing.` | An admin helper/stylesheet is absent. | Re‑deploy `assets/css/admin.css`, `includes/admin.php`, or `includes/admin-auth.php` as listed. |
| Online | `All critical application files are present.` | — | No action. |

### 3.5 Upload storage

**Verifies:** the product image and consultation attachment upload directories (from `config/app.php → paths`) exist and are writable. A problem here degrades a feature but not the core site, so it is a **warning**.

| Status | Message you may see | Likely cause | Fix |
| --- | --- | --- | --- |
| Warning | `<label>: directory missing (<relative path>).` | The upload folder was never created (they are gitignored). | Create the directory, e.g. `uploads/products` and `uploads/consultation`. See [`docs/installation-guide.md`](installation-guide.md) §7. |
| Warning | `<label>: not writable (<relative path>).` | Wrong permissions/owner. | Make the directory writable by the web server user (e.g. `chmod`/`chown` as your host requires). |
| Online | `Upload directories exist and are writable.` | — | No action. |

### 3.6 Site theme

**Verifies:** the active theme stylesheet resolves to a real file. Because base `main.css` is always linked, a missing theme is only a **warning**.

| Status | Message you may see | Likely cause | Fix |
| --- | --- | --- | --- |
| Warning | `No theme stylesheet resolved; the site falls back to base styles only.` | The active theme file was renamed/removed, or none is set. | Re‑select a theme in the admin themes page, or restore the missing stylesheet under `assets/css/themes/`. |
| Online | `The active theme stylesheet is present and valid.` | — | No action. |

The details row reports how many theme stylesheets are available and the active theme's file name.

### 3.7 Learning Centre media

**Verifies:** compares the declared media catalogue (`customcore_media_catalogue`) against files on disk — primary media file, poster image, and caption track. Missing media degrades the Learning Centre only, so it is a **warning**.

| Status | Message you may see | Likely cause | Fix |
| --- | --- | --- | --- |
| Warning | `N of M media lesson file(s) are missing.` (details list the lesson titles) | A declared video/audio file is not on disk. | Add the missing file under the media directory, or remove/adjust the entry. See [`docs/content-update-guide.md`](content-update-guide.md). |
| Warning | `N supporting media asset(s) (posters/captions) are missing.` | A poster image or captions track is absent. | Add the missing poster/caption file referenced by the lesson. |
| Warning | `No Learning Centre media lessons are declared.` | The catalogue is empty. | Declare lessons in `includes/media.php` if the Learning Centre is expected to have content. |
| Online | `All N media lesson(s) and their assets are present.` | — | No action. |

---

## 4. Live statistics panel

The panel reuses `customcore_admin_dashboard_stats` for database counts, so the numbers match the admin dashboard exactly. Image and media counts are read from disk.

| Symptom | Cause | Fix |
| --- | --- | --- |
| A warning flash: live database statistics are unavailable, but image/media counts still show. | The database is offline (see §3.2). | Restore the database; the counts return automatically. Image/media counts are filesystem‑based and remain accurate meanwhile. |
| Product image counts look low. | Seeded (`assets/images/products`) and uploaded (`uploads/products`) images are counted separately then summed; the uploads folder may be missing. | Confirm the upload directory exists (see §3.5). |
| Stats numbers differ from the admin dashboard. | Should not happen — both use the same aggregate query. | Reload; if it persists, verify you are viewing the same environment/database. |

---

## 5. Getting more detail while diagnosing (developers)

On a live site, `config/app.php → debug` must stay **`false`** so messages remain generic and safe. **Locally**, you can temporarily set `debug` to `true` to see a **sanitized** detail line for failing checks (still stripped of passwords, absolute paths, and stack traces by `customcore_monitoring_safe_message`).

For the raw underlying error (e.g. the exact PDO message), check the **server error log** rather than the page — the monitoring page never prints stack traces by design.

You can also run the engine from the command line for a quick check:

```bash
php -r 'require "includes/monitoring.php"; $r = customcore_monitoring_run; echo $r["overall"], "\n"; foreach ($r["checks"] as $c) { echo str_pad($c["status"],8), $c["label"], " — ", $c["summary"], "\n"; }'
```

Remember the CLI uses `php.ini` / credentials for the command‑line environment, which may differ from the web server.

---

## 6. Quick reference

| Service | Warning means | Offline means |
| --- | --- | --- |
| PHP runtime | `fileinfo` missing (uploads validation off) | Old PHP, or `pdo_mysql`/`session` missing |
| Database (MySQL) | — | Cannot connect or query |
| Sessions | Save path not writable | Sessions disabled |
| Core files | Recommended admin file missing | Critical file missing |
| Upload storage | Directory missing or not writable | — |
| Site theme | Active theme not resolved (base styles only) | — |
| Learning Centre media | A media/poster/caption file is missing | — |

**Golden rules**

- The monitoring page loads even when the database is offline — use it *because* something is broken, not only when everything is fine.
- Warnings mean the core site still works; fix them at your convenience. Offline means act now.
- Never turn on `debug` in production to read an error — check the server log instead.
