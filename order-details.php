<?php
/**
 * CustomCore — Customer Order Details (Commit 6.6).
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

/**
 * Human-readable status label.
 */
function customcore_order_details_status_label(string $status): string
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

/**
 * Human-readable payment method label.
 */
function customcore_order_payment_label(string $method): string
{
    $labels = [
        'pay_on_pickup' => 'Pay on pickup',
        'simulated_credit' => 'Credit card (simulated)',
        'simulated_debit' => 'Debit card (simulated)',
        'simulated_paypal' => 'PayPal (simulated)',
    ];

    return $labels[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

/**
 * Decode frozen options JSON into a readable string.
 *
 * @return list<string>
 */
function customcore_order_decode_options(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $lines = [];
    foreach ($decoded as $key => $value) {
        if (is_array($value)) {
            // Prefer structured option rows: { group, label, delta }
            if (isset($value['group'], $value['label'])) {
                $line = (string) $value['group'] . ': ' . (string) $value['label'];
                $delta = (float) ($value['delta'] ?? $value['price_delta'] ?? 0);
                if ($delta != 0.0) {
                    $line .= ' (' . ($delta > 0 ? '+' : '') . '$' . number_format($delta, 2) . ')';
                }
                $lines[] = $line;
                continue;
            }
            $value = implode(', ', array_map('strval', $value));
        }
        if (is_string($key) && !is_numeric($key)) {
            $lines[] = $key . ': ' . (string) $value;
        } else {
            $lines[] = (string) $value;
        }
    }

    return $lines;
}

/**
 * Decode frozen build snapshot JSON.
 *
 * @return list<array{category:string,component:string,price:float}>
 */
function customcore_order_decode_build_snapshot(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    $parts = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $parts[] = [
            'category' => (string) ($row['category'] ?? ''),
            'component' => (string) ($row['component'] ?? ''),
            'price' => (float) ($row['price'] ?? 0),
        ];
    }

    return $parts;
}

$status = (string) $order['status'];
$statusClass = 'order-status--' . preg_replace('/[^a-z]/', '', strtolower($status));
$orderNumber = (string) $order['order_number'];
$total = (float) $order['total'];
$subtotal = (float) $order['subtotal'];
$createdAt = (string) $order['created_at'];
$dateDisplay = '';
$ts = strtotime($createdAt);
if ($ts !== false) {
    $dateDisplay = date('F j, Y \a\t g:i A', $ts);
}

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
                            <?php echo customcore_e(customcore_order_details_status_label($status)); ?>
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
