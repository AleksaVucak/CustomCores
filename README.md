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

**Commit 8.2 complete** — three educational media items play with native controls.

Two short MP4 videos and one MP3 audio lesson live under `assets/media/`, each
with a matching WebVTT caption file and poster image. A new helper,
`customcore_media_url()`, resolves media paths only when the file exists under
`assets/media/`. The Learning Centre page (`media.php`) lists all three lessons
with native `<video controls>` / `<audio controls>` players, captions tracks,
posters, learning outcomes, and expandable transcripts. The homepage Learning
Centre teaser now embeds the PC Builder walkthrough instead of a Stage 8
placeholder. Direct HTTP checks confirm the MP4, MP3, and VTT assets return 200
with the correct content types.

**Commit 8.1 complete** — copyright-safe imagery integrated site-wide.

Next: **Stage 8.3** — polish / confirm the multimedia Learning Centre presentation
(organization already started with `media.php` in 8.2 so the items can play).

## Security notes

- Never commit real database credentials.
- The live config file `config/database.php` is ignored by Git.
- Use [`config/database.example.php`](config/database.example.php) as the template
  (see [`config/README.md`](config/README.md)).
- Never commit plain-text passwords or private customer data.

## Licence

See [LICENSE](LICENSE) for terms.
