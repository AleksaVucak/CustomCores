<?php
/**
 * CustomCore — Shopping Cart (Commits 6.1–6.2).
 *
 * File responsibility:
 *   Displays the logged-in customer's shopping cart and processes add, update,
 *   remove, and clear actions via POST. Supports two item types:
 *     - 'product'      – catalogue products (with optional configuration options).
 *     - 'saved_build'  – custom PC builds the user has saved.
 *
 * Commit 6.2 — quantity and removal controls:
 *   - Per-line quantity inputs with stock-aware max
 *   - Bulk "Update cart" for all product lines at once
 *   - Per-line Remove and Clear cart (with confirm prompts)
 *   - Server-side clamps keep line totals and subtotal accurate
 *   - Client-side live line-total preview (assets/js/cart.js)
 *
 * Supported POST actions:
 *   - add_product:    Add a catalogue product (from product.php).
 *   - add_build:      Add a saved build (from saved-build.php).
 *   - update:         Change quantity of a single cart item.
 *   - update_all:     Bulk-update quantities from the cart form.
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
 *   - Quantity bounds: 0 (remove) or 1–99, never above stock.
 *   - Saved build ownership checked before adding.
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

                $basePrice = (float) $prodRow['base_price'];
                $selectedOptions = [];
                $optionsDelta = 0.00;

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
                customcore_cart_refresh_count($pdo, $userId);
                customcore_flash_success((string) $prodRow['name'] . ' added to your cart.');
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
                customcore_cart_refresh_count($pdo, $userId);
                customcore_flash_success((string) $buildRow['name'] . ' added to your cart.');
                customcore_redirect('cart.php');
                break;

            // -----------------------------------------------------------------
            // Update a single line quantity (Commit 6.2)
            // -----------------------------------------------------------------
            case 'update':
                $itemId = isset($_POST['item_id']) && is_string($_POST['item_id']) && ctype_digit($_POST['item_id'])
                    ? (int) $_POST['item_id']
                    : 0;
                $newQty = isset($_POST['quantity']) && is_string($_POST['quantity']) && ctype_digit($_POST['quantity'])
                    ? (int) $_POST['quantity']
                    : -1;

                if ($itemId < 1) {
                    customcore_flash_error('Invalid item.');
                    customcore_redirect('cart.php');
                }

                $result = customcore_cart_update_quantity($pdo, $userId, $itemId, $newQty);

                if (!$result['ok']) {
                    customcore_flash_warning($result['message']);
                } elseif ($result['removed']) {
                    customcore_flash_success($result['message']);
                } elseif ($result['message'] !== 'Cart updated.') {
                    customcore_flash_warning($result['message']);
                } else {
                    customcore_flash_success('Cart updated.');
                }

                customcore_cart_refresh_count($pdo, $userId);
                customcore_redirect('cart.php');
                break;

            // -----------------------------------------------------------------
            // Bulk update all quantities (Commit 6.2)
            // -----------------------------------------------------------------
            case 'update_all':
                $rawQuantities = isset($_POST['quantities']) && is_array($_POST['quantities'])
                    ? $_POST['quantities']
                    : [];

                if ($rawQuantities === []) {
                    customcore_flash_warning('No quantities were submitted.');
                    customcore_redirect('cart.php');
                }

                $bulk = customcore_cart_update_quantities($pdo, $userId, $rawQuantities);

                if ($bulk['messages'] !== []) {
                    foreach ($bulk['messages'] as $msg) {
                        customcore_flash_warning($msg);
                    }
                }

                if ($bulk['updated'] > 0 || $bulk['removed'] > 0) {
                    $parts = [];
                    if ($bulk['updated'] > 0) {
                        $parts[] = $bulk['updated'] === 1
                            ? '1 quantity updated'
                            : $bulk['updated'] . ' quantities updated';
                    }
                    if ($bulk['removed'] > 0) {
                        $parts[] = $bulk['removed'] === 1
                            ? '1 item removed'
                            : $bulk['removed'] . ' items removed';
                    }
                    customcore_flash_success(implode('; ', $parts) . '.');
                } elseif ($bulk['messages'] === []) {
                    customcore_flash_success('Cart updated.');
                }

                customcore_cart_refresh_count($pdo, $userId);
                customcore_redirect('cart.php');
                break;

            // -----------------------------------------------------------------
            // Remove one item (Commit 6.2)
            // -----------------------------------------------------------------
            case 'remove':
                $itemId = isset($_POST['item_id']) && is_string($_POST['item_id']) && ctype_digit($_POST['item_id'])
                    ? (int) $_POST['item_id']
                    : 0;

                if ($itemId < 1) {
                    customcore_flash_error('Invalid item.');
                    customcore_redirect('cart.php');
                }

                if (customcore_cart_remove_item($pdo, $userId, $itemId)) {
                    customcore_flash_success('Item removed from cart.');
                } else {
                    customcore_flash_error('Cart item not found.');
                }

                customcore_cart_refresh_count($pdo, $userId);
                customcore_redirect('cart.php');
                break;

            // -----------------------------------------------------------------
            // Clear entire cart (Commit 6.2)
            // -----------------------------------------------------------------
            case 'clear':
                $cleared = customcore_cart_clear($pdo, $userId);

                if ($cleared > 0) {
                    customcore_flash_success('Cart cleared.');
                } else {
                    customcore_flash_warning('Your cart was already empty.');
                }

                customcore_cart_refresh_count($pdo, $userId);
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
$itemCount = 0;

try {
    $pdo = customcore_pdo();
    $cartId = customcore_cart_id($pdo, $userId);
    $cartItems = customcore_cart_items($pdo, $cartId);
    $subtotal = customcore_cart_subtotal($cartItems);
    foreach ($cartItems as $ci) {
        $itemCount += (int) $ci['quantity'];
    }
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

<section class="content-section cart-page" aria-labelledby="cart-heading" data-cart-page>
    <header class="cart-page__header">
        <h1 id="cart-heading">Shopping Cart</h1>
        <?php if ($cartItems !== []): ?>
            <p class="cart-page__count">
                <?php echo customcore_e((string) $itemCount); ?>
                <?php echo $itemCount === 1 ? 'item' : 'items'; ?>
            </p>
        <?php endif; ?>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/index.html')); ?>">Help centre</a>
            — cart quantities, remove, and clear controls keep your total accurate before checkout.
        </p>
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
                    <?php $emptyCartImage = customcore_image_url('assets/images/ui/empty-cart.jpg'); ?>
                    <?php if ($emptyCartImage !== null) : ?>
                        <img
                            class="cart-empty__image"
                            src="<?php echo customcore_e($emptyCartImage); ?>"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            width="320"
                            height="240"
                        >
                    <?php endif; ?>
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
                    <form
                        id="cart-update-form"
                        class="cart-update-form"
                        method="post"
                        action="<?php echo customcore_e(customcore_url('cart.php')); ?>"
                        data-cart-update-form
                    >
                        <?php echo customcore_csrf_field(); ?>
                        <input type="hidden" name="action" value="update_all">

                        <div class="table-wrap cart-table-wrap">
                            <table class="data-table cart-table" data-cart-table>
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
                                        $stockMax = 99;

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
                                        if (!$isBuild) {
                                            if (!$item['product_active'] || $item['product_id'] === null) {
                                                $isUnavailable = true;
                                            }
                                            $stockMax = max(1, min(99, (int) $item['stock']));
                                            if ((int) $item['stock'] < 1) {
                                                $isUnavailable = true;
                                                $stockMax = 1;
                                            }
                                        }
                                        ?>
                                        <tr
                                            class="cart-item<?php echo $isUnavailable ? ' cart-item--unavailable' : ''; ?>"
                                            data-cart-item
                                            data-unit-price="<?php echo customcore_e(number_format($unitPrice, 2, '.', '')); ?>"
                                            data-item-type="<?php echo customcore_e($item['item_type']); ?>"
                                        >
                                            <td class="cart-item__info" data-label="Item">
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
                                                <?php elseif (!$isBuild && (int) $item['stock'] > 0 && (int) $item['stock'] <= 5): ?>
                                                    <span class="cart-item__badge cart-item__badge--stock">
                                                        Only <?php echo customcore_e((string) (int) $item['stock']); ?> left
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="data-table__num cart-item__unit" data-label="Unit price">
                                                $<?php echo customcore_e(number_format($unitPrice, 2)); ?>
                                            </td>
                                            <td class="data-table__num cart-item__qty" data-label="Qty">
                                                <?php if ($isBuild): ?>
                                                    <span class="cart-item__qty-fixed" aria-label="Quantity 1 (custom builds are limited to one)">1</span>
                                                <?php else: ?>
                                                    <div class="cart-qty-controls">
                                                        <button
                                                            type="button"
                                                            class="cart-qty-controls__btn"
                                                            data-cart-qty-dec
                                                            aria-label="Decrease quantity for <?php echo customcore_e($itemName); ?>"
                                                            <?php echo $isUnavailable ? ' disabled' : ''; ?>
                                                        >&minus;</button>
                                                        <?php if ($isUnavailable): ?>
                                                            <input
                                                                type="number"
                                                                value="<?php echo customcore_e((string) $qty); ?>"
                                                                min="0"
                                                                max="<?php echo customcore_e((string) $stockMax); ?>"
                                                                class="cart-qty-form__input"
                                                                data-cart-qty
                                                                aria-label="Quantity for <?php echo customcore_e($itemName); ?>"
                                                                disabled
                                                            >
                                                            <input type="hidden" name="quantities[<?php echo customcore_e((string) $item['id']); ?>]" value="<?php echo customcore_e((string) $qty); ?>">
                                                        <?php else: ?>
                                                            <input
                                                                type="number"
                                                                name="quantities[<?php echo customcore_e((string) $item['id']); ?>]"
                                                                value="<?php echo customcore_e((string) $qty); ?>"
                                                                min="0"
                                                                max="<?php echo customcore_e((string) $stockMax); ?>"
                                                                class="cart-qty-form__input"
                                                                data-cart-qty
                                                                aria-label="Quantity for <?php echo customcore_e($itemName); ?>"
                                                            >
                                                        <?php endif; ?>
                                                        <button
                                                            type="button"
                                                            class="cart-qty-controls__btn"
                                                            data-cart-qty-inc
                                                            aria-label="Increase quantity for <?php echo customcore_e($itemName); ?>"
                                                            <?php echo $isUnavailable ? ' disabled' : ''; ?>
                                                        >+</button>
                                                    </div>
                                                    <?php if (!$isUnavailable): ?>
                                                        <span class="cart-item__qty-hint">Set to 0 to remove</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="data-table__num cart-item__line" data-label="Line total">
                                                <span data-cart-line-total>
                                                    $<?php echo customcore_e(number_format($lineTotal, 2)); ?>
                                                </span>
                                            </td>
                                            <td class="data-table__num cart-item__actions" data-label="Actions">
                                                <button
                                                    type="submit"
                                                    class="button button--danger button--sm"
                                                    form="cart-remove-<?php echo customcore_e((string) $item['id']); ?>"
                                                    data-cart-remove
                                                    data-item-name="<?php echo customcore_e($itemName); ?>"
                                                    aria-label="Remove <?php echo customcore_e($itemName); ?>"
                                                >
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th scope="row" colspan="3">Subtotal</th>
                                        <td class="data-table__num data-table__total">
                                            <span data-cart-subtotal>
                                                $<?php echo customcore_e(number_format($subtotal, 2)); ?>
                                            </span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="cart-actions">
                            <div class="cart-actions__left">
                                <button type="submit" class="button button--secondary" data-cart-update-submit>
                                    Update cart
                                </button>
                                <button
                                    type="submit"
                                    class="button button--danger button--sm"
                                    form="cart-clear-form"
                                    data-cart-clear
                                >
                                    Clear cart
                                </button>
                            </div>

                            <a class="button" href="<?php echo customcore_e(customcore_url('checkout.php')); ?>">
                                Proceed to checkout
                            </a>
                        </div>
                    </form>

                    <?php foreach ($cartItems as $item): ?>
                        <form
                            id="cart-remove-<?php echo customcore_e((string) $item['id']); ?>"
                            method="post"
                            action="<?php echo customcore_e(customcore_url('cart.php')); ?>"
                            class="visually-hidden"
                            hidden
                        >
                            <?php echo customcore_csrf_field(); ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="item_id" value="<?php echo customcore_e((string) $item['id']); ?>">
                        </form>
                    <?php endforeach; ?>

                    <form
                        id="cart-clear-form"
                        method="post"
                        action="<?php echo customcore_e(customcore_url('cart.php')); ?>"
                        class="visually-hidden"
                        hidden
                    >
                        <?php echo customcore_csrf_field(); ?>
                        <input type="hidden" name="action" value="clear">
                    </form>
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
