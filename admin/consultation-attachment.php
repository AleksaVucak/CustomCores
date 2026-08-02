<?php
/**
 * CustomCore — Administrator consultation attachment download (Commit 9.7).
 *
 * File responsibility:
 *   Streams a consultation attachment to an administrator. Mirrors the customer
 *   endpoint's hardening but scopes access to the admin role rather than the
 *   file's owner, so support staff can review any customer's uploads.
 *
 * URL format:
 *   admin/consultation-attachment.php?id=N
 *
 * Authentication requirements:
 *   Administrator role (customcore_require_admin()).
 *
 * Security:
 *   - Admin-only (customcore_require_admin()).
 *   - On-disk name is the generated stored_filename (basename-guarded), and the
 *     resolved path is confirmed to stay inside the upload directory.
 *   - Sent as an attachment with X-Content-Type-Options: nosniff and a
 *     sanitized (RFC 5987) filename header to prevent header injection.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/consultations.php';
require_once __DIR__ . '/../includes/admin-consultations.php';

customcore_require_admin();

/**
 * Send a 404 and stop. Generic so a missing attachment ID looks the same.
 */
function customcore_admin_attachment_not_found(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Attachment not found.';
    exit;
}

$attachmentId = 0;
if (isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id'])) {
    $attachmentId = (int) $_GET['id'];
}

if ($attachmentId < 1) {
    customcore_admin_attachment_not_found();
}

try {
    $pdo = customcore_pdo();
    $attachment = customcore_admin_consultation_fetch_attachment($pdo, $attachmentId);
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to retrieve the attachment right now.';
    exit;
}

if ($attachment === null) {
    customcore_admin_attachment_not_found();
}

// Resolve the on-disk path safely (stored name is generated; basename-guarded).
$storedName = basename((string) $attachment['stored_filename']);
$dir = customcore_consultation_upload_dir();
$path = $dir . '/' . $storedName;

if ($storedName === '' || !is_file($path) || !is_readable($path)) {
    customcore_admin_attachment_not_found();
}

// Confirm the resolved path is really inside the upload directory.
$realDir = realpath($dir);
$realPath = realpath($path);
if ($realDir === false || $realPath === false
    || strncmp($realPath, $realDir . DIRECTORY_SEPARATOR, strlen($realDir) + 1) !== 0
) {
    customcore_admin_attachment_not_found();
}

// Build a safe download filename (fallback ASCII + RFC 5987 UTF-8 variant).
$originalName = (string) $attachment['original_filename'];
$asciiFallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
if ($asciiFallback === '' || $asciiFallback === null) {
    $asciiFallback = 'attachment';
}
$encodedName = rawurlencode($originalName);

$mime = (string) $attachment['mime_type'];
if ($mime === '') {
    $mime = 'application/octet-stream';
}

$size = (int) $attachment['file_size'];
$diskSize = filesize($path);
if ($diskSize !== false) {
    $size = (int) $diskSize;
}

// Discard any buffered output so the binary stream is not corrupted.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $asciiFallback . '"; filename*=UTF-8\'\'' . $encodedName);
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Pragma: no-cache');

readfile($path);
exit;
