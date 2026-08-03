<?php
/**
 * CustomCore — Customer Order Details (Commit 6.8).
 *
 * File responsibility:
 *   Displays a single itemized order that belongs to the logged-in customer.
 *   Shows shipping snapshot, payment-method label, status, and every line
 *   item with frozen prices. Product options and custom-build component
 *   snapshots are expanded from JSON stored at checkout time.
 *
 * Authentication requirements:
 *   Logged-in customer. Ownership verified via user_id = session user.
 *
 * Completion test:
 *   Direct URL access to another user's order ID is denied.
 *
 * Security:
 *   - Order loaded with customcore_order_fetch_owned() (id AND user_id).
 *   - Line items loaded with customcore_order_fetch_items() which JOINs
 *     orders so foreign order_ids cannot leak item rows.
 *   - Missing / foreign order → identical flash + redirect (no existence leak).
 *   - All outputs escaped via customcore_e().
 *   - Payment method is a label only — never card data.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/orders.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'orders';

// ---------------------------------------------------------------------------
// Validate order id from query string
// ---------------------------------------------------------------------------

$orderId = 0;
if (isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id'])) {
    $orderId = (int) $_GET['id'];
}

if ($orderId <= 0) {
    customcore_flash_error('Invalid order ID.');
    customcore_redirect('order-history.php');
}

// ---------------------------------------------------------------------------
// Load order + items — ownership enforced on both queries
// ---------------------------------------------------------------------------

$order = null;
$items = [];
$loadError = null;

try {
    $pdo = customcore_pdo();
    $order = customcore_order_fetch_owned($pdo, $orderId, $userId);

    if ($order !== null) {
        $items = customcore_order_fetch_items($pdo, $orderId, $userId);
    }
} catch (Throwable $exception) {
    $loadError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load this order right now. Please try again later.';
}

// Deny missing orders and foreign IDs with the same message (no enumeration).
if ($order === null) {
    customcore_flash_error(
        $loadError ?? 'Order not found or you do not have permission to view it.'
    );
    customcore_redirect('order-history.php');
}

$status = (string) $order['status'];
$statusClass = customcore_order_status_class($status);
$orderNumber = (string) $order['order_number'];
$total = (float) $order['total'];
$subtotal = (float) $order['subtotal'];
$dateDisplay = customcore_order_format_datetime(
    (string) $order['created_at'],
    'F j, Y \a\t g:i A'
);

$linesSubtotal = 0.0;
foreach ($items as $line) {
    $linesSubtotal += (float) $line['line_total'];
}
$linesSubtotal = round($linesSubtotal, 2);

$pageTitle = 'Order ' . $orderNumber . ' — CustomCore';
$pageDescription = 'Itemized details for order ' . $orderNumber . '.';
$pageKeywords = 'CustomCore, order details, receipt';
$currentPage = 'orders';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Order details page: itemized view of a single order -->
<section class="content-section profile-page order-details-page" aria-labelledby="order-details-heading">
    <header class="profile-page__header">
        <h1 id="order-details-heading">Order <?php echo customcore_e($orderNumber); ?></h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/orders.html#details')); ?>">Orders guide</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">Back to order history</a>
        </p>
    </header>

    <!-- Account layout: sidebar navigation beside main content -->
    <div class="layout-split layout-split--account">
        <!-- Account navigation sidebar -->
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <!-- Main order-details content column -->
        <div class="profile-page__main">

            <!-- Order meta: status, placed date, and confirmation number -->
            <div class="order-details__meta">
                <p>
                    <span class="order-status <?php echo customcore_e($statusClass); ?>">
                        <?php echo customcore_e(customcore_order_status_label($status)); ?>
                    </span>
                    <?php if ($dateDisplay !== ''): ?>
                        <span class="order-details__date">Placed <?php echo customcore_e($dateDisplay); ?></span>
                    <?php endif; ?>
                </p>
                <p class="order-details__number-line">
                    Confirmation number:
                    <strong class="order-details__number"><?php echo customcore_e($orderNumber); ?></strong>
                </p>
            </div>

            <!-- Summary grid: shipping and payment/totals cards -->
            <div class="order-details__grid">
                <!-- Shipping details card -->
                <section class="order-details__card" aria-labelledby="shipping-heading">
                    <h2 id="shipping-heading" class="order-details__card-title">Shipping details</h2>
                    <dl class="order-details__dl">
                        <dt>Name</dt>
                        <dd><?php echo customcore_e((string) $order['shipping_name']); ?></dd>

                        <dt>Phone</dt>
                        <dd><?php echo customcore_e((string) $order['shipping_phone']); ?></dd>

                        <dt>Address</dt>
                        <dd>
                            <?php echo customcore_e((string) $order['shipping_addr1']); ?>
                            <?php if ((string) $order['shipping_addr2'] !== ''): ?>
                                <br><?php echo customcore_e((string) $order['shipping_addr2']); ?>
                            <?php endif; ?>
                            <br>
                            <?php echo customcore_e((string) $order['shipping_city']); ?>,
                            <?php echo customcore_e((string) $order['shipping_prov']); ?>
                            <?php echo customcore_e((string) $order['shipping_postal']); ?>
                        </dd>
                    </dl>
                </section>

                <!-- Payment method and order totals card -->
                <section class="order-details__card" aria-labelledby="payment-heading">
                    <h2 id="payment-heading" class="order-details__card-title">Payment &amp; totals</h2>
                    <dl class="order-details__dl">
                        <dt>Payment method</dt>
                        <dd><?php echo customcore_e(customcore_order_payment_label((string) $order['payment_method'])); ?></dd>

                        <dt>Subtotal</dt>
                        <dd>$<?php echo customcore_e(number_format($subtotal, 2)); ?></dd>

                        <dt>Total</dt>
                        <dd class="order-details__total">$<?php echo customcore_e(number_format($total, 2)); ?></dd>
                    </dl>
                    <p class="order-details__payment-note">
                        Simulated checkout — no real payment card data was collected.
                    </p>
                </section>
            </div>

            <!-- Line items: options, build snapshots, and totals -->
            <section class="order-details__items" aria-labelledby="items-heading">
                <h2 id="items-heading" class="order-details__card-title">Items ordered</h2>

                <?php if ($items === []): ?>
                    <p>No line items were recorded for this order.</p>
                <?php else: ?>
                    <div class="order-details-table-wrap">
                        <table class="order-details-table">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Qty</th>
                                    <th scope="col">Unit price</th>
                                    <th scope="col">Line total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $isBuild = (string) $item['item_type'] === 'saved_build';
                                    $productId = $item['product_id'] !== null ? (int) $item['product_id'] : null;
                                    $optionLines = customcore_order_decode_options(
                                        isset($item['options_json']) ? (string) $item['options_json'] : null
                                    );
                                    $buildParts = customcore_order_decode_build_snapshot(
                                        isset($item['build_snapshot_json']) ? (string) $item['build_snapshot_json'] : null
                                    );
                                    ?>
                                    <tr>
                                        <td data-label="Item">
                                            <?php if (!$isBuild && $productId !== null && $productId > 0): ?>
                                                <a
                                                    class="order-details__item-link"
                                                    href="<?php echo customcore_e(customcore_url('product.php?id=' . $productId)); ?>"
                                                >
                                                    <strong><?php echo customcore_e((string) $item['item_name']); ?></strong>
                                                </a>
                                            <?php else: ?>
                                                <strong><?php echo customcore_e((string) $item['item_name']); ?></strong>
                                            <?php endif; ?>

                                            <?php if ($optionLines !== []): ?>
                                                <ul class="order-details__options">
                                                    <?php foreach ($optionLines as $optLine): ?>
                                                        <li><?php echo customcore_e($optLine); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>

                                            <?php if ($buildParts !== []): ?>
                                                <details class="order-details__build">
                                                    <summary>Build components (<?php echo customcore_e((string) count($buildParts)); ?>)</summary>
                                                    <ul class="order-details__build-parts">
                                                        <?php foreach ($buildParts as $part): ?>
                                                            <li>
                                                                <?php if ($part['category'] !== ''): ?>
                                                                    <span class="order-details__part-cat"><?php echo customcore_e($part['category']); ?>:</span>
                                                                <?php endif; ?>
                                                                <?php echo customcore_e($part['component']); ?>
                                                                <span class="order-details__part-price">
                                                                    $<?php echo customcore_e(number_format($part['price'], 2)); ?>
                                                                </span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </details>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Type">
                                            <?php if ($isBuild): ?>
                                                <span class="order-confirm__badge">Build</span>
                                            <?php else: ?>
                                                <span class="order-details__type">Product</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Qty"><?php echo customcore_e((string) (int) $item['quantity']); ?></td>
                                        <td data-label="Unit price">$<?php echo customcore_e(number_format((float) $item['unit_price'], 2)); ?></td>
                                        <td data-label="Line total">$<?php echo customcore_e(number_format((float) $item['line_total'], 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="order-confirm__total-label">Items subtotal</td>
                                    <td>$<?php echo customcore_e(number_format($linesSubtotal, 2)); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="order-confirm__total-label">Order total</td>
                                    <td class="order-confirm__total-value">$<?php echo customcore_e(number_format($total, 2)); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Page actions: back to history, confirmation, keep shopping -->
            <div class="order-details__actions">
                <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">
                    &larr; Order history
                </a>
                <a
                    class="button button--secondary"
                    href="<?php echo customcore_e(customcore_url('order-confirmation.php?id=' . (int) $order['id'])); ?>"
                >
                    View confirmation
                </a>
                <a class="button button--primary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                    Continue shopping
                </a>
            </div>

        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
