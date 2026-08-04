# CustomCore — Customer Workflow Verification (Commit 15.6)

**Document type:** Stage 15 verification  
**Purpose:** Prove that every core customer action succeeds end-to-end — from account registration all the way through placing an order — exercising the real PHP handlers, session/auth, CSRF, and database writes.  
**Acceptance:** Every core customer action succeeds; any avoidable defect found in a customer workflow is corrected.  
**Related:** Prior Stage 15 records [`html-validation.md`](html-validation.md), [`css-validation.md`](css-validation.md), [`js-validation.md`](js-validation.md), [`responsiveness-desktop.md`](responsiveness-desktop.md), [`responsiveness-mobile.md`](responsiveness-mobile.md). Administrator workflows are verified separately in Commit 15.7.

### Status legend

| Status | Meaning |
| ------ | ------- |
| **Pass** | The action completed with the expected result (correct HTTP status/redirect, DB row created/updated, or correct rejection of invalid input) |
| **Fail** | The action did not complete or produced a wrong result on a core customer path |

---

## 1. Scope — every core customer action

| Area | Actions verified |
| ---- | ---------------- |
| Account | Register (new + duplicate-email rejection), log in (correct + wrong-password rejection), authenticated session, log out, re-login |
| Catalogue | Browse catalogue, sort + filter (`price_asc`, `in_stock`), search, product detail, compare (multi-product) |
| Wishlist | Add from product page, view list, remove |
| Custom PC builder | Walk all builder steps, live-price API, save a **complete + compatible** build |
| Saved builds | List, view owned build, add saved build to cart |
| Cart | Add product (with options, qty > 1), view, update quantity, add saved build |
| Checkout → order | Checkout with validated shipping + payment, order creation (PRG), order confirmation view, order history list, order details view |
| Reviews | Submit a product review (enters moderation as `pending`) |
| Consultation | Submit a consultation request, view in consultation history |
| Profile | Edit account details, change password (then re-login with the new password) |
| Access control | Order details rejects a non-owned / non-existent order id |

---

## 2. Method

1. **Workflow map.** Every customer action was mapped to its page, handler file, form fields, preconditions (login/ownership/stock), CSRF requirement, and success/redirect behaviour (see §1 of the request; handlers in `includes/*.php` and the page controllers).
2. **Automated end-to-end harness.** A disposable throwaway script drove the running site over the project PHP server (`php -S localhost:8000`) using a real cookie jar (session `CUSTOMCORESESSID`) and correct **CSRF** handling (`_csrf` re-read from each form page before every POST). It performed the full **registration → order** journey plus every action in §1, asserting on each step:
   - HTTP status / `Location` redirect (PRG expected on state changes),
   - presence of expected content (product links, order number `CC-YYYYMMDD-XXXXXX`, cart line items), and
   - correct **rejection** of invalid input (wrong password, duplicate email, foreign order id).
3. **Compatible build selection.** Because the builder legitimately refuses to save an **incompatible** build, a known-compatible complete component set was computed with the application's own `customcore_compatibility_check()` rules and fed through the builder steps, so the save path was exercised on a valid build.
4. **Disposable data + cleanup.** Each run registered a fresh `wf15_6_*@example.test` customer. After verification, **all** test users and their orders, order items, saved builds, carts, wishlists, consultations, and reviews were deleted. Post-cleanup the store was confirmed back to its pre-test state: the three seed customers only, **0 orders, 0 saved builds, 0 carts**.

---

## 3. Results summary

**All core customer actions: Pass — 41/41 assertions.** After the fix in §4, a full registration-to-order journey (including ordering a saved custom build) completes without error, and every invalid-input path is correctly rejected.

| Group | Assertions | Pass | Fail |
| ----- | ---------: | ---: | ---: |
| Account (register / login / session / logout / re-login) | 9 | 9 | 0 |
| Catalogue / search / compare / product | 6 | 6 | 0 |
| Wishlist | 3 | 3 | 0 |
| Builder / saved builds | 6 | 6 | 0 |
| Cart | 4 | 4 | 0 |
| Checkout / order / history / details / access control | 6 | 6 | 0 |
| Reviews | 1 | 1 | 0 |
| Consultation | 3 | 3 | 0 |
| Profile edit / password | 3 | 3 | 0 |
| **Total** | **41** | **41** | **0** |

---

## 4. Defect found and fixed

**Ordering a saved PC build failed (order could not be placed).**

- **Symptom:** With a **saved build** in the cart, submitting checkout and loading `order-confirmation.php` returned the page with *"We could not place your order."* instead of creating the order. A cart containing only catalogue products ordered fine, which masked the bug.
- **Root cause:** `customcore_snapshot_build()` in [`order-confirmation.php`](../order-confirmation.php) joined the component category with the wrong column:

```sql
JOIN component_categories cc ON cc.id = c.category_id   -- ❌ components has no category_id
```

  The `components` table's foreign key is **`component_category_id`** (the `category_id` column belongs to the `products` table). The bad identifier threw `SQLSTATE[42S22] Unknown column 'c.category_id'`, which was caught by the order-creation `try/catch`, rolling back the whole transaction so no order was ever created for any cart containing a saved build.
- **Fix:** use the correct column (matching every other components join in the codebase — `builder-results.php`, `saved-build.php`, `includes/compatibility.php`, `includes/performance.php`, `includes/admin-compatibility.php`):

```sql
JOIN component_categories cc ON cc.id = c.component_category_id   -- ✅
```

- **Verification:** re-running the harness, a checkout containing a saved build now creates the order (PRG → `order-confirmation.php?id=N`), and the stored `order_items.build_snapshot_json` is populated with a valid category/component/price snapshot (~692 B). `php -l order-confirmation.php` is clean.

No other occurrence of the wrong join exists in the codebase (grep-verified).

---

## 5. Design notes

- **Server-authoritative pricing & stock.** Cart add/update recompute prices and clamp quantities server-side; the harness confirmed adds, quantity updates, and option selections all succeed with server-side values.
- **PRG everywhere.** Every state change (register, login, cart, wishlist, save build, checkout, order creation, review, consultation, profile) responds with a redirect, so a refresh never re-submits.
- **Legitimate refusals are not defects.** The builder correctly refuses to save incomplete/incompatible builds; checkout refuses an empty cart; order/consultation views are owner-scoped. These were exercised and behave as intended.
- **Reviews enter moderation.** A submitted review is stored as `pending` and only appears publicly after admin approval (verified via the submit redirect; moderation is an admin action covered in 15.7).

---

## 6. How to re-run

```
# 1. Serve from the project root (connects to MySQL from config/database.php):
php -S localhost:8000

# 2. Walk the journey in a browser (or re-create the harness):
#    register → login → catalogue/search/compare → product → add to cart
#    → builder (complete, compatible) → save build → add build to cart
#    → checkout → order confirmation → order history/details
#    → submit review → consultation → edit profile + change password → logout
#
#    Expect: each state change redirects (PRG) with a success flash; the order
#    receives a CC-YYYYMMDD-XXXXXX number; a saved build can be ordered and its
#    snapshot appears on the order.
```

Testing uses a disposable `@example.test` account that is deleted afterward, returning the store to its seed state.

---

## 7. Sign-off

| Criterion | Result |
| --------- | ------ |
| Every core customer action succeeds (registration → order) | **Yes — 41/41** |
| Invalid input correctly rejected (wrong password, duplicate email, foreign order) | **Yes** |
| Avoidable customer-workflow defects corrected | **1 found, 1 fixed** (order-confirmation saved-build snapshot join) |
| Test data cleaned up; store returned to pre-test state | **Yes** |
| Customer-workflow results recorded | **This document** |

**Commit 15.6 complete.** Next: Commit **15.7** — verify complete administrator workflows.
