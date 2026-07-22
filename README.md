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

**Commit 4.8 complete** — strengthened session security.

`customcore_session_start()` now sets strict, cookie-only, HTTP-only,
SameSite=Lax sessions (Secure under HTTPS) and calls `customcore_session_harden()`
on every request. Authenticated sessions get: user-agent binding, a 30-minute
idle timeout, a 12-hour absolute lifetime, and automatic session-ID rotation
every 15 minutes. Expired sessions are cleared and the user sees a warning.
Timeouts are configurable in `config/app.php`.

Stage 4 (registration, authentication, and profiles) is complete.

## Security notes

- Never commit real database credentials.
- The live config file `config/database.php` is ignored by Git.
- Use [`config/database.example.php`](config/database.example.php) as the template
  (see [`config/README.md`](config/README.md)).
- Never commit plain-text passwords or private customer data.

## Licence

See [LICENSE](LICENSE) for terms.
