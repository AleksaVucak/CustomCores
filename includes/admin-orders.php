<?php
/**
 * CustomCore — Administrator order management helpers (Commit 9.5).
 *
 * File responsibility:
 *   Security-first helpers for the admin order screens: search/list with
 *   status filtering and pagination, full order + line-item fetch (admin scope,
 *   any owner), status transitions, and administrator notes.
 *
 * Usage:
 *   require_once __DIR__ . '/admin-orders.php';
 *
 * Security:
 *   - Every query uses PDO prepared statements.
 *   - Status writes are validated against the orders.status ENUM allow-list.
 *   - Item rows are always scoped by order_id.
 */

declare(strict_types=1);

if (!function_exists('customcore_order_statuses')) {
    require_once __DIR__ . '/orders.php';
}

/**
 * Search / list orders for the admin table with pagination.
 *
 * @param array{search?:string, status?:string} $filters
 * @param int $page    1-based page number.
 * @param int $perPage Rows per page (clamped).
 * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, per_page:int}
 */
function customcore_admin_order_list(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 25): array
{
    $perPage = max(5, min(100, $perPage));

    [$where, $params] = customcore_admin_order_where($filters);

    // Total count for pagination.
    $countStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         ' . $where
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    $sql =
        'SELECT o.id, o.order_number, o.status, o.subtotal, o.total,
                o.payment_method, o.created_at, o.updated_at,
                u.id AS user_id, u.first_name, u.last_name, u.email,
                (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         ' . $where . '
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT ' . $perPage . ' OFFSET ' . $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return [
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
    ];
}

/**
 * Build the shared WHERE clause + params for order search/count.
 *
 * @param array{search?:string, status?:string} $filters
 * @return array{0:string, 1:array<string,mixed>}
 */
function customcore_admin_order_where(array $filters): array
{
    $clauses = ['1 = 1'];
    $params = [];

    $search = isset($filters['search']) && is_string($filters['search']) ? trim($filters['search']) : '';
    if ($search !== '') {
        $clauses[] = '(o.order_number LIKE :s_num OR u.email LIKE :s_email '
            . "OR CONCAT(u.first_name, ' ', u.last_name) LIKE :s_name)";
        $like = '%' . $search . '%';
        $params[':s_num'] = $like;
        $params[':s_email'] = $like;
        $params[':s_name'] = $like;
    }

    $status = isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : '';
    if ($status !== '' && in_array($status, customcore_order_statuses(), true)) {
        $clauses[] = 'o.status = :status';
        $params[':status'] = $status;
    }

    return ['WHERE ' . implode(' AND ', $clauses), $params];
}

/**
 * Fetch a single order with its customer details (admin scope — any owner).
 *
 * @return array<string, mixed>|null
 */
function customcore_admin_order_fetch(PDO $pdo, int $orderId): ?array
{
    if ($orderId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT o.*, u.first_name, u.last_name, u.email, u.is_active AS user_active
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         WHERE o.id = :id'
    );
    $stmt->execute([':id' => $orderId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Fetch all line items for an order (admin scope, ordered).
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_order_items(PDO $pdo, int $orderId): array
{
    if ($orderId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, item_type, product_id, saved_build_id, item_name, quantity,
                unit_price, line_total, options_json, build_snapshot_json, created_at
         FROM order_items
         WHERE order_id = :oid
         ORDER BY id ASC'
    );
    $stmt->execute([':oid' => $orderId]);

    return $stmt->fetchAll();
}

/**
 * Update an order's status (validated against the ENUM allow-list).
 *
 * @return bool True when a valid status was applied.
 */
function customcore_admin_order_update_status(PDO $pdo, int $orderId, string $status): bool
{
    if (!in_array($status, customcore_order_statuses(), true)) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $orderId]);

    return true;
}

/**
 * Update an order's administrator notes (stored NULL when blank).
 */
function customcore_admin_order_update_notes(PDO $pdo, int $orderId, string $notes): void
{
    $notes = trim($notes);
    if (mb_strlen($notes) > 5000) {
        $notes = mb_substr($notes, 0, 5000);
    }

    $stmt = $pdo->prepare('UPDATE orders SET admin_notes = :notes WHERE id = :id');
    $stmt->execute([
        ':notes' => $notes === '' ? null : $notes,
        ':id' => $orderId,
    ]);
}

/**
 * Count orders per status for the filter summary chips.
 *
 * @return array<string, int> status => count (includes a 'all' key)
 */
function customcore_admin_order_status_counts(PDO $pdo): array
{
    $counts = ['all' => 0];
    foreach (customcore_order_statuses() as $s) {
        $counts[$s] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status');
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
