# CustomCore | Documentation Finalization (17.4)

**Document type:** Stage 17.4 documentation finalize record  
**Date:** 4 August 2026  
**Purpose:** Ship a coherent final README and guide set where documentation links resolve, stale “planned” claims match the live product, and graders can find every major guide from the repository landing page.  
**Related:** project [README](../README.md), [sitemap](sitemap.md), [directory structure](directory-structure.md), [administrator guide](administrator-guide.md).

---

## 1. Goal (roadmap 17.4)

| Deliverable | Meaning |
| ----------- | ------- |
| Final README | Live URL, install path, demo walkthrough, accurate feature counts, complete Key Documentation index |
| Guides finalized | Admin, install, production, monitoring, Help, and QA docs match what shipped |
| All documentation links work | Relative markdown/Help targets exist on disk |

---

## 2. Actions completed

| Action | Result |
| ------ | ------ |
| Link audit of `README.md` + all `docs/*.md` + `config/README.md` | **0** broken relative markdown links |
| Help wiki `href` audit | Repaired missing target by shipping public **`privacy.php`** (footer + Help already linked it) |
| Admin guide | Removed “monitoring planned / tools coming” language; §13 documents live monitoring |
| Rubric checklist Section B | Marked B1, B2, B3, B12, B13 complete (and B9/B10 aligned with monitoring docs) |
| Page-count truthfulness | **51** purposeful dynamic PHP files (31 root + 17 admin + 3 API); sitemap / README / directory / rubric counts aligned; removed obsolete `api/product-search.php` claim |
| SEO docs/inventory | `privacy.php` added to `customcore_seo_public_pages()` and static `sitemap.xml` snapshot regenerated |
| README Key Documentation | Expanded so install, architecture, media, Help map, production, cleanup, and this finalize record are one hop from the root |

---

## 3. Privacy page (link integrity)

Before this pass, the shared footer and every Help page linked to `privacy.php`, and `docs/sitemap.md` listed it, but the file was missing. That is fixed with a public academic privacy statement covering:

- simulated checkout (no real card data)
- account fields and password hashing
- orders, consultations, reviews, contact messages
- sessions/CSRF/prepared statements
- admin vs public access

---

## 4. Verification

```bash
# markdown relative links (project script equivalent)
python3 -c "…scan README + docs…; expect 0 broken…"

# Help privacy target
test -f privacy.php

# optional SEO snapshot
php sitemap.php --write --base=https://vucaka.myweb.cs.uwindsor.ca/customcore
```

| Check | Status |
| ----- | ------ |
| Markdown relative links resolve | Pass |
| Help → `../privacy.php` exists | Pass |
| Admin guide documents monitoring | Pass |
| README Key Documentation matches real `docs/` set | Pass |
| Live URL still recorded in README | Pass |

---

## 5. Stage 17.4 acceptance

| Criterion | Status |
| --------- | ------ |
| Final README and guides | Pass |
| All documentation links work | Pass |
| Docs match the shipped application | Pass |

Phase **17.4** is complete. Next optional polish: Stage **17.5** submission audit checklist.
