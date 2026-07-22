<?php
/**
 * CustomCore — Authentication state helpers (read side).
 *
 * File responsibility:
 *   Read helpers for the current authenticated user, plus customcore_logout()
 *   to fully end a session (Commit 4.3). Login (4.2), route protection (4.4),
 *   roles (4.7), and session hardening (4.8) share the same session keys.
 *
 * Session key convention (set by login.php in Commit 4.2):
 *   $_SESSION['user_id']    int    — users.id
 *   $_SESSION['user_role']  string — 'customer' | 'admin'
 *   $_SESSION['user_name']  string — display first name
 *   $_SESSION['user_email'] string — account email
 *
 * Authentication requirements:
 *   None to include. Read helpers only inspect session state; logout clears it.
 *
 * Usage:
 *   require_once __DIR__ . '/auth.php';
 *   if (customcore_is_logged_in()) { ... }
 */

declare(strict_types=1);

if (!function_exists('customcore_session_start')) {
    require_once __DIR__ . '/functions.php';
}

/**
 * Whether a customer or admin is currently authenticated.
 */
function customcore_is_logged_in(): bool
{
    customcore_session_start();

    return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
}

/**
 * The current user's id, or 0 when not logged in.
 */
function customcore_current_user_id(): int
{
    customcore_session_start();

    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

/**
 * The current user's role, or '' when not logged in.
 */
function customcore_current_user_role(): string
{
    customcore_session_start();

    return isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : '';
}

/**
 * The current user's display name, or '' when not logged in.
 */
function customcore_current_user_name(): string
{
    customcore_session_start();

    return isset($_SESSION['user_name']) ? (string) $_SESSION['user_name'] : '';
}

/**
 * The current user's email, or '' when not logged in.
 */
function customcore_current_user_email(): string
{
    customcore_session_start();

    return isset($_SESSION['user_email']) ? (string) $_SESSION['user_email'] : '';
}

/**
 * Whether the current user holds the admin role.
 */
function customcore_is_admin(): bool
{
    return customcore_current_user_role() === 'admin';
}

/**
 * Guard: require an authenticated user, or redirect guests to login.
 *
 * Private customer pages call this at the very top, before any output:
 *   require_once __DIR__ . '/includes/auth.php';
 *   customcore_require_login();
 *
 * Behaviour:
 *   - Logged-in users continue normally.
 *   - Guests get a warning flash and are redirected to login.php. The page they
 *     tried to reach (GET only) is stored so login can send them back (4.8
 *     hardens this further; the stored path is already validated as local).
 */
function customcore_require_login(): void
{
    if (customcore_is_logged_in()) {
        return;
    }

    require_once __DIR__ . '/flash.php';

    $method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET';
    $uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
        ? $_SERVER['REQUEST_URI']
        : '';

    if ($method === 'GET' && customcore_is_safe_local_path($uri)) {
        $_SESSION['_cc_return_to'] = $uri;
    }

    customcore_flash_warning('Please log in to continue.');
    customcore_redirect('login.php');
}

/**
 * Guard: require a guest, or redirect authenticated users away.
 *
 * Used by login.php and register.php so logged-in users skip those forms.
 */
function customcore_require_guest(): void
{
    if (!customcore_is_logged_in()) {
        return;
    }

    $destination = is_file(dirname(__DIR__) . '/profile.php') ? 'profile.php' : 'index.php';
    customcore_redirect($destination);
}

/**
 * Consume the stored post-login return path, if any.
 *
 * Returns a validated same-origin path or null. The value is removed from the
 * session so it is only used once.
 */
function customcore_take_return_to(): ?string
{
    customcore_session_start();

    $target = $_SESSION['_cc_return_to'] ?? null;
    unset($_SESSION['_cc_return_to']);

    if (is_string($target) && customcore_is_safe_local_path($target)) {
        return $target;
    }

    return null;
}

/**
 * End the current authenticated session completely.
 *
 * Clears all session data, destroys the session, and expires the session cookie
 * using the same parameters used when the session was created. After this call
 * the visitor is a guest. A fresh session may be started afterward (e.g. for a
 * flash message on the logout redirect).
 *
 * Used by: logout.php (Commit 4.3).
 */
function customcore_logout(): void
{
    customcore_session_start();

    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        $name = session_name();
        $params = session_get_cookie_params();

        if (ini_get('session.use_cookies') && is_string($name) && $name !== '') {
            setcookie(
                $name,
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => !empty($params['secure']),
                    'httponly' => !empty($params['httponly']),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }
}
