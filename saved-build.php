<?php
/**
 * CustomCore — Single Saved Build Management (Commit 5.7).
 *
 * File responsibility:
 *   Displays a single saved build that belongs to the logged-in customer.
 *   Supports rename, delete, and "edit in builder" (reloads into session).
 *   Ownership is enforced: users cannot view or modify another user's build.
 *
 * Supported actions (POST):
 *   - rename: Update the build name and/or notes.
 *   - delete: Remove the build and its items (cascade).
 *   - edit:   Reload the build into the session builder and redirect.
 *
 * Authentication requirements:
 *   Logged-in customer. Ownership is verified via user_id = session user.
 *
 * Database queries:
 *   - saved_builds (owner-scoped)
 *   - saved_build_items + components + component_categories (for display)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/performance.php';

customcore_require_login();

$userId = customcore_current_user_id();
$accountNavCurrent = 'builds';

// ---------------------------------------------------------------------------
// Validate and load build (ownership enforced)
// ---------------------------------------------------------------------------

$buildId = 0;
if (isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id'])) {
    $buildId = (int) $_GET['id'];
}

if ($buildId <= 0) {
    customcore_flash_error('Invalid build ID.');
    customcore_redirect('saved-builds.php');
}

$build = null;
$items = [];
$loadError = null;

try {
    $pdo = customcore_pdo();

    $buildStmt = $pdo->prepare(
        'SELECT id, user_id, name, total_price, compatibility_status, notes,
                created_at, updated_at
         FROM saved_builds
         WHERE id = :id AND user_id = :uid
         LIMIT 1'
    );
    $buildStmt->execute([':id' => $buildId, ':uid' => $userId]);
    $build = $buildStmt->fetch();
} catch (Throwable $exception) {
    $loadError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load this build right now. Please try again later.';
}

if ($build === false || $build === null) {
    if ($loadError === null) {
        customcore_flash_error('Build not found or you do not have permission to view it.');
    } else {
        customcore_flash_error($loadError);
    }
    customcore_redirect('saved-builds.php');
}

// Load items with component details.
try {
    $itemStmt = $pdo->prepare(
        'SELECT sbi.id AS item_id, sbi.component_id, sbi.unit_price,
                c.name AS component_name, c.brand, c.is_active,
                c.component_category_id, cc.name AS category_name,
                cc.slug AS category_slug, cc.sort_order,
                c.socket, c.ram_type, c.form_factor,
                c.psu_wattage, c.wattage_estimate, c.storage_interface,
                c.cooler_type, c.gpu_length_mm,
                c.performance_gaming, c.performance_productivity
         FROM saved_build_items sbi
         JOIN components c ON c.id = sbi.component_id
         JOIN component_categories cc ON cc.id = c.component_category_id
         WHERE sbi.saved_build_id = :bid
         ORDER BY cc.sort_order ASC'
    );
    $itemStmt->execute([':bid' => $buildId]);
    $items = $itemStmt->fetchAll();
} catch (Throwable $exception) {
    $loadError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Could not load build components.';
}

// ---------------------------------------------------------------------------
// Handle POST actions (rename, delete, edit-in-builder)
// ---------------------------------------------------------------------------

$actionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = customcore_csrf_verify(
        isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
    );

    if (!$csrfOk) {
        $actionError = 'Your session expired. Please try again.';
    } else {
        $action = isset($_POST['action']) && is_string($_POST['action'])
            ? $_POST['action']
            : '';

        switch ($action) {
            case 'rename':
                $newName = isset($_POST['build_name']) && is_string($_POST['build_name'])
                    ? trim($_POST['build_name'])
                    : '';

                if ($newName === '') {
                    $newName = 'My Build';
                }
                if (mb_strlen($newName) > 200) {
                    $newName = mb_substr($newName, 0, 200);
                }

                $newNotes = isset($_POST['build_notes']) && is_string($_POST['build_notes'])
                    ? trim($_POST['build_notes'])
                    : null;
                if ($newNotes !== null && $newNotes === '') {
                    $newNotes = null;
                }

                try {
                    $renameStmt = $pdo->prepare(
                        'UPDATE saved_builds
                         SET name = :name, notes = :notes
                         WHERE id = :id AND user_id = :uid'
                    );
                    $renameStmt->execute([
                        ':name' => $newName,
                        ':notes' => $newNotes,
                        ':id' => $buildId,
                        ':uid' => $userId,
                    ]);

                    customcore_flash_success('Build renamed successfully.');
                    customcore_redirect('saved-build.php?id=' . $buildId);
                } catch (Throwable $exception) {
                    $actionError = customcore_is_debug()
                        ? $exception->getMessage()
                        : 'Could not rename the build. Please try again.';
                }
                break;

            case 'delete':
                try {
                    $deleteStmt = $pdo->prepare(
                        'DELETE FROM saved_builds WHERE id = :id AND user_id = :uid'
                    );
                    $deleteStmt->execute([':id' => $buildId, ':uid' => $userId]);

                    customcore_flash_success('Build deleted.');
                    customcore_redirect('saved-builds.php');
                } catch (Throwable $exception) {
                    $actionError = customcore_is_debug()
                        ? $exception->getMessage()
                        : 'Could not delete the build. Please try again.';
                }
                break;

            case 'edit':
                // Reload this build's components into the session builder.
                $_SESSION['_cc_build'] = [];
                foreach ($items as $item) {
                    $catId = (int) $item['component_category_id'];
                    $compId = (int) $item['component_id'];
                    if ($catId > 0 && $compId > 0) {
                        $_SESSION['_cc_build'][$catId] = $compId;
                    }
                }

                customcore_flash_success('Build loaded into the builder. Make your changes, then save again.');
                customcore_redirect('builder.php');
                break;

            default:
                $actionError = 'Unknown action.';
        }
    }
}

// ---------------------------------------------------------------------------
// Page setup and render
// ---------------------------------------------------------------------------

$buildName = (string) $build['name'];
$buildNotes = $build['notes'];
$totalPrice = (float) $build['total_price'];
$compatStatus = (string) $build['compatibility_status'];
$createdAt = (string) $build['created_at'];
$updatedAt = (string) $build['updated_at'];

$compatLabel = 'Compatible';
$compatBadgeClass = 'compat-badge--compatible';
if ($compatStatus === 'warning') {
    $compatLabel = 'Warning';
    $compatBadgeClass = 'compat-badge--warning';
} elseif ($compatStatus === 'incompatible') {
    $compatLabel = 'Incompatible';
    $compatBadgeClass = 'compat-badge--incompatible';
}

$dateCreated = '';
$dateUpdated = '';
try {
    $dateCreated = (new DateTimeImmutable($createdAt))->format('M j, Y \a\t g:i A');
    $dateUpdated = (new DateTimeImmutable($updatedAt))->format('M j, Y \a\t g:i A');
} catch (Throwable $e) {
    $dateCreated = $createdAt;
    $dateUpdated = $updatedAt;
}

$pageTitle = customcore_e($buildName) . ' — Saved build — CustomCore';
$pageDescription = 'View and manage your saved build: ' . $buildName;
$pageKeywords = 'CustomCore, saved build, manage, rename, delete';
$currentPage = 'builder';
$loadCharts = true;

// Performance report for chart + text fallback.
$perfReport = [
    'gaming' => 0,
    'productivity' => 0,
    'upgrade_gaming' => 0,
    'upgrade_productivity' => 0,
    'upgrade_headroom' => 0,
];
$perfComponentIds = [];
try {
    $perfRows = [];
    foreach ($items as $item) {
        $perfComponentIds[] = (int) $item['component_id'];
        $perfRows[] = [
            'name' => (string) $item['component_name'],
            'category_slug' => (string) ($item['category_slug'] ?? ''),
            'category_name' => (string) $item['category_name'],
            'performance_gaming' => $item['performance_gaming'],
            'performance_productivity' => $item['performance_productivity'],
        ];
    }
    $perfReport = customcore_performance_report($pdo, $perfRows);
} catch (Throwable $exception) {
    // Chart still loads via API if this fails.
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section profile-page" aria-labelledby="build-heading">
    <header class="profile-page__header">
        <h1 id="build-heading"><?php echo customcore_e($buildName); ?></h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html#manage-builds')); ?>">Manage builds guide</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html#performance')); ?>">Performance chart</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html')); ?>">Full PC Builder guide</a>
        </p>
    </header>

    <div class="layout-split layout-split--account">
        <aside class="profile-page__aside">
            <?php require __DIR__ . '/includes/account-nav.php'; ?>
        </aside>

        <div class="profile-page__main">
            <?php if ($actionError !== null): ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($actionError); ?>
                </div>
            <?php endif; ?>

            <?php if ($loadError !== null): ?>
                <div class="flash flash--warning" role="status">
                    <?php echo customcore_e($loadError); ?>
                </div>
            <?php endif; ?>

            <!-- Build overview -->
            <div class="saved-build-detail">
                <div class="saved-build-detail__meta">
                    <span class="compat-badge <?php echo customcore_e($compatBadgeClass); ?>">
                        <?php echo customcore_e($compatLabel); ?>
                    </span>
                    <span class="saved-build-detail__total">
                        Total: $<?php echo customcore_e(number_format($totalPrice, 2)); ?>
                    </span>
                    <span class="saved-build-detail__date">
                        Created <?php echo customcore_e($dateCreated); ?>
                        <?php if ($dateUpdated !== $dateCreated): ?>
                            · Updated <?php echo customcore_e($dateUpdated); ?>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($buildNotes !== null && trim((string) $buildNotes) !== ''): ?>
                    <p class="saved-build-detail__notes">
                        <?php echo customcore_e(trim((string) $buildNotes)); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Component list -->
            <?php if ($items !== []): ?>
                <h2 class="saved-build-section-title">Components</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Category</th>
                                <th scope="col">Component</th>
                                <th scope="col">Details</th>
                                <th scope="col" class="data-table__num">Saved price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php
                                $isActive = (int) $item['is_active'] === 1;
                                $attrs = [];
                                if (!empty($item['socket'])) {
                                    $attrs[] = 'Socket ' . $item['socket'];
                                }
                                if (!empty($item['ram_type'])) {
                                    $attrs[] = $item['ram_type'];
                                }
                                if (!empty($item['form_factor'])) {
                                    $attrs[] = $item['form_factor'];
                                }
                                if (!empty($item['psu_wattage']) && (int) $item['psu_wattage'] > 0) {
                                    $attrs[] = (int) $item['psu_wattage'] . 'W';
                                }
                                if (
                                    !empty($item['wattage_estimate'])
                                    && (int) $item['wattage_estimate'] > 0
                                    && empty($item['psu_wattage'])
                                ) {
                                    $attrs[] = 'TDP ' . (int) $item['wattage_estimate'] . 'W';
                                }
                                if (!empty($item['storage_interface'])) {
                                    $attrs[] = $item['storage_interface'];
                                }
                                if (!empty($item['cooler_type'])) {
                                    $attrs[] = $item['cooler_type'];
                                }
                                if (!empty($item['gpu_length_mm']) && (int) $item['gpu_length_mm'] > 0) {
                                    $attrs[] = (int) $item['gpu_length_mm'] . 'mm';
                                }
                                $unitPrice = (float) $item['unit_price'];
                                ?>
                                <tr<?php echo !$isActive ? ' class="saved-build-item--inactive"' : ''; ?>>
                                    <th scope="row"><?php echo customcore_e((string) $item['category_name']); ?></th>
                                    <td>
                                        <span class="saved-build-item__name"><?php echo customcore_e((string) $item['component_name']); ?></span>
                                        <?php if ((string) ($item['brand'] ?? '') !== ''): ?>
                                            <span class="saved-build-item__brand"><?php echo customcore_e((string) $item['brand']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!$isActive): ?>
                                            <span class="saved-build-item__badge saved-build-item__badge--warn">Discontinued</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="saved-build-item__attrs">
                                        <?php echo $attrs !== [] ? customcore_e(implode(' · ', $attrs)) : '—'; ?>
                                    </td>
                                    <td class="data-table__num">
                                        <?php echo $unitPrice > 0
                                            ? '$' . customcore_e(number_format($unitPrice, 2))
                                            : 'Included'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row" colspan="3">Total (at time of save)</th>
                                <td class="data-table__num data-table__total">
                                    $<?php echo customcore_e(number_format($totalPrice, 2)); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($perfComponentIds !== []): ?>
                <div class="saved-build-perf">
                    <?php
                    $perfChartApi = customcore_url('api/chart-data.php');
                    $perfChartIds = $perfComponentIds;
                    $perfChartForm = '';
                    $perfChartTitle = 'Performance visualization';
                    require __DIR__ . '/includes/perf-chart.php';
                    ?>
                </div>
            <?php endif; ?>

            <!-- Management actions -->
            <div class="saved-build-actions">
                <h2 class="saved-build-section-title">Manage this build</h2>

                <!-- Rename form -->
                <details class="saved-build-action-panel" open>
                    <summary class="saved-build-action-panel__title">Rename / update notes</summary>
                    <form class="saved-build-rename-form" method="post" action="<?php echo customcore_e(customcore_url('saved-build.php?id=' . $buildId)); ?>">
                        <?php echo customcore_csrf_field(); ?>
                        <input type="hidden" name="action" value="rename">

                        <div class="saved-build-rename-form__field">
                            <label for="build-name">Build name</label>
                            <input
                                type="text"
                                id="build-name"
                                name="build_name"
                                class="form-input"
                                maxlength="200"
                                value="<?php echo customcore_e($buildName); ?>"
                                required
                            >
                        </div>

                        <div class="saved-build-rename-form__field">
                            <label for="build-notes">Notes <span class="saved-build-rename-form__optional">(optional)</span></label>
                            <textarea
                                id="build-notes"
                                name="build_notes"
                                class="form-input form-textarea"
                                rows="3"
                                maxlength="2000"
                            ><?php echo customcore_e($buildNotes !== null ? trim((string) $buildNotes) : ''); ?></textarea>
                        </div>

                        <button type="submit" class="button button--sm">Save changes</button>
                    </form>
                </details>

                <!-- Edit in builder -->
                <details class="saved-build-action-panel">
                    <summary class="saved-build-action-panel__title">Edit in builder</summary>
                    <p class="saved-build-action-panel__desc">
                        Load this build back into the PC Builder so you can swap components.
                        After editing, save again from the summary page.
                    </p>
                    <form method="post" action="<?php echo customcore_e(customcore_url('saved-build.php?id=' . $buildId)); ?>">
                        <?php echo customcore_csrf_field(); ?>
                        <input type="hidden" name="action" value="edit">
                        <button type="submit" class="button button--secondary button--sm">
                            Load into builder
                        </button>
                    </form>
                </details>

                <!-- Delete -->
                <details class="saved-build-action-panel saved-build-action-panel--danger">
                    <summary class="saved-build-action-panel__title">Delete this build</summary>
                    <p class="saved-build-action-panel__desc">
                        This action is permanent and cannot be undone.
                    </p>
                    <form method="post" action="<?php echo customcore_e(customcore_url('saved-build.php?id=' . $buildId)); ?>">
                        <?php echo customcore_csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="button button--danger button--sm">
                            Delete permanently
                        </button>
                    </form>
                </details>
            </div>

            <div class="saved-build-nav">
                <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('saved-builds.php')); ?>">
                    Back to all builds
                </a>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
