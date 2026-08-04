<?php
/**
 * CustomCore — Order Confirmation / Place Order (Commits 6.5 + 6.6).
 *
 * File responsibility:
 *   Two modes:
 *   1. Place — when $_SESSION['_cc_checkout'] exists, convert the cart into a
 *      permanent order (orders + order_items), clear the cart, then redirect
 *      to this page with ?id= so the confirmation is loaded from the database.
 *   2. View — when ?id=N is provided, load that order (owner-scoped) and show
 *      the confirmation number and summary matching the saved DB record.
 *
 * Authentication requirements:
 *   Logged-in customer (customcore_require_login).
 *
 * Security:
 *   - Prices snapshotted from cart_items (DB), never client totals.
 *   - Unique order_number generated server-side.
 *   - Transaction ensures atomicity.
 *   - View mode enforces user_id ownership on every load.
 *   - All outputs escaped via customcore_e().
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/orders.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'orders';

/**
 * Create a human-readable order number: CC-YYYYMMDD-XXXXXX
 */
function customcore_generate_order_number(): string
{
    $date = date('Ymd');
    $random = strtoupper(bin2hex(random_bytes(3)));
    return 'CC-' . $date . '-' . $random;
}

/**
 * Create a JSON snapshot of a saved build's components for permanent storage.
 */
function customcore_snapshot_build(PDO $pdo, int $buildId): ?string
{
    $stmt = $pdo->prepare(
        'SELECT sbi.component_id, c.name AS component_name,
                cc.name AS category_name, sbi.unit_price
         FROM saved_build_items sbi
         JOIN components c ON c.id = sbi.component_id
         JOIN component_categories cc ON cc.id = c.category_id
         WHERE sbi.saved_build_id = :bid
         ORDER BY cc.sort_order ASC, c.name ASC'
    );
    $stmt->execute([':bid' => $buildId]);
    $rows = $stmt->fetchAll();

    if ($rows === []) {
        return null;
    }

    $snapshot = [];
    foreach ($rows as $row) {
        $snapshot[] = [
            'category' => $row['category_name'],
            'component' => $row['component_name'],
            'price' => (float) $row['unit_price'],
        ];
    }

    return json_encode($snapshot, JSON_UNESCAPED_UNICODE);
}

$orderError = null;
$order = null;
$items = [];

// ---------------------------------------------------------------------------
// MODE A — Place order from checkout session, then redirect to view mode
// ---------------------------------------------------------------------------

if (isset($_SESSION['_cc_checkout']) && is_array($_SESSION['_cc_checkout'])) {
    $checkout = $_SESSION['_cc_checkout'];

    try {
        $pdo = customcore_pdo();
        $cartId = customcore_cart_id($pdo, $userId);
        $cartItems = customcore_cart_items($pdo, $cartId);
        $subtotal = customcore_cart_subtotal($cartItems);

        if ($cartItems === []) {
            unset($_SESSION['_cc_checkout']);
            customcore_flash_warning('Your cart is empty — no order to place.');
            customcore_redirect('cart.php');
        }

        $pdo->beginTransaction();

        $orderNumber = customcore_generate_order_number();

        $orderStmt = $pdo->prepare(
            'INSERT INTO orders
                (user_id, order_number, status, subtotal, total,
                 shipping_name, shipping_phone, shipping_addr1, shipping_addr2,
                 shipping_city, shipping_prov, shipping_postal, payment_method)
             VALUES
                (:user_id, :order_number, :status, :subtotal, :total,
                 :shipping_name, :shipping_phone, :shipping_addr1, :shipping_addr2,
                 :shipping_city, :shipping_prov, :shipping_postal, :payment_method)'
        );
        $orderStmt->execute([
            ':user_id' => $userId,
            ':order_number' => $orderNumber,
            ':status' => 'pending',
            ':subtotal' => $subtotal,
            ':total' => $subtotal,
            ':shipping_name' => $checkout['shipping_name'] ?? '',
            ':shipping_phone' => $checkout['shipping_phone'] ?? '',
            ':shipping_addr1' => $checkout['shipping_addr1'] ?? '',
            ':shipping_addr2' => $checkout['shipping_addr2'] ?? '',
            ':shipping_city' => $checkout['shipping_city'] ?? '',
            ':shipping_prov' => $checkout['shipping_prov'] ?? '',
            ':shipping_postal' => $checkout['shipping_postal'] ?? '',
            ':payment_method' => $checkout['payment_method'] ?? 'pay_on_pickup',
        ]);

        $newOrderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items
                (order_id, item_type, product_id, saved_build_id,
                 item_name, quantity, unit_price, line_total,
                 options_json, build_snapshot_json)
             VALUES
                (:order_id, :item_type, :product_id, :saved_build_id,
                 :item_name, :quantity, :unit_price, :line_total,
                 :options_json, :build_snapshot_json)'
        );

        foreach ($cartItems as $item) {
            $buildSnapshot = null;
            if ($item['item_type'] === 'saved_build' && $item['saved_build_id'] !== null) {
                $buildSnapshot = customcore_snapshot_build($pdo, $item['saved_build_id']);
            }

            $itemStmt->execute([
                ':order_id' => $newOrderId,
                ':item_type' => $item['item_type'],
                ':product_id' => $item['product_id'],
                ':saved_build_id' => $item['saved_build_id'],
                ':item_name' => $item['name'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':line_total' => $item['line_total'],
                ':options_json' => $item['options_json'],
                ':build_snapshot_json' => $buildSnapshot,
            ]);
        }

        customcore_cart_clear($pdo, $userId);
        $pdo->commit();

        unset($_SESSION['_cc_checkout']);
        customcore_cart_refresh_count($pdo, $userId);

        // PRG: confirmation is always loaded from the database.
        customcore_redirect('order-confirmation.php?id=' . $newOrderId);
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $orderError = customcore_is_debug()
            ? $exception->getMessage()
            : 'We could not place your order. Please try again or contact support.';
    }
}

// ---------------------------------------------------------------------------
// MODE B — View confirmation from database (owner-scoped)
// ---------------------------------------------------------------------------

$viewId = 0;
if (isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id'])) {
    $viewId = (int) $_GET['id'];
}

if ($orderError === null && $viewId > 0) {
    try {
        $pdo = customcore_pdo();
        $order = customcore_order_fetch_owned($pdo, $viewId, $userId);

        if ($order === null) {
            customcore_flash_error('Order not found or you do not have permission to view it.');
            customcore_redirect('order-history.php');
        }

        $items = customcore_order_fetch_items($pdo, $viewId, $userId);
    } catch (Throwable $exception) {
        $orderError = customcore_is_debug()
            ? $exception->getMessage()
            : 'We could not load your order confirmation. Please try again.';
    }
} elseif ($orderError === null) {
    customcore_flash_warning('Please complete checkout before viewing this page.');
    customcore_redirect('checkout.php');
}

// ---------------------------------------------------------------------------
// Page metadata
// ---------------------------------------------------------------------------

$orderNumber = $order !== null ? (string) $order['order_number'] : '';
$pageTitle = $orderNumber !== ''
    ? 'Order Confirmation ' . $orderNumber . ' — CustomCore'
    : 'Order Confirmation — CustomCore';
$pageDescription = 'Your order has been placed successfully.';
$pageKeywords = 'CustomCore, order confirmation, receipt';
$currentPage = 'orders';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Order confirmation page: place-failure notice or placed-order receipt -->
<section class="content-section order-confirm" aria-labelledby="confirm-heading">

    <!-- Failure state when the order could not be placed -->
    <?php if ($orderError !== null): ?>
        <header class="order-confirm__header">
            <h1 id="confirm-heading">Order could not be placed</h1>
        </header>
        <div class="flash flash--error" role="alert">
            <?php echo customcore_e($orderError); ?>
        </div>
        <p>
            <a href="<?php echo customcore_e(customcore_url('checkout.php')); ?>">&larr; Return to checkout</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">Order history</a>
        </p>

    <?php elseif ($order !== null): ?>
        <?php
        $total = (float) $order['total'];
        $status = (string) $order['status'];
        ?>
        <!-- Success header with confirmation number and tracking links -->
        <header class="order-confirm__header">
            <h1 id="confirm-heading">Order placed successfully!</h1>
            <p class="order-confirm__subtitle">
                Thank you for your order. Your confirmation number is:
            </p>
            <p class="order-confirm__number" aria-label="Order number">
                <?php echo customcore_e($orderNumber); ?>
            </p>
            <p class="order-confirm__note">
                Please save this number for your records. You can track your order
                status in your <a href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">order history</a>
                or view the
                <a href="<?php echo customcore_e(customcore_url('order-details.php?id=' . (int) $order['id'])); ?>">full order details</a>.
            </p>
            <p class="context-help">
                Help:
                <a href="<?php echo customcore_e(customcore_url('help/orders.html#confirmation')); ?>">Orders guide</a>
                — what your confirmation number means and how to track this order.
            </p>
        </header>

        <!-- Shipping details snapshot -->
        <div class="order-confirm__section">
            <h2 class="order-confirm__section-title">Shipping details</h2>
            <dl class="order-confirm__dl">
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

                <dt>Payment method</dt>
                <dd><?php echo customcore_e(customcore_order_payment_label((string) $order['payment_method'])); ?></dd>
            </dl>
        </div>

        <!-- Ordered items table with per-line and order totals -->
        <div class="order-confirm__section">
            <h2 class="order-confirm__section-title">Items ordered</h2>
            <table class="order-confirm__table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Unit price</th>
                        <th>Line total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <?php echo customcore_e((string) $item['item_name']); ?>
                                <?php if ((string) $item['item_type'] === 'saved_build'): ?>
                                    <span class="order-confirm__badge">Build</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo customcore_e((string) (int) $item['quantity']); ?></td>
                            <td>$<?php echo customcore_e(number_format((float) $item['unit_price'], 2)); ?></td>
                            <td>$<?php echo customcore_e(number_format((float) $item['line_total'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <!-- Wide total row: spans the 4-column table on tablet/desktop. -->
                    <tr class="order-confirm__total-row order-confirm__total-row--wide">
                        <td colspan="3" class="order-confirm__total-label">Total</td>
                        <td class="order-confirm__total-value">$<?php echo customcore_e(number_format($total, 2)); ?></td>
                    </tr>
                    <!-- Narrow total row: the unit-price column is hidden on phones, so this
                         row uses colspan="2" to stay aligned with the 3 visible columns and
                         avoid a phantom 4th column (which would force horizontal overflow). -->
                    <tr class="order-confirm__total-row order-confirm__total-row--narrow">
                        <td colspan="2" class="order-confirm__total-label">Total</td>
                        <td class="order-confirm__total-value">$<?php echo customcore_e(number_format($total, 2)); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Current order status -->
        <div class="order-confirm__section">
            <h2 class="order-confirm__section-title">Order status</h2>
            <p>
                <span class="order-confirm__status"><?php echo customcore_e(customcore_order_status_label($status)); ?></span>
                — we have received your order and will begin processing it shortly.
            </p>
        </div>

        <!-- Post-order actions: details, history, keep shopping -->
        <div class="order-confirm__actions">
            <a class="button button--primary" href="<?php echo customcore_e(customcore_url('order-details.php?id=' . (int) $order['id'])); ?>">
                View full details
            </a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">
                Order history
            </a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                Continue shopping
            </a>
        </div>

    <?php endif; ?>

</section>

<?php
require_once __DIR__ . '/includes/footer.php';
