# CustomCore

CustomCore is a database-driven custom gaming PC store and PC-building website.
Customers can browse configurable prebuilt systems, use a guided custom builder
with compatibility feedback, manage accounts and saved builds, and complete a
simulated checkout. Administrators manage catalogue data, orders, reviews,
consultations, themes, reports, and site monitoring.

This repository is a university web-development project intended for deployment
on standard shared PHP/MySQL hosting (for example `myweb.cs.uwindsor.ca`).

## Technology stack

- HTML5
- External CSS (including three switchable site themes)
- Vanilla JavaScript
- PHP with sessions
- MySQL via PDO prepared statements
- Git / GitHub

No React, Vue, Angular, Node.js, Laravel, Docker, Composer, or URL rewriting is
required. The application uses ordinary `.php` URLs for hosting compatibility.

## Documentation

- [Business case and project objectives](docs/business-case.md)
- [Rubric compliance checklist](docs/rubric-checklist.md)
- [Application sitemap](docs/sitemap.md)
- [Desktop and mobile wireframes](docs/wireframes.md)
- [Database entity-relationship design](docs/database-design.md)
- [Database import, verification, and backup](docs/database-import.md)
- [Application directory structure](docs/directory-structure.md)
- [Flash message usage](docs/flash-messages.md)

## Current status

**Commit 8.6 complete** — accessible multimedia fallbacks.

Every piece of Stage 8 multimedia now has a documented text equivalent, so no
information is locked inside media. A new public `accessibility.php` statement
(linked from the footer) explains each fallback and links to it: descriptive
`alt` text with graceful placeholders for images; native controls + English
captions + expandable transcripts + download links for the video/audio guides;
the live-data table beside the catalogue chart (8.5); the text score summary
beside the builder performance chart (5.8); and the always-visible address,
hours, and `<noscript>` message for the store map (8.4). The homepage video
teaser now links straight to the guide's transcript/captions, and the stylesheet
honours `prefers-reduced-motion`. Verified in a browser: the statement page lists
all six multimedia types with working links and the teaser transcript link
resolves to the correct Learning Centre lesson.

**Commit 8.5 complete** — public catalogue data visualization from live MySQL.

`catalogue.php` opens with a "Catalogue at a glance" section that charts the
number of active products in each performance tier. Counts and price ranges are
computed server-side from the database by `customcore_catalogue_tier_stats()`
(in `includes/catalogue-stats.php`) — never hard-coded — so the graph always
reflects real seeded/administered data. The payload is JSON-encoded into a
`data-catalogue-chart` attribute and drawn by `assets/js/catalogue-chart.js`
using Chart.js (loaded from CDN only on this page via `$loadCatalogueChart`). An
accessible data table listing each tier's count and price range is rendered
server-side beside the canvas and remains the source of truth if Chart.js fails
to load. A separate `try/catch` keeps a stats failure from blanking the product
grid. Verified in a real browser: four bars (Budget/Esports/High-Performance/
Creator, 5 each) render and the table mirrors the same figures.

**Commit 8.4 complete** — interactive store & service map with text fallback.

`store-locations.php` shows the fictional CustomCore Campus Service Desk. An
always-visible `<address>` block (name, street, city/region/postal, `tel:` and
`mailto:` links), an hours list, and a storefront photo remain fully usable even
if JavaScript, Leaflet, or the map tiles fail. The interactive map is a
progressive enhancement: `assets/js/store-map.js` initialises Leaflet +
OpenStreetMap from `data-*` attributes on the map container (no inline script,
popup built from DOM text nodes), with scroll-wheel zoom enabled only while the
map is focused so keyboard users are never trapped. Location data is centralised
in `config/app.php` (`store_location`) for easy, non-programmer edits. Leaflet's
CSS/JS load only on this page via the shared header/footer. Verified in a real
browser: map tiles + marker render and the text address stays visible.

**Commit 8.3 complete** — multimedia Learning Centre showcase.

`media.php` is now an organized, responsive Learning Centre. A lesson directory
at the top summarises the mix ("3 short lessons — 2 videos and 1 audio guide")
and offers poster-thumbnail cards with type/duration badges that jump to each
full player below. Each lesson plays with native `<video controls>` /
`<audio controls>`, English caption tracks, learning outcomes, and an expandable
transcript. Accessibility refinements: the standalone audio poster now carries a
descriptive `alt`, video posters are treated as decorative, jumped-to lessons get
a visible `:target` highlight and focus handling, and heading levels nest
correctly (h1 → directory/lesson h2/h3 → outcomes h4).

**Commit 8.2 complete** — three educational media items play with native controls
(2× MP4, 1× MP3 under `assets/media/` with WebVTT captions; `customcore_media_url()`
helper; homepage teaser embeds the PC Builder walkthrough).

**Commit 8.1 complete** — copyright-safe imagery integrated site-wide.

Next: **Stage 8.7** — multimedia credits (`docs/media-credits.md`).

## Security notes

- Never commit real database credentials.
- The live config file `config/database.php` is ignored by Git.
- Use [`config/database.example.php`](config/database.example.php) as the template
  (see [`config/README.md`](config/README.md)).
- Never commit plain-text passwords or private customer data.

## Licence

See [LICENSE](LICENSE) for terms.
