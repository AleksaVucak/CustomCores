<?php
/**
 * CustomCore — Administrator reports helpers (Commit 9.9).
 *
 * File responsibility:
 *   Live MySQL aggregates and Chart.js payloads for the administrator reports
 *   page: orders by status, products by performance tier, user accounts by
 *   role/status, and inventory health. Every number comes from the database —
 *   never hard-coded decorative figures — and the same figures power both the
 *   charts and the accessible server-rendered tables.
 *
 * Usage:
 *   require_once __DIR__ . '/admin-reports.php';
 *   $report = customcore_admin_report_bundle($pdo);
 */

declare(strict_types=1);

if (!function_exists('customcore_admin_low_stock_threshold')) {
    require_once __DIR__ . '/admin.php';
}

if (!function_exists('customcore_order_statuses')) {
    require_once __DIR__ . '/orders.php';
}

if (!function_exists('customcore_catalogue_tier_colour')) {
    require_once __DIR__ . '/catalogue-stats.php';
}

/**
 * Shared colour palette for admin report charts (brand-aligned, not purple glow).
 *
 * @return array<string, array{fill:string, border:string}>
 */
function customcore_admin_report_colours(): array
{
    return [
        'pending' => ['fill' => '#c9a227', 'border' => '#a6851f'],
        'processing' => ['fill' => '#5b6b7a', 'border' => '#485865'],
        'ready' => ['fill' => '#0f7a6e', 'border' => '#0b5f56'],
        'completed' => ['fill' => '#15202b', 'border' => '#0f1820'],
        'cancelled' => ['fill' => '#9aa8b5', 'border' => '#7f8f9d'],
        'customer' => ['fill' => '#0f7a6e', 'border' => '#0b5f56'],
        'admin' => ['fill' => '#15202b', 'border' => '#0f1820'],
        'active' => ['fill' => '#0f7a6e', 'border' => '#0b5f56'],
        'inactive' => ['fill' => '#9aa8b5', 'border' => '#7f8f9d'],
        'healthy' => ['fill' => '#0f7a6e', 'border' => '#0b5f56'],
        'low' => ['fill' => '#c9a227', 'border' => '#a6851f'],
        'out' => ['fill' => '#a33b2b', 'border' => '#7a2a1f'],
        'disabled' => ['fill' => '#9aa8b5', 'border' => '#7f8f9d'],
    ];
}

/**
 * Build a Chart.js payload from parallel label/value/colour arrays.
 *
 * @param list<string> $labels
 * @param list<int|float> $values
 * @param list<string> $fills
 * @param list<string> $borders
 * @return array{labels:list<string>, datasets:list<array<string,mixed>>}
 */
function customcore_admin_report_chart_payload(
    array $labels,
    array $values,
    array $fills,
    array $borders,
    string $datasetLabel
): array {
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => $datasetLabel,
                'data' => $values,
                'backgroundColor' => $fills,
                'borderColor' => $borders,
                'borderWidth' => 1,
            ],
        ],
    ];
}

/**
 * Orders grouped by fulfilment status, plus lifetime revenue totals.
 *
 * @return array{
 *   rows:list<array{status:string, label:string, count:int, fill:string, border:string}>,
 *   total:int,
 *   revenue:float,
 *   chart:array{labels:list<string>, datasets:list<array<string,mixed>>}
 * }
 */
function customcore_admin_report_orders(PDO $pdo): array
{
    $colours = customcore_admin_report_colours();
    $counts = [];
    foreach (customcore_order_statuses() as $s) {
        $counts[$s] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status');
    if ($stmt) {
        foreach ($stmt->fetchAll() as $row) {
            $status = (string) $row['status'];
            if (isset($counts[$status])) {
                $counts[$status] = (int) $row['c'];
            }
        }
    }

    $revenueStmt = $pdo->query(
        "SELECT COALESCE(SUM(total), 0) AS revenue
         FROM orders
         WHERE status <> 'cancelled'"
    );
    $revenue = $revenueStmt ? (float) $revenueStmt->fetchColumn() : 0.0;

    $rows = [];
    $labels = [];
    $values = [];
    $fills = [];
    $borders = [];
    $total = 0;

    foreach (customcore_order_statuses() as $status) {
        $count = $counts[$status];
        $total += $count;
        $colour = $colours[$status] ?? $colours['processing'];
        $label = customcore_order_status_label($status);
        $rows[] = [
            'status' => $status,
            'label' => $label,
            'count' => $count,
            'fill' => $colour['fill'],
            'border' => $colour['border'],
        ];
        $labels[] = $label;
        $values[] = $count;
        $fills[] = $colour['fill'];
        $borders[] = $colour['border'];
    }

    return [
        'rows' => $rows,
        'total' => $total,
        'revenue' => $revenue,
        'chart' => customcore_admin_report_chart_payload($labels, $values, $fills, $borders, 'Orders'),
    ];
}

/**
 * Active products grouped by performance tier (same categories as the catalogue).
 *
 * @return array{
 *   rows:list<array{name:string, slug:string, active_count:int, inactive_count:int, fill:string, border:string}>,
 *   active_total:int,
 *   inactive_total:int,
 *   chart:array{labels:list<string>, datasets:list<array<string,mixed>>}
 * }
 */
function customcore_admin_report_products(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT c.id, c.name, c.slug, c.sort_order,
                COALESCE(SUM(CASE WHEN p.is_active = 1 THEN 1 ELSE 0 END), 0) AS active_count,
                COALESCE(SUM(CASE WHEN p.is_active = 0 THEN 1 ELSE 0 END), 0) AS inactive_count
         FROM categories c
         LEFT JOIN products p ON p.category_id = c.id
         WHERE c.is_active = 1
         GROUP BY c.id, c.name, c.slug, c.sort_order
         ORDER BY c.sort_order ASC, c.name ASC'
    );

    $rows = [];
    $labels = [];
    $values = [];
    $fills = [];
    $borders = [];
    $activeTotal = 0;
    $inactiveTotal = 0;

    foreach ($stmt ? $stmt->fetchAll() : [] as $row) {
        $slug = (string) ($row['slug'] ?? '');
        $colour = customcore_catalogue_tier_colour($slug);
        $active = (int) ($row['active_count'] ?? 0);
        $inactive = (int) ($row['inactive_count'] ?? 0);
        $activeTotal += $active;
        $inactiveTotal += $inactive;
        $name = (string) ($row['name'] ?? '');

        $rows[] = [
            'name' => $name,
            'slug' => $slug,
            'active_count' => $active,
            'inactive_count' => $inactive,
            'fill' => $colour['fill'],
            'border' => $colour['border'],
        ];
        $labels[] = $name;
        $values[] = $active;
        $fills[] = $colour['fill'];
        $borders[] = $colour['border'];
    }

    return [
        'rows' => $rows,
        'active_total' => $activeTotal,
        'inactive_total' => $inactiveTotal,
        'chart' => customcore_admin_report_chart_payload(
            $labels,
            $values,
            $fills,
            $borders,
            'Active products'
        ),
    ];
}

/**
 * User accounts by role and by active status.
 *
 * @return array{
 *   role_rows:list<array{key:string, label:string, count:int, fill:string, border:string}>,
 *   status_rows:list<array{key:string, label:string, count:int, fill:string, border:string}>,
 *   total:int,
 *   role_chart:array{labels:list<string>, datasets:list<array<string,mixed>>},
 *   status_chart:array{labels:list<string>, datasets:list<array<string,mixed>>}
 * }
 */
function customcore_admin_report_users(PDO $pdo): array
{
    $colours = customcore_admin_report_colours();

    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(role = 'customer'), 0) AS customers,
            COALESCE(SUM(role = 'admin'), 0) AS admins,
            COALESCE(SUM(is_active = 1), 0) AS active,
            COALESCE(SUM(is_active = 0), 0) AS inactive
         FROM users"
    );
    $row = $stmt ? ($stmt->fetch() ?: []) : [];

    $roleRows = [
        [
            'key' => 'customer',
            'label' => 'Customers',
            'count' => (int) ($row['customers'] ?? 0),
            'fill' => $colours['customer']['fill'],
            'border' => $colours['customer']['border'],
        ],
        [
            'key' => 'admin',
            'label' => 'Administrators',
            'count' => (int) ($row['admins'] ?? 0),
            'fill' => $colours['admin']['fill'],
            'border' => $colours['admin']['border'],
        ],
    ];

    $statusRows = [
        [
            'key' => 'active',
            'label' => 'Active',
            'count' => (int) ($row['active'] ?? 0),
            'fill' => $colours['active']['fill'],
            'border' => $colours['active']['border'],
        ],
        [
            'key' => 'inactive',
            'label' => 'Disabled',
            'count' => (int) ($row['inactive'] ?? 0),
            'fill' => $colours['inactive']['fill'],
            'border' => $colours['inactive']['border'],
        ],
    ];

    return [
        'role_rows' => $roleRows,
        'status_rows' => $statusRows,
        'total' => (int) ($row['total'] ?? 0),
        'role_chart' => customcore_admin_report_chart_payload(
            array_column($roleRows, 'label'),
            array_column($roleRows, 'count'),
            array_column($roleRows, 'fill'),
            array_column($roleRows, 'border'),
            'Accounts'
        ),
        'status_chart' => customcore_admin_report_chart_payload(
            array_column($statusRows, 'label'),
            array_column($statusRows, 'count'),
            array_column($statusRows, 'fill'),
            array_column($statusRows, 'border'),
            'Accounts'
        ),
    ];
}

/**
 * Inventory health for catalogue products (active + disabled buckets).
 *
 * @return array{
 *   rows:list<array{key:string, label:string, count:int, fill:string, border:string}>,
 *   threshold:int,
 *   active_total:int,
 *   chart:array{labels:list<string>, datasets:list<array<string,mixed>>}
 * }
 */
function customcore_admin_report_inventory(PDO $pdo): array
{
    $threshold = customcore_admin_low_stock_threshold();
    $colours = customcore_admin_report_colours();

    $stmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN is_active = 1 AND stock_quantity = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
            COALESCE(SUM(CASE WHEN is_active = 1 AND stock_quantity > 0 AND stock_quantity <= :threshold THEN 1 ELSE 0 END), 0) AS low_stock,
            COALESCE(SUM(CASE WHEN is_active = 1 AND stock_quantity > :threshold2 THEN 1 ELSE 0 END), 0) AS healthy,
            COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS disabled,
            COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_total
         FROM products'
    );
    $stmt->execute([':threshold' => $threshold, ':threshold2' => $threshold]);
    $row = $stmt->fetch() ?: [];

    $rows = [
        [
            'key' => 'healthy',
            'label' => 'Healthy stock',
            'count' => (int) ($row['healthy'] ?? 0),
            'fill' => $colours['healthy']['fill'],
            'border' => $colours['healthy']['border'],
        ],
        [
            'key' => 'low',
            'label' => 'Low stock (≤ ' . $threshold . ')',
            'count' => (int) ($row['low_stock'] ?? 0),
            'fill' => $colours['low']['fill'],
            'border' => $colours['low']['border'],
        ],
        [
            'key' => 'out',
            'label' => 'Out of stock',
            'count' => (int) ($row['out_of_stock'] ?? 0),
            'fill' => $colours['out']['fill'],
            'border' => $colours['out']['border'],
        ],
        [
            'key' => 'disabled',
            'label' => 'Disabled products',
            'count' => (int) ($row['disabled'] ?? 0),
            'fill' => $colours['disabled']['fill'],
            'border' => $colours['disabled']['border'],
        ],
    ];

    return [
        'rows' => $rows,
        'threshold' => $threshold,
        'active_total' => (int) ($row['active_total'] ?? 0),
        'chart' => customcore_admin_report_chart_payload(
            array_column($rows, 'label'),
            array_column($rows, 'count'),
            array_column($rows, 'fill'),
            array_column($rows, 'border'),
            'Products'
        ),
    ];
}

/**
 * Bundle every report section for the admin page in one call.
 *
 * @return array{
 *   orders:array<string,mixed>,
 *   products:array<string,mixed>,
 *   users:array<string,mixed>,
 *   inventory:array<string,mixed>
 * }
 */
function customcore_admin_report_bundle(PDO $pdo): array
{
    return [
        'orders' => customcore_admin_report_orders($pdo),
        'products' => customcore_admin_report_products($pdo),
        'users' => customcore_admin_report_users($pdo),
        'inventory' => customcore_admin_report_inventory($pdo),
    ];
}
