<?php
/**
 * CustomCore — Administrator reports & charts (Commit 9.9).
 *
 * File responsibility:
 *   Protected analytics page. Charts for orders by status, products by
 *   performance tier, user accounts, and inventory health — all driven by live
 *   MySQL aggregates. Each chart has a server-rendered accessible data table
 *   that remains the source of truth if Chart.js fails to load.
 *
 * Authentication requirements:
 *   Administrator role (customcore_require_admin()).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/admin-reports.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

$report = [
    'orders' => ['rows' => [], 'total' => 0, 'revenue' => 0.0, 'chart' => ['labels' => [], 'datasets' => []]],
    'products' => ['rows' => [], 'active_total' => 0, 'inactive_total' => 0, 'chart' => ['labels' => [], 'datasets' => []]],
    'users' => [
        'role_rows' => [],
        'status_rows' => [],
        'total' => 0,
        'role_chart' => ['labels' => [], 'datasets' => []],
        'status_chart' => ['labels' => [], 'datasets' => []],
    ],
    'inventory' => ['rows' => [], 'threshold' => 5, 'active_total' => 0, 'chart' => ['labels' => [], 'datasets' => []]],
];
$reportError = null;

try {
    $report = customcore_admin_report_bundle($pdo);
} catch (Throwable $e) {
    $reportError = customcore_is_debug() ? $e->getMessage() : 'Reports are temporarily unavailable.';
}

$ordersJson = json_encode(
    $report['orders']['chart'],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
$productsJson = json_encode(
    $report['products']['chart'],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
$usersRoleJson = json_encode(
    $report['users']['role_chart'],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
$usersStatusJson = json_encode(
    $report['users']['status_chart'],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
$inventoryJson = json_encode(
    $report['inventory']['chart'],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);

if ($ordersJson === false) {
    $ordersJson = '{}';
}
if ($productsJson === false) {
    $productsJson = '{}';
}
if ($usersRoleJson === false) {
    $usersRoleJson = '{}';
}
if ($usersStatusJson === false) {
    $usersStatusJson = '{}';
}
if ($inventoryJson === false) {
    $inventoryJson = '{}';
}

$adminNavCurrent = 'reports';
$loadAdminCss = true;
$loadAdminReports = true;
$currentPage = 'admin';

$pageTitle = 'Reports — CustomCore admin';
$pageDescription = 'Live CustomCore charts for orders, catalogue, users, and inventory.';
$pageKeywords = 'CustomCore, admin, reports, charts';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-reports" aria-labelledby="admin-reports-heading">
    <header class="admin-page__header">
        <h1 id="admin-reports-heading">Reports</h1>
        <p class="admin-page__intro">
            Live charts from the CustomCore database — orders, catalogue composition,
            accounts, and inventory health. Each graph has a matching data table that
            stays readable if charts cannot load.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Back to dashboard</a>
        </p>
    </header>

    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <?php if ($reportError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($reportError); ?></p>
    <?php else : ?>

        <section class="admin-report-kpis-section" aria-labelledby="admin-report-kpis-heading">
            <h2 id="admin-report-kpis-heading">At a glance</h2>
            <div class="admin-report-kpis">
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Orders</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $report['orders']['total']); ?></p>
                    <p class="admin-stat__meta">
                        Revenue $<?php echo customcore_e(number_format((float) $report['orders']['revenue'], 2)); ?>
                        (excl. cancelled)
                    </p>
                </article>
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Active products</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $report['products']['active_total']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $report['products']['inactive_total']); ?> disabled
                    </p>
                </article>
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Accounts</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $report['users']['total']); ?></p>
                    <p class="admin-stat__meta">Customers + administrators</p>
                </article>
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Active inventory</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $report['inventory']['active_total']); ?></p>
                    <p class="admin-stat__meta">
                        Low-stock threshold ≤ <?php echo customcore_e((string) $report['inventory']['threshold']); ?>
                    </p>
                </article>
            </div>
        </section>

        <!-- Orders by status -->
        <section class="admin-card admin-report-panel" aria-labelledby="report-orders-heading">
            <h2 id="report-orders-heading" class="admin-card__title">Orders by status</h2>
            <div
                class="admin-report-chart"
                data-admin-report-chart="<?php echo customcore_e($ordersJson); ?>"
                data-chart-type="doughnut"
                data-chart-title="Orders by fulfilment status"
            >
                <div class="admin-report-chart__canvas-wrap">
                    <canvas
                        id="admin-report-orders"
                        role="img"
                        aria-label="Doughnut chart of orders by fulfilment status. The same figures are listed in the table beside it."
                    ></canvas>
                </div>
                <div class="admin-report-chart__summary">
                    <h3 class="admin-report-chart__summary-title">Order counts</h3>
                    <table class="admin-report-chart__table">
                        <caption class="visually-hidden">Number of orders in each fulfilment status.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Status</th>
                                <th scope="col">Orders</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['orders']['rows'] as $row) : ?>
                                <tr>
                                    <th scope="row"><?php echo customcore_e((string) $row['label']); ?></th>
                                    <td><?php echo customcore_e((string) $row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row">Total</th>
                                <td><?php echo customcore_e((string) $report['orders']['total']); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    <p class="admin-report-chart__note">
                        <?php if ($report['orders']['total'] === 0) : ?>
                            No orders have been placed yet — the chart will fill in as customers check out.
                        <?php else : ?>
                            Figures are read live from the orders table.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </section>

        <!-- Products by tier -->
        <section class="admin-card admin-report-panel" aria-labelledby="report-products-heading">
            <h2 id="report-products-heading" class="admin-card__title">Products by performance tier</h2>
            <div
                class="admin-report-chart"
                data-admin-report-chart="<?php echo customcore_e($productsJson); ?>"
                data-chart-type="bar"
                data-chart-title="Active products by performance tier"
            >
                <div class="admin-report-chart__canvas-wrap">
                    <canvas
                        id="admin-report-products"
                        role="img"
                        aria-label="Bar chart of active products by performance tier. The same figures are listed in the table beside it."
                    ></canvas>
                </div>
                <div class="admin-report-chart__summary">
                    <h3 class="admin-report-chart__summary-title">Catalogue composition</h3>
                    <table class="admin-report-chart__table">
                        <caption class="visually-hidden">Active and disabled products in each performance tier.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Tier</th>
                                <th scope="col">Active</th>
                                <th scope="col">Disabled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['products']['rows'] as $row) : ?>
                                <tr>
                                    <th scope="row"><?php echo customcore_e((string) $row['name']); ?></th>
                                    <td><?php echo customcore_e((string) $row['active_count']); ?></td>
                                    <td><?php echo customcore_e((string) $row['inactive_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="admin-report-chart__note">
                        Active counts power the chart; disabled products are listed for inventory context.
                    </p>
                </div>
            </div>
        </section>

        <!-- Users -->
        <section class="admin-card admin-report-panel" aria-labelledby="report-users-heading">
            <h2 id="report-users-heading" class="admin-card__title">User accounts</h2>
            <div class="admin-report-users">
                <div
                    class="admin-report-chart"
                    data-admin-report-chart="<?php echo customcore_e($usersRoleJson); ?>"
                    data-chart-type="doughnut"
                    data-chart-title="Accounts by role"
                >
                    <div class="admin-report-chart__canvas-wrap admin-report-chart__canvas-wrap--sm">
                        <canvas
                            id="admin-report-users-role"
                            role="img"
                            aria-label="Doughnut chart of accounts by role. The same figures are listed in the table beside it."
                        ></canvas>
                    </div>
                    <div class="admin-report-chart__summary">
                        <h3 class="admin-report-chart__summary-title">By role</h3>
                        <table class="admin-report-chart__table">
                            <caption class="visually-hidden">Number of accounts by role.</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Role</th>
                                    <th scope="col">Accounts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['users']['role_rows'] as $row) : ?>
                                    <tr>
                                        <th scope="row"><?php echo customcore_e((string) $row['label']); ?></th>
                                        <td><?php echo customcore_e((string) $row['count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="admin-report-chart"
                    data-admin-report-chart="<?php echo customcore_e($usersStatusJson); ?>"
                    data-chart-type="doughnut"
                    data-chart-title="Accounts by status"
                >
                    <div class="admin-report-chart__canvas-wrap admin-report-chart__canvas-wrap--sm">
                        <canvas
                            id="admin-report-users-status"
                            role="img"
                            aria-label="Doughnut chart of accounts by active status. The same figures are listed in the table beside it."
                        ></canvas>
                    </div>
                    <div class="admin-report-chart__summary">
                        <h3 class="admin-report-chart__summary-title">By status</h3>
                        <table class="admin-report-chart__table">
                            <caption class="visually-hidden">Number of active and disabled accounts.</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Status</th>
                                    <th scope="col">Accounts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['users']['status_rows'] as $row) : ?>
                                    <tr>
                                        <th scope="row"><?php echo customcore_e((string) $row['label']); ?></th>
                                        <td><?php echo customcore_e((string) $row['count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <p class="admin-report-chart__note">
                <?php echo customcore_e((string) $report['users']['total']); ?> accounts total — figures are read live from the users table.
            </p>
        </section>

        <!-- Inventory -->
        <section class="admin-card admin-report-panel" aria-labelledby="report-inventory-heading">
            <h2 id="report-inventory-heading" class="admin-card__title">Inventory health</h2>
            <div
                class="admin-report-chart"
                data-admin-report-chart="<?php echo customcore_e($inventoryJson); ?>"
                data-chart-type="bar"
                data-chart-title="Catalogue inventory health"
            >
                <div class="admin-report-chart__canvas-wrap">
                    <canvas
                        id="admin-report-inventory"
                        role="img"
                        aria-label="Bar chart of inventory health. The same figures are listed in the table beside it."
                    ></canvas>
                </div>
                <div class="admin-report-chart__summary">
                    <h3 class="admin-report-chart__summary-title">Stock buckets</h3>
                    <table class="admin-report-chart__table">
                        <caption class="visually-hidden">
                            Product counts for healthy stock, low stock, out of stock, and disabled products.
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Bucket</th>
                                <th scope="col">Products</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['inventory']['rows'] as $row) : ?>
                                <tr>
                                    <th scope="row"><?php echo customcore_e((string) $row['label']); ?></th>
                                    <td><?php echo customcore_e((string) $row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="admin-report-chart__note">
                        Low stock means an active product with quantity greater than zero and at most
                        <?php echo customcore_e((string) $report['inventory']['threshold']); ?>.
                    </p>
                </div>
            </div>
        </section>

    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
