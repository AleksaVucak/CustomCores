<?php
/**
 * CustomCore — Secure Logout (Commit 4.3).
 *
 * File responsibility:
 *   Ends the authenticated session completely — clears session data, destroys
 *   the server-side session, and expires the session cookie — then redirects
 *   the visitor to the login page as a guest.
 *
 * Completion test:
 *   After logout, protected pages become inaccessible (Commit 4.4 wires the
 *   redirects; until then, nav returns to Log in / Register and session
 *   identity keys are gone).
 *
 * Authentication requirements:
 *   None. Guests who hit this URL are still redirected cleanly.
 *
 * Security:
 *   - Uses customcore_logout() to wipe $_SESSION and expire the cookie with
 *     the same path / Secure / HttpOnly / SameSite flags used at start.
 *   - Starts a fresh empty session only to carry a one-time flash message.
 *   - Does not accept a return URL (open-redirect hardening lands in 4.8).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

customcore_session_start();

$wasLoggedIn = customcore_is_logged_in();

customcore_logout();

// Fresh session so the success flash survives the redirect.
customcore_session_start();

if ($wasLoggedIn) {
    customcore_flash_success('You have been logged out.');
} else {
    customcore_flash_warning('You were not logged in.');
}

customcore_redirect('login.php');
