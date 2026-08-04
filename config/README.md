# CustomCore | Configuration setup

This folder holds application and database settings for every environment
(local laptop and university shared hosting).

**Related:** full production walkthrough in
[`docs/production-configuration.md`](../docs/production-configuration.md);
host capability list in
[`docs/production-requirements.md`](../docs/production-requirements.md).

## Files

| File | Tracked in Git? | Purpose |
| ---- | --------------- | ------- |
| `app.php` | Yes | Non-secret site settings (flags, paths, store details) |
| `app.production.example.php` | Yes | Production-safe non-secret template to copy on the live host |
| `database.example.php` | Yes | Template for database credentials (placeholders only) |
| `database.php` | **No** (gitignored) | Real credentials for **this** machine or host |
| `README.md` | Yes | This document |

## Create database credentials (every environment)

From the project root:

```bash
cp config/database.example.php config/database.php
```

Then edit **only** `config/database.php` and replace:

- `your_database_name`
- `your_database_username`
- `your_database_password`
- `host` / `port` if your host requires different values

Leave `charset` as `utf8mb4`.

## Apply production application flags (live host only)

On the university server, either edit `config/app.php` and set:

```php
'environment' => 'production',
'debug' => false,
'base_url' => '', // or your absolute https://myweb.../~id/customcore URL
```

…or replace the file with the ready template:

```bash
cp config/app.production.example.php config/app.php
```

`base_url` should stay empty unless absolute SEO URLs are required. Paths under
`paths` stay project-relative (`uploads/...`, `assets/...`).

## Security rules

1. Never commit `config/database.php`.
2. Never put real passwords in `database.example.php` or any `*.example.php` file.
3. Keep `app.php` → `debug` set to `false` on every public host.
4. Do not print database credentials in HTML error pages.
5. Do not put MySQL username, password, or database name keys into `app.php`.

Confirm ignore rules:

```bash
git check-ignore -v config/database.php
git status   # database.php must not be listed
```

## Verify configuration (prints no secrets)

```bash
php database/verify-config.php              # local / general
php database/verify-config.php --production # live host readiness
```

Expected ending line: `RESULT: PASS ...`

## Test the database connection

After `config/database.php` exists and MySQL is available:

```bash
php database/test-connection.php
```

Expected success output includes `CustomCore database connection: OK` and does
**not** print the password.

The reusable helper used by the website is `includes/database.php`
(`customcore_pdo()`).

## Full database import

See [`docs/database-import.md`](../docs/database-import.md) for the complete
schema → seeds → admin → verification → backup sequence.

## Production host requirements

Before uploading to university shared hosting, confirm the server matches
[`docs/production-requirements.md`](../docs/production-requirements.md)
(PHP version and modules, MySQL engine, upload permissions, and browser baselines).

## Create an administrator account

After the database schema and config are in place:

```bash
php database/create-admin.php
```

The script prompts interactively for email, name, and password. The password is
hashed with `password_hash()` (bcrypt) and stored securely. No plain-text
password appears in Git or in seed files. Run this once per environment.
