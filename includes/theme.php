<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Active theme resolver, hardened in 10.5).
// Decides which theme stylesheet the shared header links. The active theme is stored in MySQL
// (site_settings.active_theme_id → themes.css_file), with a defence-in-depth fallback chain so a
// page is NEVER left unstyled and a bad value can never smuggle in a foreign file path.
// Resolution order (first safe, on-disk match wins): 1. site_settings.active_theme_id →
// themes.css_file (admin selection) 2. themes.is_active_default = 1 → css_file (seeded fallback)
// 3. config/app.php → default_theme slug (offline fallback) 4. canonical slug "rgb-gaming" (hard-
// coded fallback) 5. first valid assets/themes/*.css on disk (last-resort scan)
// Safety guarantees:
//   Every candidate, from the database, config, or a scan, is passed through
//     customcore_theme_normalise_path(), which only accepts files that match
//     ^assets/themes/<slug>.css exactly. This blocks path traversal (../), absolute paths,
//     subdirectories, query strings, and non-CSS files even if a row in the database is corrupt or
//     malicious.
//   A candidate must also exist on disk before it is linked, so a missing or renamed file
//     transparently falls through to the next candidate.
//   Database access is wrapped in try/catch: if MySQL is unavailable the resolver still returns a
//     styled theme from config / canonical / scan.
//   If somehow no theme file exists at all, the resolver returns null and the header simply omits
//     the theme link, the site remains styled by the base assets/css/main.css, which is always
//     linked first.
// Usage (in includes/header.php): require_once __DIR__. '/theme.php';
//   $themeHref = customcore_active_theme_href();

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Canonical fallback slug guaranteed to ship with the project (rubric default).
 *
 * This is intentionally hard-coded and independent of config so that even a
 * corrupted config/app.php value cannot leave the site without a theme.
 */
function customcore_theme_canonical_slug(): string
{
    return 'rgb-gaming';
}

/**
 * Slug used when no database-selected theme is available.
 */
function customcore_theme_default_slug(): string
{
    $app = customcore_app_config();
    $slug = isset($app['default_theme']) ? (string) $app['default_theme'] : '';

    return $slug !== '' ? $slug : customcore_theme_canonical_slug();
}

/**
 * Normalise and validate a theme CSS path relative to the project root.
 *
 * Only files directly inside assets/themes/ that look like "<slug>.css" are
 * accepted, which prevents directory traversal from database or config values.
 *
 * @return string|null Safe relative path, or null when the value is rejected.
 */
function customcore_theme_normalise_path(string $relative): ?string
{
    $relative = trim($relative);
    if ($relative === '') {
        return null;
    }

    $relative = ltrim(str_replace('\\', '/', $relative), '/');

    if (!preg_match('#^assets/themes/[a-z0-9][a-z0-9-]*\.css$#', $relative)) {
        return null;
    }

    return $relative;
}

/**
 * List every valid, on-disk theme stylesheet under assets/themes/.
 *
 * Used as the last-resort fallback so the site stays styled even if both the
 * database and config point at missing files. Results are validated through the
 * same allow-pattern and sorted for deterministic output.
 *
 * @return list<string> Relative paths (e.g. "assets/themes/rgb-gaming.css").
 */
function customcore_theme_scan_available(): array
{
    $root = dirname(__DIR__);
    $dir = $root . '/assets/themes';

    if (!is_dir($dir)) {
        return [];
    }

    $matches = glob($dir . '/*.css');
    if ($matches === false || $matches === []) {
        return [];
    }

    sort($matches, SORT_STRING);

    $files = [];
    foreach ($matches as $absolute) {
        $safe = customcore_theme_normalise_path('assets/themes/' . basename($absolute));
        if ($safe !== null && is_file($root . '/' . $safe)) {
            $files[] = $safe;
        }
    }

    return $files;
}

/**
 * Resolve the active theme stylesheet as a validated, on-disk relative path.
 *
 * @return string|null Relative path (e.g. "assets/themes/rgb-gaming.css"), or
 * null when no valid stylesheet can be found at all.
 */
function customcore_active_theme_file(): ?string
{
    $root = dirname(__DIR__);
    $candidates = [];

    // 1 + 2: Database lookups are best-effort, a failure must never break a page.
    try {
        require_once __DIR__ . '/database.php';
        $pdo = customcore_pdo();

        $selected = $pdo->query(
            'SELECT t.css_file '
            . 'FROM site_settings s '
            . 'INNER JOIN themes t ON t.id = CAST(s.setting_value AS UNSIGNED) '
            . "WHERE s.setting_key = 'active_theme_id' "
            . 'LIMIT 1'
        );
        $selectedFile = $selected !== false ? $selected->fetchColumn() : false;
        if (is_string($selectedFile) && $selectedFile !== '') {
            $candidates[] = $selectedFile;
        }

        $default = $pdo->query(
            'SELECT css_file FROM themes WHERE is_active_default = 1 ORDER BY id LIMIT 1'
        );
        $defaultFile = $default !== false ? $default->fetchColumn() : false;
        if (is_string($defaultFile) && $defaultFile !== '') {
            $candidates[] = $defaultFile;
        }
    } catch (Throwable $exception) {
        // Ignore and fall back to the config / canonical / scan candidates below.
    }

    // 3: Offline fallback from non-secret config.
    $candidates[] = 'assets/themes/' . customcore_theme_default_slug() . '.css';

    // 4: Hard-coded canonical fallback (independent of DB and config).
    $candidates[] = 'assets/themes/' . customcore_theme_canonical_slug() . '.css';

    // 5: Last-resort scan of whatever theme files actually exist on disk.
    foreach (customcore_theme_scan_available() as $scanned) {
        $candidates[] = $scanned;
    }

    // First candidate that is both path-safe and present on disk wins.
    $seen = [];
    foreach ($candidates as $candidate) {
        $safe = customcore_theme_normalise_path((string) $candidate);
        if ($safe === null || isset($seen[$safe])) {
            continue;
        }
        $seen[$safe] = true;

        if (is_file($root . '/' . $safe)) {
            return $safe;
        }
    }

    return null;
}

/**
 * Public URL for the active theme stylesheet, or null when none is available.
 */
function customcore_active_theme_href(): ?string
{
    $file = customcore_active_theme_file();

    return $file === null ? null : customcore_url($file);
}
