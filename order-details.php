<?php
/**
 * CustomCore — Customer Order Details (Commit 6.8).
 *
 * File responsibility:
 *   Displays a single itemized order that belongs to the logged-in customer.
 *   Shows shipping snapshot, payment method label, status, and every line
 *   item with frozen prices. For custom builds, expands the frozen component
 *   snapshot. Ownership is enforced: a direct URL to another user's order
 *   is denied.
 *
 * Authentication requirements:
 *   Logged-in customer. Ownership verified via user_id = session user.
 *
 * Security:
 *   - Query always includes AND user_id = :uid (never trust id alone).
 *   - Missing / foreign order → flash error + redirect to history.
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
// Load order — ownership enforced
// ---------------------------------------------------------------------------

$order = null;
$items = [];
$loadError = null;

try {
    $pdo = customcore_pdo();

    $orderStmt = $pdo->prepare(
        'SELECT id, user_id, order_number, status, subtotal, total,
                shipping_name, shipping_phone, shipping_addr1, shipping_addr2,
                shipping_city, shipping_prov, shipping_postal, payment_method,
                created_at, updated_at
         FROM orders
         WHERE id = :id AND user_id = :uid
         LIMIT 1'
    );
    $orderStmt->execute([':id' => $orderId, ':uid' => $userId]);
    $order = $orderStmt->fetch();

    if ($order !== false) {
        $itemStmt = $pdo->prepare(
            'SELECT id, item_type, product_id, saved_build_id, item_name,
                    quantity, unit_price, line_total, options_json,
                    build_snapshot_json, created_at
             FROM order_items
             WHERE order_id = :oid
             ORDER BY id ASC'
        );
        $itemStmt->execute([':oid' => $orderId]);
        $items = $itemStmt->fetchAll();
    }
} catch (Throwable $exception) {
    $loadError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load this order right now. Please try again later.';
}

if ($order === false || $order === null) {
    if ($loadError === null) {
        customcore_flash_error('Order not found or you do not have permission to view it.');
    } else {
        customcore_flash_error($loadError);
    }
    customcore_redirect('order-history.php');
}

$status = (string) $order['status'];
$statusClass = customcore_order_status_class($status);
$orderNumber = (string) $order['order_number'];
$total = (float) $order['total'];
$subtotal = (float) $order['subtotal'];
$dateDisplay = customcore_order_format_datetime((string) $order['created_at'], 'F j, Y \a\t g:i A');

$pageTitle = 'Order ' . $orderNumber . ' — CustomCore';
$pageDescription = 'Itemized details for order ' . $orderNumber . '.';
$pageKeywords = 'CustomCore, order details, receipt';
$currentPage = 'orders';

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section profile-page order-details-page" aria-labelledby="order-details-heading">
    <header class="profile-page__header">
        <h1 id="order-details-heading">Order <?php echo customcore_e($orderNumber); ?></h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Help centre</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">Back to order history</a>
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
            <?php else: ?>

                <div class="order-details__meta">
                    <p>
                        <span class="order-status <?php echo customcore_e($statusClass); ?>">
                            <?php echo customcore_e(customcore_order_status_label($status)); ?>
                        </span>
                        <?php if ($dateDisplay !== ''): ?>
                            <span class="order-details__date">Placed <?php echo customcore_e($dateDisplay); ?></span>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="order-details__grid">
                    <!-- Shipping -->
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

                    <!-- Payment & totals -->
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
                    </section>
                </div>

                <!-- Line items -->
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
                                        <th scope="col">Qty</th>
                                        <th scope="col">Unit price</th>
                                        <th scope="col">Line total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $isBuild = (string) $item['item_type'] === 'saved_build';
                                        $optionLines = customcore_order_decode_options(
                                            isset($item['options_json']) ? (string) $item['options_json'] : null
                                        );
                                        $buildParts = customcore_order_decode_build_snapshot(
                                            isset($item['build_snapshot_json']) ? (string) $item['build_snapshot_json'] : null
                                        );
                                        ?>
                                        <tr>
                                            <td data-label="Item">
                                                <strong><?php echo customcore_e((string) $item['item_name']); ?></strong>
                                                <?php if ($isBuild): ?>
                                                    <span class="order-confirm__badge">Build</span>
                                                <?php endif; ?>

                                                <?php if ($optionLines !== []): ?>
                                                    <ul class="order-details__options">
                                                        <?php foreach ($optionLines as $optLine): ?>
                                                            <li><?php echo customcore_e($optLine); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>

                                                <?php if ($buildParts !== []): ?>
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
                                        <td colspan="3" class="order-confirm__total-label">Total</td>
                                        <td class="order-confirm__total-value">$<?php echo customcore_e(number_format($total, 2)); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <div class="order-details__actions">
                    <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">
                        &larr; Order history
                    </a>
                    <a class="button button--primary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                        Continue shopping
                    </a>
                </div>

            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
