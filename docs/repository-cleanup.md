# CustomCore | Repository Cleanup Record
**Document type:** submission hygiene record 
**Date:** 4 August 2026 
**Purpose:** Confirm the Git working tree is free of temporary files, backups, debug leftovers, and credentials before final packaging. 
**Related:** [`.gitignore`](../.gitignore), [`config/database.example.php`](../config/database.example.php), [`database/verify-config.php`](../database/verify-config.php), [production configuration](production-configuration.md).

---

## 1. Goal 

| Deliverable | Meaning |
| --- | --- |
| Clean repository | No debug scripts, one-off dumps, duplicate backups, or OS junk in the package you submit |
| Credentials stay private | Real MySQL secrets exist only in gitignored `config/database.php` (on each machine), never in Git |
| Safe seeds remain | Tracked `database/schema.sql` and `database/seed-*.sql` contain catalogue/demo data only |

---

## 2. Actions completed

| Action | Result |
| --- | --- |
| Remove macOS `.DS_Store` from the tree | Deleted (already ignored; no longer present on disk) |
| Strengthen [`.gitignore`](../.gitignore) | Blocks cookies/tmp dumps, `__tmp*.php`, backup extensions, local SQL dumps, chrome profile leftovers, `error_log` |
| Confirm real credentials file is ignored | `git check-ignore` reports `/config/database.php`; file is **not** tracked |
| Confirm no junk paths are tracked | `git ls-files` contains no `.bak`, `tmp-*`, cookies, `.env` (non-example), or `config/database.php` |
| Confirm no debug leftovers in app PHP | No `var_dump` / `print_r` / `phpinfo` / display_errors dumps in application code |
| Confirm intentional tools remain | `database/create-admin.php`, `test-connection.php`, and `verify-config.php` are kept (CLI setup, not temp scripts) |
| Extend `database/verify-config.php` | Checks tracked secrets/junk and on-disk leftover junk so the same CLI proves submission hygiene |

---

## 3. What stays in Git (not “temp”)

These look operational but are **required** project artefacts:

| Path | Why it stays |
| --- | --- |
| `config/database.example.php` | Placeholder only — templates for setup |
| `config/app.php` | Non-secret settings |
| `database/schema.sql` + seeds | Fresh import package (no live private customers) |
| `database/verify-config.php` | Ongoing secret/package hygiene checker |
| `uploads/**/index.php` + `.htaccess` | Directory guards (real uploads remain ignored) |
| Demo password notes for seed users in README / `seed-reviews.sql` | Public demo accounts (`DemoPass123!`); not host admin secrets |

---

## 4. Verification command

From the project root:

```bash
php database/verify-config.php
```

Expect:

- **Passed** includes: ignored and untracked `config/database.php`, example placeholders only in `database.example.php`, no tracked credential or junk paths, clean (or warning-only) on-disk junk scan.
- **Never** prints passwords.

Optional production packaging mode:

```bash
php database/verify-config.php --production
```

That mode fails if placeholders remain in the host `database.php` or leftover junk is still on disk.

---

## 5. Acceptance

| Criterion | Status |
| --- | --- |
| No temporary / debug files in the submission package | Pass |
| No backup/duplicate junk tracked | Pass |
| No credentials in Git | Pass |
| `.gitignore` hardened against future accident files | Pass |
| CLI proof available via `verify-config.php` | Pass |

Repository cleanup is complete.
