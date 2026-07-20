# CustomCore — Business Case and Project Objectives

**Project name:** CustomCore  
**Document type:** Stage 0 planning (Commit 0.2)  
**Purpose:** Define the business idea, audience, problem, solution, and objectives that guide the website build. A condensed version of this business case will also appear on the public About page in a later stage so the grading rubric’s business-description requirement is visible on the live site.

---

## 1. Business description

CustomCore is an online gaming PC store and custom computer-building platform for customers who want a reliable system without guessing about part compatibility. The catalogue focuses on at least twenty configurable prebuilt gaming and creator desktops, organized into clear performance tiers, while a guided custom PC builder lets experienced users choose processors, motherboards, graphics cards, memory, storage, power supplies, cases, cooling, operating systems, and assembly services. Live price totals, simplified compatibility feedback, performance estimates, product comparison, reviews, wishlists, saved builds, consultation requests, and a simulated checkout with order history turn the site into a complete commercial-style application rather than a set of disconnected assignment pages. Administrators manage products and options, customer accounts, orders, reviews, consultation responses, switchable site themes, reports, multimedia content, and a monitoring dashboard that reports whether major services are online, in a warning state, or offline. The finished application is designed for ordinary university PHP/MySQL hosting such as `myweb.cs.uwindsor.ca`, using HTML5, external CSS, vanilla JavaScript, PHP sessions, and MySQL with PDO prepared statements—without React, Node.js, Laravel, Docker, Composer, or URL rewriting.

---

## 2. Target customers

CustomCore serves people who are buying or configuring a gaming or creator PC and need clearer guidance than a raw parts list:

| Audience | Why CustomCore helps |
| -------- | -------------------- |
| Casual PC users | Plain-language product pages and prebuilt tiers reduce jargon |
| Competitive gamers | Esports and high-performance systems with upgrade options |
| University students | Budget and starter builds with transparent pricing |
| Streamers and content creators | Creator/workstation tier and consultation support |
| First-time gaming PC buyers | Guided builder, compatibility messages, and Help wiki |
| Experienced builders | Full component selection with server-validated compatibility |

---

## 3. Business problem

Choosing a gaming PC is difficult for inexperienced buyers. Component lists are technical, incompatible parts are easy to mix, prices change with every option, and many storefronts either hide upgrade choices or dump users into an advanced parts picker with little explanation. Customers also lose track of builds, past orders, and support conversations when those features are missing or scattered across tools.

**Core problem statement:** Selecting compatible PC components and understanding the cost and purpose of each choice is hard for inexperienced users, which leads to confusion, abandoned purchases, and poorly matched systems.

---

## 4. Solution

CustomCore reduces that difficulty by combining a curated, database-driven catalogue of configurable prebuilt systems with a guided custom builder and account-centred shopping tools:

1. **Clear product information** — Dynamic catalogue and detail pages loaded from MySQL, not hardcoded HTML copies.
2. **Configurable prebuilts** — At least twenty systems across four tiers, each with multiple options (for example RAM, storage, case colour, warranty, OS, cooling, and graphics upgrades where supported).
3. **Guided custom builder** — Step-by-step component selection with live price calculation and simplified compatibility checking (compatible, warning, or incompatible), validated on the server as well as in the browser.
4. **Performance estimates** — Visual summaries that help customers compare gaming and productivity expectations.
5. **Customer accounts** — Registration, authentication, profiles, saved builds, wishlists, cart, simulated checkout, order history, reviews, and consultation requests with safe file attachments.
6. **Human support path** — Consultation forms and administrator responses for customers who want advice before buying.
7. **Administrator control** — Product and option editing, user disable/re-enable, order and review moderation, theme switching, reports, and service monitoring.
8. **Learning and help** — Multimedia learning centre, interactive map, charts, and a multi-page Help wiki with context-sensitive links from feature pages.

Checkout is academic and simulated only: payment-method labels may be stored; credit-card numbers and other sensitive financial credentials are never requested or saved.

---

## 5. Product strategy (catalogue overview)

Primary catalogue: **at least 20 configurable prebuilt gaming/creator PCs** in four tiers:

1. Five budget and starter systems  
2. Five esports and mainstream systems  
3. Five high-performance gaming systems  
4. Five creator and workstation systems  

Every product will support **at least two options** (target: four or more option groups per prebuilt for rubric safety). A separate component inventory will power the custom builder. Product data, options, components, and simplified compatibility metadata will live in MySQL and be rendered dynamically in PHP.

---

## 6. Planned feature summary

### Public features
Homepage, About (business case), catalogue, product detail, search, filters, sorting, comparison, approved reviews, custom PC builder, live price calculator, compatibility feedback, performance visualization, store/service map, multimedia learning centre, Help centre, and contact form.

### Private customer features
Login/logout, profile view and edit, password change, saved builds (create/edit/delete), wishlist, cart (products and custom builds), simulated checkout, order history and details, review submission, consultation requests with attachments, and consultation history with administrator replies.

### Administrator features
Protected dashboard; product add/edit/disable, stock, price, and image management; product-option management; compatibility metadata editing; order status updates; user disable/re-enable; consultation responses; review moderation; theme selection (three distinct templates); reports/charts; and monitoring dashboard.

---

## 7. Project objectives

| Objective | Success measure |
| --------- | --------------- |
| Satisfy the course rubric | Every graded requirement has planned evidence (About page, 20+ products with options, 3 themes + switching, dynamic forms, documented PHP/MySQL, comments, Help wiki, responsive menu, dynamic pages, CSS/JS/images/media, update instructions, live URL, advanced CSS, SEO) |
| Remain hosting-compatible | Deployable on shared PHP/MySQL hosting with normal `.php` URLs and no heavy framework dependency |
| Feel like one application | Shared layout, navigation, themes, and account flow across public, private, and admin areas |
| Prefer clarity over complexity | Simplified compatibility rules that are reliable and explainable, not a full commercial parts engine |
| Keep the repository safe | No real credentials, plain-text passwords, or private customer data in Git |
| Deliver incrementally | One logical Git commit per roadmap step, with `main` as the working branch |

---

## 8. Technology constraints (confirmed)

- HTML5, external CSS, vanilla JavaScript, PHP, MySQL  
- PDO prepared statements and PHP sessions  
- Optional lightweight libraries only where helpful (for example Chart.js, Leaflet) with text fallbacks  
- No React, Vue, Angular, Node.js, Laravel, Docker, or required Composer/build pipeline  

---

## 9. Relationship to later documentation

| Later document | How this business case feeds it |
| -------------- | ------------------------------- |
| About page (`about.php`) | Public paragraph(s) derived from Section 1 |
| `docs/rubric-checklist.md` | Business-case row (#1) evidence points here and to About (checklist added in Commit 0.3) |
| `docs/sitemap.md` | Pages listed in Section 6 (sitemap added in Commit 0.4) |
| `docs/wireframes.md` | Layout/nav contract for core screens (added in Commit 0.5) |
| `docs/database-design.md` | Tables for catalogue, builder, accounts, and admin (ER design added in Commit 0.6) |
| README | High-level project summary remains consistent with this document |

---

## 10. Status

**Stage 0 planning complete** through Commit 0.6. Business idea, rubric map, sitemap, wireframes, and database ER design are finalized. Application coding begins at Stage 1.
