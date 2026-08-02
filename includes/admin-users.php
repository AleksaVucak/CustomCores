<?php
/**
 * CustomCore — Administrator user management helpers (Commit 9.6).
 *
 * File responsibility:
 *   Security-first helpers for the admin user screens: search/list with role +
 *   status filtering and pagination, single-account fetch (never the password
 *   hash), per-account activity counts, enable/disable, and role changes — with
 *   the invariants that protect the site from being locked out of its own admin.
 *
 * Usage:
 *   require_once __DIR__ . '/admin-users.php';
 *
 * Security:
 *   - Every query uses PDO prepared statements.
 *   - Role writes are validated against the users.role ENUM allow-list.
 *   - password_hash is never selected into admin views.
 *   - Callers must apply customcore_admin_user_guard() before a status/role
 *     write so an admin cannot disable/demote themselves or the last active admin.
 */

declare(strict_types=1);

if (!function_exists('customcore_pdo')) {
    require_once __DIR__ . '/database.php';
}

/**
 * Valid account roles (matches users.role ENUM).
 *
 * @return list<string>
 */
function customcore_admin_user_roles(): array
{
    return ['customer', 'admin'];
}

/**
 * Human-readable role label.
 */
function customcore_admin_user_role_label(string $role): string
{
    $labels = ['customer' => 'Customer', 'admin' => 'Administrator'];

    return $labels[$role] ?? ucfirst($role);
}

/**
 * Build the shared WHERE clause + params for user search/count.
 *
 * @param array{search?:string, role?:string, status?:string} $filters
 * @return array{0:string, 1:array<string,mixed>}
 */
function customcore_admin_user_where(array $filters): array
{
    $clauses = ['1 = 1'];
    $params = [];

    $search = isset($filters['search']) && is_string($filters['search']) ? trim($filters['search']) : '';
    if ($search !== '') {
        $clauses[] = "(u.email LIKE :s_email OR CONCAT(u.first_name, ' ', u.last_name) LIKE :s_name)";
        $like = '%' . $search . '%';
        $params[':s_email'] = $like;
        $params[':s_name'] = $like;
    }

    $role = isset($filters['role']) && is_string($filters['role']) ? $filters['role'] : '';
    if ($role !== '' && in_array($role, customcore_admin_user_roles(), true)) {
        $clauses[] = 'u.role = :role';
        $params[':role'] = $role;
    }

    $status = isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : '';
    if ($status === 'active') {
        $clauses[] = 'u.is_active = 1';
    } elseif ($status === 'inactive') {
        $clauses[] = 'u.is_active = 0';
    }

    return ['WHERE ' . implode(' AND ', $clauses), $params];
}

/**
 * Search / list user accounts for the admin table with pagination.
 *
 * @param array{search?:string, role?:string, status?:string} $filters
 * @return array{rows:list<array<string,mixed>>, total:int, page:int, pages:int, per_page:int}
 */
function customcore_admin_user_list(PDO $pdo, array $filters = [], int $page = 1, int $perPage = 25): array
{
    $perPage = max(5, min(100, $perPage));

    [$where, $params] = customcore_admin_user_where($filters);

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM users u ' . $where);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;

    $sql =
        'SELECT u.id, u.email, u.first_name, u.last_name, u.role, u.is_active, u.created_at,
                (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count
         FROM users u
         ' . $where . '
         ORDER BY u.created_at DESC, u.id DESC
         LIMIT ' . $perPage . ' OFFSET ' . $offset;

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
 * Fetch a single account for the admin detail screen (never the password hash).
 *
 * @return array<string, mixed>|null
 */
function customcore_admin_user_fetch(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, email, first_name, last_name, phone, address_line1, address_line2,
                city, province, postal_code, role, is_active, created_at, updated_at
         FROM users
         WHERE id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Per-account activity counts + lifetime spend for the detail screen.
 *
 * @return array{orders:int, orders_total:float, reviews:int, consultations:int, wishlist:int}
 */
function customcore_admin_user_activity(PDO $pdo, int $userId): array
{
    $activity = [
        'orders' => 0,
        'orders_total' => 0.0,
        'reviews' => 0,
        'consultations' => 0,
        'wishlist' => 0,
    ];
    if ($userId < 1) {
        return $activity;
    }

    $orderStmt = $pdo->prepare(
        'SELECT COUNT(*) AS c, COALESCE(SUM(total), 0) AS t FROM orders WHERE user_id = :id'
    );
    $orderStmt->execute([':id' => $userId]);
    $orderRow = $orderStmt->fetch() ?: ['c' => 0, 't' => 0];
    $activity['orders'] = (int) $orderRow['c'];
    $activity['orders_total'] = (float) $orderRow['t'];

    // Optional tables — guarded so a lean install still renders.
    $activity['reviews'] = customcore_admin_user_count_table($pdo, 'reviews', $userId);
    $activity['consultations'] = customcore_admin_user_count_table($pdo, 'consultation_requests', $userId);

    // Wishlist items are scoped through the owning wishlist (no user_id column).
    try {
        $wStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM wishlist_items wi
             INNER JOIN wishlists w ON w.id = wi.wishlist_id
             WHERE w.user_id = :id'
        );
        $wStmt->execute([':id' => $userId]);
        $activity['wishlist'] = (int) $wStmt->fetchColumn();
    } catch (Throwable $e) {
        $activity['wishlist'] = 0;
    }

    return $activity;
}

/**
 * COUNT(*) for a user-owned table, returning 0 if the table is absent.
 */
function customcore_admin_user_count_table(PDO $pdo, string $table, int $userId): int
{
    // Table name is a fixed internal literal — never user input.
    if (!preg_match('/^[a-z_]+$/', $table)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * The most recent orders for one account (admin scope).
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_user_recent_orders(PDO $pdo, int $userId, int $limit = 5): array
{
    if ($userId < 1) {
        return [];
    }
    $limit = max(1, min(20, $limit));

    $stmt = $pdo->prepare(
        'SELECT id, order_number, status, total, created_at
         FROM orders
         WHERE user_id = :id
         ORDER BY created_at DESC, id DESC
         LIMIT ' . $limit
    );
    $stmt->execute([':id' => $userId]);

    return $stmt->fetchAll();
}

/**
 * Count active administrators (used to protect the last admin).
 */
function customcore_admin_active_admin_count(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");

    return $stmt ? (int) $stmt->fetchColumn() : 0;
}

/**
 * Role + status counts for the filter dropdowns.
 *
 * @return array{total:int, customers:int, admins:int, active:int, inactive:int}
 */
function customcore_admin_user_counts(PDO $pdo): array
{
    $counts = ['total' => 0, 'customers' => 0, 'admins' => 0, 'active' => 0, 'inactive' => 0];

    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(role = 'customer'), 0) AS customers,
            COALESCE(SUM(role = 'admin'), 0) AS admins,
            COALESCE(SUM(is_active = 1), 0) AS active,
            COALESCE(SUM(is_active = 0), 0) AS inactive
         FROM users"
    );
    if ($stmt) {
        $row = $stmt->fetch() ?: [];
        foreach ($counts as $k => $_) {
            $counts[$k] = (int) ($row[$k] ?? 0);
        }
    }

    return $counts;
}

/**
 * Decide whether a proposed status/role change is allowed.
 *
 * Invariants protected here:
 *   - An admin can never change their OWN status or role (no self-lockout).
 *   - The LAST active administrator can never be disabled or demoted.
 *
 * @param string $change  'deactivate' | 'demote'
 * @return array{0:bool, 1:string} [allowed, reason-if-blocked]
 */
function customcore_admin_user_guard(PDO $pdo, array $target, int $currentAdminId, string $change): array
{
    $targetId = (int) ($target['id'] ?? 0);

    if ($targetId === $currentAdminId) {
        return [false, 'You cannot change your own account status or role.'];
    }

    $targetIsActiveAdmin = ((string) ($target['role'] ?? '') === 'admin') && ((int) ($target['is_active'] ?? 0) === 1);
    if ($targetIsActiveAdmin && in_array($change, ['deactivate', 'demote'], true)) {
        if (customcore_admin_active_admin_count($pdo) <= 1) {
            return [false, 'This is the last active administrator — promote or enable another admin first.'];
        }
    }

    return [true, ''];
}

/**
 * Enable or disable an account.
 */
function customcore_admin_user_set_active(PDO $pdo, int $userId, bool $active): void
{
    $stmt = $pdo->prepare('UPDATE users SET is_active = :a WHERE id = :id');
    $stmt->execute([':a' => $active ? 1 : 0, ':id' => $userId]);
}

/**
 * Change an account's role (validated against the ENUM allow-list).
 *
 * @return bool True when a valid role was applied.
 */
function customcore_admin_user_set_role(PDO $pdo, int $userId, string $role): bool
{
    if (!in_array($role, customcore_admin_user_roles(), true)) {
        return false;
    }

    $stmt = $pdo->prepare('UPDATE users SET role = :r WHERE id = :id');
    $stmt->execute([':r' => $role, ':id' => $userId]);

    return true;
}
