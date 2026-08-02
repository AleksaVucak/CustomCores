<?php
/**
 * CustomCore — Administrator Dashboard (Commit 9.1).
 *
 * File responsibility:
 *   Protected administrator home screen. Displays live MySQL counts for the
 *   catalogue, orders, users, reviews, consultations, and contact inbox;
 *   attention alerts; recent activity tables; and the Stage 9 tool registry.
 *   Access control remains customcore_require_admin() (Commit 4.7).
 *
 * Authentication requirements:
 *   Administrator role.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/reviews.php';
require_once __DIR__ . '/../includes/consultations.php';

customcore_require_admin();

$adminName = customcore_current_user_name();
$adminNavCurrent = 'dashboard';
$loadAdminCss = true;

$pageTitle = 'Admin dashboard — CustomCore';
$pageDescription = 'CustomCore administrator dashboard with live catalogue, order, and moderation summaries.';
$pageKeywords = 'CustomCore, admin, dashboard, management';
$currentPage = 'admin';

$stats = null;
$alerts = [];
$recentOrders = [];
$pendingReviews = [];
$openConsultations = [];
$lowStockProducts = [];
$adminTools = customcore_admin_tools();
$dashboardError = null;

try {
    $pdo = customcore_pdo();
    $stats = customcore_admin_dashboard_stats($pdo);
    $alerts = customcore_admin_dashboard_alerts($stats);
    $recentOrders = customcore_admin_recent_orders($pdo, 5);
    $pendingReviews = customcore_admin_pending_reviews($pdo, 5);
    $openConsultations = customcore_admin_open_consultations($pdo, 5);
    $lowStockProducts = customcore_admin_low_stock_products($pdo, 5);
} catch (Throwable $exception) {
    $dashboardError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Dashboard statistics are temporarily unavailable.';
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-dashboard" aria-labelledby="admin-heading">
    <header class="admin-page__header">
        <h1 id="admin-heading">Administrator dashboard</h1>
        <p class="admin-page__intro">
            Welcome, <?php echo customcore_e($adminName !== '' ? $adminName : 'Administrator'); ?>.
            Counts and alerts below are read live from the CustomCore database.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('index.php')); ?>">Back to store</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('profile.php')); ?>">My account</a>
        </p>
    </header>

    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <?php if ($dashboardError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($dashboardError); ?></p>
    <?php elseif ($stats !== null) : ?>

        <?php if ($alerts !== []) : ?>
            <section class="admin-alerts" aria-labelledby="admin-alerts-heading">
                <h2 id="admin-alerts-heading">Needs attention</h2>
                <ul class="admin-alerts__list">
                    <?php foreach ($alerts as $alert) : ?>
                        <?php
                        $alertHref = $alert['tool'] !== ''
                            ? customcore_admin_tool_href($alert['tool'])
                            : '';
                        ?>
                        <li class="admin-alerts__item admin-alerts__item--<?php echo customcore_e($alert['level']); ?>">
                            <p class="admin-alerts__title"><?php echo customcore_e($alert['title']); ?></p>
                            <p class="admin-alerts__detail"><?php echo customcore_e($alert['detail']); ?></p>
                            <?php if ($alertHref !== '') : ?>
                                <p class="admin-alerts__action">
                                    <a href="<?php echo customcore_e(customcore_url($alertHref)); ?>">Open tool</a>
                                </p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php else : ?>
            <p class="flash flash--success" role="status">
                No urgent alerts — the queue looks clear right now.
            </p>
        <?php endif; ?>

        <section class="admin-stats" aria-labelledby="admin-stats-heading">
            <h2 id="admin-stats-heading">At a glance</h2>
            <div class="admin-stats__grid">
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Active products</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['products_active']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['products_total']); ?> total
                        · <?php echo customcore_e((string) $stats['products_inactive']); ?> disabled
                    </p>
                </article>
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Orders in progress</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['orders_open']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['orders_total']); ?> all time
                        · <?php echo customcore_e((string) $stats['orders_completed']); ?> completed
                    </p>
                </article>
                <article class="admin-stat">
                    <h3 class="admin-stat__label">User accounts</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['users_total']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['users_customers']); ?> customers
                        · <?php echo customcore_e((string) $stats['users_admins']); ?> admins
                    </p>
                </article>
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Pending reviews</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['reviews_pending']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['reviews_approved']); ?> approved
                        · <?php echo customcore_e((string) $stats['reviews_hidden']); ?> hidden
                    </p>
                </article>
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Open consultations</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['consultations_needs_attention']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['consultations_open']); ?> open
                        · <?php echo customcore_e((string) $stats['consultations_in_progress']); ?> in progress
                    </p>
                </article>
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Stock warnings</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) ($stats['products_low_stock'] + $stats['products_out_of_stock'])); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['products_low_stock']); ?> low
                        · <?php echo customcore_e((string) $stats['products_out_of_stock']); ?> out of stock
                    </p>
                </article>
            </div>
        </section>

        <section class="admin-summary" aria-labelledby="admin-summary-heading">
            <h2 id="admin-summary-heading">Status summary</h2>
            <div class="admin-summary__grid">
                <div class="admin-summary__card">
                    <h3>Orders by status</h3>
                    <ul class="admin-summary__list">
                        <li><span>Pending</span><strong><?php echo customcore_e((string) $stats['orders_pending']); ?></strong></li>
                        <li><span>Processing</span><strong><?php echo customcore_e((string) $stats['orders_processing']); ?></strong></li>
                        <li><span>Ready for pickup</span><strong><?php echo customcore_e((string) $stats['orders_ready']); ?></strong></li>
                        <li><span>Completed</span><strong><?php echo customcore_e((string) $stats['orders_completed']); ?></strong></li>
                        <li><span>Cancelled</span><strong><?php echo customcore_e((string) $stats['orders_cancelled']); ?></strong></li>
                    </ul>
                </div>
                <div class="admin-summary__card">
                    <h3>Accounts &amp; inbox</h3>
                    <ul class="admin-summary__list">
                        <li><span>Active users</span><strong><?php echo customcore_e((string) $stats['users_active']); ?></strong></li>
                        <li><span>Disabled users</span><strong><?php echo customcore_e((string) $stats['users_inactive']); ?></strong></li>
                        <li><span>Contact messages</span><strong><?php echo customcore_e((string) $stats['contact_total']); ?></strong></li>
                        <li><span>Unread messages</span><strong><?php echo customcore_e((string) $stats['contact_unread']); ?></strong></li>
                        <li><span>Consultations answered</span><strong><?php echo customcore_e((string) $stats['consultations_answered']); ?></strong></li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="admin-activity" aria-labelledby="admin-activity-heading">
            <h2 id="admin-activity-heading">Recent activity</h2>
            <div class="admin-activity__grid">
                <div class="admin-activity__panel">
                    <h3>Latest orders</h3>
                    <?php if ($recentOrders === []) : ?>
                        <p class="admin-activity__empty">No orders have been placed yet.</p>
                    <?php else : ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th scope="col">Order</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order) : ?>
                                    <tr>
                                        <td>
                                            <?php echo customcore_e((string) $order['order_number']); ?>
                                            <span class="admin-table__sub"><?php echo customcore_e(customcore_order_format_datetime((string) $order['created_at'])); ?></span>
                                        </td>
                                        <td>
                                            <?php echo customcore_e(trim((string) $order['first_name'] . ' ' . (string) $order['last_name'])); ?>
                                            <span class="admin-table__sub"><?php echo customcore_e((string) $order['email']); ?></span>
                                        </td>
                                        <td>
                                            <span class="order-status <?php echo customcore_e(customcore_order_status_class((string) $order['status'])); ?>">
                                                <?php echo customcore_e(customcore_order_status_label((string) $order['status'])); ?>
                                            </span>
                                        </td>
                                        <td>$<?php echo customcore_e(number_format((float) $order['total'], 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="admin-activity__panel">
                    <h3>Pending reviews</h3>
                    <?php if ($pendingReviews === []) : ?>
                        <p class="admin-activity__empty">No reviews are waiting for moderation.</p>
                    <?php else : ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Rating</th>
                                    <th scope="col">Customer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingReviews as $review) : ?>
                                    <tr>
                                        <td>
                                            <?php echo customcore_e((string) $review['product_name']); ?>
                                            <span class="admin-table__sub"><?php echo customcore_e((string) $review['title']); ?></span>
                                        </td>
                                        <td><?php echo customcore_e(customcore_format_rating((int) $review['rating'])); ?></td>
                                        <td>
                                            <?php echo customcore_e(trim((string) $review['first_name'] . ' ' . (string) $review['last_name'])); ?>
                                            <span class="admin-table__sub"><?php echo customcore_e(customcore_format_date((string) $review['created_at'])); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="admin-activity__panel">
                    <h3>Open consultations</h3>
                    <?php if ($openConsultations === []) : ?>
                        <p class="admin-activity__empty">No open or in-progress consultation requests.</p>
                    <?php else : ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Budget</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($openConsultations as $consult) : ?>
                                    <tr>
                                        <td>
                                            <?php echo customcore_e(trim((string) $consult['first_name'] . ' ' . (string) $consult['last_name'])); ?>
                                            <span class="admin-table__sub"><?php echo customcore_e((string) $consult['email']); ?></span>
                                        </td>
                                        <td><?php echo customcore_e((string) $consult['budget']); ?></td>
                                        <td>
                                            <span class="consult-status <?php echo customcore_e(customcore_consultation_status_class((string) $consult['status'])); ?>">
                                                <?php echo customcore_e(customcore_consultation_status_label((string) $consult['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="admin-activity__panel">
                    <h3>Low stock (≤ <?php echo customcore_e((string) $stats['low_stock_threshold']); ?>)</h3>
                    <?php if ($lowStockProducts === []) : ?>
                        <p class="admin-activity__empty">No active products are in the low-stock range.</p>
                    <?php else : ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Stock</th>
                                    <th scope="col">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo customcore_e(customcore_url('product.php?slug=' . rawurlencode((string) $product['slug']))); ?>">
                                                <?php echo customcore_e((string) $product['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo customcore_e((string) $product['stock_quantity']); ?></td>
                                        <td>$<?php echo customcore_e(number_format((float) $product['base_price'], 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    <?php endif; ?>

    <section class="admin-tools" aria-labelledby="admin-tools-heading">
        <h2 id="admin-tools-heading">Management tools</h2>
        <p class="admin-tools__intro">
            Tools light up as each Stage 9–13 page is added. Access control for every
            route remains enforced by <code>customcore_require_admin()</code>.
        </p>
        <div class="admin-tools__grid">
            <?php foreach ($adminTools as $tool) : ?>
                <article class="admin-tool<?php echo $tool['available'] ? '' : ' admin-tool--soon'; ?>">
                    <h3 class="admin-tool__title"><?php echo customcore_e($tool['label']); ?></h3>
                    <p class="admin-tool__desc"><?php echo customcore_e($tool['description']); ?></p>
                    <?php if ($tool['available']) : ?>
                        <p class="admin-tool__action">
                            <a class="button button--sm" href="<?php echo customcore_e(customcore_url($tool['href'])); ?>">
                                Open <?php echo customcore_e($tool['label']); ?>
                            </a>
                        </p>
                    <?php else : ?>
                        <p class="admin-tool__meta">Coming in commit <?php echo customcore_e($tool['commit']); ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
