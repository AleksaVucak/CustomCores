# CustomCore | Production Configuration Guide

**Document type:** Project documentation
**Purpose:** Explain how to configure CustomCore for a public host without putting secrets in Git. Covers database credentials, non-secret app settings, configurable paths, and how to verify the setup.
**Audience:** Students deploying to `myweb.cs.uwindsor.ca` (or any second server) and graders checking that the repository is credential-free.
**Related:** host capability list in [`production-requirements.md`](production-requirements.md); upload steps in [`deployment-troubleshooting.md`](deployment-troubleshooting.md); folder overview in [`../config/README.md`](../config/README.md).

---

## 1. What lives in Git vs what stays private

| File | Tracked in Git? | Holds secrets? | Role |
| ---- | --------------- | -------------- | ---- |
| `config/database.example.php` | Yes | No (placeholders only) | Template for copying |
| `config/database.php` | **No** (gitignored) | Yes | Real MySQL host, database name, user, password |
| `config/app.php` | Yes | No | Site name, flags, paths, store details, session limits |
| `config/app.production.example.php` | Yes | No | Production-safe values to copy into `app.php` on the host |
| `config/README.md` | Yes | No | Human setup notes |
| `uploads/products/*` (real files) | **No** | User content | Created by admin uploads |
| `uploads/consultation/*` (real files) | **No** | User content | Created by consultation attach |

**Completion rule for this project:** the Git history and the working tree that you push must never contain a real MySQL password. Only `config/database.php` (ignored) does.

---

## 2. Database template

### Create the real file once per environment

```bash
cp config/database.example.php config/database.php
```

Edit **only** `config/database.php`. Set:

| Key | Typical myweb value | Notes |
| --- | ------------------- | ----- |
| `host` | `localhost` | Use the hostname your host panel shows if not local |
| `port` | `3306` | Rarely different |
| `dbname` | (from panel) | Empty database created before seed import |
| `username` | (from panel) | App user with rights on that database |
| `password` | (from panel) | Never paste this into example files or commits |
| `charset` | `utf8mb4` | Required to match `database/schema.sql` |

### Confirm Git cannot see it

```bash
git check-ignore -v config/database.php
# Expected: a line pointing at .gitignore

git status
# config/database.php must NOT appear as an untracked or staged file
```

### Test without printing secrets

```bash
php database/test-connection.php
# Success text includes: CustomCore database connection: OK
# The password is never printed
```

---

## 3. Non-secret application settings

`config/app.php` is loaded by every request through `customcore_app_config()`. It is safe to commit.

### Production flags (required on the live host)

| Key | Production value | Why |
| --- | ---------------- | --- |
| `environment` | `'production'` | Marks the live host; local copies may use `'local'` |
| `debug` | `false` | Hides stack traces and detailed database errors from visitors |
| `base_url` | `''` (recommended) | Relative links work under `~user/customcore/`; set only when absolute SEO URLs are required |
| `timezone` | `'America/Toronto'` | Matches the university region; change if your host needs another zone |
| `session_name` | `'CUSTOMCORESESSID'` | Avoids cookie collisions on shared multi-site hosts |

### Optional absolute URL

If the university host needs absolute canonical links:

```php
'base_url' => 'https://myweb.cs.uwindsor.ca/~yourUWinID/customcore',
```

No trailing slash. Then regenerate the static sitemap snapshot:

```bash
php sitemap.php --write
```

### Ready-made production copy

`config/app.production.example.php` is a complete, credentials-free example with `environment = production` and `debug = false`. On the host you may either:

1. Edit the existing `config/app.php` values, or  
2. Replace it: `cp config/app.production.example.php config/app.php`

Do not put database passwords into either app file.

---

## 4. Configurable paths

All filesystem locations used by the app are project-relative strings under `app.php → paths`:

| Key | Default | Used for |
| --- | ------- | -------- |
| `uploads_consultation` | `uploads/consultation` | Consultation attachments |
| `uploads_products` | `uploads/products` | Admin product images |
| `themes` | `assets/themes` | Switchable theme CSS |
| `images` | `assets/images` | Catalogue and site imagery |
| `media` | `assets/media` | Learning Centre video/audio |

Rules:

- Do **not** use absolute system paths (`/var/www/...`) unless you fully control the host and understand the security implications.
- Do **not** include `..` segments.
- Keep the matching folders writable for upload keys and readable for asset keys, as described in [`production-requirements.md`](production-requirements.md).

Upload size limits:

- `upload_max_bytes` (default 2 MB) applies to consultation attachments and is consulted by product image validation.
- `upload_allowed_extensions` lists allowed consultation file extensions. Image MIME types are still verified server-side with `finfo`.

---

## 5. Verify the configuration (no secrets printed)

From the project root:

```bash
# Local checkout (warnings OK if database.php not filled yet)
php database/verify-config.php

# On the live host before you announce the URL
php database/verify-config.php --production
```

The verifier checks:

1. `.gitignore` covers `config/database.php` and Git is not tracking it  
2. `database.example.php` still uses only `your_*` placeholders  
3. `app.production.example.php` uses production-safe flags and no credential keys  
4. `app.php` has safe `debug` / `environment` / relative `paths`  
5. `database.php` exists (on production), is complete, and is not still filled with example placeholders  

Exit code `0` means pass. Exit code `1` means fix the listed FAILs before going live. Passwords are never echoed.

---

## 6. Second-server checklist

- [ ] `config/database.example.php` committed with placeholders only  
- [ ] `config/database.php` created on the host and gitignored  
- [ ] `config/app.php` has `debug => false` and `environment => production` on the host  
- [ ] Paths still point inside the project tree  
- [ ] `php database/verify-config.php --production` reports `RESULT: PASS`  
- [ ] `php database/test-connection.php` reports `OK` without showing a password  
- [ ] `git status` shows no credential files staged  

When those boxes are checked, continue with the file upload and SQL import steps in [`deployment-troubleshooting.md`](deployment-troubleshooting.md).

---

## 7. Status

**Summary.** CustomCore ships safe configuration templates for both secrets (`database.example.php`) and non-secret production flags (`app.php` plus `app.production.example.php`), keeps real credentials out of Git via `.gitignore`, exposes configurable project-relative storage paths, and provides `database/verify-config.php` to prove the repository remains free of real database passwords.
