<?php
/**
 * Aleksa Vucak
 * 110139920
 * COMP 3340, Final Project
 * August 5th, 2026
 */
// Shared compatibility evaluation.
// Loads active rules and selected component attributes, evaluates the seven seeded compatibility
// checks, and returns an overall status plus per-rule results. Used by api/compatibility-check.php
// (live JS) and builder-results.php (server-rendered summary) so both surfaces stay in sync.
// Access: None. Callers decide access control.
// Usage: require_once __DIR__. '/compatibility.php';
//   $report = customcore_compatibility_check($pdo, [1, 8, 15]); // $report = ['status' =>
//     'compatible'|'warning'|'incompatible', 'results' => [...]]

declare(strict_types=1);

/**
 * Run all active compatibility rules against a list of component IDs.
 *
 * @param PDO $pdo Active PDO connection.
 * @param int[] $componentIds Positive component primary keys.
 * @return array{status:string, results:array<int, array{rule_code:string, name:string, status:string, severity:string, message:string}>}
 */
function customcore_compatibility_check(PDO $pdo, array $componentIds): array
{
    $cleanIds = [];
    foreach ($componentIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $cleanIds[] = $id;
        }
    }
    $cleanIds = array_values(array_unique($cleanIds));

    if ($cleanIds === []) {
        return [
            'status' => 'compatible',
            'results' => [],
        ];
    }

    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $compStmt = $pdo->prepare(
        "SELECT c.id, c.component_category_id, cc.slug AS category_slug,
                c.name, c.price, c.wattage_estimate, c.socket, c.ram_type,
                c.form_factor, c.gpu_length_mm, c.max_gpu_length_mm,
                c.cooler_height_mm, c.max_cooler_height_mm, c.cooler_type,
                c.storage_interface, c.supported_storage, c.psu_wattage
         FROM components c
         JOIN component_categories cc ON cc.id = c.component_category_id
         WHERE c.id IN ($placeholders) AND c.is_active = 1"
    );
    $compStmt->execute($cleanIds);
    $rows = $compStmt->fetchAll();

    /** @var array<string, array> $byCategory */
    $byCategory = [];
    foreach ($rows as $row) {
        $byCategory[(string) $row['category_slug']] = $row;
    }

    $ruleStmt = $pdo->query(
        'SELECT id, rule_code, name, description, severity, config
         FROM compatibility_rules
         WHERE is_active = 1
         ORDER BY id ASC'
    );
    $rules = $ruleStmt->fetchAll();

    $results = [];
    $overallStatus = 'compatible';

    foreach ($rules as $rule) {
        $ruleSeverity = (string) $rule['severity'];
        $config = json_decode((string) ($rule['config'] ?? ''), true);
        if (!is_array($config)) {
            $config = [];
        }

        $compareType = (string) ($config['compare'] ?? '');
        $eval = customcore_compat_evaluate_rule($compareType, $config, $byCategory, $rows);

        $results[] = [
            'rule_code' => (string) $rule['rule_code'],
            'name' => (string) $rule['name'],
            'status' => $eval['status'],
            'severity' => $eval['effective_severity'] ?? $ruleSeverity,
            'message' => $eval['message'],
        ];

        if ($eval['status'] === 'fail') {
            $severity = $eval['effective_severity'] ?? $ruleSeverity;
            if ($severity === 'error') {
                $overallStatus = 'incompatible';
            } elseif ($severity === 'warning' && $overallStatus !== 'incompatible') {
                $overallStatus = 'warning';
            }
        } elseif ($eval['status'] === 'warning' && $overallStatus === 'compatible') {
            $overallStatus = 'warning';
        }
    }

    return [
        'status' => $overallStatus,
        'results' => $results,
    ];
}

/**
 * Evaluate a single compatibility rule.
 *
 * @param array<string, array> $byCategory
 * @param array<int, array> $allRows
 * @return array{status:string, message:string, effective_severity?:string}
 */
function customcore_compat_evaluate_rule(
    string $compareType,
    array $config,
    array $byCategory,
    array $allRows
): array {
    switch ($compareType) {
        case 'equal':
            return customcore_compat_rule_equal($config, $byCategory);

        case 'form_factor_fits':
            return customcore_compat_rule_form_factor($config, $byCategory);

        case 'psu_sufficient':
            return customcore_compat_rule_psu($config, $byCategory, $allRows);

        case 'gte':
            return customcore_compat_rule_gte($config, $byCategory);

        case 'cooler_fits':
            return customcore_compat_rule_cooler($config, $byCategory);

        case 'csv_contains':
            return customcore_compat_rule_csv($config, $byCategory);

        default:
            return ['status' => 'skip', 'message' => 'Unknown rule type.'];
    }
}

/**
 * Rule: two columns must be equal (socket_match, ram_type_match).
 *
 * @param array<string, array> $byCategory
 * @return array{status:string, message:string, effective_severity?:string}
 */
function customcore_compat_rule_equal(array $config, array $byCategory): array
{
    $sourceCat = (string) ($config['source_category'] ?? '');
    $sourceCol = (string) ($config['source_column'] ?? '');
    $targetCat = (string) ($config['target_category'] ?? '');
    $targetCol = (string) ($config['target_column'] ?? '');

    if (!isset($byCategory[$sourceCat]) || !isset($byCategory[$targetCat])) {
        return ['status' => 'skip', 'message' => 'Required components not yet selected.'];
    }

    $sourceVal = (string) ($byCategory[$sourceCat][$sourceCol] ?? '');
    $targetVal = (string) ($byCategory[$targetCat][$targetCol] ?? '');

    if ($sourceVal === '' || $targetVal === '') {
        return ['status' => 'skip', 'message' => 'Attribute data unavailable.'];
    }

    if (strcasecmp($sourceVal, $targetVal) === 0) {
        return [
            'status' => 'pass',
            'message' => ucfirst($sourceCol) . ' match: ' . $sourceVal . '.',
        ];
    }

    return [
        'status' => 'fail',
        'message' => ucfirst($sourceCat) . ' (' . $sourceVal . ') is incompatible with '
            . $targetCat . ' (' . $targetVal . ').',
    ];
}

/**
 * Rule: motherboard form factor must fit inside the case.
 *
 * @param array<string, array> $byCategory
 * @return array{status:string, message:string, effective_severity?:string}
 */
function customcore_compat_rule_form_factor(array $config, array $byCategory): array
{
    $sourceCat = (string) ($config['source_category'] ?? '');
    $sourceCol = (string) ($config['source_column'] ?? '');
    $targetCat = (string) ($config['target_category'] ?? '');
    $targetCol = (string) ($config['target_column'] ?? '');
    $hierarchy = $config['hierarchy'] ?? ['ITX', 'mATX', 'ATX'];

    if (!isset($byCategory[$sourceCat]) || !isset($byCategory[$targetCat])) {
        return ['status' => 'skip', 'message' => 'Required components not yet selected.'];
    }

    $boardFF = strtoupper(trim((string) ($byCategory[$sourceCat][$sourceCol] ?? '')));
    $caseFF = strtoupper(trim((string) ($byCategory[$targetCat][$targetCol] ?? '')));

    if ($boardFF === '' || $caseFF === '') {
        return ['status' => 'skip', 'message' => 'Form factor data unavailable.'];
    }

    $hierarchyUpper = array_map('strtoupper', $hierarchy);
    $boardIdx = array_search($boardFF, $hierarchyUpper, true);
    $caseIdx = array_search($caseFF, $hierarchyUpper, true);

    if ($boardIdx === false || $caseIdx === false) {
        return ['status' => 'skip', 'message' => 'Unknown form factor.'];
    }

    if ($caseIdx >= $boardIdx) {
        return [
            'status' => 'pass',
            'message' => $boardFF . ' motherboard fits in ' . $caseFF . ' case.',
        ];
    }

    return [
        'status' => 'fail',
        'message' => $boardFF . ' motherboard does not fit in ' . $caseFF . ' case.',
    ];
}

/**
 * Rule: PSU wattage must cover total estimated draw (+ headroom).
 *
 * @param array<string, array> $byCategory
 * @param array<int, array> $allRows
 * @return array{status:string, message:string, effective_severity?:string}
 */
function customcore_compat_rule_psu(array $config, array $byCategory, array $allRows): array
{
    $sourceCat = (string) ($config['source_category'] ?? 'psu');
    $sourceCol = (string) ($config['source_column'] ?? 'psu_wattage');
    $headroom = (int) ($config['headroom_percent'] ?? 20);
    $sevBelowTotal = (string) ($config['severity_below_total'] ?? 'error');
    $sevBelowHeadroom = (string) ($config['severity_below_headroom'] ?? 'warning');

    if (!isset($byCategory[$sourceCat])) {
        return ['status' => 'skip', 'message' => 'PSU not yet selected.'];
    }

    $psuWattage = (int) ($byCategory[$sourceCat][$sourceCol] ?? 0);

    if ($psuWattage <= 0) {
        return ['status' => 'skip', 'message' => 'PSU wattage data unavailable.'];
    }

    $totalDraw = 0;
    foreach ($allRows as $row) {
        if ((string) $row['category_slug'] === $sourceCat) {
            continue;
        }
        $totalDraw += (int) ($row['wattage_estimate'] ?? 0);
    }

    if ($totalDraw <= 0) {
        return ['status' => 'pass', 'message' => 'Estimated draw is negligible.'];
    }

    $headroomTarget = (int) ceil($totalDraw * (1 + $headroom / 100));

    if ($psuWattage >= $headroomTarget) {
        return [
            'status' => 'pass',
            'message' => $psuWattage . 'W PSU provides sufficient headroom for ~'
                . $totalDraw . 'W estimated draw.',
        ];
    }

    if ($psuWattage >= $totalDraw) {
        return [
            'status' => 'warning',
            'effective_severity' => $sevBelowHeadroom,
            'message' => $psuWattage . 'W PSU covers ' . $totalDraw
                . 'W draw but has less than ' . $headroom . '% headroom (recommend '
                . $headroomTarget . 'W+).',
        ];
    }

    return [
        'status' => 'fail',
        'effective_severity' => $sevBelowTotal,
        'message' => $psuWattage . 'W PSU is insufficient for ' . $totalDraw
            . 'W estimated draw.',
    ];
}

/**
 * Rule: source column >= target column (gpu_clearance).
 *
 * @param array<string, array> $byCategory
 * @return array{status:string, message:string, effective_severity?:string}
 */
function customcore_compat_rule_gte(array $config, array $byCategory): array
{
    $sourceCat = (string) ($config['source_category'] ?? '');
    $sourceCol = (string) ($config['source_column'] ?? '');
    $targetCat = (string) ($config['target_category'] ?? '');
    $targetCol = (string) ($config['target_column'] ?? '');

    if (!isset($byCategory[$sourceCat]) || !isset($byCategory[$targetCat])) {
        return ['status' => 'skip', 'message' => 'Required components not yet selected.'];
    }

    $sourceVal = (int) ($byCategory[$sourceCat][$sourceCol] ?? 0);
    $targetVal = (int) ($byCategory[$targetCat][$targetCol] ?? 0);

    if ($sourceVal <= 0 || $targetVal <= 0) {
        return ['status' => 'skip', 'message' => 'Clearance data unavailable.'];
    }

    if ($sourceVal >= $targetVal) {
        return [
            'status' => 'pass',
            'message' => 'Clearance OK (' . $sourceVal . 'mm available, '
                . $targetVal . 'mm needed).',
        ];
    }

    return [
        'status' => 'fail',
        'message' => 'Insufficient clearance: ' . $sourceVal . 'mm available but '
            . $targetVal . 'mm needed.',
    ];
}

/**
 * Rule: cooler fits in case (height for air, type support for liquid).
 *
 * @param array<string, array> $byCategory
 * @return array{status:string, message:string, effective_severity?:string}
 */
function customcore_compat_rule_cooler(array $config, array $byCategory): array
{
    $coolerCat = (string) ($config['source_category'] ?? 'cooling');
    $caseCat = (string) ($config['target_category'] ?? 'case');
    $heightColCooler = (string) ($config['height_column_cooler'] ?? 'cooler_height_mm');
    $heightColCase = (string) ($config['height_column_case'] ?? 'max_cooler_height_mm');
    $typeColCooler = (string) ($config['type_column_cooler'] ?? 'cooler_type');
    $typeColCase = (string) ($config['type_column_case'] ?? 'cooler_type');

    if (!isset($byCategory[$coolerCat]) || !isset($byCategory[$caseCat])) {
        return ['status' => 'skip', 'message' => 'Required components not yet selected.'];
    }

    $coolerType = strtolower(trim((string) ($byCategory[$coolerCat][$typeColCooler] ?? '')));
    $caseTypes = strtolower(trim((string) ($byCategory[$caseCat][$typeColCase] ?? '')));

    if ($coolerType === '' || $caseTypes === '') {
        return ['status' => 'skip', 'message' => 'Cooler type data unavailable.'];
    }

    $caseSupportedTypes = array_map('trim', explode(',', $caseTypes));

    if ($coolerType === 'liquid') {
        if (in_array('liquid', $caseSupportedTypes, true)) {
            return [
                'status' => 'pass',
                'message' => 'Case supports liquid cooling.',
            ];
        }

        return [
            'status' => 'fail',
            'message' => 'Case does not support liquid cooling radiators.',
        ];
    }

    if (!in_array('air', $caseSupportedTypes, true)) {
        return [
            'status' => 'fail',
            'message' => 'Case does not support air coolers.',
        ];
    }

    $coolerHeight = (int) ($byCategory[$coolerCat][$heightColCooler] ?? 0);
    $caseMaxHeight = (int) ($byCategory[$caseCat][$heightColCase] ?? 0);

    if ($coolerHeight <= 0 || $caseMaxHeight <= 0) {
        return ['status' => 'pass', 'message' => 'Height data unavailable; assumed OK.'];
    }

    if ($caseMaxHeight >= $coolerHeight) {
        return [
            'status' => 'pass',
            'message' => 'Cooler fits (' . $coolerHeight . 'mm in '
                . $caseMaxHeight . 'mm clearance).',
        ];
    }

    return [
        'status' => 'fail',
        'message' => 'Cooler too tall: ' . $coolerHeight . 'mm but case allows '
            . $caseMaxHeight . 'mm.',
    ];
}

/**
 * Rule: target value is contained in source CSV (storage_interface).
 *
 * @param array<string, array> $byCategory
 * @return array{status:string, message:string, effective_severity?:string}
 */
function customcore_compat_rule_csv(array $config, array $byCategory): array
{
    $sourceCat = (string) ($config['source_category'] ?? '');
    $sourceCol = (string) ($config['source_column'] ?? '');
    $targetCat = (string) ($config['target_category'] ?? '');
    $targetCol = (string) ($config['target_column'] ?? '');

    if (!isset($byCategory[$sourceCat]) || !isset($byCategory[$targetCat])) {
        return ['status' => 'skip', 'message' => 'Required components not yet selected.'];
    }

    $csvValue = (string) ($byCategory[$sourceCat][$sourceCol] ?? '');
    $needle = trim((string) ($byCategory[$targetCat][$targetCol] ?? ''));

    if ($csvValue === '' || $needle === '') {
        return ['status' => 'skip', 'message' => 'Interface data unavailable.'];
    }

    $supported = array_map('trim', explode(',', $csvValue));

    if (in_array($needle, $supported, true)) {
        return [
            'status' => 'pass',
            'message' => $needle . ' is supported by the motherboard.',
        ];
    }

    return [
        'status' => 'fail',
        'message' => $needle . ' is not supported. Motherboard supports: '
            . implode(', ', $supported) . '.',
    ];
}
