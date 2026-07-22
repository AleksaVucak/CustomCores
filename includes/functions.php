<?php
/**
 * CustomCore — Shared helper functions for layout and output.
 *
 * File responsibility:
 *   Loads application config and provides escaping, URL helpers, and nav helpers
 *   used by header, footer, navigation, and public pages.
 *
 * Usage:
 *   require_once __DIR__ . '/functions.php';
 */

declare(strict_types=1);

/**
 * Load the non-secret application configuration array.
 *
 * @return array<string, mixed>
 */
function customcore_app_config(): array
{
    static $app = null;

    if ($app === null) {
        $path = dirname(__DIR__) . '/config/app.php';
        if (!is_readable($path)) {
            throw new RuntimeException('Application configuration file is missing.');
        }

        /** @var array<string, mixed> $loaded */
        $loaded = require $path;
        $app = $loaded;

        if (!empty($app['timezone']) && is_string($app['timezone'])) {
            date_default_timezone_set($app['timezone']);
        }
    }

    return $app;
}

/**
 * Whether developer-oriented error detail may be shown.
 */
function customcore_is_debug(): bool
{
    $app = customcore_app_config();
    return !empty($app['debug']);
}

/**
 * Escape text for safe HTML output.
 *
 * @param string|int|float|null $value Raw value to display.
 */
function customcore_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Start the PHP session once with the configured session name.
 *
 * Security notes:
 *   - Uses a custom session name to reduce collisions on shared hosts.
 *   - Cookie flags favour HTTP-only cookies; Secure is enabled under HTTPS.
 */
function customcore_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $app = customcore_app_config();
    $sessionName = (string) ($app['session_name'] ?? 'CUSTOMCORESESSID');

    if ($sessionName !== '') {
        session_name($sessionName);
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443));

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Redirect to a project-root path and stop script execution.
 *
 * @param string $path Path relative to the project root (e.g. "login.php").
 * @param int $statusCode HTTP status code (303 recommended after POST).
 */
function customcore_redirect(string $path, int $statusCode = 303): void
{
    $target = customcore_url($path);

    if (!headers_sent()) {
        header('Location: ' . $target, true, $statusCode);
    }

    exit;
}

/**
 * How many directory levels below the project root the current script lives in.
 *
 * Examples: index.php => 0, admin/products.php => 1.
 */
function customcore_script_depth(): int
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dirname = dirname($scriptName);
    $dirname = trim($dirname, '/');

    if ($dirname === '' || $dirname === '.') {
        return 0;
    }

    return substr_count($dirname, '/') + 1;
}

/**
 * Build a relative href from the current script to a project-root path.
 *
 * @param string $path Path relative to the project root (e.g. "about.php", "assets/css/main.css").
 */
function customcore_url(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $depth = customcore_script_depth();
    $prefix = $depth > 0 ? str_repeat('../', $depth) : '';

    if ($path === '') {
        return $prefix === '' ? './' : $prefix;
    }

    return $prefix . $path;
}

/**
 * Whether the current page matches a navigation key.
 *
 * Pages set $currentPage before including the header (e.g. "home", "about").
 *
 * @param string $pageKey Navigation key to compare.
 */
function customcore_is_current_page(string $pageKey): bool
{
    global $currentPage;
    return isset($currentPage) && is_string($currentPage) && $currentPage === $pageKey;
}

/**
 * CSS class string for an active navigation link.
 *
 * @param string $pageKey Navigation key for this link.
 */
function customcore_nav_class(string $pageKey): string
{
    return customcore_is_current_page($pageKey) ? ' is-active' : '';
}

/**
 * Format a 1–5 rating as accessible star text (★ / ☆).
 *
 * @param int $rating Rating value (clamped to 1–5).
 */
function customcore_format_rating(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

/**
 * Format a datetime string from MySQL for display (e.g. 22 Jul 2026).
 *
 * @param string $datetime MySQL DATETIME value.
 */
function customcore_format_date(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }

    return date('j M Y', $timestamp);
}
