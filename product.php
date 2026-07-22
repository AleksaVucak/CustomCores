<?php
/**
 * CustomCore — Product Detail Page (Commit 3.4).
 *
 * File responsibility:
 *   Displays a single product loaded from MySQL with specifications, configurable
 *   option groups (RAM, Storage, Colour, etc.), price with default-option total,
 *   stock status, approved reviews (Commit 3.8), a compare entry point (Commit 3.7),
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
                        Add to cart with your choices once the checkout is active.
                    </p>
                </section>
            <?php endif; ?>

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

                <p class="product-detail__reviews-note">
                    Only reviews with status <code>approved</code> are shown.
                    Submission and moderation arrive in later stages.
                </p>
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
