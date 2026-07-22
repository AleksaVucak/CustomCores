<?php
/**
 * CustomCore — Approved Product Reviews (Commit 3.8).
 *
 * File responsibility:
 *   Public listing of product reviews with status = approved only.
 *   Optional ?product_id=N limits results to one catalogue product.
 *   Review submission (pending moderation) arrives in Stage 7; this page
 *   is read-only display for Stage 3.
 *
 * Authentication requirements:
 *   None for reading (public). Submission later requires a customer account.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$pageTitle = 'Reviews — CustomCore';
$pageDescription = 'Read approved customer reviews of CustomCore gaming and creator PCs.';
$pageKeywords = 'CustomCore, reviews, ratings, gaming PC';
$currentPage = 'catalogue';

// ---------------------------------------------------------------------------
// Optional product filter
// ---------------------------------------------------------------------------

$filterProductId = 0;
if (isset($_GET['product_id']) && is_string($_GET['product_id']) && ctype_digit($_GET['product_id'])) {
    $filterProductId = (int) $_GET['product_id'];
    if ($filterProductId < 1) {
        $filterProductId = 0;
    }
}

$reviews = [];
$filterProduct = null;
$reviewsError = null;
$averageRating = null;
$reviewCount = 0;

try {
    $pdo = customcore_pdo();

    if ($filterProductId > 0) {
        $prodStmt = $pdo->prepare(
            'SELECT id, name, slug
             FROM products
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $prodStmt->execute([':id' => $filterProductId]);
        $filterProduct = $prodStmt->fetch();
        if ($filterProduct === false) {
            $filterProduct = null;
            $filterProductId = 0;
        }
    }

    $sql = 'SELECT r.id, r.product_id, r.rating, r.title, r.body, r.created_at,
                   u.first_name, u.last_name,
                   p.name AS product_name, p.slug AS product_slug
            FROM reviews r
            INNER JOIN users u ON u.id = r.user_id
            INNER JOIN products p ON p.id = r.product_id
            WHERE r.status = :status
              AND p.is_active = 1';

    $params = [':status' => 'approved'];

    if ($filterProductId > 0) {
        $sql .= ' AND r.product_id = :product_id';
        $params[':product_id'] = $filterProductId;
    }

    $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT 100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
    $reviewCount = count($reviews);

    if ($reviewCount > 0) {
        $sum = 0;
        foreach ($reviews as $row) {
            $sum += (int) ($row['rating'] ?? 0);
        }
        $averageRating = round($sum / $reviewCount, 1);
    }
} catch (Throwable $exception) {
    $reviewsError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Reviews are temporarily unavailable.';
    $reviews = [];
    $reviewCount = 0;
}

if ($filterProduct !== null) {
    $pageTitle = 'Reviews: ' . (string) $filterProduct['name'] . ' — CustomCore';
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section reviews-page" aria-labelledby="reviews-heading">
    <header class="reviews-page__header">
        <h1 id="reviews-heading">
            <?php if ($filterProduct !== null) : ?>
                Reviews for <?php echo customcore_e((string) $filterProduct['name']); ?>
            <?php else : ?>
                Customer reviews
            <?php endif; ?>
        </h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/support.html')); ?>">Reviews &amp; support guide</a>
        </p>
        <p class="reviews-page__intro">
            Only <strong>approved</strong> reviews are shown publicly.
            Pending and hidden reviews stay private until an administrator moderates them.
            Writing a new review will be available after customer accounts are enabled.
        </p>
    </header>

    <?php if ($reviewsError !== null) : ?>
        <div class="flash flash--warning" role="status">
            <?php echo customcore_e($reviewsError); ?>
        </div>
    <?php endif; ?>

    <div class="reviews-toolbar">
        <p class="reviews-toolbar__count" aria-live="polite">
            <?php if ($reviewCount === 0) : ?>
                No approved reviews
                <?php if ($filterProduct !== null) : ?>
                    for this system yet
                <?php else : ?>
                    published yet
                <?php endif; ?>
            <?php else : ?>
                <strong><?php echo customcore_e((string) $reviewCount); ?></strong>
                <?php echo $reviewCount === 1 ? 'approved review' : 'approved reviews'; ?>
                <?php if ($averageRating !== null) : ?>
                    · average
                    <span class="review-rating" aria-label="<?php echo customcore_e($averageRating . ' out of 5'); ?>">
                        <?php echo customcore_e(customcore_format_rating((int) round($averageRating))); ?>
                        (<?php echo customcore_e((string) $averageRating); ?>/5)
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </p>
        <p class="reviews-toolbar__links">
            <?php if ($filterProduct !== null) : ?>
                <a href="<?php echo customcore_e(customcore_url('product.php?id=' . $filterProductId)); ?>">View product</a>
                ·
                <a href="<?php echo customcore_e(customcore_url('reviews.php')); ?>">All reviews</a>
            <?php else : ?>
                <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">Browse catalogue</a>
            <?php endif; ?>
        </p>
    </div>

    <?php if ($reviewCount === 0 && $reviewsError === null) : ?>
        <p class="empty-state">
            <?php if ($filterProduct !== null) : ?>
                Be the first to review this system once review submission is enabled.
                <a href="<?php echo customcore_e(customcore_url('product.php?id=' . $filterProductId)); ?>">Back to product</a>
            <?php else : ?>
                Import <code>database/seed-reviews.sql</code> to load demo approved reviews,
                or check back after customers submit and administrators approve ratings.
            <?php endif; ?>
        </p>
    <?php elseif ($reviewCount > 0) : ?>
        <ul class="review-list">
            <?php foreach ($reviews as $review) : ?>
                <?php
                $rating = (int) ($review['rating'] ?? 0);
                $title = (string) ($review['title'] ?? '');
                $body = (string) ($review['body'] ?? '');
                $first = (string) ($review['first_name'] ?? 'Customer');
                $productName = (string) ($review['product_name'] ?? 'Product');
                $productId = (int) ($review['product_id'] ?? 0);
                $created = customcore_format_date((string) ($review['created_at'] ?? ''));
                $productUrl = customcore_url('product.php?id=' . $productId);
                ?>
                <li class="review-card">
                    <header class="review-card__header">
                        <p class="review-rating" aria-label="<?php echo customcore_e($rating . ' out of 5 stars'); ?>">
                            <?php echo customcore_e(customcore_format_rating($rating)); ?>
                        </p>
                        <h2 class="review-card__title"><?php echo customcore_e($title !== '' ? $title : 'Customer review'); ?></h2>
                    </header>
                    <p class="review-card__meta">
                        By <?php echo customcore_e($first); ?>
                        · <?php echo customcore_e($created); ?>
                        <?php if ($filterProduct === null) : ?>
                            · <a href="<?php echo customcore_e($productUrl); ?>"><?php echo customcore_e($productName); ?></a>
                        <?php endif; ?>
                    </p>
                    <p class="review-card__body"><?php echo customcore_e($body); ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p class="reviews-page__note">
        Review submission and administrator moderation arrive in later stages.
        Until then, this page only displays rows where <code>status = approved</code>.
    </p>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
