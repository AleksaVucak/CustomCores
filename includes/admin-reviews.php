<?php
/**
 * CustomCore — Administrator review moderation helpers (Commit 9.8).
 *
 * File responsibility:
 *   Security-first helpers for the admin review queue: search/list with status
 *   filtering and pagination, single-review fetch, ENUM-validated status
 *   transitions (approve / hide / restore to pending), and hard delete.
 *
 * Usage:
 *   require_once __DIR__ . '/admin-reviews.php';
 *
 * Security:
 *   - Every query uses PDO prepared statements.
 *   - Status writes are validated against the reviews.status ENUM allow-list.
 *   - Delete is intentional and permanent (moderation tool, not soft-disable).
 *   - Public catalogue pages still only show status = 'approved'.
 */

declare(strict_types=1);

if (!function_exists('customcore_review_statuses')) {
    require_once __DIR__ . '/reviews.php';
}

/**
 * CSS modifier class for a review status badge (e.g. review-status--pending).
 */
function customcore_admin_review_status_class(string $status): string
{
    $known = customcore_review_statuses();

    return in_array($status, $known, true)
        ? 'review-status--' . $status
        : 'review-status--pending';
}

/**
 * Build the shared WHERE clause + params for review search/count.
 *
 * @param array{search?:string, status?:string, product_id?:int} $filters
 * @return array{0:string, 1:array<string,mixed>}
 */
function customcore_admin_review_where(array $filters): array
{
    $clauses = ['1 = 1'];
    $params = [];

    $search = isset($filters['search']) && is_string($filters['search']) ? trim($filters['search']) : '';
    if ($search !== '') {
        $clauses[] = '(r.title LIKE :s_title OR r.body LIKE :s_body OR p.name LIKE :s_product '
            . "OR u.email LIKE :s_email OR CONCAT(u.first_name, ' ', u.last_name) LIKE :s_name)";
        $like = '%' . $search . '%';
        $params[':s_title'] = $like;
        $params[':s_body'] = $like;
        $params[':s_product'] = $like;
        $params[':s_email'] = $like;
        $params[':s_name'] = $like;
    }

    $status = isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : '';
    if ($status !== '' && in_array($status, customcore_review_statuses(), true)) {
        $clauses[] = 'r.status = :status';
        $params[':status'] = $status;
    }

    $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : 0;
    if ($productId > 0) {
        $clauses[] = 'r.product_id = :pid';
        $params[':pid'] = $productId;
    }

    return ['WHERE ' . implode(' AND ', $clauses), $params];
}

/**
 * Search / list reviews for the admin moderation table with pagination.
 *
 * Pending reviews sort to the top (oldest first within that group) so the
 * moderation queue surfaces what needs attention; everything else is newest-first.
 *
 * @param array{search?:string, status?:string, product_id?:int} $filters
 * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, per_page:int}
 */
function customcore_admin_review_list(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 25): array
{
    $perPage = max(5, min(100, $perPage));

    [$where, $params] = customcore_admin_review_where($filters);

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM reviews r
         INNER JOIN products p ON p.id = r.product_id
         INNER JOIN users u ON u.id = r.user_id
         ' . $where
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    $sql =
        "SELECT r.id, r.product_id, r.user_id, r.rating, r.title, r.body, r.status,
                r.created_at, r.updated_at,
                p.name AS product_name, p.slug AS product_slug, p.is_active AS product_active,
                u.first_name, u.last_name, u.email, u.is_active AS user_active
         FROM reviews r
         INNER JOIN products p ON p.id = r.product_id
         INNER JOIN users u ON u.id = r.user_id
         " . $where . "
         ORDER BY (r.status = 'pending') DESC,
                  CASE WHEN r.status = 'pending' THEN r.created_at END ASC,
                  r.created_at DESC, r.id DESC
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
 * Fetch a single review with product + customer details (admin scope).
 *
 * @return array<string, mixed>|null
 */
function customcore_admin_review_fetch(PDO $pdo, int $reviewId): ?array
{
    if ($reviewId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT r.id, r.product_id, r.user_id, r.rating, r.title, r.body, r.status,
                r.created_at, r.updated_at,
                p.name AS product_name, p.slug AS product_slug, p.is_active AS product_active,
                u.first_name, u.last_name, u.email, u.is_active AS user_active
         FROM reviews r
         INNER JOIN products p ON p.id = r.product_id
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $reviewId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Update a review's moderation status (validated against the ENUM allow-list).
 *
 * @return bool True when a valid status was applied.
 */
function customcore_admin_review_set_status(PDO $pdo, int $reviewId, string $status): bool
{
    if (!in_array($status, customcore_review_statuses(), true)) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE reviews SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $reviewId]);

    return true;
}

/**
 * Permanently delete a review (moderation tool — intentional hard delete).
 *
 * @return bool True when a row was removed.
 */
function customcore_admin_review_delete(PDO $pdo, int $reviewId): bool
{
    if ($reviewId < 1) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = :id');
    $stmt->execute([':id' => $reviewId]);

    return $stmt->rowCount() > 0;
}

/**
 * Count reviews per status for the filter dropdown.
 *
 * @return array<string, int> status => count (includes 'all')
 */
function customcore_admin_review_status_counts(PDO $pdo): array
{
    $counts = ['all' => 0];
    foreach (customcore_review_statuses() as $s) {
        $counts[$s] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS c FROM reviews GROUP BY status');
    if ($stmt) {
        foreach ($stmt->fetchAll() as $row) {
            $status = (string) $row['status'];
            $c = (int) $row['c'];
            $counts[$status] = $c;
            $counts['all'] += $c;
        }
    }

    return $counts;
}
