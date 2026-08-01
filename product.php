<?php
/**
 * CustomCore — Product Detail Page (Commit 3.4).
 *
 * File responsibility:
 *   Displays a single product loaded from MySQL with specifications, configurable
 *   option groups (RAM, Storage, Colour, etc.), price with default-option total,
 *   stock status, approved reviews (Commit 3.8), review submission as pending
 *   (Commit 7.2), a compare entry point (Commit 3.7), wishlist save (Commit 7.1),
 *   and a context-sensitive Help link. One reusable page serves every product ID.
 *
 * URL format:
 *   product.php?id=1
 *
 * Authentication requirements:
 *   None (public).
 *
 * Data sources:
 *   - products + categories (joined)
 *   - product_options (grouped by option_group, sorted)
 *   - reviews (status = approved only) + users (first name)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/wishlist.php';
require_once __DIR__ . '/includes/reviews.php';

// ---------------------------------------------------------------------------
// Validate and fetch the product
// ---------------------------------------------------------------------------

$productId = 0;
if (isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id'])) {
    $productId = (int) $_GET['id'];
}

$product = null;
$options = [];
$optionGroups = [];
$approvedReviews = [];
$reviewAverage = null;
$reviewCount = 0;
$detailError = null;

if ($productId < 1) {
    $detailError = 'Invalid product ID.';
} else {
    try {
        $pdo = customcore_pdo();

        $stmt = $pdo->prepare(
            'SELECT p.id, p.name, p.slug, p.brand, p.short_description, p.description,
                    p.base_price, p.stock_quantity, p.image_path,
                    p.spec_cpu, p.spec_gpu, p.spec_ram, p.spec_storage,
                    p.is_featured, p.is_active,
                    c.name AS category_name, c.slug AS category_slug
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.id = :id AND p.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if ($product === false) {
            $product = null;
            $detailError = 'Product not found or no longer available.';
        } else {
            $optStmt = $pdo->prepare(
                'SELECT id, option_group, option_label, price_delta, is_default, sort_order
                 FROM product_options
                 WHERE product_id = :pid AND is_active = 1
                 ORDER BY option_group ASC, sort_order ASC, option_label ASC'
            );
            $optStmt->execute([':pid' => $productId]);
            $options = $optStmt->fetchAll();

            foreach ($options as $opt) {
                $group = (string) $opt['option_group'];
                $optionGroups[$group][] = $opt;
            }

            // Approved reviews only (Commit 3.8) — pending/hidden never appear.
            $revStmt = $pdo->prepare(
                'SELECT r.id, r.rating, r.title, r.body, r.created_at,
                        u.first_name
                 FROM reviews r
                 INNER JOIN users u ON u.id = r.user_id
                 WHERE r.product_id = :pid
                   AND r.status = :status
                 ORDER BY r.created_at DESC, r.id DESC
                 LIMIT 20'
            );
            $revStmt->execute([
                ':pid' => $productId,
                ':status' => 'approved',
            ]);
            $approvedReviews = $revStmt->fetchAll();
            $reviewCount = count($approvedReviews);

            if ($reviewCount > 0) {
                $sum = 0;
                foreach ($approvedReviews as $row) {
                    $sum += (int) ($row['rating'] ?? 0);
                }
                $reviewAverage = round($sum / $reviewCount, 1);
            }
        }
    } catch (Throwable $exception) {
        $detailError = customcore_is_debug()
            ? $exception->getMessage()
            : 'Product data is temporarily unavailable.';
    }
}

// ---------------------------------------------------------------------------
// Wishlist + review-submission state (logged-in customers only)
// ---------------------------------------------------------------------------

$isLoggedIn = customcore_is_logged_in();
$onWishlist = false;
$userExistingReview = null;
$loadReviewForm = false;

if ($isLoggedIn && $product !== null) {
    try {
        if (!isset($pdo)) {
            $pdo = customcore_pdo();
        }
        $uid = customcore_current_user_id();
        $onWishlist = customcore_wishlist_contains($pdo, $uid, $productId);
        $userExistingReview = customcore_review_user_existing($pdo, $uid, $productId);
        $loadReviewForm = $userExistingReview === null;
    } catch (Throwable $e) {
        $onWishlist = false;
        $userExistingReview = null;
        $loadReviewForm = false;
    }
}

// ---------------------------------------------------------------------------
// Calculate configured price (defaults selected)
// ---------------------------------------------------------------------------

$basePrice = $product !== null ? (float) $product['base_price'] : 0.00;
$defaultDelta = 0.00;

foreach ($optionGroups as $groupOptions) {
    foreach ($groupOptions as $opt) {
        if (!empty($opt['is_default'])) {
            $defaultDelta += (float) $opt['price_delta'];
            break;
        }
    }
}

$configuredPrice = $basePrice + $defaultDelta;

// ---------------------------------------------------------------------------
// Page metadata
// ---------------------------------------------------------------------------

$productName = $product !== null ? (string) $product['name'] : 'Product';
$categoryName = $product !== null ? (string) ($product['category_name'] ?? '') : '';

$pageTitle = $productName . ' — CustomCore';
$pageDescription = $product !== null
    ? (string) $product['short_description']
    : 'Product detail page — CustomCore.';
$pageKeywords = 'CustomCore, ' . $productName . ', gaming PC, ' . $categoryName;
$currentPage = 'catalogue';

require_once __DIR__ . '/includes/header.php';
?>

<article class="content-section product-detail" aria-labelledby="product-heading">

    <?php if ($detailError !== null) : ?>
        <div class="flash flash--warning" role="alert">
            <?php echo customcore_e($detailError); ?>
        </div>
        <p>
            <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">&larr; Back to catalogue</a>
        </p>
    <?php elseif ($product !== null) : ?>

        <nav class="product-detail__breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Catalogue</a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo customcore_e(customcore_url('catalogue.php?category=' . rawurlencode((string) $product['category_slug']))); ?>">
                <?php echo customcore_e($categoryName); ?>
            </a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?php echo customcore_e($productName); ?></span>
        </nav>

        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/catalogue.html')); ?>">Catalogue &amp; product guide</a>
        </p>

        <header class="product-detail__header">
            <div class="product-detail__media" aria-hidden="true">
                <span class="product-card__media-label">PC</span>
            </div>
            <div class="product-detail__info">
                <h1 id="product-heading"><?php echo customcore_e($productName); ?></h1>
                <p class="product-detail__brand">
                    <?php echo customcore_e((string) $product['brand']); ?>
                    — <?php echo customcore_e($categoryName); ?> tier
                    <?php if (!empty($product['is_featured'])) : ?>
                        <span class="product-card__badge">Featured</span>
                    <?php endif; ?>
                </p>
                <p class="product-detail__short">
                    <?php echo customcore_e((string) $product['short_description']); ?>
                </p>
                <p class="product-detail__price">
                    <span class="product-detail__price-label">From</span>
                    <span class="product-detail__price-amount">$<?php echo customcore_e(number_format($configuredPrice, 2)); ?></span>
                    <?php if ($defaultDelta != 0.00) : ?>
                        <span class="product-detail__price-base">(base $<?php echo customcore_e(number_format($basePrice, 2)); ?>)</span>
                    <?php endif; ?>
                </p>
                <?php
                $stock = (int) $product['stock_quantity'];
                $inStock = $stock > 0;
                ?>
                <p class="product-detail__stock<?php echo $inStock ? '' : ' is-out'; ?>">
                    <?php echo $inStock
                        ? customcore_e('In stock (' . $stock . ' available)')
                        : 'Out of stock'; ?>
                </p>
            </div>
        </header>

        <div class="product-detail__body">
            <section class="product-detail__description" aria-labelledby="desc-heading">
                <h2 id="desc-heading">About this system</h2>
                <p><?php echo customcore_e((string) $product['description']); ?></p>
            </section>

            <section class="product-detail__specs" aria-labelledby="specs-heading">
                <h2 id="specs-heading">Default specifications</h2>
                <table class="specs-table">
                    <tbody>
                        <?php if ((string) $product['spec_cpu'] !== '') : ?>
                            <tr>
                                <th scope="row">Processor</th>
                                <td><?php echo customcore_e((string) $product['spec_cpu']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ((string) $product['spec_gpu'] !== '') : ?>
                            <tr>
                                <th scope="row">Graphics</th>
                                <td><?php echo customcore_e((string) $product['spec_gpu']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ((string) $product['spec_ram'] !== '') : ?>
                            <tr>
                                <th scope="row">Memory</th>
                                <td><?php echo customcore_e((string) $product['spec_ram']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ((string) $product['spec_storage'] !== '') : ?>
                            <tr>
                                <th scope="row">Storage</th>
                                <td><?php echo customcore_e((string) $product['spec_storage']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th scope="row">Category</th>
                            <td><?php echo customcore_e($categoryName); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Brand</th>
                            <td><?php echo customcore_e((string) $product['brand']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <?php if ($optionGroups !== []) : ?>
                <form class="product-detail__cart-form" method="post" action="<?php echo customcore_e(customcore_url('cart.php')); ?>">
                    <?php echo customcore_csrf_field(); ?>
                    <input type="hidden" name="action" value="add_product">
                    <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">

                    <section class="product-detail__options" aria-labelledby="options-heading">
                        <h2 id="options-heading">Configuration options</h2>
                        <p class="product-detail__options-note">
                            Prices shown as +/− from the base price of
                            $<?php echo customcore_e(number_format($basePrice, 2)); ?>.
                            The default selection for each group is pre-selected.
                        </p>

                        <?php foreach ($optionGroups as $groupName => $groupOptions) : ?>
                            <fieldset class="option-group">
                                <legend class="option-group__legend">
                                    <?php echo customcore_e($groupName); ?>
                                </legend>
                                <div class="option-group__choices">
                                    <?php foreach ($groupOptions as $opt) : ?>
                                        <?php
                                        $optId = (int) $opt['id'];
                                        $label = (string) $opt['option_label'];
                                        $delta = (float) $opt['price_delta'];
                                        $isDefault = !empty($opt['is_default']);
                                        $deltaLabel = '';
                                        if ($delta > 0.00) {
                                            $deltaLabel = '+$' . number_format($delta, 2);
                                        } elseif ($delta < 0.00) {
                                            $deltaLabel = '-$' . number_format(abs($delta), 2);
                                        }
                                        $inputName = 'option_' . customcore_e($groupName);
                                        ?>
                                        <label class="option-choice<?php echo $isDefault ? ' is-default' : ''; ?>">
                                            <input
                                                type="radio"
                                                name="<?php echo $inputName; ?>"
                                                value="<?php echo customcore_e((string) $optId); ?>"
                                                <?php echo $isDefault ? ' checked' : ''; ?>
                                            >
                                            <span class="option-choice__label">
                                                <?php echo customcore_e($label); ?>
                                            </span>
                                            <?php if ($deltaLabel !== '') : ?>
                                                <span class="option-choice__delta">
                                                    <?php echo customcore_e($deltaLabel); ?>
                                                </span>
                                            <?php else : ?>
                                                <span class="option-choice__delta option-choice__delta--included">
                                                    Included
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php endforeach; ?>

                        <p class="product-detail__options-count">
                            <?php echo customcore_e((string) count($options)); ?> options across
                            <?php echo customcore_e((string) count($optionGroups)); ?> groups.
                        </p>
                    </section>

                    <?php if ($inStock) : ?>
                        <div class="product-detail__add-to-cart">
                            <label for="product-qty" class="product-detail__qty-label">Quantity</label>
                            <input
                                type="number"
                                id="product-qty"
                                name="quantity"
                                value="1"
                                min="1"
                                max="<?php echo customcore_e((string) min(99, $stock)); ?>"
                                class="product-detail__qty-input"
                            >
                            <button type="submit" class="button button--primary">Add to cart</button>
                        </div>
                    <?php else : ?>
                        <p class="product-detail__oos-notice">This product is currently out of stock.</p>
                    <?php endif; ?>
                </form>
            <?php else : ?>
                <!-- No options — simple add to cart form -->
                <?php if ($inStock) : ?>
                    <form class="product-detail__cart-form product-detail__cart-form--simple" method="post" action="<?php echo customcore_e(customcore_url('cart.php')); ?>">
                        <?php echo customcore_csrf_field(); ?>
                        <input type="hidden" name="action" value="add_product">
                        <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">
                        <div class="product-detail__add-to-cart">
                            <label for="product-qty" class="product-detail__qty-label">Quantity</label>
                            <input
                                type="number"
                                id="product-qty"
                                name="quantity"
                                value="1"
                                min="1"
                                max="<?php echo customcore_e((string) min(99, $stock)); ?>"
                                class="product-detail__qty-input"
                            >
                            <button type="submit" class="button button--primary">Add to cart</button>
                        </div>
                    </form>
                <?php else : ?>
                    <p class="product-detail__oos-notice">This product is currently out of stock.</p>
                <?php endif; ?>
            <?php endif; ?>

            <div class="product-detail__wishlist">
                <?php if ($isLoggedIn) : ?>
                    <?php if ($onWishlist) : ?>
                        <form method="post" action="<?php echo customcore_e(customcore_url('wishlist.php')); ?>" class="product-detail__wishlist-form">
                            <?php echo customcore_csrf_field(); ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">
                            <span class="product-detail__wishlist-note">Saved to your wishlist.</span>
                            <button type="submit" class="button button--ghost button--sm">Remove from wishlist</button>
                        </form>
                    <?php else : ?>
                        <form method="post" action="<?php echo customcore_e(customcore_url('wishlist.php')); ?>" class="product-detail__wishlist-form">
                            <?php echo customcore_csrf_field(); ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">
                            <input type="hidden" name="return_to" value="<?php echo customcore_e('product.php?id=' . $productId); ?>">
                            <button type="submit" class="button button--secondary button--sm">♡ Save to wishlist</button>
                        </form>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="product-detail__wishlist-note">
                        <a href="<?php echo customcore_e(customcore_url('login.php')); ?>">Log in</a>
                        to save this product to your wishlist.
                    </p>
                <?php endif; ?>
            </div>

            <section class="product-detail__reviews" aria-labelledby="reviews-heading">
                <h2 id="reviews-heading">Customer reviews</h2>
                <p class="product-detail__reviews-summary">
                    <?php if ($reviewCount === 0) : ?>
                        No approved reviews for this system yet.
                    <?php else : ?>
                        <span class="review-rating" aria-label="<?php echo customcore_e((string) $reviewAverage . ' out of 5 average'); ?>">
                            <?php echo customcore_e(customcore_format_rating((int) round((float) $reviewAverage))); ?>
                        </span>
                        <strong><?php echo customcore_e((string) $reviewAverage); ?>/5</strong>
                        from
                        <strong><?php echo customcore_e((string) $reviewCount); ?></strong>
                        <?php echo $reviewCount === 1 ? 'approved review' : 'approved reviews'; ?>
                    <?php endif; ?>
                    ·
                    <a href="<?php echo customcore_e(customcore_url('reviews.php?product_id=' . $productId)); ?>">
                        View all reviews for this product
                    </a>
                </p>

                <?php if ($approvedReviews !== []) : ?>
                    <ul class="review-list review-list--product">
                        <?php foreach ($approvedReviews as $review) : ?>
                            <?php
                            $rating = (int) ($review['rating'] ?? 0);
                            $title = (string) ($review['title'] ?? '');
                            $body = (string) ($review['body'] ?? '');
                            $first = (string) ($review['first_name'] ?? 'Customer');
                            $created = customcore_format_date((string) ($review['created_at'] ?? ''));
                            ?>
                            <li class="review-card">
                                <header class="review-card__header">
                                    <p class="review-rating" aria-label="<?php echo customcore_e($rating . ' out of 5 stars'); ?>">
                                        <?php echo customcore_e(customcore_format_rating($rating)); ?>
                                    </p>
                                    <h3 class="review-card__title">
                                        <?php echo customcore_e($title !== '' ? $title : 'Customer review'); ?>
                                    </h3>
                                </header>
                                <p class="review-card__meta">
                                    By <?php echo customcore_e($first); ?>
                                    · <?php echo customcore_e($created); ?>
                                </p>
                                <p class="review-card__body"><?php echo customcore_e($body); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="product-detail__write-review">
                    <h3 class="product-detail__write-review-heading">Write a review</h3>

                    <?php if (!$isLoggedIn) : ?>
                        <p class="product-detail__reviews-note">
                            <a href="<?php echo customcore_e(customcore_url('login.php')); ?>">Log in</a>
                            to submit a review. New reviews are held as
                            <strong>pending</strong> until an administrator approves them.
                            ·
                            <a href="<?php echo customcore_e(customcore_url('reviews.php?product_id=' . $productId)); ?>">
                                Open the full reviews page
                            </a>
                        </p>
                    <?php elseif ($userExistingReview !== null) : ?>
                        <?php
                        $existStatus = (string) ($userExistingReview['status'] ?? '');
                        $existLabel = customcore_review_status_label($existStatus);
                        ?>
                        <div class="review-submit__existing" role="status">
                            <p>
                                You already submitted a review for this product
                                (<span class="review-status review-status--<?php echo customcore_e($existStatus); ?>">
                                    <?php echo customcore_e($existLabel); ?>
                                </span>).
                                <?php if ($existStatus === 'pending') : ?>
                                    It will appear publicly once approved.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else : ?>
                        <p class="review-submit__note">
                            Your review is saved as <strong>pending</strong> and stays private until approved.
                        </p>
                        <form
                            id="review-form"
                            class="review-form form-stack"
                            method="post"
                            action="<?php echo customcore_e(customcore_url('reviews.php?product_id=' . $productId)); ?>"
                            novalidate
                        >
                            <?php echo customcore_csrf_field(); ?>
                            <input type="hidden" name="action" value="submit_review">
                            <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">
                            <input type="hidden" name="return_to" value="<?php echo customcore_e('product.php?id=' . $productId); ?>">

                            <fieldset class="review-form__rating">
                                <legend class="form-label">
                                    Rating <span class="form-required" aria-hidden="true">*</span>
                                </legend>
                                <div class="review-form__stars" role="radiogroup" aria-label="Star rating">
                                    <?php for ($star = 5; $star >= 1; $star--) : ?>
                                        <?php $starId = 'product-review-rating-' . $star; ?>
                                        <input
                                            type="radio"
                                            id="<?php echo customcore_e($starId); ?>"
                                            name="rating"
                                            value="<?php echo customcore_e((string) $star); ?>"
                                            required
                                        >
                                        <label for="<?php echo customcore_e($starId); ?>" title="<?php echo customcore_e($star . ' star' . ($star === 1 ? '' : 's')); ?>">
                                            <span class="visually-hidden"><?php echo customcore_e((string) $star); ?> star<?php echo $star === 1 ? '' : 's'; ?></span>
                                            <span aria-hidden="true">★</span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </fieldset>

                            <div class="form-row">
                                <label class="form-label" for="product-review-title">
                                    Title <span class="form-required" aria-hidden="true">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="product-review-title"
                                    name="title"
                                    maxlength="200"
                                    required
                                >
                            </div>

                            <div class="form-row">
                                <label class="form-label" for="product-review-body">
                                    Your review <span class="form-required" aria-hidden="true">*</span>
                                </label>
                                <textarea
                                    id="product-review-body"
                                    name="body"
                                    class="form-textarea"
                                    rows="5"
                                    maxlength="5000"
                                    minlength="20"
                                    required
                                ></textarea>
                                <p class="form-help">At least 20 characters.</p>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="button button--primary">Submit review</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <footer class="product-detail__actions">
            <a class="button" href="<?php echo customcore_e(customcore_url('catalogue.php?category=' . rawurlencode((string) $product['category_slug']))); ?>">
                &larr; Back to <?php echo customcore_e($categoryName); ?>
            </a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('compare.php?ids=' . $productId)); ?>">
                Compare this system
            </a>
            <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                All systems
            </a>
        </footer>

    <?php endif; ?>
</article>

<?php
require_once __DIR__ . '/includes/footer.php';
