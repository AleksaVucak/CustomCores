<?php
/**
 * CustomCore — Server-Side Compatibility Checker (Commit 5.4).
 *
 * File responsibility:
 *   Accepts a JSON payload of component IDs, loads the active compatibility
 *   rules from the database, evaluates each rule against the selected
 *   components' attributes, and returns a per-rule result plus an overall
 *   status (compatible / warning / incompatible).
 *
 * Endpoint:
 *   POST api/compatibility-check.php
 *   Content-Type: application/json
 *
 * Request body:
 *   { "components": [1, 8, 15, 24, 28, 35, 40, 48, 54, 57] }
 *
 * Response (200):
 *   {
 *     "success": true,
 *     "status": "compatible" | "warning" | "incompatible",
 *     "results": [
 *       {
 *         "rule_code": "socket_match",
 *         "name": "CPU / Motherboard Socket",
 *         "status": "pass" | "warning" | "fail",
 *         "severity": "error" | "warning",
 *         "message": "..."
 *       },
 *       ...
 *     ]
 *   }
 *
 * Authentication requirements:
 *   None (public). The builder is available to guests.
 *
 * Security:
 *   - POST only.
 *   - Input validated: array of integers, max 20.
 *   - Only active components and rules are used.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

// ---------------------------------------------------------------------------
// Only accept POST
// ---------------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------------------------
// Parse and validate JSON body
// ---------------------------------------------------------------------------

$rawBody = file_get_contents('php://input');

if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Request body is empty.']);
    exit;
}

$payload = json_decode($rawBody, true);

if (!is_array($payload) || !isset($payload['components'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON. Expected {"components": [...]}.']);
    exit;
}

$componentIds = $payload['components'];

if (!is_array($componentIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '"components" must be an array of integers.']);
    exit;
}

$maxComponents = 20;
$cleanIds = [];

foreach ($componentIds as $id) {
    if (is_int($id) && $id > 0) {
        $cleanIds[] = $id;
    } elseif (is_string($id) && ctype_digit($id) && (int) $id > 0) {
        $cleanIds[] = (int) $id;
    }
}

$cleanIds = array_values(array_unique($cleanIds));

if (count($cleanIds) === 0) {
    echo json_encode([
        'success' => true,
        'status' => 'compatible',
        'results' => [],
    ]);
    exit;
}

if (count($cleanIds) > $maxComponents) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Too many components. Maximum is ' . $maxComponents . '.']);
    exit;
}

// ---------------------------------------------------------------------------
// Load selected components with all attributes
// ---------------------------------------------------------------------------

try {
    $pdo = customcore_pdo();

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
} catch (Throwable $exception) {
    http_response_code(500);
    $msg = customcore_is_debug() ? $exception->getMessage() : 'Failed to load components.';
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// Index components by category slug for rule evaluation.
/** @var array<string, array> $byCategory */
$byCategory = [];
foreach ($rows as $row) {
    $slug = (string) $row['category_slug'];
    $byCategory[$slug] = $row;
}

// ---------------------------------------------------------------------------
// Load active compatibility rules
// ---------------------------------------------------------------------------

try {
    $ruleStmt = $pdo->query(
        'SELECT id, rule_code, name, description, severity, config
         FROM compatibility_rules
         WHERE is_active = 1
         ORDER BY id ASC'
    );
    $rules = $ruleStmt->fetchAll();
} catch (Throwable $exception) {
    http_response_code(500);
    $msg = customcore_is_debug() ? $exception->getMessage() : 'Failed to load rules.';
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ---------------------------------------------------------------------------
// Evaluate each rule
// ---------------------------------------------------------------------------

$results = [];
$overallStatus = 'compatible'; // can escalate to 'warning' then 'incompatible'

foreach ($rules as $rule) {
    $ruleCode = (string) $rule['rule_code'];
    $ruleName = (string) $rule['name'];
    $ruleDesc = (string) $rule['description'];
    $ruleSeverity = (string) $rule['severity'];
    $config = json_decode((string) $rule['config'], true);

    if (!is_array($config)) {
        $config = [];
    }

    $compareType = (string) ($config['compare'] ?? '');
    $result = evaluateRule($compareType, $config, $byCategory, $rows);

    $ruleResult = [
        'rule_code' => $ruleCode,
        'name' => $ruleName,
        'status' => $result['status'], // 'pass', 'warning', 'fail', 'skip'
        'severity' => $result['effective_severity'] ?? $ruleSeverity,
        'message' => $result['message'],
    ];

    $results[] = $ruleResult;

    // Escalate overall status.
    if ($result['status'] === 'fail') {
        $severity = $result['effective_severity'] ?? $ruleSeverity;
        if ($severity === 'error') {
            $overallStatus = 'incompatible';
        } elseif ($severity === 'warning' && $overallStatus !== 'incompatible') {
            $overallStatus = 'warning';
        }
    } elseif ($result['status'] === 'warning' && $overallStatus === 'compatible') {
        $overallStatus = 'warning';
    }
}

echo json_encode([
    'success' => true,
    'status' => $overallStatus,
    'results' => $results,
]);

// ===========================================================================
// Rule evaluation functions
// ===========================================================================

/**
 * Evaluate a single compatibility rule.
 *
 * @param string $compareType The comparison method from the rule config.
 * @param array  $config      The full JSON config from the rule.
 * @param array  $byCategory  Components indexed by category slug.
 * @param array  $allRows     All selected component rows.
 * @return array{status:string, message:string, effective_severity?:string}
 */
function evaluateRule(string $compareType, array $config, array $byCategory, array $allRows): array
{
    switch ($compareType) {
        case 'equal':
            return ruleEqual($config, $byCategory);

        case 'form_factor_fits':
            return ruleFormFactorFits($config, $byCategory);

        case 'psu_sufficient':
            return rulePsuSufficient($config, $byCategory, $allRows);

        case 'gte':
            return ruleGte($config, $byCategory);

        case 'cooler_fits':
            return ruleCoolerFits($config, $byCategory);

        case 'csv_contains':
            return ruleCsvContains($config, $byCategory);

        default:
            return ['status' => 'skip', 'message' => 'Unknown rule type.'];
    }
}

/**
 * Rule: Two columns must be equal (socket_match, ram_type_match).
 */
function ruleEqual(array $config, array $byCategory): array
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
 * Rule: Motherboard form factor must fit inside the case.
 * Hierarchy: ATX > mATX > ITX (index determines what fits).
 */
function ruleFormFactorFits(array $config, array $byCategory): array
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

    // Normalize hierarchy to uppercase for comparison.
    $hierarchyUpper = array_map('strtoupper', $hierarchy);

    $boardIdx = array_search($boardFF, $hierarchyUpper, true);
    $caseIdx = array_search($caseFF, $hierarchyUpper, true);

    if ($boardIdx === false || $caseIdx === false) {
        return ['status' => 'skip', 'message' => 'Unknown form factor.'];
    }

    // The case index must be >= board index (larger case fits smaller/equal board).
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
 */
function rulePsuSufficient(array $config, array $byCategory, array $allRows): array
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

    // Sum wattage_estimate across ALL selected components (excluding PSU itself).
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
 */
function ruleGte(array $config, array $byCategory): array
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
 * Rule: Cooler fits in case (height for air, type support for liquid).
 */
function ruleCoolerFits(array $config, array $byCategory): array
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

    // Parse case supported types (CSV: "air,liquid" or "air").
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

    // Air cooler: check height.
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
 * Rule: Target value is contained in source CSV (storage_interface).
 */
function ruleCsvContains(array $config, array $byCategory): array
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
