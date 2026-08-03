<?php
/**
 * CustomCore — Customer Wishlist (Commit 7.1).
 *
 * File responsibility:
 *   Displays the logged-in customer's wishlist and processes add, remove,
 *   move-to-cart, and clear actions via POST. The wishlist holds catalogue
 *   products only; each product appears at most once.
 *
 * Supported POST actions:
 *   - add:           Add a product to the wishlist (from product/catalogue).
 *   - remove:        Remove a single product from the wishlist.
 *   - move_to_cart:  Add the product to the cart (default configuration) and
 *                    remove it from the wishlist.
 *   - clear:         Remove all products from the wishlist.
 *
 * Authentication requirements:
 *   Logged-in customer. Wishlist is per-user (private) — every query is scoped
 *   to the session user_id.
 *
 * Security:
 *   - CSRF verification on all POST actions.
 *   - Ownership enforced (wishlist belongs to the session user).
 *   - Move-to-cart re-fetches the product and recomputes the trusted price
 *     from the database (default options); client values are never trusted.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/wishlist.php';
require_once __DIR__ . '/includes/cart.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'wishlist';

/**
 * Compute the default configuration (trusted unit price + options snapshot)
 * for a product, mirroring the "no options submitted" path used by cart.php.
 *
 * @return array{unit_price: float, options_json: ?string}
 */
function customcore_wishlist_default_config(PDO $pdo, int $productId, float $basePrice): array
{
    $optStmt = $pdo->prepare(
        'SELECT id, option_group, option_label, price_delta, is_default
         FROM product_options
         WHERE product_id = :pid AND is_active = 1
         ORDER BY option_group ASC, sort_order ASC, id ASC'
    );
    $optStmt->execute([':pid' => $productId]);
    $rows = $optStmt->fetchAll();

    if ($rows === []) {
        return ['unit_price' => round($basePrice, 2), 'options_json' => null];
    }

    // Group options so we can pick one default per group.
    $groups = [];
    foreach ($rows as $row) {
        $groups[(string) $row['option_group']][] = $row;
    }

    $selectedOptions = [];
    $optionsDelta = 0.00;

    foreach ($groups as $groupName => $groupOptions) {
        $chosen = null;
        foreach ($groupOptions as $opt) {
            if (!empty($opt['is_default'])) {
                $chosen = $opt;
                break;
            }
        }
        // Fall back to the first option if no explicit default exists.
        if ($chosen === null) {
            $chosen = $groupOptions[0];
        }

        $optionsDelta += (float) $chosen['price_delta'];
        $selectedOptions[] = [
            'id' => (int) $chosen['id'],
            'group' => $groupName,
            'label' => (string) $chosen['option_label'],
            'delta' => (float) $chosen['price_delta'],
        ];
    }

    return [
        'unit_price' => round($basePrice + $optionsDelta, 2),
        'options_json' => $selectedOptions !== [] ? json_encode($selectedOptions) : null,
    ];
}

// ---------------------------------------------------------------------------
// Handle POST actions
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = customcore_csrf_verify(
        isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
    );

    if (!$csrfOk) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect('wishlist.php');
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    $productId = isset($_POST['product_id']) && is_string($_POST['product_id']) && ctype_digit($_POST['product_id'])
        ? (int) $_POST['product_id']
        : 0;

    // Where to return after add / move actions (safe local path only).
    $returnTo = 'wishlist.php';
    if (isset($_POST['return_to']) && is_string($_POST['return_to'])
        && customcore_is_safe_return_target($_POST['return_to'])) {
        $returnTo = $_POST['return_to'];
    }

    try {
        $pdo = customcore_pdo();

        switch ($action) {
            // -----------------------------------------------------------------
            // Add a product to the wishlist
            // -----------------------------------------------------------------
            case 'add':
                if ($productId < 1) {
                    customcore_flash_error('Invalid product.');
                    customcore_redirect($returnTo);
                }

                $prodStmt = $pdo->prepare(
                    'SELECT id, name, is_active FROM products WHERE id = :id LIMIT 1'
                );
                $prodStmt->execute([':id' => $productId]);
                $prodRow = $prodStmt->fetch();

                if ($prodRow === false || (int) $prodRow['is_active'] !== 1) {
                    customcore_flash_error('Product not found or no longer available.');
                    customcore_redirect($returnTo);
                }

                $wishlistId = customcore_wishlist_id($pdo, $userId);
                $added = customcore_wishlist_add($pdo, $wishlistId, $productId);

                if ($added) {
                    customcore_flash_success((string) $prodRow['name'] . ' added to your wishlist.');
                } else {
                    customcore_flash_warning((string) $prodRow['name'] . ' is already on your wishlist.');
                }
                customcore_redirect($returnTo);
                break;

            // -----------------------------------------------------------------
            // Remove a single product
            // -----------------------------------------------------------------
            case 'remove':
                if ($productId < 1) {
                    customcore_flash_error('Invalid product.');
                    customcore_redirect('wishlist.php');
                }

                $removed = customcore_wishlist_remove($pdo, $userId, $productId);
                if ($removed > 0) {
                    customcore_flash_success('Item removed from your wishlist.');
                } else {
                    customcore_flash_warning('That item was not on your wishlist.');
                }
                customcore_redirect('wishlist.php');
                break;

            // -----------------------------------------------------------------
            // Move a product to the cart (default configuration) + remove
            // -----------------------------------------------------------------
            case 'move_to_cart':
                if ($productId < 1) {
                    customcore_flash_error('Invalid product.');
                    customcore_redirect('wishlist.php');
                }

                // Must currently be on the wishlist (ownership + presence).
                if (!customcore_wishlist_contains($pdo, $userId, $productId)) {
                    customcore_flash_warning('That item is no longer on your wishlist.');
                    customcore_redirect('wishlist.php');
                }

                $prodStmt = $pdo->prepare(
                    'SELECT id, name, base_price, stock_quantity, is_active
                     FROM products WHERE id = :id LIMIT 1'
                );
                $prodStmt->execute([':id' => $productId]);
                $prodRow = $prodStmt->fetch();

                if ($prodRow === false || (int) $prodRow['is_active'] !== 1) {
                    customcore_flash_error('Product not found or no longer available.');
                    customcore_redirect('wishlist.php');
                }

                if ((int) $prodRow['stock_quantity'] < 1) {
                    customcore_flash_warning((string) $prodRow['name'] . ' is out of stock.');
                    customcore_redirect('wishlist.php');
                }

                $config = customcore_wishlist_default_config(
                    $pdo,
                    $productId,
                    (float) $prodRow['base_price']
                );

                $cartId = customcore_cart_id($pdo, $userId);
                customcore_cart_add_product(
                    $pdo,
                    $cartId,
                    $productId,
                    $config['unit_price'],
                    1,
                    $config['options_json']
                );
                customcore_cart_refresh_count($pdo, $userId);

                // Remove from wishlist once safely in the cart.
                customcore_wishlist_remove($pdo, $userId, $productId);

                customcore_flash_success((string) $prodRow['name'] . ' moved to your cart.');
                customcore_redirect('wishlist.php');
                break;

            // -----------------------------------------------------------------
            // Clear the whole wishlist
            // -----------------------------------------------------------------
            case 'clear':
                $cleared = customcore_wishlist_clear($pdo, $userId);
                if ($cleared > 0) {
                    customcore_flash_success('Your wishlist has been cleared.');
                } else {
                    customcore_flash_warning('Your wishlist is already empty.');
                }
                customcore_redirect('wishlist.php');
                break;

            default:
                customcore_flash_error('Unknown action.');
                customcore_redirect('wishlist.php');
                break;
        }
    } catch (Throwable $exception) {
        customcore_flash_error(
            customcore_is_debug()
                ? $exception->getMessage()
                : 'Something went wrong updating your wishlist. Please try again.'
        );
        customcore_redirect('wishlist.php');
    }
}

// ---------------------------------------------------------------------------
// Load wishlist for display
// ---------------------------------------------------------------------------

$items = [];
$loadError = null;

try {
    $pdo = customcore_pdo();
    $items = customcore_wishlist_items($pdo, $userId);
} catch (Throwable $exception) {
    $loadError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load your wishlist right now. Please try again later.';
}

$pageTitle = 'Wishlist — CustomCore';
$pageDescription = 'Products you have saved to buy later on CustomCore.';
$pageKeywords = 'CustomCore, wishlist, saved products, gaming PC';
$currentPage = 'wishlist';

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section profile-page wishlist-page" aria-labelledby="wishlist-heading">
    <header class="profile-page__header">
        <h1 id="wishlist-heading">Wishlist</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/catalogue.html#wishlist')); ?>">Wishlist guide</a>
            — save products to buy later and move them to your cart when ready.
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
            <?php elseif ($items === []): ?>
                <div class="wishlist-empty">
                    <?php $emptyWishlistImage = customcore_image_url('assets/images/ui/empty-wishlist.jpg'); ?>
                    <?php if ($emptyWishlistImage !== null) : ?>
                        <img
                            class="wishlist-empty__image"
                            src="<?php echo customcore_e($emptyWishlistImage); ?>"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            width="320"
                            height="240"
                        >
                    <?php endif; ?>
                    <p>Your wishlist is empty.</p>
                    <div class="wishlist-empty__actions">
                        <a class="button button--primary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                            Browse catalogue
                        </a>
                        <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">
                            Build a custom PC
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="wishlist-toolbar">
                    <p class="wishlist-toolbar__summary">
                        You have
                        <strong><?php echo customcore_e((string) count($items)); ?></strong>
                        item<?php echo count($items) === 1 ? '' : 's'; ?> saved.
                    </p>
                    <form
                        method="post"
                        action="<?php echo customcore_e(customcore_url('wishlist.php')); ?>"
                        onsubmit="return confirm('Remove all items from your wishlist?');"
                    >
                        <?php echo customcore_csrf_field(); ?>
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="button button--danger button--sm">Clear wishlist</button>
                    </form>
                </div>

                <ul class="wishlist-grid">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $pid = $item['product_id'];
                        $inStock = $item['is_active'] && $item['stock_quantity'] > 0;
                        $productHref = customcore_url('product.php?id=' . $pid);
                        $imageUrl = customcore_product_image_url($item['image_path'] ?? null);
                        ?>
                        <li class="wishlist-card">
                            <?php if ($imageUrl !== null) : ?>
                                <a class="wishlist-card__media" href="<?php echo customcore_e($productHref); ?>">
                                    <img
                                        class="wishlist-card__image"
                                        src="<?php echo customcore_e($imageUrl); ?>"
                                        alt="<?php echo customcore_e($item['name']); ?>"
                                        loading="lazy"
                                        decoding="async"
                                        width="480"
                                        height="360"
                                    >
                                </a>
                            <?php endif; ?>
                            <div class="wishlist-card__body">
                                <h2 class="wishlist-card__name">
                                    <a href="<?php echo customcore_e($productHref); ?>">
                                        <?php echo customcore_e($item['name']); ?>
                                    </a>
                                </h2>
                                <?php
                                $metaParts = [];
                                if ($item['brand'] !== '') {
                                    $metaParts[] = $item['brand'];
                                }
                                if ($item['category_name'] !== null && $item['category_name'] !== '') {
                                    $metaParts[] = $item['category_name'];
                                }
                                ?>
                                <?php if ($metaParts !== []): ?>
                                    <p class="wishlist-card__meta">
                                        <?php echo customcore_e(implode(' · ', $metaParts)); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($item['short_description'] !== null && $item['short_description'] !== ''): ?>
                                    <p class="wishlist-card__desc">
                                        <?php echo customcore_e(mb_strimwidth($item['short_description'], 0, 110, '…')); ?>
                                    </p>
                                <?php endif; ?>

                                <p class="wishlist-card__price">
                                    From $<?php echo customcore_e(number_format($item['base_price'], 2)); ?>
                                </p>

                                <?php if ($inStock): ?>
                                    <p class="wishlist-card__stock wishlist-card__stock--in">In stock</p>
                                <?php else: ?>
                                    <p class="wishlist-card__stock wishlist-card__stock--out">Currently unavailable</p>
                                <?php endif; ?>
                            </div>

                            <div class="wishlist-card__actions">
                                <?php if ($inStock): ?>
                                    <form method="post" action="<?php echo customcore_e(customcore_url('wishlist.php')); ?>">
                                        <?php echo customcore_csrf_field(); ?>
                                        <input type="hidden" name="action" value="move_to_cart">
                                        <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $pid); ?>">
                                        <button type="submit" class="button button--primary button--sm">Move to cart</button>
                                    </form>
                                <?php else: ?>
                                    <a class="button button--secondary button--sm" href="<?php echo customcore_e($productHref); ?>">
                                        View product
                                    </a>
                                <?php endif; ?>

                                <form method="post" action="<?php echo customcore_e(customcore_url('wishlist.php')); ?>">
                                    <?php echo customcore_csrf_field(); ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $pid); ?>">
                                    <button type="submit" class="button button--ghost button--sm">Remove</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
