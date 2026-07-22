<?php
/**
 * CustomCore — Customer Profile Dashboard (Commit 4.5).
 *
 * File responsibility:
 *   Private account home for the logged-in customer. Shows their own profile
 *   summary, activity counts (orders, builds, wishlist, consultations, reviews),
 *   and recent activity. Guests are redirected to login via require_login().
 *
 * Authentication requirements:
 *   Logged-in customer or admin. Data is always scoped to session user_id —
 *   never to a query-string id.
 *
 * Completion test:
 *   Logged-in user sees only their own information.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

customcore_require_login();

$userId = customcore_current_user_id();
$user = null;
$profileError = null;

$counts = [
    'orders' => 0,
    'builds' => 0,
    'wishlist' => 0,
    'consultations' => 0,
    'reviews' => 0,
];

/** @var list<array{type:string,label:string,detail:string,when:string,href:?string}> $activity */
$activity = [];

try {
    $pdo = customcore_pdo();

    // Own record only — never accept a user id from the request.
    $stmt = $pdo->prepare(
        'SELECT id, email, first_name, last_name, phone,
                address_line1, address_line2, city, province, postal_code,
                role, is_active, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if ($user === false || (int) ($user['is_active'] ?? 0) !== 1) {
        // Session points at a missing/disabled account — force logout.
        customcore_logout();
        customcore_session_start();
        customcore_flash_error('Your account is no longer available. Please contact support.');
        customcore_redirect('login.php');
    }

    // Keep session display name in sync with the database.
    $_SESSION['user_name'] = (string) $user['first_name'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_role'] = (string) $user['role'];

    // --- Activity counts (own rows only) ---------------------------------
    $countQueries = [
        'orders' => 'SELECT COUNT(*) FROM orders WHERE user_id = :uid',
        'builds' => 'SELECT COUNT(*) FROM saved_builds WHERE user_id = :uid',
        'consultations' => 'SELECT COUNT(*) FROM consultation_requests WHERE user_id = :uid',
        'reviews' => 'SELECT COUNT(*) FROM reviews WHERE user_id = :uid',
        'wishlist' => 'SELECT COUNT(*)
                       FROM wishlist_items wi
                       INNER JOIN wishlists w ON w.id = wi.wishlist_id
                       WHERE w.user_id = :uid',
    ];

    foreach ($countQueries as $key => $sql) {
        $cStmt = $pdo->prepare($sql);
        $cStmt->execute([':uid' => $userId]);
        $counts[$key] = (int) $cStmt->fetchColumn();
    }

    // --- Recent activity (own rows only) ---------------------------------
    $orderStmt = $pdo->prepare(
        'SELECT order_number, status, total, created_at
         FROM orders
         WHERE user_id = :uid
         ORDER BY created_at DESC
         LIMIT 5'
    );
    $orderStmt->execute([':uid' => $userId]);
    foreach ($orderStmt->fetchAll() as $row) {
        $activity[] = [
            'type' => 'order',
            'label' => 'Order ' . (string) $row['order_number'],
            'detail' => 'Status: ' . (string) $row['status']
                . ' · $' . number_format((float) $row['total'], 2),
            'when' => (string) $row['created_at'],
            'href' => is_file(__DIR__ . '/order-detail.php')
                ? 'orders.php'
                : null,
        ];
    }

    $buildStmt = $pdo->prepare(
        'SELECT id, name, total_price, compatibility_status, updated_at
         FROM saved_builds
         WHERE user_id = :uid
         ORDER BY updated_at DESC
         LIMIT 5'
    );
    $buildStmt->execute([':uid' => $userId]);
    foreach ($buildStmt->fetchAll() as $row) {
        $activity[] = [
            'type' => 'build',
            'label' => 'Saved build: ' . (string) $row['name'],
            'detail' => '$' . number_format((float) $row['total_price'], 2)
                . ' · ' . (string) $row['compatibility_status'],
            'when' => (string) $row['updated_at'],
            'href' => is_file(__DIR__ . '/saved-builds.php')
                ? 'saved-builds.php'
                : null,
        ];
    }

    $consultStmt = $pdo->prepare(
        'SELECT id, status, created_at
         FROM consultation_requests
         WHERE user_id = :uid
         ORDER BY created_at DESC
         LIMIT 5'
    );
    $consultStmt->execute([':uid' => $userId]);
    foreach ($consultStmt->fetchAll() as $row) {
        $activity[] = [
            'type' => 'consultation',
            'label' => 'Consultation #' . (string) $row['id'],
            'detail' => 'Status: ' . (string) $row['status'],
            'when' => (string) $row['created_at'],
            'href' => is_file(__DIR__ . '/consultation-history.php')
                ? 'consultation-history.php'
                : null,
        ];
    }

    $reviewStmt = $pdo->prepare(
        'SELECT r.rating, r.title, r.status, r.created_at, p.name AS product_name
         FROM reviews r
         INNER JOIN products p ON p.id = r.product_id
         WHERE r.user_id = :uid
         ORDER BY r.created_at DESC
         LIMIT 5'
    );
    $reviewStmt->execute([':uid' => $userId]);
    foreach ($reviewStmt->fetchAll() as $row) {
        $activity[] = [
            'type' => 'review',
            'label' => 'Review: ' . (string) ($row['title'] !== '' ? $row['title'] : $row['product_name']),
            'detail' => customcore_format_rating((int) $row['rating'])
                . ' · ' . (string) $row['status']
                . ' · ' . (string) $row['product_name'],
            'when' => (string) $row['created_at'],
            'href' => null,
        ];
    }

    usort($activity, static function (array $a, array $b): int {
        return strcmp($b['when'], $a['when']);
    });
    $activity = array_slice($activity, 0, 8);
} catch (Throwable $exception) {
    $profileError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Your profile data is temporarily unavailable.';
}

$firstName = $user !== null ? (string) $user['first_name'] : customcore_current_user_name();
$lastName = $user !== null ? (string) $user['last_name'] : '';
$fullName = trim($firstName . ' ' . $lastName);
$email = $user !== null ? (string) $user['email'] : customcore_current_user_email();
$memberSince = ($user !== null && !empty($user['created_at']))
    ? customcore_format_date((string) $user['created_at'])
    : '';

$pageTitle = 'My account — CustomCore';
$pageDescription = 'Your CustomCore account dashboard: profile summary and activity.';
$pageKeywords = 'CustomCore, profile, my account, dashboard';
$currentPage = 'profile';
$accountNavCurrent = 'profile';

$hasEditProfile = is_file(__DIR__ . '/edit-profile.php');

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section profile-page" aria-labelledby="profile-heading">
    <header class="profile-page__header">
        <h1 id="profile-heading">My account</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/accounts.html')); ?>">Accounts guide</a>
        </p>
    </header>

    <?php if ($profileError !== null) : ?>
        <div class="flash flash--warning" role="status">
            <?php echo customcore_e($profileError); ?>
        </div>
    <?php endif; ?>

    <div class="layout-split layout-split--account">
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <div class="profile-page__main">
            <?php if ($user !== null) : ?>
                <div class="profile-welcome">
                    <h2 class="profile-welcome__hello">
                        Hello, <?php echo customcore_e($firstName !== '' ? $firstName : 'there'); ?>
                    </h2>
                    <p class="profile-welcome__meta">
                        <?php echo customcore_e($email); ?>
                        <?php if ($memberSince !== '') : ?>
                            · Member since <?php echo customcore_e($memberSince); ?>
                        <?php endif; ?>
                        · Role: <?php echo customcore_e((string) $user['role']); ?>
                    </p>
                </div>

                <div class="profile-stats" aria-label="Account activity summary">
                    <article class="profile-stat">
                        <p class="profile-stat__value"><?php echo customcore_e((string) $counts['orders']); ?></p>
                        <p class="profile-stat__label">Orders</p>
                    </article>
                    <article class="profile-stat">
                        <p class="profile-stat__value"><?php echo customcore_e((string) $counts['builds']); ?></p>
                        <p class="profile-stat__label">Saved builds</p>
                    </article>
                    <article class="profile-stat">
                        <p class="profile-stat__value"><?php echo customcore_e((string) $counts['wishlist']); ?></p>
                        <p class="profile-stat__label">Wishlist items</p>
                    </article>
                    <article class="profile-stat">
                        <p class="profile-stat__value"><?php echo customcore_e((string) $counts['consultations']); ?></p>
                        <p class="profile-stat__label">Consultations</p>
                    </article>
                    <article class="profile-stat">
                        <p class="profile-stat__value"><?php echo customcore_e((string) $counts['reviews']); ?></p>
                        <p class="profile-stat__label">Reviews</p>
                    </article>
                </div>

                <section class="profile-details" aria-labelledby="details-heading">
                    <h2 id="details-heading">Profile details</h2>
                    <dl class="profile-details__list">
                        <div>
                            <dt>Name</dt>
                            <dd><?php echo customcore_e($fullName !== '' ? $fullName : '—'); ?></dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd><?php echo customcore_e($email); ?></dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd><?php
                                $phone = (string) ($user['phone'] ?? '');
                                echo customcore_e($phone !== '' ? $phone : 'Not set');
                            ?></dd>
                        </div>
                        <div>
                            <dt>Address</dt>
                            <dd class="profile-details__address"><?php
                                $addr1 = (string) ($user['address_line1'] ?? '');
                                $addr2 = (string) ($user['address_line2'] ?? '');
                                $city = (string) ($user['city'] ?? '');
                                $province = (string) ($user['province'] ?? '');
                                $postal = (string) ($user['postal_code'] ?? '');
                                $cityLine = trim($city . ($province !== '' ? ', ' . $province : ''));
                                $lines = array_values(array_filter(
                                    [$addr1, $addr2, $cityLine, $postal],
                                    static function (string $p): bool {
                                        return $p !== '';
                                    }
                                ));
                                if ($lines === []) {
                                    echo 'Not set';
                                } else {
                                    echo customcore_e(implode(', ', $lines));
                                }
                            ?></dd>
                        </div>
                    </dl>
                </section>

                <section class="profile-activity" aria-labelledby="activity-heading">
                    <h2 id="activity-heading">Recent activity</h2>
                    <?php if ($activity === []) : ?>
                        <p class="empty-state">
                            No account activity yet. Browse the catalogue, try the PC Builder,
                            or leave a product review once those features are available.
                        </p>
                    <?php else : ?>
                        <ul class="profile-activity__list">
                            <?php foreach ($activity as $item) : ?>
                                <li class="profile-activity__item">
                                    <p class="profile-activity__label">
                                        <?php if (!empty($item['href'])) : ?>
                                            <a href="<?php echo customcore_e(customcore_url((string) $item['href'])); ?>">
                                                <?php echo customcore_e($item['label']); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo customcore_e($item['label']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="profile-activity__detail">
                                        <?php echo customcore_e($item['detail']); ?>
                                        · <?php echo customcore_e(customcore_format_date($item['when'])); ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <div class="form-actions profile-page__actions">
                    <?php if ($hasEditProfile) : ?>
                        <a class="button" href="<?php echo customcore_e(customcore_url('edit-profile.php')); ?>">
                            Edit profile
                        </a>
                    <?php endif; ?>
                    <a class="button<?php echo $hasEditProfile ? ' button--secondary' : ''; ?>" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                        Browse catalogue
                    </a>
                    <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">
                        PC Builder
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
