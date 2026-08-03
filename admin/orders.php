<?php
/**
 * CustomCore — Administrator order list & search (Commit 9.5).
 *
 * File responsibility:
 *   Protected order index. Searches orders by number, customer name, or email;
 *   filters by status; paginates; and links to the per-order detail screen.
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
require_once __DIR__ . '/../includes/admin-orders.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

$filters = [
    'search' => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
    'status' => isset($_GET['status']) && in_array($_GET['status'] ?? '', customcore_order_statuses(), true)
        ? (string) $_GET['status']
        : '',
];
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25];
$statusCounts = ['all' => 0];
$listError = null;

try {
    $result = customcore_admin_order_list($pdo, $filters, $page, 25);
    $statusCounts = customcore_admin_order_status_counts($pdo);
} catch (Throwable $e) {
    $listError = customcore_is_debug() ? $e->getMessage() : 'Orders are temporarily unavailable.';
}

/** Build a query string preserving the current search/status (+ optional page). */
function customcore_admin_orders_query(array $filters, ?int $page = null): string
{
    $params = [];
    if (($filters['search'] ?? '') !== '') {
        $params['q'] = (string) $filters['search'];
    }
    if (($filters['status'] ?? '') !== '') {
        $params['status'] = (string) $filters['status'];
    }
    if ($page !== null && $page > 1) {
        $params['page'] = $page;
    }

    return $params === [] ? '' : '?' . http_build_query($params);
}

$adminNavCurrent = 'orders';
$loadAdminCss = true;
$currentPage = 'admin';

$pageTitle = 'Orders — CustomCore admin';
$pageDescription = 'Search and manage CustomCore customer orders.';
$pageKeywords = 'CustomCore, admin, orders';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Orders management: filters, results table, and pagination -->
<section class="content-section admin-page admin-orders" aria-labelledby="admin-orders-heading">
    <header class="admin-page__header">
        <h1 id="admin-orders-heading">Orders</h1>
        <p class="admin-page__intro">
            Search customer orders, review details, update fulfilment status, and record
            administrator notes.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Back to dashboard</a>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Load error banner -->
    <?php if ($listError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($listError); ?></p>
    <?php endif; ?>

    <!-- Filters: search + status with counts (GET) -->
    <form class="admin-filter" method="get" action="<?php echo customcore_e(customcore_url('admin/orders.php')); ?>">
        <div class="admin-filter__field">
            <label for="filter-q">Search</label>
            <input type="search" id="filter-q" name="q" value="<?php echo customcore_e($filters['search']); ?>"
                   placeholder="Order number, name, or email" maxlength="200">
        </div>
        <div class="admin-filter__field">
            <label for="filter-status">Status</label>
            <select id="filter-status" name="status">
                <option value="">All statuses (<?php echo customcore_e((string) ($statusCounts['all'] ?? 0)); ?>)</option>
                <?php foreach (customcore_order_statuses() as $s) : ?>
                    <option value="<?php echo customcore_e($s); ?>" <?php echo $filters['status'] === $s ? 'selected' : ''; ?>>
                        <?php echo customcore_e(customcore_order_status_label($s)); ?>
                        (<?php echo customcore_e((string) ($statusCounts[$s] ?? 0)); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filter__actions">
            <button type="submit" class="button button--sm">Apply</button>
            <?php if ($filters['search'] !== '' || $filters['status'] !== '') : ?>
                <a class="button button--ghost button--sm" href="<?php echo customcore_e(customcore_url('admin/orders.php')); ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Results: empty state, otherwise order count and table -->
    <?php if ($result['rows'] === []) : ?>
        <p class="admin-activity__empty">No orders match your filters.</p>
    <?php else : ?>
        <p class="admin-products__count">
            <?php echo customcore_e((string) $result['total']); ?>
            order<?php echo $result['total'] === 1 ? '' : 's'; ?> found
            · page <?php echo customcore_e((string) $result['page']); ?>
            of <?php echo customcore_e((string) $result['pages']); ?>
        </p>
        <!-- Orders table: number, customer, items, total, status, and view action -->
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--orders">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Items</th>
                        <th scope="col">Total</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $order) : ?>
                        <tr>
                            <td>
                                <span class="admin-product-cell__name"><?php echo customcore_e((string) $order['order_number']); ?></span>
                                <span class="admin-table__sub"><?php echo customcore_e(customcore_order_format_datetime((string) $order['created_at'])); ?></span>
                            </td>
                            <td>
                                <?php echo customcore_e(trim((string) $order['first_name'] . ' ' . (string) $order['last_name'])); ?>
                                <span class="admin-table__sub"><?php echo customcore_e((string) $order['email']); ?></span>
                            </td>
                            <td><?php echo customcore_e((string) (int) $order['item_count']); ?></td>
                            <td>$<?php echo customcore_e(number_format((float) $order['total'], 2)); ?></td>
                            <td>
                                <span class="order-status <?php echo customcore_e(customcore_order_status_class((string) $order['status'])); ?>">
                                    <?php echo customcore_e(customcore_order_status_label((string) $order['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <a class="button button--ghost button--sm"
                                   href="<?php echo customcore_e(customcore_url('admin/order-details.php?id=' . (int) $order['id'])); ?>">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination: previous/next across order pages -->
        <?php if ($result['pages'] > 1) : ?>
            <nav class="admin-pagination" aria-label="Order pages">
                <?php if ($result['page'] > 1) : ?>
                    <a class="button button--ghost button--sm"
                       href="<?php echo customcore_e(customcore_url('admin/orders.php' . customcore_admin_orders_query($filters, $result['page'] - 1))); ?>">
                        ← Previous
                    </a>
                <?php endif; ?>
                <span class="admin-pagination__status">
                    Page <?php echo customcore_e((string) $result['page']); ?> of <?php echo customcore_e((string) $result['pages']); ?>
                </span>
                <?php if ($result['page'] < $result['pages']) : ?>
                    <a class="button button--ghost button--sm"
                       href="<?php echo customcore_e(customcore_url('admin/orders.php' . customcore_admin_orders_query($filters, $result['page'] + 1))); ?>">
                        Next →
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
