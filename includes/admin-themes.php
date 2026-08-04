<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator theme switching helpers.
// Lists the seeded site themes, reads the currently selected site_settings.active_theme_id, and
// persists a new selection after the administrator confirms it on admin/themes.php. Selection is
// validated against a real themes.id and an on-disk CSS file before writing.
// Access: Callers must already have run customcore_require_admin().
// Security:
//   Theme IDs are cast to integers and looked up with prepared statements.
//   css_file values are path-validated via customcore_theme_normalise_path() and must exist on
//     disk before activation (blocks traversal / dead links).
//   Only the active_theme_id setting is mutated; is_active_default is left alone as the permanent
//     fallback marker used by includes/theme.php.

declare(strict_types=1);

require_once __DIR__ . '/theme.php';

/** Setting key that stores the currently selected themes.id. */
function customcore_admin_theme_setting_key(): string
{
    return 'active_theme_id';
}

/**
 * Short human-readable blurbs for the three seeded themes.
 *
 * The themes table has no description column; these labels are keyed by slug
 * so the admin UI can explain each look without hard-coding database rows.
 *
 * @return array<string, string>
 */
function customcore_admin_theme_blurbs(): array
{
    return [
        'rgb-gaming' => 'Bold dark battlestation look with a cyan accent, neon flourishes, and a gradient wordmark.',
        'minimal-pro' => 'Calm light editorial look with a serif display face, hairline borders, and a professional blue accent.',
        'cyber-grid' => 'Technical HUD look with a visible blueprint grid, angular type, square edges, and mint accents.',
    ];
}

/**
 * Return a display blurb for a theme slug, or a generic sentence.
 */
function customcore_admin_theme_blurb(string $slug): string
{
    $blurbs = customcore_admin_theme_blurbs();

    return $blurbs[$slug] ?? 'Site-wide CSS template applied on top of the shared base stylesheet.';
}

/**
 * Fetch every theme row ordered for the switcher UI.
 *
 * @return list<array{
 * id:int
 * name:string
 * slug:string
 * css_file:string
 * is_active_default:int
 * css_exists:bool
 * blurb:string
 * }>
 */
function customcore_admin_theme_list(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, name, slug, css_file, is_active_default '
        . 'FROM themes '
        . 'ORDER BY id ASC'
    );

    if ($stmt === false) {
        return [];
    }

    $root = dirname(__DIR__);
    $rows = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!is_array($row)) {
            continue;
        }

        $cssFile = (string) ($row['css_file'] ?? '');
        $safe = customcore_theme_normalise_path($cssFile);
        $exists = $safe !== null && is_file($root . '/' . $safe);
        $slug = (string) ($row['slug'] ?? '');

        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => $slug,
            'css_file' => $cssFile,
            'is_active_default' => (int) ($row['is_active_default'] ?? 0),
            'css_exists' => $exists,
            'blurb' => customcore_admin_theme_blurb($slug),
        ];
    }

    return $rows;
}

/**
 * Return the currently selected theme id from site_settings, or null.
 */
function customcore_admin_theme_active_id(PDO $pdo): ?int
{
    $stmt = $pdo->prepare(
        'SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1'
    );
    $stmt->execute([':key' => customcore_admin_theme_setting_key()]);
    $value = $stmt->fetchColumn();

    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $id = (int) $value;

    return $id > 0 ? $id : null;
}

/**
 * Fetch one theme by primary key, or null when missing.
 *
 * @return array{
 * id:int
 * name:string
 * slug:string
 * css_file:string
 * is_active_default:int
 * }|null
 */
function customcore_admin_theme_fetch(PDO $pdo, int $themeId): ?array
{
    if ($themeId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, slug, css_file, is_active_default '
        . 'FROM themes WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $themeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'slug' => (string) $row['slug'],
        'css_file' => (string) $row['css_file'],
        'is_active_default' => (int) $row['is_active_default'],
    ];
}

/**
 * True when a theme row has a safe, on-disk CSS file that can be linked.
 */
function customcore_admin_theme_is_activatable(array $theme): bool
{
    $safe = customcore_theme_normalise_path((string) ($theme['css_file'] ?? ''));
    if ($safe === null) {
        return false;
    }

    return is_file(dirname(__DIR__) . '/' . $safe);
}

/**
 * Persist the active theme selection to site_settings.active_theme_id.
 *
 * Validates that the theme exists and its CSS file is safe and present on disk.
 * Creates the setting row if it is missing (UPSERT-style).
 *
 * @return array{ok:bool, message:string, theme:?array}
 */
function customcore_admin_theme_set_active(PDO $pdo, int $themeId): array
{
    $theme = customcore_admin_theme_fetch($pdo, $themeId);
    if ($theme === null) {
        return [
            'ok' => false,
            'message' => 'That theme could not be found.',
            'theme' => null,
        ];
    }

    if (!customcore_admin_theme_is_activatable($theme)) {
        return [
            'ok' => false,
            'message' => 'That theme’s stylesheet is missing or invalid, so it cannot be activated.',
            'theme' => $theme,
        ];
    }

    $key = customcore_admin_theme_setting_key();
    $value = (string) $theme['id'];

    // Look up first so we never depend on MySQL UPDATE rowCount quirks.
    $check = $pdo->prepare(
        'SELECT id FROM site_settings WHERE setting_key = :key LIMIT 1'
    );
    $check->execute([':key' => $key]);
    $existingId = $check->fetchColumn();

    if ($existingId === false) {
        $insert = $pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value) VALUES (:key, :value)'
        );
        $insert->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
    } else {
        $update = $pdo->prepare(
            'UPDATE site_settings SET setting_value = :value WHERE setting_key = :key'
        );
        $update->execute([
            ':value' => $value,
            ':key' => $key,
        ]);
    }

    return [
        'ok' => true,
        'message' => 'Active theme updated to “' . $theme['name'] . '”.',
        'theme' => $theme,
    ];
}
