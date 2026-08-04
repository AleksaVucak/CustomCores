<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator order detail.
// Protected per-order screen. Shows customer, shipping snapshot, payment label, frozen line items,
// and totals. Lets an administrator change the fulfilment status and record internal notes via
// Post/Redirect/Get.
// Access: Administrator role (customcore_require_admin()).
// Security:
//   Both write actions require a valid CSRF token.
//   Status is validated against the orders.status ENUM allow-list.
//   All output escaped via customcore_e(); payment is a label only.

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/admin-orders.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

// Resolve the order id (GET on view, POST on write)
$orderId = 0;
$rawId = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['order_id'] ?? null) : ($_GET['id'] ?? null);
if (is_string($rawId) && ctype_digit($rawId)) {
    $orderId = (int) $rawId;
}

if ($orderId <= 0) {
    customcore_flash_error('Invalid order ID.');
    customcore_redirect('admin/orders.php');
}

$detailUrl = 'admin/order-details.php?id=' . $orderId;

// Handle write actions (status change / notes), CSRF + PRG
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;
    if (!customcore_csrf_verify($token)) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect($detailUrl);
    }

    $order = customcore_admin_order_fetch($pdo, $orderId);
    if ($order === null) {
        customcore_flash_error('That order could not be found.');
        customcore_redirect('admin/orders.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_status') {
        $newStatus = isset($_POST['status']) && is_string($_POST['status']) ? $_POST['status'] : '';
        if ($newStatus === (string) $order['status']) {
            customcore_flash_warning('Status is already "' . customcore_order_status_label($newStatus) . '".');
        } elseif (customcore_admin_order_update_status($pdo, $orderId, $newStatus)) {
            customcore_flash_success('Order status updated to "' . customcore_order_status_label($newStatus) . '".');
        } else {
            customcore_flash_error('That status is not valid.');
        }
    } elseif ($action === 'update_notes') {
        $notes = isset($_POST['admin_notes']) && is_string($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
        customcore_admin_order_update_notes($pdo, $orderId, $notes);
        customcore_flash_success('Administrator notes saved.');
    } else {
        customcore_flash_error('Unknown action.');
    }

    customcore_redirect($detailUrl);
}

// Load order + items for display
$order = customcore_admin_order_fetch($pdo, $orderId);
if ($order === null) {
    customcore_flash_error('That order could not be found.');
    customcore_redirect('admin/orders.php');
}

$items = customcore_admin_order_items($pdo, $orderId);
$subtotal = (float) $order['subtotal'];
$total = (float) $order['total'];
$customerName = trim((string) $order['first_name'] . ' ' . (string) $order['last_name']);

$adminNavCurrent = 'orders';
$loadAdminCss = true;
$currentPage = 'admin';

$pageTitle = 'Order ' . $order['order_number'] . ' | CustomCore Admin';
$pageDescription = 'Administrator view of a CustomCore customer order.';
$pageKeywords = 'CustomCore, admin, order';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Order detail: customer/shipping/payment, status and notes forms, line items -->
<section class="content-section admin-page admin-order-detail" aria-labelledby="admin-order-heading">
    <header class="admin-page__header">
        <h1 id="admin-order-heading">Order <?php echo customcore_e((string) $order['order_number']); ?></h1>
        <p class="admin-page__intro">
            Placed <?php echo customcore_e(customcore_order_format_datetime((string) $order['created_at'])); ?>
            · <span class="order-status <?php echo customcore_e(customcore_order_status_class((string) $order['status'])); ?>">
                <?php echo customcore_e(customcore_order_status_label((string) $order['status'])); ?>
            </span>
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/orders.php')); ?>">← Back to orders</a>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Snapshot cards: customer, shipping address, and payment/totals -->
    <div class="admin-order-detail__grid">
        <section class="admin-card" aria-labelledby="customer-heading">
            <h2 id="customer-heading" class="admin-card__title">Customer</h2>
            <dl class="admin-dl">
                <dt>Name</dt>
                <dd><?php echo customcore_e($customerName !== '' ? $customerName : 'No name'); ?></dd>
                <dt>Email</dt>
                <dd><a href="mailto:<?php echo customcore_e((string) $order['email']); ?>"><?php echo customcore_e((string) $order['email']); ?></a></dd>
                <dt>Account</dt>
                <dd>
                    <?php if ((int) $order['user_active'] === 1) : ?>
                        <span class="admin-badge admin-badge--ok">Active</span>
                    <?php else : ?>
                        <span class="admin-badge admin-badge--danger">Disabled</span>
                    <?php endif; ?>
                </dd>
            </dl>
        </section>

        <section class="admin-card" aria-labelledby="shipping-heading">
            <h2 id="shipping-heading" class="admin-card__title">Shipping</h2>
            <dl class="admin-dl">
                <dt>Name</dt>
                <dd><?php echo customcore_e((string) $order['shipping_name']); ?></dd>
                <dt>Phone</dt>
                <dd><?php echo customcore_e((string) $order['shipping_phone']); ?></dd>
                <dt>Address</dt>
                <dd>
                    <?php echo customcore_e((string) $order['shipping_addr1']); ?>
                    <?php if ((string) $order['shipping_addr2'] !== '') : ?>
                        <br><?php echo customcore_e((string) $order['shipping_addr2']); ?>
                    <?php endif; ?>
                    <br>
                    <?php echo customcore_e((string) $order['shipping_city']); ?>,
                    <?php echo customcore_e((string) $order['shipping_prov']); ?>
                    <?php echo customcore_e((string) $order['shipping_postal']); ?>
                </dd>
            </dl>
        </section>

        <section class="admin-card" aria-labelledby="payment-heading">
            <h2 id="payment-heading" class="admin-card__title">Payment &amp; totals</h2>
            <dl class="admin-dl">
                <dt>Payment method</dt>
                <dd><?php echo customcore_e(customcore_order_payment_label((string) $order['payment_method'])); ?></dd>
                <dt>Subtotal</dt>
                <dd>$<?php echo customcore_e(number_format($subtotal, 2)); ?></dd>
                <dt>Total</dt>
                <dd class="admin-order-detail__total">$<?php echo customcore_e(number_format($total, 2)); ?></dd>
            </dl>
            <p class="admin-order-detail__note">Simulated checkout, no real card data was collected.</p>
        </section>
    </div>

    <!-- Admin actions: update fulfilment status and internal notes (POST) -->
    <div class="admin-order-detail__grid admin-order-detail__grid--forms">
        <section class="admin-card" aria-labelledby="status-heading">
            <h2 id="status-heading" class="admin-card__title">Update status</h2>
            <form method="post" action="<?php echo customcore_e(customcore_url('admin/order-details.php')); ?>" class="admin-inline-form">
                <?php echo customcore_csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo customcore_e((string) $orderId); ?>">
                <label class="form-field" for="order-status">
                    <span class="form-field__label">Fulfilment status</span>
                    <select id="order-status" name="status">
                        <?php foreach (customcore_order_statuses() as $s) : ?>
                            <option value="<?php echo customcore_e($s); ?>" <?php echo (string) $order['status'] === $s ? 'selected' : ''; ?>>
                                <?php echo customcore_e(customcore_order_status_label($s)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="button button--sm">Save status</button>
            </form>
        </section>

        <section class="admin-card" aria-labelledby="notes-heading">
            <h2 id="notes-heading" class="admin-card__title">Administrator notes</h2>
            <form method="post" action="<?php echo customcore_e(customcore_url('admin/order-details.php')); ?>" class="admin-inline-form">
                <?php echo customcore_csrf_field(); ?>
                <input type="hidden" name="action" value="update_notes">
                <input type="hidden" name="order_id" value="<?php echo customcore_e((string) $orderId); ?>">
                <label class="form-field form-field--wide" for="admin-notes">
                    <span class="form-field__label">Internal notes (not shown to the customer)</span>
                    <textarea id="admin-notes" name="admin_notes" rows="4" maxlength="5000"
                              placeholder="e.g. Called customer to confirm pickup time."><?php echo customcore_e((string) ($order['admin_notes'] ?? '')); ?></textarea>
                </label>
                <button type="submit" class="button button--sm">Save notes</button>
            </form>
        </section>
    </div>

    <!-- Items ordered: empty state, otherwise frozen line-item table -->
    <section class="admin-card" aria-labelledby="items-heading">
        <h2 id="items-heading" class="admin-card__title">Items ordered</h2>
        <?php if ($items === []) : ?>
            <p>No line items were recorded for this order.</p>
        <?php else : ?>
            <!-- Line items: name/options/build, type, qty, unit price, line total -->
            <div class="admin-table-wrap">
                <table class="admin-table admin-table--order-items">
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
                        <?php foreach ($items as $item) : ?>
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
                                    <?php if (!$isBuild && $productId !== null && $productId > 0) : ?>
                                        <a href="<?php echo customcore_e(customcore_url('product.php?id=' . $productId)); ?>">
                                            <strong><?php echo customcore_e((string) $item['item_name']); ?></strong>
                                        </a>
                                    <?php else : ?>
                                        <strong><?php echo customcore_e((string) $item['item_name']); ?></strong>
                                    <?php endif; ?>

                                    <?php if ($optionLines !== []) : ?>
                                        <ul class="admin-order-detail__options">
                                            <?php foreach ($optionLines as $optLine) : ?>
                                                <li><?php echo customcore_e($optLine); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <?php if ($buildParts !== []) : ?>
                                        <details class="admin-order-detail__build">
                                            <summary>Build components (<?php echo customcore_e((string) count($buildParts)); ?>)</summary>
                                            <ul>
                                                <?php foreach ($buildParts as $part) : ?>
                                                    <li>
                                                        <?php if ($part['category'] !== '') : ?>
                                                            <span class="admin-order-detail__part-cat"><?php echo customcore_e($part['category']); ?>:</span>
                                                        <?php endif; ?>
                                                        <?php echo customcore_e($part['component']); ?>
                                                        $<?php echo customcore_e(number_format($part['price'], 2)); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Type">
                                    <?php echo $isBuild ? '<span class="admin-badge admin-badge--featured">Build</span>' : '<span class="admin-badge admin-badge--muted">Product</span>'; ?>
                                </td>
                                <td data-label="Qty"><?php echo customcore_e((string) (int) $item['quantity']); ?></td>
                                <td data-label="Unit price">$<?php echo customcore_e(number_format((float) $item['unit_price'], 2)); ?></td>
                                <td data-label="Line total">$<?php echo customcore_e(number_format((float) $item['line_total'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="4">Total</th>
                            <td>$<?php echo customcore_e(number_format($total, 2)); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
