# CustomCore | Deployment & Troubleshooting Guide

**Document type:** Project documentation
**Purpose:** Take a working local install to a live server (with `myweb.cs.uwindsor.ca`-style shared PHP/MySQL hosting as the primary target) and provide a practical troubleshooting reference for the most likely problems.
**Audience:** Whoever deploys and maintains the live site.
**Related:** first-time setup in [`docs/installation-guide.md`](installation-guide.md); database import/backup in [`docs/database-import.md`](database-import.md); admin tasks in [`docs/administrator-guide.md`](administrator-guide.md).

---

## 1. Deployment principles

CustomCore is designed to deploy cleanly on **standard shared PHP/MySQL hosting**:

- **Plain `.php` URLs** — no URL rewriting, `.htaccess` routing, or front controller is required.
- **Depth-safe relative links** via `customcore_url` — the project works whether it lives at the domain root or in a subfolder like `~yourid/customcore/`.
- **No build step** — no Composer, Node, or Docker. Deploying is copying files + importing the database.
- **Secrets stay out of Git** — `config/database.php` and `uploads/*` are gitignored; you create them per environment.

---

## 2. Pre-deployment checklist

Before uploading, confirm on your local copy:

- [ ] The site passes the §9 smoke test in [`docs/installation-guide.md`](installation-guide.md).
- [ ] `config/app.php → debug` is **`false`**.
- [ ] `config/app.php → environment` is set to `production` for the live copy.
- [ ] No real credentials or customer data are staged for commit.
- [ ] You have the host's MySQL host, database name, username, and password.
- [ ] You know the public URL/path the project will live under.

---

## 3. Deploy the files

Choose whichever transfer method your host supports.

### Option A — Git on the server (if available)

```bash
cd ~/public_html
git clone <your-repository-url> customcore
```

### Option B — SFTP / host file manager

Upload the entire project folder into your web space (for example `public_html/customcore/`). Include every folder **except** you will create `config/database.php` and the `uploads/` contents on the server.

Either way, ensure these gitignored items exist on the server afterwards:

- `config/database.php` (created in §4)
- `uploads/products/` and `uploads/consultation/` (writable — see §5)

---

## 4. Configure credentials on the server

```bash
cp config/database.example.php config/database.php
```

Edit `config/database.php` with the **host's** MySQL values. Then set, in `config/app.php`:

- `debug` → `false`
- `environment` → `production`
- `base_url` → leave empty for relative URLs (recommended). Only set it (e.g. `https://myweb.cs.uwindsor.ca/~yourid/customcore`) if you specifically need absolute links. When set, regenerate absolute sitemap locs with `php sitemap.php --write` (or rely on the live `sitemap.php` endpoint).

Verify connectivity from the server shell if you have CLI access:

```bash
php database/test-connection.php # expect: CustomCore database connection: OK
```

---

## 5. File permissions

The web server user must be able to **write** to the upload folders and **read** everything else:

```bash
# read/execute for the app; writable uploads
find. -type d -exec chmod 755 {} \;
find. -type f -exec chmod 644 {} \;
chmod -R u+rwX uploads
```

Adjust to your host's user model (some shared hosts prefer `775`/`664` with a shared group). Both upload folders contain an `index.php` guard so they can't be directory-browsed even if permissions are loose.

**Never** make `config/database.php` world-readable beyond what the web server needs.

---

## 6. Import the database on the host

Use the host's MySQL (via SSH `mysql` client or a panel like phpMyAdmin) and run the same ordered import as local — schema first, then seeds — from [`docs/installation-guide.md`](installation-guide.md) §5 and [`docs/database-import.md`](database-import.md) §2. Then create the admin account:

```bash
php database/create-admin.php
```

If the host has no shell access, import the `.sql` files through phpMyAdmin in order, and create the admin either by running `create-admin.php` via SSH elsewhere against the same DB, or (least preferred) by inserting a row whose `password_hash` you generated with `password_hash` — never store a plain-text password.

---

## 7. HTTPS and sessions

CustomCore's session layer (`includes/functions.php`) automatically hardens cookies under HTTPS:

- `Secure` cookie flag is enabled when the request is HTTPS (it also honours `X-Forwarded-Proto: https` from a TLS-terminating proxy).
- Cookies are always `HttpOnly` + `SameSite=Lax`, and the session uses strict mode and cookie-only IDs.

**Recommendation:** serve the live site over HTTPS so the `Secure` flag activates. Session timeouts (30-minute idle, 12-hour absolute, 15-minute ID rotation) are configured in `config/app.php` and apply automatically.

---

## 8. Post-deployment verification (live)

- [ ] Homepage loads over the public URL with no PHP errors and correct styling.
- [ ] A public page's source shows `main.css` then the theme CSS **last**; `main.js` loads in the footer.
- [ ] Catalogue chart and its data table render; a product page shows options.
- [ ] Register → log in → build → cart → checkout produces an order number.
- [ ] Admin login works and the dashboard shows live counts.
- [ ] Admin → Themes switch restyles the public site.
- [ ] Consultation attachment and product image uploads succeed and cannot be directory-browsed.
- [ ] Record the live URL in the project `README.md` (rubric #11).
- [ ] `robots.txt` Disallows `/admin/` and private customer pages; `sitemap.php` (or `sitemap.xml`) lists only public URLs.

---

## 9. Troubleshooting reference

| Symptom | Likely cause | Fix |
| ------- | ------------ | --- |
| Blank white page / HTTP 500 | PHP fatal with display off | Check the host's PHP error log; temporarily set `config/app.php → debug` to `true` **locally** to reproduce, never leave it on live. |
| "Database configuration file is missing" | `config/database.php` not created on the server | `cp config/database.example.php config/database.php` and fill in host credentials. |
| "The database is temporarily unavailable" | Wrong credentials/host, or MySQL down | Verify values in `config/database.php`; run `php database/test-connection.php`; confirm the DB exists and the user has grants. |
| Foreign-key / import errors | Seeds imported before schema, or wrong order | Import `database/schema.sql` first, then seeds in the documented order. |
| CSS looks unstyled / wrong theme | Theme CSS missing on disk, or wrong stylesheet order | Ensure `assets/themes/*.css` uploaded; the resolver falls back to RGB Gaming/`main.css`, so a plain-but-styled page means the theme file is missing. |
| Images/uploads not saving | `uploads/` not writable by web user | `chmod -R u+rwX uploads`; confirm the web server user owns/can write the folders. |
| Broken product images | File missing or invalid type | The site shows a placeholder by design; re-upload a valid JPG/PNG/WEBP/GIF under 2 MB via Admin → Products. |
| Links 404 in a subfolder deploy | Absolute paths assumed | CustomCore uses `customcore_url` relative links; ensure you didn't set `base_url` incorrectly in `config/app.php`. |
| Logged out too quickly | Session idle/absolute timeouts | Expected security behaviour; adjust `session_*` values in `config/app.php` if policy requires. |
| Admin link missing after login | Account isn't an admin | Promote via Admin → Users, or run `php database/create-admin.php`. |
| PC Builder runs no compatibility checks | `compatibility_rules` table empty | Re-import `database/seed-compatibility.sql` (idempotent; touches only that table). |
| Login always fails for a known user | Account disabled, or wrong password | Check Admin → Users status; error messages are intentionally generic for security. |
| Map or charts don't appear | CDN (Leaflet/Chart.js) blocked or offline | Text address and data tables remain as fallbacks by design; check network access to the CDN. |
| Credentials appear in an error | `debug` left on in production | Set `config/app.php → debug` to `false`; production messages are already sanitised. |

---

## 10. Backup, restore, and rollback

- **Database backup** (before any change to live data):

```bash
mysqldump -u your_username -p --single-transaction --routines --triggers \
 your_database_name > customcore-backup.sql
```

- **Restore** into a scratch DB first to test, then the target. See [`docs/database-import.md`](database-import.md) §5.
- **Uploaded files:** back up `uploads/products/` and `uploads/consultation/` separately (they are not in Git).
- **Code rollback:** `git checkout <previous-tag-or-commit>` (or re-upload the previous files). Because config and uploads are outside Git, a code rollback does not touch credentials or user files.
- **Never commit** dumps containing real customer data or production credentials.

---

## 11. Routine maintenance

- Keep `debug` off and monitor the host's PHP/MySQL error logs.
- Watch the admin dashboard attention alerts (pending reviews, open consultations, low/out-of-stock).
- Back up the database and uploads on a regular schedule.
- Rotate admin credentials if staff change; disable rather than delete departing accounts.
- After pulling new code, re-check the §8 verification list.

---

## 12. Status

**Summary.** Deploying CustomCore to standard shared PHP/MySQL hosting is documented end-to-end (file transfer, per-environment credentials, permissions, database import, HTTPS/session behaviour, and live verification), alongside a practical troubleshooting table and backup/rollback procedures. Supports rubric row **B14** and prepares rubric **#11** (record the live URL in the README once hosted).
