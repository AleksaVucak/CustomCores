# CustomCore | Security Audit: SQL, Output Escaping, CSRF & File Uploads

**Document type:** Project documentation
**Purpose:** Record the evidence-based audit of every SQL execution path, every dynamic output site, every state-changing request, and every file-upload path — confirming that no user input is concatenated into SQL, that all output is escaped, that every state-changing request requires a valid CSRF token, and that uploads are validated and dangerous files rejected.
**Audience:** Developers, reviewers, and whoever maintains the live site.
**Related:** session hardening in [`includes/functions.php`](../includes/functions.php) and the [rubric checklist](rubric-checklist.md).

---

## 1. Scope & result

This audit covers four acceptance criteria:

1. **Prepared statements** — no raw user input is concatenated into SQL.
2. **Output escaping** — every dynamic value written to the page is escaped.
3. **CSRF protection** — every state-changing request requires a valid token; missing/invalid tokens are rejected.
4. **File upload security** — uploads validate type/MIME/size/name/storage; invalid and dangerous files are rejected.

**Result: PASS.** Across the whole application (282 PHP functions, 41 view templates, 11 JavaScript modules) the audit found **zero SQL-injection and zero XSS vulnerabilities**, one CSRF gap (logout via GET) **fixed** (see §5.3), and robust upload validation to which one storage-execution hardening was **added** (see §6.4). Defense-in-depth patterns (integer clamping, identifier whitelists, open-redirect guards, client-side escaping, content-based upload detection) are in place.

---

## 2. Methodology

The audit combined exhaustive pattern search with manual review of every dynamic site:

- Enumerated every database call: `->query(`, `->exec(`, `->prepare(` (0 `->exec` calls exist).
- Searched for the real injection risks — string-built SQL and interpolation — with patterns such as `ORDER BY`, `LIMIT`, `implode(`, `.= `, `sprintf(`, `{$`, and double‑quoted SQL containing `$`.
- Inspected every dynamic SQL fragment (WHERE builders, `IN (...)` lists, `LIMIT`/`OFFSET`, dynamic `SET`, and any interpolated identifier) to confirm placeholders, integer casts, or whitelists.
- Enumerated output sites: `echo $`, `<?= $`, `print $`, direct superglobal output, and JavaScript `innerHTML` / `insertAdjacentHTML` / `document.write`.
- Verified the escaping helper (`customcore_e`) and the redirect guards.

---

## 3. SQL injection audit (prepared statements)

### 3.1 How queries run

All database access goes through a single PDO connection (`customcore_pdo`). Every query is either:

- a **static string literal** passed to `PDO::query` (contains no variables), or
- a **prepared statement** (`PDO::prepare` + `execute`) with **named or positional bound parameters**.

There are **no `->exec` calls** and **no `mysqli`/string-interpolated queries**.

### 3.2 Static `->query` sites — no user input

Every `->query` call uses a constant SQL literal (catalogue/category/brand lists, dashboard aggregates, theme lookups, monitoring `SELECT 1`, etc.). Representative examples:

- [`index.php`](../index.php) featured products / categories
- [`includes/admin.php`](../includes/admin.php) dashboard count aggregates
- [`includes/admin-products.php`](../includes/admin-products.php) `SELECT DISTINCT brand...`
- [`includes/theme.php`](../includes/theme.php) active-theme lookup

None interpolate a variable.

### 3.3 Prepared statements with bound parameters

All value-carrying queries bind parameters. Examples: authentication (`login.php` — `email =:email`), registration duplicate check, product/option/review reads, order and consultation reads, cart/wishlist writes, and every admin create/update.

### 3.4 Dynamic SQL — why each pattern is safe

| Pattern | Where | Why it is safe |
| --- | --- | --- |
| Dynamic `WHERE` building | `includes/admin-products.php`, `includes/admin-users.php`, `includes/admin-orders.php`, `includes/admin-reviews.php`, `includes/admin-compatibility.php`, `order-history.php`, `reviews.php` | Only **fixed clause literals** are appended (e.g. `' AND o.status =:status'`); user values are always **bound** (`LIKE:s_name` with `$like = '%'.$search.'%'`). |
| `IN (...)` lists | `compare.php`, `builder-results.php`, `includes/performance.php`, `includes/compatibility.php`, `api/builder-price.php` | Placeholders are generated (`implode(',', array_fill(0, count($ids), '?'))` or `:id0..:idN`) and the **IDs are bound**, never inlined. |
| `LIMIT` / `OFFSET` | `includes/admin-users.php`, `includes/admin-orders.php`, `includes/admin-reviews.php`, `includes/admin-consultations.php` | `$perPage` is `int`-typed and clamped `max(5, min(100, …))`; `$offset = ($page-1)*$perPage` with `$page` clamped to `[1, $pages]`. Both are guaranteed integers (PDO cannot bind `LIMIT` under emulated prepares, so integer casting is the correct approach). |
| Dynamic `SET` (UPDATE) | `includes/admin-products.php`, `includes/admin-compatibility.php` | Column names come from **fixed literals / a per-category whitelist** (`isset($allowed[$col])`); every value is bound. |
| Interpolated identifier `{$table}` | `includes/admin-user_count_table` in `includes/admin-users.php` | Called only with **internal literals** (`'reviews'`, `'consultation_requests'`) and additionally guarded by `preg_match('/^[a-z_]+$/', $table)`. |

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

Every dynamic string rendered into HTML passes through `customcore_e` — element text, attribute values, and `<meta>` content. The only raw `echo $...` sites are provably safe:

- **Boolean/ternary toggles** emitting literal strings — e.g. `echo $isActive ? 'selected': ''`, `echo $isActive ? 'Disable': 'Enable'`.
- **Integers** that are cast at their source — e.g. `$compId = (int) …`, `$avgGaming = (int) …`, pagination values.
- **Pre-escaped strings** — e.g. `$inputName = 'option_'. customcore_e($groupName)`, the `$fieldError` closure which wraps the message in `customcore_e`.
- **XML** — `sitemap.php` echoes `$xml`, built by `customcore_seo_build_sitemap_xml` which XML-escapes every value; served with an XML content type.
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

## 5. CSRF protection audit

**Result: PASS.** Every state-changing request requires a valid per-session CSRF token, and missing/invalid tokens are rejected.

### 5.1 The token mechanism

`includes/csrf.php` provides three helpers:

- `customcore_csrf_token` — 32 random bytes (`random_bytes`, hex) stored in the session.
- `customcore_csrf_field` — renders `<input type="hidden" name="_csrf" value="…">` (escaped).
- `customcore_csrf_verify(?string $token)` — compares with `hash_equals` (timing-safe); returns `false` for a missing, empty, or non-matching token.

### 5.2 Coverage

- **Every `<form method="post">`** in the app renders `customcore_csrf_field` — verified by cross-referencing all POST forms against `csrf_field` calls (counts match per file).
- **Every POST handler** calls `customcore_csrf_verify($_POST['_csrf'] ?? null)` and **rejects on failure** — either a redirect with a "session expired" flash (`cart`, `wishlist`, `reviews`, `saved-build`) or a blocking error branch that prevents the mutation (`login`, `register`, `checkout`, `consultation`, `builder`, `builder-results`, `edit-profile`, `contact`, and all `admin/*` write pages).
- **Read-only `api/` endpoints** (`builder-price.php`, `compatibility-check.php`, `chart-data.php`) accept POST but perform **no writes** (no `INSERT`/`UPDATE`/`DELETE`/`exec`), so they are not state-changing and do not require a token.

### 5.3 Gap found and fixed: logout CSRF

**Finding:** `logout.php` was reachable by **GET** via a plain nav link with no token. Logging out is state-changing, so this was a logout-CSRF vector (e.g. `<img src="logout.php">` forcing a sign-out).

**Fix:**
- `logout.php` now performs the logout **only** on a POST request with a valid CSRF token; a GET or a missing/invalid token redirects the visitor without touching the session.
- The logout controls in `includes/navigation.php` and `includes/account-nav.php` are now `<form method="post" action="logout.php">` with `customcore_csrf_field` and a submit button styled to match the surrounding links (`assets/css/main.css`).

Verified truth table (exact conditions used by `logout.php`):

| Request | Result |
| --- | --- |
| GET + valid token | redirect, **no logout** |
| POST + no token | redirect, **no logout** |
| POST + wrong token | redirect, **no logout** |
| POST + valid token | **logout performed** |

Also confirmed over HTTP: `GET /logout.php` and a token-less `POST /logout.php` both return `303 → login.php` without clearing an authenticated session.

---

## 6. File upload security audit

**Result: PASS.** Both upload surfaces validate type, MIME, size, name, and storage, and reject invalid/dangerous files. One defense-in-depth hardening (script execution in the storage dirs) was added.

### 6.1 Surfaces

| Surface | Who | Handler | Storage |
| --- | --- | --- | --- |
| Product images | Admin | `includes/admin-products.php` (`…_validate_image`, `…_store_image`) | `uploads/products/` |
| Consultation attachments | Customer | `includes/consultations.php` (`…_validate_files`, `…_store_files`) | `uploads/consultation/` |

### 6.2 Checks confirmed

- **Type / MIME (content-based, never trusted from the client)** — the real MIME is detected with `finfo` (`FILEINFO_MIME_TYPE`) and matched against an allowlist; the stored **extension is derived from the detected MIME**, not from the uploaded filename. Product images allow `jpg/png/webp/gif`; consultation allows `pdf/txt/png/jpg/webp`. **SVG is deliberately excluded** (it can carry script). If `finfo` is unavailable the upload **fails closed**.
- **Size** — empty files rejected; each file capped at `upload_max_bytes` (2 MB, `config/app.php`); PHP `UPLOAD_ERR_INI_SIZE`/`FORM_SIZE` handled. Consultation additionally caps the count at `CUSTOMCORE_CONSULTATION_MAX_FILES`.
- **Name** — the on-disk name is always `bin2hex(random_bytes)` + the trusted extension, so no user input reaches the filesystem path (no traversal, no double extension, no collisions). The original name is sanitized (`basename`, control chars stripped, length-clamped) and kept **for display only**.
- **Storage** — `is_uploaded_file` + `move_uploaded_file`, `chmod 0644`, dir created `0755`; consultation storage runs inside the request transaction and cleans up moved files on rollback. Deletion is whitelisted by regex to `uploads/products/…` and refuses `..`.
- **Serving** — attachments are streamed only through `consultation-attachment.php` / `admin/consultation-attachment.php`: login/ownership (or admin) enforced, generic 404 (no enumeration), `id` validated with `ctype_digit`, the on-disk path is `basename`-guarded **and** confirmed inside the upload dir via `realpath`, and the response uses `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, an RFC 5987 filename, and `Cache-Control: private, no-store` with the output buffer flushed before `readfile`.

### 6.3 Rejection demonstrated

Running the app's exact `finfo` + allowlist logic against sample files:

| File | Detected MIME | Product | Consultation |
| --- | --- | --- | --- |
| `shell.php` (webshell) | `text/x-php` | reject | reject |
| `evil.jpg` (PHP renamed `.jpg`) | `text/x-php` | reject | reject |
| `evil.svg` (`<script>`) | `image/svg+xml` | reject | reject |
| `real.pdf` | `application/pdf` | reject | accept → `.pdf` |
| `real.txt` | `text/plain` | reject | accept → `.txt` |
| malformed image | `application/octet-stream` | reject | reject |

A disguised extension is ignored because the decision is content-based.

### 6.4 Hardening added

The upload dirs already had an `index.php` 403 guard against browsing. Added `.htaccess` for defense-in-depth against code execution should the application controls ever be bypassed:

- `uploads/products/.htaccess` — `Options -Indexes`, disables the PHP engine (mod_php), and denies serving/executing any script-like extension (`.php/.phtml/.phar/.cgi/.pl/.py/.sh/…`). Images are still served normally.
- `uploads/consultation/.htaccess` — `Options -Indexes` and **denies all direct web access** (these files are only ever delivered through the download endpoints; the `readfile` path is unaffected).

Both files are Apache 2.2/2.4-safe (authz guarded per version), no-ops under PHP-FPM/non-Apache, and are tracked in git (`.gitignore` exceptions added).

---

## 7. Related protections (defense-in-depth)

These are outside the SQL/escaping criteria but were confirmed during the audit:

- **Open redirect / header injection** — redirect targets are fixed literals or validated by `customcore_is_safe_return_target` / `customcore_is_safe_local_path`, which reject absolute URLs, protocol-relative `//host`, path traversal, backslashes, and CR/LF/NUL.
- **Passwords** — `password_hash` / `password_verify` (never stored or echoed).
- **Sessions** — HTTP-only + `SameSite=Lax` cookies, `Secure` under HTTPS, strict mode, idle/absolute timeouts (`customcore_session_harden`).
- **Error messages** — database/monitoring errors are scrubbed in production (`customcore_is_debug` gate) so stack traces and paths never leak.
- **File uploads** — fully audited (see §6): content-based `finfo` allowlist, size/count limits, random on-disk names, hardened serving endpoints, and `.htaccess` execution guards.

---

## 8. Re-running the audit

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
rg -n "echo\s+\$_(GET|POST|REQUEST|SERVER|COOKIE)" --glob '*.php' # expect: no matches

# 5. Client-side HTML injection (verify escapeHtml / static):
rg -n "innerHTML|insertAdjacentHTML|document\.write" assets/js

# 6. CSRF — compare POST forms against csrf_field renders, and every
# POST handler against a csrf_verify call:
rg -n 'method=["'"'"']post' -i --glob '*.php'
rg -c "customcore_csrf_field\(" --glob '*.php'
rg -n "customcore_csrf_verify\(" --glob '*.php'

# 7. File uploads — every handler (verify finfo allowlist, size, random name):
rg -n "\$_FILES|move_uploaded_file|is_uploaded_file|finfo_" --glob '*.php'
ls -la uploads/products/.htaccess uploads/consultation/.htaccess # storage hardening present
```

---

## 9. Sign-off

| Criterion | Result |
| --- | --- |
| No user input concatenated into SQL | **PASS** |
| All output escaped, server + client | **PASS** |
| CSRF token on every state-changing request; invalid rejected | **PASS** |
| Uploads validate type/MIME/size/name/storage; dangerous files rejected | **PASS** |
| Source changes required | SQL/escape: none · CSRF: logout hardened to token-verified POST · uploads: added upload-dir `.htaccess` execution guards |

Findings are current as of the security pass; re-run Sections 3–8 after adding new queries, output sites, POST endpoints, or upload handlers.
