# CustomCore | Final Application Screenshots (17.1)

**Document type:** Stage 17.1 submission gallery  
**Host captured:** [https://vucaka.myweb.cs.uwindsor.ca/customcore/](https://vucaka.myweb.cs.uwindsor.ca/customcore/)  
**Capture date:** 4 August 2026  
**Purpose:** Provide visual proof of the live, final storefront and administrator experience for the completed CustomCore release.  
**Related:** [production customer workflows (16.6)](production-customer-workflows.md), [production administrator workflows (16.7)](production-admin-workflows.md), project [README](../README.md).

---

## 1. Capture method

| Item | Detail |
| ---- | ------ |
| Source | Live myweb deploy (not localhost) |
| Theme active | RGB Gaming (`rgb-gaming.css`) |
| Desktop view | Emulated layout width **1440×900**, full-page PNG |
| Mobile view | Emulated phone layout width **390×844**, full-page PNG |
| Session note | Captured while signed in as the host administrator so cart, profile, admin, themes, and monitoring pages are reachable in one pass. Public routes still show the same branded storefront CSS and content. |
| Credentials | Not stored in this document or beside the images |

All PNG files live under [`docs/screenshots/`](screenshots/).

---

## 2. Gallery index

Roadmap items covered: homepage, catalogue, builder, cart, profile, admin, theme, monitoring, and mobile.

| # | File | Route | What it shows |
| - | ---- | ----- | ------------- |
| 01 | [01-homepage-desktop.png](screenshots/01-homepage-desktop.png) | `/customcore/` | Brand, hero, CTAs, featured systems, tiers strip (desktop) |
| 02 | [02-catalogue-desktop.png](screenshots/02-catalogue-desktop.png) | `catalogue.php` | Live chart, filters, search, all 20 catalogue cards (desktop) |
| 03 | [03-builder-desktop.png](screenshots/03-builder-desktop.png) | `builder.php` | Step 1 CPU list, progress steps, build summary / live total (desktop) |
| 04 | [04-cart-desktop.png](screenshots/04-cart-desktop.png) | `cart.php` | Line item (CoreStart Plus), quantities, checkout link (desktop) |
| 05 | [05-profile-desktop.png](screenshots/05-profile-desktop.png) | `profile.php` | Account hub, profile details, account navigation (desktop) |
| 06 | [06-admin-dashboard.png](screenshots/06-admin-dashboard.png) | `admin/` | Dashboard counts, attention alerts, tool directory (desktop) |
| 07 | [07-admin-themes.png](screenshots/07-admin-themes.png) | `admin/themes.php` | Three themes with RGB Gaming active (desktop) |
| 08 | [08-admin-monitoring.png](screenshots/08-admin-monitoring.png) | `admin/monitoring.php` | All seven checks online, live stats (desktop) |
| 09 | [09-homepage-mobile.png](screenshots/09-homepage-mobile.png) | `/customcore/` | Mobile chrome with **Open menu** toggle and stacked content |
| 10 | [10-catalogue-mobile.png](screenshots/10-catalogue-mobile.png) | `catalogue.php` | Mobile catalogue filters and product cards |

---

## 3. Preview (desktop storefront and account)

### Homepage

![Homepage desktop](screenshots/01-homepage-desktop.png)

### Catalogue

![Catalogue desktop](screenshots/02-catalogue-desktop.png)

### PC Builder (CPU step with a part selected)

![PC Builder desktop](screenshots/03-builder-desktop.png)

### Shopping cart

![Shopping cart desktop](screenshots/04-cart-desktop.png)

### Profile / My account

![Profile desktop](screenshots/05-profile-desktop.png)

---

## 4. Preview (administrator)

### Dashboard

![Admin dashboard](screenshots/06-admin-dashboard.png)

### Themes

![Admin themes](screenshots/07-admin-themes.png)

### Service monitoring

![Admin monitoring](screenshots/08-admin-monitoring.png)

---

## 5. Preview (mobile)

### Homepage (mobile)

![Homepage mobile](screenshots/09-homepage-mobile.png)

### Catalogue (mobile)

![Catalogue mobile](screenshots/10-catalogue-mobile.png)

---

## 6. Stage 17.1 acceptance

| Criterion | Status |
| --------- | ------ |
| Homepage screenshot | Pass — `01-homepage-desktop.png` |
| Catalogue screenshot | Pass — `02-catalogue-desktop.png` |
| Builder screenshot | Pass — `03-builder-desktop.png` |
| Cart screenshot | Pass — `04-cart-desktop.png` |
| Profile screenshot | Pass — `05-profile-desktop.png` |
| Admin screenshot | Pass — `06-admin-dashboard.png` |
| Theme screenshot | Pass — `07-admin-themes.png` (active RGB Gaming + two alternate themes listed) |
| Monitoring screenshot | Pass — `08-admin-monitoring.png` (7 online) |
| Mobile screenshot(s) | Pass — `09-homepage-mobile.png`, `10-catalogue-mobile.png` |
| Match final live version | Pass — captured against the public myweb URL on 4 August 2026 |
| No secrets in assets or this doc | Pass |

Phase **17.1** is complete for the documentation package. Related cleanup and final audit steps remain Stage 17.2–17.5 if continued separately.
