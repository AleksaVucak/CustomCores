<?php
/**
 * CustomCore — Administrator product options helpers (Commit 9.3).
 *
 * File responsibility:
 *   Shared, security-first helpers for managing a product's configurable
 *   options (RAM, Storage, Colour, Warranty, …): validation, list/fetch,
 *   create/update/delete, active toggling, default selection, and the invariant
 *   that keeps exactly one active default per option group so the storefront and
 *   PC Builder always price a valid default configuration.
 *
 * Usage:
 *   require_once __DIR__ . '/admin-options.php';
 *
 * Data model (see database/schema.sql):
 *   product_options(id, product_id, option_group, option_label, price_delta,
 *                   is_default, is_active, sort_order, created_at, updated_at)
 *
 * Business rules enforced here:
 *   - Among a product/group's ACTIVE options there is exactly one is_default = 1
 *     (auto-normalised after every change). Inactive options are never default.
 *   - price_delta may be positive or negative (a group choice can add to or
 *     reduce base_price) within the DECIMAL(10,2) range.
 *   - Every write uses PDO prepared statements; callers wrap multi-step actions
 *     in a transaction.
 */

declare(strict_types=1);

if (!function_exists('customcore_app_config')) {
    require_once __DIR__ . '/functions.php';
}

const CUSTOMCORE_OPTION_GROUP_MAX = 50;
const CUSTOMCORE_OPTION_LABEL_MAX = 150;
const CUSTOMCORE_OPTION_DELTA_MAX = 999999.99;
const CUSTOMCORE_OPTION_SORT_MAX = 100000;

/**
 * All options for a product (active and inactive) for the admin table.
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_options_for_product(PDO $pdo, int $productId): array
{
    if ($productId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, product_id, option_group, option_label, price_delta,
                is_default, is_active, sort_order, updated_at
         FROM product_options
         WHERE product_id = :pid
         ORDER BY option_group ASC, sort_order ASC, option_label ASC'
    );
    $stmt->execute([':pid' => $productId]);

    return $stmt->fetchAll();
}

/**
 * Group a flat option list by option_group, preserving order.
 *
 * @param list<array<string, mixed>> $options
 * @return array<string, list<array<string, mixed>>>
 */
function customcore_admin_options_group(array $options): array
{
    $grouped = [];
    foreach ($options as $opt) {
        $grouped[(string) $opt['option_group']][] = $opt;
    }

    return $grouped;
}

/**
 * Distinct option-group names already used by a product (for a datalist).
 *
 * @return list<string>
 */
function customcore_admin_option_group_names(PDO $pdo, int $productId): array
{
    if ($productId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT option_group FROM product_options
         WHERE product_id = :pid ORDER BY option_group ASC'
    );
    $stmt->execute([':pid' => $productId]);

    return array_map('strval', array_column($stmt->fetchAll(), 'option_group'));
}

/**
 * Fetch a single option row (includes product_id for ownership checks).
 *
 * @return array<string, mixed>|null
 */
function customcore_admin_option_fetch(PDO $pdo, int $optionId): ?array
{
    if ($optionId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM product_options WHERE id = :id');
    $stmt->execute([':id' => $optionId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Suggested next sort_order for a group (max + 10, or 10 when empty).
 */
function customcore_admin_option_next_sort(PDO $pdo, int $productId, string $group): int
{
    $stmt = $pdo->prepare(
        'SELECT MAX(sort_order) FROM product_options
         WHERE product_id = :pid AND option_group = :grp'
    );
    $stmt->execute([':pid' => $productId, ':grp' => $group]);
    $max = $stmt->fetchColumn();

    return $max === false || $max === null ? 10 : ((int) $max + 10);
}

/**
 * Validate and normalise option form input.
 *
 * @param array<string, mixed> $input Raw $_POST.
 * @return array{
 *   errors: array<string, string>,
 *   values: array{
 *     option_group:string, option_label:string, price_delta:float,
 *     is_default:int, is_active:int, sort_order:int
 *   }
 * }
 */
function customcore_admin_option_validate(array $input): array
{
    $errors = [];

    $group = isset($input['option_group']) && is_string($input['option_group'])
        ? trim($input['option_group'])
        : '';
    if ($group === '') {
        $errors['option_group'] = 'An option group is required (e.g. RAM, Storage).';
    } elseif (mb_strlen($group) > CUSTOMCORE_OPTION_GROUP_MAX) {
        $errors['option_group'] = 'Group must be ' . CUSTOMCORE_OPTION_GROUP_MAX . ' characters or fewer.';
        $group = mb_substr($group, 0, CUSTOMCORE_OPTION_GROUP_MAX);
    }

    $label = isset($input['option_label']) && is_string($input['option_label'])
        ? trim($input['option_label'])
        : '';
    if ($label === '') {
        $errors['option_label'] = 'An option label is required (e.g. 32 GB DDR5).';
    } elseif (mb_strlen($label) > CUSTOMCORE_OPTION_LABEL_MAX) {
        $errors['option_label'] = 'Label must be ' . CUSTOMCORE_OPTION_LABEL_MAX . ' characters or fewer.';
        $label = mb_substr($label, 0, CUSTOMCORE_OPTION_LABEL_MAX);
    }

    $priceDelta = 0.0;
    $rawDelta = $input['price_delta'] ?? '';
    if (is_string($rawDelta) || is_int($rawDelta) || is_float($rawDelta)) {
        $rawDelta = trim((string) $rawDelta);
        if ($rawDelta === '') {
            $priceDelta = 0.0;
        } elseif (!is_numeric($rawDelta)) {
            $errors['price_delta'] = 'Enter a valid price change (use negative values to reduce the price).';
        } else {
            $priceDelta = round((float) $rawDelta, 2);
            if ($priceDelta > CUSTOMCORE_OPTION_DELTA_MAX || $priceDelta < -CUSTOMCORE_OPTION_DELTA_MAX) {
                $errors['price_delta'] = 'Price change is out of range.';
                $priceDelta = max(-CUSTOMCORE_OPTION_DELTA_MAX, min(CUSTOMCORE_OPTION_DELTA_MAX, $priceDelta));
            }
        }
    } else {
        $errors['price_delta'] = 'Enter a valid price change.';
    }

    $sortOrder = 0;
    $rawSort = $input['sort_order'] ?? '';
    if (is_string($rawSort) || is_int($rawSort)) {
        $rawSort = trim((string) $rawSort);
        if ($rawSort === '') {
            $sortOrder = 0;
        } elseif (!preg_match('/^\d+$/', $rawSort)) {
            $errors['sort_order'] = 'Sort order must be a whole number (0 or more).';
        } else {
            $sortOrder = (int) $rawSort;
            if ($sortOrder > CUSTOMCORE_OPTION_SORT_MAX) {
                $sortOrder = CUSTOMCORE_OPTION_SORT_MAX;
            }
        }
    }

    $isActive = !empty($input['is_active']) ? 1 : 0;
    // An inactive option can never be the default choice.
    $isDefault = ($isActive === 1 && !empty($input['is_default'])) ? 1 : 0;

    return [
        'errors' => $errors,
        'values' => [
            'option_group' => $group,
            'option_label' => $label,
            'price_delta' => $priceDelta,
            'is_default' => $isDefault,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ],
    ];
}

/**
 * Ensure a product/group has exactly one active default option.
 *
 * - If two or more active defaults exist, keep the first (lowest sort_order,
 *   then id) and clear the rest.
 * - If no active default exists but active options do, promote the first.
 * - Inactive options are always forced to is_default = 0.
 *
 * Intended to run inside the caller's transaction after any change.
 */
function customcore_admin_option_normalize_group(PDO $pdo, int $productId, string $group): void
{
    if ($productId < 1 || $group === '') {
        return;
    }

    // Inactive options must never be marked default.
    $pdo->prepare(
        'UPDATE product_options SET is_default = 0
         WHERE product_id = :pid AND option_group = :grp AND is_active = 0 AND is_default = 1'
    )->execute([':pid' => $productId, ':grp' => $group]);

    $stmt = $pdo->prepare(
        'SELECT id, is_default FROM product_options
         WHERE product_id = :pid AND option_group = :grp AND is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([':pid' => $productId, ':grp' => $group]);
    $active = $stmt->fetchAll();

    if ($active === []) {
        return;
    }

    $defaultIds = [];
    foreach ($active as $row) {
        if ((int) $row['is_default'] === 1) {
            $defaultIds[] = (int) $row['id'];
        }
    }

    if (count($defaultIds) === 1) {
        return;
    }

    // Choose the winner (first active row) and reset everyone else in the group.
    $keepId = (int) $active[0]['id'];

    $clear = $pdo->prepare(
        'UPDATE product_options SET is_default = 0
         WHERE product_id = :pid AND option_group = :grp AND id <> :keep'
    );
    $clear->execute([':pid' => $productId, ':grp' => $group, ':keep' => $keepId]);

    $set = $pdo->prepare('UPDATE product_options SET is_default = 1 WHERE id = :keep');
    $set->execute([':keep' => $keepId]);
}

/**
 * Insert a new option and normalise its group's default. Returns new id.
 *
 * @param array<string, mixed> $values customcore_admin_option_validate()['values'].
 */
function customcore_admin_option_create(PDO $pdo, int $productId, array $values): int
{
    // A new explicit default clears the previous default in the group first.
    if ($values['is_default'] === 1) {
        $clear = $pdo->prepare(
            'UPDATE product_options SET is_default = 0
             WHERE product_id = :pid AND option_group = :grp'
        );
        $clear->execute([':pid' => $productId, ':grp' => $values['option_group']]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO product_options
            (product_id, option_group, option_label, price_delta, is_default, is_active, sort_order)
         VALUES
            (:pid, :grp, :label, :delta, :is_default, :is_active, :sort_order)'
    );
    $stmt->execute([
        ':pid' => $productId,
        ':grp' => $values['option_group'],
        ':label' => $values['option_label'],
        ':delta' => $values['price_delta'],
        ':is_default' => $values['is_default'],
        ':is_active' => $values['is_active'],
        ':sort_order' => $values['sort_order'],
    ]);

    $newId = (int) $pdo->lastInsertId();

    customcore_admin_option_normalize_group($pdo, $productId, $values['option_group']);

    return $newId;
}

/**
 * Update an existing option and normalise affected group defaults.
 *
 * @param array<string, mixed> $values customcore_admin_option_validate()['values'].
 * @param string $previousGroup The option's group before this edit (for renames).
 */
function customcore_admin_option_update(
    PDO $pdo,
    int $optionId,
    int $productId,
    array $values,
    string $previousGroup
): void {
    if ($values['is_default'] === 1) {
        $clear = $pdo->prepare(
            'UPDATE product_options SET is_default = 0
             WHERE product_id = :pid AND option_group = :grp AND id <> :id'
        );
        $clear->execute([
            ':pid' => $productId,
            ':grp' => $values['option_group'],
            ':id' => $optionId,
        ]);
    }

    $stmt = $pdo->prepare(
        'UPDATE product_options SET
            option_group = :grp,
            option_label = :label,
            price_delta = :delta,
            is_default = :is_default,
            is_active = :is_active,
            sort_order = :sort_order
         WHERE id = :id AND product_id = :pid'
    );
    $stmt->execute([
        ':grp' => $values['option_group'],
        ':label' => $values['option_label'],
        ':delta' => $values['price_delta'],
        ':is_default' => $values['is_default'],
        ':is_active' => $values['is_active'],
        ':sort_order' => $values['sort_order'],
        ':id' => $optionId,
        ':pid' => $productId,
    ]);

    // Normalise the new group and, if the group changed, the old one too.
    customcore_admin_option_normalize_group($pdo, $productId, $values['option_group']);
    if ($previousGroup !== '' && $previousGroup !== $values['option_group']) {
        customcore_admin_option_normalize_group($pdo, $productId, $previousGroup);
    }
}

/**
 * Delete an option, then normalise its group's default.
 */
function customcore_admin_option_delete(PDO $pdo, int $optionId, int $productId, string $group): void
{
    $stmt = $pdo->prepare('DELETE FROM product_options WHERE id = :id AND product_id = :pid');
    $stmt->execute([':id' => $optionId, ':pid' => $productId]);

    customcore_admin_option_normalize_group($pdo, $productId, $group);
}

/**
 * Enable or disable an option, then normalise its group's default.
 */
function customcore_admin_option_set_active(
    PDO $pdo,
    int $optionId,
    int $productId,
    string $group,
    bool $active
): void {
    $stmt = $pdo->prepare(
        'UPDATE product_options SET is_active = :active
         WHERE id = :id AND product_id = :pid'
    );
    $stmt->execute([
        ':active' => $active ? 1 : 0,
        ':id' => $optionId,
        ':pid' => $productId,
    ]);

    customcore_admin_option_normalize_group($pdo, $productId, $group);
}

/**
 * Make a specific option the default for its group (clears the group first).
 */
function customcore_admin_option_set_default(
    PDO $pdo,
    int $optionId,
    int $productId,
    string $group
): void {
    // Only an active option may become the default.
    $clear = $pdo->prepare(
        'UPDATE product_options SET is_default = 0
         WHERE product_id = :pid AND option_group = :grp'
    );
    $clear->execute([':pid' => $productId, ':grp' => $group]);

    $set = $pdo->prepare(
        'UPDATE product_options SET is_default = 1, is_active = 1
         WHERE id = :id AND product_id = :pid'
    );
    $set->execute([':id' => $optionId, ':pid' => $productId]);

    customcore_admin_option_normalize_group($pdo, $productId, $group);
}

/**
 * Summary counts for the advisory banner (rubric: ≥ 2 active options / product).
 *
 * @return array{active:int, total:int, groups:int, groups_without_default:list<string>}
 */
function customcore_admin_option_summary(PDO $pdo, int $productId): array
{
    $options = customcore_admin_options_for_product($pdo, $productId);

    $active = 0;
    $grouped = [];
    foreach ($options as $opt) {
        $grp = (string) $opt['option_group'];
        $grouped[$grp][] = $opt;
        if ((int) $opt['is_active'] === 1) {
            $active++;
        }
    }

    $groupsWithoutDefault = [];
    foreach ($grouped as $grp => $rows) {
        $hasActive = false;
        $hasDefault = false;
        foreach ($rows as $row) {
            if ((int) $row['is_active'] === 1) {
                $hasActive = true;
                if ((int) $row['is_default'] === 1) {
                    $hasDefault = true;
                }
            }
        }
        if ($hasActive && !$hasDefault) {
            $groupsWithoutDefault[] = $grp;
        }
    }

    return [
        'active' => $active,
        'total' => count($options),
        'groups' => count($grouped),
        'groups_without_default' => $groupsWithoutDefault,
    ];
}
