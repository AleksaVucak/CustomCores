<?php
/**
 * CustomCore — PC Build Summary (Commit 5.5).
 *
 * File responsibility:
 *   Renders the completed (or in-progress) build from the session: every
 *   selected component with trusted database prices, a server-side
 *   compatibility report, and power / performance estimates. Guests and
 *   logged-in customers can review the build; saving arrives in Commit 5.6.
 *
 * Flow:
 *   GET — load $_SESSION['_cc_build'], look up components, evaluate rules,
 *         display summary. Empty / incomplete builds redirect or warn.
 *
 * Authentication requirements:
 *   None (public). Save CTA prompts login when the user is a guest.
 *
 * Database queries:
 *   - component_categories (ordered)
 *   - components (selected IDs, active only)
 *   - compatibility_rules (via includes/compatibility.php)
 *
 * Session:
 *   $_SESSION['_cc_build'] — category ID → component ID (from builder.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/compatibility.php';

customcore_session_start();

$pageTitle = 'Build summary — CustomCore';
$pageDescription = 'Review your custom PC build: components, trusted prices, compatibility, and estimates.';
$pageKeywords = 'CustomCore, PC builder, build summary, compatibility, custom gaming PC';
$currentPage = 'builder';

// ---------------------------------------------------------------------------
// Guest "log in to save" — stash a safe return path, then send to login
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Read build from session
// ---------------------------------------------------------------------------

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
    customcore_flash_warning('Your build is empty. Select components to see a summary.');
    customcore_redirect('builder.php');
}

// ---------------------------------------------------------------------------
// Load categories and selected components (trusted DB prices)
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Compatibility + estimates
// ---------------------------------------------------------------------------

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

$avgGaming = $gamingScores !== []
    ? (int) round(array_sum($gamingScores) / count($gamingScores))
    : 0;
$avgProductivity = $productivityScores !== []
    ? (int) round(array_sum($productivityScores) / count($productivityScores))
    : 0;
$recommendedPsu = $estimatedDraw > 0 ? (int) ceil($estimatedDraw * 1.2) : 0;

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

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section results-page" aria-labelledby="results-heading">
    <header class="results-page__header">
        <h1 id="results-heading">Your build summary</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html')); ?>">PC Builder guide</a>
        </p>
        <p class="results-page__intro">
            Review every selected part, the trusted total from our database, compatibility
            results, and power / performance estimates. You can go back to edit any step.
        </p>
    </header>

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

    <div class="results-layout">
        <!-- Parts list -->
        <div class="results-layout__parts">
            <h2 class="results-section-title">Selected components</h2>

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
                                    —
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
                                    —
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
                Prices are loaded from the database for your selected component IDs — client-side figures are not trusted.
            </p>
        </div>

        <!-- Sidebar: compatibility + estimates + actions -->
        <aside class="results-layout__aside" aria-label="Compatibility and estimates">
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

            <div class="results-panel">
                <h2 class="results-section-title">Estimates</h2>
                <dl class="results-estimates">
                    <div class="results-estimates__row">
                        <dt>Estimated power draw</dt>
                        <dd><?php echo $estimatedDraw > 0 ? customcore_e((string) $estimatedDraw) . ' W' : '—'; ?></dd>
                    </div>
                    <div class="results-estimates__row">
                        <dt>PSU capacity</dt>
                        <dd><?php echo $psuWattage > 0 ? customcore_e((string) $psuWattage) . ' W' : '—'; ?></dd>
                    </div>
                    <div class="results-estimates__row">
                        <dt>Recommended PSU (20% headroom)</dt>
                        <dd><?php echo $recommendedPsu > 0 ? customcore_e((string) $recommendedPsu) . ' W+' : '—'; ?></dd>
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
                                —
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
                                —
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
                <p class="results-panel__hint">
                    Performance figures are simple averages of scored parts. A full comparison chart arrives in a later builder update.
                </p>
            </div>

            <div class="results-panel results-panel--actions">
                <h2 class="results-section-title">Next steps</h2>
                <div class="results-actions">
                    <a class="button button--secondary" href="<?php echo customcore_e(customcore_url('builder.php')); ?>">
                        Edit build
                    </a>

                    <?php if ($isLoggedIn): ?>
                        <?php if ($isComplete && $compatStatus !== 'incompatible'): ?>
                            <p class="results-panel__hint results-panel__hint--ready">
                                You are signed in as <?php echo customcore_e(customcore_current_user_name()); ?>.
                                Saving this build to your account is the next builder step — your selections stay in this session until then.
                            </p>
                        <?php else: ?>
                            <p class="results-panel__hint">
                                Finish all required parts and resolve incompatible issues before you can save this build.
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
                            Guests can review builds freely. Create an account or log in to save this configuration later.
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
