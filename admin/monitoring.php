<?php
/**
 * CustomCore — Administrator monitoring dashboard (Commits 13.2–13.3).
 *
 * File responsibility:
 *   Protected back-office page that renders the Stage 13 health-check report as
 *   an online / warning / offline status table, plus live monitoring statistics
 *   (products, users, orders, consultation requests, images, and stock). Health
 *   checks come from customcore_monitoring_run(); statistics from
 *   customcore_monitoring_stats(). Both are isolated so a database failure never
 *   blanks the status table — filesystem image/media counts still appear.
 *
 * Authentication requirements:
 *   Administrator role (customcore_require_admin()). This guard uses session
 *   state only, so the page still renders when the database is offline.
 *
 * Security:
 *   All output is escaped. The monitoring engine returns production-safe
 *   summaries only (no credentials, absolute paths, or stack traces).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/monitoring.php';

customcore_require_admin();

$adminName = customcore_current_user_name();
$adminNavCurrent = 'monitoring';
$loadAdminCss = true;

$pageTitle = 'Service monitoring — CustomCore admin';
$pageDescription = 'CustomCore administrator monitoring dashboard: live online, warning, and offline health checks plus catalogue, order, and stock statistics.';
$pageKeywords = 'CustomCore, admin, monitoring, health, status, statistics';
$currentPage = 'admin';

// The engine is built so it cannot throw, but guard defensively so a bug in a
// single check could never blank the whole page.
$report = null;
$monitorError = null;
try {
    $report = customcore_monitoring_run();
} catch (Throwable $exception) {
    $monitorError = customcore_is_debug()
        ? $exception->getMessage()
        : 'The monitoring report is temporarily unavailable.';
}

// Statistics are loaded separately so a DB failure never hides the status table.
$stats = customcore_monitoring_stats();

// Overall status + per-status counts for the summary banner.
$overallStatus = $report['overall'] ?? 'warning';
$checks = isset($report['checks']) && is_array($report['checks']) ? $report['checks'] : [];
$statusCounts = ['online' => 0, 'warning' => 0, 'offline' => 0];
foreach ($checks as $check) {
    $key = (string) ($check['status'] ?? '');
    if (isset($statusCounts[$key])) {
        $statusCounts[$key]++;
    }
}

$overallSummary = 'All monitored services are online.';
if ($overallStatus === 'offline') {
    $overallSummary = 'One or more critical services are offline and need immediate attention.';
} elseif ($overallStatus === 'warning') {
    $overallSummary = 'Core services are online, but one or more need attention.';
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-monitoring" aria-labelledby="monitor-heading">
    <header class="admin-page__header">
        <h1 id="monitor-heading">Service monitoring</h1>
        <p class="admin-page__intro">
            Live health checks for CustomCore's core services, plus live counts for
            products, users, orders, consultation requests, images, and stock.
            Every figure is read from the running application or MySQL — never hard-coded.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('index.php')); ?>">Back to store</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Admin dashboard</a>
        </p>
    </header>

    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <?php if ($monitorError !== null || $report === null) : ?>
        <p class="flash flash--error" role="alert">
            <?php echo customcore_e($monitorError ?? 'The monitoring report is temporarily unavailable.'); ?>
        </p>
    <?php else : ?>

        <section
            class="monitor-overall monitor-overall--<?php echo customcore_e($overallStatus); ?>"
            aria-labelledby="monitor-overall-heading"
            role="status"
        >
            <div class="monitor-overall__head">
                <span class="admin-badge <?php echo customcore_e(customcore_monitoring_status_badge_class($overallStatus)); ?>">
                    <?php echo customcore_e(customcore_monitoring_status_label($overallStatus)); ?>
                </span>
                <h2 id="monitor-overall-heading" class="monitor-overall__title">Overall status</h2>
            </div>
            <p class="monitor-overall__summary"><?php echo customcore_e($overallSummary); ?></p>
            <p class="monitor-overall__meta">
                <?php echo customcore_e((string) $statusCounts['online']); ?> online
                · <?php echo customcore_e((string) $statusCounts['warning']); ?> warning
                · <?php echo customcore_e((string) $statusCounts['offline']); ?> offline
                <?php if (!empty($report['generated_at'])) : ?>
                    · checked <?php echo customcore_e((string) $report['generated_at']); ?>
                <?php endif; ?>
            </p>
        </section>

        <section class="monitor-checks" aria-labelledby="monitor-checks-heading">
            <h2 id="monitor-checks-heading">Service checks</h2>
            <table class="admin-table admin-table--monitor">
                <thead>
                    <tr>
                        <th scope="col">Service</th>
                        <th scope="col">Status</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checks as $check) : ?>
                        <?php
                        $status = (string) ($check['status'] ?? 'warning');
                        $label = (string) ($check['label'] ?? 'Service');
                        $summary = (string) ($check['summary'] ?? '');
                        $details = isset($check['details']) && is_array($check['details']) ? $check['details'] : [];
                        ?>
                        <tr class="monitor-row monitor-row--<?php echo customcore_e($status); ?>">
                            <th scope="row" class="monitor-row__service"><?php echo customcore_e($label); ?></th>
                            <td class="monitor-row__status">
                                <span class="admin-badge <?php echo customcore_e(customcore_monitoring_status_badge_class($status)); ?>">
                                    <?php echo customcore_e(customcore_monitoring_status_label($status)); ?>
                                </span>
                            </td>
                            <td class="monitor-row__details">
                                <p class="monitor-row__summary"><?php echo customcore_e($summary); ?></p>
                                <?php if ($details !== []) : ?>
                                    <ul class="monitor-row__list">
                                        <?php foreach ($details as $detail) : ?>
                                            <li><?php echo customcore_e((string) $detail); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="monitor-stats admin-stats" aria-labelledby="monitor-stats-heading">
            <h2 id="monitor-stats-heading">Live statistics</h2>
            <p class="monitor-stats__intro">
                Catalogue, account, order, request, image, and stock counts.
                Database totals reuse the same queries as the admin dashboard so the
                numbers always match.
            </p>

            <?php if (!$stats['available']) : ?>
                <p class="flash flash--warning" role="status">
                    <?php echo customcore_e((string) ($stats['error'] ?? 'Live database statistics are temporarily unavailable.')); ?>
                    Image and media file counts below are still read from disk.
                </p>
            <?php endif; ?>

            <div class="admin-stats__grid">
                <article class="admin-stat">
                    <h3 class="admin-stat__label">Products</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['products_active']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['products_total']); ?> total
                        · <?php echo customcore_e((string) $stats['products_inactive']); ?> disabled
                    </p>
                </article>

                <article class="admin-stat">
                    <h3 class="admin-stat__label">Users</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['users_total']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['users_customers']); ?> customers
                        · <?php echo customcore_e((string) $stats['users_admins']); ?> admins
                    </p>
                </article>

                <article class="admin-stat">
                    <h3 class="admin-stat__label">Orders</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['orders_total']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['orders_open']); ?> in progress
                    </p>
                </article>

                <article class="admin-stat">
                    <h3 class="admin-stat__label">Consultation requests</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['consultations_total']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['consultations_needs_attention']); ?> need attention
                        <?php if ((int) $stats['contact_unread'] > 0) : ?>
                            · <?php echo customcore_e((string) $stats['contact_unread']); ?> unread contact
                        <?php endif; ?>
                    </p>
                </article>

                <article class="admin-stat">
                    <h3 class="admin-stat__label">Product images</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) $stats['images_product_total']); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['images_product_seeded']); ?> seeded
                        · <?php echo customcore_e((string) $stats['images_product_uploaded']); ?> uploaded
                        · <?php echo customcore_e((string) $stats['images_site']); ?> site extras
                    </p>
                </article>

                <article class="admin-stat">
                    <h3 class="admin-stat__label">Stock warnings</h3>
                    <p class="admin-stat__value"><?php echo customcore_e((string) ((int) $stats['stock_low'] + (int) $stats['stock_out'])); ?></p>
                    <p class="admin-stat__meta">
                        <?php echo customcore_e((string) $stats['stock_low']); ?> low (≤ <?php echo customcore_e((string) $stats['low_stock_threshold']); ?>)
                        · <?php echo customcore_e((string) $stats['stock_out']); ?> out of stock
                    </p>
                </article>
            </div>

            <div class="monitor-stats__extra">
                <p>
                    Learning Centre media:
                    <strong><?php echo customcore_e((string) $stats['media_lessons_available']); ?></strong>
                    of
                    <strong><?php echo customcore_e((string) $stats['media_lessons_declared']); ?></strong>
                    declared lesson(s) available on disk
                    <?php if ((int) $stats['reviews_pending'] > 0) : ?>
                        ·
                        <strong><?php echo customcore_e((string) $stats['reviews_pending']); ?></strong>
                        review(s) awaiting moderation
                    <?php endif; ?>
                    <?php if (!empty($stats['generated_at'])) : ?>
                        · counted <?php echo customcore_e((string) $stats['generated_at']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </section>

        <section class="monitor-legend" aria-labelledby="monitor-legend-heading">
            <h2 id="monitor-legend-heading">What the statuses mean</h2>
            <ul class="monitor-legend__list">
                <li>
                    <span class="admin-badge admin-badge--ok">Online</span>
                    The service is fully operational.
                </li>
                <li>
                    <span class="admin-badge admin-badge--warn">Warning</span>
                    Degraded, or a non-critical dependency is missing; the core site still works.
                </li>
                <li>
                    <span class="admin-badge admin-badge--danger">Offline</span>
                    A critical dependency is unavailable and needs immediate attention.
                </li>
            </ul>
            <p class="monitor-legend__note">
                Reload this page to run the checks and recount statistics.
                A troubleshooting guide for each warning arrives in commit 13.5.
            </p>
        </section>

    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
