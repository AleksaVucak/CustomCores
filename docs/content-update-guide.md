# CustomCore — Content Update Guide (for Non-Programmers)

**Document type:** Stage 12 documentation (Commit 12.3)
**Purpose:** Show a non-programmer how to keep CustomCore's content current — products, product images, the store location, site name, and the Learning Centre videos/audio — **without editing application logic**. This is the guide referenced by rubric item **#10f**.
**Audience:** Store staff and content editors. You need a CustomCore administrator account (see [`docs/administrator-guide.md`](administrator-guide.md) §2) and, for media only, the ability to copy a file into a folder on the server.

> **Golden rule:** most day-to-day content (products, prices, stock, images) is changed entirely through the **admin website** — no files, no code. Only adding a brand-new **video or audio lesson** requires copying a file and pasting one small block into a clearly marked list.

---

## 1. Before you start

| You need | How to get it |
| -------- | ------------- |
| An admin login | Ask an existing administrator to create one, or run `php database/create-admin.php` (one-time). |
| A web browser | Any modern browser. |
| (Media only) File access | The ability to copy a file into the `assets/media/` folder on the server (via your host's file manager, SFTP, or Git). |

Sign in, then click **Admin** in the top menu to open the dashboard.

---

## 2. Products — add, edit, price, stock, hide (no code)

Everything about the catalogue is managed at **Admin → Products** (`admin/products.php`).

### 2.1 Add a new product

1. Go to **Admin → Products** and click **Add product**.
2. Fill in:
   - **Name** — the product's display name. A web-friendly link (slug) is created for you automatically.
   - **Category / tier** — Budget, Esports, High-Performance, or Creator.
   - **Base price** — the starting price before options.
   - **Stock quantity** — how many are available.
   - **Short description** and **full description**.
   - **Featured** — tick to highlight it on the homepage.
   - **Active** — leave ticked so it appears on the site.
3. (Optional) **Upload an image** — see §3.
4. Click **Save**. You'll return to the product list with a confirmation.

> Every product should have **at least two options** (for example RAM and Storage choices). Add these in §4 right after creating the product.

### 2.2 Edit an existing product

1. **Admin → Products**, use the search box if needed, then click **Edit** on the row.
2. Change any field (price, stock, descriptions, image, category, featured) and click **Save**.

### 2.3 Change price or stock quickly

Prices and stock are just fields on the Edit screen (§2.2). Update the number and save. The dashboard will flag any active product that drops to **0** (out of stock) or **1–5** units (low stock).

### 2.4 Hide a product (don't delete it)

To stop selling a product, **disable** it instead of deleting:

- Use the **enable/disable** toggle on the product list, **or** untick **Active** on the Edit screen.

Disabling removes the product from the catalogue, search, and PC Builder while **keeping its past orders and reviews intact**. This is why there is no "delete product" button — deleting would erase history.

---

## 3. Product images — upload and replace (no code)

Product images are uploaded through the same product **Add/Edit** form:

1. On the product's Edit screen, use the **image** field to choose a picture from your computer.
2. Save. The new image appears across the catalogue, product page, search results, wishlist, and homepage automatically.

Rules the system enforces for you (to keep the site safe and fast):

- **Allowed types:** JPG, PNG, WEBP, or GIF only.
- **Maximum size:** 2 MB per image.
- Uploaded files are stored under `uploads/products/` with a safe, auto-generated filename.
- **Replacing or removing** an image deletes the old file automatically — no cleanup needed.

If an image is missing or invalid, the site shows a tidy placeholder instead of a broken picture, so you never end up with a broken layout.

**Tip:** use clear, well-lit photos around 1200px wide and compress them (many free tools export "web quality" JPGs) so they stay under 2 MB.

---

## 4. Product options — the choices buyers pick (no code)

Options are the configurable choices on a product (RAM, Storage, Colour, Warranty, …). Manage them at **Admin → Product options** (`admin/product-options.php`), or click **Options** on any product row.

For each option group you can:

- **Add** an option and set its **price adjustment** (which can be **+** to add cost or **−** to reduce it).
- **Reorder** options, **enable/disable** them, and pick the **default** choice.
- **Delete** an option.

The system guarantees each group always has exactly **one active default**, so buyers always see a valid, priced configuration. A warning appears if a product falls below two active options — add more so the product stays fully configurable.

---

## 5. Reviews and consultations — moderate customer content (no code)

- **Reviews:** new customer reviews are hidden until you approve them. Go to **Admin → Reviews** and click **Approve** (or Hide / Delete). Only approved reviews show publicly.
- **Consultations:** answer PC-advice requests at **Admin → Consultations**. Writing a response marks the request **Answered** and shows it to the customer.

Full steps are in [`docs/administrator-guide.md`](administrator-guide.md) §9–§10.

---

## 6. Store location and contact details (one small config edit)

The address, phone, email, opening hours, and map pin come from a single settings file: `config/app.php`, under `store_location`. You do **not** touch any page logic — you only change the values in quotes.

Find this block and edit the text between the quotes:

```php
'store_location' => [
    'name' => 'CustomCore Campus Service Desk',
    'street' => '1000 Innovation Drive',
    'city' => 'Windsor',
    'region' => 'Ontario',
    'postal_code' => 'N9C 4E6',
    'country' => 'Canada',
    'phone_display' => '519-555-0148',
    'phone_href' => '+15195550148',   // digits only, with country code
    'email' => 'support@customcore.example',
    'latitude' => 42.3049,            // map pin (decimal degrees)
    'longitude' => -83.0662,
    'map_zoom' => 14,
    'hours' => [
        'Monday–Friday' => '10:00 a.m.–7:00 p.m.',
        'Saturday' => '11:00 a.m.–5:00 p.m.',
        'Sunday' => 'Closed',
    ],
],
```

Save the file. The Locations page and its map update automatically. (To move the map pin, look up your address's latitude/longitude on any maps site and paste the two numbers.)

The same file also holds the **site name** and **tagline** near the top (`'name'` and `'tagline'`) if branding text ever changes.

---

## 7. Learning Centre videos and audio (add a file + one small block)

The Learning Centre (`media.php`) plays short video/audio lessons. This is the **only** content type that isn't fully managed from the admin website, because media files must be copied onto the server. It's still a copy-and-paste job — you do not change any logic.

### 7.1 Step 1 — copy the files into place

Put your files in these folders (create matching names):

| File | Folder | Allowed types |
| ---- | ------ | ------------- |
| The video or audio | `assets/media/` | `.mp4`, `.webm`, `.ogg` (video); `.mp3`, `.wav`, `.m4a` (audio) |
| A poster/thumbnail image | `assets/images/media/` | `.jpg`, `.png`, `.webp` |
| A captions file (required for video, recommended for audio) | `assets/media/captions/` | `.vtt` |

### 7.2 Step 2 — add one entry to the lesson list

Open `includes/media.php` and find the `$catalogue = [ ... ];` list near the top. Copy an existing entry and paste it as a new block, then change the values. Use this template:

```php
[
    'id' => 'my-new-lesson',                 // unique, lowercase, dashes
    'type' => 'video',                        // 'video' or 'audio'
    'title' => 'My New Lesson Title',
    'description' => 'One or two sentences describing the lesson.',
    'duration_label' => 'About 2 minutes',
    'mime' => 'video/mp4',                     // match the file type
    'src' => 'assets/media/my-new-lesson.mp4',
    'poster' => 'assets/images/media/my-new-lesson-poster.jpg',
    'poster_alt' => 'Short description of the poster image.',
    'captions' => 'assets/media/captions/my-new-lesson.vtt',
    'learn' => [
        'First thing viewers will learn',
        'Second thing viewers will learn',
    ],
    'transcript' => [
        'First paragraph of the spoken words.',
        'Second paragraph of the spoken words.',
    ],
],
```

Save the file. The lesson appears on the Learning Centre automatically.

**Why the transcript matters:** the `transcript` and `captions` are the accessible text version of the media (required so no information is locked inside audio/video). Always fill them in — the accessibility statement page relies on them.

**Safety net:** if the media file named in `src` is missing on disk, CustomCore simply skips that lesson rather than showing a broken player, so a typo can never break the page.

### 7.3 Step 3 — record the credit

Add the source and licence for the new file to [`docs/media-credits.md`](media-credits.md) so licensing stays documented (rubric #10d/#10e).

---

## 8. What NOT to edit

To stay safe, avoid these unless you are a developer:

- Anything in `includes/` **except** the clearly-marked media list in §7.2.
- Any `.php` page in the project root or `admin/` (page logic).
- `config/database.php` (database credentials).
- The database directly — use the admin website instead.

If in doubt, make the change through the **admin website** first; it validates your input and protects the data.

---

## 9. Quick reference

| I want to… | Where | Code needed? |
| ---------- | ----- | ------------ |
| Add / edit a product | Admin → Products | No |
| Change price or stock | Admin → Products → Edit | No |
| Hide a product | Admin → Products (disable toggle) | No |
| Upload / replace a product image | Admin → Products → Edit (image field) | No |
| Manage product options | Admin → Product options | No |
| Approve a review | Admin → Reviews | No |
| Answer a consultation | Admin → Consultations | No |
| Switch the site theme | Admin → Themes | No |
| Change address / hours / map | `config/app.php` → `store_location` | Tiny (values only) |
| Change site name / tagline | `config/app.php` → `name` / `tagline` | Tiny (values only) |
| Add a video / audio lesson | copy files + `includes/media.php` list | Small (paste a block) |

---

## 10. Status

**Commit 12.3 complete.** A non-programmer can add and update products, prices, stock, images, and options entirely through the admin website; moderate reviews and consultations; change store/contact details and branding via labelled values in `config/app.php`; and add a new Learning Centre video or audio lesson by copying files and pasting one clearly-marked block. Satisfies rubric item **#10f**.
