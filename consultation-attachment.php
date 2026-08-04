<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Secure consultation attachment download.
// Streams a consultation attachment to its owner. The uploads directory is guarded against direct
// browsing (uploads/consultation/index.php returns 403); this endpoint is the only way a customer
// reaches their own files.
// URL: consultation-attachment.php?id=N
// Access: Logged-in customer. The attachment must belong to a consultation request owned by the
// session user, or the response is 404 (no enumeration).
// Security:
//   Ownership enforced via JOIN to consultation_requests.user_id.
//   On-disk name is the generated stored_filename (basename-guarded).
//   Sent as an attachment with X-Content-Type-Options: nosniff.
//   Filename header is sanitized (RFC 5987) to prevent header injection.

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/consultations.php';

customcore_require_login();

$userId = customcore_current_user_id();

/**
 * Send a 404 and stop. Kept generic so a foreign attachment ID is
 * indistinguishable from a non-existent one.
 */
function customcore_attachment_not_found(): void
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
    customcore_attachment_not_found();
}

try {
    $pdo = customcore_pdo();
    $attachment = customcore_consultation_fetch_attachment($pdo, $attachmentId, $userId);
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Unable to retrieve the attachment right now.';
    exit;
}

if ($attachment === null) {
    customcore_attachment_not_found();
}

// Resolve the on-disk path safely (stored name is generated; basename-guarded).
$storedName = basename((string) $attachment['stored_filename']);
$dir = customcore_consultation_upload_dir();
$path = $dir . '/' . $storedName;

if ($storedName === '' || !is_file($path) || !is_readable($path)) {
    customcore_attachment_not_found();
}

// Confirm the resolved path is really inside the upload directory.
$realDir = realpath($dir);
$realPath = realpath($path);
if ($realDir === false || $realPath === false || strncmp($realPath, $realDir . DIRECTORY_SEPARATOR, strlen($realDir) + 1) !== 0) {
    customcore_attachment_not_found();
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
