<?php
/**
 * CustomCore — Administrator compatibility metadata (Commit 9.4).
 *
 * File responsibility:
 *   Protected editor for the simplified compatibility metadata used by the PC
 *   Builder: (1) component attribute columns (only the fields relevant to each
 *   builder category) and (2) compatibility rules (name, description, severity,
 *   active flag; the JSON config wiring is shown read-only). Toggling a
 *   component or rule active status is a one-click POST action.
 *
 * Authentication requirements:
 *   Administrator role (customcore_require_admin()).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/admin-compatibility.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/flash.php';

customcore_require_admin();

$pdo = customcore_pdo();

$adminNavCurrent = 'compatibility';
$loadAdminCss = true;
$currentPage = 'admin';

/** Build the components filter query string for redirects. */
function customcore_admin_compat_query(array $filters): string
{
    $params = [];
    if (($filters['search'] ?? '') !== '') {
        $params['q'] = (string) $filters['search'];
    }
    if ((int) ($filters['category_id'] ?? 0) > 0) {
        $params['category'] = (int) $filters['category_id'];
    }

    return $params === [] ? '' : '?' . http_build_query($params);
}

$filters = [
    'search' => isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
    'category_id' => isset($_GET['category']) ? (int) $_GET['category'] : 0,
];
$listQuery = customcore_admin_compat_query($filters);

// Edit contexts and form state.
$editComponent = null;
$componentErrors = [];
$componentValues = [];      // col => submitted value (for redisplay on error)
$componentActive = 1;

$editRule = null;
$ruleErrors = [];
$ruleValues = [];

// ---------------------------------------------------------------------------
// POST actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : null;
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

    if (!customcore_csrf_verify($token)) {
        customcore_flash_error('Your session expired. Please try again.');
        customcore_redirect('admin/compatibility.php' . $listQuery);
    }

    // ----- Component actions -----
    if ($action === 'update_component' || $action === 'toggle_component') {
        $componentId = isset($_POST['component_id']) ? (int) $_POST['component_id'] : 0;
        $component = customcore_admin_compat_component_fetch($pdo, $componentId);
        if ($component === null) {
            customcore_flash_error('That component could not be found.');
            customcore_redirect('admin/compatibility.php' . $listQuery);
        }

        if ($action === 'toggle_component') {
            try {
                $makeActive = (int) $component['is_active'] !== 1;
                customcore_admin_compat_set_component_active($pdo, $componentId, $makeActive);
                customcore_flash_success(
                    '“' . (string) $component['name'] . '” is now '
                    . ($makeActive ? 'active in the builder' : 'hidden from the builder') . '.'
                );
            } catch (Throwable $e) {
                customcore_flash_error(customcore_is_debug() ? $e->getMessage() : 'The component could not be updated.');
            }
            customcore_redirect('admin/compatibility.php' . $listQuery);
        }

        // update_component
        $slug = (string) $component['category_slug'];
        $validation = customcore_admin_compat_validate_component($_POST, $slug);
        $componentErrors = $validation['errors'];
        $componentValues = $validation['values'];
        $componentActive = $validation['is_active'];

        if ($componentErrors === []) {
            try {
                customcore_admin_compat_update_component($pdo, $componentId, $slug, $componentValues, $componentActive);
                customcore_flash_success('Compatibility metadata for “' . (string) $component['name'] . '” was updated.');
                customcore_redirect('admin/compatibility.php?component_id=' . $componentId);
            } catch (Throwable $e) {
                $componentErrors['form'] = customcore_is_debug() ? $e->getMessage() : 'The component could not be saved.';
            }
        }
        // Re-render with the component in edit mode.
        $editComponent = $component;
    } elseif ($action === 'update_rule' || $action === 'toggle_rule') {
        // ----- Rule actions -----
        $ruleId = isset($_POST['rule_id']) ? (int) $_POST['rule_id'] : 0;
        $rule = customcore_admin_compat_rule_fetch($pdo, $ruleId);
        if ($rule === null) {
            customcore_flash_error('That rule could not be found.');
            customcore_redirect('admin/compatibility.php' . $listQuery);
        }

        if ($action === 'toggle_rule') {
            try {
                $makeActive = (int) $rule['is_active'] !== 1;
                customcore_admin_compat_set_rule_active($pdo, $ruleId, $makeActive);
                customcore_flash_success(
                    'Rule “' . (string) $rule['name'] . '” is now '
                    . ($makeActive ? 'active' : 'disabled') . '.'
                );
            } catch (Throwable $e) {
                customcore_flash_error(customcore_is_debug() ? $e->getMessage() : 'The rule could not be updated.');
            }
            customcore_redirect('admin/compatibility.php' . $listQuery);
        }

        // update_rule
        $validation = customcore_admin_compat_validate_rule($_POST);
        $ruleErrors = $validation['errors'];
        $ruleValues = $validation['values'];

        if ($ruleErrors === []) {
            try {
                customcore_admin_compat_update_rule($pdo, $ruleId, $ruleValues);
                customcore_flash_success('Rule “' . $ruleValues['name'] . '” was updated.');
                customcore_redirect('admin/compatibility.php?rule_id=' . $ruleId);
            } catch (Throwable $e) {
                $ruleErrors['form'] = customcore_is_debug() ? $e->getMessage() : 'The rule could not be saved.';
            }
        }
        $editRule = $rule;
    } else {
        customcore_flash_error('That action could not be completed.');
        customcore_redirect('admin/compatibility.php' . $listQuery);
    }
}

// ---------------------------------------------------------------------------
// GET edit requests
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['component_id'])) {
    $editComponent = customcore_admin_compat_component_fetch($pdo, (int) $_GET['component_id']);
    if ($editComponent === null) {
        customcore_flash_error('That component could not be found.');
        customcore_redirect('admin/compatibility.php');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['rule_id'])) {
    $editRule = customcore_admin_compat_rule_fetch($pdo, (int) $_GET['rule_id']);
    if ($editRule === null) {
        customcore_flash_error('That rule could not be found.');
        customcore_redirect('admin/compatibility.php');
    }
}

// ---------------------------------------------------------------------------
// Load lists for rendering
// ---------------------------------------------------------------------------
$categories = [];
$components = [];
$rules = [];
$loadError = null;

try {
    $categories = customcore_admin_compat_categories($pdo);
    $components = customcore_admin_compat_components($pdo, $filters);
    $rules = customcore_admin_compat_rules($pdo);
} catch (Throwable $e) {
    $loadError = customcore_is_debug() ? $e->getMessage() : 'Compatibility data is temporarily unavailable.';
}

// Helper: current value for a component field (submitted on error, else stored).
$componentFieldValue = static function (string $col) use ($componentValues, $editComponent) {
    if (array_key_exists($col, $componentValues)) {
        return $componentValues[$col];
    }
    if ($editComponent !== null && array_key_exists($col, $editComponent)) {
        return $editComponent[$col];
    }
    return '';
};

// Helper: current value for a rule field.
$ruleFieldValue = static function (string $key) use ($ruleValues, $editRule) {
    if (array_key_exists($key, $ruleValues)) {
        return $ruleValues[$key];
    }
    if ($editRule !== null && array_key_exists($key, $editRule)) {
        return $editRule[$key];
    }
    return '';
};

$pageTitle = 'Compatibility metadata — CustomCore admin';
$pageDescription = 'Edit the simplified compatibility metadata used by the CustomCore PC Builder.';
$pageKeywords = 'CustomCore, admin, compatibility, PC builder';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="content-section admin-page admin-compat" aria-labelledby="admin-compat-heading">
    <header class="admin-page__header">
        <h1 id="admin-compat-heading">Compatibility metadata</h1>
        <p class="admin-page__intro">
            Edit the component attributes and rules that power the PC&nbsp;Builder's
            compatibility checks. Changes take effect immediately for new builds.
        </p>
        <p class="context-help">
            <a href="<?php echo customcore_e(customcore_url('admin/index.php')); ?>">Back to dashboard</a>
            ·
            <a href="<?php echo customcore_e(customcore_url('builder.php')); ?>" target="_blank" rel="noopener">Open PC Builder</a>
        </p>
    </header>

    <?php require __DIR__ . '/../includes/admin-nav.php'; ?>

    <?php if ($loadError !== null) : ?>
        <p class="flash flash--error" role="alert"><?php echo customcore_e($loadError); ?></p>
    <?php endif; ?>

    <?php // ----- Component edit form ----- ?>
    <?php if ($editComponent !== null) :
        $slug = (string) $editComponent['category_slug'];
        $fields = customcore_admin_compat_fields_for($slug);
        ?>
        <div class="admin-compat__editor" id="component-editor">
            <h2>Edit component metadata</h2>
            <p class="admin-table__sub">
                <strong><?php echo customcore_e((string) $editComponent['name']); ?></strong>
                · <?php echo customcore_e((string) $editComponent['category_name']); ?>
                · <?php echo customcore_e((string) $editComponent['brand']); ?>
                · $<?php echo customcore_e(number_format((float) $editComponent['price'], 2)); ?>
            </p>

            <?php if (isset($componentErrors['form'])) : ?>
                <p class="flash flash--error" role="alert"><?php echo customcore_e($componentErrors['form']); ?></p>
            <?php endif; ?>

            <form class="admin-form" method="post" action="<?php echo customcore_e(customcore_url('admin/compatibility.php')); ?>" novalidate>
                <?php echo customcore_csrf_field(); ?>
                <input type="hidden" name="action" value="update_component">
                <input type="hidden" name="component_id" value="<?php echo customcore_e((string) $editComponent['id']); ?>">

                <?php if ($fields === []) : ?>
                    <p class="admin-activity__empty">This category has no editable compatibility attributes.</p>
                <?php else : ?>
                    <div class="admin-form__grid">
                        <?php foreach ($fields as $field) :
                            $col = (string) $field['col'];
                            $val = $componentFieldValue($col);
                            $val = $val === null ? '' : (string) $val;
                            $hasErr = isset($componentErrors[$col]);
                            ?>
                            <div class="form-field">
                                <label for="cf-<?php echo customcore_e($col); ?>"><?php echo customcore_e((string) $field['label']); ?></label>
                                <?php if ($field['type'] === 'enum') : ?>
                                    <select id="cf-<?php echo customcore_e($col); ?>" name="<?php echo customcore_e($col); ?>"
                                            <?php echo $hasErr ? 'aria-invalid="true"' : ''; ?>>
                                        <?php foreach (($field['options'] ?? ['']) as $opt) : ?>
                                            <option value="<?php echo customcore_e((string) $opt); ?>"
                                                <?php echo strcasecmp((string) $opt, $val) === 0 ? 'selected' : ''; ?>>
                                                <?php echo $opt === '' ? '— none —' : customcore_e((string) $opt); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($field['type'] === 'int') : ?>
                                    <input type="number" id="cf-<?php echo customcore_e($col); ?>" name="<?php echo customcore_e($col); ?>"
                                           min="0" max="<?php echo customcore_e((string) ($field['max'] ?? 100000)); ?>" step="1"
                                           value="<?php echo customcore_e($val); ?>"
                                           <?php echo $hasErr ? 'aria-invalid="true"' : ''; ?>>
                                <?php else : ?>
                                    <input type="text" id="cf-<?php echo customcore_e($col); ?>" name="<?php echo customcore_e($col); ?>"
                                           maxlength="<?php echo customcore_e((string) ($field['maxlen'] ?? 255)); ?>"
                                           value="<?php echo customcore_e($val); ?>"
                                           <?php echo $hasErr ? 'aria-invalid="true"' : ''; ?>>
                                <?php endif; ?>
                                <?php if (!empty($field['hint'])) : ?>
                                    <span class="form-hint"><?php echo customcore_e((string) $field['hint']); ?></span>
                                <?php endif; ?>
                                <?php if ($hasErr) : ?>
                                    <span class="form-error" role="alert"><?php echo customcore_e($componentErrors[$col]); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="form-field admin-form__toggles">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1"
                            <?php
                            $activeVal = array_key_exists('is_active', $_POST) || $_SERVER['REQUEST_METHOD'] === 'POST'
                                ? $componentActive
                                : (int) $editComponent['is_active'];
                            echo $activeVal === 1 ? 'checked' : '';
                            ?>>
                        Active (selectable in the builder)
                    </label>
                </div>

                <div class="admin-form__actions">
                    <button type="submit" class="button">Save metadata</button>
                    <a class="button button--ghost" href="<?php echo customcore_e(customcore_url('admin/compatibility.php' . $listQuery)); ?>">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php // ----- Rule edit form ----- ?>
    <?php if ($editRule !== null) :
        $configPretty = '';
        $rawConfig = (string) ($editRule['config'] ?? '');
        if ($rawConfig !== '') {
            $decoded = json_decode($rawConfig, true);
            $configPretty = is_array($decoded)
                ? (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : $rawConfig;
        }
        ?>
        <div class="admin-compat__editor" id="rule-editor">
            <h2>Edit compatibility rule</h2>
            <p class="admin-table__sub">
                Code: <code><?php echo customcore_e((string) $editRule['rule_code']); ?></code>
            </p>

            <?php if (isset($ruleErrors['form'])) : ?>
                <p class="flash flash--error" role="alert"><?php echo customcore_e($ruleErrors['form']); ?></p>
            <?php endif; ?>

            <form class="admin-form" method="post" action="<?php echo customcore_e(customcore_url('admin/compatibility.php')); ?>" novalidate>
                <?php echo customcore_csrf_field(); ?>
                <input type="hidden" name="action" value="update_rule">
                <input type="hidden" name="rule_id" value="<?php echo customcore_e((string) $editRule['id']); ?>">

                <div class="admin-form__grid">
                    <div class="form-field form-field--wide">
                        <label for="rule-name">Name <span class="form-required">*</span></label>
                        <input type="text" id="rule-name" name="name" maxlength="150" required
                               value="<?php echo customcore_e((string) $ruleFieldValue('name')); ?>"
                               <?php echo isset($ruleErrors['name']) ? 'aria-invalid="true"' : ''; ?>>
                        <?php if (isset($ruleErrors['name'])) : ?>
                            <span class="form-error" role="alert"><?php echo customcore_e($ruleErrors['name']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field form-field--wide">
                        <label for="rule-desc">Description <span class="form-required">*</span></label>
                        <textarea id="rule-desc" name="description" rows="3" required
                                  <?php echo isset($ruleErrors['description']) ? 'aria-invalid="true"' : ''; ?>><?php echo customcore_e((string) $ruleFieldValue('description')); ?></textarea>
                        <span class="form-hint">Shown to buyers when the rule warns or fails.</span>
                        <?php if (isset($ruleErrors['description'])) : ?>
                            <span class="form-error" role="alert"><?php echo customcore_e($ruleErrors['description']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-field">
                        <label for="rule-sev">Severity <span class="form-required">*</span></label>
                        <?php $sevVal = (string) $ruleFieldValue('severity'); ?>
                        <select id="rule-sev" name="severity">
                            <option value="error" <?php echo $sevVal === 'error' ? 'selected' : ''; ?>>Error (blocks as incompatible)</option>
                            <option value="warning" <?php echo $sevVal === 'warning' ? 'selected' : ''; ?>>Warning (allows with caution)</option>
                        </select>
                        <span class="form-hint">Rules that use per-result severity in their config keep that behaviour.</span>
                    </div>

                    <div class="form-field admin-form__toggles">
                        <label class="form-check">
                            <input type="checkbox" name="is_active" value="1"
                                <?php echo (int) $ruleFieldValue('is_active') === 1 ? 'checked' : ''; ?>>
                            Active (evaluated on every build)
                        </label>
                    </div>

                    <?php if ($configPretty !== '') : ?>
                        <div class="form-field form-field--wide">
                            <label for="rule-config">Configuration (read-only)</label>
                            <textarea id="rule-config" rows="8" readonly class="admin-compat__config"><?php echo customcore_e($configPretty); ?></textarea>
                            <span class="form-hint">This wiring maps the rule to component columns and is managed in code/seeds.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="admin-form__actions">
                    <button type="submit" class="button">Save rule</button>
                    <a class="button button--ghost" href="<?php echo customcore_e(customcore_url('admin/compatibility.php' . $listQuery)); ?>">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php // ----- Component metadata list ----- ?>
    <section class="admin-compat__section" aria-labelledby="compat-components-heading">
        <h2 id="compat-components-heading">Component attributes</h2>

        <form class="admin-filter" method="get" action="<?php echo customcore_e(customcore_url('admin/compatibility.php')); ?>">
            <div class="admin-filter__field">
                <label for="filter-q">Search</label>
                <input type="search" id="filter-q" name="q" value="<?php echo customcore_e($filters['search']); ?>" placeholder="Name or brand" maxlength="200">
            </div>
            <div class="admin-filter__field">
                <label for="filter-category">Category</label>
                <select id="filter-category" name="category">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $cat) : ?>
                        <option value="<?php echo customcore_e((string) $cat['id']); ?>"
                            <?php echo $filters['category_id'] === $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo customcore_e($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="admin-filter__actions">
                <button type="submit" class="button button--sm">Apply</button>
                <?php if ($listQuery !== '') : ?>
                    <a class="button button--ghost button--sm" href="<?php echo customcore_e(customcore_url('admin/compatibility.php')); ?>">Reset</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($components === []) : ?>
            <p class="admin-activity__empty">No components match your filters.</p>
        <?php else : ?>
            <div class="admin-table-wrap">
                <table class="admin-table admin-table--compat">
                    <thead>
                        <tr>
                            <th scope="col">Component</th>
                            <th scope="col">Category</th>
                            <th scope="col">Compatibility attributes</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($components as $comp) :
                            $cActive = (int) $comp['is_active'] === 1;
                            $fields = customcore_admin_compat_fields_for((string) $comp['category_slug']);
                            ?>
                            <tr class="<?php echo $cActive ? '' : 'is-disabled-row'; ?>">
                                <td>
                                    <span class="admin-product-cell__name"><?php echo customcore_e((string) $comp['name']); ?></span>
                                    <span class="admin-table__sub"><?php echo customcore_e((string) $comp['brand']); ?></span>
                                </td>
                                <td><?php echo customcore_e((string) $comp['category_name']); ?></td>
                                <td>
                                    <?php if ($fields === []) : ?>
                                        <span class="admin-table__sub">No compatibility attributes</span>
                                    <?php else : ?>
                                        <ul class="admin-compat__chips">
                                            <?php foreach ($fields as $field) :
                                                $col = (string) $field['col'];
                                                $raw = $comp[$col] ?? null;
                                                $disp = customcore_admin_compat_display($raw === null ? null : (string) $raw);
                                                ?>
                                                <li class="admin-compat__chip">
                                                    <span class="admin-compat__chip-key"><?php echo customcore_e((string) $field['label']); ?>:</span>
                                                    <?php echo customcore_e($disp); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cActive) : ?>
                                        <span class="admin-badge admin-badge--ok">Active</span>
                                    <?php else : ?>
                                        <span class="admin-badge admin-badge--muted">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="admin-actions">
                                        <a class="button button--ghost button--sm"
                                           href="<?php echo customcore_e(customcore_url('admin/compatibility.php?component_id=' . (int) $comp['id'])); ?>">Edit</a>
                                        <form class="admin-inline-form" method="post" action="<?php echo customcore_e(customcore_url('admin/compatibility.php' . $listQuery)); ?>">
                                            <?php echo customcore_csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_component">
                                            <input type="hidden" name="component_id" value="<?php echo customcore_e((string) $comp['id']); ?>">
                                            <button type="submit" class="button button--sm <?php echo $cActive ? 'button--danger' : 'button--success'; ?>">
                                                <?php echo $cActive ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php // ----- Rules list ----- ?>
    <section class="admin-compat__section" aria-labelledby="compat-rules-heading">
        <h2 id="compat-rules-heading">Compatibility rules</h2>
        <p class="admin-products__count">
            <?php echo customcore_e((string) count($rules)); ?> rule<?php echo count($rules) === 1 ? '' : 's'; ?> drive the builder's checks.
        </p>

        <?php if ($rules === []) : ?>
            <p class="admin-activity__empty">No compatibility rules are defined.</p>
        <?php else : ?>
            <div class="admin-table-wrap">
                <table class="admin-table admin-table--rules">
                    <thead>
                        <tr>
                            <th scope="col">Rule</th>
                            <th scope="col">Severity</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $rule) :
                            $rActive = (int) $rule['is_active'] === 1;
                            $sev = (string) $rule['severity'];
                            ?>
                            <tr class="<?php echo $rActive ? '' : 'is-disabled-row'; ?>">
                                <td>
                                    <span class="admin-product-cell__name"><?php echo customcore_e((string) $rule['name']); ?></span>
                                    <span class="admin-table__sub"><code><?php echo customcore_e((string) $rule['rule_code']); ?></code></span>
                                </td>
                                <td>
                                    <?php if ($sev === 'error') : ?>
                                        <span class="admin-badge admin-badge--danger">Error</span>
                                    <?php else : ?>
                                        <span class="admin-badge admin-badge--warn">Warning</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($rActive) : ?>
                                        <span class="admin-badge admin-badge--ok">Active</span>
                                    <?php else : ?>
                                        <span class="admin-badge admin-badge--muted">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="admin-actions">
                                        <a class="button button--ghost button--sm"
                                           href="<?php echo customcore_e(customcore_url('admin/compatibility.php?rule_id=' . (int) $rule['id'])); ?>">Edit</a>
                                        <form class="admin-inline-form" method="post" action="<?php echo customcore_e(customcore_url('admin/compatibility.php' . $listQuery)); ?>">
                                            <?php echo customcore_csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_rule">
                                            <input type="hidden" name="rule_id" value="<?php echo customcore_e((string) $rule['id']); ?>">
                                            <button type="submit" class="button button--sm <?php echo $rActive ? 'button--danger' : 'button--success'; ?>">
                                                <?php echo $rActive ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
