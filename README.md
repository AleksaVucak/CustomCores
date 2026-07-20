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

## Current status

**Stage 0 / Commit 0.2** — business case and project objectives documented.

Application code, database schema, remaining planning docs, and deployment
guides will be added in later roadmap commits. Do not expect a runnable site
until the foundation and database stages are complete.

## Security notes

- Never commit real database credentials.
- The live config file `config/database.php` is ignored by Git.
- Use `config/database.example.php` (added in a later commit) as the template.
- Never commit plain-text passwords or private customer data.

## Licence

See [LICENSE](LICENSE) for terms.
