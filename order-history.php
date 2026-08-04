<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Customer Order History.
// Lists orders placed by the logged-in customer in a table: order number, date, status, item
// count, payment method label, total, and a link to the itemized detail page. Optional status
// filter via ?status=.
// Access: Logged-in customer (customcore_require_login). All queries scoped to session user_id,
// users see only their own orders.
// Completion test: Users see only their orders.
// Security:
//   Ownership enforced via WHERE user_id =:uid on every query.
//   Status filter values are whitelisted against the ENUM set.
//   All outputs escaped via customcore_e().

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/orders.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'orders';

$pageTitle = 'Order History | CustomCore';
$pageDescription = 'View your past CustomCore orders and their status.';
$pageKeywords = 'CustomCore, order history, orders, purchase history';
$currentPage = 'orders';

// Optional status filter (whitelisted)

$statusFilter = '';
if (isset($_GET['status']) && is_string($_GET['status'])) {
    $candidate = strtolower(trim($_GET['status']));
    if (in_array($candidate, customcore_order_statuses(), true)) {
        $statusFilter = $candidate;
    }
}

// Load user's orders (owner-scoped)

$orders = [];
$loadError = null;
$totalForUser = 0;

try {
    $pdo = customcore_pdo();

    // Count all of this user's orders (unfiltered) for the summary line.
    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM orders WHERE user_id = :uid'
    );
    $countStmt->execute([':uid' => $userId]);
    $totalForUser = (int) $countStmt->fetchColumn();

    $sql = 'SELECT o.id, o.order_number, o.status, o.subtotal, o.total,
                   o.payment_method, o.created_at,
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
            FROM orders o
            WHERE o.user_id = :uid';
    $params = [':uid' => $userId];

    if ($statusFilter !== '') {
        $sql .= ' AND o.status = :status';
        $params[':status'] = $statusFilter;
    }

    $sql .= ' ORDER BY o.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Throwable $exception) {
    $loadError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load your order history right now. Please try again later.';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Order history page: list of the customer's past orders -->
<section class="content-section profile-page order-history-page" aria-labelledby="orders-heading">
    <header class="profile-page__header">
        <h1 id="orders-heading">Order history</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/orders.html#history')); ?>">Orders guide</a>
            review past orders, status, and itemized receipts.
        </p>
    </header>

    <!-- Account layout: sidebar navigation beside main content -->
    <div class="layout-split layout-split--account">
        <!-- Account navigation sidebar -->
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <!-- Main order-history content column -->
        <div class="profile-page__main">
            <!-- Error banner when the history fails to load -->
            <?php if ($loadError !== null): ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($loadError); ?>
                </div>
            <!-- Empty state when the customer has no orders yet -->
            <?php elseif ($totalForUser === 0): ?>
                <div class="order-history-empty">
                    <p>You have not placed any orders yet.</p>
                    <div class="order-history-empty__actions">
                        <a class="button button--primary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                            Browse catalogue
                        </a>
                        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">
                            Build a custom PC
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <!-- Toolbar: result summary and status filter links -->
                <div class="order-history-toolbar">
                    <p class="order-history-toolbar__summary">
                        <?php if ($statusFilter !== ''): ?>
                            Showing
                            <strong><?php echo customcore_e((string) count($orders)); ?></strong>
                            <?php echo customcore_e(customcore_order_status_label($statusFilter)); ?>
                            order<?php echo count($orders) === 1 ? '' : 's'; ?>
                            of
                            <strong><?php echo customcore_e((string) $totalForUser); ?></strong>
                            total.
                        <?php else: ?>
                            You have
                            <strong><?php echo customcore_e((string) $totalForUser); ?></strong>
                            order<?php echo $totalForUser === 1 ? '' : 's'; ?>.
                        <?php endif; ?>
                    </p>

                    <!-- Status filter navigation -->
                    <nav class="order-history-filters" aria-label="Filter orders by status">
                        <a
                            class="order-history-filters__link<?php echo $statusFilter === '' ? ' is-active' : ''; ?>"
                            href="<?php echo customcore_e(customcore_url('order-history.php')); ?>"
                        >All</a>
                        <?php foreach (customcore_order_statuses() as $st): ?>
                            <a
                                class="order-history-filters__link<?php echo $statusFilter === $st ? ' is-active' : ''; ?>"
                                href="<?php echo customcore_e(customcore_url('order-history.php?status=' . rawurlencode($st))); ?>"
                            ><?php echo customcore_e(customcore_order_status_label($st)); ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Empty state when no orders match the active filter -->
                <?php if ($orders === []): ?>
                    <div class="order-history-empty order-history-empty--filtered">
                        <p>
                            No
                            <?php echo customcore_e(customcore_order_status_label($statusFilter)); ?>
                            orders found.
                        </p>
                        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">
                            Show all orders
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Orders table: number, date, status, totals, and details link -->
                    <div class="order-history-table-wrap">
                        <table class="order-history-table">
                            <thead>
                                <tr>
                                    <th scope="col">Order</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Items</th>
                                    <th scope="col">Payment</th>
                                    <th scope="col">Total</th>
                                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $orderId = (int) $order['id'];
                                    $orderNumber = (string) $order['order_number'];
                                    $status = (string) $order['status'];
                                    $total = (float) $order['total'];
                                    $itemCount = (int) $order['item_count'];
                                    $payment = (string) $order['payment_method'];
                                    $dateDisplay = customcore_order_format_datetime((string) $order['created_at']);
                                    $detailsHref = customcore_url('order-details.php?id=' . $orderId);
                                    ?>
                                    <tr>
                                        <td data-label="Order">
                                            <a
                                                class="order-history-table__number"
                                                href="<?php echo customcore_e($detailsHref); ?>"
                                            ><?php echo customcore_e($orderNumber); ?></a>
                                        </td>
                                        <td data-label="Date"><?php echo customcore_e($dateDisplay); ?></td>
                                        <td data-label="Status">
                                            <span class="order-status <?php echo customcore_e(customcore_order_status_class($status)); ?>">
                                                <?php echo customcore_e(customcore_order_status_label($status)); ?>
                                            </span>
                                        </td>
                                        <td data-label="Items"><?php echo customcore_e((string) $itemCount); ?></td>
                                        <td data-label="Payment"><?php echo customcore_e(customcore_order_payment_label($payment)); ?></td>
                                        <td data-label="Total">$<?php echo customcore_e(number_format($total, 2)); ?></td>
                                        <td data-label="">
                                            <a
                                                class="button button--secondary button--sm"
                                                href="<?php echo customcore_e($detailsHref); ?>"
                                            >View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
