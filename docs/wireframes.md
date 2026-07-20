# CustomCore — Desktop and Mobile Wireframes

**Document type:** Stage 0 planning (Commit 0.5)  
**Purpose:** Define layout and navigation for the six core screens before CSS/PHP implementation.  
**Acceptance:** Core navigation is visible in both desktop and mobile layouts.  
**Related:** Page list in `docs/sitemap.md`; responsive menu rubric item #9.

These are structural wireframes (not final visual design). Theme colours, fonts, and decorative effects come in Stages 1 and 10.

### Breakpoints (planned)

| Name | Width guide | Navigation pattern |
| ---- | ----------- | ------------------ |
| Desktop | ≥ 900px | Horizontal main nav in header |
| Mobile | < 900px | Header brand + menu toggle; nav links in expandable panel |

### Shared chrome (every customer screen)

| Region | Desktop | Mobile |
| ------ | ------- | ------ |
| Skip link | “Skip to content” (first focusable) | Same |
| Header | Logo **CustomCore** + horizontal nav + account/cart | Logo + ☰ menu + cart icon |
| Main nav links | Home, About, Catalogue, PC Builder, Media, Locations, Help, Contact | Same links inside open menu panel |
| Account cluster | Register/Login or Profile / Orders / Log out | Same items in menu or compact account row |
| Footer | Help links, privacy, accessibility, context Help | Stacked footer links |
| Context Help | Page-specific Help link near page title or form | Same, full-width under title |

---

## 1. Homepage — `index.php`

### 1A. Desktop

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ [Skip to content]                                                        │
├──────────────────────────────────────────────────────────────────────────┤
│ CUSTOMCORE    Home About Catalogue Builder Media Locations Help Contact │
│                                               [Account ▾]  [Cart (n)]   │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│   HERO (full-bleed visual plane)                                         │
│   Brand: CustomCore                                                      │
│   One headline                                                           │
│   One supporting sentence                                                │
│   [Shop prebuilts]  [Start PC Builder]                                   │
│                                                                          │
├──────────────────────────────────────────────────────────────────────────┤
│   Featured systems (from MySQL)     │  Category tier cards               │
│   [PC] [PC] [PC] [PC]               │  Budget | Esports | High | Creator │
├──────────────────────────────────────────────────────────────────────────┤
│   Short learning-centre teaser + [Watch guides]                          │
├──────────────────────────────────────────────────────────────────────────┤
│   Footer: Help | Privacy | Accessibility | Contact                       │
└──────────────────────────────────────────────────────────────────────────┘
```

**Nav visible:** Yes — full horizontal menu in header.

### 1B. Mobile

```text
┌────────────────────────────┐
│ CUSTOMCORE      ☰   Cart   │
├────────────────────────────┤
│ ▌ MENU (when open)         │
│ │ Home                     │
│ │ About                    │
│ │ Catalogue                │
│ │ PC Builder               │
│ │ Media                    │
│ │ Locations                │
│ │ Help                     │
│ │ Contact                  │
│ │ Account / Login          │
│ └──────────────────────────┤
│ HERO                       │
│ CustomCore                 │
│ Headline                   │
│ Support line               │
│ [Shop] [Builder]           │
├────────────────────────────┤
│ Featured (stacked cards)   │
├────────────────────────────┤
│ Categories (stacked)       │
├────────────────────────────┤
│ Footer (stacked links)     │
└────────────────────────────┘
```

**Nav visible:** Yes — via ☰ toggle panel containing the same core links.

---

## 2. Catalogue — `catalogue.php`

### 2A. Desktop

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ CUSTOMCORE    Home About Catalogue Builder Media Locations Help Contact │
│                                               [Account ▾]  [Cart (n)]   │
├──────────────────────────────────────────────────────────────────────────┤
│ Catalogue                          Help: Catalogue guide →               │
├───────────────┬──────────────────────────────────────────────────────────┤
│ FILTERS       │  Sort: [Featured ▾]     Results: N systems               │
│ Category      │  ┌────┐ ┌────┐ ┌────┐ ┌────┐                             │
│ Price range   │  │ PC │ │ PC │ │ PC │ │ PC │                             │
│ Brand         │  │$   │ │$   │ │$   │ │$   │                             │
│ In stock      │  └────┘ └────┘ └────┘ └────┘                             │
│ [Apply]       │  ┌────┐ ┌────┐ …                                         │
│ [Compare (k)] │                                                          │
└───────────────┴──────────────────────────────────────────────────────────┘
│ Footer …                                                                 │
└──────────────────────────────────────────────────────────────────────────┘
```

**Nav visible:** Yes — shared header menu.  
**Notes:** Filters left; product grid right. Compare uses selected checkboxes.

### 2B. Mobile

```text
┌────────────────────────────┐
│ CUSTOMCORE      ☰   Cart   │
├────────────────────────────┤
│ Catalogue                  │
│ Help: Catalogue guide →    │
│ [Filters ▾]  Sort [▾]      │
├────────────────────────────┤
│ ┌────────────────────────┐ │
│ │ Product card           │ │
│ └────────────────────────┘ │
│ ┌────────────────────────┐ │
│ │ Product card           │ │
│ └────────────────────────┘ │
│ …                          │
│ [Compare selected]         │
├────────────────────────────┤
│ Footer …                   │
└────────────────────────────┘
```

**Nav visible:** Yes — ☰ menu. Filters collapse into a disclosure panel above the grid.

---

## 3. PC Builder — `builder.php`

### 3A. Desktop

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ CUSTOMCORE    Home About Catalogue Builder Media Locations Help Contact │
│                                               [Account ▾]  [Cart (n)]   │
├──────────────────────────────────────────────────────────────────────────┤
│ Custom PC Builder              Help: PC Builder guide →                  │
│ Steps: [1 CPU] [2 Board] [3 GPU] [4 RAM] [5 Storage] [6 PSU] …          │
├─────────────────────────────────┬────────────────────────────────────────┤
│ COMPONENT PICKER                │ LIVE SUMMARY                           │
│ Category: Graphics cards        │ Subtotal: $….                          │
│ ( ) Option A   $…               │ Compatibility: Compatible/Warn/Fail    │
│ (•) Option B   $…               │ Notes: …                               │
│ ( ) Option C   $…               │ Performance preview (chart placeholder)│
│                                 │ [Back] [Next] [Review build]           │
└─────────────────────────────────┴────────────────────────────────────────┘
│ Footer …                                                                 │
└──────────────────────────────────────────────────────────────────────────┘
```

**Nav visible:** Yes — shared header.  
**Notes:** Two-column builder: selections left, live price/compatibility right (feeds dynamic-form rubric).

### 3B. Mobile

```text
┌────────────────────────────┐
│ CUSTOMCORE      ☰   Cart   │
├────────────────────────────┤
│ PC Builder                 │
│ Help: Builder guide →      │
│ Step 3 of 10 — GPU         │
├────────────────────────────┤
│ Live total: $….            │
│ Status: Warning — tap why  │
├────────────────────────────┤
│ ( ) GPU A                  │
│ (•) GPU B                  │
│ ( ) GPU C                  │
├────────────────────────────┤
│ [Back]        [Next]       │
│ [Review build]             │
├────────────────────────────┤
│ Footer …                   │
└────────────────────────────┘
```

**Nav visible:** Yes — ☰ menu. Summary stacks above the picker so totals stay visible while scrolling options.

---

## 4. Profile — `profile.php`

### 4A. Desktop

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ CUSTOMCORE    Home About Catalogue Builder Media Locations Help Contact │
│                                               [Account ▾]  [Cart (n)]   │
├──────────────────────────────────────────────────────────────────────────┤
│ My account                         Help: Accounts guide →                │
├──────────────────┬───────────────────────────────────────────────────────┤
│ ACCOUNT NAV      │  Hello, {name}                                        │
│ Profile (active) │  Email | member since                                 │
│ Edit profile     │  ┌─────────────┐ ┌─────────────┐ ┌─────────────────┐ │
│ Saved builds     │  │ Orders (n)  │ │ Builds (n)  │ │ Consultations   │ │
│ Wishlist         │  └─────────────┘ └─────────────┘ └─────────────────┘ │
│ Orders           │  Recent activity list                                 │
│ Consultations    │  [Edit profile] [Browse catalogue] [PC Builder]       │
│ Log out          │                                                       │
└──────────────────┴───────────────────────────────────────────────────────┘
│ Footer …                                                                 │
└──────────────────────────────────────────────────────────────────────────┘
```

**Nav visible:** Yes — site header **and** account side nav.  
**Notes:** Private area; guests redirect to login (Stage 4).

### 4B. Mobile

```text
┌────────────────────────────┐
│ CUSTOMCORE      ☰   Cart   │
├────────────────────────────┤
│ My account                 │
│ Help: Accounts guide →     │
│ Account menu [▾]           │
│  Profile | Edit | Builds…  │
├────────────────────────────┤
│ Hello, {name}              │
│ Summary tiles (stacked)    │
│ Recent activity            │
│ Primary actions (stacked)  │
├────────────────────────────┤
│ Footer …                   │
└────────────────────────────┘
```

**Nav visible:** Yes — ☰ site menu; account links in a secondary disclosure under the title.

---

## 5. Cart — `cart.php`

### 5A. Desktop

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ CUSTOMCORE    Home About Catalogue Builder Media Locations Help Contact │
│                                               [Account ▾]  [Cart (n)]   │
├──────────────────────────────────────────────────────────────────────────┤
│ Shopping cart                      Help: Orders & checkout →             │
├──────────────────────────────────────────────────────────────────────────┤
│ Item                    Qty        Price        Line total               │
│ Prebuilt: Arena Pulse   [ 1 ▾]     $1,299       $1,299     [Remove]      │
│ Custom build: My Rig    [ 1 ▾]     $2,100       $2,100     [Remove]      │
├──────────────────────────────────────────────────────────────────────────┤
│                              Subtotal  $….                               │
│                              [Update cart]  [Clear cart]  [Checkout]     │
│                              [Continue shopping]                         │
├──────────────────────────────────────────────────────────────────────────┤
│ Footer …                                                                 │
└──────────────────────────────────────────────────────────────────────────┘
```

**Nav visible:** Yes — shared header (Cart control remains reachable).  
**Notes:** Supports both catalogue products and custom builds (Stage 6).

### 5B. Mobile

```text
┌────────────────────────────┐
│ CUSTOMCORE      ☰   Cart   │
├────────────────────────────┤
│ Shopping cart              │
│ Help: Orders guide →       │
├────────────────────────────┤
│ ┌ Item card ─────────────┐ │
│ │ Name                   │ │
│ │ Qty [▾]  $line         │ │
│ │ [Remove]               │ │
│ └────────────────────────┘ │
│ …                          │
├────────────────────────────┤
│ Subtotal $….               │
│ [Update] [Checkout]        │
│ [Continue shopping]        │
├────────────────────────────┤
│ Footer …                   │
└────────────────────────────┘
```

**Nav visible:** Yes — ☰ menu. Line items become stacked cards instead of a wide table.

---

## 6. Administrator dashboard — `admin/index.php`

### 6A. Desktop

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ CUSTOMCORE ADMIN     Dashboard Products Orders Users Themes Reports …   │
│                                              [View store] [Log out]      │
├──────────────────────────────────────────────────────────────────────────┤
│ Dashboard                                                                │
│ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐                              │
│ │Products│ │ Orders │ │ Users  │ │Open    │                              │
│ │  20+   │ │   n    │ │   n    │ │requests│                              │
│ └────────┘ └────────┘ └────────┘ └────────┘                              │
│ Alerts: low stock | pending reviews | consultations                      │
│ Recent orders table                                                      │
│ Monitoring snapshot: Online / Warning / Offline                          │
├──────────────────────────────────────────────────────────────────────────┤
│ Admin footer / docs link                                                 │
└──────────────────────────────────────────────────────────────────────────┘
```

**Nav visible:** Yes — admin horizontal nav (Products, Orders, Users, Consultations, Reviews, Themes, Reports, Monitoring, Compatibility).  
**Notes:** Separate from customer chrome; still shows clear primary navigation.

### 6B. Mobile

```text
┌────────────────────────────┐
│ CC ADMIN           ☰       │
├────────────────────────────┤
│ ▌ ADMIN MENU               │
│ │ Dashboard                │
│ │ Products                 │
│ │ Orders                   │
│ │ Users                    │
│ │ Consultations            │
│ │ Reviews                  │
│ │ Themes                   │
│ │ Reports                  │
│ │ Monitoring               │
│ │ Compatibility            │
│ │ View store / Log out     │
│ └──────────────────────────┤
│ Dashboard tiles (stacked)  │
│ Alerts list                │
│ Recent orders (cards)      │
│ Monitoring status          │
└────────────────────────────┘
```

**Nav visible:** Yes — ☰ admin menu with the same admin destinations.

---

## 7. Navigation coverage matrix

| Screen | Desktop main nav | Mobile main nav | Extra local nav |
| ------ | ---------------- | --------------- | --------------- |
| Homepage | Header links | ☰ panel | — |
| Catalogue | Header links | ☰ panel | Filter panel |
| PC Builder | Header links | ☰ panel | Step indicator |
| Profile | Header links | ☰ panel | Account side / disclosure nav |
| Cart | Header links | ☰ panel | — |
| Admin dashboard | Admin header links | Admin ☰ panel | — |

**Commit 0.5 acceptance:** For all six core screens, primary navigation is present in both desktop and mobile wireframes.

---

## 8. Implementation notes for later stages

1. Build one shared `includes/navigation.php` for customer pages and a separate admin nav include.  
2. Mobile toggle must be keyboard operable (Escape closes; focus returns to button) — Stage 1 / 14.  
3. Homepage hero stays one composition (brand, one headline, one sentence, CTA group, dominant visual) — avoid packing stats/schedules into the first viewport.  
4. Cart and builder summaries must remain readable without horizontal scrolling on small screens.  
5. Convert these wireframes into CSS layout in Stage 1 (`main.css`) and refine per theme in Stage 10.

---

## 9. Status

**Commit 0.5 complete (documentation).** Visual coding begins in Stage 1; these wireframes are the layout contract for the six priority screens.
