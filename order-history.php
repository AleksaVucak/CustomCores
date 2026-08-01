<?php
/**
 * CustomCore — Customer Order History (Commit 6.6).
 *
 * File responsibility:
 *   Lists all orders placed by the logged-in customer. Shows order number,
 *   status, total, date, and a link to the itemized detail page. Never
 *   exposes another user's orders.
 *
 * Authentication requirements:
 *   Logged-in customer (customcore_require_login). All queries scoped to
 *   session user_id.
 *
 * Security:
 *   - Ownership enforced via WHERE user_id = :uid on every query.
 *   - All outputs escaped via customcore_e().
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'orders';

$pageTitle = 'Order history — CustomCore';
$pageDescription = 'View your past CustomCore orders and their status.';
$pageKeywords = 'CustomCore, order history, orders, purchase history';
$currentPage = 'orders';

// ---------------------------------------------------------------------------
// Load user's orders (owner-scoped)
// ---------------------------------------------------------------------------

$orders = [];
$loadError = null;

try {
    $pdo = customcore_pdo();

    $stmt = $pdo->prepare(
        'SELECT o.id, o.order_number, o.status, o.subtotal, o.total,
                o.payment_method, o.created_at,
                COUNT(oi.id) AS item_count
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = :uid
         GROUP BY o.id
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([':uid' => $userId]);
    $orders = $stmt->fetchAll();
} catch (Throwable $exception) {
    $loadError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load your order history right now. Please try again later.';
}

/**
 * Human-readable status label.
 */
function customcore_order_status_label(string $status): string
{
    $labels = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'ready' => 'Ready for pickup',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    return $labels[$status] ?? ucfirst($status);
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section profile-page order-history-page" aria-labelledby="orders-heading">
    <header class="profile-page__header">
        <h1 id="orders-heading">Order history</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Help centre</a>
            — review past orders, status, and itemized receipts.
        </p>
    </header>

    <div class="layout-split layout-split--account">
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <div class="profile-page__main">
            <?php if ($loadError !== null): ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($loadError); ?>
                </div>
            <?php elseif ($orders === []): ?>
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
                <div class="order-history-table-wrap">
                    <table class="order-history-table">
                        <thead>
                            <tr>
                                <th scope="col">Order</th>
                                <th scope="col">Date</th>
                                <th scope="col">Status</th>
                                <th scope="col">Items</th>
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
                                $createdAt = (string) $order['created_at'];
                                $dateDisplay = '';
                                $ts = strtotime($createdAt);
                                if ($ts !== false) {
                                    $dateDisplay = date('M j, Y g:i A', $ts);
                                }
                                $statusClass = 'order-status--' . preg_replace('/[^a-z]/', '', strtolower($status));
                                ?>
                                <tr>
                                    <td data-label="Order">
                                        <a
                                            class="order-history-table__number"
                                            href="<?php echo customcore_e(customcore_url('order-details.php?id=' . $orderId)); ?>"
                                        ><?php echo customcore_e($orderNumber); ?></a>
                                    </td>
                                    <td data-label="Date"><?php echo customcore_e($dateDisplay); ?></td>
                                    <td data-label="Status">
                                        <span class="order-status <?php echo customcore_e($statusClass); ?>">
                                            <?php echo customcore_e(customcore_order_status_label($status)); ?>
                                        </span>
                                    </td>
                                    <td data-label="Items"><?php echo customcore_e((string) $itemCount); ?></td>
                                    <td data-label="Total">$<?php echo customcore_e(number_format($total, 2)); ?></td>
                                    <td data-label="">
                                        <a
                                            class="button button--secondary button--sm"
                                            href="<?php echo customcore_e(customcore_url('order-details.php?id=' . $orderId)); ?>"
                                        >View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
