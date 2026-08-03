# CustomCore — Security Audit: SQL, Output Escaping & CSRF

**Document type:** Stage 14 security audit (Commits 14.8 & 14.9)
**Purpose:** Record the evidence-based audit of every SQL execution path, every dynamic output site, and every state-changing request, confirming that no user input is concatenated into SQL, that all output is escaped, and that every state-changing request requires a valid CSRF token.
**Audience:** Developers, reviewers, and whoever maintains the live site.
**Related:** upload hardening (Commit 14.10), session hardening in [`includes/functions.php`](../includes/functions.php), and the [rubric checklist](rubric-checklist.md).

---

## 1. Scope & result

This audit covers three acceptance criteria across two commits:

1. **Prepared statements** (14.8) — no raw user input is concatenated into SQL.
2. **Output escaping** (14.8) — every dynamic value written to the page is escaped.
3. **CSRF protection** (14.9) — every state-changing request requires a valid token; missing/invalid tokens are rejected.

**Result: PASS.** Across the whole application (282 PHP functions, 41 view templates, 11 JavaScript modules) the audit found **zero SQL-injection and zero XSS vulnerabilities**, and one CSRF gap (logout via GET) which was **fixed** in 14.9 (see §5.3). Defense-in-depth patterns (integer clamping, identifier whitelists, open-redirect guards, client-side escaping) are in place.

---

## 2. Methodology

The audit combined exhaustive pattern search with manual review of every dynamic site:

- Enumerated every database call: `->query(`, `->exec(`, `->prepare(` (0 `->exec()` calls exist).
- Searched for the real injection risks — string-built SQL and interpolation — with patterns such as `ORDER BY`, `LIMIT`, `implode(`, `.= `, `sprintf(`, `{$`, and double‑quoted SQL containing `$`.
- Inspected every dynamic SQL fragment (WHERE builders, `IN (...)` lists, `LIMIT`/`OFFSET`, dynamic `SET`, and any interpolated identifier) to confirm placeholders, integer casts, or whitelists.
- Enumerated output sites: `echo $`, `<?= $`, `print $`, direct superglobal output, and JavaScript `innerHTML` / `insertAdjacentHTML` / `document.write`.
- Verified the escaping helper (`customcore_e()`) and the redirect guards.

---

## 3. SQL injection audit (prepared statements)

### 3.1 How queries run

All database access goes through a single PDO connection (`customcore_pdo()`). Every query is either:

- a **static string literal** passed to `PDO::query()` (contains no variables), or
- a **prepared statement** (`PDO::prepare()` + `execute()`) with **named or positional bound parameters**.

There are **no `->exec()` calls** and **no `mysqli`/string-interpolated queries**.

### 3.2 Static `->query()` sites — no user input

Every `->query()` call uses a constant SQL literal (catalogue/category/brand lists, dashboard aggregates, theme lookups, monitoring `SELECT 1`, etc.). Representative examples:

- [`index.php`](../index.php) featured products / categories
- [`includes/admin.php`](../includes/admin.php) dashboard count aggregates
- [`includes/admin-products.php`](../includes/admin-products.php) `SELECT DISTINCT brand ...`
- [`includes/theme.php`](../includes/theme.php) active-theme lookup

None interpolate a variable.

### 3.3 Prepared statements with bound parameters

All value-carrying queries bind parameters. Examples: authentication (`login.php` — `email = :email`), registration duplicate check, product/option/review reads, order and consultation reads, cart/wishlist writes, and every admin create/update.

### 3.4 Dynamic SQL — why each pattern is safe

| Pattern | Where | Why it is safe |
|--------|-------|----------------|
| Dynamic `WHERE` building | `includes/admin-products.php`, `includes/admin-users.php`, `includes/admin-orders.php`, `includes/admin-reviews.php`, `includes/admin-compatibility.php`, `order-history.php`, `reviews.php` | Only **fixed clause literals** are appended (e.g. `' AND o.status = :status'`); user values are always **bound** (`LIKE :s_name` with `$like = '%'.$search.'%'`). |
| `IN (...)` lists | `compare.php`, `builder-results.php`, `includes/performance.php`, `includes/compatibility.php`, `api/builder-price.php` | Placeholders are generated (`implode(',', array_fill(0, count($ids), '?'))` or `:id0..:idN`) and the **IDs are bound**, never inlined. |
| `LIMIT` / `OFFSET` | `includes/admin-users.php`, `includes/admin-orders.php`, `includes/admin-reviews.php`, `includes/admin-consultations.php` | `$perPage` is `int`-typed and clamped `max(5, min(100, …))`; `$offset = ($page-1)*$perPage` with `$page` clamped to `[1, $pages]`. Both are guaranteed integers (PDO cannot bind `LIMIT` under emulated prepares, so integer casting is the correct approach). |
| Dynamic `SET` (UPDATE) | `includes/admin-products.php`, `includes/admin-compatibility.php` | Column names come from **fixed literals / a per-category whitelist** (`isset($allowed[$col])`); every value is bound. |
| Interpolated identifier `{$table}` | `includes/admin-user_count_table()` in `includes/admin-users.php` | Called only with **internal literals** (`'reviews'`, `'consultation_requests'`) and additionally guarded by `preg_match('/^[a-z_]+$/', $table)`. |

### 3.5 Conclusion

No branch concatenates user-supplied data into a SQL string. **Acceptance met: no direct user input in SQL.**

---

## 4. Output escaping audit (XSS)

### 4.1 The escaping helper

```php
function customcore_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
```

`ENT_QUOTES` escapes both single and double quotes (safe inside any attribute); `ENT_SUBSTITUTE` replaces invalid byte sequences instead of returning an empty string.

### 4.2 Server-side output

Every dynamic string rendered into HTML passes through `customcore_e()` — element text, attribute values, and `<meta>` content. The only raw `echo $...` sites are provably safe:

- **Boolean/ternary toggles** emitting literal strings — e.g. `echo $isActive ? 'selected' : ''`, `echo $isActive ? 'Disable' : 'Enable'`.
- **Integers** that are cast at their source — e.g. `$compId = (int) …`, `$avgGaming = (int) …`, pagination values.
- **Pre-escaped strings** — e.g. `$inputName = 'option_' . customcore_e($groupName)`, the `$fieldError()` closure which wraps the message in `customcore_e()`.
- **XML** — `sitemap.php` echoes `$xml`, built by `customcore_seo_build_sitemap_xml()` which XML-escapes every value; served with an XML content type.
- **CLI output** — `database/create-admin.php` writes to the terminal, not the browser.

No superglobal (`$_GET` / `$_POST` / `$_SERVER` / `$_COOKIE`) is echoed directly, so there is no reflected-XSS surface.

### 4.3 Client-side output

Every JavaScript `innerHTML` write is either a **static literal** or passes dynamic values through a textNode-based escaper:

```js
function escapeHtml(str) {
  var div = document.createElement("div");
  div.appendChild(document.createTextNode(str || ""));
  return div.innerHTML;
}
```

- `assets/js/builder.js` — escapes `r.name` / `r.message` from the compatibility API before insertion.
- `assets/js/charts.js` — escapes fallback row `label` / `value`.
- `assets/js/store-map.js` — builds the popup with `document.createElement` + `textContent` (no HTML injection).

### 4.4 Conclusion

All dynamic output is escaped on the server and on the client. **Acceptance met: output escaped.**

---

## 5. CSRF protection audit (Commit 14.9)

**Result: PASS.** Every state-changing request requires a valid per-session CSRF token, and missing/invalid tokens are rejected.

### 5.1 The token mechanism

`includes/csrf.php` provides three helpers:

- `customcore_csrf_token()` — 32 random bytes (`random_bytes(32)`, hex) stored in the session.
- `customcore_csrf_field()` — renders `<input type="hidden" name="_csrf" value="…">` (escaped).
- `customcore_csrf_verify(?string $token)` — compares with `hash_equals()` (timing-safe); returns `false` for a missing, empty, or non-matching token.

### 5.2 Coverage

- **Every `<form method="post">`** in the app renders `customcore_csrf_field()` — verified by cross-referencing all POST forms against `csrf_field()` calls (counts match per file).
- **Every POST handler** calls `customcore_csrf_verify($_POST['_csrf'] ?? null)` and **rejects on failure** — either a redirect with a "session expired" flash (`cart`, `wishlist`, `reviews`, `saved-build`) or a blocking error branch that prevents the mutation (`login`, `register`, `checkout`, `consultation`, `builder`, `builder-results`, `edit-profile`, `contact`, and all `admin/*` write pages).
- **Read-only `api/` endpoints** (`builder-price.php`, `compatibility-check.php`, `chart-data.php`) accept POST but perform **no writes** (no `INSERT`/`UPDATE`/`DELETE`/`exec`), so they are not state-changing and do not require a token.

### 5.3 Gap found and fixed: logout CSRF

**Finding:** `logout.php` was reachable by **GET** via a plain nav link with no token. Logging out is state-changing, so this was a logout-CSRF vector (e.g. `<img src="logout.php">` forcing a sign-out).

**Fix:**
- `logout.php` now performs the logout **only** on a POST request with a valid CSRF token; a GET or a missing/invalid token redirects the visitor without touching the session.
- The logout controls in `includes/navigation.php` and `includes/account-nav.php` are now `<form method="post" action="logout.php">` with `customcore_csrf_field()` and a submit button styled to match the surrounding links (`assets/css/main.css`).

Verified truth table (exact conditions used by `logout.php`):

| Request | Result |
|--------|--------|
| GET + valid token | redirect, **no logout** |
| POST + no token | redirect, **no logout** |
| POST + wrong token | redirect, **no logout** |
| POST + valid token | **logout performed** |

Also confirmed over HTTP: `GET /logout.php` and a token-less `POST /logout.php` both return `303 → login.php` without clearing an authenticated session.

---

## 6. Related protections (defense-in-depth)

These are outside the SQL/escaping criteria but were confirmed during the audit:

- **Open redirect / header injection** — redirect targets are fixed literals or validated by `customcore_is_safe_return_target()` / `customcore_is_safe_local_path()`, which reject absolute URLs, protocol-relative `//host`, path traversal, backslashes, and CR/LF/NUL.
- **Passwords** — `password_hash()` / `password_verify()` (never stored or echoed).
- **Sessions** — HTTP-only + `SameSite=Lax` cookies, `Secure` under HTTPS, strict mode, idle/absolute timeouts (`customcore_session_harden()`).
- **Error messages** — database/monitoring errors are scrubbed in production (`customcore_is_debug()` gate) so stack traces and paths never leak.
- **File uploads** — audited in **Commit 14.10**.

---

## 7. Re-running the audit

To reproduce from the repo root:

```bash
# 1. Every DB call (verify each is static or uses bound params):
rg -n "->query\(|->exec\(|->prepare\(" --glob '*.php'

# 2. Dynamic-SQL risk patterns (verify placeholders / int casts / whitelists):
rg -n "ORDER BY|LIMIT|implode\(|\.= |sprintf\(|\{\$" --glob '*.php'

# 3. Double-quoted SQL containing a variable (should be only whitelisted identifiers):
rg -n '"[^"]*(SELECT|INSERT|UPDATE|DELETE|WHERE|VALUES|SET|FROM)[^"]*\$' -i --glob '*.php'

# 4. Direct/unescaped output (verify each is boolean/int/pre-escaped):
rg -n "echo \$|<\?=\s*\$|print \$" --glob '*.php'
rg -n "echo\s+\$_(GET|POST|REQUEST|SERVER|COOKIE)" --glob '*.php'   # expect: no matches

# 5. Client-side HTML injection (verify escapeHtml / static):
rg -n "innerHTML|insertAdjacentHTML|document\.write" assets/js

# 6. CSRF — compare POST forms against csrf_field() renders, and every
#    POST handler against a csrf_verify() call:
rg -n 'method=["'"'"']post' -i --glob '*.php'
rg -c "customcore_csrf_field\(" --glob '*.php'
rg -n "customcore_csrf_verify\(" --glob '*.php'
```

---

## 8. Sign-off

| Criterion | Result |
|-----------|--------|
| No user input concatenated into SQL (14.8) | **PASS** |
| All output escaped, server + client (14.8) | **PASS** |
| CSRF token on every state-changing request; invalid rejected (14.9) | **PASS** |
| Source changes required | 14.8: none · 14.9: logout hardened to token-verified POST |

Audited for Commits 14.8 & 14.9. Findings are current as of the Stage 14 security pass; re-run Sections 7–8 after adding new queries, output sites, or POST endpoints.
