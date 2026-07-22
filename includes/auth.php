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
 * Whether the current user holds the admin role.
 */
function customcore_is_admin(): bool
{
    return customcore_current_user_role() === 'admin';
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
