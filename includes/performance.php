<?php
/**
 * CustomCore — Build performance scoring (Commit 5.8).
 *
 * File responsibility:
 *   Computes gaming, productivity, and upgrade-ceiling scores from selected
 *   components. Used by api/chart-data.php and server-rendered pages so the
 *   chart and text fallback stay in sync.
 *
 * Scoring model (1–100):
 *   Gaming        — weighted: GPU 50%, CPU 30%, RAM 10%, Storage 10%
 *   Productivity  — weighted: CPU 40%, RAM 25%, Storage 20%, GPU 15%
 *   Upgrade max   — same weights applied to the best active catalogue scores
 *                   in each category (catalogue ceiling for upgrade comparison)
 *
 * Only categories present in the build contribute; weights are re-normalized.
 */

declare(strict_types=1);

/**
 * Category slugs that contribute to performance charts.
 *
 * @return array<string, array{gaming:float, productivity:float}>
 */
function customcore_performance_weights(): array
{
    return [
        'cpu' => ['gaming' => 0.30, 'productivity' => 0.40],
        'gpu' => ['gaming' => 0.50, 'productivity' => 0.15],
        'ram' => ['gaming' => 0.10, 'productivity' => 0.25],
        'storage' => ['gaming' => 0.10, 'productivity' => 0.20],
    ];
}

/**
 * Weighted score from a map of category slug → score (1–100).
 *
 * @param array<string, int>                         $scoresBySlug
 * @param array<string, array{gaming:float, productivity:float}> $weights
 * @param string                                     $axis 'gaming' or 'productivity'
 */
function customcore_performance_weighted_score(array $scoresBySlug, array $weights, string $axis): int
{
    $totalWeight = 0.0;
    $weighted = 0.0;

    foreach ($weights as $slug => $axisWeights) {
        if (!isset($scoresBySlug[$slug]) || $scoresBySlug[$slug] <= 0) {
            continue;
        }
        $w = (float) ($axisWeights[$axis] ?? 0);
        if ($w <= 0) {
            continue;
        }
        $totalWeight += $w;
        $weighted += $w * (int) $scoresBySlug[$slug];
    }

    if ($totalWeight <= 0) {
        return 0;
    }

    return (int) max(0, min(100, round($weighted / $totalWeight)));
}

/**
 * Build performance report for selected component rows.
 *
 * @param PDO                    $pdo
 * @param array<int, array>      $componentRows Rows with category_slug, performance_gaming, performance_productivity
 * @return array{
 *   gaming:int,
 *   productivity:int,
 *   upgrade_gaming:int,
 *   upgrade_productivity:int,
 *   upgrade_headroom:int,
 *   by_category:array<int, array{slug:string, name:string, gaming:int|null, productivity:int|null}>,
 *   labels:array{gaming:string, productivity:string, upgrade:string}
 * }
 */
function customcore_performance_report(PDO $pdo, array $componentRows): array
{
    $weights = customcore_performance_weights();
    $currentGaming = [];
    $currentProd = [];
    $byCategory = [];

    foreach ($componentRows as $row) {
        $slug = (string) ($row['category_slug'] ?? '');
        if ($slug === '' || !isset($weights[$slug])) {
            continue;
        }

        $g = isset($row['performance_gaming']) && $row['performance_gaming'] !== null
            ? (int) $row['performance_gaming']
            : null;
        $p = isset($row['performance_productivity']) && $row['performance_productivity'] !== null
            ? (int) $row['performance_productivity']
            : null;

        if ($g !== null && $g > 0) {
            $currentGaming[$slug] = $g;
        }
        if ($p !== null && $p > 0) {
            $currentProd[$slug] = $p;
        }

        $byCategory[] = [
            'slug' => $slug,
            'name' => (string) ($row['category_name'] ?? $row['name'] ?? $slug),
            'component' => (string) ($row['name'] ?? ''),
            'gaming' => $g,
            'productivity' => $p,
        ];
    }

    $gaming = customcore_performance_weighted_score($currentGaming, $weights, 'gaming');
    $productivity = customcore_performance_weighted_score($currentProd, $weights, 'productivity');

    // Catalogue ceilings (best active parts per contributing category).
    $ceilingGaming = [];
    $ceilingProd = [];

    try {
        $ceilStmt = $pdo->query(
            "SELECT cc.slug,
                    MAX(c.performance_gaming) AS max_gaming,
                    MAX(c.performance_productivity) AS max_productivity
             FROM components c
             JOIN component_categories cc ON cc.id = c.component_category_id
             WHERE c.is_active = 1
               AND cc.slug IN ('cpu', 'gpu', 'ram', 'storage')
             GROUP BY cc.slug"
        );
        $ceilRows = $ceilStmt->fetchAll();
        foreach ($ceilRows as $crow) {
            $slug = (string) $crow['slug'];
            if ($crow['max_gaming'] !== null) {
                $ceilingGaming[$slug] = (int) $crow['max_gaming'];
            }
            if ($crow['max_productivity'] !== null) {
                $ceilingProd[$slug] = (int) $crow['max_productivity'];
            }
        }
    } catch (Throwable $exception) {
        $ceilingGaming = $currentGaming;
        $ceilingProd = $currentProd;
    }

    $upgradeGaming = customcore_performance_weighted_score($ceilingGaming, $weights, 'gaming');
    $upgradeProductivity = customcore_performance_weighted_score($ceilingProd, $weights, 'productivity');

    // Headroom: average remaining room toward catalogue ceiling (0 = at ceiling).
    $gaps = [];
    if ($upgradeGaming > 0) {
        $gaps[] = max(0, $upgradeGaming - $gaming);
    }
    if ($upgradeProductivity > 0) {
        $gaps[] = max(0, $upgradeProductivity - $productivity);
    }
    $upgradeHeadroom = $gaps !== [] ? (int) round(array_sum($gaps) / count($gaps)) : 0;

    return [
        'gaming' => $gaming,
        'productivity' => $productivity,
        'upgrade_gaming' => $upgradeGaming,
        'upgrade_productivity' => $upgradeProductivity,
        'upgrade_headroom' => $upgradeHeadroom,
        'by_category' => $byCategory,
        'labels' => [
            'gaming' => 'Gaming performance',
            'productivity' => 'Productivity performance',
            'upgrade' => 'Upgrade headroom (vs best catalogue parts)',
        ],
    ];
}

/**
 * Load component rows by IDs for performance scoring.
 *
 * @param int[] $componentIds
 * @return array<int, array>
 */
function customcore_performance_load_components(PDO $pdo, array $componentIds): array
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
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT c.id, c.name, c.performance_gaming, c.performance_productivity,
                cc.slug AS category_slug, cc.name AS category_name
         FROM components c
         JOIN component_categories cc ON cc.id = c.component_category_id
         WHERE c.id IN ($placeholders) AND c.is_active = 1"
    );
    $stmt->execute($cleanIds);

    return $stmt->fetchAll();
}
