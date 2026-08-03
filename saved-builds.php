<?php
/**
 * CustomCore — Saved Builds List (Commit 5.7).
 *
 * File responsibility:
 *   Lists all builds saved by the logged-in customer. Shows name, date,
 *   component count, total price, and compatibility status for each build.
 *   Links to the single-build detail page for viewing / editing / deleting.
 *
 * Authentication requirements:
 *   Logged-in customer (customcore_require_login). Only shows the current
 *   user's builds — never exposes another user's data.
 *
 * Database queries:
 *   - saved_builds (user_id scoped)
 *   - saved_build_items (COUNT per build)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';

customcore_require_login();

$userId = customcore_current_user_id();

$pageTitle = 'Saved builds — CustomCore';
$pageDescription = 'View and manage your saved custom PC builds.';
$pageKeywords = 'CustomCore, saved builds, custom PC, manage builds';
$currentPage = 'builder';
$accountNavCurrent = 'builds';

// ---------------------------------------------------------------------------
// Load user's saved builds
// ---------------------------------------------------------------------------

$builds = [];
$loadError = null;

try {
    $pdo = customcore_pdo();

    $stmt = $pdo->prepare(
        'SELECT sb.id, sb.name, sb.total_price, sb.compatibility_status,
                sb.notes, sb.created_at, sb.updated_at,
                COUNT(sbi.id) AS item_count
         FROM saved_builds sb
         LEFT JOIN saved_build_items sbi ON sbi.saved_build_id = sb.id
         WHERE sb.user_id = :uid
         GROUP BY sb.id
         ORDER BY sb.updated_at DESC'
    );
    $stmt->execute([':uid' => $userId]);
    $builds = $stmt->fetchAll();
} catch (Throwable $exception) {
    $loadError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load your saved builds right now. Please try again later.';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Saved builds page: the customer's stored PC builds -->
<section class="content-section profile-page" aria-labelledby="builds-heading">
    <header class="profile-page__header">
        <h1 id="builds-heading">Saved builds</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html#saved-builds')); ?>">Saving builds</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html#manage-builds')); ?>">Manage builds</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html')); ?>">Full PC Builder guide</a>
        </p>
    </header>

    <!-- Account layout: sidebar navigation plus main content -->
    <div class="layout-split layout-split--account">
        <!-- Account section navigation -->
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <div class="profile-page__main">
            <?php if ($loadError !== null): ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($loadError); ?>
                </div>
            <?php elseif ($builds === []): ?>
                <!-- Empty state: no saved builds yet -->
                <div class="saved-builds-empty">
                    <p>You have no saved builds yet.</p>
                    <a class="button" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">
                        Start building
                    </a>
                </div>
            <?php else: ?>
                <!-- Saved builds grid -->
                <div class="saved-builds-grid">
                    <?php foreach ($builds as $b): ?>
                        <?php
                        $buildId = (int) $b['id'];
                        $buildName = (string) $b['name'];
                        $total = (float) $b['total_price'];
                        $compat = (string) $b['compatibility_status'];
                        $itemCount = (int) $b['item_count'];
                        $createdAt = (string) $b['created_at'];
                        $updatedAt = (string) $b['updated_at'];
                        $notes = $b['notes'];

                        $compatClass = 'compat-badge--compatible';
                        $compatLabel = 'Compatible';
                        if ($compat === 'warning') {
                            $compatClass = 'compat-badge--warning';
                            $compatLabel = 'Warning';
                        } elseif ($compat === 'incompatible') {
                            $compatClass = 'compat-badge--incompatible';
                            $compatLabel = 'Incompatible';
                        }

                        $dateDisplay = '';
                        try {
                            $dt = new DateTimeImmutable($updatedAt);
                            $dateDisplay = $dt->format('M j, Y');
                        } catch (Throwable $e) {
                            $dateDisplay = $updatedAt;
                        }
                        ?>
                        <article class="saved-build-card">
                            <header class="saved-build-card__header">
                                <h2 class="saved-build-card__name">
                                    <a href="<?php echo customcore_e(customcore_url('saved-build.php?id=' . $buildId)); ?>">
                                        <?php echo customcore_e($buildName); ?>
                                    </a>
                                </h2>
                                <span class="compat-badge <?php echo customcore_e($compatClass); ?>">
                                    <?php echo customcore_e($compatLabel); ?>
                                </span>
                            </header>
                            <dl class="saved-build-card__meta">
                                <div class="saved-build-card__meta-item">
                                    <dt>Components</dt>
                                    <dd><?php echo $itemCount; ?></dd>
                                </div>
                                <div class="saved-build-card__meta-item">
                                    <dt>Total</dt>
                                    <dd>$<?php echo customcore_e(number_format($total, 2)); ?></dd>
                                </div>
                                <div class="saved-build-card__meta-item">
                                    <dt>Last updated</dt>
                                    <dd><?php echo customcore_e($dateDisplay); ?></dd>
                                </div>
                            </dl>
                            <?php if ($notes !== null && trim((string) $notes) !== ''): ?>
                                <p class="saved-build-card__notes">
                                    <?php echo customcore_e(mb_strimwidth(trim((string) $notes), 0, 100, '…')); ?>
                                </p>
                            <?php endif; ?>
                            <footer class="saved-build-card__footer">
                                <a
                                    class="button button--sm"
                                    href="<?php echo customcore_e(customcore_url('saved-build.php?id=' . $buildId)); ?>"
                                >View / Edit</a>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Create-new-build action -->
                <div class="saved-builds-actions">
                    <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">
                        Create a new build
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
