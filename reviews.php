<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Product Reviews listing + submission+ 7.2).
// Public listing of approved product reviews (optional ?product_id=N filter). Logged-in customers
// can submit a new review (rating, title, body) which is stored with status = pending until an
// administrator moderates it. Pending and hidden reviews never appear in the public list.
// Access: None for reading (public). Submission requires a logged-in customer.
// Security:
//   CSRF verification on submit.
//   Server-side validation of rating (1 to 5), title, and body.
//   Product must exist and be active.
//   One pending/approved review per user per product (no stacking).
//   Output escaped; PRG redirect after successful submit.

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/reviews.php';

$pageTitle = 'Reviews | CustomCore';
$pageDescription = 'Read approved customer reviews of CustomCore gaming and creator PCs, or submit your own.';
$pageKeywords = 'CustomCore, reviews, ratings, gaming PC';
$currentPage = 'catalogue';
$loadReviewForm = false;

// Optional product filter

$filterProductId = 0;
if (isset($_GET['product_id']) && is_string($_GET['product_id']) && ctype_digit($_GET['product_id'])) {
    $filterProductId = (int) $_GET['product_id'];
    if ($filterProductId < 1) {
        $filterProductId = 0;
    }
}

$isLoggedIn = customcore_is_logged_in();
$userId = $isLoggedIn ? customcore_current_user_id() : 0;

$reviews = [];
$filterProduct = null;
$reviewsError = null;
$averageRating = null;
$reviewCount = 0;
$catalogueProducts = [];

/** @var array<string, string> Sticky form values after validation failure. */
$formValues = [
    'product_id' => $filterProductId > 0 ? (string) $filterProductId : '',
    'rating' => '',
    'title' => '',
    'body' => '',
];
/** @var array<string, string> Field-level errors for the submission form. */
$formErrors = [];
/** @var string|null Banner shown above the form on same-request validation failure. */
$formBanner = null;
$existingReview = null;
$canSubmit = false;

// Handle POST, submit a review

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'submit_review') {
        if (!$isLoggedIn) {
            customcore_flash_error('Please log in to write a review.');
            customcore_redirect('login.php');
        }

        $csrfOk = customcore_csrf_verify(
            isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
        );

        $returnTo = 'reviews.php';
        if (isset($_POST['return_to']) && is_string($_POST['return_to'])
            && customcore_is_safe_return_target($_POST['return_to'])) {
            $returnTo = $_POST['return_to'];
        }

        $productIdPost = isset($_POST['product_id']) && is_string($_POST['product_id']) && ctype_digit($_POST['product_id'])
            ? (int) $_POST['product_id']
            : 0;

        $formValues['product_id'] = $productIdPost > 0 ? (string) $productIdPost : '';
        $formValues['rating'] = isset($_POST['rating']) && is_string($_POST['rating']) ? $_POST['rating'] : '';
        $formValues['title'] = isset($_POST['title']) && is_string($_POST['title']) ? $_POST['title'] : '';
        $formValues['body'] = isset($_POST['body']) && is_string($_POST['body']) ? $_POST['body'] : '';

        if (!$csrfOk) {
            customcore_flash_error('Your session expired. Please try again.');
            customcore_redirect($returnTo);
        }

        $validated = customcore_review_validate([
            'rating' => $formValues['rating'],
            'title' => $formValues['title'],
            'body' => $formValues['body'],
        ]);
        $formErrors = $validated['errors'];
        $formValues['title'] = $validated['title'];
        $formValues['body'] = $validated['body'];
        if ($validated['rating'] > 0) {
            $formValues['rating'] = (string) $validated['rating'];
        }

        try {
            $pdo = customcore_pdo();

            $product = customcore_review_product($pdo, $productIdPost);
            if ($product === null) {
                $formErrors['product_id'] = 'Please choose a valid product to review.';
            } else {
                $existing = customcore_review_user_existing($pdo, $userId, $productIdPost);
                if ($existing !== null) {
                    $status = (string) ($existing['status'] ?? '');
                    if ($status === 'pending') {
                        customcore_flash_warning(
                            'You already have a review for this product awaiting moderation.'
                        );
                        customcore_redirect($returnTo);
                    }
                    customcore_flash_warning(
                        'You have already reviewed this product. Thank you for your feedback.'
                    );
                    customcore_redirect($returnTo);
                }
            }

            if ($product !== null && $validated['ok'] && $formErrors === []) {
                customcore_review_submit(
                    $pdo,
                    $userId,
                    $productIdPost,
                    $validated['rating'],
                    $validated['title'],
                    $validated['body']
                );
                customcore_flash_success(
                    'Thank you! Your review of '
                    . (string) $product['name']
                    . ' was submitted and is pending moderation. It will appear publicly once approved.'
                );
                customcore_redirect($returnTo);
            }

            // Validation failed, keep sticky values and re-render (no redirect).
            if ($productIdPost > 0) {
                $filterProductId = $productIdPost;
            }
            $formBanner = 'Please correct the highlighted fields and try again.';
        } catch (Throwable $exception) {
            customcore_flash_error(
                customcore_is_debug()
                    ? $exception->getMessage()
                    : 'We could not save your review right now. Please try again later.'
            );
            customcore_redirect($returnTo);
        }
    }
}

// Load approved reviews for display

try {
    $pdo = customcore_pdo();

    if ($filterProductId > 0) {
        $filterProduct = customcore_review_product($pdo, $filterProductId);
        if ($filterProduct === null) {
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

    // Product picker for the submission form (when no single product is filtered).
    $prodListStmt = $pdo->query(
        'SELECT id, name FROM products WHERE is_active = 1 ORDER BY name ASC'
    );
    $catalogueProducts = $prodListStmt !== false ? $prodListStmt->fetchAll() : [];

    if ($isLoggedIn && $filterProductId > 0) {
        $existingReview = customcore_review_user_existing($pdo, $userId, $filterProductId);
    }
} catch (Throwable $exception) {
    $reviewsError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Reviews are temporarily unavailable.';
    $reviews = [];
    $reviewCount = 0;
}

$canSubmit = $isLoggedIn
    && ($filterProductId > 0 || $catalogueProducts !== [])
    && $existingReview === null;

// Show the form (and load JS) whenever a logged-in user can submit or has sticky errors.
$loadReviewForm = $isLoggedIn && ($canSubmit || $formErrors !== []);

if ($filterProduct !== null) {
    $pageTitle = 'Reviews: ' . (string) $filterProduct['name'] . ' | CustomCore';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Reviews page: approved review list and submission form -->
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
            <a href="<?php echo customcore_e(customcore_url('help/support.html#reviews')); ?>">Reviews &amp; support guide</a>
        </p>
        <p class="reviews-page__intro">
            Only <strong>approved</strong> reviews are shown publicly.
            New submissions enter a <strong>pending</strong> moderation queue and stay private until an administrator approves them.
        </p>
    </header>

    <!-- Warning banner when reviews cannot be loaded -->
    <?php if ($reviewsError !== null) : ?>
        <div class="flash flash--warning" role="status">
            <?php echo customcore_e($reviewsError); ?>
        </div>
    <?php endif; ?>

    <!-- Toolbar: review count, average rating, and filter links -->
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
                    <span class="review-rating" aria-label="<?php echo customcore_e((string) $averageRating . ' out of 5'); ?>">
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

    <!-- Empty state when no approved reviews exist yet -->
    <?php if ($reviewCount === 0 && $reviewsError === null) : ?>
        <p class="empty-state">
            <?php if ($filterProduct !== null) : ?>
                Be the first to review this system, use the form below if you are signed in.
                <a href="<?php echo customcore_e(customcore_url('product.php?id=' . $filterProductId)); ?>">Back to product</a>
            <?php else : ?>
                No approved reviews are published yet. Signed-in customers can submit a review below;
                it will appear here after moderation.
            <?php endif; ?>
        </p>
    <?php elseif ($reviewCount > 0) : ?>
        <!-- Review list: one card per approved review -->
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

    <!-- Review submission: login prompt, existing notice, or form -->
    <section class="review-submit" aria-labelledby="review-submit-heading">
        <h2 id="review-submit-heading">Write a review</h2>

        <?php if (!$isLoggedIn) : ?>
            <p class="review-submit__note">
                <a href="<?php echo customcore_e(customcore_url('login.php')); ?>">Log in</a>
                to rate a CustomCore system. New reviews are held as
                <strong>pending</strong> until an administrator approves them.
            </p>
        <?php elseif ($existingReview !== null) : ?>
            <?php
            $existStatus = (string) ($existingReview['status'] ?? '');
            $existLabel = customcore_review_status_label($existStatus);
            ?>
            <div class="review-submit__existing" role="status">
                <p>
                    You already submitted a review for
                    <?php echo customcore_e($filterProduct !== null ? (string) $filterProduct['name'] : 'this product'); ?>
                    (<span class="review-status review-status--<?php echo customcore_e($existStatus); ?>">
                        <?php echo customcore_e($existLabel); ?>
                    </span>).
                </p>
                <?php if ($existStatus === 'pending') : ?>
                    <p>It will appear publicly once an administrator approves it.</p>
                <?php endif; ?>
            </div>
        <?php elseif ($canSubmit || $formErrors !== []) : ?>
            <p class="review-submit__note">
                Your review will be saved as <strong>pending</strong> and will not appear publicly until approved.
            </p>

            <?php if ($formBanner !== null) : ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($formBanner); ?>
                </div>
            <?php endif; ?>

            <!-- Review form: product, star rating, title, and body -->
            <form
                id="review-form"
                class="review-form form-stack"
                method="post"
                action="<?php echo customcore_e(customcore_url('reviews.php' . ($filterProductId > 0 ? '?product_id=' . $filterProductId : ''))); ?>"
                novalidate
            >
                <?php echo customcore_csrf_field(); ?>
                <input type="hidden" name="action" value="submit_review">
                <input
                    type="hidden"
                    name="return_to"
                    value="<?php echo customcore_e(
                        $filterProductId > 0
                            ? 'reviews.php?product_id=' . $filterProductId
                            : 'reviews.php'
                    ); ?>"
                >

                <?php if ($filterProductId > 0) : ?>
                    <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $filterProductId); ?>">
                    <p class="review-form__product">
                        Reviewing:
                        <strong><?php echo customcore_e((string) ($filterProduct['name'] ?? 'Product')); ?></strong>
                    </p>
                <?php else : ?>
                    <div class="form-row<?php echo isset($formErrors['product_id']) ? ' has-error' : ''; ?>">
                        <label class="form-label" for="review-product">Product <span class="form-required" aria-hidden="true">*</span></label>
                        <select
                            id="review-product"
                            name="product_id"
                            required
                            <?php echo isset($formErrors['product_id']) ? 'aria-invalid="true" aria-describedby="err-product"' : ''; ?>
                        >
                            <option value="">Select a system…</option>
                            <?php foreach ($catalogueProducts as $prod) : ?>
                                <?php
                                $pid = (int) $prod['id'];
                                $selected = $formValues['product_id'] === (string) $pid;
                                ?>
                                <option value="<?php echo customcore_e((string) $pid); ?>"<?php echo $selected ? ' selected' : ''; ?>>
                                    <?php echo customcore_e((string) $prod['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($formErrors['product_id'])) : ?>
                            <p class="form-error" id="err-product"><?php echo customcore_e($formErrors['product_id']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <fieldset class="review-form__rating<?php echo isset($formErrors['rating']) ? ' has-error' : ''; ?>">
                    <legend class="form-label">
                        Rating <span class="form-required" aria-hidden="true">*</span>
                    </legend>
                    <div class="review-form__stars" role="radiogroup" aria-label="Star rating">
                        <?php for ($star = 5; $star >= 1; $star--) : ?>
                            <?php
                            $starId = 'review-rating-' . $star;
                            $checked = $formValues['rating'] === (string) $star;
                            ?>
                            <input
                                type="radio"
                                id="<?php echo customcore_e($starId); ?>"
                                name="rating"
                                value="<?php echo customcore_e((string) $star); ?>"
                                <?php echo $checked ? ' checked' : ''; ?>
                                required
                            >
                            <label for="<?php echo customcore_e($starId); ?>" title="<?php echo customcore_e($star . ' star' . ($star === 1 ? '' : 's')); ?>">
                                <span class="visually-hidden"><?php echo customcore_e((string) $star); ?> star<?php echo $star === 1 ? '' : 's'; ?></span>
                                <span aria-hidden="true">★</span>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <?php if (isset($formErrors['rating'])) : ?>
                        <p class="form-error" id="err-rating"><?php echo customcore_e($formErrors['rating']); ?></p>
                    <?php endif; ?>
                </fieldset>

                <div class="form-row<?php echo isset($formErrors['title']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="review-title">
                        Title <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="review-title"
                        name="title"
                        maxlength="200"
                        required
                        value="<?php echo customcore_e($formValues['title']); ?>"
                        <?php echo isset($formErrors['title']) ? 'aria-invalid="true" aria-describedby="err-title"' : ''; ?>
                    >
                    <?php if (isset($formErrors['title'])) : ?>
                        <p class="form-error" id="err-title"><?php echo customcore_e($formErrors['title']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-row<?php echo isset($formErrors['body']) ? ' has-error' : ''; ?>">
                    <label class="form-label" for="review-body">
                        Your review <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea
                        id="review-body"
                        name="body"
                        class="form-textarea"
                        rows="6"
                        maxlength="5000"
                        required
                        minlength="20"
                        <?php echo isset($formErrors['body']) ? 'aria-invalid="true" aria-describedby="err-body"' : ''; ?>
                    ><?php echo customcore_e($formValues['body']); ?></textarea>
                    <p class="form-help">At least 20 characters. Mentions of performance, build quality, or value help other shoppers.</p>
                    <?php if (isset($formErrors['body'])) : ?>
                        <p class="form-error" id="err-body"><?php echo customcore_e($formErrors['body']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button--primary">Submit review</button>
                </div>
            </form>
        <?php else : ?>
            <p class="review-submit__note">
                Choose a product from the
                <a href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">catalogue</a>
                to leave a review.
            </p>
        <?php endif; ?>
    </section>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
