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

**Commit 8.1 complete** — copyright-safe imagery integrated site-wide.

Thirty-three studio-style, logo-free images live under `assets/images/`
(`products/`, `hero/`, `categories/`, `ui/`, `media/`, `og/`, `map/`). Product
photos render on the homepage, catalogue, search, product detail, and wishlist
cards; the homepage hero and PC Builder header use background art; performance
tiers show category banners; and empty cart/wishlist states use friendly
illustrations. A new helper, `customcore_image_url()`, resolves a path only when
the file physically exists under `assets/images/` (and passes a strict
traversal/extension check), so any missing asset gracefully falls back to the
original gradient placeholder instead of a broken image. Every content image has
descriptive `alt` text, `loading`/`decoding` hints, and explicit dimensions to
avoid layout shift. A social-share (`og:image`) is now emitted from the shared
header.

**Stage 7 complete** — Wishlist · Reviews · Consultations (+ attachments) · Contact · History.

Next: **Stage 8.2** — video/audio learning-centre media.

## Security notes

- Never commit real database credentials.
- The live config file `config/database.php` is ignored by Git.
- Use [`config/database.example.php`](config/database.example.php) as the template
  (see [`config/README.md`](config/README.md)).
- Never commit plain-text passwords or private customer data.

## Licence

See [LICENSE](LICENSE) for terms.
