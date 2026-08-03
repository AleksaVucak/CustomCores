<?php
/**
 * CustomCore — Administrator review moderation (Commit 9.8).
 *
 * File responsibility:
 *   Protected review queue. Lists pending/approved/hidden reviews with search
 *   and status filters; lets an administrator approve, hide, restore to pending,
 *   or permanently delete a review via Post/Redirect/Get.
 *
 * Authentication requirements:
 *   Administrator role (customcore_require_admin()).
 *
 * Security:
 *   - Every write requires a valid CSRF token.
 *   - Status writes are validated against the reviews.status ENUM.
 *   - Delete confirms via a client-side prompt (server still verifies CSRF).
 *   - Public pages continue to show only status = 'approved' reviews.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/reviews.php';
require_once __DIR__ . '/../includes/admin-reviews.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

/** Build a query string preserving the current filters (+ optional page). */
function customcore_admin_reviews_query(array $filters, ?int $page = null): string
{
    $params = [];
    if (($filters['search'] ?? '') !== '') {
        $params['q'] = (string) $filters['search'];
    }
    if (($filters['status'] ?? '') !== '') {
        $params['status'] = (string) $filters['status'];
    }
    if ($page !== null && $page > 1) {
        $params['page'] = $page;
    }

    return $params === [] ? '' : '?' . http_build_query($params);
}

// ---------------------------------------------------------------------------
// Handle moderation actions — CSRF + PRG
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;

    $returnFilters = [
        'search' => isset($_POST['q']) && is_string($_POST['q']) ? trim($_POST['q']) : '',
        'status' => isset($_POST['status_filter']) && is_string($_POST['status_filter'])
            ? $_POST['status_filter']
            : '',
    ];
    // Drop an invalid status filter silently so the redirect stays clean.
    if ($returnFilters['status'] !== ''
        && !in_array($returnFilters['status'], customcore_review_statuses(), true)
    ) {
        $returnFilters['status'] = '';
    }
    $returnUrl = 'admin/reviews.php' . customcore_admin_reviews_query($returnFilters);

    if (!customcore_csrf_verify($token)) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect($returnUrl);
    }

    $reviewId = isset($_POST['review_id']) ? (int) $_POST['review_id'] : 0;
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    $review = customcore_admin_review_fetch($pdo, $reviewId);
    if ($review === null) {
        customcore_flash_error('That review could not be found.');
        customcore_redirect($returnUrl);
    }

    $productLabel = (string) $review['product_name'];

    if ($action === 'approve') {
        if ((string) $review['status'] === 'approved') {
            customcore_flash_warning('That review is already approved.');
        } elseif (customcore_admin_review_set_status($pdo, $reviewId, 'approved')) {
            customcore_flash_success('Approved the review for “' . $productLabel . '”. It is now visible publicly.');
        } else {
            customcore_flash_error('Could not approve that review.');
        }
    } elseif ($action === 'hide') {
        if ((string) $review['status'] === 'hidden') {
            customcore_flash_warning('That review is already hidden.');
        } elseif (customcore_admin_review_set_status($pdo, $reviewId, 'hidden')) {
            customcore_flash_success('Hid the review for “' . $productLabel . '”. It will not appear publicly.');
        } else {
            customcore_flash_error('Could not hide that review.');
        }
    } elseif ($action === 'pending') {
        if ((string) $review['status'] === 'pending') {
            customcore_flash_warning('That review is already pending.');
        } elseif (customcore_admin_review_set_status($pdo, $reviewId, 'pending')) {
            customcore_flash_success('Moved the review for “' . $productLabel . '” back to pending.');
        } else {
            customcore_flash_error('Could not update that review.');
        }
    } elseif ($action === 'delete') {
        if (customcore_admin_review_delete($pdo, $reviewId)) {
            customcore_flash_success('Deleted the review for “' . $productLabel . '”.');
        } else {
            customcore_flash_error('Could not delete that review.');
        }
    } else {
        customcore_flash_error('Unknown moderation action.');
    }

    customcore_redirect($returnUrl);
}

// ---------------------------------------------------------------------------
// Read filters + load list
// ---------------------------------------------------------------------------
$filters = [
    'search' => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
    'status' => isset($_GET['status']) && in_array($_GET['status'] ?? '', customcore_review_statuses(), true)
        ? (string) $_GET['status']
        : '',
];
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$result = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25];
$statusCounts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'hidden' => 0];
$listError = null;

try {
    $result = customcore_admin_review_list($pdo, $filters, $page, 25);
    $statusCounts = customcore_admin_review_status_counts($pdo);
} catch (Throwable $e) {
    $listError = customcore_is_debug() ? $e->getMessage() : 'Reviews are temporarily unavailable.';
}

$currentQuery = customcore_admin_reviews_query($filters);

$adminNavCurrent = 'reviews';
$loadAdminCss = true;
$currentPage = 'admin';

$pageTitle = 'Reviews — CustomCore admin';
$pageDescription = 'Approve, hide, or delete CustomCore product reviews.';
$pageKeywords = 'CustomCore, admin, reviews, moderation';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-reviews" aria-labelledby="admin-reviews-heading">
    <header class="admin-page__header">
        <h1 id="admin-reviews-heading">Reviews</h1>
        <p class="admin-page__intro">
            Moderate customer product reviews. Only <strong>approved</strong> reviews appear
            on the public catalogue and product pages. Pending reviews wait here for a decision;
            hidden reviews stay in the database but are not shown publicly.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Back to dashboard</a>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Flash: review list load error -->
    <?php if ($listError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($listError); ?></p>
    <?php endif; ?>

    <!-- Search & filter: text search and status -->
    <form class="admin-filter" method="get" action="<?php echo customcore_e(customcore_url('admin/reviews.php')); ?>">
        <div class="admin-filter__field">
            <label for="filter-q">Search</label>
            <input type="search" id="filter-q" name="q" value="<?php echo customcore_e($filters['search']); ?>"
                   placeholder="Title, body, product, or customer" maxlength="200">
        </div>
        <div class="admin-filter__field">
            <label for="filter-status">Status</label>
            <select id="filter-status" name="status">
                <option value="">All statuses (<?php echo customcore_e((string) ($statusCounts['all'] ?? 0)); ?>)</option>
                <?php foreach (customcore_review_statuses() as $s) : ?>
                    <option value="<?php echo customcore_e($s); ?>" <?php echo $filters['status'] === $s ? 'selected' : ''; ?>>
                        <?php echo customcore_e(customcore_review_status_label($s)); ?>
                        (<?php echo customcore_e((string) ($statusCounts[$s] ?? 0)); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filter__actions">
            <button type="submit" class="button button--sm">Apply</button>
            <?php if ($currentQuery !== '') : ?>
                <a class="button button--ghost button--sm" href="<?php echo customcore_e(customcore_url('admin/reviews.php')); ?>">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Results: empty state or review moderation list -->
    <?php if ($result['rows'] === []) : ?>
        <p class="admin-activity__empty">No reviews match your filters.</p>
    <?php else : ?>
        <p class="admin-products__count">
            <?php echo customcore_e((string) $result['total']); ?>
            review<?php echo $result['total'] === 1 ? '' : 's'; ?> found
            · <?php echo customcore_e((string) ($statusCounts['pending'] ?? 0)); ?> pending
            · page <?php echo customcore_e((string) $result['page']); ?>
            of <?php echo customcore_e((string) $result['pages']); ?>
        </p>

        <!-- Review cards: approve, hide, pending, delete actions -->
        <div class="admin-review-list">
            <?php foreach ($result['rows'] as $review) : ?>
                <?php
                $rid = (int) $review['id'];
                $status = (string) $review['status'];
                $customerName = trim((string) $review['first_name'] . ' ' . (string) $review['last_name']);
                $isPending = $status === 'pending';
                ?>
                <article class="admin-card admin-review-card<?php echo $isPending ? ' admin-review-card--pending' : ''; ?>"
                         aria-labelledby="review-title-<?php echo customcore_e((string) $rid); ?>">
                    <header class="admin-review-card__header">
                        <div>
                            <h2 id="review-title-<?php echo customcore_e((string) $rid); ?>" class="admin-review-card__title">
                                <?php echo customcore_e((string) $review['title']); ?>
                            </h2>
                            <p class="admin-review-card__meta">
                                <span class="admin-review-card__rating" aria-label="Rating">
                                    <?php echo customcore_e(customcore_format_rating((int) $review['rating'])); ?>
                                </span>
                                ·
                                <a href="<?php echo customcore_e(customcore_url('product.php?id=' . (int) $review['product_id'])); ?>">
                                    <?php echo customcore_e((string) $review['product_name']); ?>
                                </a>
                                ·
                                <?php echo customcore_e($customerName !== '' ? $customerName : (string) $review['email']); ?>
                                ·
                                <?php echo customcore_e(customcore_format_date((string) $review['created_at'])); ?>
                            </p>
                        </div>
                        <span class="review-status <?php echo customcore_e(customcore_admin_review_status_class($status)); ?>">
                            <?php echo customcore_e(customcore_review_status_label($status)); ?>
                        </span>
                    </header>

                    <div class="admin-review-card__body">
                        <?php echo nl2br(customcore_e((string) $review['body'])); ?>
                    </div>

                    <footer class="admin-review-card__actions admin-actions">
                        <?php if ($status !== 'approved') : ?>
                            <form method="post" action="<?php echo customcore_e(customcore_url('admin/reviews.php')); ?>" class="admin-inline-form">
                                <?php echo customcore_csrf_field(); ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="review_id" value="<?php echo customcore_e((string) $rid); ?>">
                                <input type="hidden" name="q" value="<?php echo customcore_e($filters['search']); ?>">
                                <input type="hidden" name="status_filter" value="<?php echo customcore_e($filters['status']); ?>">
                                <button type="submit" class="button button--success button--sm">Approve</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($status !== 'hidden') : ?>
                            <form method="post" action="<?php echo customcore_e(customcore_url('admin/reviews.php')); ?>" class="admin-inline-form">
                                <?php echo customcore_csrf_field(); ?>
                                <input type="hidden" name="action" value="hide">
                                <input type="hidden" name="review_id" value="<?php echo customcore_e((string) $rid); ?>">
                                <input type="hidden" name="q" value="<?php echo customcore_e($filters['search']); ?>">
                                <input type="hidden" name="status_filter" value="<?php echo customcore_e($filters['status']); ?>">
                                <button type="submit" class="button button--sm">Hide</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($status !== 'pending') : ?>
                            <form method="post" action="<?php echo customcore_e(customcore_url('admin/reviews.php')); ?>" class="admin-inline-form">
                                <?php echo customcore_csrf_field(); ?>
                                <input type="hidden" name="action" value="pending">
                                <input type="hidden" name="review_id" value="<?php echo customcore_e((string) $rid); ?>">
                                <input type="hidden" name="q" value="<?php echo customcore_e($filters['search']); ?>">
                                <input type="hidden" name="status_filter" value="<?php echo customcore_e($filters['status']); ?>">
                                <button type="submit" class="button button--ghost button--sm">Mark pending</button>
                            </form>
                        <?php endif; ?>

                        <form method="post" action="<?php echo customcore_e(customcore_url('admin/reviews.php')); ?>" class="admin-inline-form">
                            <?php echo customcore_csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="review_id" value="<?php echo customcore_e((string) $rid); ?>">
                            <input type="hidden" name="q" value="<?php echo customcore_e($filters['search']); ?>">
                            <input type="hidden" name="status_filter" value="<?php echo customcore_e($filters['status']); ?>">
                            <button type="submit" class="button button--danger button--sm"
                                    onclick="return confirm('Permanently delete this review? This cannot be undone.');">
                                Delete
                            </button>
                        </form>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination controls -->
        <?php if ($result['pages'] > 1) : ?>
            <nav class="admin-pagination" aria-label="Review pages">
                <?php if ($result['page'] > 1) : ?>
                    <a class="button button--ghost button--sm"
                       href="<?php echo customcore_e(customcore_url('admin/reviews.php' . customcore_admin_reviews_query($filters, $result['page'] - 1))); ?>">← Previous</a>
                <?php endif; ?>
                <span class="admin-pagination__status">
                    Page <?php echo customcore_e((string) $result['page']); ?> of <?php echo customcore_e((string) $result['pages']); ?>
                </span>
                <?php if ($result['page'] < $result['pages']) : ?>
                    <a class="button button--ghost button--sm"
                       href="<?php echo customcore_e(customcore_url('admin/reviews.php' . customcore_admin_reviews_query($filters, $result['page'] + 1))); ?>">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
