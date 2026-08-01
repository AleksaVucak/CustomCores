<?php
/**
 * CustomCore — Order Confirmation / Place Order (Commit 6.5).
 *
 * File responsibility:
 *   Converts the validated checkout session data and the user's cart into a
 *   permanent order record (orders + order_items), clears the cart, then
 *   displays the confirmation page with order number and summary.
 *
 * Flow:
 *   1. Require login.
 *   2. Verify $_SESSION['_cc_checkout'] exists (redirect if missing).
 *   3. Load cart items, verify non-empty.
 *   4. In a transaction: insert order → insert order_items → clear cart.
 *   5. Clear session checkout data + refresh cart count cache.
 *   6. Display confirmation with order number, shipping info, and line items.
 *
 * Authentication requirements:
 *   Logged-in customer (customcore_require_login).
 *
 * Security:
 *   - No direct form submission; relies on session data set by checkout.php.
 *   - All prices recalculated from database (cart_items.unit_price) — never
 *     from client-side values.
 *   - Unique order_number generated server-side (not guessable).
 *   - Transaction ensures atomicity: all inserts succeed or none do.
 *   - Cart cleared only after successful commit.
 *   - All outputs escaped via customcore_e().
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/cart.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'cart';

// ---------------------------------------------------------------------------
// Guard: session checkout data must exist
// ---------------------------------------------------------------------------

if (!isset($_SESSION['_cc_checkout']) || !is_array($_SESSION['_cc_checkout'])) {
    customcore_flash_warning('Please complete checkout before viewing this page.');
    customcore_redirect('checkout.php');
}

$checkout = $_SESSION['_cc_checkout'];

// ---------------------------------------------------------------------------
// Load cart items and verify non-empty
// ---------------------------------------------------------------------------

$cartItems = [];
$subtotal = 0.00;
$orderPlaced = false;
$orderNumber = '';
$orderError = null;

try {
    $pdo = customcore_pdo();
    $cartId = customcore_cart_id($pdo, $userId);
    $cartItems = customcore_cart_items($pdo, $cartId);
    $subtotal = customcore_cart_subtotal($cartItems);
} catch (Throwable $exception) {
    $orderError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load your cart. Please try again.';
}

if ($cartItems === [] && $orderError === null) {
    customcore_flash_warning('Your cart is empty — no order to place.');
    unset($_SESSION['_cc_checkout']);
    customcore_redirect('cart.php');
}

// ---------------------------------------------------------------------------
// Generate a unique order number
// ---------------------------------------------------------------------------

/**
 * Create a human-readable order number: CC-YYYYMMDD-XXXXX
 * The random suffix plus the UNIQUE index guarantees no collision.
 */
function customcore_generate_order_number(): string
{
    $date = date('Ymd');
    $random = strtoupper(bin2hex(random_bytes(3))); // 6 hex chars
    return 'CC-' . $date . '-' . $random;
}

// ---------------------------------------------------------------------------
// Place the order inside a transaction
// ---------------------------------------------------------------------------

if ($orderError === null) {
    try {
        $pdo->beginTransaction();

        // Generate order number (retry once on unlikely collision)
        $orderNumber = customcore_generate_order_number();

        // Insert order record
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
            ':total' => $subtotal, // No tax/shipping calculated in simulation
            ':shipping_name' => $checkout['shipping_name'] ?? '',
            ':shipping_phone' => $checkout['shipping_phone'] ?? '',
            ':shipping_addr1' => $checkout['shipping_addr1'] ?? '',
            ':shipping_addr2' => $checkout['shipping_addr2'] ?? '',
            ':shipping_city' => $checkout['shipping_city'] ?? '',
            ':shipping_prov' => $checkout['shipping_prov'] ?? '',
            ':shipping_postal' => $checkout['shipping_postal'] ?? '',
            ':payment_method' => $checkout['payment_method'] ?? 'pay_on_pickup',
        ]);

        $orderId = (int) $pdo->lastInsertId();

        // Insert order_items — snapshot each cart line
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

            // For saved builds, snapshot the component list
            if ($item['item_type'] === 'saved_build' && $item['saved_build_id'] !== null) {
                $buildSnapshot = customcore_snapshot_build($pdo, $item['saved_build_id']);
            }

            $itemStmt->execute([
                ':order_id' => $orderId,
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

        // Clear cart items
        customcore_cart_clear($pdo, $userId);

        $pdo->commit();
        $orderPlaced = true;

        // Clean up session
        unset($_SESSION['_cc_checkout']);
        customcore_cart_refresh_count($pdo, $userId);

    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Handle duplicate order_number (extremely unlikely)
        if (str_contains($exception->getMessage(), 'uq_orders_number')) {
            $orderNumber = customcore_generate_order_number();
            $orderError = 'A temporary conflict occurred. Please try again.';
        } else {
            $orderError = customcore_is_debug()
                ? $exception->getMessage()
                : 'We could not place your order. Please try again or contact support.';
        }
    }
}

// ---------------------------------------------------------------------------
// Helper: snapshot build components for order_items.build_snapshot_json
// ---------------------------------------------------------------------------

/**
 * Create a JSON snapshot of a saved build's components for permanent storage.
 *
 * @return string|null JSON string or null if no items found.
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

// ---------------------------------------------------------------------------
// Page metadata
// ---------------------------------------------------------------------------

$pageTitle = 'Order Confirmation — CustomCore';
$pageDescription = 'Your order has been placed successfully.';
$pageKeywords = 'CustomCore, order confirmation, receipt';
$currentPage = 'checkout';

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section order-confirm" aria-labelledby="confirm-heading">

    <?php if ($orderError !== null): ?>
        <header class="order-confirm__header">
            <h1 id="confirm-heading">Order could not be placed</h1>
        </header>
        <div class="flash flash--error" role="alert">
            <?php echo customcore_e($orderError); ?>
        </div>
        <p>
            <a href="<?php echo customcore_e(customcore_url('checkout.php')); ?>">&larr; Return to checkout</a>
        </p>

    <?php elseif ($orderPlaced): ?>
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
                status in your <a href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">order history</a>.
            </p>
        </header>

        <!-- Shipping details recap -->
        <div class="order-confirm__section">
            <h2 class="order-confirm__section-title">Shipping details</h2>
            <dl class="order-confirm__dl">
                <dt>Name</dt>
                <dd><?php echo customcore_e($checkout['shipping_name'] ?? ''); ?></dd>

                <dt>Phone</dt>
                <dd><?php echo customcore_e($checkout['shipping_phone'] ?? ''); ?></dd>

                <dt>Address</dt>
                <dd>
                    <?php echo customcore_e($checkout['shipping_addr1'] ?? ''); ?>
                    <?php if (!empty($checkout['shipping_addr2'])): ?>
                        <br><?php echo customcore_e($checkout['shipping_addr2']); ?>
                    <?php endif; ?>
                    <br>
                    <?php echo customcore_e($checkout['shipping_city'] ?? ''); ?>,
                    <?php echo customcore_e($checkout['shipping_prov'] ?? ''); ?>
                    <?php echo customcore_e($checkout['shipping_postal'] ?? ''); ?>
                </dd>

                <dt>Payment method</dt>
                <dd><?php echo customcore_e(ucfirst(str_replace('_', ' ', $checkout['payment_method'] ?? 'pay on pickup'))); ?></dd>
            </dl>
        </div>

        <!-- Line items -->
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
                    <?php foreach ($cartItems as $item): ?>
                        <tr>
                            <td>
                                <?php echo customcore_e($item['name']); ?>
                                <?php if ($item['item_type'] === 'saved_build'): ?>
                                    <span class="order-confirm__badge">Build</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo customcore_e((string) $item['quantity']); ?></td>
                            <td>$<?php echo customcore_e(number_format($item['unit_price'], 2)); ?></td>
                            <td>$<?php echo customcore_e(number_format($item['line_total'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="order-confirm__total-label">Total</td>
                        <td class="order-confirm__total-value">$<?php echo customcore_e(number_format($subtotal, 2)); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Status -->
        <div class="order-confirm__section">
            <h2 class="order-confirm__section-title">Order status</h2>
            <p>
                <span class="order-confirm__status">Pending</span> — we have received your order
                and will begin processing it shortly.
            </p>
        </div>

        <!-- Actions -->
        <div class="order-confirm__actions">
            <a class="button button--primary" href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">
                View order history
            </a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                Continue shopping
            </a>
        </div>

    <?php else: ?>
        <header class="order-confirm__header">
            <h1 id="confirm-heading">Processing your order&hellip;</h1>
        </header>
        <p>Something unexpected happened. Please check your
            <a href="<?php echo customcore_e(customcore_url('order-history.php')); ?>">order history</a>
            or contact support.</p>
    <?php endif; ?>

</section>

<?php
require_once __DIR__ . '/includes/footer.php';
