<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Live SEO sitemap.
// Emits an XML sitemap of public, indexable URLs with absolute <loc> values. Private customer
// pages, admin/, api/, uploads/, and action-only scripts are never included (see
// includes/seo.php). Active catalogue products are added as product.php?id=N when the database is
// reachable.
// Usage: Browser / crawler: GET /sitemap.php Regenerate static: php sitemap.php --write php
// sitemap.php --write --base=https://host/~id/customcore
// robots.txt points crawlers at this endpoint (and at the static sitemap.xml snapshot) so private
// routes stay out of the index.

declare(strict_types=1);

require_once __DIR__ . '/includes/seo.php';

$isCli = (PHP_SAPI === 'cli');
$writeStatic = false;
$fallbackBase = 'https://example.com';

if ($isCli) {
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if ($arg === '--write') {
            $writeStatic = true;
            continue;
        }
        if (str_starts_with($arg, '--base=')) {
            $candidate = trim(substr($arg, 7));
            if ($candidate !== '' && preg_match('#^https?://[^\s]+$#i', $candidate)) {
                $fallbackBase = rtrim($candidate, '/');
            }
        }
    }
}

$xml = customcore_seo_build_sitemap_xml($isCli ? $fallbackBase : null, true);

if ($writeStatic) {
    $target = __DIR__ . '/sitemap.xml';
    $bytes = file_put_contents($target, $xml);
    if ($bytes === false) {
        fwrite(STDERR, "Failed to write {$target}\n");
        exit(1);
    }
    fwrite(STDOUT, "Wrote {$bytes} bytes to sitemap.xml\n");
    exit(0);
}

if (!$isCli) {
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex, follow');
}

echo $xml;
