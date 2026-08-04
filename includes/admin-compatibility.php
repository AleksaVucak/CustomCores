<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Administrator compatibility metadata helpers.
// Security-first helpers for editing the simplified compatibility metadata the
// PC Builder relies on: 1. Component attribute columns (socket, ram_type, form_factor, clearances,
// wattage, cooler type, storage interface, PSU wattage, performance scores), only the fields
// relevant to each builder category are exposed. 2. Compatibility rules (name, description,
// severity, active flag). The raw JSON `config` that wires a rule to component columns is shown
// read-only so admins can tune wording/severity without breaking the evaluator.
// Usage: require_once __DIR__. '/admin-compatibility.php';
// Security:
//   Every write uses PDO prepared statements.
//   Editable component columns come from a fixed allow-list keyed by category slug
//     (customcore_admin_compat_fields()); user input never names a column.
//   Values are validated/normalised per field type before persistence.

declare(strict_types=1);

if (!function_exists('customcore_app_config')) {
    require_once __DIR__ . '/functions.php';
}

/**
 * Attribute field definitions per component-category slug.
 *
 * Only fields listed for a category are rendered and updatable for its
 * components, so the editor stays focused on metadata that actually applies.
 * Each descriptor: ['col'=>string, 'label'=>string, 'type'=>'int|text|enum'
 * 'hint'=>?string, 'options'=>?list<string>, 'max'=>?int, 'maxlen'=>?int].
 *
 * @return array<string, list<array<string, mixed>>>
 */
function customcore_admin_compat_fields(): array
{
    $ff = ['', 'ATX', 'mATX', 'ITX'];

    return [
        'cpu' => [
            ['col' => 'socket', 'label' => 'Socket', 'type' => 'text', 'maxlen' => 50, 'hint' => 'e.g. AM5, LGA1700'],
            ['col' => 'wattage_estimate', 'label' => 'Power draw (W)', 'type' => 'int', 'max' => 2000, 'hint' => 'TDP estimate used for the PSU check'],
            ['col' => 'performance_gaming', 'label' => 'Gaming score', 'type' => 'int', 'max' => 100, 'hint' => '0 to 100'],
            ['col' => 'performance_productivity', 'label' => 'Productivity score', 'type' => 'int', 'max' => 100, 'hint' => '0 to 100'],
        ],
        'motherboard' => [
            ['col' => 'socket', 'label' => 'Socket', 'type' => 'text', 'maxlen' => 50, 'hint' => 'Must match CPU socket, e.g. AM5'],
            ['col' => 'ram_type', 'label' => 'RAM type', 'type' => 'enum', 'options' => ['', 'DDR4', 'DDR5']],
            ['col' => 'form_factor', 'label' => 'Form factor', 'type' => 'enum', 'options' => $ff, 'hint' => 'Board size'],
            ['col' => 'supported_storage', 'label' => 'Supported storage', 'type' => 'text', 'maxlen' => 100, 'hint' => 'Comma-separated, e.g. NVMe,SATA'],
            ['col' => 'wattage_estimate', 'label' => 'Power draw (W)', 'type' => 'int', 'max' => 2000],
        ],
        'gpu' => [
            ['col' => 'gpu_length_mm', 'label' => 'Card length (mm)', 'type' => 'int', 'max' => 1000, 'hint' => 'Compared to the case GPU clearance'],
            ['col' => 'wattage_estimate', 'label' => 'Power draw (W)', 'type' => 'int', 'max' => 2000],
            ['col' => 'performance_gaming', 'label' => 'Gaming score', 'type' => 'int', 'max' => 100],
            ['col' => 'performance_productivity', 'label' => 'Productivity score', 'type' => 'int', 'max' => 100],
        ],
        'ram' => [
            ['col' => 'ram_type', 'label' => 'RAM type', 'type' => 'enum', 'options' => ['', 'DDR4', 'DDR5'], 'hint' => 'Must match motherboard RAM type'],
            ['col' => 'wattage_estimate', 'label' => 'Power draw (W)', 'type' => 'int', 'max' => 500],
            ['col' => 'performance_gaming', 'label' => 'Gaming score', 'type' => 'int', 'max' => 100],
            ['col' => 'performance_productivity', 'label' => 'Productivity score', 'type' => 'int', 'max' => 100],
        ],
        'storage' => [
            ['col' => 'storage_interface', 'label' => 'Interface', 'type' => 'text', 'maxlen' => 30, 'hint' => 'e.g. NVMe or SATA (must be supported by the board)'],
            ['col' => 'wattage_estimate', 'label' => 'Power draw (W)', 'type' => 'int', 'max' => 200],
            ['col' => 'performance_gaming', 'label' => 'Gaming score', 'type' => 'int', 'max' => 100],
            ['col' => 'performance_productivity', 'label' => 'Productivity score', 'type' => 'int', 'max' => 100],
        ],
        'psu' => [
            ['col' => 'psu_wattage', 'label' => 'Rated wattage (W)', 'type' => 'int', 'max' => 3000, 'hint' => 'Must cover total build draw + headroom'],
        ],
        'case' => [
            ['col' => 'form_factor', 'label' => 'Max board form factor', 'type' => 'enum', 'options' => $ff, 'hint' => 'Largest board the case accepts'],
            ['col' => 'max_gpu_length_mm', 'label' => 'Max GPU length (mm)', 'type' => 'int', 'max' => 1000],
            ['col' => 'max_cooler_height_mm', 'label' => 'Max cooler height (mm)', 'type' => 'int', 'max' => 400],
            ['col' => 'cooler_type', 'label' => 'Supported cooling', 'type' => 'text', 'maxlen' => 20, 'hint' => 'Comma-separated, e.g. air,liquid'],
        ],
        'cooling' => [
            ['col' => 'cooler_type', 'label' => 'Cooler type', 'type' => 'enum', 'options' => ['', 'air', 'liquid']],
            ['col' => 'cooler_height_mm', 'label' => 'Cooler height (mm)', 'type' => 'int', 'max' => 400, 'hint' => 'Air coolers only'],
            ['col' => 'wattage_estimate', 'label' => 'Power draw (W)', 'type' => 'int', 'max' => 200],
        ],
        'os' => [
            ['col' => 'wattage_estimate', 'label' => 'Power draw (W)', 'type' => 'int', 'max' => 100],
        ],
        'service' => [],
    ];
}

/**
 * Field descriptors for a given category slug ([] when none/unknown).
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_compat_fields_for(string $slug): array
{
    $map = customcore_admin_compat_fields();

    return $map[$slug] ?? [];
}

/**
 * Builder component categories (for filters and edit context).
 *
 * @return list<array{id:int, name:string, slug:string}>
 */
function customcore_admin_compat_categories(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, name, slug FROM component_categories ORDER BY sort_order ASC, name ASC'
    );
    $rows = $stmt ? $stmt->fetchAll() : [];

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'slug' => (string) $r['slug'],
        ];
    }

    return $out;
}

/**
 * List components (active and inactive) for the admin table, optionally
 * filtered by category id and a name/brand search term.
 *
 * @param array{category_id?:int, search?:string} $filters
 * @return list<array<string, mixed>>
 */
function customcore_admin_compat_components(PDO $pdo, array $filters = []): array
{
    $sql =
        'SELECT c.id, c.name, c.brand, c.price, c.is_active,
                c.socket, c.ram_type, c.form_factor, c.gpu_length_mm,
                c.max_gpu_length_mm, c.cooler_height_mm, c.max_cooler_height_mm,
                c.cooler_type, c.storage_interface, c.supported_storage,
                c.psu_wattage, c.wattage_estimate,
                c.performance_gaming, c.performance_productivity,
                cc.name AS category_name, cc.slug AS category_slug, cc.sort_order
         FROM components c
         JOIN component_categories cc ON cc.id = c.component_category_id
         WHERE 1 = 1';
    $params = [];

    $categoryId = (int) ($filters['category_id'] ?? 0);
    if ($categoryId > 0) {
        $sql .= ' AND c.component_category_id = :cid';
        $params[':cid'] = $categoryId;
    }

    $search = isset($filters['search']) && is_string($filters['search']) ? trim($filters['search']) : '';
    if ($search !== '') {
        $sql .= ' AND (c.name LIKE :s_name OR c.brand LIKE :s_brand)';
        $params[':s_name'] = '%' . $search . '%';
        $params[':s_brand'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY cc.sort_order ASC, c.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Fetch one component with its category slug.
 *
 * @return array<string, mixed>|null
 */
function customcore_admin_compat_component_fetch(PDO $pdo, int $componentId): ?array
{
    if ($componentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, cc.name AS category_name, cc.slug AS category_slug
         FROM components c
         JOIN component_categories cc ON cc.id = c.component_category_id
         WHERE c.id = :id'
    );
    $stmt->execute([':id' => $componentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Validate component attribute input for a category's field set.
 *
 * @param array<string, mixed> $input Raw $_POST.
 * @param string $slug Category slug (selects the field allow-list).
 * @return array{
 * errors: array<string, string>
 * values: array<string, int|string|null>
 * is_active: int
 * }
 */
function customcore_admin_compat_validate_component(array $input, string $slug): array
{
    $errors = [];
    $values = [];

    foreach (customcore_admin_compat_fields_for($slug) as $field) {
        $col = (string) $field['col'];
        $type = (string) $field['type'];
        $raw = array_key_exists($col, $input) ? $input[$col] : '';
        $raw = is_string($raw) || is_int($raw) ? trim((string) $raw) : '';

        if ($type === 'int') {
            if ($raw === '') {
                $values[$col] = null; // NULL = not applicable / unspecified
                continue;
            }
            if (!preg_match('/^\d+$/', $raw)) {
                $errors[$col] = $field['label'] . ' must be a whole number (or blank).';
                continue;
            }
            $num = (int) $raw;
            $max = (int) ($field['max'] ?? 100000);
            if ($num > $max) {
                $errors[$col] = $field['label'] . ' must be ' . $max . ' or less.';
                $num = $max;
            }
            $values[$col] = $num;
        } elseif ($type === 'enum') {
            $options = $field['options'] ?? [''];
            $match = null;
            foreach ($options as $opt) {
                if (strcasecmp($raw, (string) $opt) === 0) {
                    $match = (string) $opt;
                    break;
                }
            }
            if ($match === null) {
                $errors[$col] = 'Choose a valid ' . strtolower((string) $field['label']) . '.';
                $values[$col] = null;
            } else {
                $values[$col] = $match === '' ? null : $match;
            }
        } else { // text
            $maxlen = (int) ($field['maxlen'] ?? 255);
            if (mb_strlen($raw) > $maxlen) {
                $errors[$col] = $field['label'] . ' must be ' . $maxlen . ' characters or fewer.';
                $raw = mb_substr($raw, 0, $maxlen);
            }
            $values[$col] = $raw === '' ? null : $raw;
        }
    }

    $isActive = !empty($input['is_active']) ? 1 : 0;

    return ['errors' => $errors, 'values' => $values, 'is_active' => $isActive];
}

/**
 * Update a component's compatibility attributes + active flag.
 *
 * Only the whitelisted columns for the component's category are written; all
 * other columns (name, price, performance where not shown, …) are untouched.
 *
 * @param array<string, int|string|null> $values Column => value (validated).
 */
function customcore_admin_compat_update_component(
    PDO $pdo,
    int $componentId,
    string $slug,
    array $values,
    int $isActive
): void {
    $allowed = [];
    foreach (customcore_admin_compat_fields_for($slug) as $field) {
        $allowed[(string) $field['col']] = true;
    }

    $sets = [];
    $params = [':id' => $componentId];
    foreach ($values as $col => $val) {
        if (!isset($allowed[$col])) {
            continue; // defence in depth: never write a non-whitelisted column
        }
        $sets[] = "`$col` = :$col";
        $params[":$col"] = $val;
    }

    $sets[] = 'is_active = :is_active';
    $params[':is_active'] = $isActive;

    $sql = 'UPDATE components SET ' . implode(', ', $sets) . ' WHERE id = :id';
    $pdo->prepare($sql)->execute($params);
}

/**
 * Toggle a component's active status (whether it appears in the builder).
 */
function customcore_admin_compat_set_component_active(PDO $pdo, int $componentId, bool $active): void
{
    $stmt = $pdo->prepare('UPDATE components SET is_active = :a WHERE id = :id');
    $stmt->execute([':a' => $active ? 1 : 0, ':id' => $componentId]);
}

/**
 * All compatibility rules, ordered for display.
 *
 * @return list<array<string, mixed>>
 */
function customcore_admin_compat_rules(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, rule_code, name, description, severity, config, is_active, updated_at
         FROM compatibility_rules
         ORDER BY id ASC'
    );

    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Fetch a single compatibility rule.
 *
 * @return array<string, mixed>|null
 */
function customcore_admin_compat_rule_fetch(PDO $pdo, int $ruleId): ?array
{
    if ($ruleId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM compatibility_rules WHERE id = :id');
    $stmt->execute([':id' => $ruleId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Validate rule editing input (name, description, severity, active).
 *
 * The programmatic rule_code and JSON config are intentionally not editable
 * here to keep the evaluator's wiring intact.
 *
 * @param array<string, mixed> $input
 * @return array{errors: array<string,string>, values: array{name:string, description:string, severity:string, is_active:int}}
 */
function customcore_admin_compat_validate_rule(array $input): array
{
    $errors = [];

    $name = isset($input['name']) && is_string($input['name']) ? trim($input['name']) : '';
    if ($name === '') {
        $errors['name'] = 'A rule name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors['name'] = 'Name must be 150 characters or fewer.';
        $name = mb_substr($name, 0, 150);
    }

    $description = isset($input['description']) && is_string($input['description']) ? trim($input['description']) : '';
    if ($description === '') {
        $errors['description'] = 'A description is required.';
    } elseif (mb_strlen($description) > 5000) {
        $errors['description'] = 'Description is too long.';
        $description = mb_substr($description, 0, 5000);
    }

    $severity = isset($input['severity']) && is_string($input['severity']) ? strtolower(trim($input['severity'])) : '';
    if (!in_array($severity, ['error', 'warning'], true)) {
        $errors['severity'] = 'Choose error or warning.';
        $severity = 'error';
    }

    $isActive = !empty($input['is_active']) ? 1 : 0;

    return [
        'errors' => $errors,
        'values' => [
            'name' => $name,
            'description' => $description,
            'severity' => $severity,
            'is_active' => $isActive,
        ],
    ];
}

/**
 * Update a compatibility rule's editable fields.
 *
 * @param array{name:string, description:string, severity:string, is_active:int} $values
 */
function customcore_admin_compat_update_rule(PDO $pdo, int $ruleId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE compatibility_rules
         SET name = :name, description = :description, severity = :severity, is_active = :is_active
         WHERE id = :id'
    );
    $stmt->execute([
        ':name' => $values['name'],
        ':description' => $values['description'],
        ':severity' => $values['severity'],
        ':is_active' => $values['is_active'],
        ':id' => $ruleId,
    ]);
}

/**
 * Toggle a compatibility rule's active status.
 */
function customcore_admin_compat_set_rule_active(PDO $pdo, int $ruleId, bool $active): void
{
    $stmt = $pdo->prepare('UPDATE compatibility_rules SET is_active = :a WHERE id = :id');
    $stmt->execute([':a' => $active ? 1 : 0, ':id' => $ruleId]);
}

/**
 * Human-readable display for a stored attribute value, or "Not set" when empty.
 */
function customcore_admin_compat_display(?string $value): string
{
    $value = $value === null ? '' : trim($value);

    return $value === '' ? 'Not set' : $value;
}
