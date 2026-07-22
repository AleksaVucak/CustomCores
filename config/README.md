# CustomCore — Configuration setup

This folder holds application and database settings.

## Files

| File | Tracked in Git? | Purpose |
| ---- | --------------- | ------- |
| `app.php` | Yes | Non-secret site settings |
| `database.example.php` | Yes | Template for database credentials |
| `database.php` | **No** (gitignored) | Real credentials for your machine or host |

## Create your local database config

From the project root:

```bash
cp config/database.example.php config/database.php
```

Then edit `config/database.php` and replace:

- `your_database_name`
- `your_database_username`
- `your_database_password`
- `host` / `port` if your host requires different values

## Security rules

1. Never commit `config/database.php`.
2. Never put real passwords in `database.example.php`.
3. Keep `app.php` → `debug` set to `false` on the live server.
4. Do not print database credentials in HTML error pages.

## Test the connection (Commit 1.3)

After `config/database.php` exists and MySQL is available:

```bash
php database/test-connection.php
```

Expected success output includes `CustomCore database connection: OK` and does **not** print the password.

The reusable helper used by the website is `includes/database.php` (`customcore_pdo()`).
