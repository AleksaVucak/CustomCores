# CustomCore | Production Administrator Workflow Verification (16.7)

**Document type:** Stage 16 production test record  
**Host:** [https://vucaka.myweb.cs.uwindsor.ca/customcore/](https://vucaka.myweb.cs.uwindsor.ca/customcore/)  
**Date:** 4 August 2026  
**Purpose:** Prove administrator tools work on the live myweb deploy against real host data created in 16.6.  
**Related:** local record [`admin-workflows.md`](admin-workflows.md); production customer record [`production-customer-workflows.md`](production-customer-workflows.md).

### Status legend

| Status | Meaning |
| ------ | ------- |
| **Pass** | Live admin action completed correctly |
| **Fail** | Unexpected error or broken path |

---

## 1. Environment

| Item | Value |
| ---- | ----- |
| Public base | `https://vucaka.myweb.cs.uwindsor.ca/customcore/` |
| Admin account | `admin@customcore.local` (role **admin**, password not stored in docs) |
| Host PHP | 8.3.30 (Service Monitoring) |
| Method | Interactive browser against the live URL after customer data existed |

---

## 2. Results summary

**Core live administrator path: Pass.** Dashboard reflected new host activity; order status and notes, consultation response, review moderation, and all major admin pages loaded. Monitoring reported **7 online / 0 warning / 0 offline**, MySQL connected, uploads writable.

| Group | Result |
| ----- | ------ |
| Admin login + Admin nav | Pass |
| Dashboard KPIs against live DB | Pass |
| Order status + notes | Pass |
| Consultation respond → Answered | Pass |
| Review approve from pending queue | Pass |
| Products / options / compatibility / users lists | Pass (HTTP 200) |
| Reports (Chart.js present) | Pass |
| Themes page load + activate POST | Pass |
| Monitoring overall online | Pass |

---

## 3. Step evidence

| ID | Action | Expect | Result |
| -- | ------ | ------ | ------ |
| A1 | Log in as admin | Welcome, role admin | **Pass** — “Welcome back, Aleksa”, Role: admin |
| A2 | Open `/admin/` | Counts from MySQL | **Pass** — 20 products, 1 order in progress, 5 users (4 customers + 1 admin), 1 pending review, 1 open consultation |
| A3 | Order `CC-20260804-0974C1` detail | Customer + line options | **Pass** |
| A4 | Set status **Processing** | Flash success | **Pass** — “Order status updated to Processing” |
| A5 | Save admin notes | Flash success | **Pass** — “Administrator notes saved” |
| A6 | Consultation #1 respond | Status becomes Answered | **Pass** — badge **Answered**, responded 4 Aug 2026 4:16 PM |
| A7 | Reviews · approve pending (#9) | Moderated | **Pass** (POST approve succeeded) |
| A8 | Open products list | Manage Products loads | **Pass** |
| A9 | Product options | Page loads | **Pass** |
| A10 | Compatibility | Page loads | **Pass** |
| A11 | Users list | Includes Live Tester / seed users | **Pass** |
| A12 | Reports | Charts present in source | **Pass** |
| A13 | Themes | Activate alternate theme POST | **Pass**; monitoring still shows **rgb-gaming.css** as active stylesheet after checks (default theme healthy) |
| A14 | Monitoring | All checks Online | **Pass** — 7 online; DB connected; uploads writable; media 3/3; theme valid |

---

## 4. Monitoring snapshot (live)

| Check area | Observation |
| ---------- | ----------- |
| Overall | All monitored services are online |
| PHP | 8.3.30; PDO MySQL, fileinfo, session loaded |
| Database | Connected and responding |
| Uploads | Product + consultation directories writable |
| Themes | 3 stylesheets; active `rgb-gaming.css` |
| Media | 3/3 Learning Centre lessons on disk |
| Stats | 20 products, 5 users, 1 order in progress, 1 consultation |

---

## 5. Host data after 16.6 + 16.7 (ignore as demo)

| Artefact | Notes |
| -------- | ----- |
| Customer `live166.aug4@example.test` | Created during 16.6 |
| Order `CC-20260804-0974C1` | Status **processing**; admin notes set |
| Consultation #1 | **Answered** with admin response |
| Review queue | Live pending review moderated (approved) |
| Users total | 5 (3 seed customers + test customer + admin) |

Optional cleanup: disable the test customer or leave as demo — not required for pass.

---

## 6. Sign-off

| Criterion | Result |
| --------- | ------ |
| Admin can log in and open dashboard on host | **Yes** |
| Order management works live | **Yes** |
| Consultation + review tools work live | **Yes** |
| Reports + monitoring work live | **Yes** (monitoring fully online) |
| Avoidable live admin defects found | **None** |
| Recorded | **This document (16.7)** |
