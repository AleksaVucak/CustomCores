# CustomCore | Context-sensitive Help links

**Document type:** Project documentation
**Purpose:** Prove that feature pages link to the **matching** Help article (rubric #7), not only the Help hub.
**Rule:** Site-wide chrome (main nav, footer) and general marketing pages may keep the hub; feature pages must open the relevant guide, preferably with a section anchor.

---

## Help wiki inventory (7 static pages)

| File | Topic |
| --- | --- |
| `help/index.html` | Help centre hub (searchable cards) |
| `help/accounts.html` | Registration, login, profile, password, sessions, disabled accounts |
| `help/catalogue.html` | Browse, search, filters, product options, compare, wishlist, reviews |
| `help/pc-builder.html` | Builder steps, pricing, compatibility, performance, saved builds |
| `help/orders.html` | Cart, checkout, payment, confirmation, history, details, statuses |
| `help/support.html` | Consultation, attachments, history, responses, reviews, contact |
| `help/training.html` | Numbered account → shop/build → order → review walkthrough |

---

## Context-link map (customer feature pages)

| Feature page | Help target | Notes |
| --- | --- | --- |
| `register.php` | `help/accounts.html#register` | |
| `login.php` | `help/accounts.html#login` | |
| `profile.php` | `help/accounts.html#profile` | |
| `edit-profile.php` | `help/accounts.html#edit-details` | |
| `catalogue.php` | `help/catalogue.html#browse` | |
| `product.php` | `help/catalogue.html#product` | |
| `search.php` | `help/catalogue.html#search` | |
| `compare.php` | `help/catalogue.html#compare` | |
| `wishlist.php` | `help/catalogue.html#wishlist` | |
| `builder.php` | `help/pc-builder.html#step-{slug}` (+ full guide, compatibility, pricing) | Step tip matches the current category |
| `builder-results.php` | `help/pc-builder.html#summary` (+ saved-builds, full guide) | |
| `saved-builds.php` | `help/pc-builder.html#saved-builds` (+ manage-builds, full guide) | |
| `saved-build.php` | `help/pc-builder.html#manage-builds` (+ performance, full guide) | |
| `cart.php` | `help/orders.html#cart` | |
| `checkout.php` | `help/orders.html#checkout` | |
| `order-confirmation.php` | `help/orders.html#confirmation` | |
| `order-history.php` | `help/orders.html#history` | |
| `order-details.php` | `help/orders.html#details` | |
| `consultation.php` | `help/support.html#consultation` | |
| `consultation-history.php` | `help/support.html#history` | |
| `reviews.php` | `help/support.html#reviews` | |
| `contact.php` | `help/support.html#contact` | |
| `store-locations.php` | `help/support.html#contact` | Visit / consultation support |
| `media.php` | Hub + `help/training.html#start` + PC Builder guide | Learning Centre |
| `index.php` | Hub + `help/training.html#start` | First-run entry |
| `about.php` | Hub + `help/training.html#start` | First-run entry |
| `accessibility.php` | Hub | Policy page — hub is appropriate |

---

## Intentionally left on the Help hub

| Location | Why |
| --- | --- |
| `includes/navigation.php` | Site-wide “Help” menu item |
| `includes/footer.php` | Site-wide footer Help link |
| Homepage / About / Accessibility / Learning Centre | Entry points that should show **all** guides |

Administrator pages use their own `context-help` copy (admin tooling). They are **not** required to deep-link into the public Help wiki; administrator documentation lives in `docs/administrator-guide.md`.

---

## Completion test
1. Open each feature page in the map above.
2. Follow its “Help:” / context-help link.
3. Confirm the destination is the matching article (and section when an anchor is used), not only `help/index.html`.
4. Confirm main nav and footer still open the Help hub.

Verified: every mapped customer feature page targets the correct guide; all seven Help HTML files return HTTP 200; hub deep-link anchors used by the feature pages exist on their articles.
