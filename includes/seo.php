<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// SEO public-URL catalogue and sitemap builder.
// Single source of truth for which project paths belong in the public sitemap and which paths
// crawlers should avoid. Used by:
//   sitemap.php (live XML with absolute URLs)
//   sitemap.xml (static snapshot regenerated via --write)
//   robots.txt (hand-maintained to match disallow list below)
//   customcore_is_noindex_page() policy in functions.php
// Design rules:
//   Private customer pages, the entire admin area, APIs, uploads, and action-only scripts (logout,
//     attachment download) are NEVER listed.
//   Only real, public content routes and Help HTML pages are included.
//   Absolute <loc> URLs prefer config/app.php → base_url; otherwise they are derived from the
//     current request (same rules as canonical URLs).

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Public, indexable content pages for the SEO sitemap.
 *
 * Each entry is a project-root-relative path (no leading slash) plus optional
 * changefreq / priority hints. Query strings are allowed (e.g. future filters)
 * but product detail URLs are added separately from the database.
 *
 * @return list<array{path:string, changefreq:string, priority:string}>
 */
function customcore_seo_public_pages(): array
{
    return [
        // Primary marketing / catalogue surface
        ['path' => 'index.php', 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['path' => 'about.php', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['path' => 'catalogue.php', 'changefreq' => 'daily', 'priority' => '0.9'],
        ['path' => 'search.php', 'changefreq' => 'weekly', 'priority' => '0.5'],
        ['path' => 'compare.php', 'changefreq' => 'weekly', 'priority' => '0.6'],
        ['path' => 'reviews.php', 'changefreq' => 'weekly', 'priority' => '0.6'],

        // Builder and learning
        ['path' => 'builder.php', 'changefreq' => 'monthly', 'priority' => '0.9'],
        ['path' => 'builder-results.php', 'changefreq' => 'monthly', 'priority' => '0.4'],
        ['path' => 'media.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['path' => 'store-locations.php', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['path' => 'contact.php', 'changefreq' => 'yearly', 'priority' => '0.5'],
        ['path' => 'accessibility.php', 'changefreq' => 'yearly', 'priority' => '0.4'],

        // Guest account entry points (public forms)
        ['path' => 'register.php', 'changefreq' => 'yearly', 'priority' => '0.5'],
        ['path' => 'login.php', 'changefreq' => 'yearly', 'priority' => '0.5'],

        // Static Help wiki (rubric Help pages)
        ['path' => 'help/index.html', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['path' => 'help/accounts.html', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['path' => 'help/catalogue.html', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['path' => 'help/pc-builder.html', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['path' => 'help/orders.html', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['path' => 'help/support.html', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['path' => 'help/training.html', 'changefreq' => 'monthly', 'priority' => '0.5'],
    ];
}

/**
 * Paths that must never appear in the public sitemap (and are Disallow'd in robots.txt).
 *
 * Kept as an explicit list so audits can compare sitemap / robots / noindex policy.
 *
 * @return list<string>
 */
function customcore_seo_excluded_path_prefixes(): array
{
    return [
        'admin/',
        'api/',
        'uploads/',
        'config/',
        'includes/',
        'database/',
        'docs/',
    ];
}

/**
 * Exact private / action scripts excluded from the sitemap.
 *
 * @return list<string>
 */
function customcore_seo_excluded_scripts(): array
{
    return [
        // Private customer area
        'cart.php',
        'checkout.php',
        'order-confirmation.php',
        'order-details.php',
        'order-history.php',
        'profile.php',
        'edit-profile.php',
        'wishlist.php',
        'saved-builds.php',
        'saved-build.php',
        'consultation.php',
        'consultation-history.php',
        'consultation-attachment.php',
        // Action-only / non-content
        'logout.php',
        // SEO endpoints themselves (avoid recursive listing)
        'sitemap.php',
    ];
}

/**
 * Build an absolute public URL for a project-relative path.
 *
 * Prefers config/app.php → base_url. When that is empty, derives scheme + host
 * (+ optional app base path) from the current request, the same model used by
 * customcore_canonical_url(). Falls back to $fallbackBase when running CLI
 * with no host (e.g. regenerating sitemap.xml).
 *
 * @param string $path Project-relative path, optionally with query (e.g. "product.php?id=3").
 * @param string|null $fallbackBase Absolute origin used only when neither base_url
 * nor a request host is available (e.g. "https://example.com/customcore").
 */
function customcore_seo_absolute_url(string $path, ?string $fallbackBase = null): ?string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, '..') || strpbrk($path, "\r\n\t\0") !== false) {
        return null;
    }

    $app = customcore_app_config();
    $configured = isset($app['base_url']) ? trim((string) $app['base_url']) : '';

    if ($configured !== '') {
        return rtrim($configured, '/') . '/' . $path;
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '' && preg_match('/^[A-Za-z0-9.\-]+(?::\d+)?$/', $host)) {
        $scheme = function_exists('customcore_request_is_https') && customcore_request_is_https()
            ? 'https'
            : 'http';
        $basePath = function_exists('customcore_app_base_path')
            ? customcore_app_base_path()
            : '';

        return $scheme . '://' . $host . $basePath . '/' . $path;
    }

    if ($fallbackBase !== null && $fallbackBase !== '') {
        return rtrim($fallbackBase, '/') . '/' . $path;
    }

    return null;
}

/**
 * Fetch active catalogue product detail URLs for the sitemap.
 *
 * Returns an empty list when the database is unavailable, the rest of the
 * sitemap still builds so crawlers get the public surface either way.
 *
 * @return list<array{path:string, changefreq:string, priority:string, lastmod:?string}>
 */
function customcore_seo_product_sitemap_entries(): array
{
    try {
        require_once __DIR__ . '/database.php';
        $pdo = customcore_pdo();
        $stmt = $pdo->query(
            'SELECT id, updated_at
               FROM products
              WHERE is_active = 1
              ORDER BY id ASC'
        );
        if ($stmt === false) {
            return [];
        }

        $entries = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $lastmod = null;
            $updated = (string) ($row['updated_at'] ?? '');
            if ($updated !== '') {
                $ts = strtotime($updated);
                if ($ts !== false) {
                    $lastmod = date('Y-m-d', $ts);
                }
            }
            $entries[] = [
                'path' => 'product.php?id=' . $id,
                'changefreq' => 'weekly',
                'priority' => '0.7',
                'lastmod' => $lastmod,
            ];
        }

        return $entries;
    } catch (Throwable $exception) {
        return [];
    }
}

/**
 * Whether a project-relative path is allowed in the public sitemap.
 */
function customcore_seo_path_is_public(string $path): bool
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $pathOnly = explode('?', $path, 2)[0];

    foreach (customcore_seo_excluded_path_prefixes() as $prefix) {
        if (str_starts_with($pathOnly, $prefix)) {
            return false;
        }
    }

    $base = basename($pathOnly);
    if (in_array($base, customcore_seo_excluded_scripts(), true)) {
        return false;
    }

    // Anything under admin/ is already caught by the prefix list; belt-and-braces:
    if (str_contains('/' . $pathOnly, '/admin/')) {
        return false;
    }

    return true;
}

/**
 * Build a complete urlset XML document for the public sitemap.
 *
 * @param string|null $fallbackBase Absolute origin for CLI regeneration when
 * base_url and HTTP_HOST are both unavailable.
 * @param bool $includeProducts When true, append active product detail URLs.
 */
function customcore_seo_build_sitemap_xml(
    ?string $fallbackBase = null,
    bool $includeProducts = true
): string {
    $entries = [];

    foreach (customcore_seo_public_pages() as $page) {
        if (!customcore_seo_path_is_public($page['path'])) {
            continue;
        }
        $entries[] = [
            'path' => $page['path'],
            'changefreq' => $page['changefreq'],
            'priority' => $page['priority'],
            'lastmod' => null,
        ];
    }

    if ($includeProducts) {
        foreach (customcore_seo_product_sitemap_entries() as $product) {
            if (!customcore_seo_path_is_public($product['path'])) {
                continue;
            }
            $entries[] = $product;
        }
    }

    $lines = [];
    $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $lines[] = '<!--';
    $lines[] = '  CustomCore public sitemap.';
    $lines[] = '  Private customer pages, the admin area, APIs, uploads, and';
    $lines[] = '  action-only scripts are intentionally excluded. Prefer the live';
    $lines[] = '  sitemap.php endpoint in production (builds absolute URLs from';
    $lines[] = '  base_url or the current request). To regenerate this static file';
    $lines[] = '  run: php sitemap.php followed by the write flag (see README).';
    $lines[] = '-->';
    $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    $today = date('Y-m-d');

    foreach ($entries as $entry) {
        $loc = customcore_seo_absolute_url($entry['path'], $fallbackBase);
        if ($loc === null) {
            continue;
        }

        $lines[] = '  <url>';
        $lines[] = '    <loc>' . customcore_seo_xml_escape($loc) . '</loc>';
        $lastmod = $entry['lastmod'] ?? $today;
        if (is_string($lastmod) && $lastmod !== '') {
            $lines[] = '    <lastmod>' . customcore_seo_xml_escape($lastmod) . '</lastmod>';
        }
        $lines[] = '    <changefreq>' . customcore_seo_xml_escape((string) $entry['changefreq']) . '</changefreq>';
        $lines[] = '    <priority>' . customcore_seo_xml_escape((string) $entry['priority']) . '</priority>';
        $lines[] = '  </url>';
    }

    $lines[] = '</urlset>';

    return implode("\n", $lines) . "\n";
}

/**
 * Escape a string for use inside XML text/attribute content.
 */
function customcore_seo_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
