<?php
/**
 * CustomCore — Active theme resolver (Stage 10).
 *
 * File responsibility:
 *   Decides which theme stylesheet the shared header links. The active theme is
 *   stored in MySQL (site_settings.active_theme_id → themes.css_file), with a
 *   defence-in-depth fallback chain so a page always renders even when the
 *   database is unavailable or a record is missing/corrupt.
 *
 * Resolution order:
 *   1. site_settings.active_theme_id → themes.css_file  (admin selection)
 *   2. themes.is_active_default = 1                      (seeded fallback)
 *   3. config/app.php → default_theme slug              (offline fallback)
 * Every candidate is validated against a strict allow-pattern and must exist on
 * disk before it is linked, which blocks path traversal and dead links.
 *
 * Stage roadmap:
 *   10.1 introduces this resolver plus the RGB Gaming stylesheet. Stage 10.4
 *   adds the administrator switcher that writes active_theme_id, and 10.5
 *   hardens the fallback behaviour that already lives here.
 *
 * Usage (in includes/header.php):
 *   require_once __DIR__ . '/theme.php';
 *   $themeHref = customcore_active_theme_href();
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Slug used when no database-selected theme is available.
 */
function customcore_theme_default_slug(): string
{
    $app = customcore_app_config();
    $slug = isset($app['default_theme']) ? (string) $app['default_theme'] : '';

    return $slug !== '' ? $slug : 'rgb-gaming';
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
 * Resolve the active theme stylesheet as a validated, on-disk relative path.
 *
 * @return string|null Relative path (e.g. "assets/themes/rgb-gaming.css"), or
 *                     null when no valid stylesheet can be found.
 */
function customcore_active_theme_file(): ?string
{
    $root = dirname(__DIR__);
    $candidates = [];

    // Database lookups are best-effort: a failure must never break the page.
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
        // Ignore and fall back to the config/default stylesheet below.
    }

    // Final offline fallback from non-secret config.
    $candidates[] = 'assets/themes/' . customcore_theme_default_slug() . '.css';

    foreach ($candidates as $candidate) {
        $safe = customcore_theme_normalise_path((string) $candidate);
        if ($safe === null) {
            continue;
        }

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
