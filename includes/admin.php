<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator helpers.
// Shared dashboard statistics, attention alerts, recent activity lists, and the tool registry used
// by the administrator shell. Every count is computed from MySQL with prepared/parameterless PDO
// queries, never hard-coded decorative numbers.
// Usage: require_once __DIR__. '/admin.php';
//   $stats = customcore_admin_dashboard_stats($pdo);

declare(strict_types=1);

/**
 * Stock quantity at or below this value (but still > 0) counts as low stock.
 */
function customcore_admin_low_stock_threshold(): int
{
    return 5;
}

/**
 * Absolute filesystem path for an admin/*.php tool page.
 */
function customcore_admin_tool_path(string $relativeHref): string
{
    $relativeHref = ltrim(str_replace('\\', '/', $relativeHref), '/');

    return dirname(__DIR__) . '/' . $relativeHref;
}

/**
 * Whether an admin tool PHP file exists on disk (so dashboard links never 404).
 */
function customcore_admin_tool_available(string $relativeHref): bool
{
    $path = customcore_admin_tool_path($relativeHref);

    return is_file($path);
}

/**
 * Administrator tools shown on the dashboard and in the admin nav.
 *
 * `available` is resolved at call time from the filesystem so nav links
 * only appear when the corresponding page file exists on disk.
 *
 * @return list<array{
 * key:string
 * label:string
 * href:string
 * description:string
 * available:bool
 * }>
 */
function customcore_admin_tools(): array
{
    $tools = [
        [
            'key' => 'products',
            'label' => 'Products',
            'href' => 'admin/products.php',
            'description' => 'Add, edit, stock, price, images, and disable catalogue systems.',
        ],
        [
            'key' => 'options',
            'label' => 'Product options',
            'href' => 'admin/product-options.php',
            'description' => 'Manage configurable options and price adjustments per product.',
        ],
        [
            'key' => 'compatibility',
            'label' => 'Compatibility',
            'href' => 'admin/compatibility.php',
            'description' => 'Edit simplified compatibility metadata used by the PC Builder.',
        ],
        [
            'key' => 'orders',
            'label' => 'Orders',
            'href' => 'admin/orders.php',
            'description' => 'Search orders, update status, and add administrator notes.',
        ],
        [
            'key' => 'users',
            'label' => 'Users',
            'href' => 'admin/users.php',
            'description' => 'Search accounts and disable or re-enable customer logins.',
        ],
        [
            'key' => 'consultations',
            'label' => 'Consultations',
            'href' => 'admin/consultations.php',
            'description' => 'Review PC advice requests, respond, and manage status.',
        ],
        [
            'key' => 'reviews',
            'label' => 'Reviews',
            'href' => 'admin/reviews.php',
            'description' => 'Approve, hide, or delete product reviews awaiting moderation.',
        ],
        [
            'key' => 'reports',
            'label' => 'Reports',
            'href' => 'admin/reports.php',
            'description' => 'Charts for orders, catalogue inventory, and user activity.',
        ],
        [
            'key' => 'themes',
            'label' => 'Themes',
            'href' => 'admin/themes.php',
            'description' => 'Choose the active site-wide CSS theme.',
        ],
        [
            'key' => 'monitoring',
            'label' => 'Monitoring',
            'href' => 'admin/monitoring.php',
            'description' => 'Service health checks: online, warning, or offline.',
        ],
    ];

    foreach ($tools as &$tool) {
        $tool['available'] = customcore_admin_tool_available($tool['href']);
    }
    unset($tool);

    return $tools;
}

/**
 * Aggregate catalogue, order, user, review, consultation, and contact counts.
 *
 * @return array{
 * products_total:int
 * products_active:int
 * products_inactive:int
 * products_low_stock:int
 * products_out_of_stock:int
 * low_stock_threshold:int
 * orders_total:int
 * orders_pending:int
 * orders_processing:int
 * orders_ready:int
 * orders_completed:int
 * orders_cancelled:int
 * orders_open:int
 * users_total:int
 * users_customers:int
 * users_admins:int
 * users_active:int
 * users_inactive:int
 * reviews_total:int
 * reviews_pending:int
 * reviews_approved:int
 * reviews_hidden:int
 * consultations_total:int
 * consultations_open:int
 * consultations_in_progress:int
 * consultations_answered:int
 * consultations_closed:int
 * consultations_needs_attention:int
 * contact_total:int
 * contact_unread:int
 * }
 */
function customcore_admin_dashboard_stats(PDO $pdo): array
{
    $threshold = customcore_admin_low_stock_threshold();

    $productStmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS products_total,
            COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS products_active,
            COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS products_inactive,
            COALESCE(SUM(CASE WHEN is_active = 1 AND stock_quantity = 0 THEN 1 ELSE 0 END), 0) AS products_out_of_stock,
            COALESCE(SUM(CASE WHEN is_active = 1 AND stock_quantity > 0 AND stock_quantity <= :threshold THEN 1 ELSE 0 END), 0) AS products_low_stock
         FROM products'
    );
    $productStmt->execute([':threshold' => $threshold]);
    $products = $productStmt->fetch() ?: [];

    $orderStmt = $pdo->query(
        "SELECT
            COUNT(*) AS orders_total,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS orders_pending,
            COALESCE(SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END), 0) AS orders_processing,
            COALESCE(SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END), 0) AS orders_ready,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS orders_completed,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS orders_cancelled
         FROM orders"
    );
    $orders = $orderStmt ? $orderStmt->fetch() : [];

    $userStmt = $pdo->query(
        "SELECT
            COUNT(*) AS users_total,
            COALESCE(SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END), 0) AS users_customers,
            COALESCE(SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END), 0) AS users_admins,
            COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS users_active,
            COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS users_inactive
         FROM users"
    );
    $users = $userStmt ? $userStmt->fetch() : [];

    $reviewStmt = $pdo->query(
        "SELECT
            COUNT(*) AS reviews_total,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS reviews_pending,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS reviews_approved,
            COALESCE(SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END), 0) AS reviews_hidden
         FROM reviews"
    );
    $reviews = $reviewStmt ? $reviewStmt->fetch() : [];

    $consultStmt = $pdo->query(
        "SELECT
            COUNT(*) AS consultations_total,
            COALESCE(SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END), 0) AS consultations_open,
            COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) AS consultations_in_progress,
            COALESCE(SUM(CASE WHEN status = 'answered' THEN 1 ELSE 0 END), 0) AS consultations_answered,
            COALESCE(SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END), 0) AS consultations_closed
         FROM consultation_requests"
    );
    $consultations = $consultStmt ? $consultStmt->fetch() : [];

    $contactStmt = $pdo->query(
        'SELECT
            COUNT(*) AS contact_total,
            COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) AS contact_unread
         FROM contact_messages'
    );
    $contacts = $contactStmt ? $contactStmt->fetch() : [];

    $ordersPending = (int) ($orders['orders_pending'] ?? 0);
    $ordersProcessing = (int) ($orders['orders_processing'] ?? 0);
    $ordersReady = (int) ($orders['orders_ready'] ?? 0);
    $consultOpen = (int) ($consultations['consultations_open'] ?? 0);
    $consultProgress = (int) ($consultations['consultations_in_progress'] ?? 0);

    return [
        'products_total' => (int) ($products['products_total'] ?? 0),
        'products_active' => (int) ($products['products_active'] ?? 0),
        'products_inactive' => (int) ($products['products_inactive'] ?? 0),
        'products_low_stock' => (int) ($products['products_low_stock'] ?? 0),
        'products_out_of_stock' => (int) ($products['products_out_of_stock'] ?? 0),
        'low_stock_threshold' => $threshold,
        'orders_total' => (int) ($orders['orders_total'] ?? 0),
        'orders_pending' => $ordersPending,
        'orders_processing' => $ordersProcessing,
        'orders_ready' => $ordersReady,
        'orders_completed' => (int) ($orders['orders_completed'] ?? 0),
        'orders_cancelled' => (int) ($orders['orders_cancelled'] ?? 0),
        'orders_open' => $ordersPending + $ordersProcessing + $ordersReady,
        'users_total' => (int) ($users['users_total'] ?? 0),
        'users_customers' => (int) ($users['users_customers'] ?? 0),
        'users_admins' => (int) ($users['users_admins'] ?? 0),
        'users_active' => (int) ($users['users_active'] ?? 0),
        'users_inactive' => (int) ($users['users_inactive'] ?? 0),
        'reviews_total' => (int) ($reviews['reviews_total'] ?? 0),
        'reviews_pending' => (int) ($reviews['reviews_pending'] ?? 0),
        'reviews_approved' => (int) ($reviews['reviews_approved'] ?? 0),
        'reviews_hidden' => (int) ($reviews['reviews_hidden'] ?? 0),
        'consultations_total' => (int) ($consultations['consultations_total'] ?? 0),
        'consultations_open' => $consultOpen,
        'consultations_in_progress' => $consultProgress,
        'consultations_answered' => (int) ($consultations['consultations_answered'] ?? 0),
        'consultations_closed' => (int) ($consultations['consultations_closed'] ?? 0),
        'consultations_needs_attention' => $consultOpen + $consultProgress,
        'contact_total' => (int) ($contacts['contact_total'] ?? 0),
        'contact_unread' => (int) ($contacts['contact_unread'] ?? 0),
    ];
}

/**
 * Build attention alerts from live dashboard stats.
 *
 * @param array<string, int> $stats
 * @return list<array{level:string, title:string, detail:string, tool:string}>
 */
function customcore_admin_dashboard_alerts(array $stats): array
{
    $alerts = [];

    if ((int) ($stats['reviews_pending'] ?? 0) > 0) {
        $n = (int) $stats['reviews_pending'];
        $alerts[] = [
            'level' => 'warning',
            'title' => $n === 1 ? '1 review awaiting moderation' : $n . ' reviews awaiting moderation',
            'detail' => 'Pending reviews stay hidden from the public catalogue until approved.',
            'tool' => 'reviews',
        ];
    }

    if ((int) ($stats['consultations_needs_attention'] ?? 0) > 0) {
        $n = (int) $stats['consultations_needs_attention'];
        $alerts[] = [
            'level' => 'warning',
            'title' => $n === 1 ? '1 consultation needs a response' : $n . ' consultations need a response',
            'detail' => 'Open or in-progress PC advice requests are waiting for an administrator.',
            'tool' => 'consultations',
        ];
    }

    if ((int) ($stats['orders_open'] ?? 0) > 0) {
        $n = (int) $stats['orders_open'];
        $alerts[] = [
            'level' => 'info',
            'title' => $n === 1 ? '1 order in progress' : $n . ' orders in progress',
            'detail' => 'Includes pending, processing, and ready-for-pickup orders.',
            'tool' => 'orders',
        ];
    }

    if ((int) ($stats['products_out_of_stock'] ?? 0) > 0) {
        $n = (int) $stats['products_out_of_stock'];
        $alerts[] = [
            'level' => 'warning',
            'title' => $n === 1 ? '1 active product is out of stock' : $n . ' active products are out of stock',
            'detail' => 'Update stock quantities or disable systems that cannot ship.',
            'tool' => 'products',
        ];
    }

    if ((int) ($stats['products_low_stock'] ?? 0) > 0) {
        $n = (int) $stats['products_low_stock'];
        $threshold = (int) ($stats['low_stock_threshold'] ?? customcore_admin_low_stock_threshold());
        $alerts[] = [
            'level' => 'info',
            'title' => $n === 1 ? '1 active product is low on stock' : $n . ' active products are low on stock',
            'detail' => 'Active systems with 1, ' . $threshold . ' units remaining.',
            'tool' => 'products',
        ];
    }

    if ((int) ($stats['contact_unread'] ?? 0) > 0) {
        $n = (int) $stats['contact_unread'];
        $alerts[] = [
            'level' => 'info',
            'title' => $n === 1 ? '1 unread contact message' : $n . ' unread contact messages',
            'detail' => 'Customer and guest messages submitted through the public contact form.',
            'tool' => '',
        ];
    }

    if ((int) ($stats['users_inactive'] ?? 0) > 0) {
        $n = (int) $stats['users_inactive'];
        $alerts[] = [
            'level' => 'info',
            'title' => $n === 1 ? '1 disabled user account' : $n . ' disabled user accounts',
            'detail' => 'Disabled accounts cannot sign in until an administrator re-enables them.',
            'tool' => 'users',
        ];
    }

    return $alerts;
}

/**
 * Most recent orders across all customers (administrator overview).
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_recent_orders(PDO $pdo, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));

    $stmt = $pdo->prepare(
        'SELECT o.id, o.order_number, o.status, o.total, o.created_at,
                u.first_name, u.last_name, u.email
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT ' . $limit
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Reviews waiting for moderation, newest first.
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_pending_reviews(PDO $pdo, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));

    $stmt = $pdo->prepare(
        "SELECT r.id, r.rating, r.title, r.created_at,
                p.name AS product_name, p.slug AS product_slug,
                u.first_name, u.last_name, u.email
         FROM reviews r
         INNER JOIN products p ON p.id = r.product_id
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.status = 'pending'
         ORDER BY r.created_at DESC, r.id DESC
         LIMIT " . $limit
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Consultations that still need administrator attention.
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_open_consultations(PDO $pdo, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));

    $stmt = $pdo->prepare(
        "SELECT cr.id, cr.budget, cr.status, cr.created_at,
                u.first_name, u.last_name, u.email
         FROM consultation_requests cr
         INNER JOIN users u ON u.id = cr.user_id
         WHERE cr.status IN ('open', 'in_progress')
         ORDER BY cr.created_at ASC, cr.id ASC
         LIMIT " . $limit
    );
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * Active catalogue products at or below the low-stock threshold.
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_low_stock_products(PDO $pdo, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    $threshold = customcore_admin_low_stock_threshold();

    $stmt = $pdo->prepare(
        'SELECT id, name, slug, stock_quantity, base_price
         FROM products
         WHERE is_active = 1
           AND stock_quantity > 0
           AND stock_quantity <= :threshold
         ORDER BY stock_quantity ASC, name ASC
         LIMIT ' . $limit
    );
    $stmt->execute([':threshold' => $threshold]);

    return $stmt->fetchAll();
}

/**
 * Resolve a tool href from the registry by key (empty string if unknown).
 */
function customcore_admin_tool_href(string $toolKey): string
{
    foreach (customcore_admin_tools() as $tool) {
        if ($tool['key'] === $toolKey) {
            return $tool['available'] ? $tool['href'] : '';
        }
    }

    return '';
}
