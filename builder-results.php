<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// PC Build Summary + Save/ 5.6).
//   GET, Renders the completed (or in-progress) build from the session: every selected component
//     with trusted database prices, a server-side compatibility report, and power / performance
//     estimates. POST, Saves the build to the database (saved_builds + saved_build_items). Requires
//     login, a complete build, and a valid CSRF token.
// Access: GET is public. POST requires an authenticated customer.
// Database queries:
//   component_categories (ordered)
//   components (selected IDs, active only)
//   compatibility_rules (via includes/compatibility.php)
//   saved_builds / saved_build_items (insert on POST)
// Session:
//   $_SESSION['_cc_build'], category ID → component ID (from builder.php). Cleared on successful
//     save.

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/compatibility.php';
require_once __DIR__ . '/includes/performance.php';

customcore_session_start();

$pageTitle = 'Build Summary | CustomCore';
$pageDescription = 'Review your custom PC build: components, trusted prices, compatibility, and estimates.';
$pageKeywords = 'CustomCore, PC builder, build summary, compatibility, custom gaming PC';
$currentPage = 'builder';

// Guest "log in to save", stash a safe return path, then send to login

if (isset($_GET['intent']) && $_GET['intent'] === 'login' && !customcore_is_logged_in()) {
    $returnPath = isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])
        ? (string) $_SERVER['SCRIPT_NAME']
        : '/builder-results.php';

    if (customcore_is_safe_local_path($returnPath)) {
        $_SESSION['_cc_return_to'] = $returnPath;
    }

    customcore_flash_warning('Please log in to save your build. Your current selections will stay in this session.');
    customcore_redirect('login.php');
}

// Read build from session

$build = [];
if (isset($_SESSION['_cc_build']) && is_array($_SESSION['_cc_build'])) {
    foreach ($_SESSION['_cc_build'] as $catId => $compId) {
        $catId = (int) $catId;
        $compId = (int) $compId;
        if ($catId > 0 && $compId > 0) {
            $build[$catId] = $compId;
        }
    }
}

if ($build === []) {
    // After a successful save, the session build is cleared. If ?saved=X is
    // present we load that saved build for display instead of redirecting.
    if (
        isset($_GET['saved'])
        && is_string($_GET['saved'])
        && ctype_digit($_GET['saved'])
        && (int) $_GET['saved'] > 0
        && customcore_is_logged_in()
    ) {
        $savedBuildId = (int) $_GET['saved'];

        try {
            $pdo = customcore_pdo();

            // Verify ownership.
            $ownerStmt = $pdo->prepare(
                'SELECT id, name, total_price, compatibility_status, notes
                 FROM saved_builds
                 WHERE id = :id AND user_id = :uid
                 LIMIT 1'
            );
            $ownerStmt->execute([':id' => $savedBuildId, ':uid' => customcore_current_user_id()]);
            $savedRow = $ownerStmt->fetch();

            if ($savedRow !== false) {
                // Reconstruct build from saved_build_items.
                $itemsStmt = $pdo->prepare(
                    'SELECT sbi.component_id
                     FROM saved_build_items sbi
                     WHERE sbi.saved_build_id = :bid'
                );
                $itemsStmt->execute([':bid' => $savedBuildId]);
                $savedItems = $itemsStmt->fetchAll();

                // Determine category for each component.
                $compIds = array_column($savedItems, 'component_id');
                if ($compIds !== []) {
                    $ph = implode(',', array_fill(0, count($compIds), '?'));
                    $catMapStmt = $pdo->prepare(
                        "SELECT id, component_category_id FROM components WHERE id IN ($ph)"
                    );
                    $catMapStmt->execute($compIds);
                    $catMapRows = $catMapStmt->fetchAll();

                    foreach ($catMapRows as $mapRow) {
                        $build[(int) $mapRow['component_category_id']] = (int) $mapRow['id'];
                    }
                }
            }
        } catch (Throwable $exception) {
            // Fall through to redirect below.
        }
    }

    if ($build === []) {
        customcore_flash_warning('Your build is empty. Select components to see a summary.');
        customcore_redirect('builder.php');
    }
}

// Load categories and selected components (trusted DB prices)

$categories = [];
$selectedByCategory = [];
$totalPrice = 0.0;
$estimatedDraw = 0;
$psuWattage = 0;
$gamingScores = [];
$productivityScores = [];
$dbError = null;
$missingRequired = [];
$staleSelections = [];

try {
    $pdo = customcore_pdo();

    $catStmt = $pdo->query(
        'SELECT id, name, slug, sort_order, is_required
         FROM component_categories
         ORDER BY sort_order ASC'
    );
    $categories = $catStmt->fetchAll();

    $ids = array_values($build);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $compStmt = $pdo->prepare(
        "SELECT c.id, c.component_category_id, cc.slug AS category_slug, cc.name AS category_name,
                c.name, c.brand, c.price, c.wattage_estimate, c.socket, c.ram_type,
                c.form_factor, c.gpu_length_mm, c.max_gpu_length_mm,
                c.cooler_height_mm, c.max_cooler_height_mm, c.cooler_type,
                c.storage_interface, c.supported_storage, c.psu_wattage,
                c.performance_gaming, c.performance_productivity
         FROM components c
         JOIN component_categories cc ON cc.id = c.component_category_id
         WHERE c.id IN ($placeholders) AND c.is_active = 1"
    );
    $compStmt->execute($ids);
    $rows = $compStmt->fetchAll();

    $foundIds = [];
    foreach ($rows as $row) {
        $catId = (int) $row['component_category_id'];
        $price = (float) $row['price'];
        $selectedByCategory[$catId] = $row;
        $foundIds[(int) $row['id']] = true;
        $totalPrice += $price;

        $slug = (string) $row['category_slug'];
        if ($slug !== 'psu') {
            $estimatedDraw += (int) ($row['wattage_estimate'] ?? 0);
        } else {
            $psuWattage = (int) ($row['psu_wattage'] ?? 0);
        }

        $gScore = $row['performance_gaming'];
        $pScore = $row['performance_productivity'];
        if ($gScore !== null && (int) $gScore > 0) {
            $gamingScores[] = (int) $gScore;
        }
        if ($pScore !== null && (int) $pScore > 0) {
            $productivityScores[] = (int) $pScore;
        }
    }

    // Detect session IDs that no longer resolve to active components.
    foreach ($build as $catId => $compId) {
        if (!isset($foundIds[$compId])) {
            $staleSelections[] = $compId;
            unset($build[$catId]);
            unset($_SESSION['_cc_build'][$catId]);
        }
    }

    foreach ($categories as $cat) {
        $catId = (int) $cat['id'];
        if ((int) $cat['is_required'] === 1 && !isset($selectedByCategory[$catId])) {
            $missingRequired[] = (string) $cat['name'];
        }
    }
} catch (Throwable $exception) {
    $dbError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load your build summary right now. Please try again later.';
}

if ($dbError !== null) {
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="content-section" aria-labelledby="results-heading">';
    echo '<h1 id="results-heading">Build summary</h1>';
    echo '<p class="flash flash--error">' . customcore_e($dbError) . '</p>';
    echo '<p><a class="button" href="' . customcore_e(customcore_url('builder.php')) . '">Return to builder</a></p>';
    echo '</section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

if ($selectedByCategory === []) {
    customcore_flash_warning('Your build selections are no longer available. Please start again.');
    unset($_SESSION['_cc_build']);
    customcore_redirect('builder.php');
}

// Compatibility + estimates

$compatReport = [
    'status' => 'compatible',
    'results' => [],
];

try {
    $compatReport = customcore_compatibility_check($pdo, array_values($build));
} catch (Throwable $exception) {
    $compatReport = [
        'status' => 'warning',
        'results' => [[
            'rule_code' => 'check_failed',
            'name' => 'Compatibility check',
            'status' => 'warning',
            'severity' => 'warning',
            'message' => customcore_is_debug()
                ? $exception->getMessage()
                : 'Compatibility could not be verified right now.',
        ]],
    ];
}

$compatStatus = (string) $compatReport['status'];
$compatResults = $compatReport['results'];

$recommendedPsu = $estimatedDraw > 0 ? (int) ceil($estimatedDraw * 1.2) : 0;

// Weighted performance report, replaces simple averages for the chart.
$perfReport = [
    'gaming' => 0,
    'productivity' => 0,
    'upgrade_gaming' => 0,
    'upgrade_productivity' => 0,
    'upgrade_headroom' => 0,
    'by_category' => [],
];
try {
    $perfRows = [];
    foreach ($selectedByCategory as $part) {
        $perfRows[] = $part;
    }
    $perfReport = customcore_performance_report($pdo, $perfRows);
} catch (Throwable $exception) {
    // Keep zeroed defaults; text UI still shows power estimates.
}

$avgGaming = (int) ($perfReport['gaming'] ?? 0);
$avgProductivity = (int) ($perfReport['productivity'] ?? 0);
$loadCharts = true;

$isLoggedIn = customcore_is_logged_in();
$isComplete = $missingRequired === [];

$compatLabel = 'Compatible';
$compatBadgeClass = 'compat-badge--compatible';
if ($compatStatus === 'warning') {
    $compatLabel = 'Warning';
    $compatBadgeClass = 'compat-badge--warning';
} elseif ($compatStatus === 'incompatible') {
    $compatLabel = 'Incompatible';
    $compatBadgeClass = 'compat-badge--incompatible';
}

// Handle POST, save build to database

$saveError = null;
$justSaved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = customcore_csrf_verify(
        isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
    );

    if (!$csrfOk) {
        $saveError = 'Your session expired. Please try again.';
    } elseif (!$isLoggedIn) {
        $saveError = 'You must be logged in to save a build.';
    } elseif (!$isComplete) {
        $saveError = 'Cannot save an incomplete build. Please select all required components.';
    } elseif ($compatStatus === 'incompatible') {
        $saveError = 'Cannot save an incompatible build. Please resolve all compatibility errors first.';
    } else {
        $buildName = isset($_POST['build_name']) && is_string($_POST['build_name'])
            ? trim($_POST['build_name'])
            : '';

        if ($buildName === '') {
            $buildName = 'My Build';
        }

        if (mb_strlen($buildName) > 200) {
            $buildName = mb_substr($buildName, 0, 200);
        }

        $notes = isset($_POST['build_notes']) && is_string($_POST['build_notes'])
            ? trim($_POST['build_notes'])
            : null;

        if ($notes !== null && $notes === '') {
            $notes = null;
        }

        try {
            $pdo->beginTransaction();

            $insertBuild = $pdo->prepare(
                'INSERT INTO saved_builds (user_id, name, total_price, compatibility_status, notes)
                 VALUES (:uid, :name, :total, :compat, :notes)'
            );
            $insertBuild->execute([
                ':uid' => customcore_current_user_id(),
                ':name' => $buildName,
                ':total' => round($totalPrice, 2),
                ':compat' => $compatStatus,
                ':notes' => $notes,
            ]);
            $savedBuildId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare(
                'INSERT INTO saved_build_items (saved_build_id, component_id, unit_price)
                 VALUES (:bid, :cid, :price)'
            );

            foreach ($selectedByCategory as $catId => $part) {
                $insertItem->execute([
                    ':bid' => $savedBuildId,
                    ':cid' => (int) $part['id'],
                    ':price' => round((float) $part['price'], 2),
                ]);
            }

            $pdo->commit();

            // Clear session build after successful save.
            unset($_SESSION['_cc_build']);

            customcore_flash_success(
                'Build "' . htmlspecialchars($buildName, ENT_QUOTES, 'UTF-8')
                . '" saved successfully!'
            );
            customcore_redirect('builder-results.php?saved=' . $savedBuildId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $saveError = customcore_is_debug()
                ? $exception->getMessage()
                : 'We could not save your build right now. Please try again.';
        }
    }
}

// Determine if we are viewing a just-saved build

$viewingSavedBuild = false;

if (
    isset($_GET['saved'])
    && is_string($_GET['saved'])
    && ctype_digit($_GET['saved'])
    && (int) $_GET['saved'] > 0
) {
    $viewingSavedBuild = true;
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Build summary: parts list with trusted prices, compatibility, estimates, and save -->
<section class="content-section results-page" aria-labelledby="results-heading">
    <header class="results-page__header">
        <h1 id="results-heading">Your build summary</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html#summary')); ?>">Build summary guide</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html#saved-builds')); ?>">How to save</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html')); ?>">Full PC Builder guide</a>
        </p>
        <p class="results-page__intro">
            Review every selected part, the trusted total from our database, compatibility
            results, and power / performance estimates. You can go back to edit any step.
        </p>
    </header>

    <!-- Notices: parts removed as unavailable, and missing required components -->
    <?php if ($staleSelections !== []): ?>
        <div class="flash flash--warning" role="status">
            One or more previously selected parts are no longer available and were removed from your build.
        </div>
    <?php endif; ?>

    <?php if (!$isComplete): ?>
        <div class="flash flash--warning" role="status">
            This build is incomplete. Still needed:
            <?php echo customcore_e(implode(', ', $missingRequired)); ?>.
            <a href="<?php echo customcore_e(customcore_url('builder.php')); ?>">Continue building</a>
        </div>
    <?php endif; ?>

    <!-- Split layout: selected parts table on the left, summary sidebar on the right -->
    <div class="results-layout">
        <!-- Parts list -->
        <div class="results-layout__parts">
            <h2 class="results-section-title">Selected components</h2>

            <!-- One row per category with trusted DB prices and an edit link; total in the footer -->
            <table class="results-table">
                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        <th scope="col">Component</th>
                        <th scope="col">Details</th>
                        <th scope="col" class="results-table__price">Price</th>
                        <th scope="col"><span class="visually-hidden">Edit</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $idx => $cat): ?>
                        <?php
                        $catId = (int) $cat['id'];
                        $stepNum = $idx + 1;
                        $part = $selectedByCategory[$catId] ?? null;
                        $isRequired = (int) $cat['is_required'] === 1;
                        ?>
                        <tr class="<?php echo $part === null ? 'results-table__row--empty' : ''; ?>">
                            <th scope="row"><?php echo customcore_e((string) $cat['name']); ?></th>
                            <td>
                                <?php if ($part !== null): ?>
                                    <span class="results-table__name"><?php echo customcore_e((string) $part['name']); ?></span>
                                    <?php if ((string) $part['brand'] !== ''): ?>
                                        <span class="results-table__brand"><?php echo customcore_e((string) $part['brand']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="results-table__empty">
                                        <?php echo $isRequired ? 'Not selected' : 'Skipped'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="results-table__attrs">
                                <?php if ($part !== null): ?>
                                    <?php
                                    $attrs = [];
                                    if (!empty($part['socket'])) {
                                        $attrs[] = 'Socket ' . $part['socket'];
                                    }
                                    if (!empty($part['ram_type'])) {
                                        $attrs[] = $part['ram_type'];
                                    }
                                    if (!empty($part['form_factor'])) {
                                        $attrs[] = $part['form_factor'];
                                    }
                                    if (!empty($part['psu_wattage']) && (int) $part['psu_wattage'] > 0) {
                                        $attrs[] = (int) $part['psu_wattage'] . 'W';
                                    }
                                    if (
                                        !empty($part['wattage_estimate'])
                                        && (int) $part['wattage_estimate'] > 0
                                        && empty($part['psu_wattage'])
                                    ) {
                                        $attrs[] = 'TDP ' . (int) $part['wattage_estimate'] . 'W';
                                    }
                                    if (!empty($part['storage_interface'])) {
                                        $attrs[] = $part['storage_interface'];
                                    }
                                    if (!empty($part['cooler_type'])) {
                                        $attrs[] = $part['cooler_type'];
                                    }
                                    if (!empty($part['gpu_length_mm']) && (int) $part['gpu_length_mm'] > 0) {
                                        $attrs[] = (int) $part['gpu_length_mm'] . 'mm';
                                    }
                                    echo customcore_e(implode(' · ', $attrs));
                                    ?>
                                <?php else: ?>
                                    
                                <?php endif; ?>
                            </td>
                            <td class="results-table__price">
                                <?php if ($part !== null): ?>
                                    <?php
                                    $price = (float) $part['price'];
                                    echo $price > 0
                                        ? '$' . customcore_e(number_format($price, 2))
                                        : 'Included';
                                    ?>
                                <?php else: ?>
                                    
                                <?php endif; ?>
                            </td>
                            <td>
                                <a
                                    class="results-table__edit"
                                    href="<?php echo customcore_e(customcore_url('builder.php?step=' . $stepNum)); ?>"
                                >Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="3">Trusted total</th>
                        <td class="results-table__price results-table__total" colspan="2">
                            $<?php echo customcore_e(number_format($totalPrice, 2)); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <p class="results-table__note">
                Prices are loaded from the database for your selected component IDs, client-side figures are not trusted.
            </p>
        </div>

        <!-- Sidebar: compatibility + estimates + actions -->
        <aside class="results-layout__aside" aria-label="Compatibility and estimates">
            <!-- Compatibility panel: overall badge plus per-rule pass/warning/fail results -->
            <div class="results-panel">
                <h2 class="results-section-title">Compatibility</h2>
                <p class="results-panel__status">
                    <span class="compat-badge <?php echo customcore_e($compatBadgeClass); ?>">
                        <?php echo customcore_e($compatLabel); ?>
                    </span>
                </p>

                <?php if ($compatResults !== []): ?>
                    <ul class="compat-results results-compat-list">
                        <?php foreach ($compatResults as $rule): ?>
                            <?php
                            $ruleStatus = (string) ($rule['status'] ?? 'skip');
                            if ($ruleStatus === 'skip') {
                                continue;
                            }
                            $itemClass = 'compat-results__item';
                            if ($ruleStatus === 'pass') {
                                $itemClass .= ' compat-results__item--pass';
                            } elseif ($ruleStatus === 'warning') {
                                $itemClass .= ' compat-results__item--warning';
                            } elseif ($ruleStatus === 'fail') {
                                $itemClass .= ' compat-results__item--fail';
                            }
                            ?>
                            <li class="<?php echo customcore_e($itemClass); ?>">
                                <strong><?php echo customcore_e((string) $rule['name']); ?>:</strong>
                                <?php echo customcore_e((string) $rule['message']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="results-panel__hint">Select more components to run compatibility checks.</p>
                <?php endif; ?>
            </div>

            <!-- Estimates panel: power draw, recommended PSU, and performance scores/chart -->
            <div class="results-panel">
                <h2 class="results-section-title">Estimates</h2>
                <dl class="results-estimates">
                    <div class="results-estimates__row">
                        <dt>Estimated power draw</dt>
                        <dd><?php echo $estimatedDraw > 0 ? customcore_e((string) $estimatedDraw) . ' W' : 'Not available'; ?></dd>
                    </div>
                    <div class="results-estimates__row">
                        <dt>PSU capacity</dt>
                        <dd><?php echo $psuWattage > 0 ? customcore_e((string) $psuWattage) . ' W' : 'Not available'; ?></dd>
                    </div>
                    <div class="results-estimates__row">
                        <dt>Recommended PSU (20% headroom)</dt>
                        <dd><?php echo $recommendedPsu > 0 ? customcore_e((string) $recommendedPsu) . ' W+' : 'Not available'; ?></dd>
                    </div>
                    <div class="results-estimates__row">
                        <dt>Gaming performance estimate</dt>
                        <dd>
                            <?php if ($avgGaming > 0): ?>
                                <span class="results-estimates__score" aria-label="<?php echo $avgGaming; ?> out of 100">
                                    <span class="results-estimates__bar" style="--score: <?php echo $avgGaming; ?>"></span>
                                    <?php echo $avgGaming; ?> / 100
                                </span>
                            <?php else: ?>
                                
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="results-estimates__row">
                        <dt>Productivity performance estimate</dt>
                        <dd>
                            <?php if ($avgProductivity > 0): ?>
                                <span class="results-estimates__score" aria-label="<?php echo $avgProductivity; ?> out of 100">
                                    <span class="results-estimates__bar results-estimates__bar--prod" style="--score: <?php echo $avgProductivity; ?>"></span>
                                    <?php echo $avgProductivity; ?> / 100
                                </span>
                            <?php else: ?>
                                
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="results-estimates__row">
                        <dt>Upgrade headroom</dt>
                        <dd>
                            <?php
                            $headroom = (int) ($perfReport['upgrade_headroom'] ?? 0);
                            echo $avgGaming > 0 || $avgProductivity > 0
                                ? customcore_e((string) $headroom) . ' pts'
                                : 'Not available';
                            ?>
                        </dd>
                    </div>
                </dl>

                <?php
                $perfChartApi = customcore_url('api/chart-data.php');
                $perfChartIds = array_values($build);
                $perfChartForm = '';
                $perfChartTitle = 'Gaming, productivity & upgrade chart';
                require __DIR__ . '/includes/perf-chart.php';
                ?>

                <p class="results-panel__hint">
                    Performance scores are weighted from CPU, GPU, RAM, and storage (server-side).
                    The chart compares this build to the best active catalogue parts.
                </p>
            </div>

            <!-- Next steps: save form for logged-in customers, or a log-in prompt for guests -->
            <div class="results-panel results-panel--actions">
                <h2 class="results-section-title">Next steps</h2>

                <?php if ($saveError !== null): ?>
                    <div class="flash flash--error" role="alert">
                        <?php echo customcore_e($saveError); ?>
                    </div>
                <?php endif; ?>

                <div class="results-actions">
                    <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">
                        Edit build
                    </a>

                    <?php if ($isLoggedIn): ?>
                        <?php if ($isComplete && $compatStatus !== 'incompatible'): ?>
                            <?php if ($viewingSavedBuild): ?>
                                <p class="results-panel__hint results-panel__hint--ready">
                                    This build has been saved to your account.
                                    <a href="<?php echo customcore_e(customcore_url('saved-builds.php')); ?>">View saved builds</a>
                                </p>
                            <?php else: ?>
                                <form class="results-save-form" method="post" action="<?php echo customcore_e(customcore_url('builder-results.php')); ?>">
                                    <?php echo customcore_csrf_field(); ?>
                                    <div class="results-save-form__field">
                                        <label for="build-name" class="results-save-form__label">Build name</label>
                                        <input
                                            type="text"
                                            id="build-name"
                                            name="build_name"
                                            class="form-input"
                                            maxlength="200"
                                            placeholder="My Build"
                                            value=""
                                        >
                                    </div>
                                    <div class="results-save-form__field">
                                        <label for="build-notes" class="results-save-form__label">Notes <span class="results-save-form__optional">(optional)</span></label>
                                        <textarea
                                            id="build-notes"
                                            name="build_notes"
                                            class="form-input form-textarea"
                                            rows="2"
                                            maxlength="2000"
                                            placeholder="e.g. Budget gaming rig for 1080p"
                                        ></textarea>
                                    </div>
                                    <button type="submit" class="button">
                                        Save build to my account
                                    </button>
                                </form>
                                <p class="results-panel__hint results-panel__hint--ready">
                                    Signed in as <?php echo customcore_e(customcore_current_user_name()); ?>.
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="results-panel__hint">
                                <?php if (!$isComplete): ?>
                                    Finish all required parts before saving.
                                <?php else: ?>
                                    Resolve compatibility errors before saving.
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <a
                            class="button"
                            href="<?php echo customcore_e(customcore_url('builder-results.php?intent=login')); ?>"
                        >
                            Log in to save
                        </a>
                        <p class="results-panel__hint">
                            Guests can review builds freely. Create an account or log in to save this configuration.
                        </p>
                    <?php endif; ?>

                    <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('catalogue.php')); ?>">
                        Browse catalogue
                    </a>
                    <a class="button button--secondary button--sm" href="<?php echo customcore_e(customcore_url('builder.php?reset=1')); ?>">
                        Start over
                    </a>
                </div>
            </div>
        </aside>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
