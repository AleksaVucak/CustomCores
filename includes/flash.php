<?php
/**
 * CustomCore — Flash message system (one-redirect lifetime).
 *
 * File responsibility:
 *   Stores success, warning, and error messages in the session so they survive
 *   exactly one redirect (Post/Redirect/Get), then clears them after display.
 *
 * Authentication requirements:
 *   None. Any page may set or render flashes after starting the shared session.
 *
 * Usage:
 *   require_once __DIR__ . '/flash.php';
 *   customcore_flash_set('success', 'Your profile was updated.');
 *   customcore_redirect('profile.php');
 *
 *   // On the next page, includes/header.php calls customcore_flash_render().
 *
 * Message types:
 *   - success
 *   - warning
 *   - error
 */

declare(strict_types=1);

if (!function_exists('customcore_session_start')) {
    require_once __DIR__ . '/functions.php';
}

/**
 * Prepare session flash bags once per HTTP request.
 *
 * Messages queued on the previous request move into the current-display bag.
 * Messages set during this request wait in the queue for the next request.
 */
function customcore_flash_bootstrap(): void
{
    static $ready = false;

    customcore_session_start();

    if ($ready) {
        return;
    }

    $ready = true;

    if (!isset($_SESSION['_cc_flash_queue']) || !is_array($_SESSION['_cc_flash_queue'])) {
        $_SESSION['_cc_flash_queue'] = [];
    }

    // Promote last request's queue into the messages shown on this request.
    $_SESSION['_cc_flash_current'] = $_SESSION['_cc_flash_queue'];
    $_SESSION['_cc_flash_queue'] = [];
}

/**
 * Normalize a flash type to one of the allowed values.
 *
 * @param string $type Requested type.
 * @return string One of: success, warning, error.
 */
function customcore_flash_normalize_type(string $type): string
{
    $type = strtolower(trim($type));

    if ($type === 'success' || $type === 'warning' || $type === 'error') {
        return $type;
    }

    return 'error';
}

/**
 * Queue a flash message for the next request (typically after a redirect).
 *
 * @param string $type Message type: success, warning, or error.
 * @param string $message Human-readable message (will be escaped on output).
 */
function customcore_flash_set(string $type, string $message): void
{
    customcore_flash_bootstrap();

    $message = trim($message);
    if ($message === '') {
        return;
    }

    $_SESSION['_cc_flash_queue'][] = [
        'type' => customcore_flash_normalize_type($type),
        'message' => $message,
    ];
}

/**
 * Convenience helpers for the three supported flash types.
 */
function customcore_flash_success(string $message): void
{
    customcore_flash_set('success', $message);
}

function customcore_flash_warning(string $message): void
{
    customcore_flash_set('warning', $message);
}

function customcore_flash_error(string $message): void
{
    customcore_flash_set('error', $message);
}

/**
 * Return current-request flash messages without clearing them.
 *
 * @return array<int, array{type: string, message: string}>
 */
function customcore_flash_peek(): array
{
    customcore_flash_bootstrap();

    $current = $_SESSION['_cc_flash_current'] ?? [];
    if (!is_array($current)) {
        return [];
    }

    $messages = [];
    foreach ($current as $item) {
        if (!is_array($item)) {
            continue;
        }
        $message = isset($item['message']) ? trim((string) $item['message']) : '';
        if ($message === '') {
            continue;
        }
        $messages[] = [
            'type' => customcore_flash_normalize_type((string) ($item['type'] ?? 'error')),
            'message' => $message,
        ];
    }

    return $messages;
}

/**
 * Render queued flash messages as HTML and clear them so they do not repeat.
 */
function customcore_flash_render(): void
{
    $messages = customcore_flash_peek();
    $_SESSION['_cc_flash_current'] = [];

    if ($messages === []) {
        return;
    }

    echo '<div class="flash-stack" aria-live="polite">';

    foreach ($messages as $item) {
        $type = $item['type'];
        $role = $type === 'error' ? 'alert' : 'status';

        echo '<div class="flash flash--' . customcore_e($type) . '" role="' . customcore_e($role) . '">';
        echo customcore_e($item['message']);
        echo '</div>';
    }

    echo '</div>';
}
