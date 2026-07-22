# CustomCore — Database Import and Backup Guide

**Document type:** Stage 2 documentation (Commit 2.8)  
**Purpose:** Enable a new developer or grader to create the database from scratch, load all seed data, create an admin account, verify the catalogue, and back up/restore safely.  
**Related:** Entity-relationship design in [`docs/database-design.md`](database-design.md); config setup in [`config/README.md`](../config/README.md).

---

## 1. Prerequisites

| Requirement | Notes |
| ----------- | ----- |
| MySQL or MariaDB | InnoDB, `utf8mb4` support |
| PHP CLI | For connection test and admin setup (`php` on PATH) |
| Empty (or dedicated) database | Create one before importing |
| Local credentials | `config/database.php` (copied from the example; **not** in Git) |

### Create the MySQL database

```sql
CREATE DATABASE customcore
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Grant your application user access to that database (host-specific). Then:

```bash
cp config/database.example.php config/database.php
# Edit config/database.php with host, dbname, username, password
```

Test connectivity:

```bash
php database/test-connection.php
```

Expected: `CustomCore database connection: OK` (password is never printed).

---

## 2. Import order (from scratch)

Run these commands from the **project root**, substituting your MySQL username and database name. You will be prompted for the MySQL password.

```bash
# 1. Schema — all 21 tables, PKs, FKs, indexes
mysql -u your_username -p your_database_name < database/schema.sql

# 2. Catalogue categories + 20 products
mysql -u your_username -p your_database_name < database/seed-products.sql

# 3. Product options (≥ 2 per product)
mysql -u your_username -p your_database_name < database/seed-product-options.sql

# 4. Builder categories + 60 components
mysql -u your_username -p your_database_name < database/seed-components.sql

# 5. Compatibility rules (7 simplified checks)
mysql -u your_username -p your_database_name < database/seed-compatibility.sql

# 6. Themes + site settings
mysql -u your_username -p your_database_name < database/seed-themes.sql

# 7. Demo approved reviews (Commit 3.8 — optional but recommended for catalogue UI)
mysql -u your_username -p your_database_name < database/seed-reviews.sql

# 8. Admin account (interactive; hashed password — not a SQL seed)
php database/create-admin.php
```

### File inventory

| Order | File | What it loads |
| ----: | ---- | ------------- |
| 1 | `database/schema.sql` | 21 empty InnoDB tables |
| 2 | `database/seed-products.sql` | 4 categories + 20 products |
| 3 | `database/seed-product-options.sql` | 323 product options |
| 4 | `database/seed-components.sql` | 10 builder categories + 60 components |
| 5 | `database/seed-compatibility.sql` | 7 compatibility rules |
| 6 | `database/seed-themes.sql` | 3 themes + site settings |
| 7 | `database/seed-reviews.sql` | Demo customers + approved/pending/hidden reviews (3.8) |
| 8 | `database/create-admin.php` | One admin user (CLI prompts) |

Seed files that clear and re-insert data (products, options, components, themes) are safe to re-run during development. Always run `schema.sql` first on a new database.

---

## 3. Verification checklist

Run these after a full import. Expected results are noted in comments.

### Structure

```sql
-- Expect 21
SELECT COUNT(*) AS table_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE';
```

### Catalogue (rubric #2 / B11)

```sql
-- Expect ≥ 20
SELECT COUNT(*) AS active_products
FROM products
WHERE is_active = 1;

-- Expect 0 rows (no product with fewer than 2 active options)
SELECT p.id, p.name, COUNT(po.id) AS option_count
FROM products p
LEFT JOIN product_options po
  ON po.product_id = p.id AND po.is_active = 1
WHERE p.is_active = 1
GROUP BY p.id, p.name
HAVING COUNT(po.id) < 2;
```

### Builder inventory

```sql
-- Expect 0 rows (every category has ≥ 1 active part)
SELECT cc.id, cc.name, COUNT(c.id) AS part_count
FROM component_categories cc
LEFT JOIN components c
  ON c.component_category_id = cc.id AND c.is_active = 1
GROUP BY cc.id, cc.name
HAVING COUNT(c.id) < 1;

-- Expect 7
SELECT COUNT(*) AS rule_count
FROM compatibility_rules
WHERE is_active = 1;
```

### Themes

```sql
-- Expect 3 themes and one active setting (RGB Gaming by default)
SELECT t.id, t.name, t.slug, t.css_file
FROM site_settings s
INNER JOIN themes t ON t.id = CAST(s.setting_value AS UNSIGNED)
WHERE s.setting_key = 'active_theme_id';
```

### Reviews (Commit 3.8 — after seed-reviews.sql)

```sql
-- Expect ≥ 8 approved (public pages use this filter only)
SELECT COUNT(*) AS approved_reviews
FROM reviews
WHERE status = 'approved';

-- Expect pending + hidden rows to exist (proves moderation data is present)
SELECT status, COUNT(*) AS cnt
FROM reviews
GROUP BY status;

-- Public pages must never surface these:
--   SELECT ... FROM reviews WHERE status = 'approved'
```

### Admin account

```sql
-- Expect ≥ 1 after create-admin.php
SELECT id, email, role, is_active
FROM users
WHERE role = 'admin';
```

---

## 4. Alignment with the ER design

The live schema in `database/schema.sql` implements the plan in [`docs/database-design.md`](database-design.md):

| Design artefact | Implemented as |
| --------------- | -------------- |
| 21-table inventory | `CREATE TABLE` for each named entity |
| ER relationships | Foreign keys with documented `ON DELETE` / `ON UPDATE` behaviour |
| Catalogue ≥ 20 × ≥ 2 options | Seeds 2.2–2.3 + verification queries above |
| Builder + 7 compatibility rules | Seeds 2.4–2.5 |
| Themes + active setting | Seed 2.6 |
| Password hashing only | `users.password_hash`; admin created via `create-admin.php` |
| No payment secrets | `orders.payment_method` is a label column only |

If the ER document and `schema.sql` ever diverge, treat **`schema.sql` as the executable source of truth** and update the design doc in the same change.

---

## 5. Backup and restore

### Backup (structure + data)

```bash
mysqldump -u your_username -p \
  --single-transaction \
  --routines \
  --triggers \
  your_database_name > backups/customcore-backup.sql
```

Create a local `backups/` folder if needed. **Do not commit dumps that contain real customer data or production credentials.** The project `.gitignore` already ignores local dump patterns where configured; keep backups outside Git when they hold private data.

### Restore

```bash
mysql -u your_username -p your_database_name < backups/customcore-backup.sql
```

Restoring replaces existing table data for objects included in the dump. Prefer restoring into a scratch database first when testing.

### Fresh reseed (development only)

To wipe catalogue/builder/theme seed data and reload from the repo seeds, re-run steps 2–6 from [Section 2](#2-import-order-from-scratch). Do **not** drop `users` casually if you still need the admin account — recreate the admin with `php database/create-admin.php` after a full schema reset.

---

## 6. Security reminders

1. Never commit `config/database.php` or real passwords.  
2. Never commit plain-text admin passwords or live customer dumps.  
3. Prefer `create-admin.php` over hand-written `INSERT` with plaintext passwords.  
4. On shared hosting, keep PHP debug mode off (`config/app.php` → `debug` => `false`).  
5. Seed data uses fictional catalogue content only — safe for Git.

---

## 7. Troubleshooting

| Symptom | Likely cause | Fix |
| ------- | ------------ | --- |
| `Access denied` on mysql | Wrong user/password or missing grants | Check credentials; grant privileges on the database |
| Foreign key errors on seed | Schema not imported first, or wrong order | Re-run `schema.sql`, then seeds in order |
| `create-admin.php` fails on config | Missing `config/database.php` | Copy from `database.example.php` |
| Connection test fails | Host/port/dbname wrong or MySQL down | Fix config; confirm MySQL is running |
| Option verification returns rows | Options seed skipped or partial | Re-import `seed-product-options.sql` |
| Theme JOIN returns empty | Themes seed skipped | Re-import `seed-themes.sql` |

---

## 8. Status

**Commit 2.8 complete.** A new developer can import CustomCore’s database from scratch, verify Stage 2 acceptance criteria, create an admin account securely, and back up/restore without relying on undocumented steps.
