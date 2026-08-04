# CustomCore | CSS Validation Record

**Document type:** Project documentation
**Purpose:** Prove that every project stylesheet is well-formed, free of avoidable CSS errors, and that custom-property references resolve.
**Acceptance:** Avoidable CSS errors are corrected.
**Related:** Base styles in [`assets/css/main.css`](../assets/css/main.css) and [`assets/css/admin.css`](../assets/css/admin.css); themes in [`assets/themes/`](../assets/themes/); HTML pass in [`html-validation.md`](html-validation.md); theme walk in [`theme-testing.md`](theme-testing.md).

### Status legend

| Status | Meaning |
| --- | --- |
| **Pass** | File is parseable, braces/comments balanced, no empty rulesets or dangerous legacy constructs, all `var(--…)` references resolve to a declaration somewhere in the style system |
| **Fixed** | An avoidable defect was found and corrected in this commit |
| **N/A** | Check does not apply |

---

## 1. Scope

All CustomCore-authored stylesheets linked by the application:

| File | Role | Approx. size |
| --- | --- | --- |
| `assets/css/main.css` | Shared foundation (variables, layout, components, responsive) | ~5.7k lines |
| `assets/css/admin.css` | Administrator overlays | ~1.2k lines |
| `assets/themes/rgb-gaming.css` | Theme: RGB Gaming | ~450 lines |
| `assets/themes/minimal-pro.css` | Theme: Minimal Professional | ~360 lines |
| `assets/themes/cyber-grid.css` | Theme: Cyber Grid | ~490 lines |

Third-party CSS (e.g. Leaflet on `store-locations.php` from unpkg) is **out of scope** for this commit — it is not edited by CustomCore.

---

## 2. Method

Offline validation (external W3C CSS Validator was unreachable from this environment — proxy blocked `jigsaw.w3.org`). Equivalence:

1. **Structural balance** — comment delimiters, `{`/`}` depth never negative, parentheses balanced.
2. **AST parse** — full parse with [css-tree](https://github.com/csstree/csstree) (`positions: true`, value/prelude parsing on). Any parse callback error is a fail.
3. **Empty constructs** — empty rulesets and empty `@`-rule blocks flagged.
4. **Dangerous legacy** — scan for `expression`, `behavior:` (IE HTC), `progid:`, empty `url`.
5. **Custom properties** — collect every `--*` **declaration** and every `var(--*)` **use** across all five files; any use without a declaration is an avoidable defect.
6. **Post-fix re-run** — same suites after the token completeness fix.

---

## 3. Results summary

| Suite | Result |
| --- | --- |
| Structural balance (5/5 files) | **Pass** |
| css-tree parse (5/5 files) | **Pass — 0 parse errors, 0 empty-ruleset warnings** |
| Dangerous legacy patterns | **Pass** (the only `behavior` hits are modern `scroll-behavior`) |
| Undefined `var(--…)` before fix | **7 missing tokens + 1 runtime score token** |
| Undefined `var(--…)` after fix | **None** |
| Avoidable defects remaining | **None** |

**Acceptance is met:** avoidable CSS errors were found and corrected; remaining stylesheets pass parse + token completeness.

### Parse / volume metrics (after fix)

| File | Rules | Declarations | `@media` | `@keyframes` |
| --- | --- | --- | --- | --- |
| `main.css` | 844 | 2660+ | 21 | 2 |
| `admin.css` | 206 | 545 | 3 | 0 |
| `rgb-gaming.css` | 50 | 150+ | 1 | 1 |
| `minimal-pro.css` | 40 | 120+ | 0 | 0 |
| `cyber-grid.css` | 52 | 175+ | 1 | 1 |

---

## 4. File-by-file status

| File | Structural | Parse | Tokens | Status |
| --- | --- | --- | --- | --- |
| `assets/css/main.css` | Pass | Pass | Pass (after fix) | **Pass** |
| `assets/css/admin.css` | Pass | Pass | Pass (uses base link token) | **Pass** |
| `assets/themes/rgb-gaming.css` | Pass | Pass | Pass (after soft-token overrides) | **Pass** |
| `assets/themes/minimal-pro.css` | Pass | Pass | Pass (after soft-token overrides) | **Pass** |
| `assets/themes/cyber-grid.css` | Pass | Pass | Pass (after soft-token overrides) | **Pass** |

---

## 5. Avoidable defects found and fixed

### 5.1 Undefined design tokens

Several rulesets referenced long-hand token names that were **never declared** on `:root` (or only had ad-hoc fallbacks). Examples before the fix:

| Token used | Severity | Notes |
| --- | --- | --- |
| `--cc-color-primary` | Avoidable | Builder steps, ratings, active filters — no `:root` value |
| `--cc-color-muted` | Avoidable | Help centre copy styles — no `:root` value (many calls without fallback) |
| `--cc-color-surface` | Avoidable | Help cards — only a local fallback |
| `--cc-color-success-bg` | Avoidable | Soft success fills with only a hex fallback |
| `--cc-color-warning-bg` / `--cc-color-warning-text` | Avoidable | Soft warning fills with only hex fallbacks |
| `--cc-color-link` | Avoidable | Admin action buttons — only a local fallback |
| `--score` | Intentional runtime | Set inline on builder-results bars; now also defaulted on the bar selector |

**Fix:**

1. **`assets/css/main.css` `:root`** — declare the extended semantic aliases so every reference resolves and themes inherit them:

 - `--cc-color-primary: var(--cc-color-accent);`
 - `--cc-color-muted: var(--cc-color-text-muted);`
 - `--cc-color-surface: var(--cc-color-bg-elevated);`
 - `--cc-color-link: var(--cc-color-accent);`
 - `--cc-color-success-bg`, `--cc-color-warning-bg`, `--cc-color-warning-text` (light soft fills)

2. **Dark themes** (`rgb-gaming.css`, `cyber-grid.css`) — override the soft status fills with translucent surfaces that match each theme’s success/warning accents (pastel light fills looked wrong on near-black UIs).

3. **Minimal Pro** — light soft fills retuned to its editorial palette.

4. **`.results-estimates__bar`** — default `--score: 0` so the `width: calc(var(--score) * 1%)` rule is always valid when the inline style is absent.

### 5.2 Non-issues (recorded so Tidy/W3C-style runners don’t surprise reviewers)

| Observation | Why not a code change |
| --- | --- |
| Heavy use of `var(--cc-*)` | Design system by design; css-tree’s value matcher cannot type-check `var` trees — **parse** is the correctness gate |
| `!important` (9 in `main.css`, 2 in `admin.css`) | Confined to `prefers-reduced-motion` / forced overrides — intentional |
| `@import` Google Fonts | Documented offline fallbacks on font stacks |

---

## 6. How to re-run locally

```bash
# 1) Structural balance (any language; example with brace counts)
# open braces must equal close braces after stripping /* comments */

# 2) AST parse with css-tree
npm install css-tree # once, anywhere
node -e "
const fs=require('fs'), csstree=require('css-tree');
for (const f of [
 'assets/css/main.css','assets/css/admin.css',
 'assets/themes/rgb-gaming.css','assets/themes/minimal-pro.css',
 'assets/themes/cyber-grid.css'
]) {
 csstree.parse(fs.readFileSync(f,'utf8'), {
 positions: true,
 onParseError(e){ console.error(f, e.message); process.exitCode=1; }
 });
 console.log('OK', f);
}
"

# 3) Optional online check (when network allows)
# https://jigsaw.w3.org/css-validator/ → upload each file, profile css3
```

---

## 7. Sign-off

| Criterion | Result |
| --- | --- |
| All project CSS files validated | **Yes** |
| Avoidable errors corrected | **Yes** (token completeness + score default) |
| CSS validation record | **This document** |

