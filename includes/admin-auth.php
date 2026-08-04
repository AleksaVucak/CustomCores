<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator authorization guard.
// Restricts admin-only routes to authenticated users with the 'admin' role. Guests are sent to
// login; logged-in non-admins (customers) are redirected away with a permission error. Every file
// under admin/ includes this first.
// Usage (top of any admin/*.php page, before output): require_once __DIR__. '/../includes/admin-
// auth.php'; customcore_require_admin();
// Access: Admin role required. Builds on the same session keys as includes/auth.php.
// Security:
//   Reuses customcore_require_login() so guests get the standard login flow.
//   Role check uses the session role, kept in sync from the database on profile load; sensitive
//     admin actions in re-verify as needed.

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';

/**
 * Require an authenticated administrator, or redirect away.
 *
 * - Guests → login (via customcore_require_login()), with return-to.
 * - Logged-in customers → profile with a permission-denied flash.
 * - Admins → continue.
 */
function customcore_require_admin(): void
{
    // Guests are handled by the standard login guard (redirect + return-to).
    customcore_require_login();

    if (customcore_is_admin()) {
        return;
    }

    // Authenticated but not an admin: deny and send to their own dashboard.
    customcore_flash_error('You do not have permission to access the administrator area.');
    customcore_redirect('profile.php');
}
