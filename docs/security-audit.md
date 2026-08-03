# CustomCore — Security Audit: Prepared Statements & Output Escaping

**Document type:** Stage 14 security audit (Commit 14.8)
**Purpose:** Record the evidence-based audit of every SQL execution path and every dynamic output site, confirming that no user input is concatenated into SQL and that all output is escaped.
**Audience:** Developers, reviewers, and whoever maintains the live site.
**Related:** CSRF protection (Commit 14.9), upload hardening (Commit 14.10), session hardening in [`includes/functions.php`](../includes/functions.php), and the [rubric checklist](rubric-checklist.md).

---

## 1. Scope & result

This audit covers the two acceptance criteria for Commit 14.8:

1. **Prepared statements** — no raw user input is concatenated into SQL.
2. **Output escaping** — every dynamic value written to the page is escaped.

**Result: PASS.** Across the whole application (282 PHP functions, 41 view templates, 11 JavaScript modules) the audit found **zero SQL-injection and zero XSS vulnerabilities**. No source changes were required; this document is the audit record. Defense-in-depth patterns (integer clamping, identifier whitelists, open-redirect guards, client-side escaping) are already in place.

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

## 5. Related protections (defense-in-depth)

These are outside 14.8's two criteria but were confirmed during the audit:

- **Open redirect / header injection** — redirect targets are fixed literals or validated by `customcore_is_safe_return_target()` / `customcore_is_safe_local_path()`, which reject absolute URLs, protocol-relative `//host`, path traversal, backslashes, and CR/LF/NUL.
- **Passwords** — `password_hash()` / `password_verify()` (never stored or echoed).
- **Sessions** — HTTP-only + `SameSite=Lax` cookies, `Secure` under HTTPS, strict mode, idle/absolute timeouts (`customcore_session_harden()`).
- **Error messages** — database/monitoring errors are scrubbed in production (`customcore_is_debug()` gate) so stack traces and paths never leak.
- **CSRF** — state-changing forms already carry tokens; formalised and audited in **Commit 14.9**.

---

## 6. Re-running the audit

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
```

---

## 7. Sign-off

| Criterion | Result |
|-----------|--------|
| No user input concatenated into SQL | **PASS** |
| All output escaped (server + client) | **PASS** |
| Source changes required | None |

Audited for Commit 14.8. Findings are current as of the Stage 14 security pass; re-run Section 6 after adding new queries or output sites.
