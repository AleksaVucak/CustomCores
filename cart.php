<?php
/**
 * CustomCore — Shopping Cart (Commit 6.1).
 *
 * File responsibility:
 *   Displays the logged-in customer's shopping cart and processes add, update,
 *   and remove actions via POST. Supports two item types:
 *     - 'product'      – catalogue products (with optional configuration options).
 *     - 'saved_build'  – custom PC builds the user has saved.
 *
 * Supported POST actions:
 *   - add_product:    Add a catalogue product (from product.php).
 *   - add_build:      Add a saved build (from saved-build.php).
 *   - update:         Change quantity of a cart item.
 *   - remove:         Remove a cart item.
 *   - clear:          Remove all items from the cart.
 *
 * Authentication requirements:
 *   Logged-in customer. Cart is per-user (database table).
 *
 * Security:
 *   - CSRF verification on all POST actions.
 *   - Server-side price verification for products (re-fetches from DB).
 *   - Ownership enforced (cart belongs to the session user).
 *   - Quantity bounds: 1–99.
 *   - Saved build ownership checked before adding.
 *
 * Database queries:
 *   - carts (get or create for user)
 *   - cart_items (CRUD)
 *   - products + product_options (price verification)
 *   - saved_builds + saved_build_items (build total verification)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/cart.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'cart';

// ---------------------------------------------------------------------------
// Handle POST actions
// ---------------------------------------------------------------------------

$cartError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = customcore_csrf_verify(
        isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
    );

    if (!$csrfOk) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect('cart.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action'])
        ? $_POST['action']
        : '';

    try {
        $pdo = customcore_pdo();
        $cartId = customcore_cart_id($pdo, $userId);

        switch ($action) {
            // -----------------------------------------------------------------
            // Add a catalogue product
            // -----------------------------------------------------------------
            case 'add_product':
                $productId = isset($_POST['product_id']) && is_string($_POST['product_id']) && ctype_digit($_POST['product_id'])
                    ? (int) $_POST['product_id']
                    : 0;
                $quantity = isset($_POST['quantity']) && is_string($_POST['quantity']) && ctype_digit($_POST['quantity'])
                    ? (int) $_POST['quantity']
                    : 1;

                if ($productId < 1) {
                    customcore_flash_error('Invalid product.');
                    customcore_redirect('cart.php');
                }

                $quantity = max(1, min(99, $quantity));

                // Verify product exists, is active, and has stock.
                $prodStmt = $pdo->prepare(
                    'SELECT id, name, base_price, stock_quantity, is_active
                     FROM products
                     WHERE id = :id
                     LIMIT 1'
                );
                $prodStmt->execute([':id' => $productId]);
                $prodRow = $prodStmt->fetch();

                if ($prodRow === false || (int) $prodRow['is_active'] !== 1) {
                    customcore_flash_error('Product not found or no longer available.');
                    customcore_redirect('cart.php');
                }

                if ((int) $prodRow['stock_quantity'] < $quantity) {
                    customcore_flash_warning('Insufficient stock for this product.');
                    customcore_redirect('product.php?id=' . $productId);
                }

                // Calculate server-trusted price with selected options.
                $basePrice = (float) $prodRow['base_price'];
                $selectedOptions = [];
                $optionsDelta = 0.00;

                // Collect posted option selections (format: option_<GroupName> = <option_id>).
                $optStmt = $pdo->prepare(
                    'SELECT id, option_group, option_label, price_delta, is_default
                     FROM product_options
                     WHERE product_id = :pid AND is_active = 1
                     ORDER BY option_group, sort_order'
                );
                $optStmt->execute([':pid' => $productId]);
                $allOptions = $optStmt->fetchAll();

                $groups = [];
                foreach ($allOptions as $opt) {
                    $groups[(string) $opt['option_group']][] = $opt;
                }

                foreach ($groups as $groupName => $groupOptions) {
                    $postKey = 'option_' . $groupName;
                    $selectedId = isset($_POST[$postKey]) && is_string($_POST[$postKey]) && ctype_digit($_POST[$postKey])
                        ? (int) $_POST[$postKey]
                        : 0;

                    $matched = false;
                    foreach ($groupOptions as $opt) {
                        if ((int) $opt['id'] === $selectedId) {
                            $optionsDelta += (float) $opt['price_delta'];
                            $selectedOptions[] = [
                                'id' => (int) $opt['id'],
                                'group' => $groupName,
                                'label' => (string) $opt['option_label'],
                                'delta' => (float) $opt['price_delta'],
                            ];
                            $matched = true;
                            break;
                        }
                    }

                    // If no valid selection, use the default.
                    if (!$matched) {
                        foreach ($groupOptions as $opt) {
                            if (!empty($opt['is_default'])) {
                                $optionsDelta += (float) $opt['price_delta'];
                                $selectedOptions[] = [
                                    'id' => (int) $opt['id'],
                                    'group' => $groupName,
                                    'label' => (string) $opt['option_label'],
                                    'delta' => (float) $opt['price_delta'],
                                ];
                                break;
                            }
                        }
                    }
                }

                $unitPrice = round($basePrice + $optionsDelta, 2);
                $optionsJson = $selectedOptions !== [] ? json_encode($selectedOptions) : null;

                customcore_cart_add_product($pdo, $cartId, $productId, $unitPrice, $quantity, $optionsJson);
                customcore_flash_success(htmlspecialchars((string) $prodRow['name'], ENT_QUOTES, 'UTF-8') . ' added to your cart.');
                customcore_redirect('cart.php');
                break;

            // -----------------------------------------------------------------
            // Add a saved build
            // -----------------------------------------------------------------
            case 'add_build':
                $savedBuildId = isset($_POST['saved_build_id']) && is_string($_POST['saved_build_id']) && ctype_digit($_POST['saved_build_id'])
                    ? (int) $_POST['saved_build_id']
                    : 0;

                if ($savedBuildId < 1) {
                    customcore_flash_error('Invalid build.');
                    customcore_redirect('cart.php');
                }

                // Verify the build belongs to this user.
                $buildStmt = $pdo->prepare(
                    'SELECT id, name, total_price
                     FROM saved_builds
                     WHERE id = :id AND user_id = :uid
                     LIMIT 1'
                );
                $buildStmt->execute([':id' => $savedBuildId, ':uid' => $userId]);
                $buildRow = $buildStmt->fetch();

                if ($buildRow === false) {
                    customcore_flash_error('Build not found or you do not own it.');
                    customcore_redirect('saved-builds.php');
                }

                $buildPrice = (float) $buildRow['total_price'];

                // Re-calculate trusted total from item prices.
                $itemStmt = $pdo->prepare(
                    'SELECT COALESCE(SUM(sbi.unit_price), 0) AS real_total
                     FROM saved_build_items sbi
                     WHERE sbi.saved_build_id = :bid'
                );
                $itemStmt->execute([':bid' => $savedBuildId]);
                $realTotal = (float) ($itemStmt->fetchColumn());
                if ($realTotal > 0) {
                    $buildPrice = $realTotal;
                }

                customcore_cart_add_build($pdo, $cartId, $savedBuildId, $buildPrice);
                customcore_flash_success(htmlspecialchars((string) $buildRow['name'], ENT_QUOTES, 'UTF-8') . ' added to your cart.');
                customcore_redirect('cart.php');
                break;

            // -----------------------------------------------------------------
            // Update quantity
            // -----------------------------------------------------------------
            case 'update':
                $itemId = isset($_POST['item_id']) && is_string($_POST['item_id']) && ctype_digit($_POST['item_id'])
                    ? (int) $_POST['item_id']
                    : 0;
                $newQty = isset($_POST['quantity']) && is_string($_POST['quantity']) && ctype_digit($_POST['quantity'])
                    ? (int) $_POST['quantity']
                    : 0;

                if ($itemId < 1) {
                    customcore_flash_error('Invalid item.');
                    customcore_redirect('cart.php');
                }

                $newQty = max(1, min(99, $newQty));

                // Ownership: verify item belongs to this user's cart.
                $verifyStmt = $pdo->prepare(
                    'SELECT ci.id, ci.item_type
                     FROM cart_items ci
                     JOIN carts c ON c.id = ci.cart_id
                     WHERE ci.id = :iid AND c.user_id = :uid
                     LIMIT 1'
                );
                $verifyStmt->execute([':iid' => $itemId, ':uid' => $userId]);
                $verifyRow = $verifyStmt->fetch();

                if ($verifyRow === false) {
                    customcore_flash_error('Cart item not found.');
                    customcore_redirect('cart.php');
                }

                // Builds are always qty 1.
                if ((string) $verifyRow['item_type'] === 'saved_build') {
                    $newQty = 1;
                }

                $updStmt = $pdo->prepare(
                    'UPDATE cart_items SET quantity = :qty WHERE id = :id'
                );
                $updStmt->execute([':qty' => $newQty, ':id' => $itemId]);

                customcore_flash_success('Cart updated.');
                customcore_redirect('cart.php');
                break;

            // -----------------------------------------------------------------
            // Remove one item
            // -----------------------------------------------------------------
            case 'remove':
                $itemId = isset($_POST['item_id']) && is_string($_POST['item_id']) && ctype_digit($_POST['item_id'])
                    ? (int) $_POST['item_id']
                    : 0;

                if ($itemId < 1) {
                    customcore_flash_error('Invalid item.');
                    customcore_redirect('cart.php');
                }

                // Ownership check.
                $verifyStmt = $pdo->prepare(
                    'DELETE ci FROM cart_items ci
                     JOIN carts c ON c.id = ci.cart_id
                     WHERE ci.id = :iid AND c.user_id = :uid'
                );
                $verifyStmt->execute([':iid' => $itemId, ':uid' => $userId]);

                customcore_flash_success('Item removed from cart.');
                customcore_redirect('cart.php');
                break;

            // -----------------------------------------------------------------
            // Clear entire cart
            // -----------------------------------------------------------------
            case 'clear':
                $clearStmt = $pdo->prepare(
                    'DELETE ci FROM cart_items ci
                     JOIN carts c ON c.id = ci.cart_id
                     WHERE c.user_id = :uid'
                );
                $clearStmt->execute([':uid' => $userId]);

                customcore_flash_success('Cart cleared.');
                customcore_redirect('cart.php');
                break;

            default:
                customcore_flash_error('Unknown action.');
                customcore_redirect('cart.php');
        }
    } catch (Throwable $exception) {
        $cartError = customcore_is_debug()
            ? $exception->getMessage()
            : 'Something went wrong processing your cart. Please try again.';
    }
}

// ---------------------------------------------------------------------------
// Load cart items for display
// ---------------------------------------------------------------------------

$cartItems = [];
$subtotal = 0.00;

try {
    $pdo = customcore_pdo();
    $cartId = customcore_cart_id($pdo, $userId);
    $cartItems = customcore_cart_items($pdo, $cartId);
    $subtotal = customcore_cart_subtotal($cartItems);
} catch (Throwable $exception) {
    $cartError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load your cart right now. Please try again later.';
}

// ---------------------------------------------------------------------------
// Page metadata
// ---------------------------------------------------------------------------

$pageTitle = 'Shopping Cart — CustomCore';
$pageDescription = 'View and manage items in your CustomCore shopping cart.';
$pageKeywords = 'CustomCore, shopping cart, checkout, gaming PC';
$currentPage = 'cart';

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section cart-page" aria-labelledby="cart-heading">
    <header class="cart-page__header">
        <h1 id="cart-heading">Shopping Cart</h1>
    </header>

    <div class="layout-split layout-split--account">
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <div class="profile-page__main">
            <?php if ($cartError !== null): ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($cartError); ?>
                </div>
            <?php endif; ?>

            <?php if ($cartItems === []): ?>
                <div class="cart-empty" role="status">
                    <p class="cart-empty__message">Your cart is empty.</p>
                    <div class="cart-empty__actions">
                        <a class="button" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                            Browse catalogue
                        </a>
                        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('saved-builds.php')); ?>">
                            Your saved builds
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="cart-items">
                    <div class="table-wrap">
                        <table class="data-table cart-table">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col" class="data-table__num">Unit price</th>
                                    <th scope="col" class="data-table__num">Qty</th>
                                    <th scope="col" class="data-table__num">Line total</th>
                                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                    <?php
                                    $isBuild = $item['item_type'] === 'saved_build';
                                    $itemName = $item['name'];
                                    $itemBrand = $item['brand'];
                                    $unitPrice = $item['unit_price'];
                                    $qty = $item['quantity'];
                                    $lineTotal = $item['line_total'];
                                    $itemLink = '';
                                    $optionsSummary = '';

                                    if ($isBuild && $item['saved_build_id'] !== null) {
                                        $itemLink = customcore_url('saved-build.php?id=' . $item['saved_build_id']);
                                    } elseif (!$isBuild && $item['product_id'] !== null) {
                                        $itemLink = customcore_url('product.php?id=' . $item['product_id']);
                                    }

                                    if (!$isBuild && $item['options_json'] !== null) {
                                        $decoded = json_decode($item['options_json'], true);
                                        if (is_array($decoded) && $decoded !== []) {
                                            $labels = [];
                                            foreach ($decoded as $optSnap) {
                                                if (isset($optSnap['label'])) {
                                                    $labels[] = (string) $optSnap['label'];
                                                }
                                            }
                                            $optionsSummary = implode(', ', $labels);
                                        }
                                    }

                                    $isUnavailable = false;
                                    if (!$isBuild && (!$item['product_active'] || $item['product_id'] === null)) {
                                        $isUnavailable = true;
                                    }
                                    ?>
                                    <tr class="cart-item<?php echo $isUnavailable ? ' cart-item--unavailable' : ''; ?>">
                                        <td class="cart-item__info">
                                            <span class="cart-item__type-badge<?php echo $isBuild ? ' cart-item__type-badge--build' : ''; ?>">
                                                <?php echo $isBuild ? 'Custom Build' : 'Product'; ?>
                                            </span>
                                            <?php if ($itemLink !== ''): ?>
                                                <a href="<?php echo customcore_e($itemLink); ?>" class="cart-item__name">
                                                    <?php echo customcore_e($itemName); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="cart-item__name"><?php echo customcore_e($itemName); ?></span>
                                            <?php endif; ?>
                                            <?php if ($itemBrand !== ''): ?>
                                                <span class="cart-item__brand"><?php echo customcore_e($itemBrand); ?></span>
                                            <?php endif; ?>
                                            <?php if ($optionsSummary !== ''): ?>
                                                <span class="cart-item__options"><?php echo customcore_e($optionsSummary); ?></span>
                                            <?php endif; ?>
                                            <?php if ($isUnavailable): ?>
                                                <span class="cart-item__badge cart-item__badge--warn">Unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="data-table__num">
                                            $<?php echo customcore_e(number_format($unitPrice, 2)); ?>
                                        </td>
                                        <td class="data-table__num">
                                            <?php if ($isBuild): ?>
                                                <span>1</span>
                                            <?php else: ?>
                                                <form method="post" action="<?php echo customcore_e(customcore_url('cart.php')); ?>" class="cart-qty-form">
                                                    <?php echo customcore_csrf_field(); ?>
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="item_id" value="<?php echo customcore_e((string) $item['id']); ?>">
                                                    <input
                                                        type="number"
                                                        name="quantity"
                                                        value="<?php echo customcore_e((string) $qty); ?>"
                                                        min="1"
                                                        max="99"
                                                        class="cart-qty-form__input"
                                                        aria-label="Quantity for <?php echo customcore_e($itemName); ?>"
                                                    >
                                                    <button type="submit" class="button button--sm cart-qty-form__btn">Update</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td class="data-table__num">
                                            $<?php echo customcore_e(number_format($lineTotal, 2)); ?>
                                        </td>
                                        <td class="data-table__num">
                                            <form method="post" action="<?php echo customcore_e(customcore_url('cart.php')); ?>" class="cart-remove-form">
                                                <?php echo customcore_csrf_field(); ?>
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="item_id" value="<?php echo customcore_e((string) $item['id']); ?>">
                                                <button type="submit" class="button button--danger button--sm" aria-label="Remove <?php echo customcore_e($itemName); ?>">
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th scope="row" colspan="3">Subtotal</th>
                                    <td class="data-table__num data-table__total">
                                        $<?php echo customcore_e(number_format($subtotal, 2)); ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="cart-actions">
                        <form method="post" action="<?php echo customcore_e(customcore_url('cart.php')); ?>" class="cart-clear-form">
                            <?php echo customcore_csrf_field(); ?>
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="button button--danger button--sm">Clear cart</button>
                        </form>

                        <a class="button" href="<?php echo customcore_e(customcore_url('checkout.php')); ?>">
                            Proceed to checkout
                        </a>
                    </div>
                </div>

                <div class="cart-continue">
                    <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                        &larr; Continue shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
