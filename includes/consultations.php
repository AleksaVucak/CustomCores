<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Consultation helper functions.
// Shared logic for PC consultation requests: allowed status values and labels/classes, budget
// option list, server-side validation of the request form, and insertion of a new request (status
// = open). Attachments arrive in
// Access: Creation expects a logged-in user (consultation_requests.user_id FK). Callers must
// enforce login before customcore_consultation_create().

declare(strict_types=1);

/**
 * Allowed consultation status values (matches consultation_requests.status ENUM).
 *
 * @return list<string>
 */
function customcore_consultation_statuses(): array
{
    return ['open', 'in_progress', 'answered', 'closed'];
}

/**
 * Human-readable label for a consultation status.
 */
function customcore_consultation_status_label(string $status): string
{
    $labels = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'answered' => 'Answered',
        'closed' => 'Closed',
    ];

    return $labels[$status] ?? $status;
}

/**
 * CSS modifier class for a consultation status badge.
 */
function customcore_consultation_status_class(string $status): string
{
    $known = customcore_consultation_statuses();

    return in_array($status, $known, true)
        ? 'consult-status--' . $status
        : 'consult-status--open';
}

/**
 * Selectable budget ranges for the request form. Stored as a label string.
 *
 * @return list<string>
 */
function customcore_consultation_budget_options(): array
{
    return [
        'Under $1,000',
        '$1,000, $1,500',
        '$1,500, $2,000',
        '$2,000, $3,000',
        '$3,000, $4,000',
        '$4,000+',
        'Not sure yet',
    ];
}

/**
 * Validate consultation request form fields.
 *
 * Required: budget (from the allowed list), games, software, performance_goals.
 * Optional: notes.
 *
 * @param array<string, mixed> $input Raw form values.
 * @return array{
 * ok: bool
 * errors: array<string, string>
 * values: array{budget: string, games: string, software: string, performance_goals: string, notes: string}
 * }
 */
function customcore_consultation_validate(array $input): array
{
    $errors = [];

    $budget = isset($input['budget']) && is_string($input['budget']) ? trim($input['budget']) : '';
    if ($budget === '') {
        $errors['budget'] = 'Please select an approximate budget.';
    } elseif (!in_array($budget, customcore_consultation_budget_options(), true)) {
        $errors['budget'] = 'Please choose a budget from the list.';
        $budget = '';
    }

    $textFields = [
        'games' => [
            'label' => 'the games you play',
            'min' => 3,
            'max' => 2000,
        ],
        'software' => [
            'label' => 'the software you use',
            'min' => 2,
            'max' => 2000,
        ],
        'performance_goals' => [
            'label' => 'your performance goals',
            'min' => 3,
            'max' => 2000,
        ],
    ];

    $values = [
        'budget' => $budget,
        'games' => '',
        'software' => '',
        'performance_goals' => '',
        'notes' => '',
    ];

    foreach ($textFields as $field => $rules) {
        $value = isset($input[$field]) && is_string($input[$field]) ? trim($input[$field]) : '';

        if ($value === '') {
            $errors[$field] = 'Please describe ' . $rules['label'] . '.';
        } elseif (mb_strlen($value) < $rules['min']) {
            $errors[$field] = 'Please provide a little more detail about ' . $rules['label'] . '.';
        } elseif (mb_strlen($value) > $rules['max']) {
            $errors[$field] = 'Please keep this under ' . number_format($rules['max']) . ' characters.';
            $value = mb_substr($value, 0, $rules['max']);
        }

        $values[$field] = $value;
    }

    $notes = isset($input['notes']) && is_string($input['notes']) ? trim($input['notes']) : '';
    if (mb_strlen($notes) > 2000) {
        $errors['notes'] = 'Please keep notes under 2,000 characters.';
        $notes = mb_substr($notes, 0, 2000);
    }
    $values['notes'] = $notes;

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'values' => $values,
    ];
}

/**
 * Maximum number of attachments accepted per consultation request.
 */
const CUSTOMCORE_CONSULTATION_MAX_FILES = 5;

/**
 * Canonical accepted attachment types.
 *
 * Maps a canonical file extension to the list of MIME types (as reported by
 * finfo) that are acceptable for it. This is the authoritative allowlist
 * user-supplied extensions and browser-declared MIME types are never trusted.
 *
 * @return array<string, list<string>>
 */
function customcore_consultation_upload_types(): array
{
    return [
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];
}

/**
 * Absolute filesystem path to the consultation upload directory.
 */
function customcore_consultation_upload_dir(): string
{
    $app = customcore_app_config();
    $relative = 'uploads/consultation';
    if (isset($app['paths']['uploads_consultation']) && is_string($app['paths']['uploads_consultation'])) {
        $relative = trim($app['paths']['uploads_consultation'], '/');
    }

    return dirname(__DIR__) . '/' . $relative;
}

/**
 * Maximum accepted upload size in bytes (from app config).
 */
function customcore_consultation_upload_max_bytes(): int
{
    $app = customcore_app_config();
    $max = (int) ($app['upload_max_bytes'] ?? (2 * 1024 * 1024));

    return $max > 0 ? $max : (2 * 1024 * 1024);
}

/**
 * Normalize PHP's $_FILES structure for a multiple-file input into a simple
 * list of per-file arrays, skipping empty slots (UPLOAD_ERR_NO_FILE).
 *
 * @param mixed $field The $_FILES['attachments'] entry (may be missing).
 * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
 */
function customcore_consultation_normalize_files($field): array
{
    if (!is_array($field) || !isset($field['name'])) {
        return [];
    }

    $files = [];

    // Multiple-file input: each key is an array.
    if (is_array($field['name'])) {
        $count = count($field['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = isset($field['error'][$i]) ? (int) $field['error'][$i] : UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name' => (string) ($field['name'][$i] ?? ''),
                'type' => (string) ($field['type'][$i] ?? ''),
                'tmp_name' => (string) ($field['tmp_name'][$i] ?? ''),
                'error' => $error,
                'size' => (int) ($field['size'][$i] ?? 0),
            ];
        }

        return $files;
    }

    // Single-file input fallback.
    $error = (int) ($field['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return [];
    }

    return [[
        'name' => (string) ($field['name'] ?? ''),
        'type' => (string) ($field['type'] ?? ''),
        'tmp_name' => (string) ($field['tmp_name'] ?? ''),
        'error' => $error,
        'size' => (int) ($field['size'] ?? 0),
    ]];
}

/**
 * Sanitize a user-supplied filename for safe display/storage as the original
 * name. Strips any path components and control characters, collapses spaces
 * and clamps length. The result is display-only; the on-disk name is generated.
 */
function customcore_consultation_clean_original_name(string $name): string
{
    // Drop any directory portion a browser might send.
    $name = basename(str_replace('\\', '/', $name));
    // Remove control characters.
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $name = trim($name);

    if ($name === '') {
        $name = 'attachment';
    }

    if (mb_strlen($name) > 255) {
        $name = mb_substr($name, 0, 255);
    }

    return $name;
}

/**
 * Translate a PHP upload error code into a human-readable message.
 */
function customcore_consultation_upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'is larger than the allowed size';
        case UPLOAD_ERR_PARTIAL:
            return 'was only partially uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'could not be processed by the server';
        default:
            return 'could not be uploaded';
    }
}

/**
 * Validate a normalized list of uploaded files against type, size, and count
 * rules. Detects the real MIME type via finfo and derives a trusted extension.
 *
 * @param list<array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
 * @return array{
 * ok: bool
 * errors: list<string>
 * valid: list<array{original_name: string, tmp_name: string, mime_type: string, extension: string, size: int}>
 * }
 */
function customcore_consultation_validate_files(array $files): array
{
    $errors = [];
    $valid = [];

    if ($files === []) {
        return ['ok' => true, 'errors' => [], 'valid' => []];
    }

    if (count($files) > CUSTOMCORE_CONSULTATION_MAX_FILES) {
        $errors[] = 'You can attach at most '
            . CUSTOMCORE_CONSULTATION_MAX_FILES . ' files.';
        return ['ok' => false, 'errors' => $errors, 'valid' => []];
    }

    $allowedTypes = customcore_consultation_upload_types();
    $maxBytes = customcore_consultation_upload_max_bytes();
    $maxLabel = number_format($maxBytes / (1024 * 1024), 1) . ' MB';

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

    foreach ($files as $file) {
        $display = customcore_consultation_clean_original_name($file['name']);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = '“' . $display . '” '
                . customcore_consultation_upload_error_message((int) $file['error']) . '.';
            continue;
        }

        if ($file['tmp_name'] === '' || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = '“' . $display . '” could not be verified as an uploaded file.';
            continue;
        }

        if ($file['size'] <= 0) {
            $errors[] = '“' . $display . '” is empty.';
            continue;
        }

        if ($file['size'] > $maxBytes) {
            $errors[] = '“' . $display . '” exceeds the ' . $maxLabel . ' size limit.';
            continue;
        }

        // Detect the real MIME type from file contents, never trust the browser.
        $detectedMime = '';
        if ($finfo !== false) {
            $detectedMime = (string) finfo_file($finfo, $file['tmp_name']);
        }

        // Match the detected MIME to a canonical extension in the allowlist.
        $matchedExt = null;
        foreach ($allowedTypes as $ext => $mimes) {
            if (in_array($detectedMime, $mimes, true)) {
                $matchedExt = $ext;
                break;
            }
        }

        if ($matchedExt === null) {
            $errors[] = '“' . $display
                . '” is not an accepted file type (allowed: PDF, TXT, PNG, JPG, WEBP).';
            continue;
        }

        $valid[] = [
            'original_name' => $display,
            'tmp_name' => $file['tmp_name'],
            'mime_type' => $detectedMime,
            'extension' => $matchedExt,
            'size' => (int) $file['size'],
        ];
    }

    if ($finfo !== false) {
        finfo_close($finfo);
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'valid' => $valid,
    ];
}

/**
 * Generate a random, collision-resistant on-disk filename for an attachment.
 */
function customcore_consultation_generate_stored_name(string $extension): string
{
    return bin2hex(random_bytes(16)) . '.' . $extension;
}

/**
 * Move validated uploaded files into the consultation upload directory and
 * insert a consultation_attachments row for each.
 *
 * Intended to run inside the same transaction as the request insert. On any
 * failure this throws; the caller rolls back the transaction and calls
 * customcore_consultation_cleanup_files() with the returned/collected paths.
 *
 * @param list<array{original_name: string, tmp_name: string, mime_type: string, extension: string, size: int}> $validFiles
 * @param list<string> $movedPaths Populated by reference with absolute paths of files moved to disk.
 * @return int Number of attachments stored.
 */
function customcore_consultation_store_files(
    PDO $pdo,
    int $requestId,
    array $validFiles,
    array &$movedPaths
): int {
    if ($validFiles === []) {
        return 0;
    }

    $dir = customcore_consultation_upload_dir();
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Upload directory is unavailable.');
        }
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('Upload directory is not writable.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO consultation_attachments
            (consultation_request_id, original_filename, stored_filename, mime_type, file_size)
         VALUES
            (:rid, :original, :stored, :mime, :size)'
    );

    $stored = 0;

    foreach ($validFiles as $file) {
        $storedName = customcore_consultation_generate_stored_name($file['extension']);
        $target = $dir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Failed to store an uploaded file.');
        }
        // Restrict permissions on the stored file.
        @chmod($target, 0644);
        $movedPaths[] = $target;

        $insert->execute([
            ':rid' => $requestId,
            ':original' => $file['original_name'],
            ':stored' => $storedName,
            ':mime' => $file['mime_type'],
            ':size' => $file['size'],
        ]);

        $stored++;
    }

    return $stored;
}

/**
 * Delete files that were moved to disk during a failed submission (best effort).
 *
 * @param list<string> $paths Absolute paths.
 */
function customcore_consultation_cleanup_files(array $paths): void
{
    foreach ($paths as $path) {
        if (is_string($path) && $path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * Fetch attachments for a consultation request (ownership enforced via JOIN).
 *
 * @return list<array<string, mixed>>
 */
function customcore_consultation_attachments(PDO $pdo, int $requestId, int $userId): array
{
    if ($requestId < 1 || $userId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT ca.id, ca.original_filename, ca.stored_filename, ca.mime_type, ca.file_size, ca.created_at
         FROM consultation_attachments ca
         JOIN consultation_requests cr ON cr.id = ca.consultation_request_id
         WHERE ca.consultation_request_id = :rid AND cr.user_id = :uid
         ORDER BY ca.id ASC'
    );
    $stmt->execute([':rid' => $requestId, ':uid' => $userId]);
    $rows = $stmt->fetchAll();

    return is_array($rows) ? $rows : [];
}

/**
 * Format a consultation datetime for display.
 */
function customcore_consultation_format_datetime(string $datetime, string $format = 'M j, Y g:i A'): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }

    return date($format, $ts);
}

/**
 * List all consultation requests for a user, newest first, with an attachment
 * count. Ownership is enforced by WHERE user_id =:uid.
 *
 * @return list<array<string, mixed>>
 */
function customcore_consultation_list(PDO $pdo, int $userId): array
{
    if ($userId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT cr.id, cr.budget, cr.games, cr.software, cr.performance_goals,
                cr.notes, cr.status, cr.admin_response, cr.responded_at,
                cr.created_at, cr.updated_at,
                (SELECT COUNT(*) FROM consultation_attachments ca
                 WHERE ca.consultation_request_id = cr.id) AS attachment_count
         FROM consultation_requests cr
         WHERE cr.user_id = :uid
         ORDER BY cr.created_at DESC, cr.id DESC'
    );
    $stmt->execute([':uid' => $userId]);
    $rows = $stmt->fetchAll();

    return is_array($rows) ? $rows : [];
}

/**
 * Fetch a single consultation request owned by the user, or null.
 *
 * Ownership is enforced by WHERE id =:id AND user_id =:uid, so a foreign
 * request ID is indistinguishable from a non-existent one (no enumeration).
 *
 * @return array<string, mixed>|null
 */
function customcore_consultation_fetch_owned(PDO $pdo, int $requestId, int $userId): ?array
{
    if ($requestId < 1 || $userId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, user_id, budget, games, software, performance_goals,
                notes, status, admin_response, responded_at, created_at, updated_at
         FROM consultation_requests
         WHERE id = :id AND user_id = :uid
         LIMIT 1'
    );
    $stmt->execute([':id' => $requestId, ':uid' => $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Fetch a single attachment row owned by the user (ownership via JOIN), or null.
 *
 * @return array<string, mixed>|null
 */
function customcore_consultation_fetch_attachment(PDO $pdo, int $attachmentId, int $userId): ?array
{
    if ($attachmentId < 1 || $userId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ca.id, ca.consultation_request_id, ca.original_filename,
                ca.stored_filename, ca.mime_type, ca.file_size
         FROM consultation_attachments ca
         JOIN consultation_requests cr ON cr.id = ca.consultation_request_id
         WHERE ca.id = :id AND cr.user_id = :uid
         LIMIT 1'
    );
    $stmt->execute([':id' => $attachmentId, ':uid' => $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Human-readable file size (e.g. "1.2 MB", "834 KB").
 */
function customcore_consultation_format_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }

    return number_format($bytes / (1024 * 1024), 1) . ' MB';
}

/**
 * Insert a new consultation request (status = open).
 *
 * @param array{budget: string, games: string, software: string, performance_goals: string, notes: string} $values
 * @return int The new consultation request ID.
 */
function customcore_consultation_create(PDO $pdo, int $userId, array $values): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO consultation_requests
            (user_id, budget, games, software, performance_goals, notes, status)
         VALUES
            (:uid, :budget, :games, :software, :goals, :notes, :status)'
    );
    $stmt->execute([
        ':uid' => $userId,
        ':budget' => $values['budget'],
        ':games' => $values['games'],
        ':software' => $values['software'],
        ':goals' => $values['performance_goals'],
        ':notes' => ($values['notes'] === '' ? null : $values['notes']),
        ':status' => 'open',
    ]);

    return (int) $pdo->lastInsertId();
}
