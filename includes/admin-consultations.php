<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator consultation management helpers.
// Security-first helpers for the admin consultation screens: search/list with status filtering and
// pagination, admin-scope fetch of any request + its attachments, status transitions, and saving
// the administrator's response (which timestamps and auto-advances an open request to "answered").
// Usage: require_once __DIR__. '/admin-consultations.php';
// Security:
//   Every query uses PDO prepared statements.
//   Status writes are validated against the consultation_requests.status ENUM.
//   Attachment fetch returns the generated stored name; the download endpoint basename-guards and
//     confirms the path stays inside the upload directory.

declare(strict_types=1);

if (!function_exists('customcore_consultation_statuses')) {
    require_once __DIR__ . '/consultations.php';
}

/**
 * Maximum length of an administrator response.
 */
const CUSTOMCORE_ADMIN_CONSULT_RESPONSE_MAX = 5000;

/**
 * Build the shared WHERE clause + params for consultation search/count.
 *
 * @param array{search?:string, status?:string} $filters
 * @return array{0:string, 1:array<string,mixed>}
 */
function customcore_admin_consultation_where(array $filters): array
{
    $clauses = ['1 = 1'];
    $params = [];

    $search = isset($filters['search']) && is_string($filters['search']) ? trim($filters['search']) : '';
    if ($search !== '') {
        $clauses[] = "(u.email LIKE :s_email OR CONCAT(u.first_name, ' ', u.last_name) LIKE :s_name "
            . 'OR cr.budget LIKE :s_budget)';
        $like = '%' . $search . '%';
        $params[':s_email'] = $like;
        $params[':s_name'] = $like;
        $params[':s_budget'] = $like;
    }

    $status = isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : '';
    if ($status !== '' && in_array($status, customcore_consultation_statuses(), true)) {
        $clauses[] = 'cr.status = :status';
        $params[':status'] = $status;
    }

    return ['WHERE ' . implode(' AND ', $clauses), $params];
}

/**
 * Search / list consultation requests for the admin table with pagination.
 *
 * Open and in-progress requests sort to the top (oldest-first within that group)
 * so the queue surfaces what needs attention; everything else is newest-first.
 *
 * @param array{search?:string, status?:string} $filters
 * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, per_page:int}
 */
function customcore_admin_consultation_list(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 25): array
{
    $perPage = max(5, min(100, $perPage));

    [$where, $params] = customcore_admin_consultation_where($filters);

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM consultation_requests cr
         INNER JOIN users u ON u.id = cr.user_id
         ' . $where
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    $sql =
        "SELECT cr.id, cr.budget, cr.status, cr.admin_response, cr.responded_at,
                cr.created_at, cr.updated_at,
                u.id AS user_id, u.first_name, u.last_name, u.email,
                (SELECT COUNT(*) FROM consultation_attachments ca
                 WHERE ca.consultation_request_id = cr.id) AS attachment_count
         FROM consultation_requests cr
         INNER JOIN users u ON u.id = cr.user_id
         " . $where . "
         ORDER BY (cr.status IN ('open', 'in_progress')) DESC,
                  CASE WHEN cr.status IN ('open', 'in_progress') THEN cr.created_at END ASC,
                  cr.created_at DESC, cr.id DESC
         LIMIT " . $perPage . ' OFFSET ' . $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [
        'rows' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
    ];
}

/**
 * Fetch a single consultation request with its customer details (admin scope).
 *
 * @return array<string, mixed>|null
 */
function customcore_admin_consultation_fetch(PDO $pdo, int $requestId): ?array
{
    if ($requestId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT cr.*, u.first_name, u.last_name, u.email, u.is_active AS user_active
         FROM consultation_requests cr
         INNER JOIN users u ON u.id = cr.user_id
         WHERE cr.id = :id'
    );
    $stmt->execute([':id' => $requestId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Fetch all attachments for a request (admin scope, no user filter).
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_consultation_attachments(PDO $pdo, int $requestId): array
{
    if ($requestId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, consultation_request_id, original_filename, stored_filename,
                mime_type, file_size, created_at
         FROM consultation_attachments
         WHERE consultation_request_id = :rid
         ORDER BY id ASC'
    );
    $stmt->execute([':rid' => $requestId]);

    return $stmt->fetchAll();
}

/**
 * Fetch a single attachment (admin scope, any owner) for the download endpoint.
 *
 * @return array<string, mixed>|null
 */
function customcore_admin_consultation_fetch_attachment(PDO $pdo, int $attachmentId): ?array
{
    if ($attachmentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, consultation_request_id, original_filename, stored_filename,
                mime_type, file_size
         FROM consultation_attachments
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $attachmentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Update a request's status (validated against the ENUM allow-list).
 *
 * @return bool True when a valid status was applied.
 */
function customcore_admin_consultation_update_status(PDO $pdo, int $requestId, string $status): bool
{
    if (!in_array($status, customcore_consultation_statuses(), true)) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE consultation_requests SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $requestId]);

    return true;
}

/**
 * Save (or clear) the administrator response for a request.
 *
 * Saving a non-empty response stamps responded_at = NOW() and, when the request
 * is still open or in progress, advances it to "answered". Clearing the response
 * (blank) resets admin_response and responded_at to NULL and leaves the status.
 *
 * @param string $currentStatus The request's current status (for auto-advance).
 * @return string The status after the write (may be auto-advanced).
 */
function customcore_admin_consultation_save_response(
    PDO $pdo,
    int $requestId,
    string $response,
    string $currentStatus
): string {
    $response = trim($response);
    if (mb_strlen($response) > CUSTOMCORE_ADMIN_CONSULT_RESPONSE_MAX) {
        $response = mb_substr($response, 0, CUSTOMCORE_ADMIN_CONSULT_RESPONSE_MAX);
    }

    if ($response === '') {
        $stmt = $pdo->prepare(
            'UPDATE consultation_requests
             SET admin_response = NULL, responded_at = NULL
             WHERE id = :id'
        );
        $stmt->execute([':id' => $requestId]);

        return $currentStatus;
    }

    $newStatus = in_array($currentStatus, ['open', 'in_progress'], true) ? 'answered' : $currentStatus;

    $stmt = $pdo->prepare(
        'UPDATE consultation_requests
         SET admin_response = :resp, responded_at = NOW(), status = :status
         WHERE id = :id'
    );
    $stmt->execute([':resp' => $response, ':status' => $newStatus, ':id' => $requestId]);

    return $newStatus;
}

/**
 * Count consultation requests per status for the filter dropdown.
 *
 * @return array<string, int> status => count (plus 'all' and 'needs_attention')
 */
function customcore_admin_consultation_status_counts(PDO $pdo): array
{
    $counts = ['all' => 0, 'needs_attention' => 0];
    foreach (customcore_consultation_statuses() as $s) {
        $counts[$s] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS c FROM consultation_requests GROUP BY status');
    if ($stmt) {
        foreach ($stmt->fetchAll() as $row) {
            $status = (string) $row['status'];
            $c = (int) $row['c'];
            $counts[$status] = $c;
            $counts['all'] += $c;
            if ($status === 'open' || $status === 'in_progress') {
                $counts['needs_attention'] += $c;
            }
        }
    }

    return $counts;
}
