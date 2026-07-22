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
 * Security notes (Commit 4.8 hardening):
 *   - Uses a custom session name to reduce collisions on shared hosts.
 *   - Cookies are HTTP-only and SameSite=Lax; Secure is enabled under HTTPS.
 *   - Strict mode rejects attacker-supplied (uninitialized) session IDs.
 *   - Cookie-only transport disables session IDs in URLs (no trans_sid).
 *   - Stronger, longer session IDs are requested where the SAPI allows it.
 *   - After the session opens, customcore_session_harden() enforces idle and
 *     absolute timeouts, user-agent binding, and periodic ID rotation.
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

    $secure = customcore_request_is_https();

    // Harden the session engine before the cookie is issued. These runtime
    // ini settings are ignored gracefully if the session has already started
    // or the host forbids overrides.
    if (function_exists('ini_set')) {
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Lax');
        @ini_set('session.sid_length', '48');
        @ini_set('session.sid_bits_per_character', '6');
        if ($secure) {
            @ini_set('session.cookie_secure', '1');
        }

        $absolute = (int) ($app['session_absolute_timeout'] ?? 0);
        if ($absolute > 0) {
            @ini_set('session.gc_maxlifetime', (string) $absolute);
        }
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    customcore_session_harden();
}

/**
 * Whether the current request arrived over HTTPS.
 */
function customcore_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    // Honour a proxy/load-balancer that terminates TLS upstream.
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return false;
}

/**
 * Enforce session-lifetime and integrity rules on authenticated sessions.
 *
 * Runs once per request, immediately after the session opens. Only sessions
 * that carry a logged-in user are guarded so guest browsing stays lightweight.
 *
 * Checks, in order (any failure expires the session):
 *   1. User-agent binding — a stolen cookie replayed from a different client
 *      no longer matches the fingerprint captured at login.
 *   2. Absolute lifetime — a session cannot outlive session_absolute_timeout,
 *      regardless of activity.
 *   3. Idle timeout — inactivity beyond session_idle_timeout ends the session.
 *   4. Periodic ID rotation — the session ID is regenerated every
 *      session_regenerate_interval seconds to shrink the fixation window.
 */
function customcore_session_harden(): void
{
    // Only guard authenticated sessions.
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $app = customcore_app_config();
    $now = time();

    $idleTimeout = (int) ($app['session_idle_timeout'] ?? 1800);
    $absoluteTimeout = (int) ($app['session_absolute_timeout'] ?? 43200);
    $regenInterval = (int) ($app['session_regenerate_interval'] ?? 900);

    // 1. User-agent binding.
    $fingerprint = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (!isset($_SESSION['_cc_fp'])) {
        $_SESSION['_cc_fp'] = $fingerprint;
    } elseif (!hash_equals((string) $_SESSION['_cc_fp'], $fingerprint)) {
        customcore_session_expire('For your security, please log in again.');
        return;
    }

    // 2. Absolute lifetime.
    if (!isset($_SESSION['_cc_created'])) {
        $_SESSION['_cc_created'] = $now;
    } elseif ($absoluteTimeout > 0 && ($now - (int) $_SESSION['_cc_created']) > $absoluteTimeout) {
        customcore_session_expire('Your session has expired. Please log in again.');
        return;
    }

    // 3. Idle timeout.
    if ($idleTimeout > 0
        && isset($_SESSION['_cc_last_activity'])
        && ($now - (int) $_SESSION['_cc_last_activity']) > $idleTimeout) {
        customcore_session_expire('You were logged out after a period of inactivity.');
        return;
    }
    $_SESSION['_cc_last_activity'] = $now;

    // 4. Periodic session ID rotation.
    if (!isset($_SESSION['_cc_last_regen'])) {
        $_SESSION['_cc_last_regen'] = $now;
    } elseif ($regenInterval > 0 && ($now - (int) $_SESSION['_cc_last_regen']) > $regenInterval) {
        session_regenerate_id(true);
        $_SESSION['_cc_last_regen'] = $now;
    }
}

/**
 * Immediately end the current session, then start a clean guest session so a
 * warning flash can be shown on the next request.
 *
 * @param string $warning Optional message queued for the next page load.
 */
function customcore_session_expire(string $warning = ''): void
{
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        if (ini_get('session.use_cookies')) {
            $name = session_name();
            $params = session_get_cookie_params();
            if (is_string($name) && $name !== '') {
                setcookie($name, '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => !empty($params['secure']),
                    'httponly' => !empty($params['httponly']),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]);
            }
        }

        session_destroy();
    }

    // Fresh, empty session for the guest so flash/CSRF still work this request.
    session_start();
    session_regenerate_id(true);

    if ($warning !== '') {
        require_once __DIR__ . '/flash.php';
        customcore_flash_warning($warning);
    }
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
 * Whether a string is a safe same-origin path for a Location redirect.
 *
 * Rejects absolute URLs, protocol-relative URLs (//host), backslashes, and
 * control characters (header-injection / open-redirect protection). Accepts
 * only paths that begin with a single "/", e.g. "/customcore/profile.php?x=1".
 *
 * @param string $path Candidate path (typically from $_SERVER['REQUEST_URI']).
 */
function customcore_is_safe_local_path(string $path): bool
{
    if ($path === '' || $path[0] !== '/') {
        return false;
    }

    // Protocol-relative URL such as //evil.example.com
    if (strncmp($path, '//', 2) === 0) {
        return false;
    }

    // Control characters (CR/LF/TAB/NUL) or backslashes are never valid here.
    if (strpbrk($path, "\r\n\t\0") !== false || strpos($path, '\\') !== false) {
        return false;
    }

    return true;
}

/**
 * Redirect to an already-validated same-origin absolute path and stop.
 *
 * The caller MUST pass a path that has been checked with
 * customcore_is_safe_local_path(); unsafe values fall back to the project root.
 *
 * @param string $path Same-origin path beginning with "/".
 * @param int $statusCode HTTP status code (303 recommended after POST).
 */
function customcore_redirect_local(string $path, int $statusCode = 303): void
{
    if (!customcore_is_safe_local_path($path)) {
        customcore_redirect('index.php', $statusCode);
        return;
    }

    if (!headers_sent()) {
        header('Location: ' . $path, true, $statusCode);
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
