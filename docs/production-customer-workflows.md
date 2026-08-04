# CustomCore | Production Customer Workflow Verification
**Document type:** production test record 
**Host:** [https://vucaka.myweb.cs.uwindsor.ca/customcore/](https://vucaka.myweb.cs.uwindsor.ca/customcore/) 
**Date:** 4 August 2026 
**Purpose:** Prove core customer journeys work on the live myweb deploy (not only local PHP). 
**Related:** local record [`customer-workflows.md`](customer-workflows.md); production URL in project README.

### Status legend

| Status | Meaning |
| --- | --- |
| **Pass** | Action completed on the public host with the expected result |
| **Partial** | Page loaded / partial flow proved; full multi-step path not re-run live |
| **Fail** | Unexpected error or broken path |

---

## 1. Environment

| Item | Value |
| --- | --- |
| Public base | `https://vucaka.myweb.cs.uwindsor.ca/customcore/` |
| PHP (from monitoring) | 8.3.30 |
| MySQL | Host database `vucaka_customcore` (via live `config/database.php`) |
| Deploy layout | Project under public document subdirectory `/customcore/` |
| Method | Interactive browser session against the live URL |

### Test customer (created on host)

| Field | Value |
| --- | --- |
| Email | `live166.aug4@example.test` |
| Name | Live Tester |
| Role | customer |

Passwords are not recorded in documentation.

---

## 2. Results summary

**Core live customer path: Pass.** Registration through simulated checkout produced order `CC-20260804-0974C1`. Wishlist, consultation, profile, media, locations, and builder first step also passed. Full multi-category builder save and cart-of-saved-build were not re-executed on production during this run (covered thoroughly in local customer workflow tests).

| Group | Result |
| --- | --- |
| Homepage + catalogue + product | Pass |
| Register → login → session | Pass |
| Cart (add product ×2, update qty) | Pass |
| Checkout → order confirmation → history/detail | Pass |
| Wishlist add / list | Pass |
| Review submit (pending moderation) | Pass (seen as pending on admin dashboard) |
| Consultation submit + history | Pass (request #1) |
| Profile details update | Pass |
| Logout | Pass |
| Builder loads steps | Pass (step 1 CPU) |
| Saved complete build → cart | Partial (not completed live) |
| Media + store map pages | Pass |

---

## 3. Step evidence

| ID | Action | Expect | Result |
| --- | --- | --- | --- |
| C1 | Open homepage | Styled, featured products, 4 tiers | **Pass** — 8 featured systems + tiers |
| C2 | Catalogue | 20 systems, filters | **Pass** |
| C3 | Product `product.php?id=2` (CoreStart Plus) | Options, stock | **Pass** |
| C4 | Register new account | Redirect to login | **Pass** |
| C5 | Log in as test customer | Profile “Hi, Live” | **Pass** |
| C6 | Add product to cart | Cart shows CoreStart Plus | **Pass** |
| C7 | Increase qty to 2, update | Cart badge / total $1,898 | **Pass** |
| C8 | Checkout shipping + pay on pickup | Order confirmation | **Pass** — `order-confirmation.php?id=1`, number **CC-20260804-0974C1** |
| C9 | Order history / detail | Same order number, shipping | **Pass** |
| C10 | Wishlist add product 2 | “1 item saved” | **Pass** |
| C11 | Submit product review | Pending moderation | **Pass** — admin later showed 1 pending |
| C12 | Consultation (budget `$1,000, $1,500`+) | Success flash | **Pass** — request **#1** |
| C13 | Edit profile (phone/city) | “Profile details were updated” | **Pass** |
| C14 | Profile summary | Order, wishlist, consultation counts | **Pass** — 1 order, 1 wishlist, 1 consultation |
| C15 | Builder `/builder.php` | Step 1 loads | **Pass** |
| C16 | Media + locations | HTTP 200, titles correct | **Pass** |
| C17 | Logout | Guest login form | **Pass** |

---

## 4. Data left on host after

For grader transparency (ignore as demo traffic):

- Customer `live166.aug4@example.test`
- Order `CC-20260804-0974C1` (later set to **processing** under)
- Consultation #1
- Wishlist entry for CoreStart Plus
- One pending/approved review (moderation completed in)

No production defects were found that blocked the customer order path.

---

## 5. Sign-off

| Criterion | Result |
| --- | --- |
| Public homepage + catalogue work live | **Yes** |
| Register / login / cart / checkout / history | **Yes** |
| Order number issued live | **Yes — CC-20260804-0974C1** |
| Consultation + wishlist + profile | **Yes** |
| Avoidable live defects found | **None** |
| Recorded | **This document** |
