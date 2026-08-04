# CustomCore | Administrator Workflow Verification

**Document type:** Project documentation
**Purpose:** Prove that every core administrator action succeeds end-to-end — from creating a product all the way through the monitoring dashboard — exercising the real admin handlers, the admin access guard, CSRF, and database writes, including the account-safety protections.
**Acceptance:** Every core administrator action succeeds; any avoidable defect found in an administrator workflow is corrected.
**Related:** Customer counterpart [`customer-workflows.md`](customer-workflows.md); earlier test records [`html-validation.md`](html-validation.md), [`css-validation.md`](css-validation.md), [`js-validation.md`](js-validation.md), [`responsiveness-desktop.md`](responsiveness-desktop.md), [`responsiveness-mobile.md`](responsiveness-mobile.md); admin tooling in [`administrator-guide.md`](administrator-guide.md).

### Status legend

| Status | Meaning |
| --- | --- |
| **Pass** | The action completed with the expected result (correct HTTP status/redirect **and** the expected database change), or an unsafe action was correctly rejected |
| **Fail** | The action did not complete or produced a wrong result on a core administrator path |

---

## 1. Scope — every core administrator action

| Area | Actions verified |
| --- | --- |
| Access guard | Guest → login redirect; logged-in customer → profile redirect; admin → allowed |
| Dashboard | Load `admin/index.php` (KPIs, alerts, activity panels) |
| Products | List, search + filter, **add** (with image upload), **edit** (update), **disable**, **enable** |
| Product options | Create, set default, toggle active, delete |
| Compatibility | List, toggle a rule (+ restore), update a rule |
| Orders | List, view detail, **change status**, **save admin notes** |
| Users | List, search + filter, view edit, **disable/enable**, **promote → admin**, **demote → customer** |
| Consultations | List, view detail, **save response** (auto-advances to *answered*), **change status** |
| Reviews | List, **approve**, **hide** |
| Reports | Load `admin/reports.php` (Chart.js datasets present) |
| Monitoring | Load `admin/monitoring.php` (health checks render) |
| Themes | Load, **switch active theme**, **restore** original |
| Safety | **Self-lockout**: an admin cannot change their own role (rejected) |

---

## 2. Method

1. **Workflow map.** Every admin action was mapped to its page, handler (`includes/admin-*.php`), POST/GET fields, enum/whitelist values, access control (`customcore_require_admin`), CSRF requirement, and success/redirect behaviour.
2. **Automated end-to-end harness.** A disposable throwaway script drove the running site over the project PHP server (`php -S localhost:8000`). Because there is **no seeded admin account** (by design), the harness:
 - registered a disposable customer **and** a disposable admin over HTTP, then promoted the latter to `admin` directly in the database;
 - as the customer, created the artifacts an admin must manage — an **order**, a **pending review**, and a **consultation request**;
 - then, in a separate authenticated admin session (own cookie jar, CSRF re-read from each form page), performed **every** admin action in §1.
3. **Result verification in the database.** Each state-changing action was confirmed not only by its `303` PRG redirect but by re-reading the affected row (product price, `is_active`, order `status`/`admin_notes`, user `role`/`is_active`, consultation `status`, review `status`, `site_settings.active_theme_id`, compatibility-rule `is_active`).
4. **Non-destructive by construction.** Global settings touched for testing were **restored**: the toggled compatibility rule was toggled back, and the active theme was switched then restored to its original value. The `update_rule` action was submitted with the rule's current values (no data change).
5. **Disposable data + cleanup.** After the run, the created product (+ its options and uploaded image file), both disposable users, and all their orders/reviews/consultations/carts were deleted. Post-cleanup the store was confirmed back to baseline: **20 products (all active), 0 orders, 0 consultations, reviews at the seed mix (8 approved / 1 pending / 1 hidden), only the three seed customers, 7/7 compatibility rules active, `active_theme_id = 1`, and no orphan upload files.**

---

## 3. Results summary

**All core administrator actions: Pass — 51/51 assertions.** Every product-to-monitoring action completed with the correct database effect, the admin guard correctly blocks guests and non-admin customers, and the self-lockout protection correctly refuses an admin's own role change.

| Group | Assertions | Pass | Fail |
| --- | --- | --- | --- |
| Setup (register/promote/seed data) | 7 | 7 | 0 |
| Access guard + admin login | 3 | 3 | 0 |
| Dashboard | 1 | 1 | 0 |
| Products (list/filter/add/image/edit/disable/enable) | 8 | 8 | 0 |
| Product options (create/default/toggle/delete) | 4 | 4 | 0 |
| Compatibility (list/toggle/restore/update) | 4 | 4 | 0 |
| Orders (list/detail/status/notes) | 4 | 4 | 0 |
| Users (list/filter/edit/disable/enable/promote/demote) | 7 | 7 | 0 |
| Consultations (list/detail/respond/status) | 4 | 4 | 0 |
| Reviews (list/approve/hide) | 3 | 3 | 0 |
| Reports + Monitoring | 2 | 2 | 0 |
| Themes (load/switch/restore) | 3 | 3 | 0 |
| Safety (self-lockout) | 1 | 1 | 0 |
| **Total** | **51** | **51** | **0** |

---

## 4. Defects found and fixed

**None.** The administrator workflows all behaved correctly, including the account-safety guards. No source changes were required for this commit.

Observations that confirm intended behaviour (not defects):

- **Admin guard is enforced everywhere** — a guest is sent to `login.php` (with return-to), a logged-in non-admin customer is sent to `profile.php`, and only `user_role === 'admin'` proceeds.
- **Soft-delete for products** — the products list "disable/enable" toggles `is_active`; there is no hard delete, so historical orders keep their references.
- **Consultation auto-advance** — saving a non-empty response while a request is `open`/`in_progress` advances it to `answered` and stamps `responded_at`, as designed.
- **Account-safety protections** — an admin cannot change their **own** role (self-lockout), verified live; the last-active-admin guard (`includes/admin-users.php`) additionally prevents disabling/demoting the final admin.
- **Global settings restored** — theme switch and compatibility-rule toggle were reverted, leaving the store exactly as before.

---

## 5. How to re-run

```
# 1. Serve from the project root (connects to MySQL from config/database.php):
php -S localhost:8000

# 2. Sign in as an administrator (create one with: php database/create-admin.php),
# then walk product → monitoring:
# dashboard → products (add w/ image → edit → disable/enable) → product options
# → compatibility (toggle a rule) → orders (status + notes) → users
# (disable/enable, promote/demote) → consultations (respond) → reviews (approve/hide)
# → reports → monitoring → themes (switch + switch back).
#
# Expect: each state change redirects (PRG) with a success flash and the row
# updates in the database; the admin guard blocks guests/customers; an admin
# cannot change their own role.
```

Testing uses disposable `@example.test` accounts and a disposable product that are deleted afterward, returning the store to its seed state.

---

## 6. Sign-off

| Criterion | Result |
| --- | --- |
| Every core administrator action succeeds (product → monitoring) | **Yes — 51/51** |
| Admin access guard blocks guests and non-admin customers | **Yes** |
| Account-safety protections enforced (self-lockout / last admin) | **Yes** |
| Global settings (theme, rules) left unchanged after testing | **Yes** |
| Avoidable administrator-workflow defects corrected | **None found** |
| Test data cleaned up; store returned to baseline | **Yes** |
| Administrator-workflow results recorded | **This document** |

