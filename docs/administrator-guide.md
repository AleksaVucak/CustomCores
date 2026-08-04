# CustomCore | Administrator User Guide

**Document type:** Project documentation
**Purpose:** Give a CustomCore administrator a complete, task-oriented guide to every back-office tool: how to sign in, read the dashboard, and manage products, options, compatibility metadata, orders, users, consultations, reviews, reports, and themes.
**Audience:** Store administrators (staff). No programming knowledge is assumed. For editing catalogue **content** specifically (adding a product, swapping an image, adding a video), see [`docs/content-update-guide.md`](content-update-guide.md).
**Related:** [`docs/database-import.md`](database-import.md), [`docs/installation-guide.md`](installation-guide.md), [`docs/frontend-documentation.md`](frontend-documentation.md).

---

## 1. What the administrator area is

Everything under `admin/` is a protected back office. Each admin page begins with:

```php
require_once __DIR__. '/../includes/admin-auth.php';
customcore_require_admin;
```

`customcore_require_admin` enforces three outcomes:

- **Guests** are sent to the login page (with a return-to link back).
- **Logged-in customers** (non-admins) are redirected to their own profile with a "You do not have permission" message.
- **Administrators** continue to the tool.

There is no separate admin password — an administrator is simply a user account whose `role` is `admin`.

---

## 2. Getting administrator access

### 2.1 Create the first administrator (command line)

The first admin is created with a secure CLI script so no plain-text password ever touches Git or a web form:

```bash
php database/create-admin.php
```

You will be prompted for email, first/last name, and a password (minimum 8 characters; hidden on macOS/Linux terminals). The password is stored as a bcrypt hash via `password_hash`. If the email already exists as a customer, the script offers to **promote** it to admin instead of creating a duplicate.

### 2.2 Promote or demote later (from the web UI)

Once you have one admin, you can manage roles from **Users → edit a user → change role** (see §7). Two safety rules are enforced automatically and cannot be bypassed:

- You can **never disable or demote your own account** (no self-lockout).
- The **last active administrator** can never be disabled or demoted.

### 2.3 Sign in

Log in through the normal customer login page. Administrators then see an extra **Admin** link in the main navigation that opens the dashboard (`admin/index.php`).

Sessions time out for security: **30 minutes** of inactivity, a **12-hour** absolute cap, and periodic session-ID rotation (configured in `config/app.php`). If you are logged out unexpectedly, just sign in again.

---

## 3. The dashboard (`admin/index.php`)

The dashboard is a live operations overview. **Every number is computed from the database** (`includes/admin.php` → `customcore_admin_dashboard_stats`), never hard-coded.

It shows:

- **KPI counts** — products (active / inactive / low stock / out of stock), orders by status, users (customers / admins, active / disabled), reviews (pending / approved / hidden), consultations (open / in progress / answered / closed), and unread contact messages.
- **Attention alerts** — an automatically prioritised to-do list: reviews awaiting moderation, consultations needing a response, orders in progress, out-of-stock and low-stock products, unread contact messages, and disabled accounts. Each alert links straight to the relevant tool.
- **Recent activity** — the latest orders, pending reviews, open consultations, and low-stock products.
- **Tool registry** — cards/links for every admin tool. Tools whose page does not yet exist show as "coming" and stay unlinked so the nav never 404s.

"Low stock" means an **active** product with **1–5 units** remaining; "out of stock" means an active product with **0** units.

---

## 4. Products (`admin/products.php`, `product-add.php`, `product-edit.php`)

Full catalogue CRUD. Logic lives in `includes/admin-products.php`.

**List (`admin/products.php`)** — search by name and filter by status; each row has an **enable/disable** toggle and links to **Edit** and **Options**.

**Add / Edit** — the two screens share one form partial (`includes/admin-product-form.php`), so the fields are identical:

- Name (a unique URL slug is generated automatically), category/tier, base price, stock quantity, short and full descriptions, feature flag, and active status.
- **Product image upload** — the file is validated by its **real** MIME type (`finfo`), restricted to JPG/PNG/WEBP/GIF, capped at 2 MB, and saved under `uploads/products/` with a random filename. Replacing or removing an image cleans the old file off disk.

**Important:** products are **never hard-deleted** — use **disable** instead. Disabling hides a product from the storefront while preserving its order and review history. A disabled product simply drops out of the catalogue, search, and builder.

Every create/update/toggle uses CSRF protection and the Post/Redirect/Get pattern, so you always land on a clean page with a confirmation message.

Step-by-step "add a product" instructions for non-programmers are in [`docs/content-update-guide.md`](content-update-guide.md).

---

## 5. Product options (`admin/product-options.php`)

Manages the configurable choices buyers pick on the product and PC Builder pages (RAM, Storage, Colour, Warranty, …). Logic lives in `includes/admin-options.php`.

Pick a product (or arrive via the **Options** link on the product list), then, grouped by option group, you can:

- Add, edit, and reorder options.
- Set a **price adjustment** that is positive **or** negative (an option can add or subtract from the base price).
- Enable/disable an option and set the group **default**.
- Delete an option.

**Key rule enforced for you:** each option group always keeps **exactly one active default**. If you disable, delete, or move the current default, the system automatically promotes a replacement, so the storefront and builder always price a valid configuration. An advisory banner warns when a product drops below two active options or a group loses its default (every product should keep at least two options to satisfy the catalogue requirement).

---

## 6. Compatibility metadata (`admin/compatibility.php`)

Edits the simplified data the PC Builder uses to warn about incompatible parts. Logic lives in `includes/admin-compatibility.php`, which only ever writes a fixed allow-list of columns.

Two panels:

- **Component attributes** — each builder part is edited through a form that shows **only the fields relevant to its category** (e.g. CPU → socket / power / scores; Case → form factor / GPU & cooler clearance / supported cooling). An enable/disable toggle controls whether the part appears in the builder.
- **Compatibility rules** — the seven seeded checks (socket, RAM type, form factor, PSU wattage, GPU clearance, cooler fit, storage) can be renamed, re-described, switched between **error** and **warning** severity, and enabled/disabled. Each rule's JSON wiring is shown **read-only** so the evaluator logic stays intact.

> If the builder ever appears to run **no** checks, the `compatibility_rules` table is probably empty. Re-import `database/seed-compatibility.sql` (it is idempotent and only touches that table).

---

## 7. Orders (`admin/orders.php`, `admin/order-details.php`)

Logic lives in `includes/admin-orders.php`.

**List** — search by order number, customer name, or email; filter by status (with live per-status counts); paginated 25 per page.

**Details** — shows the customer and account status, the shipping snapshot, the payment-method **label**, and every **frozen** line item (with decoded product options and custom-build components) plus totals. From here you can:

- **Change the fulfilment status** — the five statuses are **Pending → Processing → Ready → Completed**, plus **Cancelled**. Status writes are validated against the allowed list.
- **Record internal administrator notes** — these are **never shown to the customer** and are stored empty (NULL) when blank.

Both writes are CSRF-protected with Post/Redirect/Get.

**No real payment data is ever stored.** Checkout is simulated: only the payment-method label is kept, never card numbers.

---

## 8. Users (`admin/users.php`, `admin/user-edit.php`)

Logic lives in `includes/admin-users.php`.

**List** — search by name or email; filter by role and status (with live counts); paginated; each row has a one-click **enable/disable** toggle.

**Edit** — shows the account profile, an activity summary (orders, lifetime spend, reviews, consultations, wishlist), and recent orders. You can:

- **Enable/disable the login.** A disabled account cannot sign in until re-enabled.
- **Change the role** between Customer and Administrator.

The password hash is **never** loaded into an admin view. The two safety invariants from §2.2 (no self-lockout; protect the last active admin) are enforced here. All writes are CSRF + Post/Redirect/Get.

---

## 9. Consultations (`admin/consultations.php`, `admin/consultation-details.php`, `admin/consultation-attachment.php`)

Logic lives in `includes/admin-consultations.php`.

**Queue** — search by customer name, email, or budget; filter by status (with live counts); paginated; **open** and **in-progress** requests are surfaced first.

**Details** — shows the full request (customer + account status, budget, games/software/performance goals/notes) and every attachment. You can:

- Change the status among **Open → In progress → Answered → Closed**.
- Write or clear a **response**. Saving a non-empty response timestamps it and **auto-advances** an open/in-progress request to **Answered**, which the customer then sees in their consultation history.

**Attachments** — `admin/consultation-attachment.php` streams a customer's uploaded files to staff using the same hardening as the customer download endpoint (admin-only, basename-guarded stored name, path confined to the upload directory, `nosniff` header, sanitised download filename).

---

## 10. Reviews (`admin/reviews.php`)

The moderation queue for every product review. Logic lives in `includes/admin-reviews.php`.

- Search by title, body, product, or customer; filter by **pending / approved / hidden** (with live counts). Pending reviews sort to the top.
- Per-review actions: **Approve**, **Hide**, **Mark pending**, and **Delete**.

Only reviews with `status = 'approved'` appear on the public catalogue and product pages. Newly submitted customer reviews start as **pending** and stay hidden until you approve them. **Delete is permanent** (an intentional hard delete for moderation). All actions are CSRF + Post/Redirect/Get.

---

## 11. Reports (`admin/reports.php`)

Charts of live MySQL aggregates. Logic lives in `includes/admin-reports.php`; charts are drawn by `assets/js/admin-reports.js` using Chart.js (loaded only on this page).

Four charts, each with KPI summary cards and a **server-rendered accessible data table** beside the canvas (the table is the source of truth if JavaScript fails):

- **Orders by status**
- **Active products by performance tier**
- **User accounts by role and by status**
- **Inventory health** (healthy / low / out of stock / disabled)

---

## 12. Themes (`admin/themes.php`)

Switches the site-wide look. Logic lives in `includes/admin-themes.php`.

- Lists the three seeded themes: **RGB Gaming**, **Minimal Professional**, **Cyber Grid**.
- Activating one writes `site_settings.active_theme_id` (CSRF + Post/Redirect/Get). The shared header picks up the change immediately across both public and admin pages.
- A theme whose CSS file is missing on disk **cannot** be activated. Even if the setting were somehow invalid, the resolver falls back safely so the site is never left unstyled (see the theme resolver in [`docs/frontend-documentation.md`](frontend-documentation.md) §6).

---

## 13. Monitoring (`admin/monitoring.php`) — planned

The tool registry lists a **Monitoring** page for service health checks (online / warning / offline). It is scheduled for a later stage; until its file exists, the dashboard shows it as unavailable and does not link to it.

---

## 14. Security habits for administrators

- Keep `config/app.php → debug` set to **`false`** on the live server so errors never leak details to visitors.
- Never share admin credentials; create a separate admin account per staff member via §2.
- Prefer **disable** over delete for products and users to preserve history.
- Remember all destructive/administrative actions require the on-page forms (CSRF-protected); a copied URL alone cannot change data.
- Review the dashboard **attention alerts** regularly — pending reviews and open consultations are customer-facing waits.

---

## 15. Status

**Summary.** Every shipped administrator tool (dashboard, products, options, compatibility, orders, users, consultations, reviews, reports, themes) is documented with its purpose, key actions, and safety rules, matched to the live pages and their `includes/admin-*.php` logic. Supports rubric rows **B7 / B8 (admin data + user administration)** and **B9 (admin user documentation)**.
