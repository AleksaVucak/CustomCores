<?php
/**
 * CustomCore — Multi-step Custom PC Builder (Commit 5.1).
 *
 * File responsibility:
 *   Renders a step-by-step component selection interface. Each builder category
 *   (CPU, Motherboard, GPU, RAM, Storage, PSU, Case, Cooling, OS, Service) is
 *   one step. Users pick one component per required category (optional
 *   categories may be skipped). Selections are stored in the session so the
 *   build persists across steps without needing login.
 *
 * Flow:
 *   GET  — show the current step (defaults to step 1); honour ?step=N.
 *   POST — record a selection for the current step; advance to the next step or
 *          to the review page (builder-results.php) after the final step.
 *
 * Authentication requirements:
 *   None (public). Saving a build (Commit 5.6) requires login; this page is
 *   available to guests for browsing and building.
 *
 * Database queries:
 *   - component_categories (all rows, sorted by sort_order)
 *   - components (active rows for the current category)
 *
 * Session:
 *   $_SESSION['_cc_build'] — array keyed by category ID → component ID.
 *
 * Live pricing (Commit 5.2):
 *   Each radio carries data-price / data-name. assets/js/builder.js recalculates
 *   the this-step subtotal and running total immediately on selection change.
 *   Server-trusted totals arrive in Commit 5.3.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/csrf.php';

customcore_session_start();

// ---------------------------------------------------------------------------
// Handle reset — clear build and restart
// ---------------------------------------------------------------------------

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    unset($_SESSION['_cc_build']);
    customcore_redirect('builder.php');
}

// ---------------------------------------------------------------------------
// Load builder categories from database (ordered by sort_order)
// ---------------------------------------------------------------------------

try {
    $pdo = customcore_pdo();

    $catStmt = $pdo->query(
        'SELECT id, name, slug, sort_order, is_required
         FROM component_categories
         ORDER BY sort_order ASC'
    );
    $categories = $catStmt->fetchAll();
} catch (Throwable $exception) {
    $categories = [];
    $dbError = customcore_is_debug()
        ? $exception->getMessage()
        : 'We could not load the builder right now. Please try again later.';
}

if (empty($categories)) {
    $pageTitle = 'PC Builder — CustomCore';
    $pageDescription = 'Build your dream gaming PC with guided component selection.';
    $pageKeywords = 'CustomCore, PC builder, custom gaming PC, build your own';
    $currentPage = 'builder';
    require_once __DIR__ . '/includes/header.php';
    echo '<section class="content-section" aria-labelledby="builder-heading">';
    echo '<h1 id="builder-heading">Custom PC Builder</h1>';
    echo '<p class="flash flash--error">' . customcore_e($dbError ?? 'No builder categories available.') . '</p>';
    echo '</section>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$totalSteps = count($categories);

// ---------------------------------------------------------------------------
// Determine the current step (1-indexed)
// ---------------------------------------------------------------------------

$currentStep = 1;
if (isset($_GET['step']) && is_string($_GET['step'])) {
    $currentStep = max(1, min($totalSteps, (int) $_GET['step']));
}

// Current category based on step
$category = $categories[$currentStep - 1];
$categoryId = (int) $category['id'];
$categoryName = (string) $category['name'];
$categorySlug = (string) $category['slug'];
$isRequired = (int) $category['is_required'] === 1;

// ---------------------------------------------------------------------------
// Initialise / retrieve build session
// ---------------------------------------------------------------------------

if (!isset($_SESSION['_cc_build']) || !is_array($_SESSION['_cc_build'])) {
    $_SESSION['_cc_build'] = [];
}

$build = &$_SESSION['_cc_build'];

// ---------------------------------------------------------------------------
// Handle POST — store the selection and advance
// ---------------------------------------------------------------------------

$selectionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = customcore_csrf_verify(
        isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null
    );

    if (!$csrfOk) {
        $selectionError = 'Your session expired. Please try again.';
    } else {
        $postedCategory = isset($_POST['category_id']) && is_string($_POST['category_id'])
            ? (int) $_POST['category_id']
            : 0;

        $postedComponent = isset($_POST['component_id']) && is_string($_POST['component_id'])
            ? (int) $_POST['component_id']
            : 0;

        $skipOptional = isset($_POST['skip_optional']) && $_POST['skip_optional'] === '1';

        if ($postedCategory !== $categoryId) {
            $selectionError = 'Invalid step. Please try again.';
        } elseif ($skipOptional && !$isRequired) {
            // Allow skipping optional categories
            unset($build[$categoryId]);

            // Advance to next step or review
            if ($currentStep >= $totalSteps) {
                customcore_redirect('builder-results.php');
            }
            customcore_redirect('builder.php?step=' . ($currentStep + 1));
        } elseif ($postedComponent <= 0 && $isRequired) {
            $selectionError = 'Please select a component for this step.';
        } elseif ($postedComponent <= 0 && !$isRequired) {
            // Treat as skip for optional
            unset($build[$categoryId]);
            if ($currentStep >= $totalSteps) {
                customcore_redirect('builder-results.php');
            }
            customcore_redirect('builder.php?step=' . ($currentStep + 1));
        } else {
            // Validate that the component belongs to this category and is active
            try {
                $valStmt = $pdo->prepare(
                    'SELECT id FROM components
                     WHERE id = :cid AND component_category_id = :cat AND is_active = 1
                     LIMIT 1'
                );
                $valStmt->execute([':cid' => $postedComponent, ':cat' => $categoryId]);
                $valid = $valStmt->fetch();

                if ($valid === false) {
                    $selectionError = 'Invalid component selection.';
                } else {
                    $build[$categoryId] = $postedComponent;

                    if ($currentStep >= $totalSteps) {
                        customcore_redirect('builder-results.php');
                    }
                    customcore_redirect('builder.php?step=' . ($currentStep + 1));
                }
            } catch (Throwable $exception) {
                $selectionError = customcore_is_debug()
                    ? $exception->getMessage()
                    : 'Could not validate your selection. Please try again.';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Load components for the current category
// ---------------------------------------------------------------------------

$components = [];

try {
    $compStmt = $pdo->prepare(
        'SELECT id, name, brand, price, wattage_estimate, socket, ram_type,
                form_factor, gpu_length_mm, max_gpu_length_mm, cooler_height_mm,
                max_cooler_height_mm, cooler_type, storage_interface,
                supported_storage, psu_wattage, performance_gaming,
                performance_productivity
         FROM components
         WHERE component_category_id = :cat AND is_active = 1
         ORDER BY price ASC'
    );
    $compStmt->execute([':cat' => $categoryId]);
    $components = $compStmt->fetchAll();
} catch (Throwable $exception) {
    $selectionError = customcore_is_debug()
        ? $exception->getMessage()
        : 'Could not load components for this step.';
}

// The currently selected component for this step (if user came back)
$selectedId = isset($build[$categoryId]) ? (int) $build[$categoryId] : 0;

// ---------------------------------------------------------------------------
// Load selected component details once (summary + live-price baseline)
// ---------------------------------------------------------------------------

/** @var array<int, array{id:int,name:string,price:float,category_id:int}> $selectedDetails */
$selectedDetails = [];
$runningTotal = 0.0;
$otherTotal = 0.0; // Sum of selections excluding the current step (for live JS)
$currentStepPrice = 0.0;
$currentStepName = '';

if (!empty($build)) {
    try {
        $ids = array_values(array_map('intval', $build));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $detailStmt = $pdo->prepare(
            "SELECT id, name, price, component_category_id
             FROM components
             WHERE id IN ($placeholders)"
        );
        $detailStmt->execute($ids);
        $rows = $detailStmt->fetchAll();

        foreach ($rows as $row) {
            $rowCatId = (int) $row['component_category_id'];
            $rowPrice = (float) $row['price'];
            $selectedDetails[$rowCatId] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'price' => $rowPrice,
                'category_id' => $rowCatId,
            ];
            $runningTotal += $rowPrice;

            if ($rowCatId === $categoryId) {
                $currentStepPrice = $rowPrice;
                $currentStepName = (string) $row['name'];
            } else {
                $otherTotal += $rowPrice;
            }
        }
    } catch (Throwable $exception) {
        $selectedDetails = [];
        $runningTotal = 0.0;
        $otherTotal = 0.0;
    }
}

// ---------------------------------------------------------------------------
// Page setup and render
// ---------------------------------------------------------------------------

$pageTitle = "Step {$currentStep}: {$categoryName} — PC Builder — CustomCore";
$pageDescription = "Select a {$categoryName} for your custom PC build.";
$pageKeywords = "CustomCore, PC builder, {$categoryName}, custom gaming PC";
$currentPage = 'builder';

require_once __DIR__ . '/includes/header.php';
?>

<section class="content-section builder-page" aria-labelledby="builder-heading">
    <header class="builder-page__header">
        <h1 id="builder-heading">Custom PC Builder</h1>
        <p class="context-help">
            Help:
            <a href="<?php echo customcore_e(customcore_url('help/pc-builder.html')); ?>">PC Builder guide</a>
        </p>
    </header>

    <!-- Step indicators -->
    <nav class="builder-steps" aria-label="Builder progress">
        <ol class="builder-steps__list">
            <?php foreach ($categories as $idx => $cat): ?>
                <?php
                $stepNum = $idx + 1;
                $isCurrent = $stepNum === $currentStep;
                $isCompleted = isset($build[(int) $cat['id']]);
                $stepClass = 'builder-steps__item';
                if ($isCurrent) {
                    $stepClass .= ' builder-steps__item--current';
                } elseif ($isCompleted) {
                    $stepClass .= ' builder-steps__item--done';
                }
                ?>
                <li class="<?php echo customcore_e($stepClass); ?>">
                    <a
                        class="builder-steps__link"
                        href="<?php echo customcore_e(customcore_url('builder.php?step=' . $stepNum)); ?>"
                        <?php echo $isCurrent ? 'aria-current="step"' : ''; ?>
                    >
                        <span class="builder-steps__num"><?php echo $stepNum; ?></span>
                        <span class="builder-steps__label"><?php echo customcore_e($cat['name']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <div class="builder-layout">
        <!-- Left: Component picker -->
        <div class="builder-layout__picker">
            <h2 class="builder-section-title">
                Step <?php echo $currentStep; ?>: Select
                <?php echo customcore_e($isRequired ? '' : '(optional) '); ?>
                <?php echo customcore_e($categoryName); ?>
            </h2>

            <?php if ($selectionError !== null): ?>
                <div class="flash flash--error" role="alert">
                    <?php echo customcore_e($selectionError); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($components)): ?>
                <p>No components available for this category.</p>
            <?php else: ?>
                <form
                    class="builder-form"
                    id="builder-form"
                    method="post"
                    action="<?php echo customcore_e(customcore_url('builder.php?step=' . $currentStep)); ?>"
                    data-builder-live="1"
                    data-other-total="<?php echo customcore_e(number_format($otherTotal, 2, '.', '')); ?>"
                    data-category-id="<?php echo $categoryId; ?>"
                >
                    <?php echo customcore_csrf_field(); ?>
                    <input type="hidden" name="category_id" value="<?php echo $categoryId; ?>">

                    <div class="builder-options" role="radiogroup" aria-label="<?php echo customcore_e($categoryName); ?> options">
                        <?php foreach ($components as $comp): ?>
                            <?php
                            $compId = (int) $comp['id'];
                            $compPrice = (float) $comp['price'];
                            $compName = (string) $comp['name'];
                            $isSelected = $compId === $selectedId;
                            $cardClass = 'builder-option';
                            if ($isSelected) {
                                $cardClass .= ' builder-option--selected';
                            }
                            ?>
                            <label class="<?php echo customcore_e($cardClass); ?>" for="comp-<?php echo $compId; ?>">
                                <input
                                    type="radio"
                                    class="builder-option__radio"
                                    id="comp-<?php echo $compId; ?>"
                                    name="component_id"
                                    value="<?php echo $compId; ?>"
                                    data-price="<?php echo customcore_e(number_format($compPrice, 2, '.', '')); ?>"
                                    data-name="<?php echo customcore_e($compName); ?>"
                                    <?php echo $isSelected ? 'checked' : ''; ?>
                                >
                                <span class="builder-option__content">
                                    <span class="builder-option__name">
                                        <?php echo customcore_e($compName); ?>
                                    </span>
                                    <span class="builder-option__meta">
                                        <?php if ($comp['brand'] !== ''): ?>
                                            <span class="builder-option__brand"><?php echo customcore_e($comp['brand']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($comp['socket'] !== null && $comp['socket'] !== ''): ?>
                                            <span class="builder-option__attr">Socket: <?php echo customcore_e($comp['socket']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($comp['ram_type'] !== null && $comp['ram_type'] !== ''): ?>
                                            <span class="builder-option__attr">RAM: <?php echo customcore_e($comp['ram_type']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($comp['form_factor'] !== null && $comp['form_factor'] !== ''): ?>
                                            <span class="builder-option__attr">Form: <?php echo customcore_e($comp['form_factor']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($comp['psu_wattage'] !== null && (int) $comp['psu_wattage'] > 0): ?>
                                            <span class="builder-option__attr"><?php echo customcore_e($comp['psu_wattage']); ?>W</span>
                                        <?php endif; ?>
                                        <?php if ($comp['wattage_estimate'] !== null && (int) $comp['wattage_estimate'] > 0 && $comp['psu_wattage'] === null): ?>
                                            <span class="builder-option__attr">TDP: <?php echo customcore_e($comp['wattage_estimate']); ?>W</span>
                                        <?php endif; ?>
                                        <?php if ($comp['storage_interface'] !== null && $comp['storage_interface'] !== ''): ?>
                                            <span class="builder-option__attr"><?php echo customcore_e($comp['storage_interface']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($comp['cooler_type'] !== null && $comp['cooler_type'] !== ''): ?>
                                            <span class="builder-option__attr">Type: <?php echo customcore_e($comp['cooler_type']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($comp['gpu_length_mm'] !== null && (int) $comp['gpu_length_mm'] > 0): ?>
                                            <span class="builder-option__attr"><?php echo customcore_e($comp['gpu_length_mm']); ?>mm</span>
                                        <?php endif; ?>
                                        <?php if ($comp['max_gpu_length_mm'] !== null && (int) $comp['max_gpu_length_mm'] > 0): ?>
                                            <span class="builder-option__attr">GPU max: <?php echo customcore_e($comp['max_gpu_length_mm']); ?>mm</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="builder-option__price">
                                        <?php echo $compPrice > 0 ? '$' . customcore_e(number_format($compPrice, 2)) : 'Included'; ?>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="builder-form__actions">
                        <?php if ($currentStep > 1): ?>
                            <a
                                class="button button--secondary"
                                href="<?php echo customcore_e(customcore_url('builder.php?step=' . ($currentStep - 1))); ?>"
                            >Back</a>
                        <?php endif; ?>

                        <?php if (!$isRequired): ?>
                            <button type="submit" name="skip_optional" value="1" class="button button--secondary">
                                Skip this step
                            </button>
                        <?php endif; ?>

                        <button type="submit" class="button">
                            <?php echo $currentStep >= $totalSteps ? 'Review build' : 'Next step'; ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Right: Live summary panel -->
        <aside class="builder-layout__summary" aria-label="Build summary" id="builder-summary">
            <h2 class="builder-section-title">Build summary</h2>

            <dl class="builder-summary">
                <?php foreach ($categories as $cat): ?>
                    <?php
                    $catIdLoop = (int) $cat['id'];
                    $isActiveRow = $catIdLoop === $categoryId;
                    $detail = $selectedDetails[$catIdLoop] ?? null;
                    $hasSelection = $detail !== null;
                    ?>
                    <div
                        class="builder-summary__row<?php echo $isActiveRow ? ' builder-summary__row--active' : ''; ?>"
                        <?php if ($isActiveRow): ?>
                            data-live-category-row="1"
                        <?php endif; ?>
                    >
                        <dt class="builder-summary__label"><?php echo customcore_e($cat['name']); ?></dt>
                        <dd class="builder-summary__value">
                            <?php if ($hasSelection): ?>
                                <span class="builder-summary__part"<?php echo $isActiveRow ? ' data-live-part' : ''; ?>>
                                    <?php echo customcore_e($detail['name']); ?>
                                </span>
                                <span class="builder-summary__price"<?php echo $isActiveRow ? ' data-live-price' : ''; ?>>
                                    <?php echo $detail['price'] > 0 ? '$' . customcore_e(number_format($detail['price'], 2)) : 'Included'; ?>
                                </span>
                            <?php else: ?>
                                <?php if ($isActiveRow): ?>
                                    <span class="builder-summary__part" data-live-part hidden></span>
                                    <span class="builder-summary__price" data-live-price hidden></span>
                                    <span class="builder-summary__empty" data-live-empty>
                                        <?php echo (int) $cat['is_required'] === 0 ? 'Skipped' : '—'; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="builder-summary__empty">
                                        <?php echo (int) $cat['is_required'] === 0 ? 'Skipped' : '—'; ?>
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <div class="builder-summary__subtotal" id="builder-live-subtotal-wrap">
                <strong>This step:</strong>
                <span
                    class="builder-summary__subtotal-value"
                    id="builder-live-subtotal"
                    aria-live="polite"
                ><?php
                    if ($selectedId > 0) {
                        echo $currentStepPrice > 0
                            ? '$' . customcore_e(number_format($currentStepPrice, 2))
                            : 'Included';
                    } else {
                        echo '—';
                    }
                ?></span>
            </div>

            <div class="builder-summary__total">
                <strong>Running total:</strong>
                <span
                    class="builder-summary__total-value"
                    id="builder-live-total"
                    aria-live="polite"
                >$<?php echo customcore_e(number_format($runningTotal, 2)); ?></span>
            </div>

            <?php if (!empty($build)): ?>
                <div class="builder-summary__actions">
                    <a
                        class="button button--secondary button--sm"
                        href="<?php echo customcore_e(customcore_url('builder.php?reset=1')); ?>"
                    >Start over</a>
                </div>
            <?php endif; ?>

            <p class="builder-summary__hint" id="builder-price-hint">
                Prices update instantly as you change selections.
            </p>
        </aside>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
