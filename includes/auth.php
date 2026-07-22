<?php
/**
 * CustomCore — Authentication state helpers (read side).
 *
 * File responsibility:
 *   Read-only helpers describing the currently logged-in user based on session
 *   data. Login (Commit 4.2), route protection (4.4), roles (4.7), and session
 *   hardening (4.8) build on the same session keys defined here.
 *
 * Session key convention (set by login.php in Commit 4.2):
 *   $_SESSION['user_id']    int    — users.id
 *   $_SESSION['user_role']  string — 'customer' | 'admin'
 *   $_SESSION['user_name']  string — display first name
 *   $_SESSION['user_email'] string — account email
 *
 * Authentication requirements:
 *   None to include. These helpers only read session state.
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
