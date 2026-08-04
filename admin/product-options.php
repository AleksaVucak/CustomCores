<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator product options management.
// Protected screen for managing a single product's configurable options (RAM, Storage, Colour,
// Warranty, …). Lets an administrator add, edit, reorder, price (positive or negative delta),
// enable/disable, set the default choice, and delete options. Keeps exactly one active default per
// group so the storefront and PC Builder always price a valid configuration.
// Access: Administrator role (customcore_require_admin()).

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/admin-products.php';
require_once __DIR__ . '/../includes/admin-options.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

$productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
if ($productId < 1 && isset($_POST['product_id'])) {
    $productId = (int) $_POST['product_id'];
}

$product = $productId > 0 ? customcore_admin_product_fetch($pdo, $productId) : null;

$adminNavCurrent = 'options';
$loadAdminCss = true;
$currentPage = 'admin';

/** Redirect target that keeps the current product in view. */
$selfUrl = 'admin/product-options.php?product_id=' . $productId;

// Default (empty) form state; may be overwritten by an edit request or a
// failed create/update submission below.
$formValues = [
    'option_group' => '',
    'option_label' => '',
    'price_delta' => '0.00',
    'is_default' => 0,
    'is_active' => 1,
    'sort_order' => '',
];
$formErrors = [];
$editId = 0;

// POST actions (all require a valid product and CSRF token).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product !== null) {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if (!customcore_csrf_verify($token)) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect($selfUrl);
    }

    // Actions operating on an existing option resolve and ownership-check it.
    $optionId = isset($_POST['option_id']) ? (int) $_POST['option_id'] : 0;
    $targetOption = null;
    if (in_array($action, ['update', 'delete', 'toggle', 'set_default'], true)) {
        $targetOption = customcore_admin_option_fetch($pdo, $optionId);
        if ($targetOption === null || (int) $targetOption['product_id'] !== $productId) {
            customcore_flash_error('That option could not be found for this product.');
            customcore_redirect($selfUrl);
        }
    }

    if ($action === 'create' || $action === 'update') {
        $validation = customcore_admin_option_validate($_POST);
        $formErrors = $validation['errors'];
        $formValues = $validation['values'];

        if ($action === 'update') {
            $editId = $optionId;
        }

        if ($formErrors === []) {
            try {
                $pdo->beginTransaction();
                if ($action === 'create') {
                    customcore_admin_option_create($pdo, $productId, $formValues);
                    $pdo->commit();
                    customcore_flash_success('Option “' . $formValues['option_label'] . '” was added.');
                } else {
                    $previousGroup = (string) $targetOption['option_group'];
                    customcore_admin_option_update($pdo, $optionId, $productId, $formValues, $previousGroup);
                    $pdo->commit();
                    customcore_flash_success('Option “' . $formValues['option_label'] . '” was updated.');
                }
                customcore_redirect($selfUrl);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $formErrors['form'] = customcore_is_debug()
                    ? $exception->getMessage()
                    : 'The option could not be saved. Please try again.';
            }
        }
        // Fall through: re-render with $formErrors / $formValues populated.
    } else {
        // delete / toggle / set_default, each is a single confirmed action.
        try {
            $pdo->beginTransaction();
            $group = (string) $targetOption['option_group'];

            if ($action === 'delete') {
                customcore_admin_option_delete($pdo, $optionId, $productId, $group);
                $pdo->commit();
                customcore_flash_success('Option “' . (string) $targetOption['option_label'] . '” was deleted.');
            } elseif ($action === 'toggle') {
                $makeActive = (int) $targetOption['is_active'] !== 1;
                customcore_admin_option_set_active($pdo, $optionId, $productId, $group, $makeActive);
                $pdo->commit();
                customcore_flash_success(
                    'Option “' . (string) $targetOption['option_label'] . '” is now '
                    . ($makeActive ? 'active' : 'disabled') . '.'
                );
            } elseif ($action === 'set_default') {
                customcore_admin_option_set_default($pdo, $optionId, $productId, $group);
                $pdo->commit();
                customcore_flash_success('“' . (string) $targetOption['option_label'] . '” is now the default for ' . $group . '.');
            } else {
                $pdo->rollBack();
                customcore_flash_error('That action could not be completed.');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            customcore_flash_error(
                customcore_is_debug() ? $exception->getMessage() : 'That action could not be completed.'
            );
        }
        customcore_redirect($selfUrl);
    }
}

// Edit request (GET ?edit=ID): preload the form for that option.
if ($product !== null && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $editCandidate = (int) $_GET['edit'];
    $editRow = customcore_admin_option_fetch($pdo, $editCandidate);
    if ($editRow !== null && (int) $editRow['product_id'] === $productId) {
        $editId = $editCandidate;
        $formValues = [
            'option_group' => (string) $editRow['option_group'],
            'option_label' => (string) $editRow['option_label'],
            'price_delta' => (string) $editRow['price_delta'],
            'is_default' => (int) $editRow['is_default'],
            'is_active' => (int) $editRow['is_active'],
            'sort_order' => (string) $editRow['sort_order'],
        ];
    }
}

// Load data for rendering.
$productPickerList = [];
$options = [];
$groupedOptions = [];
$groupNames = [];
$summary = ['active' => 0, 'total' => 0, 'groups' => 0, 'groups_without_default' => []];
$loadError = null;

try {
    if ($product === null) {
        $productPickerList = customcore_admin_product_list($pdo, []);
    } else {
        $options = customcore_admin_options_for_product($pdo, $productId);
        $groupedOptions = customcore_admin_options_group($options);
        $groupNames = customcore_admin_option_group_names($pdo, $productId);
        $summary = customcore_admin_option_summary($pdo, $productId);
    }
} catch (Throwable $exception) {
    $loadError = customcore_is_debug() ? $exception->getMessage() : 'Product options are temporarily unavailable.';
}

$pageTitle = $product !== null
    ? 'Options: ' . (string) $product['name'] . ' | CustomCore Admin'
    : 'Product Options | CustomCore Admin';
$pageDescription = 'Manage configurable product options and price adjustments.';
$pageKeywords = 'CustomCore, admin, product options';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Product options: manage configurable choices and price deltas -->
<section class="content-section admin-page admin-options" aria-labelledby="admin-options-heading">
    <header class="admin-page__header">
        <h1 id="admin-options-heading">Product options</h1>
        <p class="admin-page__intro">
            Manage the configurable choices buyers pick on the product and PC&nbsp;Builder pages.
            Each group keeps exactly one default selection.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/products.php')); ?>">Back to products</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Dashboard</a>
        </p>
    </header>

    <!-- Admin section navigation -->
    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <!-- Load error banner -->
    <?php if ($loadError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($loadError); ?></p>
    <?php endif; ?>

    <!-- No product chosen: product picker; otherwise the options manager -->
    <?php if ($product === null) : ?>
        <div class="admin-options__picker">
            <h2>Choose a product</h2>
            <p>Select the product whose options you want to manage.</p>
            <form method="get" action="<?php echo customcore_e(customcore_url('admin/product-options.php')); ?>" class="admin-filter">
                <div class="admin-filter__field">
                    <label for="pick-product">Product</label>
                    <select id="pick-product" name="product_id" required>
                        <option value="">Choose a product…</option>
                        <?php foreach ($productPickerList as $p) : ?>
                            <option value="<?php echo customcore_e((string) $p['id']); ?>">
                                <?php echo customcore_e((string) $p['name']); ?>
                                <?php echo (int) $p['is_active'] === 1 ? '' : ' (disabled)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-filter__actions">
                    <button type="submit" class="button button--sm">Manage options</button>
                </div>
            </form>
        </div>
    <?php else : ?>

        <!-- Product header: name, base price, category, and edit link -->
        <div class="admin-options__product">
            <h2 class="admin-options__product-name"><?php echo customcore_e((string) $product['name']); ?></h2>
            <p class="admin-table__sub">
                Base price $<?php echo customcore_e(number_format((float) $product['base_price'], 2)); ?>
                · <?php echo customcore_e((string) ($product['category_name'] ?? 'Uncategorised')); ?>
                · <a href="<?php echo customcore_e(customcore_url('admin/product-edit.php?id=' . $productId)); ?>">Edit product</a>
            </p>
        </div>

        <?php
        $summaryFlags = [];
        if ($summary['active'] < 2) {
            $summaryFlags[] = 'This product has fewer than 2 active options. The catalogue expects at least 2 per product.';
        }
        if ($summary['groups_without_default'] !== []) {
            $summaryFlags[] = 'Missing a default in: ' . implode(', ', $summary['groups_without_default']) . '.';
        }
        ?>
        <!-- Configuration warnings: too few options or missing group defaults -->
        <?php if ($summaryFlags !== []) : ?>
            <div class="flash flash--warning" role="status">
                <?php foreach ($summaryFlags as $flag) : ?>
                    <div><?php echo customcore_e($flag); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="admin-products__count">
            <?php echo customcore_e((string) $summary['active']); ?> active
            of <?php echo customcore_e((string) $summary['total']); ?> options
            across <?php echo customcore_e((string) $summary['groups']); ?> groups.
        </p>

        <!-- Add / edit form -->
        <div class="admin-options__form-wrap">
            <h2><?php echo $editId > 0 ? 'Edit option' : 'Add option'; ?></h2>

            <?php if (isset($formErrors['form'])) : ?>
                <p class="flash flash--error" role="alert"><?php echo customcore_e($formErrors['form']); ?></p>
            <?php endif; ?>

            <form
                class="admin-form admin-options__form"
                method="post"
                action="<?php echo customcore_e(customcore_url($selfUrl)); ?>"
                novalidate
            >
                <?php echo customcore_csrf_field(); ?>
                <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">
                <input type="hidden" name="action" value="<?php echo $editId > 0 ? 'update' : 'create'; ?>">
                <?php if ($editId > 0) : ?>
                    <input type="hidden" name="option_id" value="<?php echo customcore_e((string) $editId); ?>">
                <?php endif; ?>

                <div class="admin-form__grid">
                    <div class="form-field">
                        <label for="opt-group">Group <span class="form-required">*</span></label>
                        <input
                            type="text" id="opt-group" name="option_group" list="opt-group-list"
                            maxlength="50" required
                            value="<?php echo customcore_e((string) $formValues['option_group']); ?>"
                            <?php echo isset($formErrors['option_group']) ? 'aria-invalid="true"' : ''; ?>
                        >
                        <datalist id="opt-group-list">
                            <?php foreach ($groupNames as $g) : ?>
                                <option value="<?php echo customcore_e($g); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <span class="form-hint">e.g. RAM, Storage, Colour, Warranty.</span>
                        <?php if (isset($formErrors['option_group'])) : ?>
                            <span class="form-error" role="alert"><?php echo customcore_e($formErrors['option_group']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="opt-label">Label <span class="form-required">*</span></label>
                        <input
                            type="text" id="opt-label" name="option_label" maxlength="150" required
                            value="<?php echo customcore_e((string) $formValues['option_label']); ?>"
                            <?php echo isset($formErrors['option_label']) ? 'aria-invalid="true"' : ''; ?>
                        >
                        <span class="form-hint">e.g. 32 GB DDR5, 2 TB NVMe SSD.</span>
                        <?php if (isset($formErrors['option_label'])) : ?>
                            <span class="form-error" role="alert"><?php echo customcore_e($formErrors['option_label']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="opt-delta">Price change (USD)</label>
                        <input
                            type="number" id="opt-delta" name="price_delta" step="0.01"
                            min="-999999.99" max="999999.99"
                            value="<?php echo customcore_e((string) $formValues['price_delta']); ?>"
                            <?php echo isset($formErrors['price_delta']) ? 'aria-invalid="true"' : ''; ?>
                        >
                        <span class="form-hint">Added to the base price. Use a negative value to reduce it. 0 = included.</span>
                        <?php if (isset($formErrors['price_delta'])) : ?>
                            <span class="form-error" role="alert"><?php echo customcore_e($formErrors['price_delta']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="opt-sort">Sort order</label>
                        <input
                            type="number" id="opt-sort" name="sort_order" step="1" min="0" max="100000"
                            value="<?php echo customcore_e((string) $formValues['sort_order']); ?>"
                            placeholder="0"
                            <?php echo isset($formErrors['sort_order']) ? 'aria-invalid="true"' : ''; ?>
                        >
                        <span class="form-hint">Lower numbers appear first within the group.</span>
                        <?php if (isset($formErrors['sort_order'])) : ?>
                            <span class="form-error" role="alert"><?php echo customcore_e($formErrors['sort_order']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field form-field--wide admin-form__toggles">
                        <label class="form-check">
                            <input type="checkbox" name="is_active" value="1"
                                   <?php echo (int) $formValues['is_active'] === 1 ? 'checked' : ''; ?>>
                            Active (selectable by buyers)
                        </label>
                        <label class="form-check">
                            <input type="checkbox" name="is_default" value="1"
                                   <?php echo (int) $formValues['is_default'] === 1 ? 'checked' : ''; ?>>
                            Default choice for its group
                        </label>
                    </div>
                </div>

                <div class="admin-form__actions">
                    <button type="submit" class="button"><?php echo $editId > 0 ? 'Save option' : 'Add option'; ?></button>
                    <?php if ($editId > 0) : ?>
                        <a class="button button--ghost" href="<?php echo customcore_e(customcore_url($selfUrl)); ?>">Cancel edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Existing options grouped -->
        <?php if ($options === []) : ?>
            <p class="admin-activity__empty">This product has no options yet. Add at least two using the form above.</p>
        <?php else : ?>
            <?php foreach ($groupedOptions as $groupName => $groupRows) : ?>
                <div class="admin-options__group">
                    <h3 class="admin-options__group-title"><?php echo customcore_e($groupName); ?></h3>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-table--options">
                            <thead>
                                <tr>
                                    <th scope="col">Label</th>
                                    <th scope="col">Price change</th>
                                    <th scope="col">Sort</th>
                                    <th scope="col">Default</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupRows as $opt) : ?>
                                    <?php
                                    $oid = (int) $opt['id'];
                                    $delta = (float) $opt['price_delta'];
                                    $optActive = (int) $opt['is_active'] === 1;
                                    $optDefault = (int) $opt['is_default'] === 1;
                                    if ($delta > 0) {
                                        $deltaLabel = '+$' . number_format($delta, 2);
                                    } elseif ($delta < 0) {
                                        $deltaLabel = '-$' . number_format(abs($delta), 2);
                                    } else {
                                        $deltaLabel = 'Included';
                                    }
                                    ?>
                                    <tr class="<?php echo $optActive ? '' : 'is-disabled-row'; ?>">
                                        <td><?php echo customcore_e((string) $opt['option_label']); ?></td>
                                        <td><?php echo customcore_e($deltaLabel); ?></td>
                                        <td><?php echo customcore_e((string) $opt['sort_order']); ?></td>
                                        <td>
                                            <?php if ($optDefault) : ?>
                                                <span class="admin-badge admin-badge--ok">Default</span>
                                            <?php elseif ($optActive) : ?>
                                                <form class="admin-inline-form" method="post"
                                                      action="<?php echo customcore_e(customcore_url($selfUrl)); ?>">
                                                    <?php echo customcore_csrf_field(); ?>
                                                    <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">
                                                    <input type="hidden" name="option_id" value="<?php echo customcore_e((string) $oid); ?>">
                                                    <input type="hidden" name="action" value="set_default">
                                                    <button type="submit" class="admin-actions__link admin-actions__link--button">Make default</button>
                                                </form>
                                            <?php else : ?>
                                                <span class="admin-table__sub">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($optActive) : ?>
                                                <span class="admin-badge admin-badge--ok">Active</span>
                                            <?php else : ?>
                                                <span class="admin-badge admin-badge--muted">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="admin-actions">
                                                <a class="button button--ghost button--sm"
                                                   href="<?php echo customcore_e(customcore_url($selfUrl . '&edit=' . $oid)); ?>">Edit</a>

                                                <form class="admin-inline-form" method="post"
                                                      action="<?php echo customcore_e(customcore_url($selfUrl)); ?>">
                                                    <?php echo customcore_csrf_field(); ?>
                                                    <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">
                                                    <input type="hidden" name="option_id" value="<?php echo customcore_e((string) $oid); ?>">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <button type="submit" class="button button--sm <?php echo $optActive ? 'button--danger' : 'button--success'; ?>">
                                                        <?php echo $optActive ? 'Disable' : 'Enable'; ?>
                                                    </button>
                                                </form>

                                                <form class="admin-inline-form" method="post"
                                                      action="<?php echo customcore_e(customcore_url($selfUrl)); ?>"
                                                      onsubmit="return confirm('Delete this option permanently?');">
                                                    <?php echo customcore_csrf_field(); ?>
                                                    <input type="hidden" name="product_id" value="<?php echo customcore_e((string) $productId); ?>">
                                                    <input type="hidden" name="option_id" value="<?php echo customcore_e((string) $oid); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="button button--sm button--danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
