<?php
/**
 * CustomCore — CSRF protection helpers.
 *
 * File responsibility:
 *   Generates a per-session CSRF token and verifies it on state-changing POST
 *   requests (registration, login, profile edits, cart, checkout, etc.).
 *
 * Authentication requirements:
 *   None. Any page that renders a form or handles a POST may use these helpers.
 *
 * Usage (form page):
 *   require_once __DIR__ . '/includes/csrf.php';
 *   echo customcore_csrf_field();            // inside <form>
 *
 * Usage (POST handler):
 *   if (!customcore_csrf_verify($_POST['_csrf'] ?? null)) { reject; }
 *
 * Security:
 *   - Token is 32 random bytes (hex) from random_bytes().
 *   - Verification uses hash_equals() to avoid timing attacks.
 *   - Token lives in the session; login session regeneration (Stage 4.8)
 *     keeps the token because session data is preserved across regeneration.
 */

declare(strict_types=1);

if (!function_exists('customcore_session_start')) {
    require_once __DIR__ . '/functions.php';
}

/**
 * Return the current session CSRF token, creating one if needed.
 */
function customcore_csrf_token(): string
{
    customcore_session_start();

    if (empty($_SESSION['_cc_csrf']) || !is_string($_SESSION['_cc_csrf'])) {
        $_SESSION['_cc_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_cc_csrf'];
}

/**
 * Return a ready-to-embed hidden input field carrying the CSRF token.
 */
function customcore_csrf_field(): string
{
    $token = customcore_csrf_token();

    return '<input type="hidden" name="_csrf" value="' . customcore_e($token) . '">';
}

/**
 * Verify a submitted CSRF token against the session token.
 *
 * @param string|null $token Submitted token (e.g. $_POST['_csrf']).
 */
function customcore_csrf_verify(?string $token): bool
{
    customcore_session_start();

    $stored = $_SESSION['_cc_csrf'] ?? '';

    if (!is_string($stored) || $stored === '' || !is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($stored, $token);
}
