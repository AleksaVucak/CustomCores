<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Secure Logout.
// Ends the authenticated session completely, clears session data, destroys the server-side
// session, and expires the session cookie, then redirects the visitor to the login page as a
// guest.
// Completion test: After logout, protected pages become inaccessiblewires the redirects; until
// then, nav returns to Log in / Register and session identity keys are gone).
// Access: None. Guests who hit this URL are still redirected cleanly.
// Security:
//   Logging out is a state-changing action, so it is only performed on a POST request carrying a
//     valid CSRF token. GET requests and missing/invalid tokens are ignored, this defeats logout-
//     CSRF attacks such as <img src="logout.php"> that would otherwise force a sign-out.
//   Uses customcore_logout() to wipe $_SESSION and expire the cookie with the same path / Secure /
//     HttpOnly / SameSite flags used at start.
//   Starts a fresh empty session only to carry a one-time flash message.
//   Does not accept a return URL (open-redirect hardening lands in 4.8).

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/csrf.php';

customcore_session_start();

// Reject anything that is not a token-verified POST. An unverified request
// (GET link prefetch, cross-site forgery) must never clear the session.
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$tokenOk = customcore_csrf_verify(
    isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
);

if (!$isPost || !$tokenOk) {
    if (customcore_is_logged_in()) {
        customcore_redirect(is_file(__DIR__ . '/profile.php') ? 'profile.php' : 'index.php');
    }
    customcore_redirect('login.php');
}

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
